<?php

namespace WP_AIE;

defined( 'ABSPATH' ) or exit;

/**
 * Main App Class
 *
 * Singleton class for plugin initialization and global access.
 *
 * @package WP_AIE
 */
class App {

	private static $instance = null;

	/**
	 * @var array Configuration array
	 */
	public $Config;

	/**
	 * @var \WP_AIE\View\View View instance
	 */
	public $View;

	/**
	 * @var \stdClass Controller container
	 */
	public $Controller;

	/**
	 * @var \WP_AIE\Model\Model_Registry Model registry instance
	 */
	public $Model;

	/**
	 * @return static
	 **/
	public static function getInstance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	private function __construct() {
		// Initialize model registry
		$this->Model = new \WP_AIE\Model\Model_Registry();
	}

	private function __clone() {
	}

	/**
	 * Run the core
	 **/
	public function run() {

		// Load core classes
		$this->_dispatch();
	}

	/**
	 * Load and instantiate all application
	 * classes neccessary for this theme
	 **/
	private function _dispatch() {

		$this->Config = require_once WP_AIE_PATH . '/app/config.php';

		$this->Controller = new \stdClass();
		$this->View       = new \WP_AIE\View\View();

		// Load controllers manually
		$controllers = [
			'Init',
		];

		$this->_load_controllers( $controllers );
	}

	/**
	 * Autoload core modules in a specific directory
	 *
	 * @param string
	 * @param string
	 * @param bool
	 **/
	private function _load_modules( $layer, $dir = '/' ) {

		$directory = WP_AIE_PATH . '/app/' . $layer . $dir;
		$handle    = opendir( $directory );

		if ( count( glob( "$directory/*" ) ) === 0 ) {
			return false;
		}

		while ( false !== ( $file = readdir( $handle ) ) ) {

			if ( is_file( $directory . $file ) ) {

				// Figure out class name from file name
				$class = str_replace( '.php', '', $file );

				// Avoid recursion
				if ( $class !== get_class( $this ) ) {
					$classPath            = "\\WP_AIE\\{$layer}\\{$class}";
					$this->$layer->$class = new $classPath();
				}
			}
		}
	}

	/**
	 * Autoload controllers in specific order
	 */
	private function _load_controllers( $list ) {

		$directory = WP_AIE_PATH . '/app/Controller/';

		foreach ( $list as $controller_name ) {

			if ( is_file( $directory . $controller_name . '.php' ) ) {
				// Convert controller name to class name (first letter uppercase)
				$class = ucfirst( $controller_name );

				// Avoid recursion
				if ( $class !== get_class( $this ) ) {
					$classPath                          = "\\WP_AIE\\Controller\\{$class}";
					$this->Controller->$controller_name = new $classPath();
				}
			}
		}
	}

	/**
	 * Deactivate plugin hook
	 */
	public static function deactivate_cleanup() {
	}
}
