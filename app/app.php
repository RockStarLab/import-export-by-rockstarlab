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
	public $config;
	public $view;
	public $controller;
	public $model;

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
		$this->model = new \WP_AIE\model\Model_Registry();
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

		$this->config = require_once WP_AIE_PATH . '/app/config.php';

		$this->controller = new \stdClass();
		$this->view       = new \WP_AIE\view\View();

		// Autoload models
		$this->_load_modules( 'model', '/' );

		// Load controllers manually
		$controllers = [
			'init',
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

		$directory = WP_AIE_PATH . '/app/controller/';

		foreach ( $list as $controller_name ) {

			if ( is_file( $directory . $controller_name . '.php' ) ) {
				// Convert controller name to class name (first letter uppercase)
				$class = ucfirst( $controller_name );

				// Avoid recursion
				if ( $class !== get_class( $this ) ) {
					$classPath                          = "\\WP_AIE\\controller\\{$class}";
					$this->controller->$controller_name = new $classPath();
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
