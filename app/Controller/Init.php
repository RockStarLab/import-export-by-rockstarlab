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
	 * Functions Controller
	 *
	 * @var Functions_Controller
	 */
	private $functions_controller;

	/**
	 * Cron Manager
	 *
	 * @var \WP_AIE\Model\Queue\Cron_Manager
	 */
	private $cron_manager;

	/**
	 * Constructor
	 **/
	function __construct() {

		// load plugin translations
		add_action( 'init', array( $this, 'load_translations' ) );

		// load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );

		// add settings pages
		add_action( 'admin_menu', array( $this, 'add_settings_pages' ) );

		// Initialize AJAX controllers
		add_action( 'init', array( $this, 'init_controllers' ) );

		// Initialize cron manager
		add_action( 'init', array( $this, 'init_cron_manager' ) );
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

		$this->functions_controller = new Functions_Controller();
		$this->functions_controller->init();
	}

	/**
	 * Initialize Cron Manager
	 */
	function init_cron_manager() {
		$this->cron_manager = new \WP_AIE\Model\Queue\Cron_Manager();
		$this->cron_manager->init();
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
			array(
				'toplevel_page_wp-advanced-import-export',
				'advanced-import-export_page_wp-aie-export',
				'advanced-import-export_page_wp-aie-content-sync',
				'advanced-import-export_page_wp-aie-media-sync',
				'advanced-import-export_page_wp-aie-functions',
			)
		) ) {
			return;
		}

		// Enqueue CodeMirror for code editor
		if ( 'advanced-import-export_page_wp-aie-functions' === $admin_page ) {
			wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		}

		wp_enqueue_script(
			'wp-advanced-import-export-scripts',
			plugins_url( 'assets/js/app.js', WP_AIE_FILE ),
			array( 'jquery' ),
			'1.0.1',
			array(
				'in_footer' => true,
			)
		);

		// Localize script with AJAX data
		wp_localize_script(
			'wp-advanced-import-export-scripts',
			'aieData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'aie_nonce' ),
				'pluginUrl'   => plugins_url( '', WP_AIE_FILE ),
				'currentPage' => isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '',
				'i18n'        => array(
					'skip'            => __( 'Skip', 'wp-aie' ),
					'uploading'       => __( 'Uploading...', 'wp-aie' ),
					'processing'      => __( 'Processing...', 'wp-aie' ),
					'completed'       => __( 'Completed', 'wp-aie' ),
					'failed'          => __( 'Failed', 'wp-aie' ),
					'confirmCancel'   => __( 'Are you sure you want to cancel?', 'wp-aie' ),
					'errorOccurred'   => __( 'An error occurred', 'wp-aie' ),
					'fileTooLarge'    => __( 'File size exceeds maximum allowed', 'wp-aie' ),
					'invalidFileType' => __( 'Invalid file type', 'wp-aie' ),
					'selectFile'      => __( 'Please select a file', 'wp-aie' ),
					'selectFields'    => __( 'Please select at least one field', 'wp-aie' ),
					'mapFields'       => __( 'Please map at least one field', 'wp-aie' ),
				),
			)
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
			array( $this, 'display_settings_import_page' ),
			'dashicons-update-alt',
			99,
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Import', 'wp-advanced-import-export' ),
			__( 'Import', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-advanced-import-export',
			array( $this, 'display_settings_import_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Export', 'wp-advanced-import-export' ),
			__( 'Export', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-export',
			array( $this, 'display_settings_export_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Content Sync', 'wp-advanced-import-export' ),
			__( 'Content Sync', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-content-sync',
			array( $this, 'display_content_sync_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Media Sync', 'wp-advanced-import-export' ),
			__( 'Media Sync', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-media-sync',
			array( $this, 'display_media_sync_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Functions', 'wp-advanced-import-export' ),
			__( 'Functions', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-functions',
			array( $this, 'display_settings_functions_page' )
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
