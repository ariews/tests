<?php

/**
* Habari unit test bootstrap file
*
* How to use:
* Step 1: Create a symlink to the tests directory within the htdocs directory
* Step 2: Include this file at the beginning of a test
**/

if( function_exists( 'getopt' ) ) {
	$shortopts = 'r::';
	$longopts = array();
	$options = getopt($shortopts, $longopts);
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
				throw new Exception("Couldn't find Habari's index.php");
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
	static $run_all = false;
	public $messages = array();
	public $pass_count = 0;
	public $fail_count = 0;
	public $exception_count = 0;
	public $case_count = 0;
	private $exceptions = array();
	private $checks = array();
	private $asserted_exception = null;

	public function assert_true($value, $message = 'Assertion failed')
	{
		if($value !== true) {
			$this->messages[] = array($message, debug_backtrace());
			$this->fail_count++;
		}
		else {
			$this->pass_count++;
		}
	}

	public function assert_false($value, $message = 'Assertion failed')
	{
		if($value !== false) {
			$this->messages[] = array($message, debug_backtrace());
			$this->fail_count++;
		}
		else {
			$this->pass_count++;
		}
	}

	public function assert_equal($value1, $value2, $message = 'Assertion failed')
	{
		if($value1 != $value2) {
			$this->messages[] = array($message, debug_backtrace());
			$this->fail_count++;
		}
		else {
			$this->pass_count++;
		}
	}

	public function assert_identical($value1, $value2, $message = 'Assertion failed')
	{
		if($value1 !== $value2) {
			$this->messages[] = array($message, debug_backtrace());
			$this->fail_count++;
		}
		else {
			$this->pass_count++;
		}
	}

	public function assert_exception($exception = '', $message = 'Expected exception')
	{
		$this->asserted_exception = array($exception, $message);
	}

	public function check($checkval, $message = 'Expected check')
	{
		$this->checks[$checkval] = $message;
	}

	public function pass_check($checkval)
	{
		unset($this->checks[$checkval]);
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
			$this->fail_count++;
			$this->messages[] = array($this->asserted_exception[1] . ': ' . $this->asserted_exception[0]);
		}
		foreach($this->checks as $check => $message) {
			$this->fail_count++;
			$this->messages[] = array($message);
		}
	}

	public function run($results)
	{
		$methods = get_class_methods($this);
		$methods = array_filter($methods, array($this, 'named_test_filter'));
		$cases = 0;

		$results->test(get_class($this));

		if(method_exists($this, 'module_setup')) {
			$this->module_setup();
		}

		foreach($methods as $method) {
			$this->messages = array();

			$this->pre_test();
			if(method_exists($this, 'setup')) {
				$this->setup();
			}

			try {
				ob_start();
				$this->$method();
				$output = ob_get_clean();
			}
			catch(Exception $e) {
				if(strpos($e->getMessage(), $this->asserted_exception[0]) !== false || get_class($e) == $this->asserted_exception[0]) {
					$this->pass_count++;
					$this->asserted_exception = null;
				}
				else {
					$this->exception_count++;
					$trace = $e->getTrace();
					$ary = current($trace);
					while( strpos($ary['file'], 'error.php') != false ) {
						$ary = next($trace);
					}
					$ary = current($trace);
//					echo '<div><em>Exception '. get_class($e) .':</em> ' . $e->getMessage() . '<br/>' . $ary['file'] . ':' . $ary['line'] . '</div>';
//					echo '<pre>' . print_r($trace, 1) . '</pre>';
				}
			}

			if(method_exists($this, 'teardown')) {
				$this->teardown();
			}
			$this->post_test();

			$results->method_results(get_class($this), $method, $this->messages);

			$this->case_count++;
		}
		
		if(method_exists($this, 'module_teardown')) {
			$this->module_teardown();
		}

		$results->summary(get_class($this), get_object_vars($this));

		return $results;
	}

	public static function run_one($classname)
	{
		if(self::$run_all) {
			return;
		}
		$testobj = new $classname();

		$testobj->run($results = new UnitTestResults());
		echo $results;
	}

	public static function run_all()
	{
		$pass_count = 0;
		$fail_count = 0;
		$exception_count = 0;
		$case_count = 0;

		self::$run_all = true;
		$classes = get_declared_classes();
		$classes = array_unique($classes);
		sort($classes);
		$results = new UnitTestResults();
		foreach($classes as $class) {
			$parents = class_parents($class, false);
			if(in_array('UnitTestCase', $parents)) {
				$obj = new $class();
				$obj->run($results);

				$pass_count += $obj->pass_count;
				$fail_count += $obj->fail_count;
				$exception_count += $obj->exception_count;
				$case_count += $obj->case_count;
			}
		}

		echo $results;
//		echo "<div class=\"all test complete\">{$case_count}/{$case_count} tests complete.  {$fail_count} failed assertions.  {$pass_count} passed assertions.  {$exception_count} exceptions.</div>";
	}

	public static function run_dir($directory = null)
	{
		self::$run_all = true;
		if(!isset($directory)) {
			$directory = dirname(__FILE__);
		}
		$tests = glob($directory . '/test_*.php');
		foreach($tests as $test) {
			include($test);
		}
		self::run_all();
	}
}

