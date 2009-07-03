<?php
/**
 * PHP-Frame: class for pages to extend
 * 
 * @started: Wednesday, January 18, 2005
 * @copyright: Copyright (c) 2005-2006, Cortex Creations, All Rights Reserved
 * @website: www.cortex-creations.com
 * @license: see COPYING
 * @subversion: $Id: page.php 63 2006-10-17 22:03:16Z jd $
 */
class PHP_Frame_page extends PHP_unit_tests
{
	/**
	 * All form/page input goes in this array, key => value.
	 */
	var $input = array();

	/**
	 * Status of page - either error title/body will be present,
	 * or the template file to display.
	 */
	var $error_body = false;
	var $error_title = false;
	var $view_template = false;

	/**
	 * Messages for template
	 */
	var $intro_text = '';
	var $successes = array();
	var $errors = array();

	/**
	 * Get input required for the page.
	 */
	function get_input ()
	{
		foreach ($this->required_input as $name => $default)
		{
			if (is_array($default) && count($default) >= 2)
			{
				$this->$name = get_input($GLOBALS['_' . strtoupper($default[0])], $name, $default[1]);
				$this->input[$name] = &$this->$name;
			}
			else
			{
				$this->$name = get_input($_REQUEST, $name, $default);
				$this->input[$name] = &$this->$name;
			}
		}
	}

	/**
	 * Include required libraries for this page
	 */
	function include_libraries ()
	{
		global $jdb_root_path;
		foreach ($this->required_libraries as $lib_file)
		{
			require_once($jdb_root_path . $lib_file);
		}
	}

	/**
	 * Generate breadcrumb links to send to template
	 */
	function generate_breadcrumbs ()
	{
		global $template, $school_data;
		
		// Modify this for project - first link is to home page
		$template_breadcrumbs = array();
		$template_breadcrumbs[] = array
		(
			'label' => htmlspecialchars('Index'),
			'u_breadcrumb' => build_url('index.php', array())
		);

		$breadcrumb_data = $this->breadcrumbs();
		foreach ($breadcrumb_data as $label => $link)
		{
			if ($label)
			{
				$template_breadcrumbs[] = array
				(
					'label' => $label,
					'u_breadcrumb' => build_url('index.php', $link)
				);
			}
		}

		$template->set_var('breadcrumbs', ($breadcrumb_data ? $template_breadcrumbs : array()));
	}

	/**
	 * Call after execute(), right before the template is shown.
	 * To show these messages you should have a messages.html template file.
	 */
	function after_execute()
	{
		global $template;

		// Format error and success message arrays
		foreach ($this->successes as $k=>$v)
		{
			$this->successes[$k] = array('text' => $v);
		}
		foreach ($this->errors as $k=>$v)
		{
			$this->errors[$k] = array('text' => $v);
		}

		// Send status/error messages to the template
		$template->set_var('success_messages', $this->successes);
		$template->set_var('error_messages', $this->errors);

		// Set intro text to page
		$template->set_var('page_intro_text', $this->intro_text);
	}
}
?>
