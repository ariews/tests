<?php

class TestsPlugin extends Plugin
{
	private $attributes = array(
		'name',
		'cases',
		'complete',
		'fail',
		'pass',
		'exception',
		'incomplete',
	);

	public function filter_admin_access( $access, $page, $post_type )
	{
		if ( $page != 'tests') {
			return $access;
		}

		return true;
	}

	public function action_init()
	{
		$this->add_template('tests_admin', dirname($this->get_file()) . '/plugin_admin.php');
	}


	public function filter_adminhandler_post_loadplugins_main_menu( array $menu )
	{
		$item_menu = array( 'tests' =>
			array(
				'url' => URL::get( 'admin', 'page=tests'),
				'title' => _t('Tests'),
				'text' => _t('Tests'),
				'hotkey' => 'S',
				'selected' => false
			)
		);

		$slice_point = array_search( 'groups', array_keys( $menu ) ); // Element will be inserted before "groups"
		$pre_slice = array_slice( $menu, 0, $slice_point);
		$post_slice = array_slice( $menu, $slice_point);

		$menu = array_merge( $pre_slice, $item_menu, $post_slice );

		return $menu;
	}


	/**
	 * @todo Removing initial newline shouldn't be necessary, find out what's causing it
	 */
	public function action_admin_theme_get_tests( AdminHandler $handler, Theme $theme )
	{
		$url = $this->get_url('/index.php?c=symbolic');
		$test_list = new SimpleXMLElement(preg_replace("/^\n/", "", file_get_contents($url.'&d=1')));

		$output = '';
		$units = array();
		foreach ($test_list->unit as $unit) {
			$units[] = (string)$unit->attributes()->name;
		}

		if (isset($_GET['run']) && isset($_GET['test'])) {
			$test = $_GET['test'];
			if ($test != 'all') {
				$url = $this->get_url('/index.php?c=symbolic&u='.$test);
			}
			$results = new SimpleXMLElement(preg_replace("/^\n/", "", file_get_contents($url)));
			$theme->results = $results;

			foreach ($results as $test_result) {
				if ($test_result->attributes()->name == $test) {
					$results_array = array();
					foreach($this->attributes as $attribute) {
						$results_array = array_merge( $results_array, array( $attribute => (string)$test_result->attributes()->$attribute ));
						$methods_array = array();

						foreach($test_result->method as $method) {
							$methods_array = array_merge( $methods_array, array(
								'result' => "pass",
								'name' => (string)$method['name'],
							));
						}
						$results_array = array_merge( $results_array, array( 'methods' => $methods_array ));
					}
					$theme->results = $results_array;
					break;
				}
			}
			$theme->test = $test;



			$theme->test = $test;
		}

		$theme->content = $output;
		$theme->units = $units;
		$theme->display('header');
		$theme->display('tests_admin');
		$theme->display('footer');
		exit;
	}
}

?>
