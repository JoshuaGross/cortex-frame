<?php
/**
 * PHP-frame: a PHP framework for web applications.
 * Security file. Does a few operations to ensure the script is safe.
 *
 * @started: 07/11/2005
 * @copyright: Copyright (c) 2005-2009 Cortex Creations, LLC, All Rights Reserved
 * @website: www.joshisgross.com/projects/php-frame
 * @license: see MIT-LICENSE
 */
 
// Basic security
define('IN_PHPFRAME', true);
error_reporting(E_ALL);
set_magic_quotes_runtime(0);

// Get user's IP address
$user_ip = (!empty($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : ((!empty($_ENV['REMOTE_ADDR'])) ? $_ENV['REMOTE_ADDR'] : getenv('REMOTE_ADDR'));

// Check if register_globals is on... if it is, unset all global variables.
// Note: I know $_REQUEST and $_COOKIE vars are set globally, I don't know about $_FILES.
if (@ini_get('register_globals') == '1' || strtolower(@ini_get('register_globals')) == 'on')
{
	// Loop through input vars, remove globals!
	foreach (array_merge($_REQUEST, $_FILES, $_COOKIE) as $k => $void)
	{
		unset($$k);
	}
}

// Un-slash all input variables.
// This may seem like an unsafe activity, but all
// vars need to be quoted manually when you use them in
// queries, etc anyway.
if (get_magic_quotes_gpc())
{
	$vars = array('_COOKIE', '_REQUEST', '_SESSION', '_GET', '_POST');
	foreach ($vars as $v)
	{
		if (isset($$v))
		{
			foreach ($$v as $k => $void)
			{
				if (gettype(${$v}[$k]) != 'array')
				{
					${$v}[$k] = stripslashes(${$v}[$k]);
				}
			}
		}
	}
}

/**
 * Sanitize input: safely removes all JavaScript and unsafe code from HTML. Does
 * not need to be called if you're using htmlspecialchars.
 */
function sanitize_html ($html)
{
	// Remove all non-printable characters. CR(0a), LF(0b), and TAB(09) are allowed
	// This prevents maliciousness like: <scr\0ipt>
	$html = str_replace("\0", '', $html); 
	$html = preg_replace('/([\x00-\x08][\x0b-\x0c][\x0e-\x20])/', '', $html); 

	// Remove HTML hex entities, and HTML decimal entities
	$html = preg_replace('/&#[xX](0{0,8}[a-fA-F1-9][a-fA-F0-9]+);?/e', 'chr(hexdec("$1"))', $html);
	$html = preg_replace('/&#(0{0,8}[1-9][0-9]+);?/e', 'chr("$1")', $html);

	// Find any JavaScript/unwanted tags and simply remove it
	// Removes any attribute starting with "on" or "xmlns"
	$find = array
	(
		// Unwanted tags
		'#<\s?(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)(.*?)>((.*?)</\\1>)?#si' => '$4',
		// Unwanted attributes
		'#<\s?([a-zA-Z0-9]*?)(.*?)\s?(on|xmlns)[a-zA-Z0-9]*\s*=\s*([ \'"`](.*?)|$)\s?>?#si' => '<$1$2$5>',
		// Unwanted URLs
		'#<\s?([a-zA-Z0-9]*?)(.*?)\s?(javascript|vbscript)\s*\:\s*([ \'"`](.*?)|$)\s?>?#si' => '<$1$2#$4>',
		// Unwanted CSS (only works for IE)
		'#<\s?([a-zA-Z0-9]*?)(.*?)\s?style\s?=\s?([ \'"`])(.*?)((behaviour|behavior|expression)\((.*?)\))(.*?)(\\3(.*?)|$)\s?>?#si' => '<$1$2$9>',
		'#<\s?([a-zA-Z0-9]*?)(.*?)\s?style\s?=\s?([ \'"`])(.*?)(script\s?\:\s?(.*?))(.*?)(\\3(.*?)|$)\s?>?#si' => '<$1$2$8>'
	);
	while ($find)
	{
		foreach ($find as $find_pattern => $replace)
		{
			if (preg_match($find_pattern, $html))
			{
				$html = preg_replace($find_pattern, $replace, $html);
			}
			else
			{
				unset($find[$find_pattern]);
			}
		}
	}

	return $html;
}
?>
