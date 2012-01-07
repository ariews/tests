<?php

/**
* Habari unit test bootstrap file
*
* How to use:
* Step 1: Create a symlink to the tests directory within the htdocs directory
* Step 2: Include this file at the beginning of a test
**/

/**
 * Options:
 *   -d : Dry-run, don't execute tests.
 *   -c {console|html|symbolic} : Output type.
 *   -t {testname|linenumber} : Run a specific test, or multiple separated by commas
 *   -r {path} : Set the root path for habari.
 *   -o : Display output.
 *   -u {unitname} : Run only the specified units.
 */

if(defined('STDIN') && function_exists( 'getopt' ) ) {
	$shortopts = 'u::d::c::t::r::o';
	$options = getopt($shortopts);
}
if(!isset($options) || !$options) {
	$options = array();
}
global $querystring_options;
if(!isset($querystring_options)) {
	$querystring_options = array_intersect_key($_GET, array('o'=>1,'t'=>'','c'=>'','d'=>'','u'=>''));
	$options = array_merge($options, $querystring_options);
}

if(!defined('HABARI_PATH')) {
	if(isset($options['r'])) {
		define('HABARI_PATH', $options['r']);
	}
	else {
		// Try traversing up until we find an index.php
		$dirname = dirname(dirname( __FILE__ ));
		while(!file_exists($dirname . '/index.php')) {
			$dirname = dirname($dirname);
			if(strlen($dirname) <= 1) {
				throw new \Exception("Couldn't find Habari's index.php");
			}
		}
		define('HABARI_PATH', $dirname );
	}
}

if(!defined('UNIT_TEST')) {
	define('UNIT_TEST', true);
}
if(!defined('DEBUG')) {
	define('DEBUG', true);
}

if(!class_exists('UnitTestCase')):

class UnitTestCase
{
	const FAIL = 0;
	const INCOMPLETE = 1;
	const SKIP = 2;

	public $messages = array();
	public $result;

	private $exceptions = array();
	private $checks = array();
	private $asserted_exception = null;
	protected $show_output = false;
	protected $conditions = array();
	protected $methods = array();

	public function assert_true($value, $message = 'Assertion failed')
	{
		if($value !== true) {
			$this->messages[] = array(self::FAIL, $message, debug_backtrace());
			$this->result->fail_count++;
		}
		else {
			$this->result->pass_count++;
		}
	}

	public function assert_false($value, $message = 'Assertion failed')
	{
		if($value !== false) {
			$this->messages[] = array(self::FAIL, $message, debug_backtrace());
			$this->result->fail_count++;
		}
		else {
			$this->result->pass_count++;
		}
	}

	public function assert_equal($value1, $value2, $message = 'Assertion failed')
	{
		if($value1 != $value2) {
			$this->messages[] = array(self::FAIL, $message, debug_backtrace());
			$this->result->fail_count++;
		}
		else {
			$this->result->pass_count++;
		}
	}

	public function assert_not_equal($value1, $value2, $message = 'Assertion failed')
	{
		if($value1 == $value2) {
			$this->messages[] = array(self::FAIL, $message, debug_backtrace());
			$this->result->fail_count++;
		}
		else {
			$this->result->pass_count++;
		}
	}

	public function assert_identical($value1, $value2, $message = 'Assertion failed')
	{
		if($value1 !== $value2) {
			$this->messages[] = array(self::FAIL, $message, debug_backtrace());
			$this->result->fail_count++;
		}
		else {
			$this->result->pass_count++;
		}
	}

	public function assert_exception($exception = '', $message = 'Expected exception')
	{
		$this->asserted_exception = array($exception, $message);
	}

	public function assert_type( $type, $object, $message = 'Types not equal' )
	{
		$class = get_class( $object );
		if( $class != $type ) {
			$this->messages[] = array(self::FAIL, $message, debug_backtrace());
			$this->result->fail_count++;
		}
		else {
			$this->result->pass_count++;
		}
	}

	public function mark_test_incomplete( $message = 'Tests not implemented' )
	{
		$this->messages[] = array( self::INCOMPLETE, $message);
		$this->result->incomplete_count++;
	}

	public function output($v)
	{
		$this->show_output = true;
		print_r($v);
		echo "\n";
	}

	public function check($checkval, $message = 'Expected check')
	{
		$this->checks[$checkval] = $message;
	}

