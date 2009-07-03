<?php
/**
 * test php-frame/template
 *
 * @started: 07/11/2005
 * @copyright: Copyright (c) 2005-2009 Cortex Creations, LLC, All Rights Reserved
 * @website: www.cortex-creations.com
 * @license: LGPL v2.0
 * @subversion: $Id: template.php 122 2009-04-06 02:17:18Z josh $
 */

require('../php-frame/security.php');
require('../php-frame/template.php');
require('../php-frame/unit_test.php');

class Template_test extends PHP_unit_tests
{
	function test_nested_set_var ()
	{
		$template = new Template('', '', '');

		$this->assert_true($template->tpl_vars == array());

		$template->set_var('x', 'y');

		$this->assert_true($template->tpl_vars['x'] == 'y');

		$template->set_var('y', array(1, array(2, 3, array(4, 5, array(6, 7, 8, 9)))));

		$this->assert_true(gettype($template->tpl_vars['y']) == 'array');
		$this->assert_true($template->tpl_vars['y'][0] == 1);
		$this->assert_true($template->tpl_vars['y_count'] == 2);
		$this->assert_true(gettype($template->tpl_vars['y'][1]) == 'array');
		$this->assert_true($template->tpl_vars['y']['1_count'] == 3);
		$this->assert_true($template->tpl_vars['y'][1][0] == 2);
		$this->assert_true($template->tpl_vars['y'][1]['0'] == 2);
		$this->assert_true($template->tpl_vars['y'][1][1] == 3);
		$this->assert_true($template->tpl_vars['y'][1]['1'] == 3);
		$this->assert_true(gettype($template->tpl_vars['y'][1][2]) == 'array');
		$this->assert_true($template->tpl_vars['y'][1]['2_count'] == 3);
		$this->assert_true(gettype($template->tpl_vars['y'][1][2][2]) == 'array');
		$this->assert_true($template->tpl_vars['y'][1][2]['2_count'] == 4);
	}
}


$tester = new PHP_unit_tester();
$tester->run('Template_test');
$tester->report();
?>
