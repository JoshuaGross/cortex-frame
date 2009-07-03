<?php
/**
 * PHP-Frame: code related to time
 * 
 * @started: (DBS) Wednesday, March 15, 2006
 * @started: (PHP-Frame) Monday, December 4, 2006
 * @copyright: Copyright (c) 2005-2009 Cortex Creations, LLC, All Rights Reserved
 * @website: www.joshisgross.com/projects/php-frame
 * @license: see MIT-LICENSE
 */

// Security
if (!defined('IN_PHPFRAME'))
{
	exit;
}

// In the beginning, start code proper
// Bug alert: time begins AFTER some time has passed in index.php... gap theory?

// Time zones
global $time_zone_list;
$time_zone_list = array
(
	'IDLW'	=> array(-12, 'Dateline Standard Time', 'Eniwetok, Kwaialein'),
	'NT'	=> array(-11, 'Samoa Standard Time', 'Midway Island, Samoa'),
	'HST'	=> array(-10, 'Hawaii Standard Time', 'Hawaii'),
	'AKST'	=> array(-10, 'Alaskan Standard Time', 'Alaska'),
	'YST'	=> array(-9, 'Yukon Standard Time', 'Yukon, Canada'),
	'PST'	=> array(-8, 'Pacific Standard Time', 'Anchorage, Los Angeles, San Francisco, Seattle'),
	'MST'	=> array(-7, 'Mountain Standard Time', 'Denver, Edmonton, Salt Lake City, Santa Fe'),
	'MST-AZ'	=> array(-7, 'Mountain Standard Time, no DST', 'Arizona and Sonora, no DST'),
	'CST'	=> array(-6, 'Central Standard Time', 'Chicago, Guatemala, Mexico City'),
	'SK-UTC-6'	=> array(-6, 'Sasketchewan Time', 'Saskatchewan East, no DST'),
	'EST'	=> array(-5, 'Eastern Standard Time', 'Bogota, Lima, New York'),
	'AST'	=> array(-4, 'Atlantic Standard Time', 'Caracas, La Paz'),
	'NFT'	=> array(-3.5, 'Newfoundland Time', 'Newfoundland'),
);

// DST is so much effing fail - flip this when it needs to be
$time_zone_dst = true;

// Fix bugs and anything, force PHP to think in GMT time
putenv('TZ=GMT');
if (function_exists('date_default_timezone_set'))
{
	date_default_timezone_set('GMT');
}

// Get user's time zone and put it in the global namespace
global $time_zone, $time_zone_offset;
$time_zone = '';
$time_zone_offset = 0;
get_timezone();

/**
 * Get user's timezone data and set in global namespace
 */
function get_timezone ()
{
	global $user_data, $default_timezone, $time_zone, $time_zone_offset, $time_zone_list, $time_zone_dst;

	$time_zone = (empty($user_data['time_zone'])
		? (empty($default_timezone)
			? 0
			: $default_timezone)
		: $user_data['time_zone']);

	// TZ is valid...
	if (isset($time_zone_list[$time_zone]))
	{
		$time_zone_offset = $time_zone_list[$time_zone][0] * 3600;
	}
	// TZ not found/set in the array
	else
	{
		$time_zone = 'N/A';
	}
}

/**
 * Format a date - returns a string
 */
function format_date ($time, $format = 'F j, Y')
{
	global $time_zone_offset, $time_zone;

	// Get DST offset
	// NOTE: America-centric right now
	// Hawaii, Arizona (and neighboring Sonora), Aleutia do NOT observe DST
	//
	// http://en.wikipedia.org/wiki/History_of_time_in_the_United_States
	// http://en.wikipedia.org/wiki/Daylight_saving_time_around_the_world
	$dst_offset = 0;
	if ($time_zone == 'CST' || $time_zone == 'MST' || $time_zone == 'PST' || $time_zone == 'EST')
	{
		$year = date('Y', $time);

		// Between 1967-1985
		if ($year >= 1967 && $year < 1986 && ($year != 1974 && $year != 1975))
		{
			$dst_start = find_day_of_month('last', 'Sun', 'Apr', $year);
			$dst_end = find_day_of_month('last', 'Sun', 'Oct', $year);
		}
		// 1974-1975
		else if ($year >= 1974 && $year <= 1975)
		{
			$dst_start = find_day_of_month(1, 'Sun', 'Jan', $year);
			$dst_end = find_day_of_month('last', 'Sun', 'Feb', $year+1);
		}
		// Between 1986-2006
		else if ($year >= 1986 && $year < 2007)
		{
			$dst_start = find_day_of_month(1, 'Sun', 'Apr', $year);
			$dst_end = find_day_of_month('last', 'Sun', 'Oct', $year);
		}
		// 2007 and future?
		else
		{
			$dst_start = find_day_of_month(2, 'Sun', 'Mar', $year);
			$dst_end = find_day_of_month(1, 'Sun', 'Nov', $year);
		}

		if ($time >= $dst_start && $time <= $dst_end)
		{
			$dst_offset += 3600;
		}
	}

	return date($format, $time + $time_zone_offset + $dst_offset);
}

/**
 * Parse a date, return an int (same functionality as PHP strtotime() but with TZ diff)
 * For epic faal reasons, we need to apply timezone before getting string, and then convert BACK to GMT....
 * else all our calculations elsewhere are screwed up.
 */
function parse_date ($time)
{
	global $time_zone_offset, $time_zone, $time_zone_dst;

	// TZ difference
	$now = gmmktime() + $time_zone_offset;

	// DST - TODO - REMOVE
	// DST should be only be applied to the parsed date; regardless of current state of DST
	if ($time_zone_dst)
	{
		if ($time_zone == 'CST' || $time_zone == 'MST' || $time_zone == 'PST')
		{
			$now += 3600;
		}
	}

	$seconds = strtotime($time, $now);

	// Return false if the date is invalid
	if ($seconds === false || $seconds === -1)
	{
		return false;
	}

	// TZ difference
	$seconds -= $time_zone_offset;

	// DST - TODO - REMOVE
	// DST should be only be applied to the parsed date; regardless of current state of DST
	if ($time_zone_dst)
	{
		if ($time_zone == 'CST' || $time_zone == 'MST' || $time_zone == 'PST')
		{
			$seconds -= 3600;
		}
	}
	
	return $seconds;
}

/**
 * So sick of working with time zones
 * This works
 */
function beginning_of_today ()
{
	global $time_zone_offset;
	// putenv('TZ=GMT');
	
	$now = gmmktime();
	$hours_today = (date('G', $now - $time_zone_offset));
	$minutes_today = (date('i', $now - $time_zone_offset));
	$seconds_today = (date('s', $now - $time_zone_offset));

	$beginning = $now - (3600 * $hours_today);
	$beginning -= ($minutes_today * 60);
	$beginning -= $seconds_today;

	return $beginning;
}

/**
 * Find Nth (day) in (month) of (year)
 * Return timestamp for 2 AM of user's timezone - we use this for DST, and DST usually is effective at 2?
 */
function find_day_of_month ($n, $day, $month, $year)
{
	global $time_zone_offset;

	$times_day_found = 0;
	$date_month = $month;
	$date = 1;
	while (($times_day_found < $n || $n == 'last') && $date_month == $month)
	{
		$time = parse_date("$month $date, $year");
		$date_month = date('M', $time + $time_zone_offset);
		$day_found = date('D', $time + $time_zone_offset);

		if ($day_found == $day)
		{
			$times_day_found++;
		}

		$date++;
	}

	return $time;
}
?>