	public function pass_check($checkval)
	{
		unset($this->checks[$checkval]);
	}

	public function add_condition($condition, $reason = false)
	{
		$this->conditions[$condition] = $reason;
	}

	public function skip_all()
	{
		$this->methods = array_fill_keys(array_keys($this->methods), 'Skipping all tests.');
	}

	public function skip_test($name, $reason = 'This test is explicitly skipped.')
	{
		if(isset($this->methods['test_' . $name])) {
			$name = 'test_' . $name;
		}
		if(isset($this->methods[$name])) {
			$this->methods[$name] = $reason;
		}
	}

	public function named_test_filter( $function_name )
	{
		return preg_match('%^test_%', $function_name);
	}

	private final function pre_test()
	{
		$this->asserted_exceptions = array();
		$this->exceptions = array();
		$this->checks = array();
	}

	private final function post_test()
	{
		if(isset($this->asserted_exception)) {
			$this->result->fail_count++;
			$this->messages[] = array(self::FAIL, $this->asserted_exception[1] . ': ' . $this->asserted_exception[0]);
		}
		foreach($this->checks as $check => $message) {
			$this->result->fail_count++;
			$this->messages[] = array(self::FAIL, $message);
		}
	}

	public function run()
	{
		global $options;
		$this->options = $options;

		// Get the list of methods that qualify as tests and mark them as "to test"
		$methods = get_class_methods($this);
		$methods = array_filter($methods, array($this, 'named_test_filter'));
		$this->methods = array_fill_keys($methods, 1);  // Marked as "to test"
		$cases = 0;

		// Get class info and build a result object, which will be returned
		$class = new ReflectionClass( get_class( $this ) );
		$this->result = new TestResult(get_class($this), $class->getFileName());

		// Execute any module setup that might exist
		if(method_exists($this, 'module_setup')) {
			$this->module_setup();
		}

		// If specific tests are specified to run within this unit, get that list
		if(isset($options['t'])) {
			$options['t'] = explode(',', $options['t']);
			if(count($options['t']) == 0) {
				unset($options['t']);
			}
		}

		$dryrun = isset($options['d']);

		// Attempt to execute test methods in this unit
		foreach($this->methods as $method => $run_status) {
			$this->messages = array();
			$this->show_output = false;
			$this->result->total_case_count++;

			$do_skip = false;

			// Get test line numbers and skip the test if it's not specified
			$ref_method = new ReflectionMethod($this, $method);
			if(isset($options['t'])) {
				$start_line = $ref_method->getStartLine();
				$found = false;
				foreach($options['t'] as $line) {
					if($line > $ref_method->getStartLine() && $line < $ref_method->getEndLine()) {
						$found = true;
						break;
					}
					if(strtolower($ref_method->getName()) == strtolower($line)) {
						$found = true;
						break;
					}
				}
				if(!$found) {
					$do_skip = 'Skipped by request';
				}
			}

			/**
			 * If a test module includes a line such as $this->add_condition('mysql', 'Skipping mysql tests');
			 * then the test suite will skip any test named with the prefix test_mysql_*
			 **/
			if(count($this->conditions) > 0) {
				if(preg_match('%^test_(' . implode('|', array_keys($this->conditions)) . ')_.+%i', $method, $condition_matches)) {
					if(isset($this->conditions[$condition_matches[1]]) && is_string($this->conditions[$condition_matches[1]])) {
						$do_skip = $this->conditions[$condition_matches[1]];
					}
				}
			}

			/**
			 * methods (keys) in the method list must have a value of 1
			 * If not, they will be skipped using a message of the value in the list.
			 */
			if($this->methods[$method] != 1) {
				$do_skip = $this->methods[$method];
			}


			// Skip tests that are not meant to be run
			if($do_skip) {
				$this->messages[] = array(self::SKIP, $do_skip);
				$this->result->skipped_count++;
			}
			else {
				// Reset test results and run any per-test setup method that might exist in the unit
				if(!$dryrun) {
					$this->pre_test();
					if(method_exists($this, 'setup')) {
						$this->setup();
					}
				}


				// Run the actual test
				try {
					ob_start();
					if(!$dryrun) {
						$this->$method();
					}
					$output = ob_get_clean();
				}
				// If exceptions occurred, determine if we were asserting them or not
				catch(Exception $e) {
					if(strpos($e->getMessage(), $this->asserted_exception[0]) !== false || get_class($e) == $this->asserted_exception[0]) {
						$this->result->pass_count++;
						$this->asserted_exception = null;
					}
					else {
						$this->result->exception_count++;
						$trace = $e->getTrace();
						$ary = current($trace);
						while( !isset($ary['file']) || strpos($ary['file'], 'error.php') != false ) {
							$ary = next($trace);
						}
						$ary = current($trace);
						$this->messages[] = array(self::FAIL, get_class($e) . ':' . $e->getMessage(), array($ary['file'] . ':' . $ary['line']));
					}
				}

				// Run any per-test teardown method that might be specified,
				// then run post_test to check expected exception assertion counts
				if(!$dryrun) {
					if(method_exists($this, 'teardown')) {
						$this->teardown();
					}
					$this->post_test();
				}

				if($this->show_output) {
					$this->messages[] = $output;
				}
			}

			$this->result->method_results($method, $this->messages);

			$this->result->case_count++;
//			$this->result->total_case_tount++;
		}
		
		if(method_exists($this, 'module_teardown')) {
			$this->module_teardown();
		}

		return $this->result;
	}

