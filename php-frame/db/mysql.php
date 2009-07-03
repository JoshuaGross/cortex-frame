<?php
/**
 * PHP-frame: a PHP framework for web applications.
 * MySQL database layer.
 *
 * @started: July 11, 2005
 * @copyright: Copyright (c) 2005-2008, Cortex Creations, All Rights Reserved
 * @website: www.cortex-creations.com
 * @license: see COPYING
 * @subversion: $Id: mysql.php 119 2008-09-11 20:14:08Z josh $
 */

// Security
if (!defined('IN_PHPFRAME'))
{
	exit;
}

global $phpframe_db_allow_errors, $phpframe_db_max_connection_retries;
if (!isset($phpframe_db_allow_errors))
{
	$phpframe_db_allow_errors = false;
}
if (!isset($phpframe_db_max_connection_retries))
{
	$phpframe_db_max_connection_retries = 1;
}

$mysql_repair_conditions = array(1016, 1022, 1024, 1026, 1030, 1033, 1034, 1035, 1062);

if (defined('USE_NUMERIC_BOOLEAN'))
{
	define('VALUE_FALSE', 0);
	define('VALUE_TRUE', 1);
}
else
{
	define('VALUE_FALSE', 'false');
	define('VALUE_TRUE', 'true');
}

class Database
{
	/**
	 * Database connection link
	 */
	var $connection = null;
	
	/**
	 * Current unparsed query stored here
	 */
	var $query = '';
	
	/**
	 * Query statistics
	 */
	var $num_queries = 0;
	var $queries = array();
	
	var $allow_error = false;
	
	/**
	 * Credentials
	 */
	var $server = '';
	var $user = '';
	var $pass = '';
	var $dbname = '';
	
	/**
	 * Database constructor.
	 */
	function __construct ($server, $user, $pass, $dbname)
	{
		if ($this->connection)
		{
			mysql_close($this->connection);
		}
		
		// Attempt connection
		$this->connection = mysql_connect($server, $user, $pass);
		//$this->connection = mysql_connect($server, $user, $pass, true);
		//$this->connection = mysql_pconnect($server, $user, $pass);
		
		// Did connection fail?
		if (!$this->connection)
		{
			message_exit(mysql_error(), 'MySQL Connection Failed', __FILE__, __LINE__);
		}

		// Can we use the database?
		if (!mysql_query('USE `' . $dbname . '`', $this->connection))
		{
			message_exit(mysql_error(), 'MySQL Database Usage Failed', __FILE__, __LINE__);
		}
		
		// Save values for reconnect
		$this->server = $server;
		$this->user = $user;
		$this->pass = $pass;
		$this->dbname = $dbname;
	}
	
	/**
	 * Database constructor for PHP 4
	 */
	function Database ($server, $user, $pass, $dbname)
	{
		$this->__construct($server, $user, $pass, $dbname);
	}
	
	/**
	 * Set the current query string.
	 */
	function set_query ($query = '')
	{
		$this->query = $query;
	}
	
	/**
	 * Append to the current query string.
	 */
	function append_query ($query = '')
	{
		$this->query .= $query;
	}
	
