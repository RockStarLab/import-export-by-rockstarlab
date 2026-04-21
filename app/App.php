<?php
/**
 * Main App Class
 *
 * Singleton class for plugin initialization and global access.
 *
 * @package RockStarLab\ImportExport
 */
namespace RockStarLab\ImportExport;

defined( 'ABSPATH' ) or exit;

class App {

	private static $instance = null;

	/**
	 * @var array Configuration array
	 */
	public $Config;

	/**
	 * @var \RockStarLab\ImportExport\View\View View instance
	 */
	public $View;

	/**
	 * @var \stdClass Controller container
	 */
	public $Controller;

	/**
	 * @var \RockStarLab\ImportExport\Model\Model_Registry Model registry instance
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
		$this->Model = new \RockStarLab\ImportExport\Model\Model_Registry();
	}

	private function __clone() {
	}

	/**
	 * Run the core
	 **/
	public function run() {
		// Check if database tables exist and create if needed
		if ( ! \RockStarLab\ImportExport\Helper\Database_Migration::tables_exist() ) {
			\RockStarLab\ImportExport\Helper\Database_Migration::create_tables();
		} else {
			// Check if database needs migration (version changed)
			$current_version = \RockStarLab\ImportExport\Helper\Database_Migration::get_version();
			$latest_version  = \RockStarLab\ImportExport\Helper\Database_Migration::DB_VERSION;

			if ( version_compare( $current_version, $latest_version, '<' ) ) {
				\RockStarLab\ImportExport\Helper\Database_Migration::create_tables();
			}
		}

		// Initialize Media Hash helper to add MD5 hashes to all uploads
		\RockStarLab\ImportExport\Helper\Media_Hash::init();

		// Initialize Chunk Upload handler for large file uploads
		new \RockStarLab\ImportExport\Helper\Chunk_Upload();

		// Load core classes
		$this->_dispatch();
	}

	/**
	 * Load and instantiate all application
	 * classes neccessary for this theme
	 **/
	private function _dispatch() {

		$this->Config = require_once RSL_IE_PATH . '/app/config.php';

		$this->Controller = new \stdClass();
		$this->View       = new \RockStarLab\ImportExport\View\View();

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

		$directory = RSL_IE_PATH . '/app/' . $layer . $dir;
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
					$classPath            = "\\RockStarLab\ImportExport\\{$layer}\\{$class}";
					$this->$layer->$class = new $classPath();
				}
			}
		}
	}

	/**
	 * Autoload controllers in specific order
	 */
	private function _load_controllers( $list ) {

		$directory = RSL_IE_PATH . '/app/Controller/';

		foreach ( $list as $controller_name ) {

			if ( is_file( $directory . $controller_name . '.php' ) ) {
				// Convert controller name to class name (first letter uppercase)
				$class = ucfirst( $controller_name );

				// Avoid recursion
				if ( $class !== get_class( $this ) ) {
					$classPath                          = "\\RockStarLab\ImportExport\\Controller\\{$class}";
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
