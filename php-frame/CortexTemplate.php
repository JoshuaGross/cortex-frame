<?php
/**
 * PHP-frame: a PHP framework for web applications.
 * Templating engine.
 * The syntax for the template engine parse was inspired by phpBB3.
 *
 * Usage:
 * Starting to use the CortexTemplate is as simple as doing this in your
 * controller:
 * $this->load->library('CortexTemplate');
 * $this->cortextemplate->output('index');
 *
 * This will output tpl_index.html from your root directory.
 * Check joshisgross.com for more documentation... soon!
 *
 * @package		CodeIgniter
 * @started: 07/11/2005
 * @copyright: Copyright (c) 2005-2009 Cortex Creations, LLC, All Rights Reserved
 * @website: www.joshisgross.com/projects/php-frame
 * @license: see MIT-LICENSE
 * @since		Version 1.0
 */

// Simple template engine for php-frame and CodeIgniter
class CortexTemplate
{
	/**
	 * Template file names; handle -> filename
	 */
	var $template_files = array();

	/**
	 * Cache file names; handle -> filename
	 */
	var $cache_files = array();
	
	/**
	 * Tpl prefix and postfix
	 */
	var $root_tpl_prefix = '';
	var $root_tpl_postfix = '';
	
	/**
	 * Template variables
	 */
	var $tpl_vars = array();
	
	/**
	 * This is an array containing information about open LOOPs. It is used
	 * during template compilation.
	 */
	var $loops = array();

	/**
	 * Cache directory.
	 */
	var $cache_directory = '';

	/**
	 * Filename prefix of cache files.
	 */
	var $cache_filename_prefix = '';

	/**
	 * URLs to be replaced in the HTML on-the-fly
	 */
	var $registered_urls = array();
	
	/**
	 * PHP 5 constructor.
	 *
	 * The first argument is the directory in which template files reside, and you must
	 * ALWAYS pass this argument.
	 * The next two arguments are optional. If you set them, templates will be cached after
	 * compilation. This will speed up your site or software, especially with large and complicated
	 * template files. The second argument is the directory in which cached files will be held: if
	 * this is blank, or the directory does not exist, templates will NOT be cached. The third argument
	 * is completely optional: the prefix to filenames of cached files. The default is "tpl_" and will
	 * be used if you do not provide an alternative.
	 *
	 * CodeIgniter users: pass arguments to the constructor with the load->library method.
	 * $this->load->library('CortexTemplate', 'tpl_prefix', 'cache_dir', 'cache_filename_prefix');
	 *
	 * It is recommended to leave these at default.
	 */
	function __construct ($tpl_prefix = 'tpl_', $cache_directory = '', $cache_filename_prefix = 'tpl_', $tpl_postfix = '.html')
	{
		$this->root_tpl_prefix = $tpl_prefix;
		$this->root_tpl_postfix = $tpl_postfix;
		if ($cache_directory && is_dir($cache_directory))
		{
			$this->cache_directory = $cache_directory;
			$this->cache_filename_prefix = $cache_filename_prefix;
		}
	}
	
	/**
	 * PHP 4 constructor. See description of PHP 5 constructor above.
	 */
	function Template ($tpl_prefix, $cache_directory = '', $cache_filename_prefix = 'tpl_')
	{
		$this->__construct($tpl_prefix, $cache_directory, $cache_filename_prefix);
	}

	/**
	 * Verifies that a template file exists. Modifies the passed file_name if
	 * correct files are found. Returns false if no file is found. Used internally
	 * and very useful externally also.
	 */
	function template_exists (&$file_name)
	{
		if (file_exists($file_name))
		{
			return true;
		}

		$file_name = $this->root_tpl_prefix . $file_name;

		if (file_exists($file_name))
		{
			return true;
		}

		$file_name .= $this->root_tpl_postfix;

		if (file_exists($file_name))
		{
			return true;
		}
		
		return false;
	}
	
	/**
	 * Creates a new file handle.
	 */
	function set_file ($file_handle, $file_name)
	{
		// Make sure file exists
		if (!$this->template_exists($file_name))
		{
			$message = 'Template view "' . htmlspecialchars($file_name) . '" does not exist.';

			// CodeIgniter
			if (function_exists('show_error'))
			{
				show_error($message);
				return;
			}

			die('<b>Template error</b> - ' . $message);
		}

		// Create handle
		$this->template_files[$file_handle] = $file_name;
	}
	
