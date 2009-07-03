<?php
/**
 * PHP-Frame - select-list related functions
 *
 * @begin: Saturday, December 3, 2005
 * @copyright: Copyright (c) 2005-2009 Cortex Creations, LLC, All Rights Reserved
 * @website: www.joshisgross.com/projects/php-frame
 * @license: see MIT-LICENSE
 */

// Security
if (!defined('IN_PHPFRAME'))
{
	exit;
}

/**
 * Generate <option> tags for select lists
 */
function generate_select_options ($current_value, $possible_values)
{
	$html = '';
	foreach ($possible_values as $option_value => $option_label)
	{
		$selected = ($current_value == $option_value ? ' selected="selected"' : '');
		$html .= '<option value="'.htmlspecialchars($option_value).'"'.$selected.'>'.htmlspecialchars($option_label).'</option>';
	}
	return $html;
}

/**
 * Verify that a value is valid, given a set of valid values
 */
function verify_select_list_value ($current_value, $possible_values, $default_value)
{
	// If it's in the array, it's valid
	return (isset($possible_values[$current_value]) ? $current_value : $default_value);
}

/**
 * Turn a flat array into a v => v ie: ('banana') becomes ('banana' => 'banana')
 */
function select_list_from_flat_array ($the_array)
{
	$new_array = array();
	
	foreach ($the_array as $value)
	{
		$new_array[$value] = $value;
	}
	
	return $new_array;
}
?>
