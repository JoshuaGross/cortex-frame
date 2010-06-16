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
	/* BEGIN CONFIG */

	/**
	 * This is a temporarily unsafe feature; turn it off if you are going
	 *   to accept template code from end-users. 
	 * It allows, inside an INCLUDE directive, references "../" to the parent
	 *   directory.
	 */
	var $allow_include_backdir = true;

	/**
	 * If a file cannot be found within an INCLUDE directive, execution of the
	 * template will immediately end and an error message will be shown.
	 */
	var $strict_include_mode = true;

	/* END CONFIG */

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
	 * Use this feature sparingly: array of custom tags
	 * TODO: document and clean up this feature
	 */
	var $tags = array();

	/**
	 * this is used by the compiler to count number of
	 *  DEFINE nesting blocks.
	 */
	var $compiler_inside_define = 0;
	
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
	 * TODO: document and clean up this feature
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

		// Do we have ../ or other paths at the beginning?
		// TODO: allow sandboxing of some sort
		// Added 6/8/2010
		if ($this->allow_include_backdir && preg_match('#^(([^/]+/)+)([^/]+)$#si', $file_name, $match))
		{
			preg_match('#^(([^/]+/)+)([^/]*)$#si', $this->root_tpl_prefix, $rootmatch);
			$file_name = $rootmatch[1] . $match[1] . $rootmatch[3] . $match[3];
		}
		else
		{
			$file_name = $this->root_tpl_prefix . $file_name;
		}

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
		// Warnings added 6/8/2010
		$code = preg_replace('#\<\?(php)?(.*?)\?\>#si', '<!-- Template warning: PHP code removed -->', $code);
		$code = preg_replace('#\<\%(.*?)\%\>#si', '<!-- Template warning: PHP code removed -->', $code);
		$code = preg_replace('#\<script[^>]*php[^>]*\>.*?\</script\>#si', '<!-- Template warning: PHP code removed -->', $code);

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
		// New regex 6/8/2010: nested variables: {u_var_{username}_thing}
		$directive_blocks = array();
		$directive_regex = '#<!-- ([a-zA-Z]+) (([a-zA-Z0-9\'"\.\_ /]|\{[a-zA-Z0-9\'"\.\_ /]+\})+[^ ])?[ ]?-->#s';
		preg_match_all($directive_regex, $code, $directive_blocks);
		$plaintext_blocks = preg_split($directive_regex, $code);
		// Old matching code (before 6/8/2010)
		// Did not allow nested variable matching
		//preg_match_all('#<!-- ([a-zA-Z]+) ([a-zA-Z0-9\'"\.\_ /]+[^ ])?[ ]?-->#s', $code, $directive_blocks);
		//$plaintext_blocks = preg_split('#<!-- ([a-zA-Z]+) ([a-zA-Z0-9\'"\.\_ /]+[^ ])?[ ]?-->#s', $code);
		
		// Parse through blocks
		$compiled_blocks = array();
		$num_plaintext_blocks = count($plaintext_blocks);
		for ($i = 0; $i < $num_plaintext_blocks; $i++)
		{
			// Add parsed plaintext block to compiled blocks array
			$text_block = array_shift($plaintext_blocks);
			if (strpos($text_block,	'{') !== false)
			{
				$text_block = $this->parse_tpl_variables($text_block, false, false, $tpl_reference);
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
							$replacement = '<?php $'.$tpl_reference.'->output($'.$tpl_reference.'->template_files["'.$include_file.'"], false, '.($strip_whitespace ? 'true' : 'false').'); ?>'."\n";
						}
						else if ($this->template_exists($include_file))
						{
							$replacement = '<?php $'.$tpl_reference.'->set_file("'.$include_file.'","'.$include_file.'"); $'.$tpl_reference.'->output("'.$include_file.'", false, '.($strip_whitespace ? 'true' : 'false').'); ?>'."\n";
						}
						else
						{
							$replacement = '<!-- ERROR; ' . $include_file . ' does not exist! -->';
							// Bails out at this point if strict mode is on
							if ($this->strict_include_mode)
							{
								eval((function_exists('show_error' ? 'show_error' : 'die').'(\'Failed at including template: \' . $include_file)');
								exit;
							}
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

						// Extra error checking: this can be removed to reduce
						// generated code. Added to make debugging templates much easier.
						// show_error is for CodeIgniter.
						$error_function = function_exists('show_error') ? 'show_error' : 'print';
						$extra_framework_code = "if (!isset(\$this->tpl_vars$looping_array_path)) { $error_function('Tried to LOOP over undefined variable in the template: $looping_var'); } else {";
												
						// This is the start of the loop
						$compiled_blocks[] =
							"<?php "
								. $extra_framework_code
								. "$i_var_count = 1; foreach (\$this->tpl_vars$looping_array_path as $i_var => \$useless)"
								. "{"
									/* if statement in case there's bad data in an array */
									. "if (is_array(\$this->tpl_vars$looping_array_path"."[$i_var])) {"
									. "\$this->tpl_vars$looping_array_path"."[$i_var]['row'] = $i_var_count; ++$i_var_count;"
									. "?>";
						break;

					case 'ENDLOOP':

						// Extra error checking
						$extra_framework_code = '}}';

						$compiled_blocks[] = "<?php } $extra_framework_code ?>";
						break;						
						
					// Unknown directive; probably just an HTML comment
					default:
						$compiled_blocks[] = $directive_blocks[0][$i];
						break;
				}
			}
		}

		$tpl_reference = ($this->compiler_inside_define ? 'tpl_in' : 'this');

		// Join array of compiled blocks
		$compilation_result = join('', $compiled_blocks);

		// We now have compiled code EXCEPT for custom tags...
		// Regex from: http://kevin.deldycke.com/2007/03/ultimate-regular-expression-for-html-tag-parsing-with-php/
		//$html_regex = "/<\/?\w+((\s+(\w|\w[\w-]*\w)(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)\/?".">/i";
		$tag_html_regex = "/<tpl\:(\w+)((\s+(\w|\w[\w-]*\w)(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)\/?".">/i";
		preg_match_all($tag_html_regex, $compilation_result, $matches);
		foreach ($matches[0] as $k=>$v)
		{
			// matches[0]: whole tag
			// matches[1]: tag name
			// matches[2]: parameters
			$tag_name = $matches[1][$k];

			// Construct parameter code
			$param_regex = "/(\s|^)*(\w|\w[\w-]*\w)(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))"."/i";
			preg_match_all($param_regex, $matches[2][$k], $matches_params);
			$param_code_begin = '';
			$param_code_end = '';
			foreach ($matches_params[0] as $j=>$w)
			{
				// index 2: param name
				// index 3: param value
				$param_name = '\$'.$tpl_reference.'->tpl_vars[\'tag_'.$matches_params[2][$j].'\']';
				$param_code_begin .= $param_name.$matches_params[3][$j].';';
				$param_code_end .= 'unset('.$param_name.');';
			}

			$error_function = function_exists('show_error') ? 'show_error' : 'print';
			$error_function = 'print';
			$compilation_result = preg_replace('#'.preg_quote($matches[0][$k]).'#', "<"."?php if (isset(\$".$tpl_reference."->tags['$tag_name'])) { if (function_exists(\$".$tpl_reference."->tags['$tag_name'])) { $param_code_begin call_user_func(\$".$tpl_reference."->tags['$tag_name'], \$$tpl_reference); $param_code_end } else { $error_function('Internal template error: tpl:$tag_name does not have a valid callback function, should be '.\$".$tpl_reference."->tags['$tag_name']); } } else { $error_function('$tag_name is not a valid custom template tag.'); } ?".">", $compilation_result);
		}

		//print htmlspecialchars($compilation_result);
		//$exp = explode("\n", $compilation_result);
		//print 'CODE : '.$exp[1]."\n<br />";

		return $compilation_result;
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
	function parse_tpl_variables ($content, $in_inline = false, $out_inline = false, $tpl_reference = 'this')
	{
		// Depending on whether this code will be inserted into PHP or HTML,
		// parts of the regex and output will be different.
		if ($in_inline)
		{
			$regex_before = '([^_a-zA-Z0-9]|^)';
			$regex_after = '([^_a-zA-Z0-9]|$)';
		}
		else
		{
			$regex_before = '(\{)';
			$regex_after = '(\})';
		}
		if ($out_inline)
		{
			$php_before = (!$in_inline ? '\'.' : '');
			$php_after = (!$in_inline ? '.\'' : '');
		}
		else
		{
			$php_before = '<?php print(';
			$php_after = '); ?>';
		}

		$extended = "|(\{)[a-zA-Z0-9\_\.]+(\})";

		// Match all variable references
		$variables = array();
		preg_match_all("#$regex_before([a-zA-Z]([a-zA-Z0-9\_\.]+$extended)+)$regex_after#", $content, $variables);

		// Hackjob for nested variables
		// Look at each variable; if it is nested in another variable, process nesting first
		foreach ($variables[2] as $var_num => $var_ref)
		{
			foreach ($variables[2] as $var_num2 => $var_ref2)
			{
				//print "$var_ref -- $var_ref2<br />\n";
				if ($var_num != $var_num2 && strpos($var_ref2, '{'.$var_ref.'}') !== false && isset($variables[0][$var_num]))
				{
					$variables[0][] = $variables[0][$var_num];
					$variables[1][] = $variables[1][$var_num];
					$variables[2][] = $variables[2][$var_num];
					unset($variables[0][$var_num]);
					unset($variables[1][$var_num]);
					unset($variables[2][$var_num]);
				}
			}
		}
		//print_r($variables);
			
		foreach ($variables[2] as $var_num => $var_ref)
		{
			// Don't parse inline variables if they have quotes before them
			if ($in_inline && ($variables[1][$var_num] == '\'' || $variables[1][$var_num] == '"'))
			{
				continue;
			}

			if (strpos($var_ref, '{') !== false)
			{
				//print $var_ref . "\n<br />";
				$var_ref = $this->parse_tpl_variables($var_ref, false, true, $tpl_reference);
				//print $var_ref . "\n<br />";
			}
			
			// Look if we are looping any variables. We will parse out everything
			// until the last variable reference. Example:
			// 'DOWNLOADS.file.type' is parsed into 'DOWNLOADS.file' and 'type'.
			// TODO: make this work for nested variables: {f_something_{var}_else}
			$parsed_var_ref = '$'.$tpl_reference.'->tpl_vars';
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
			$parsed_var_ref = $php_before.'(isset('.$parsed_var_ref.') ? ' . $parsed_var_ref . ' : \'' . ($out_inline ? false : $var_ref) . '\')'.$php_after;
			$count = 0;
			$content = str_replace($variables[0][$var_num], $parsed_var_ref, $content, $count);
			//$content = preg_replace('#'.preg_quote($variables[0][$var_num]).'#si', $parsed_var_ref, $content, -1, $count);

			/*if (function_exists('show_error') && $count == 0)
			{
				//show_error('Template Error: no replacements made for '.$variables[0][$var_num].' =&gt;'.$parsed_var_ref);
				print('Template Error: no replacements made for '.$variables[0][$var_num].' =&gt;'.$parsed_var_ref)."\n<br/>";
				print htmlspecialchars($content)."\n<br /><br />\n\n";
			}*/
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
		$tpl_reference = ($this->compiler_inside_define ? 'tpl_in' : 'this');
		
		return $conditions;
	}
}
?>