	/**
	 * Execute a query, substituting :1, :2, :3 etc in the current
	 * query string with the passed vars. Note: just pass all the vars as regular
	 * arguments. 
	 */
	function execute ()
	{
		global $phpframe_db_allow_errors, $phpframe_db_max_connection_retries, $phpframe_apc_cacheable_queries;
		
		// Increment number of queries
		$this->num_queries++;

		// Get arguments
		$params = func_get_args();

		// Parse SQL query
		$all_params = '';
		$all_params_len = 0;
		$query = $this->query;
		$replacements = array();
		$replacement_lengths = array();
		
		// Build keys and :values: list
		foreach ($params as $key => $value)
		{
			$value = $this->build_safe_value($value);
			$replacements[$key] = $value;
			$replacement_lengths[$key] = strlen($value);
			$all_params .= ($all_params_len ? ', ' : '') . $value;
			$all_params_len += $replacement_lengths[$key];
		}
		
		// Find all colons and try to do replacements
		for ($i = @strpos($query, ':'); $i !== false; $i = @strpos($query, ':', $i+1))
		{
			// Grab the key from the query using substr indexes
			$key = intval(substr($query, $i + 1, 2)) - 1;
			if (isset($replacements[$key]))
			{
				// use strlen($key + 1) because otherwise :10 leaves a zero in the string
				$query = substr_replace($query, $replacements[$key], $i, strlen($key + 1) + 1);
				$i += $replacement_lengths[$key];
			}
		}
		
		$query = str_replace(':values:', $all_params, $query);
		
		// Try to use the APC cache (if available)
		$apc_cache = false;
		if (
			function_exists('apc_fetch')
			&& isset($phpframe_apc_cacheable_queries)
			&& is_array($phpframe_apc_cacheable_queries)
			// Avoid iterating $phpframe_apc_cacheable_queries for non-select queries
			&& preg_match('#^select #si', $query)
		)
		{
			// Is this query in the whitelist?
			foreach ($phpframe_apc_cacheable_queries as $q)
			{
				if ($q == $query || stristr($query, $q))
				{
					$apc_cache = true;
					break;
				}
			}
			
			// Look for cached value
			if ($apc_cache)
			{
				$result = apc_fetch($query);
				
				if (!($result === false))
				{
					// Save query in debug mode
					if (defined('DEBUG_MODE'))
					{
						$this->queries[] = 'APC: ' . $query;
					}
					
					return $result;
				}
			}
			// If not cached, we fall through to the rest and cache after fetch
		}
		
		// Save query in debug mode
		if (defined('DEBUG_MODE'))
		{
			$this->queries[] = $query;
		}
		
		// Finally, execute parsed query
		$result = mysql_query($query, $this->connection);
		
		// Handle some errors
		$retries = 0;
		$log = false;
		$error_code = mysql_errno($this->connection);
		$error_body = mysql_error($this->connection);
		while (!$result && ($error = mysql_errno($this->connection)) && $retries++ < $phpframe_db_max_connection_retries)
		{
			$retry = true;
			switch ($error)
			{
				// ER_NO_DB_ERROR - no DB selected
				case 1046:
					// Reselect DB and retry query
					mysql_query("USE `$this->dbname`", $this->connection);
					break;
				case 2006: // CR_SERVER_GONE_ERROR
				case 2013: // CR_SERVER_LOST
					$this->__construct($this->server, $this->user, $this->pass, $this->dbname);
					break;
				default:
					$retry = false;
					$log = !($this->allow_error || $phpframe_db_allow_errors); //true;
			}
			
			// Retry query
			if ($retry)
			{
				$result = mysql_query($query, $this->connection);
			}
			// Don't retry queries when the error wasn't handled
			else
			{
				// Log the error
				if ($log)
				{
					$matches = array();
					$error_table = 'unknown';
					
					if (preg_match('#^insert into ([^ ]+)#si', $query, $matches))
					{
						$error_table = $matches[1];
					}
					else if (preg_match('#^update ([^ ]+)#si', $query, $matches))
					{
						$error_table = $matches[1];
					}
					else if (preg_match('#^delete from ([^ ]+)#si', $query, $matches))
					{
						$error_table = $matches[1];
					}
					else if (preg_match('#^select .*? from ([^ ]+)#si', $query, $matches))
					{
						$error_table = $matches[1];
					}
					
					$values = array
					(
						gmmktime(),
						$this->build_safe_value($error_table),
						$error_code,
						$this->build_safe_value("$query\n\n$error_body")
					);
					$values = implode(',', $values);
					mysql_query("INSERT INTO db_error_reports (time, fail_table, error_code, report) VALUES ($values)");
				}
				
				break;
			}
		}

		// Error?
		if (!$result)
		{
			if ($this->allow_error || $phpframe_db_allow_errors)
			{
				return false;
			}
			
			// In PHP 5 we can see where this was called from
			if (function_exists('debug_backtrace'))
			{
				$backtrace = debug_backtrace();
				$line = $backtrace[0]['line'];
				$file = $backtrace[0]['file'];
			}
			// We have to use our __FILE__ and __LINE__ :(
			else
			{
				$file = __FILE__;
				$line = __LINE__;
			}
			
			// Show error message
			if (defined('DEBUG_MODE') && defined('PLAINTEXT_ERROR_MESSAGES'))
			{
				print("MySQL Query Failed: $query\n\n$error_body\n\nAt line $line of $file.");
				exit;
			}
			message_exit((defined('DEBUG_MODE') ? '<i>'.htmlspecialchars($query).'</i><br /><br />' : '') . $error_body,
				'MySQL Query Failed', $line, $file);
		}
		
		// Cache value?
		if ($apc_cache)
		{
			$a_result = array();
			while ($row = $this->fetch_row($result))
			{
				$a_result[] = $row;
			}
			
			$a_result = array_reverse($a_result);
			apc_add($query, $a_result, 3600);
			return $a_result;
		}

		// Return result
		return $result;
	}
	
