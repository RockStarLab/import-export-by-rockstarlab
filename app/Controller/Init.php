<?php

namespace WP_AIE\Controller;

defined( 'ABSPATH' ) or exit;

/**
 * Init Controller Class
 *
 * Handles plugin initialization, hooks, and admin pages.
 *
 * @package WP_AIE\Controller
 */
class Init {

	/**
	 * Import Controller
	 *
	 * @var Import_Controller
	 */
	private $import_controller;

	/**
	 * Export Controller
	 *
	 * @var Export_Controller
	 */
	private $export_controller;

	/**
	 * Job Controller
	 *
	 * @var Job_Controller
	 */
	private $job_controller;

	/**
	 * Constructor
	 **/
	function __construct() {

		// load plugin translations
		add_action( 'init', [ $this, 'load_translations' ] );

		// load admin scripts and styles
		add_action( 'admin_enqueue_scripts', [ $this, 'load_admin_assets' ] );

		// add settings pages
		add_action( 'admin_menu', [ $this, 'add_settings_pages' ] );

		// Initialize AJAX controllers
		add_action( 'init', [ $this, 'init_controllers' ] );
	}

	/**
	 * Initialize AJAX controllers
	 */
	function init_controllers() {
		$this->import_controller = new Import_Controller();
		$this->import_controller->init();

		$this->export_controller = new Export_Controller();
		$this->export_controller->init();

		$this->job_controller = new Job_Controller();
		$this->job_controller->init();
	}

	/**
	 * Load plugin translations
	 */
	function load_translations() {

		load_plugin_textdomain( 'wp-advanced-import-export', false, dirname( plugin_basename( WP_AIE_FILE ) ) . '/languages' );
	}

	/**
	 * Load admin scripts
	 */
	function load_admin_assets( $admin_page ) {

		if ( ! in_array(
			$admin_page,
			[
				'toplevel_page_wp-aie-import',
				'import-export-pro_page_wp-aie-export',
				'import-export-pro_page_wp-aie-media-sync',
				'import-export-pro_page_wp-aie-functions',
			]
		) ) {
			return;
		}

		wp_enqueue_script(
			'wp-advanced-import-export-scripts',
			plugins_url( 'assets/js/app.js', WP_AIE_FILE ),
			[ 'jquery' ],
			'1.0.1',
			[
				'in_footer' => true,
			]
		);

		wp_enqueue_style(
			'wp-advanced-import-export-styles',
			plugins_url( 'assets/css/app.css', WP_AIE_FILE ),
			false,
			'1.0.0',
		);
	}

	/**
	 * Add plugin's settings pages
	 */
	function add_settings_pages() {

		add_menu_page(
			__( 'Advanced Import Export', 'wp-advanced-import-export' ),
			__( 'Advanced Import Export', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-advanced-import-export',
			[ $this, 'display_settings_import_page' ],
			'dashicons-update-alt',
			99,
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Import', 'wp-advanced-import-export' ),
			__( 'Import', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-advanced-import-export',
			[ $this, 'display_settings_import_page' ]
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Export', 'wp-advanced-import-export' ),
			__( 'Export', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-export',
			[ $this, 'display_settings_export_page' ]
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Content Sync', 'wp-advanced-import-export' ),
			__( 'Content Sync', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-content-sync',
			[ $this, 'display_content_sync_page' ]
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Media Sync', 'wp-advanced-import-export' ),
			__( 'Media Sync', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-media-sync',
			[ $this, 'display_media_sync_page' ]
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Functions', 'wp-advanced-import-export' ),
			__( 'Functions', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-functions',
			[ $this, 'display_settings_functions_page' ]
		);
	}

	/**
	 * Display Import Settings Page
	 */
	function display_settings_import_page() {
		WP_AIE()->View->load( 'settings/import' );
	}

	/**
	 * Display Export Settings Page
	 */
	function display_settings_export_page() {
		WP_AIE()->View->load( 'settings/export' );
	}

	/**
	 * Display Content Sync Settings Page
	 */
	function display_content_sync_page() {
		WP_AIE()->View->load( 'settings/content_sync' );
	}

	/**
	 * Display Media Sync Settings Page
	 */
	function display_media_sync_page() {
		WP_AIE()->View->load( 'settings/media_sync' );
	}

	/**
	 * Display "Functions" admin page
	 */
	function display_settings_functions_page() {
		WP_AIE()->View->load( 'settings/functions' );
	}
}
