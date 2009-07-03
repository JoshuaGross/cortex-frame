<?php
/**
 * Sample config file for PHP-Frame and PHP-Frame based projects
 *
 * @started: October 17, 2006
 * @subversion: $Id: config.dist.php 63 2006-10-17 22:03:16Z jd $
 */

define('DB_SERVER', 'localhost');
define('DB_USER', 'jd');
define('DB_PASS', 'banana');
define('DB_NAME', 'sm');

// PHP-Frame config
define('PHPFRAME_ROOT_FILE', 'index.php');
define('PHPFRAME_ROOT_PATH', '/sm/');
define('PHPFRAME_ROOT_URL', 'http://localhost:8080');
define('PHPFRAME_TABLE_PREFIX', 'phpf_');

// Cookie config
define('PHPFRAME_COOKIE_LENGTH', 36000);
define('PHPFRAME_COOKIE_PATH', '/');
define('PHPFRAME_COOKIE_KEY', 'chutch_m0F0');
define('PHPFRAME_COOKIE_DOMAIN', '');
define('PHPFRAME_SESSION_ID_COOKIE', 'cortex_sm_session');
define('PHPFRAME_AUTOLOGIN_COOKIE', 'cortex_sm_autologin');

define('DEBUG_MODE', true);
?>
