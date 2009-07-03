<?php
/**
 * PHP-frame: a PHP framework for web applications.
 * User authentication routines and logging in/out.
 *
 * Basic information:
 * -- A user can only have one active session; in other words, two people cannot be logged into the
 *    same account at the same time.
 * -- All cookie data is encrypted when possible
 * -- Optional: Once a user's IP changes, all old sessions become invalid (but the user may still be auto-logged-in)
 *
 * TODO:
 * -- I want to lose reliance of cookies and be able to fall back on URL-passed session IDs. The functions.php
 *    build_url() function will detect cookies, and if none are found but a session_id exists, the session_id
 *    will be passed via the URL.
 *
 * @started: 07/15/2005
 * @copyright: Copyright (c) 2005, JPortal, All Rights Reserved
 * @website: www.jportalhome.com
 * @license: GPL
 * @subversion: $Id: auth.php 120 2008-10-10 00:49:09Z josh $
 */
 
// Security
if (!defined('IN_PHPFRAME'))
{
	exit;
}

/**
 * Initializes an auth session.
 * Checks user's cookie to see if auth data is present, and sees if user is logged in; then checks if user
 * can automatically be logged in.
 * If the first argument is true, sessions will be bound to one IP address.
 */
function auth_init ($bind_by_ip = false)
{
	global $db, $user_ip;

	// Array of user data will be placed here
	$user_data = false;
	
	// Get session cookie
	$cookie_session_id = get_input($_COOKIE, PHPFRAME_SESSION_ID_COOKIE, '');
	
	// If session cookie is present, decode it and verify it 
	if ($cookie_session_id)
	{
		$cookie_session_id = auth_decode($cookie_session_id);

		// Try to fetch session from session table
		$db->set_query('SELECT u.* FROM ' . SESSIONS_TABLE . ' s, ' . USERS_TABLE . ' u
			WHERE u.user_session_id = :1
				' . ($bind_by_ip ? 'AND s.user_ip = :2' : '') . '
				AND s.session_id = u.user_session_id');
		$session_result = $db->execute($cookie_session_id, $user_ip);
		$user_data = $db->fetch_row($session_result);
	}
	
	// If user_data is not present yet, see if we can automatically log user in
	if (!$user_data)
	{
		$user_data = auth_auto_login();
	}
	
	// If user data is present, renew cookies
	if ($user_data)
	{
		auth_renew_cookies($cookie_session_id);
	}
	
	return $user_data;
}

/**
 * Tries to automatically log a user in.
 */
function auth_auto_login ()
{
	global $user_ip, $db;
	
	// Get the autologin cookie
	$autologin_cookie = get_input($_COOKIE, PHPFRAME_AUTOLOGIN_COOKIE, '');

	// If autologin cookie is present, decode it and verify it
	if ($autologin_cookie)
	{
		$autologin_cookie = auth_decode($autologin_cookie);

		// Extract user_id and autologin hash
		$user_id = get_input($autologin_cookie, 'user_id', 0);
		$autologin_hash = get_input($autologin_cookie, 'autologin_hash', '');

		// Make sure cookie has required information
		if ($user_id && $autologin_hash)
		{
			// Get user data matching user_id in autologin cookie
			$db->set_query('SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = :1');
			$result = $db->execute($user_id);
			$user_data = $db->fetch_row($result);
			
			// Is autologin hash correct?
			if ($autologin_hash == phpframe_hash($user_data[PHPFRAME_USERNAME_FIELD].$user_data['user_password']))
			{
				// Log user in
				auth_login($user_data[PHPFRAME_USERNAME_FIELD], $user_data['user_password'], true);
				
				// Return user's data
				return $user_data;
			}
		}
	}
}

/**
 * Tries to log a user in.
 */
function auth_login ($username, $password, $auto_login)
{
	global $user_ip, $db;
	
	// Are username/password correct?
	$db->set_query('SELECT * FROM ' . USERS_TABLE . '
		WHERE '.PHPFRAME_USERNAME_FIELD.' = :1 AND user_password = :2');
	$result = $db->execute($username, $password);
	
	if ($user_data = $db->fetch_row($result))
	{
		// Create a new session ID
		$session_id = phpframe_hash(str_shuffle(phpframe_hash($username . $password . $user_ip . $auto_login . time() . rand())));
		
		// Create a new session
		$db->set_query('INSERT INTO ' . SESSIONS_TABLE . ' (session_id, user_id, user_ip) VALUES (:values:)');
		$db->execute($session_id, $user_data['user_id'], $user_ip);
		
		// Set session ID in user table
		$db->set_query('UPDATE ' . USERS_TABLE . ' SET user_session_id = :1 WHERE user_id = :2');
		$db->execute($session_id, $user_data['user_id']);
	
		// Set session ID cookie
		auth_renew_cookies($session_id);
		
		// Set autologin cookie
		if ($auto_login)
		{
			$autologin_cookie = auth_encode(array('user_id' => $user_data['user_id'], 'autologin_hash' => phpframe_hash($username.$password)));
			setcookie(PHPFRAME_AUTOLOGIN_COOKIE, $autologin_cookie, time() * 2, PHPFRAME_COOKIE_PATH, PHPFRAME_COOKIE_DOMAIN);
		}
		
		// Sweet, everything's good
		return $user_data;
	}
	else
	{
		// Username or password incorrect
		return false;
	}
}

/**
 * Log a user out and delete all session data.
 */
function auth_log_out ($user_data)
{
	global $db;
	
	// Remove session data from session table
	$db->set_query('DELETE FROM ' . SESSIONS_TABLE . ' WHERE session_id = :1');
	$db->execute($user_data['user_session_id']);

	// Delete cookies
	setcookie(PHPFRAME_AUTOLOGIN_COOKIE, '', -3600, PHPFRAME_COOKIE_PATH, PHPFRAME_COOKIE_DOMAIN);
	setcookie(PHPFRAME_SESSION_ID_COOKIE, '', -3600, PHPFRAME_COOKIE_PATH, PHPFRAME_COOKIE_DOMAIN);
}

/**
 * Renews session cookies to keep session alive.
 */
function auth_renew_cookies ($session_id)
{
	setcookie(PHPFRAME_SESSION_ID_COOKIE, auth_encode($session_id), time() + PHPFRAME_COOKIE_LENGTH, PHPFRAME_COOKIE_PATH, PHPFRAME_COOKIE_DOMAIN);
}

/**
 * Encodes authorization data to be stored in cookies
 */
function auth_encode ($data)
{
	$data = serialize($data);
	
	if (function_exists('mcrypt_module_open'))
	{
		// Use triple-DES to encrypt session data
		$td = mcrypt_module_open('tripledes', '', 'ecb', '');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, PHPFRAME_COOKIE_KEY, $iv);
		$data = mcrypt_generic($td, $data);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
	}

	return $data;
}

/**
 * Decodes authorization data stored in cookies
 */
function auth_decode ($data)
{
	// Use triple-DES to decrypt session data
	if (function_exists('mcrypt_module_open'))
	{
		$td = mcrypt_module_open('tripledes', '', 'ecb', '');
		$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, PHPFRAME_COOKIE_KEY, $iv);
		$data = mdecrypt_generic($td, $data);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
	}

	// Unserialize and return data
	$data = unserialize($data);
	return $data;
}

/**
 * Hash
 */
function phpframe_hash ($text)
{
	switch (PHPFRAME_PASSWORD_HASH)
	{
		case '':
			return $text;
		default:
		case 'md5':
			return md5($text);
	}
}
?>