	/**
	 * Fetch row as an (associative) array from query result.
	 */
	function fetch_row (&$query_result, $no_type_mangle = false)
	{
		$row = false;
		if (is_resource($query_result))
		{
			$row = @mysql_fetch_assoc($query_result);
			
			// NOTE: for some reason, even numeric MySQL fields are _always_ strings
			// when they are passed to PHP. For that reason, we loop through all fields
			// of the row; if they only contain numbers we explicitly force it into an integer/float.
			if ($row)
			{
				foreach ($row as $k=>$v)
				{
					if (!$no_type_mangle && preg_match('#^([\.1-9][\.0-9]*|0)$#', $v) == 1)
					{
						$row[$k] = (float)$v;
					}
				}
			}
			// Free result if already fetched
			else
			{
				mysql_free_result($query_result);
			}
		}
		else if (is_array($query_result))
		{
			return array_pop($query_result);
		}
		return $row;
	}
	
	/**
	 * Find how many rows were affected by a query.
	 */
	function affected_rows ()
	{
		return @mysql_affected_rows($this->connection);
	}

	/**
	 * Get the value of the last automatically incremented PRIMARY KEY field.
	 */
	function last_insert_id ()
	{
		return @mysql_insert_id($this->connection);
	}
	
	/**
	 * Return array of all queries executed so far
	 */
	function get_queries ()
	{
		return $this->queries;
	}


	/*********************************************
	 * ALL QUERY BUILDING METHODS AFTER THIS POINT
	 ********************************************/
	
	/**
	 * Make a value safe to put in a query.
	 */
	function build_safe_value ($value)
	{
		if (is_array($value))
		{
			$new_value = '(';
			foreach ($value as $k=>$v)
			{
				$new_value .= ($new_value !== '(' ? ',' : '') . $this->build_safe_value($v);
			}
			return $new_value.')';
		}

		return (is_string($value)
			? "'".mysql_real_escape_string($value, $this->connection)."'"
			: (is_bool($value)
				? ($value ? VALUE_TRUE : VALUE_FALSE)
				: $value));
	}
	 
	/**
	 * Build an IN () clause for a WHERE statement. Pass an array
	 * of values to be put in the IN clause safely.
	 */
	function build_query_in ($values)
	{
		$values_formatted = '';
		foreach ($values as $v)
		{
			$values_formatted .= ($values_formatted ? ',' : '') . $this->build_safe_value($v);
		}
		return ' IN (' . $values_formatted . ') ';
	}

	/**
	 * Build and execute an INSERT statement.
	 */
	function execute_insert ($table, $data)
	{
		// Build list of fields
		$fields = implode(',', array_keys($data));

		// Build list of values
		$values = '';
		foreach ($data as $k=>$v)
		{
			$values .= (strlen($values) ? ',' : '') . $this->build_safe_value($v);
		}

		// Set and execute query
		$this->set_query('INSERT INTO ' . $table . ' ('.$fields.') VALUES ('.$values.')');
		$result = $this->execute();
		
		return ($result ? $this->last_insert_id() : $result);
	}
}
?>
