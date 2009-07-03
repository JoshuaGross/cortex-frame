<?php
/**
 * PHP-frame: a PHP framework for web applications.
 * Common functions file.
 *
 * @started: July 11, 2005
 * @copyright: Copyright (c) 2005-2008, Cortex Creations, LLC, All Rights Reserved
 * @subversion: $Id: functions.php 117 2008-03-05 16:49:47Z jd $
 */

// Security
if (!defined('IN_PHPFRAME'))
{
	exit;
}

// Hooks for build_url
$phpframe_build_url_hooks = array();

/**
 * Use this function to safely get input parameters (from $_GET, $_POST, etc).
 * The first argument is the array to fetch data from, IE $_GET. The second
 * argument is the name of the parameter. The third argument is the default
 * value to be returned if the parameter is not found. Note that any returned
 * value will have the same type as the default value; if the default value is
 * an integer, returned values will ALWAYS be integers.
 * We highly recommend the use of $_REQUEST instead of $_GET or $_POST.
 */
function get_input ($ary, $name, $default)
{
	if (isset($ary[$name]))
	{
		$return_value = $ary[$name];
		settype($return_value, gettype($default));
	}
	else
	{
		$return_value = $default;
	}
	return $return_value;
}

/**
 * Build an URL.
 * The first argument is the path  the base file, probably just index.php if you are linking to
 * an in-site page. If you link  a relative path, IE index.php then the absolute path to your site is
 * added  the beginning.
 * Then the $url_params are parsed. This should be an associative array of parameters  add to the URL.
 * This is good  use because (1) is is XHTML compliant and (2) it encodes all url parameters.
 * Building on the last sentence: the fourth argument will determine
 * whether or not output is XHTML compliant; you will need  turn it off
 * when sending URLs  the redirect function, for example.
 * Then, if the last argument is TRUE, the FULL URL will be returned,
 * including http://. It is turned off by default to keep output small.
 * Finally, a session ID (if one exists) is added to the path.
 */
function build_url ($path, $url_params = array(), $xhtml = true, $full_http_path = false)
{
	global $phpframe_build_url_hooks;

    // Call build_url hooks to modify path/url params
	foreach ($phpframe_build_url_hooks as $callback)
	{
		eval($callback.'($path, $url_params, $xhtml, $full_http_path);');
	}
	
	// Set root path if path is not absolute already
	if (!preg_match('#^(https?\://)#si', $path))
	{
		// Include root path to file, relative to top-level web directory? 
		if (!preg_match('#^/#', $path))
		{
			$path = (is_string($full_http_path) ? '/' : PHPFRAME_ROOT_PATH) . $path;
		}
		
		// Include http://?
		if ($full_http_path)
		{
			$path = ($full_http_path === true ? PHPFRAME_ROOT_URL : $full_http_path) . $path;
		}
	}
	
	// Add URL parameters
	$path .= build_url_params($url_params);
	
	// If it's XHTML, htmlspecialchar it
	// This is in case URLs have quotes in them which can break <a> tags
	if ($xhtml)
	{
		$path = htmlspecialchars($path);
	}
	
	return $path;
}

/**
 * Build an URL parameter list from an associative array
 */
function build_url_params ($url_params, $params = '')
{
	foreach ($url_params as $k=>$v)
	{
		$params .= (strlen($params) ? '&' : '?') . $k . '=' . urlencode($v);
	}
	
	return $params;
}

/**
 * Redirect to another url. If you are using build_url() with redirect,
 * as you should, make sure the third argument is false.
 */
function redirect ($url)
{
	// Behave as per HTTP/1.1 spec for cool webservers
	header('Location: ' . $url);

	// Redirect via an HTML form for un-cool webservers
	print(
		'<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'
		. '<html>'
			. '<head>'
				. '<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">'
				. '<meta http-equiv="refresh" content="0; url=' . $url . '">'
				. '<title>Redirecting...</title>'
			. '</head>'
			. '<body>'
				. 'If you are not redirected in 5 seconds, please click <a href="' . $url . '">here</a>.'
			. '</body>'
		. '</html>'
	);
	
	// Exit
	exit;
}

/**
 * This is a general replacement for die(). It will show
 * messages useful for debugging, and uses the PHP-Frame template
 * whenever possible.
 */
function message_exit ($msg_text = '', $msg_title = '', $err_line = '', $err_file = '')
{
	global $template;

	// Add file line and name to error message?
	if ($err_line != '' && $err_file)
	{
		$msg_text .= "<br />$err_file:$err_line";
	}
	
	// Show template?
	$template_file = 'information.html';
	if ($template && $template->template_exists($template_file))
	{
		$template->set_file('main', 'information.html');

		// Set tpl vars
		$template->set_var('page_title', $msg_title);
		$template->set_var('information_text', $msg_text);

		// Display template and exit
		$template->output('main');
	}
	// Show regular message
	else
	{
		printf("<b>%s</b><br /><br />%s", $msg_title, $msg_text);
	}

	exit;
}

/**
 * Easy way to confirm something, like destructive actions, or to easily ask the user a question.
 * Pass template filename as first argument. Pass base file name (for example, 'index.php') as
 * second argument, it will be used to build links.
 * When the user clicks on an option, the value of the option will be returned. For example, with
 * the default options, if the user clicks "Yes", 1 will be returned; if the user clicks "No", 0 will
 * be returned.
 *
 * Parameters will automatically be taken from $_REQUEST, and passed with whatever option the user
 * clicks on; no input is lost that you have on the page already.
 * "confirm" is the default name of the URL parameter that will be passed to the page,
 * but can be changed if it conflicts with existing parameters (see last argument).
 */
function prompt ($template_file, $url, $message, $options = array(1 => 'Yes', 0 => 'No'), $parameter_name = 'confirm')
{
	global $template;

	// Has confirmation page been displayed and confirmed already?
	if (isset($_REQUEST[$parameter_name]))
	{
		return $_REQUEST[$parameter_name];
	}
	
	// Build choices to send to template
	$choices = array();
	foreach ($options as $value => $option_label)
	{
		$option_url = build_url($url, $_POST + $_GET + array($parameter_name => $value));
		$choices[] = array('u_choice' => $option_url, 'label' => $option_label);
	}
	
	// Send all variables to template and output
	$template->set_tpl_var('choices', $choices);
	$template->set_tpl_var('message', $message);
	$template->set_tpl_var('page_title', 'Please Confirm');
	$template->set_file('main', $template_file);
	$template->output('main');
	exit;
}

/**
 * Calculate a percentage and optionally (default-ly) format as string
 *
 * percentage(75.67, 100) = "75.67%"
 * percentage(50, 200, false) = 25
 */
function percentage ($number, $percentageof, $make_string = true)
{
	// Don't divide by zero 
	if ($percentageof == 0)
	{
		return ($make_string ? 'N/A' : false);
	}
	
	$value = ($number / $percentageof) * 100;
	
	if ($make_string)
	{
		$value = sprintf('%0.2f%%', $value);
	}
	
	return $value;
}
?>