	public static function run_one() {
		/**
		 * @stub
		 * @todo remove this method
		 **/

	}
}

class TestSuite {

	static $features = array();
	static $run_all = false;

	public static function run_all()
	{
		global $options;

		if(isset($options['u'])) {
			$options['u'] = explode(',', $options['u']);
			if(count($options['u']) == 0) {
				unset($options['u']);
			}
		}

		$pass_count = 0;
		$fail_count = 0;
		$exception_count = 0;
		$case_count = 0;

		self::$run_all = true;
		$classes = get_declared_classes();
		$classes = array_unique($classes);
		sort($classes);

		$results = new TestResults();
		foreach($classes as $class) {
			if(isset($options['u']) && !in_array($class, $options['u'])) {
				continue;
			}
			$parents = class_parents($class, false);
			if(in_array('UnitTestCase', $parents)) {
				$obj = new $class();
				$results[$class] = $obj->run();

/*				$pass_count += $obj->pass_count;
				$fail_count += $obj->fail_count;
				$exception_count += $obj->exception_count;
				$case_count += $obj->case_count;*/
			}
		}

		while(ob_get_level()) {
			ob_end_clean();
		}
		echo $results;
	}

	public static function run_one($classname)
	{
		if(self::$run_all) {
			return;
		}
		$testobj = new $classname();

		$results = $testobj->run();
		echo $results;
	}

	public static function run_dir($directory = null)
	{
		self::$run_all = true;
		if(!isset($directory)) {
			$directory = dirname(__FILE__);
		}
		// Find unit tests, include them
		$unit_tests = glob($directory . '/units/test_*.php');
		foreach($unit_tests as $test) {
			include($test);
		}
		// Find feature files, list them
		self::$features = glob($directory . '/features/*.feature');

		self::run_all();
	}

}

class TestResult
{
	public $methods = array();
	public $test_name = '';
	public $file = '';
	public $summaries = array();
	private $options = array();

	function __construct($test_name, $file = null)
	{
		global $options;
		$this->options = $options;
		$this->options['HABARI_PATH'] = HABARI_PATH;
		$this->test_name = $test_name;
		$this->file = ltrim(str_replace(dirname(__FILE__), '', $file), '\\/');

		$this->summaries = array(
			'pass_count' => 0,
			'fail_count' => 0,
			'incomplete_count' => 0,
			'exception_count' => 0,
			'case_count' => 0,
			'total_case_count' => 0,
			'skipped_count' => 0,
		);
	}

	function __set($key, $value)
	{
		return $this->summaries[$key] = $value;
	}

	function __get($key)
	{
		if(!isset($this->summaries[$key])) {
			return 0;
		}
		return $this->summaries[$key];
	}

	function method_results($method, $results)
	{
		$this->methods[$method] = $results;
	}

	function summary($values)
	{
		$this->summaries = array_merge($this->summaries, $values);
	}
}


class TestResults extends ArrayObject
{
	private $options = array();
	private $type = array();

	function __construct()
	{
		global $options;
		$this->options = $options;
		$this->type = array(
			'Fail',
			'Incomplete',
			'Skipped',
		);
	}