	/**
	 * Parse and output template.
	 */
	function output ($handle, $to_file = false, $strip_whitespace = false, $enable_log = false)
	{
		global $start_time, $db;

		// Make sure handle exists
		if (!isset($this->template_files[$handle]))
		{
			$this->set_file($handle, $handle);
		}
		
		// Set special template variables
		$this->set_var('page_generation_time', ($start_time ? sprintf('%.4f', getmicrotime() - $start_time) : ''));
		$this->set_var('num_queries', ($db && isset($db->num_queries) ? $db->num_queries : ''));
		
		// Log?
		if (defined('LOG_PERFORMANCE') && $enable_log && isset($db))
		{
			$db->execute_insert(PERFORMANCE_LOG_TABLE, array(
				'lpid' => (defined('LOG_PERFORMANCE_ID') ? LOG_PERFORMANCE_ID : 'none'),
				'log_time' => gmmktime(),
				'queries' => ($db && isset($db->num_queries) ? $db->num_queries : 0),
				'ex_time' => ($start_time ? sprintf('%.4f', getmicrotime() - $start_time) : 'NA'),
				'template' => $handle
			));
		}
		
		// Does cached template exist?
		// If not, compile template and (try to) cache it.
		if (!$this->cache_file_exists($handle))
		{
			$template_code = $this->compile($handle, $strip_whitespace);
			$this->cache_write($handle, $template_code);
		}
		// Template cached.
		else
		{
			// Output Content
			if ($to_file)
			{
				$file = fopen($this->root_tpl_prefix . $to_file, 'w');
				if (!$file)
				{
					return false;
				}
				
				ob_start();
			}
			$this->cache_execute($handle);
			if ($to_file)
			{
				$text = ob_get_contents();
				ob_end_clean();
				fwrite($file, $text);
				fclose($file);
				return true;
			}
			return;
		}
		
		// Output Content
		if ($to_file)
		{
			$file = fopen($this->root_tpl_prefix . $to_file, 'w');
			if (!$file)
			{
				return false;
			}
			
			ob_start();
		}
		eval(' ?>' . $template_code);
		if ($to_file)
		{
			$text = ob_get_contents();
			ob_end_clean();
			fwrite($file, $text);
			fclose($file);
			return true;
		}
	}

