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
	 * @var Ex			'value'    			'processingItems'            => __( 'Processing items... (%1$s / %2$s)', 'wp-advanced-import-export' ),
			'functionOutput'             => __( 'Output: %s', 'wp-advanced-import-export' ),

			// Content Sync
			'addNewSite'                 => __( 'Add New Site', 'wp-advanced-import-export' ),
			'editSite'                   => __( 'Edit Site', 'wp-advanced-import-export' ),
			'saveConnection'             => __( 'Save Connection', 'wp-advanced-import-export' ),
			'hideDetails'                => __( 'Hide Details', 'wp-advanced-import-export' ),
			'showDetails'                => __( 'Show Details', 'wp-advanced-import-export' ),
			'copied'                     => __( 'Copied!', 'wp-advanced-import-export' ),
			'regenerating'               => __( 'Regenerating...', 'wp-advanced-import-export' ),
			'regenerated'                => __( 'Regenerated!', 'wp-advanced-import-export' ),
			'noConnectedSites'           => __( 'No connected sites yet. Add your first connection!', 'wp-advanced-import-export' ),
			'testConnection'             => __( 'Test Connection', 'wp-advanced-import-export' ),
			'edit'                       => __( 'Edit', 'wp-advanced-import-export' ),
			'delete'                     => __( 'Delete', 'wp-advanced-import-export' ),
			'never'                      => __( 'Never', 'wp-advanced-import-export' ),

			// Media Sync       => __( 'Value', 'wp-advanced-import-export' ),
			'errorLoadingPostTypes'      => __( 'Error loading post types', 'wp-advanced-import-export' ),
			'errorLoadingTaxonomies'     => __( 'Error loading taxonomies', 'wp-advanced-import-export' ),

			// Content Updater
			'confirmClearFields'         => __( 'Are you sure you want to clear all selected fields?', 'wp-advanced-import-export' ),
			'confirmClearFunctions'      => __( 'Are you sure you want to clear all function assignments?', 'wp-advanced-import-export' ),ontroller
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
	 * Media Sync Controller
	 *
	 * @var Media_Sync_Controller
	 */
	private $media_sync_controller;

	/**
	 * Media Hash Controller
	 *
	 * @var Media_Hash_Controller
	 */
	private $media_hash_controller;

	/**
	 * Content Updater Controller
	 *
	 * @var Content_Updater_Controller
	 */
	private $content_updater_controller;

	/**
	 * Content Sync Controller
	 *
	 * @var Content_Sync_Controller
	 */
	private $content_sync_controller;

	/**
	 * Content Sync API Controller
	 *
	 * @var Content_Sync_API_Controller
	 */
	private $content_sync_api_controller;

	/**
	 * AI URL Importer Controller
	 *
	 * @var AI_URL_Importer_Controller
	 */
	private $ai_url_importer_controller;

	/**
	 * Cron Manager
	 *
	 * @var \WP_AIE\Model\Queue\Cron_Manager
	 */
	private $cron_manager;

	/**
	 * Settings Controller
	 *
	 * @var Settings_Controller
	 */
	private $settings_controller;

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

		$this->media_sync_controller = new Media_Sync_Controller();
		$this->media_sync_controller->init();

		$this->media_hash_controller = new Media_Hash_Controller();
		$this->media_hash_controller->init();

		$this->content_updater_controller = new Content_Updater_Controller();
		$this->content_updater_controller->init();

		$this->content_sync_controller = new Content_Sync_Controller();
		$this->content_sync_controller->init();
		$this->content_sync_controller->register_post_list_hooks();

		// Initialize REST API controller (safe initialization)
		try {
			$this->content_sync_api_controller = new Content_Sync_API_Controller();
		} catch ( \Exception $e ) {
			// Log error but don't break plugin activation
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}
		}

		$this->ai_url_importer_controller = new AI_URL_Importer_Controller();
		$this->ai_url_importer_controller->init();

		$this->settings_controller = new Settings_Controller();
		$this->settings_controller->init();
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
				'advanced-import-export_page_wp-aie-content-updater',
				'advanced-import-export_page_wp-aie-jobs-log',
				'advanced-import-export_page_wp-aie-media-sync',
				'advanced-import-export_page_wp-aie-ai-url-importer',
				'advanced-import-export_page_wp-aie-functions',
				'advanced-import-export_page_wp-aie-plugin-options',
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
			filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/js/app.js' ),
			array(
				'in_footer' => true,
			)
		);

		// Localize script with AJAX data
		wp_localize_script(
			'wp-advanced-import-export-scripts',
			'aieData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'aie_nonce' ),
				'pluginUrl'      => plugins_url( '', WP_AIE_FILE ),
				'functionsUrl'   => admin_url( 'admin.php?page=wp-aie-functions' ),
				'optionsUrl'     => admin_url( 'admin.php?page=wp-aie-plugin-options' ),
				'currentPage'    => isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '',
				'hasOpenAIApiKey' => \WP_AIE\Helper\AI_Function_Generator::has_api_key(),
				'i18n'           => array(
					// General
					'skip'                       => __( 'Skip', 'wp-advanced-import-export' ),
					'uploading'                  => __( 'Uploading...', 'wp-advanced-import-export' ),
					'processing'                 => __( 'Processing...', 'wp-advanced-import-export' ),
					'completed'                  => __( 'Completed', 'wp-advanced-import-export' ),
					'failed'                     => __( 'Failed', 'wp-advanced-import-export' ),
					'confirmCancel'              => __( 'Are you sure you want to cancel?', 'wp-advanced-import-export' ),
					'errorOccurred'              => __( 'An error occurred', 'wp-advanced-import-export' ),
					'fileTooLarge'               => __( 'File size exceeds maximum allowed', 'wp-advanced-import-export' ),
					'invalidFileType'            => __( 'Invalid file type', 'wp-advanced-import-export' ),
					'selectFile'                 => __( 'Please select a file', 'wp-advanced-import-export' ),
					'selectFields'               => __( 'Please select at least one field', 'wp-advanced-import-export' ),
					'mapFields'                  => __( 'Please map at least one field', 'wp-advanced-import-export' ),
					'name_required'              => __( 'Please enter a function name.', 'wp-advanced-import-export' ),
					'code_required'              => __( 'Please enter the PHP code for your function.', 'wp-advanced-import-export' ),
					'category_required'          => __( 'Please select a category.', 'wp-advanced-import-export' ),

					// Export Step 3
					'fieldAlreadyAdded'          => __( 'This field is already added', 'wp-advanced-import-export' ),
					'confirmRemoveAllFields'     => __( 'Are you sure you want to remove all fields?', 'wp-advanced-import-export' ),
					'functionsSavedSuccess'      => __( 'Functions saved successfully', 'wp-advanced-import-export' ),
					'enterTestValue'             => __( 'Please enter a test value', 'wp-advanced-import-export' ),
					'noFunctionsToTest'          => __( 'No functions to test', 'wp-advanced-import-export' ),
					'configErrorAieData'         => __( 'Configuration error: aieData not found', 'wp-advanced-import-export' ),
					'testFailed'                 => __( 'Test failed', 'wp-advanced-import-export' ),
					'errorTestingPipeline'       => __( 'Error testing pipeline', 'wp-advanced-import-export' ),

					// Jobs Log
					'errorLoadingJobs'           => __( 'Error loading jobs: ', 'wp-advanced-import-export' ),
					'confirmResumeJob'           => __( 'Resume this job?', 'wp-advanced-import-export' ),
					'jobResumedSuccess'          => __( 'Job resumed successfully', 'wp-advanced-import-export' ),
					'errorResumingJob'           => __( 'Error resuming job: ', 'wp-advanced-import-export' ),
					'confirmRestartJob'          => __( 'Restart this job with the same settings?', 'wp-advanced-import-export' ),
					'jobRestartedSuccess'        => __( 'Job restarted successfully', 'wp-advanced-import-export' ),
					'errorRestartingJob'         => __( 'Error restarting job: ', 'wp-advanced-import-export' ),
					'confirmRetryJob'            => __( 'Retry this job with the same settings?', 'wp-advanced-import-export' ),
					'jobCreatedStarting'         => __( 'Job created, starting process...', 'wp-advanced-import-export' ),
					'errorRetryingJob'           => __( 'Error retrying job: ', 'wp-advanced-import-export' ),
					'jobDeletedSuccess'          => __( 'Job deleted successfully', 'wp-advanced-import-export' ),
					'errorDeletingJob'           => __( 'Error deleting job: ', 'wp-advanced-import-export' ),
					'downloadFailed'             => __( 'Download failed', 'wp-advanced-import-export' ),
					'failedGenerateDownloadUrl'  => __( 'Failed to generate download URL', 'wp-advanced-import-export' ),
					'errorLoadingJobDetails'     => __( 'Error loading job details: ', 'wp-advanced-import-export' ),

					// Content Sync
					'failedLoadSites'            => __( 'Failed to load sites', 'wp-advanced-import-export' ),
					'confirmDeleteSiteConnection' => __( 'Are you sure you want to delete this site connection?', 'wp-advanced-import-export' ),
					'failedDeleteSite'           => __( 'Failed to delete site', 'wp-advanced-import-export' ),
					'connectionTestFailed'       => __( 'Connection test failed', 'wp-advanced-import-export' ),
					'confirmRegenerateSiteKey'   => __( 'Are you sure you want to regenerate this site\'s API key?\n\nThis will break the connection with the remote site until you update the key there.', 'wp-advanced-import-export' ),
					'newApiKey'                  => __( 'New API Key: ', 'wp-advanced-import-export' ),
					'failedRegenerateKey'        => __( 'Failed to regenerate key', 'wp-advanced-import-export' ),
					'apiKeyCopied'               => __( 'API key copied to clipboard', 'wp-advanced-import-export' ),
					'confirmRegenerateMyKey'     => __( 'Are you sure you want to regenerate your API key?\n\nThis will invalidate the current key and all remote sites will need to update their connection settings with the new key.', 'wp-advanced-import-export' ),
					'failedRegenerateApiKey'     => __( 'Failed to regenerate API key', 'wp-advanced-import-export' ),

					// Media Sync
					'enterFolderPath'            => __( 'Please enter a folder path', 'wp-advanced-import-export' ),
					'requestFailed'              => __( 'Request failed', 'wp-advanced-import-export' ),
					'noFilesToSync'              => __( 'No files to sync. Please scan a folder first.', 'wp-advanced-import-export' ),
					'invalidFolderPath'          => __( 'Invalid folder path', 'wp-advanced-import-export' ),
					'syncStarted'                => __( 'Synchronization started', 'wp-advanced-import-export' ),
					'syncPaused'                 => __( 'Sync paused', 'wp-advanced-import-export' ),
					'syncResumed'                => __( 'Sync resumed', 'wp-advanced-import-export' ),
					'confirmCancelSync'          => __( 'Are you sure you want to cancel the synchronization?\n\nThis will stop the process and you\'ll need to start over.', 'wp-advanced-import-export' ),
					'syncCancelled'              => __( 'Sync cancelled', 'wp-advanced-import-export' ),

					// Post Sync
					'selectAtLeastOnePost'       => __( 'Please select at least one post', 'wp-advanced-import-export' ),
					'selectSite'                 => __( 'Please select a site', 'wp-advanced-import-export' ),
					'noPostsSelected'            => __( 'No posts selected', 'wp-advanced-import-export' ),

					// Export
					'exportStartedSuccess'       => __( 'Export started successfully', 'wp-advanced-import-export' ),
					'exportCompletedSuccess'     => __( 'Export completed successfully!', 'wp-advanced-import-export' ),
					'confirmCancelExport'        => __( 'Are you sure you want to cancel this export?', 'wp-advanced-import-export' ),
					'exportCancelled'            => __( 'Export cancelled', 'wp-advanced-import-export' ),

					// AI URL Importer
					'testing'                    => __( 'Testing...', 'wp-advanced-import-export' ),
					'testConnection'             => __( 'Test Connection', 'wp-advanced-import-export' ),
					'generatingPreview'          => __( 'Generating Preview...', 'wp-advanced-import-export' ),
					'generatePreview'            => __( 'Generate Preview', 'wp-advanced-import-export' ),
					'failedLoadAcfFields'        => __( 'Failed to load ACF fields. Please try again.', 'wp-advanced-import-export' ),
					'noAcfFields'                => __( 'No ACF fields found for this post type.', 'wp-advanced-import-export' ),
					'noImagesFound'              => __( 'No images found', 'wp-advanced-import-export' ),
					'noFeaturedImage'            => __( 'No featured image selected', 'wp-advanced-import-export' ),
				'confirmCancelImport'        => __( 'Are you sure you want to cancel this import?', 'wp-advanced-import-export' ),
				'failedCancelImport'         => __( 'Failed to cancel the import. Please try again.', 'wp-advanced-import-export' ),
				'error'                      => __( 'Error', 'wp-advanced-import-export' ),
				'rateLimitReached'           => __( 'Rate Limit Reached', 'wp-advanced-import-export' ),
				'importCompleted'            => __( 'Import completed! %s URLs imported successfully.', 'wp-advanced-import-export' ),
				'importFailed'               => __( 'Import failed: %s', 'wp-advanced-import-export' ),					// Import
					'showingFirstRows'           => __( 'Showing first 5 rows', 'wp-advanced-import-export' ),
					'pleaseSelectTable'          => __( 'Please select a database table above to see available columns', 'wp-advanced-import-export' ),
					'selectTable'                => __( 'Select a table...', 'wp-advanced-import-export' ),
					'noTablesFound'              => __( 'No tables found', 'wp-advanced-import-export' ),
					'errorLoadingTables'         => __( 'Error loading tables', 'wp-advanced-import-export' ),
				'loading'                    => __( 'Loading...', 'wp-advanced-import-export' ),
				'errorLoadingColumns'        => __( 'Error loading columns', 'wp-advanced-import-export' ),
				'loadingTableColumns'        => __( 'Loading table columns...', 'wp-advanced-import-export' ),
				'pleaseEnterFieldName'       => __( 'Please enter a field name', 'wp-advanced-import-export' ),
				'failedTestPipeline'         => __( 'Failed to test pipeline', 'wp-advanced-import-export' ),
				'confirmCancelImportStep'    => __( 'Are you sure you want to cancel this import?', 'wp-advanced-import-export' ),

				// Export (additional strings)
				'exportComplete'             => __( 'Export Complete!', 'wp-advanced-import-export' ),
				'selectPostType'             => __( 'Select Post Type', 'wp-advanced-import-export' ),
				'selectPostTypePlaceholder'  => __( 'Select Post Type...', 'wp-advanced-import-export' ),
				'selectTaxonomy'             => __( 'Select Taxonomy', 'wp-advanced-import-export' ),
				'selectTaxonomyPlaceholder'  => __( 'Select Taxonomy...', 'wp-advanced-import-export' ),
				'selectTablePlaceholder'     => __( 'Select Table...', 'wp-advanced-import-export' ),
				'selectField'                => __( 'Select Field...', 'wp-advanced-import-export' ),
				'value'                      => __( 'Value', 'wp-advanced-import-export' ),
				'errorLoadingPostTypes'      => __( 'Error loading post types', 'wp-advanced-import-export' ),
				'errorLoadingTaxonomies'     => __( 'Error loading taxonomies', 'wp-advanced-import-export' ),
				
				// Export Step 3
				'assignFunctionsTitle'       => __( 'Assign functions', 'wp-advanced-import-export' ),
				'remove'                     => __( 'Remove', 'wp-advanced-import-export' ),
				'functions'                  => __( 'function(s)', 'wp-advanced-import-export' ),
				'enterColumnName'            => __( 'Enter column name:', 'wp-advanced-import-export' ),
				'noFieldsSelected'           => __( 'No Fields Selected', 'wp-advanced-import-export' ),
				'pleaseSelectFieldMessage'   => __( 'Please select at least one field to continue with the export.', 'wp-advanced-import-export' ),
				'addAll'                     => __( 'Add all', 'wp-advanced-import-export' ),
				'addAllFieldsTitle'          => __( 'Add all fields from this category', 'wp-advanced-import-export' ),
				'loadingAcfFields'           => __( 'Loading ACF fields...', 'wp-advanced-import-export' ),
				'loadingYoastFields'         => __( 'Loading Yoast SEO fields...', 'wp-advanced-import-export' ),
				'noFunctionsAvailableYet'    => __( 'No functions available yet.', 'wp-advanced-import-export' ),
				'createFirstFunction'        => __( 'Create your first custom function to get started.', 'wp-advanced-import-export' ),
				'noFunctionsFound'           => __( 'No %s functions found.', 'wp-advanced-import-export' ),
				'errorLabel'                 => __( 'Error: %s', 'wp-advanced-import-export' ),

			// Content Updater
			'confirmClearFields'         => __( 'Are you sure you want to clear all selected fields?', 'wp-advanced-import-export' ),
			'confirmClearFunctions'      => __( 'Are you sure you want to clear all function assignments?', 'wp-advanced-import-export' ),
			'confirmCancelUpdate'        => __( 'Are you sure you want to cancel the update?', 'wp-advanced-import-export' ),
			'processingItems'            => __( 'Processing items... (%1$s / %2$s)', 'wp-advanced-import-export' ),
			'premiumOnlyFeature'         => __( 'This content type is only available in the Premium version. Upgrade to unlock this feature.', 'wp-advanced-import-export' ),
			'pleaseSelectContentType'    => __( 'Please select a content type', 'wp-advanced-import-export' ),
			'pleaseSelectAtLeastOneField' => __( 'Please select at least one field to update', 'wp-advanced-import-export' ),
			'pleaseAssignFunction'       => __( 'Please assign at least one function to a field', 'wp-advanced-import-export' ),
			'fieldAlreadySelected'       => __( 'Field "%s" is already selected', 'wp-advanced-import-export' ),
			'noFieldsSelected'           => __( 'No fields selected. Please go back and select fields first.', 'wp-advanced-import-export' ),
			'assignFunctions'            => __( 'Assign Functions', 'wp-advanced-import-export' ),
			'noFunctionsAvailable'       => __( 'No functions available. Create a custom function first.', 'wp-advanced-import-export' ),
			'add'                        => __( 'Add', 'wp-advanced-import-export' ),
			'enterTestValue'             => __( 'Enter a test value:', 'wp-advanced-import-export' ),
			'noFunctionAssigned'         => __( 'No function assigned to this field', 'wp-advanced-import-export' ),
			'functionTestFailed'         => __( 'Function test failed', 'wp-advanced-import-export' ),
			'updateStarted'              => __( 'Update started successfully', 'wp-advanced-import-export' ),
			'failedStartUpdate'          => __( 'Failed to start update', 'wp-advanced-import-export' ),
			'noFieldsSelectedError'      => __( 'No fields selected. Please go back and select fields to update.', 'wp-advanced-import-export' ),
			'noFunctionsAssigned'        => __( 'No functions assigned. Please go back and assign functions to fields.', 'wp-advanced-import-export' ),
			'updateCancelled'            => __( 'Update cancelled', 'wp-advanced-import-export' ),
			'functionAssignmentsCleared' => __( 'All function assignments cleared', 'wp-advanced-import-export' ),
			'enterFunctionId'            => __( 'Enter function ID to apply to all fields (or leave empty for none):', 'wp-advanced-import-export' ),
			'functionAppliedToAll'       => __( 'Function applied to all fields', 'wp-advanced-import-export' ),
			'pleaseEnterTestValue'       => __( 'Please enter a test value', 'wp-advanced-import-export' ),
			'noFunctionsToTest'          => __( 'No functions to test', 'wp-advanced-import-export' ),
			'testFailed'                 => __( 'Test failed', 'wp-advanced-import-export' ),
			'configurationError'         => __( 'Configuration error: aieData not found', 'wp-advanced-import-export' ),
			'errorTestingPipeline'       => __( 'Error testing pipeline', 'wp-advanced-import-export' ),
			'input'                      => __( 'Input', 'wp-advanced-import-export' ),
			// Filter conditions
			'equals'                     => __( 'Equals', 'wp-advanced-import-export' ),
			'notEquals'                  => __( 'Not Equals', 'wp-advanced-import-export' ),
			'contains'                   => __( 'Contains', 'wp-advanced-import-export' ),
			'notContains'                => __( 'Not Contains', 'wp-advanced-import-export' ),
			'startsWith'                 => __( 'Starts With', 'wp-advanced-import-export' ),
			'endsWith'                   => __( 'Ends With', 'wp-advanced-import-export' ),
			'isEmpty'                    => __( 'Is Empty', 'wp-advanced-import-export' ),
			'isNotEmpty'                 => __( 'Is Not Empty', 'wp-advanced-import-export' ),
			'greaterThan'                => __( 'Greater Than', 'wp-advanced-import-export' ),
			'lessThan'                   => __( 'Less Than', 'wp-advanced-import-export' ),
			'greaterOrEqual'             => __( 'Greater or Equal', 'wp-advanced-import-export' ),
			'lessOrEqual'                => __( 'Less or Equal', 'wp-advanced-import-export' ),
			'between'                    => __( 'Between', 'wp-advanced-import-export' ),
			'onDate'                     => __( 'On Date', 'wp-advanced-import-export' ),
			'before'                     => __( 'Before', 'wp-advanced-import-export' ),
			'after'                      => __( 'After', 'wp-advanced-import-export' ),				// Content Sync
				'addNewSite'                 => __( 'Add New Site', 'wp-advanced-import-export' ),
				'editSite'                   => __( 'Edit Site', 'wp-advanced-import-export' ),
				'saveConnection'             => __( 'Save Connection', 'wp-advanced-import-export' ),
				'hideDetails'                => __( 'Hide Details', 'wp-advanced-import-export' ),
				'showDetails'                => __( 'Show Details', 'wp-advanced-import-export' ),
				'copied'                     => __( 'Copied!', 'wp-advanced-import-export' ),
				'regenerating'               => __( 'Regenerating...', 'wp-advanced-import-export' ),
				'regenerated'                => __( 'Regenerated!', 'wp-advanced-import-export' ),
				'updating'                   => __( 'Updating...', 'wp-advanced-import-export' ),
				'validatingSaving'           => __( 'Validating & Saving...', 'wp-advanced-import-export' ),
				'validatingApiKey'           => __( 'Validating API key...', 'wp-advanced-import-export' ),
				'pleaseWaitVerifying'        => __( 'Please wait while we verify the connection to the remote site.', 'wp-advanced-import-export' ),
				'operationCompleted'         => __( 'Operation completed successfully', 'wp-advanced-import-export' ),
				'noChanges'                  => __( 'No Changes', 'wp-advanced-import-export' ),
				'success'                    => __( 'Success!', 'wp-advanced-import-export' ),
				'validationFailed'           => __( 'Validation Failed', 'wp-advanced-import-export' ),
				'failedSaveSiteConnection'   => __( 'Failed to save site connection', 'wp-advanced-import-export' ),
				'connectionError'            => __( 'Connection Error', 'wp-advanced-import-export' ),
				'unexpectedError'            => __( 'An unexpected error occurred while trying to save the site connection.', 'wp-advanced-import-export' ),
				'connectionFailed'           => __( 'Connection Failed', 'wp-advanced-import-export' ),
				'possibleReasons'            => __( 'Possible reasons:', 'wp-advanced-import-export' ),
				'urlIncorrect'               => __( '- The URL is incorrect or not accessible', 'wp-advanced-import-export' ),
				'remoteSiteOffline'          => __( '- The remote site is offline', 'wp-advanced-import-export' ),
				'networkFirewall'            => __( '- Network or firewall issues are blocking the connection', 'wp-advanced-import-export' ),
				'invalidApiKey'              => __( 'Invalid API Key', 'wp-advanced-import-export' ),
				'toResolveIssue'             => __( 'To resolve this issue:', 'wp-advanced-import-export' ),
				'goToContentSync'            => __( '- Go to Content Sync page on the remote site', 'wp-advanced-import-export' ),
				'clickShowDetails'           => __( '- Click "Show Details" to reveal the API key', 'wp-advanced-import-export' ),
				'copyEntireKey'              => __( '- Copy the entire key and paste it here', 'wp-advanced-import-export' ),
				'pluginNotFound'             => __( 'Plugin Not Found', 'wp-advanced-import-export' ),
				'duplicateConnection'        => __( 'Duplicate Connection', 'wp-advanced-import-export' ),
				'siteAlreadyConnected'       => __( 'This site URL is already in your connected sites list.', 'wp-advanced-import-export' ),
				'validationError'            => __( 'Validation Error', 'wp-advanced-import-export' ),
				'networkError'               => __( 'Network Error', 'wp-advanced-import-export' ),
				'unableConnectServer'        => __( 'Unable to connect to the server. Please check your internet connection.', 'wp-advanced-import-export' ),
				'serverError'                => __( 'Server Error', 'wp-advanced-import-export' ),
				'serverReturnedError'        => __( 'The server returned an error (%s). Please try again later.', 'wp-advanced-import-export' ),
				'notFound'                   => __( 'Not Found', 'wp-advanced-import-export' ),
				'endpointNotFound'           => __( 'The requested endpoint was not found. Please check if the plugin is properly installed.', 'wp-advanced-import-export' ),

				// Media Sync
				'scanning'                   => __( 'Scanning...', 'wp-advanced-import-export' ),
				'starting'                   => __( 'Starting...', 'wp-advanced-import-export' ),
				'processing'                 => __( 'Processing...', 'wp-advanced-import-export' ),
				'syncPaused'                 => __( 'Synchronization Paused', 'wp-advanced-import-export' ),
				'paused'                     => __( 'Paused', 'wp-advanced-import-export' ),
				'resume'                     => __( 'Resume', 'wp-advanced-import-export' ),
				'syncInProgress'             => __( 'Synchronization in Progress', 'wp-advanced-import-export' ),
				'pause'                      => __( 'Pause', 'wp-advanced-import-export' ),
				'startSync'                  => __( 'Start Sync', 'wp-advanced-import-export' ),
				'scanFolder'                 => __( 'Scan Folder', 'wp-advanced-import-export' ),

				// Jobs Log
				'noJobsFound'                => __( 'No jobs found.', 'wp-advanced-import-export' ),
				'showingJobs'                => __( 'Showing %1$s-%2$s of %3$s jobs', 'wp-advanced-import-export' ),
			),
		)
	);		// Localize script for Content Sync page
		if ( 'advanced-import-export_page_wp-aie-content-sync' === $admin_page ) {
			wp_localize_script(
				'wp-advanced-import-export-scripts',
				'aieContentSync',
				array(
					'nonce'     => wp_create_nonce( 'aie_nonce' ),
					'isPremium' => function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code(),
				)
			);
		}

		wp_enqueue_style(
			'wp-advanced-import-export-styles',
			plugins_url( 'assets/css/app.css', WP_AIE_FILE ),
			false,
			filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/css/app.css' )
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
			__( 'Content Updater', 'wp-advanced-import-export' ),
			__( 'Content Updater', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-content-updater',
			array( $this, 'display_content_updater_page' )
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
			__( 'AI URL Importer', 'wp-advanced-import-export' ),
			__( 'AI URL Importer', 'wp-advanced-import-export' ) . ' 🤖',
			'manage_options',
			'wp-aie-ai-url-importer',
			array( $this, 'display_ai_url_importer_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Functions', 'wp-advanced-import-export' ),
			__( 'Functions', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-functions',
			array( $this, 'display_settings_functions_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Jobs Log', 'wp-advanced-import-export' ),
			__( 'Jobs Log', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-jobs-log',
			array( $this, 'display_jobs_log_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Plugin Options', 'wp-advanced-import-export' ),
			__( 'Plugin Options', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-plugin-options',
			array( $this, 'display_plugin_options_page' )
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
	 * Display Content Updater Settings Page
	 */
	function display_content_updater_page() {
		WP_AIE()->View->load( 'settings/content_updater' );
	}

	/**
	 * Display Media Sync Settings Page
	 */
	function display_media_sync_page() {
		WP_AIE()->View->load( 'settings/media_sync' );
	}

	/**
	 * Display AI URL Importer Page
	 */
	function display_ai_url_importer_page() {
		WP_AIE()->View->load( 'settings/ai_url_importer' );
	}

	/**
	 * Display Jobs Log Page
	 */
	function display_jobs_log_page() {
		WP_AIE()->View->load( 'settings/jobs-log' );
	}

	/**
	 * Display "Functions" admin page
	 */
	function display_settings_functions_page() {
		WP_AIE()->View->load( 'settings/functions' );
	}

	/**
	 * Display Plugin Options Page
	 */
	function display_plugin_options_page() {
		WP_AIE()->View->load( 'settings/plugin_options' );
	}
}