	function __toString()
	{
		global $options;
		$default_output = defined('STDIN') ? 'console' : 'html';
		if(isset($options['c'])) {
			switch($options['c']) {
				case 'console':
				case 'c':
					$default_output = 'console';
					break;
				case 'html':
				case 'h':
					$default_output = 'html';
					break;
				case 'symbolic':;
				case 's':;
				case 'x':;
				case 'xml':;
					$default_output = 'symbolic';
					break;
			}
		}
		switch($default_output) {
			case 'console':
				header('content-type: text/plain');
				return $this->out_console();
			case 'html':
				header('content-type: text/html');
				return $this->out_html();
			case 'symbolic':
				header('content-type: text/xml');
				return $this->out_symbolic();
		}
	}

	function initial_results()
	{
		return array('total_case_count'=>0, 'case_count'=>0, 'fail_count'=>0, 'pass_count'=>0, 'exception_count'=>0, 'incomplete_count'=>0, 'skipped_count'=>0);
	}

	function out_html()
	{
		$has_output = false;
		$totals = $this->initial_results();

		switch($this->count()) {
			case 0:
				$title = 'No tests were run.';
				break;
			case 1:
				$title = "Test Results for " . reset($this)->test_name;
				break;
			default:
				$title = "Test Results for " . $this->count() . " tests";
				break;
		}

		$output = "<!DOCTYPE HTML><html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"><title>{$title}</title>" .
			'<link rel="stylesheet" type="text/css" href="style.css">' .
			"</head><body>";
		foreach($this as /* TestRestult */ $test) {
			$output .= "<h1>{$test->test_name}<a href=\"index.php?u={$test->test_name}\" style=\"font-size: xx-small;font-weight: normal;margin-left: 20px;\">Run only {$test->test_name}</a></h1>";

			if(!isset($test->methods)) {
				$test->methods = array();
				$test->summaries = $this->initial_results();
			}

			foreach($test->methods as $methodname => $messages) {
				$output .= "<h2>{$methodname}</h2>";
				foreach($messages as $message) {
					if(is_string($message)) {
						if(isset($this->options['o'])) {
							$output .= "<div style=\"white-space:pre;border: 1px solid #ccc;padding: 0px 10px 10px;background: #efefef;\"><h3>Output</h3>{$message}</div>";
						}
						else {
							$has_output = true;
						}
					}
					else {
						$output .= "<div><em>{$this->type[$message[0]]}: </em> {$message[1]}";
						if(count($message) > 2) {
							$output .= '<br/>' . $message[2][0]['file'] . ':' . $message[2][0]['line'];
						}
						$output .= '</div>';
					}
				}
			}

			$summary = $test->summaries;
			foreach($summary as $k => $v) {
				if(isset($totals[$k]) && is_numeric($v)) {
					$totals[$k] += $v;
				}
			}
			$output .= "<div class=\"test complete\"><p>{$summary['case_count']}/{$summary['total_case_count']} tests complete.  {$summary['fail_count']} failed assertions.  {$summary['pass_count']} passed assertions.  {$summary['exception_count']} exceptions.  {$summary['incomplete_count']} incomplete tests.  {$summary['skipped_count']} skipped tests.</p></div>";
		}

		$output .= '<footer><h3>Results</h3>';
		$output.= sprintf('<div class="all test complete">%d units containing %d tests. %d failed assertions.  %d passed assertions.  %d exceptions.  %d incomplete tests.  %d skipped tests.</div>', $this->count(), $totals['case_count'], $totals['fail_count'], $totals['pass_count'], $totals['exception_count'], $totals['incomplete_count'], $totals['skipped_count']);

		if($has_output) {
			$output .= "<div class=\"has_output\">Some tests have output.  <a href=\"?o=1\">Turn on the output option</a> to see output.</div>";
		}

		$output .= '<h3>Options</h3><table>';
		foreach($this->options as $k => $v) {
			$output .= "<tr><th>{$k}</th><td>{$v}</td></tr>";
		}
		$output .= '</table></footer>';

		$output .= '</body></html>';

		return $output;
	}

