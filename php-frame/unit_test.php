<?php
/**
 * PHP-Frame - PHP unit testing framework.
 *
 * @started: Thursday, December 22, 2005
 * @copyright: Copyright (c) 2005-2009 Cortex Creations, LLC, All Rights Reserved
 * @website: www.joshisgross.com/projects/php-frame
 * @license: see MIT-LICENSE
 */

/**
 * All test classes need to extend this.
 * All test methods must begin with "test_".
 */
class PHP_unit_tests
{
	var $features_tested = 0;
	var $assertions = 0;
	var $failed_feature_tests = 0;
	var $failed_features = array();
	var $current_test = '';

	/**
	 * Runs tests
	 */
	function run_all ()
	{
		// Initialize tester
		if (method_exists($this, 'init_testing'))
		{
			$this->init_testing();
		}
		
		// Loop through all class methods, and run feature tests
		$features = array();
		foreach (get_class_methods($this) as $method_name)
		{
			if (preg_match('#^test_(.*?)$#', $method_name, $match))
			{
				// Run the feature test
				$this->current_test = $method_name;
				eval('$this->' . $method_name . '();');
				++$this->features_tested;

				// Add method name and status to array of features
				$features[$match[1]] = isset($this->failed_features[$method_name]) ? 0 : 1;
			}
		}
		
		return $features;
	}

	/**
	 * Assert "true" result
	 */
	function assert_true ($test)
	{
		// Make sure feature test is only reported as failing once
		if (!isset($this->failed_features[$this->current_test]))
		{
			// Did the feature test fail?
			if ($test !== true)
			{
				$this->failed_features[$this->current_test] = true; 
				if (function_exists('debug_backtrace') && $debug_backtrace = debug_backtrace())
				{
					$this->failed_features[$this->current_test] = $debug_backtrace[0]; 
				}

				// Increment number of failed feature tests?
				++$this->failed_feature_tests;
			}

			// Increment number of assertions tested
			++$this->assertions;
		}
	}
};

/**
 * This class runs tests and reports results.
 */
class PHP_unit_tester
{
	var $tests_run = 0;
	var $features_tested = 0;
	var $failed_feature_tests = 0;
	var $assertions = 0;
	var $failed_tests = array();
	var $tests = array();
	
	/**
	 * Run a test.
	 */
	function run ($test_class_name)
	{
		eval('$test = new ' . $test_class_name . ';');

		// If test is a page, initialize
		if (method_exists($test, 'include_libraries'))
		{
			$test->include_libraries();
		}

		// Run all tests
		$this->tests[$test_class_name] = $test->run_all();

		// Were there any errors?
		if ($test->failed_features)
		{
			$this->failed_tests[$test_class_name] = $test->failed_features;
		}

		// Update testing statistics
		++$this->tests_run;
		$this->features_tested += $test->features_tested;
		$this->assertions += $test->assertions;
		$this->failed_feature_tests += $test->failed_feature_tests;
	}

	/**
	 * Report the results of testing.
	 */
	function report ($url = '')
	{
		global $db;

		// First, display statistics...
		print('<b>Tests run: </b>' . $this->tests_run . '<br />');
		print('<b>Feature tests: </b>' . $this->features_tested . '<br />');
		print('<b>Assertions tested: </b>' . $this->assertions . '<br />');
		print('<b>Queries Run: </b>' . $db->num_queries . '<br />');
		$fail_color = ($this->failed_feature_tests ? 'red' : 'blue');
		print('<span style="color: '.$fail_color.'"><b>Failed feature tests: </b>' . $this->failed_feature_tests . '</span><br /><br />');

		// Display where tests failed
		if ($this->failed_tests)
		{
			print('<b>Failures:</b>');
			print('<ul>');
			foreach ($this->failed_tests as $test_name => $failures)
			{
				print('<li><a href="'.($url ? $url . $test_name : '#').'"><i>' . $test_name . '</i></a><ul>');
				foreach ($failures as $feature_name => $data)
				{
					$debug_info = 'PHP 5 is not being used; the line number and file where the failure occurred in unknown.';
					if (isset($data['line']))
					{
						$debug_info = $data['file'] . ':' . $data['line'];
					}
					print('<li><i>' . $feature_name . '</i> - ' . $debug_info . '</li>');
				}
				print('</ul></li>');
			}
			print('</ul>');
		}

		// Display table of tests/features
		print('<table style="border: 1px solid #000000; width: 50%;">');
		foreach ($this->tests as $test_name => $results)
		{
			$test_url = ($url ? $url . $test_name : '#');
			print('<tr><td><a href="' . $test_url . '" style="color: #000000; font-weight: bold;">' . $test_name . '</a></td><td>&nbsp;</td><td>&nbsp;</td></tr>');
			foreach ($results as $feature => $result)
			{
				$status_color = ($result ? '#0000ff' : '#ff0000');
				$status_text = ($result ? 'Success' : 'Fail');
				$status = '<span style="color: ' . $status_color . '">' . $status_text . '</span>';
				print('<tr><td>&nbsp;</td><td>' . $feature . '</td><td>' . $status . '</td></tr>');
			}
		}
		print('</table>');
	}
};
?>