class UnitTestResults
{
	private $methods = array();
	private $tests = array();
	private $summaries = array();
	private $options = array();

	function __construct()
	{
		global $options;
		$this->options = $options;
		$this->options['HABARI_PATH'] = HABARI_PATH;
	}

	function __toString()
	{
		if(defined('STDIN')) {
			return $this->out_console();
		}
		else {
			return $this->out_html();
		}
	}

	function test($test)
	{
		$this->tests[] = $test;
	}

	function out_html()
	{
		if(count($this->tests) > 1) {
			$title = "Test Results for " . count($this->tests) . " tests";
		}
		else {
			$title = "Test Results for " . reset($this->tests);
		}

		$output = "<!DOCTYPE HTML><html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"><title>{$title}</title></head><body>";
		foreach($this->tests as $test) {
			$output .= "<h1>{$test}</h1>";

			foreach($this->methods[$test] as $methodname => $messages)
			{
				$output .= "<h2>{$methodname}</h2>";
				foreach($messages as $message) {
					$output .= '<div><em>Fail:</em> ' . $message[0];
					if(count($message) > 1) {
						$output .= '<br/>' . $message[1][0]['file'] . ':' . $message[1][0]['line'];
					}
					$output .= '</div>';
				}
			}

			$summary = $this->summaries[$test];
			$output .= "<div class=\"test complete\">{$summary['case_count']}/{$summary['case_count']} tests complete.  {$summary['fail_count']} failed assertions.  {$summary['pass_count']} passed assertions.  {$summary['exception_count']} exceptions.</div>";
		}

		$output .= '<footer><h3>Options</h3><table>';
		foreach($this->options as $k => $v) {
			$output .= "<tr><th>{$k}</th><td>{$v}</td></tr>";
		}
		$output .= '</table></footer>';

		$output .= '</body></html>';

		return $output;
	}

	function out_console()
	{
		if(count($this->tests) > 1) {
			$title = "Test Results for " . count($this->tests) . " tests";
		}
		else {
			$title = "Test Results for " . reset($this->tests);
		}

		$output = array();
		$output[] = "==== {$title} ====";
		foreach($this->tests as $test) {
			$output[]= "\n=== {$test} ===";

			foreach($this->methods[$test] as $methodname => $messages)
			{
				$output[]= "  {$methodname}";
				foreach($messages as $message) {
					$output[]= '    Fail: ' . $message[0];
					if(count($message) > 1) {
						$output[]= '      ' . $message[1][0]['file'] . ':' . $message[1][0]['line'];
					}
				}
			}

			$summary = $this->summaries[$test];
			$output[]= "\n{$summary['case_count']}/{$summary['case_count']} tests complete.  {$summary['fail_count']} failed assertions.  {$summary['pass_count']} passed assertions.  {$summary['exception_count']} exceptions.";
		}

		$output[]= "\n=== Options ===";
		foreach($this->options as $k => $v) {
			$output[]= "  {$k}: {$v}";
		}

		return implode("\n", $output) . "\n";
	}

	function method_results($test, $method, $results)
	{
		$this->methods[$test][$method] = $results;
	}

	function summary($test, $values)
	{
		$this->summaries[$test] = $values;
	}
}

include HABARI_PATH . '/index.php';

endif;

?>