	/**
	 * Check if a template has been cached or not.
	 */
	function cache_file_exists ($handle)
	{
		if ($this->cache_directory)
		{
			$cache_file = $this->cache_directory. preg_replace('#[^a-zA-Z0-9_\-\.]#', '_', 
				$this->cache_filename_prefix . $this->template_files[$handle]);
			$this->cache_files[$handle] = $cache_file;

			if (file_exists($cache_file) && filemtime($cache_file) > filemtime($this->template_files[$handle]))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Write compiled code to the cache.
	 */
	function cache_write ($handle, $code)
	{
		if ($this->cache_directory)
		{
			touch($this->cache_files[$handle]);
			$cache_file_handle = fopen($this->cache_files[$handle], 'w');
			fwrite($cache_file_handle, $code);
			fclose($cache_file_handle);
		}
	}

	/**
	 * Execute compiled code from the cache.
	 */
	function cache_execute ($handle)
	{
		include($this->cache_files[$handle]);
		
	}
	
	/**
	 * Compile template directives.
	 */
	function compile ($handle, $strip_whitespace = false)
	{
		// Get raw template code
		if (isset($this->template_files[$handle]))
		{
			$template_file_handle = fopen($this->template_files[$handle], 'r');
			$code = fread($template_file_handle, filesize($this->template_files[$handle]));
			fclose($template_file_handle);
		}
		else
		{
			$code = $handle;
		}
				
		// Remove all PHP from code
		$code = preg_replace('#\<\?(php)?(.*?)\?\>#si', '', $code);
		$code = preg_replace('#\<\%(.*?)\%\>#si', '', $code);
		$code = preg_replace('#\<script[^>]*php[^>]*\>.*?\</script\>#si', '', $code);

		// Remove whitespace? (extraneous newlines/tabs)
		if ($strip_whitespace)
		{
			$code = preg_replace('#([\r\n])[\t\r\n]+#si', '$1', $code);
			$code = preg_replace('#([\t\r\n\r]+)([\r\n])#si', '$2', $code);
			$code = preg_replace('#[\r\n]+#si', ' ', $code);
		}
	
		// Fix issue with evaling < ?xml
		$code = str_replace('<?xml', '<?php print(\'<?\'); ?>xml', $code);

		// Replace registered URLs
		foreach ($this->registered_urls as $k=>$v)
		{
			$code = preg_replace('#\<\!\-\- url\:'.preg_quote($k).' \-\-\>\<a([^>]*)href\=([\'"])[^"\']*\2(.*?)\>#si', '<a href="'.$v.'"$1$3>', $code);
			$code = preg_replace('#\<\!\-\- url\:'.preg_quote($k).' \-\-\>\<form([^>]*)action\=([\'"])[^"\']*\2(.*?)\>#si', '<form action="'.$v.'"$1$3>', $code);
			$code = preg_replace('#\<\!\-\- url\:'.preg_quote($k).' \-\-\>\<input([^>]*)value\=([\'"])[^"\']*\2(.*?)\>#si', '<input value="'.$v.'"$1$3>', $code);
			//print $code;
		}

		// Find possible directives and split code into plaintext blocks
		$directive_blocks = array();
		preg_match_all('#<!-- ([a-zA-Z]+) ([a-zA-Z0-9\'"\.\_ /]+[^ ])?[ ]?-->#s', $code, $directive_blocks);
		$plaintext_blocks = preg_split('#<!-- ([a-zA-Z]+) ([a-zA-Z0-9\'"\.\_ /]+[^ ])?[ ]?-->#s', $code);
		
		// Parse through blocks
		$compiled_blocks = array();
		$num_plaintext_blocks = count($plaintext_blocks);
		for ($i = 0; $i < $num_plaintext_blocks; $i++)
		{
			// Add parsed plaintext block to compiled blocks array
			$text_block = array_shift($plaintext_blocks);
			if (strpos($text_block,	'{') !== false)
			{
				$text_block = $this->parse_tpl_variables($text_block);
			}
			$compiled_blocks[] = &$text_block;
			unset($text_block);

			// Parse a directive
			if (isset($directive_blocks[1][$i]))
			{
				switch ($directive_blocks[1][$i])
				{
					case 'INCLUDE':
						$include_file = preg_replace('#[^a-zA-Z0-9\_\-\./]#si', '', $directive_blocks[2][$i]);
						
						// Is this a handle for a previously set-file'd file?
						if (isset($this->template_files[$include_file]))
						{
							/* $replacement = '<?php $this->output("'.$this->template_files[$include_file].'"); ?>'."\n"; */
							$replacement = '<?php $this->output($this->template_files["'.$include_file.'"], false, '.($strip_whitespace ? 'true' : 'false').'); ?>'."\n";
						}
						else if ($this->template_exists($include_file))
						{
							$replacement = '<?php $this->set_file("'.$include_file.'","'.$include_file.'"); $this->output("'.$include_file.'", false, '.($strip_whitespace ? 'true' : 'false').'); ?>'."\n";
						}
						else
						{
							$replacement = '<!-- ERROR; ' . $include_file . ' does not exist! -->';
						}
						$compiled_blocks[] = $replacement;
						break;

					case 'IF':
						$compiled_blocks[] = '<?php if ('.$this->parse_if_conditions($directive_blocks[2][$i]).') { ?>';
						break;

					case 'ELSEIF':
						$compiled_blocks[] = '<?php } else if ('.$this->parse_if_conditions($directive_blocks[2][$i]).') { ?>';
						break;

					case 'ELSE':
						$compiled_blocks[] = '<?php } else { ?>';
						break;
						
					case 'ENDIF':
						$compiled_blocks[] = '<?php } ?>';
						break;
						
					case 'LOOP':
						// Get number of current open loops, that is the iteration number for this loop
						$iteration_num = count($this->loops);

						// These are internally-used variables (in the compiled template
						// PHP code) used only inside this looping construct
						$i_var = '$i'.$iteration_num;
						$i_var_count = '$i'.$iteration_num.'_count';
						
						// Get the name of the variable that we are looping through
						$looping_var = $directive_blocks[2][$i];

						// In case this is a nested loop, get the path to the array we're looping
						$looping_array_path = '';
						$chunks = array();
						if (preg_match('#^([a-zA-Z0-9\_\.]+)\.([a-zA-Z0-9\_]+)$#si', $looping_var, $chunks))
						{
							if (isset($this->loops[$chunks[1]]))
							{
								$looping_array_path .= $this->loops[$chunks[1]];
							}
						}
						else
						{
							$chunks = array(2 => $looping_var);
						}
						$looping_array_path .= "['" . $chunks[2] . "']";

						// Set template variable "var path" to get template variables
						// inside this loop.
						$this->loops[$looping_var] = $looping_array_path . "[$i_var]";

						// Extra error checking for CodeIgniter
						$extra_framework_code = '';
						if (function_exists('show_error'))
						{
							$extra_framework_code = "if (!isset(\$this->tpl_vars$looping_array_path)) { show_error('Tried to LOOP over undefined variable in the template: $looping_var'); } else {";
						}
												
						// This is the start of the loop
						$compiled_blocks[] =
							"<?php "
								. $extra_framework_code
								. "$i_var_count = 1; foreach (\$this->tpl_vars$looping_array_path as $i_var => \$useless)"
								. "{"
									. "\$this->tpl_vars$looping_array_path"."[$i_var]['row'] = $i_var_count; ++$i_var_count;"
									. "?>";
						break;

					case 'ENDLOOP':

						// Extra error checking for CodeIgniter
						$extra_framework_code = '';
						if (function_exists('show_error'))
						{
							$extra_framework_code = " } ";
						}

						$compiled_blocks[] = "<?php } $extra_framework_code ?>";
						break;						
						
					// Unknown directive; probably just an HTML comment
					default:
						$compiled_blocks[] = $directive_blocks[0][$i];
						break;
				}
			}
		}

		// Join array of compiled blocks and return result
		return join('', $compiled_blocks);
	}

	/**
	 * Sets a template variable.
	 */
	function set_var ($name, $value)
	{
		$this->set_var_recursive($name, $value, $this->tpl_vars, false);
	}
	function set_var_recursive ($name, $value, &$parent, $safe)
	{
		if ($parent == false)
		{
			$parent = &$this->tpl_vars;
		}

		if ($safe && !is_array($value))
		{
			$value = htmlspecialchars($value);
		}

		$parent[$name] = $value;

		// Send array size to template
		if (is_array($value))
		{
			$parent[$name.'_count'] = count($value);
			//print $name.'_count<br />';

			foreach ($value as $k=>$v)
			{
				$this->set_var_recursive($k, $v, $parent[$name], $safe);
			}
		}
	}

	/**
	 * Sets a template variable, and cleanses all input
	 */
	function set_var_safe ($name, $value)
	{
		$this->set_var($name, $value, $this->tpl_vars, true);
	}

	/**
	 */

	/**
	 * Backwards compatibility with old-school, pre-2006 PHP-Frame code
	 * DEPRECATED
	 */
	function set_tpl_var ($k, $v)
	{
		$this->set_var($k, $v);
	}

	/**
	 * Register URLs for on-the-fly replacement
	 */
	function register_urls ($associative)
	{
		foreach ($associative as $k=>$v)
		{
			$this->set_var("u_registered_url_$k", $v);
			$this->registered_urls[$k] = $v;
		}
	}
	
	/**
	 * Parse template variables in content.
	 */
	function parse_tpl_variables ($content, $inline = false)
	{
		// Depending on whether this code will be inserted into PHP or HTML,
		// parts of the regex and output will be different.
		if ($inline)
		{
			$regex_before = '([^a-zA-Z0-9]|^)';
			$regex_after = '([^a-zA-Z0-9]|$)';
			$php_before = '';
			$php_after = '';
		}
		else
		{
			$regex_before = '(\{)';
			$regex_after = '(\})';
			$php_before = '<?php print(';
			$php_after = '); ?>';
		}

		// Match all variable references
		$variables = array();
		preg_match_all("#$regex_before([a-zA-Z][a-zA-Z0-9\_\.]+)$regex_after#", $content, $variables);
		
		foreach ($variables[2] as $var_num => $var_ref)
		{
			// Don't parse inline variables if they have quotes before them
			if ($inline && ($variables[1][$var_num] == '\'' || $variables[1][$var_num] == '"'))
			{
				continue;
			}
			
			// Look if we are looping any variables. We will parse out everything
			// until the last variable reference. Example:
			// 'DOWNLOADS.file.type' is parsed into 'DOWNLOADS.file' and 'type'.
			$parsed_var_ref = '$this->tpl_vars';
			if (preg_match('#^([a-zA-Z0-9\_\.]+)\.([a-zA-Z0-9\_]+)$#si', $var_ref, $chunks))
			{
				if (isset($this->loops[$chunks[1]]))
				{
					$parsed_var_ref .= $this->loops[$chunks[1]];
				}
			}
			else
			{
				$chunks = array(2 => $var_ref);
			}
			$parsed_var_ref .= "['" . $chunks[2] . "']";

			// Complete variable reference and replace original reference with the new one
			$parsed_var_ref = $php_before.'(isset('.$parsed_var_ref.') ? ' . $parsed_var_ref . ' : "' . ($inline ? false : $var_ref) . '")'.$php_after;
			$content = str_replace($variables[0][$var_num], $parsed_var_ref, $content);
		}
		
		// Return parsed content
		return $content;
	}
	
	/**
	 * Parse template IF and ELSE IF condition syntax to safe executable PHP code.
	 */
	function parse_if_conditions ($conditions)
	{
		// Change keywords to PHP equivalents
		$find = array('AND','OR','NOT','MOD','GREATER THAN','LESS THAN', 'EQUALS', 'NOTEQUALS');
		$replace = array('&&','||','!','%','>','<', '==', '!=');
		foreach ($find as $k => $v)
		{
			$find[$k] = '#([^A-Za-z0-9]|^)'.$v.'([^A-Za-z0-9]|$)#';
			$replace[$k] = '$1' . $replace[$k] . '$2';
		}
		$conditions = preg_replace($find, $replace, $conditions);
		
		// Change variable references in original condition to PHP equivalents
		$conditions = $this->parse_tpl_variables($conditions, true);
		
		return $conditions;
	}
}
?>