	function out_console()
	{
		$has_output = false;
		$totals = $this->initial_results();

		switch($this->count()) {
			case 0:
				$title = 'No tests were run.';
				break;
			case 1:
				$title = "Test Results for " . reset($this)->test_name;
				break;
			default:
				$title = "Test Results for " . $this->count() . " tests";
				break;
		}

		$output = array();
		$output[] = "==== {$title} ====";
		foreach($this as /* TestRestult */ $test) {
			$output[]= "\n=== {$test->test_name} ===";

			if(!isset($test->methods)) {
				$test->methods = array();
				$test->summaries = $this->initial_results();
			}

			foreach($test->methods as $methodname => $messages) {
				$output[]= "  {$methodname}";
				foreach($messages as $message) {
					if(is_string($message)) {
						if(isset($this->options['o'])) {
							$output[]= "\n          == Begin Output ==\n";
							$message = explode("\n", $message);
							$message = array_map(create_function('$s', 'return "          " . $s;'), $message);
							$output = array_merge($output, $message);
							$output[]= "          ==  End Output  ==";
						}
						else {
							$has_output = true;
						}
					}
					else {
						$output[]= str_pad($this->type[$message[0]] . ': ', 10, ' ', STR_PAD_LEFT ) . $message[1];
						if(count($message) > 2) {
							$output[]= '      ' . $message[2][0]['file'] . ':' . $message[2][0]['line'];
						}
					}
				}
			}

			$summary = $test->summaries;
			foreach($summary as $k => $v) {
				if(isset($totals[$k]) && is_numeric($v)) {
					$totals[$k] += $v;
				}
			}
			$output[]= sprintf("\n%d/%d tests complete.  %d failed assertions.  %d passed assertions.  %d exceptions.  %d incomplete tests.", $summary['case_count'], $summary['total_case_count'], $summary['fail_count'], $summary['pass_count'], $summary['exception_count'], $summary['incomplete_count']);
		}

		$output[]= "\n=== Results ===";
		$output[]= sprintf('%d units containing %d tests.  %d tests run.  %d failed assertions.  %d passed assertions.  %d exceptions.  %d incomplete tests.', $this->count(), $totals['total_case_count'], $totals['case_count'], $totals['fail_count'], $totals['pass_count'], $totals['exception_count'], $totals['incomplete_count']);
		if($has_output) {
			$output[]= "\nSome tests have output.  Run again with -o to see output.";
		}

		$output[]= "\n=== Options ===";
		foreach($this->options as $k => $v) {
			$output[]= "  {$k}: {$v}";
		}

		return implode("\n", $output) . "\n";
	}

	function out_symbolic()
	{
		$has_output = false;
		$totals = $this->initial_results();

		$xml = new SimpleXMLElement('<results></results>');

		$xml->addAttribute('unit_count', $this->count());

		$totals = $this->initial_results();

		foreach($this as $test) {
			$xunit = $xml->addChild('unit');
			$xunit->addAttribute('name', $test->test_name);

			if(!isset($test->methods)) {
				$test->methods = array();
				$test->summaries = $this->initial_results();
			}

			foreach($test->methods as $methodname => $messages) {
				$xmethod = $xunit->addChild('method');
				$xmethod->addAttribute('name', $methodname);

				$has_output = 0;
				foreach($messages as $message) {
					if(is_string($message)) {
						if(isset($this->options['o'])) {
							$xmethod->addChild('output', $message);
						}
						$has_output = 1;
					}
					else {
						$xmessage = $xmethod->addChild('message', $message[1]);
						$xmessage->addAttribute('type', $this->type[$message[0]]);
						if(count($message) > 2) {
							$xmessage->addAttribute('file', $message[2][0]['file']);
							$xmessage->addAttribute('line', $message[2][0]['line']);
						}
					}
				}
				$xmethod->addAttribute('has_output', $has_output);
			}

			$summary = $test->summaries;
			foreach($summary as $k => $v) {
				if(isset($totals[$k]) && is_numeric($v)) {
					$totals[$k] += $v;
				}
			}
			$xunit->addAttribute('cases', $summary['case_count']);
			$xunit->addAttribute('complete', $summary['total_case_count']);
			$xunit->addAttribute('fail', $summary['fail_count']);
			$xunit->addAttribute('pass', $summary['pass_count']);
			$xunit->addAttribute('exception', $summary['exception_count']);
			$xunit->addAttribute('incomplete', $summary['incomplete_count']);
		}

		$xml->addAttribute('complete', $totals['total_case_count']);
		$xml->addAttribute('fail', $totals['fail_count']);
		$xml->addAttribute('pass', $totals['pass_count']);
		$xml->addAttribute('exception', $totals['exception_count']);
		$xml->addAttribute('incomplete', $totals['incomplete_count']);

		return $xml->asXML();
	}

}

include HABARI_PATH . '/index.php';

endif;

?>