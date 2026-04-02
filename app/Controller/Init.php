<?php
/**
 * Init Controller Class
 *
 * Handles plugin initialization, hooks, and admin pages.
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

defined( 'ABSPATH' ) or exit;

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

		// Handle welcome page redirect
		add_action( 'admin_init', array( $this, 'welcome_redirect' ) );

		// Show 5-star review request notice (plugin pages only, after 1 week)
		\WP_AIE\Helper\Review_Notice::init();

		// Fix attachment URLs for "keep in current directory" mode files outside uploads.
		add_filter( 'wp_get_attachment_url', array( $this, 'fix_keep_mode_attachment_url' ), 10, 2 );
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
	 *
	 * Note: load_plugin_textdomain() is no longer needed since WordPress 4.6.
	 * WordPress automatically loads translations from the languages directory.
	 */
	function load_translations() {
		// Translations are loaded automatically by WordPress since version 4.6.
	}

	/**
	 * Load admin scripts
	 */
	function load_admin_assets( $admin_page ) {

	if ( ! in_array(
		$admin_page,
		array(
			'toplevel_page_amplified-import-export',
			'amplified-import-export_page_wp-aie-import',
			'amplified-import-export_page_wp-aie-export',
			'amplified-import-export_page_wp-aie-content-sync',
			'amplified-import-export_page_wp-aie-content-updater',
			'amplified-import-export_page_wp-aie-jobs-log',
			'amplified-import-export_page_wp-aie-media-sync',
			'amplified-import-export_page_wp-aie-ai-url-importer',
			'amplified-import-export_page_wp-aie-functions',
			'amplified-import-export_page_wp-aie-plugin-options',
		)
	) ) {
			return;
		}

		// Enqueue CodeMirror for code editor
		if ( 'amplified-import-export_page_wp-aie-functions' === $admin_page ) {
			wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		}

		wp_enqueue_script(
			'amplified-import-export-scripts',
			plugins_url( 'assets/js/app.js', WP_AIE_FILE ),
			array( 'jquery', 'jquery-ui-sortable' ),
			filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/js/app.js' ),
			array(
				'in_footer' => true,
			)
		);

		// Localize script with AJAX data
		wp_localize_script(
			'amplified-import-export-scripts',
			'aieData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'aie_nonce' ),
				'pluginUrl'      => plugins_url( '', WP_AIE_FILE ),
				'functionsUrl'   => admin_url( 'admin.php?page=wp-aie-functions' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified via verify_request(). -- Input is sanitized and validated in context.
				'optionsUrl'     => admin_url( 'admin.php?page=wp-aie-plugin-options' ),
				'currentPage'    => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin page slug, no nonce needed.
				'hasOpenAIApiKey' => \WP_AIE\Helper\AI_Function_Generator::has_api_key(),
				'isPremium'      => function_exists( 'aie_fs' ) && aie_fs()->can_use_premium_code(),
				'premiumDataTypes' => [
					'custom_post_types', 'custom_post_type',
					'media',
					'menu', 'menus', 'nav_menu',
					'user', 'users',
					'comment', 'comments',
					'taxonomy', 'taxonomy_term', 'taxonomy_terms', 'term', 'terms',
					'category', 'categories', 'tag', 'tags',
					'woo_product', 'product', 'products',
					'woo_order', 'woo_orders',
					'woo_coupon',
					'woo_attribute',
					'database_table',
				],
				'i18n'           => array(
					// General
					'skip'                       => __( 'Skip', 'amplified-import-export' ),
					'uploading'                  => __( 'Uploading...', 'amplified-import-export' ),
					'processing'                 => __( 'Processing...', 'amplified-import-export' ),
					'completed'                  => __( 'Completed', 'amplified-import-export' ),
					'failed'                     => __( 'Failed', 'amplified-import-export' ),
					'confirmCancel'              => __( 'Are you sure you want to cancel?', 'amplified-import-export' ),
					'errorOccurred'              => __( 'An error occurred', 'amplified-import-export' ),
					'fileTooLarge'               => __( 'File size exceeds maximum allowed', 'amplified-import-export' ),
					'invalidFileType'            => __( 'Invalid file type', 'amplified-import-export' ),
					'invalidFileTypeCsv'         => __( 'Invalid file type. Please upload CSV files only.', 'amplified-import-export' ),
					'fileUploadedSuccessfully'   => __( 'File uploaded successfully', 'amplified-import-export' ),
					'uploadFailed'               => __( 'Upload failed', 'amplified-import-export' ),
					'noFileDataAvailable'        => __( 'No file data available', 'amplified-import-export' ),
					'noPreviewDataAvailable'     => __( 'No preview data available', 'amplified-import-export' ),
					'selectFile'                 => __( 'Please select a file', 'amplified-import-export' ),
					'selectFields'               => __( 'Please select at least one field', 'amplified-import-export' ),
					'mapFields'                  => __( 'Please map at least one field', 'amplified-import-export' ),
					'name_required'              => __( 'Please enter a function name.', 'amplified-import-export' ),
					'code_required'              => __( 'Please enter the PHP code for your function.', 'amplified-import-export' ),
					'category_required'          => __( 'Please select a category.', 'amplified-import-export' ),

					// Export Step 3
					'fieldAlreadyAdded'          => __( 'This field is already added', 'amplified-import-export' ),
					'confirmRemoveAllFields'     => __( 'Are you sure you want to remove all fields?', 'amplified-import-export' ),
					'functionsSavedSuccess'      => __( 'Functions saved successfully', 'amplified-import-export' ),
					'enterTestValue'             => __( 'Please enter a test value', 'amplified-import-export' ),
					'noFunctionsToTest'          => __( 'No functions to test', 'amplified-import-export' ),
					'pleaseAddAtLeastOneFunction' => __( 'Please add at least one function to test', 'amplified-import-export' ),
					'createFunctionsInLibrary'   => __( 'Creating custom functions will be available in the Functions Library section', 'amplified-import-export' ),
					'errorLoadingFunctions'      => __( 'Error loading functions', 'amplified-import-export' ),
					'importStartedSuccessfully'  => __( 'Import started successfully', 'amplified-import-export' ),
					'importCompletedSuccessfully' => __( 'Import completed successfully!', 'amplified-import-export' ),
					'importCancelled'            => __( 'Import cancelled', 'amplified-import-export' ),
					'importFailed'               => __( 'Import failed', 'amplified-import-export' ),
					'configErrorAieData'         => __( 'Configuration error: aieData not found', 'amplified-import-export' ),
					'testFailed'                 => __( 'Test failed', 'amplified-import-export' ),
					'errorTestingPipeline'       => __( 'Error testing pipeline', 'amplified-import-export' ),
					
					// Custom Field Modal
					'addTaxonomyField'           => __( 'Add Taxonomy Field', 'amplified-import-export' ),
					'addCustomField'             => __( 'Add Custom Field', 'amplified-import-export' ),
					'enterTaxonomySlug'          => __( 'Enter taxonomy slug (e.g., category, post_tag, product_cat)', 'amplified-import-export' ),
					'enterFieldKey'              => __( 'Enter field key (e.g., _custom_price)', 'amplified-import-export' ),
					'dataFormat'                 => __( 'Data Format', 'amplified-import-export' ),
					'termIdFormat'               => __( 'Term ID (e.g., 5, 12, 23)', 'amplified-import-export' ),
					'termSlugFormat'             => __( 'Term Slug (e.g., technology, news)', 'amplified-import-export' ),
					'termNameFormat'             => __( 'Term Name (e.g., Technology, News)', 'amplified-import-export' ),
					'selectTaxonomyDataFormat'   => __( 'Select the format of taxonomy data in your CSV file.', 'amplified-import-export' ),				'taxonomySlugLabel'          => __( 'Taxonomy Slug', 'amplified-import-export' ),
				'taxonomySlugDescription'    => __( 'The slug of the taxonomy (category, post_tag, or custom taxonomy).', 'amplified-import-export' ),
				'metaKeyLabel'               => __( 'Meta Key', 'amplified-import-export' ),
				'metaKeyDescription'         => __( 'The meta key for the custom field (e.g., _custom_price, my_custom_field).', 'amplified-import-export' ),
				'cancel'                     => __( 'Cancel', 'amplified-import-export' ),
					'addField'                   => __( 'Add Field', 'amplified-import-export' ),
					'addTransformationFunction'  => __( 'Add transformation function', 'amplified-import-export' ),
					'removeMapping'              => __( 'Remove mapping', 'amplified-import-export' ),
					
					// Field Functions Modal
					'fieldTransformationFunctions' => __( 'Field Transformation Functions', 'amplified-import-export' ),
					'field'                      => __( 'Field', 'amplified-import-export' ),
					'type'                       => __( 'Type', 'amplified-import-export' ),
					'appliedFunctions'           => __( 'Applied Functions', 'amplified-import-export' ),
					'noFunctionsApplied'         => __( 'No functions applied yet. Add functions from the list below.', 'amplified-import-export' ),
					'functionsAppliedInOrder'    => __( 'Functions are applied in order from top to bottom. Drag to reorder.', 'amplified-import-export' ),
					'availableFunctions'         => __( 'Available Functions', 'amplified-import-export' ),
					'searchFunctions'            => __( 'Search functions...', 'amplified-import-export' ),
					'all'                        => __( 'All', 'amplified-import-export' ),
					'library'                    => __( 'Library', 'amplified-import-export' ),
					'custom'                     => __( 'Custom', 'amplified-import-export' ),
					'loadingFunctions'           => __( 'Loading functions...', 'amplified-import-export' ),
					'createNewFunction'          => __( 'Create New Function', 'amplified-import-export' ),
					'previewTransformation'      => __( 'Preview Transformation', 'amplified-import-export' ),
					'testValue'                  => __( 'Test Value', 'amplified-import-export' ),
					'enterTestValue'             => __( 'Enter test value...', 'amplified-import-export' ),
					'testPipeline'               => __( 'Test Pipeline', 'amplified-import-export' ),
					'applyFunctions'             => __( 'Apply Functions', 'amplified-import-export' ),
					// translators: %s = content placeholder.
					'initialValue'               => __( 'Initial Value', 'amplified-import-export' ),
					'finalResult'                => __( 'Final Result', 'amplified-import-export' ),
					// translators: %d is a dynamic value.
					'autoMappedFields'           => __( 'Auto-mapped %d fields', 'amplified-import-export' ),
					'dragSourceColumns'          => __( 'Drag source columns to WordPress fields to create mappings', 'amplified-import-export' ),

					// Jobs Log
					'viewDetails'                => __( 'View Details', 'amplified-import-export' ),
					'resume'                     => __( 'Resume', 'amplified-import-export' ),
					'retry'                      => __( 'Retry (Create new job with same parameters)', 'amplified-import-export' ),
					'download'                   => __( 'Download', 'amplified-import-export' ),
					'delete'                     => __( 'Delete', 'amplified-import-export' ),
					'errorLoadingJobs'           => __( 'Error loading jobs: ', 'amplified-import-export' ),
					'confirmResumeJob'           => __( 'Resume this job?', 'amplified-import-export' ),
					'jobResumedSuccess'          => __( 'Job resumed successfully', 'amplified-import-export' ),
					'errorResumingJob'           => __( 'Error resuming job: ', 'amplified-import-export' ),
					'confirmRestartJob'          => __( 'Restart this job with the same settings?', 'amplified-import-export' ),
					'jobRestartedSuccess'        => __( 'Job restarted successfully', 'amplified-import-export' ),
					'errorRestartingJob'         => __( 'Error restarting job: ', 'amplified-import-export' ),
					'confirmRetryJob'            => __( 'Retry this job with the same settings?', 'amplified-import-export' ),
					'jobCreatedStarting'         => __( 'Job created, starting process...', 'amplified-import-export' ),
					'errorRetryingJob'           => __( 'Error retrying job: ', 'amplified-import-export' ),
					'jobDeletedSuccess'          => __( 'Job deleted successfully', 'amplified-import-export' ),
					'errorDeletingJob'           => __( 'Error deleting job: ', 'amplified-import-export' ),
					'downloadFailed'             => __( 'Download failed', 'amplified-import-export' ),
					'failedGenerateDownloadUrl'  => __( 'Failed to generate download URL', 'amplified-import-export' ),
					'errorLoadingJobDetails'     => __( 'Error loading job details: ', 'amplified-import-export' ),
					'jobId'                      => __( 'ID', 'amplified-import-export' ),
					'jobType'                    => __( 'Type', 'amplified-import-export' ),
					'jobDataType'                => __( 'Data Type', 'amplified-import-export' ),
					'jobFileFormat'              => __( 'File Format', 'amplified-import-export' ),
					'jobStatus'                  => __( 'Status', 'amplified-import-export' ),
					'jobProgress'                => __( 'Progress', 'amplified-import-export' ),
					'jobItems'                   => __( 'Items', 'amplified-import-export' ),
					'jobSuccess'                 => __( 'Success', 'amplified-import-export' ),
					'jobCreated'                 => __( 'Created', 'amplified-import-export' ),
					'jobStarted'                 => __( 'Started', 'amplified-import-export' ),
					'jobCompleted'               => __( 'Completed', 'amplified-import-export' ),
					'jobFile'                    => __( 'File', 'amplified-import-export' ),
					'jobFileSize'                => __( 'File Size', 'amplified-import-export' ),
					'jobParameters'              => __( 'Parameters', 'amplified-import-export' ),
					'typeImport'                 => __( 'Import', 'amplified-import-export' ),
					'typeExport'                 => __( 'Export', 'amplified-import-export' ),
					'typeMediaSync'              => __( 'Media Sync', 'amplified-import-export' ),
					'statusPending'              => __( 'Pending', 'amplified-import-export' ),
					'statusProcessing'           => __( 'Processing', 'amplified-import-export' ),
					'statusCompleted'            => __( 'Completed', 'amplified-import-export' ),
					'statusFailed'               => __( 'Failed', 'amplified-import-export' ),
					'statusPaused'               => __( 'Paused', 'amplified-import-export' ),
					'statusCancelled'            => __( 'Cancelled', 'amplified-import-export' ),

					// Content Sync
					'failedLoadSites'            => __( 'Failed to load sites', 'amplified-import-export' ),
					'confirmDeleteSiteConnection' => __( 'Are you sure you want to delete this site connection?', 'amplified-import-export' ),
					'failedDeleteSite'           => __( 'Failed to delete site', 'amplified-import-export' ),
					'connectionTestFailed'       => __( 'Connection test failed', 'amplified-import-export' ),
					'confirmRegenerateSiteKey'   => __( 'Are you sure you want to regenerate this site\'s API key?\n\nThis will break the connection with the remote site until you update the key there.', 'amplified-import-export' ),
					'newApiKey'                  => __( 'New API Key: ', 'amplified-import-export' ),
					'failedRegenerateKey'        => __( 'Failed to regenerate key', 'amplified-import-export' ),
					'apiKeyCopied'               => __( 'API key copied to clipboard', 'amplified-import-export' ),
					'confirmRegenerateMyKey'     => __( 'Are you sure you want to regenerate your API key?\n\nThis will invalidate the current key and all remote sites will need to update their connection settings with the new key.', 'amplified-import-export' ),
					// translators: %s = content placeholder.
					'failedRegenerateApiKey'     => __( 'Failed to regenerate API key', 'amplified-import-export' ),

					// Media Sync
					'noFilesFoundCriteria'       => __( 'No files found matching the criteria', 'amplified-import-export' ),
					// translators: %d is a dynamic value.
					'foundFilesReadyToSync'      => __( 'Found %d files ready to sync', 'amplified-import-export' ),
					'noFilesFoundTitle'          => __( 'No Files Found', 'amplified-import-export' ),
					'noFilesFoundDesc'           => __( 'No files matching your criteria were found in the selected folder.', 'amplified-import-export' ),
					// translators: %s = content placeholder.
					'suggestions'                => __( 'Suggestions', 'amplified-import-export' ),
					'checkFolderPath'            => __( 'Check if the folder path is correct', 'amplified-import-export' ),
					'enableScanRecursive'        => __( 'Try enabling "Scan Recursive" to search in subfolders', 'amplified-import-export' ),
					'changeFileTypeFilter'       => __( 'Change the file type filter', 'amplified-import-export' ),
					'makeSureFolderContains'     => __( 'Make sure the folder contains supported media files', 'amplified-import-export' ),
					'scanComplete'               => __( 'Scan Complete', 'amplified-import-export' ),
					// translators: %1$s is a dynamic value, %2$s is a dynamic value.
					'foundFilesReadySync'        => __( 'Found %1$s files ready for synchronization (Total: %2$s)', 'amplified-import-export' ),
					'fileTypes'                  => __( 'File Types', 'amplified-import-export' ),
					'filesProcessedBatches'      => __( 'All files will be processed in batches. Click "Start Sync" below to begin.', 'amplified-import-export' ),
					'enterFolderPath'            => __( 'Please enter a folder path', 'amplified-import-export' ),
					'requestFailed'              => __( 'Request failed', 'amplified-import-export' ),
					'noFilesToSync'              => __( 'No files to sync. Please scan a folder first.', 'amplified-import-export' ),
					'invalidFolderPath'          => __( 'Invalid folder path', 'amplified-import-export' ),
					'syncStarted'                => __( 'Synchronization started', 'amplified-import-export' ),
					'syncPaused'                 => __( 'Sync paused', 'amplified-import-export' ),
					// translators: %s = content placeholder.
					'syncResumed'                => __( 'Sync resumed', 'amplified-import-export' ),
					'confirmCancelSync'          => __( 'Are you sure you want to cancel the synchronization?\n\nThis will stop the process and you\'ll need to start over.', 'amplified-import-export' ),
					'syncCancelled'              => __( 'Sync cancelled', 'amplified-import-export' ),			// Post Sync
			'selectAtLeastOnePost'       => __( 'Please select at least one post', 'amplified-import-export' ),
			'selectSite'                 => __( 'Please select a site', 'amplified-import-export' ),
			// translators: %s = content placeholder.
			'noPostsSelected'            => __( 'No posts selected', 'amplified-import-export' ),
			'pushTo'                     => __( 'push to', 'amplified-import-export' ),
			'pullFrom'                   => __( 'pull from', 'amplified-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
			'confirmSyncAction'          => __( 'Are you sure you want to %1$s %2$s?\n\nThis will affect %3$s post(s).', 'amplified-import-export' ),
			'preparingToPush'            => __( 'Preparing to push content...', 'amplified-import-export' ),
			'preparingToPull'            => __( 'Preparing to pull content...', 'amplified-import-export' ),
			// translators: %s = content placeholder.
			'uploadingContent'           => __( 'Uploading content...', 'amplified-import-export' ),
			// translators: %s = content placeholder.
			'downloadingContent'         => __( 'Downloading content...', 'amplified-import-export' ),
			'syncCompletedSuccessfully'  => __( 'Sync completed successfully', 'amplified-import-export' ),
			// translators: 1: number of created posts, 2: number of updated posts.
			'createdPosts'               => __( '✓ Created %1$d post(s), Updated %2$d post(s)', 'amplified-import-export' ),
			// translators: %d is a dynamic value.
			'syncedImages'               => __( '✓ Synced %d image(s)', 'amplified-import-export' ),
			'syncFailed'                 => __( 'Sync failed', 'amplified-import-export' ),
			'errorOccurredDuringSync'    => __( 'An error occurred during sync', 'amplified-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'postsProgress'              => __( 'Posts: %1$s/%2$s', 'amplified-import-export' ),
			// translators: %d is a dynamic value.
			'imagesSyncedProgress'       => __( 'Images synced: %d', 'amplified-import-export' ),

			// Time formats
			// translators: %d is a dynamic value.
			'timeFormatSeconds'          => __( '%ds', 'amplified-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'timeFormatMinutesSeconds'   => __( '%1$sm %2$ss', 'amplified-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'timeFormatHoursMinutes'     => __( '%1$sh %2$sm', 'amplified-import-export' ),

			// File validation
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'fileSizeExceeds'            => __( 'File size (%1$s) exceeds maximum allowed size (%2$s)', 'amplified-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'fileTypeNotAllowed'         => __( 'File type .%1$s is not allowed. Allowed types: %2$s', 'amplified-import-export' ),

			// Export
					'exportStartedSuccess'       => __( 'Export started successfully', 'amplified-import-export' ),
					'exportCompletedSuccess'     => __( 'Export completed successfully!', 'amplified-import-export' ),
					'confirmCancelExport'        => __( 'Are you sure you want to cancel this export?', 'amplified-import-export' ),
					'exportCancelled'            => __( 'Export cancelled', 'amplified-import-export' ),

					// AI URL Importer
					'testing'                    => __( 'Testing...', 'amplified-import-export' ),
					'testConnection'             => __( 'Test Connection', 'amplified-import-export' ),
					'generatingPreview'          => __( 'Generating Preview...', 'amplified-import-export' ),
					'generatePreview'            => __( 'Generate Preview', 'amplified-import-export' ),
					'failedLoadAcfFields'        => __( 'Failed to load ACF fields. Please try again.', 'amplified-import-export' ),
					'noAcfFields'                => __( 'No ACF fields found for this post type.', 'amplified-import-export' ),
					'noImagesFound'              => __( 'No images found', 'amplified-import-export' ),
					'noFeaturedImage'            => __( 'No featured image selected', 'amplified-import-export' ),
				'confirmCancelImport'        => __( 'Are you sure you want to cancel this import?', 'amplified-import-export' ),
				'failedCancelImport'         => __( 'Failed to cancel the import. Please try again.', 'amplified-import-export' ),
				'error'                      => __( 'Error', 'amplified-import-export' ),
				'rateLimitReached'           => __( 'Rate Limit Reached', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'importCompleted'            => __( 'Import completed! %s URLs imported successfully.', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'importFailed'               => __( 'Import failed: %s', 'amplified-import-export' ),					// Import
					'showingFirstRows'           => __( 'Showing first 5 rows', 'amplified-import-export' ),
					'pleaseSelectTable'          => __( 'Please select a database table above to see available columns', 'amplified-import-export' ),
					'selectTable'                => __( 'Select a table...', 'amplified-import-export' ),
					'noTablesFound'              => __( 'No tables found', 'amplified-import-export' ),
					'errorLoadingTables'         => __( 'Error loading tables', 'amplified-import-export' ),
				'loading'                    => __( 'Loading...', 'amplified-import-export' ),
				'errorLoadingColumns'        => __( 'Error loading columns', 'amplified-import-export' ),
				'loadingTableColumns'        => __( 'Loading table columns...', 'amplified-import-export' ),
				'pleaseEnterFieldName'       => __( 'Please enter a field name', 'amplified-import-export' ),
				'failedTestPipeline'         => __( 'Failed to test pipeline', 'amplified-import-export' ),
				'confirmCancelImportStep'    => __( 'Are you sure you want to cancel this import?', 'amplified-import-export' ),

				// Export (additional strings)
				// translators: %s = content placeholder.
				'exportComplete'             => __( 'Export Complete!', 'amplified-import-export' ),
				'selectPostType'             => __( 'Select Post Type', 'amplified-import-export' ),
				'selectPostTypePlaceholder'  => __( 'Select Post Type...', 'amplified-import-export' ),
				'selectTaxonomy'             => __( 'Select Taxonomy', 'amplified-import-export' ),
				'selectTaxonomyPlaceholder'  => __( 'Select Taxonomy...', 'amplified-import-export' ),
				'selectTablePlaceholder'     => __( 'Select Table...', 'amplified-import-export' ),
				'selectField'                => __( 'Select Field...', 'amplified-import-export' ),
				'value'                      => __( 'Value', 'amplified-import-export' ),
				'errorLoadingPostTypes'      => __( 'Error loading post types', 'amplified-import-export' ),
				'errorLoadingTaxonomies'     => __( 'Error loading taxonomies', 'amplified-import-export' ),
				
				// Export Step 3
				'assignFunctionsTitle'       => __( 'Assign functions', 'amplified-import-export' ),
				'remove'                     => __( 'Remove', 'amplified-import-export' ),
				'functions'                  => __( 'function(s)', 'amplified-import-export' ),
				'enterColumnName'            => __( 'Enter column name:', 'amplified-import-export' ),
				'noFieldsSelected'           => __( 'No Fields Selected', 'amplified-import-export' ),
				'pleaseSelectFieldMessage'   => __( 'Please select at least one field to continue with the export.', 'amplified-import-export' ),
				'addAll'                     => __( 'Add all', 'amplified-import-export' ),
				'addAllFieldsTitle'          => __( 'Add all fields from this category', 'amplified-import-export' ),
				'loadingAcfFields'           => __( 'Loading ACF fields...', 'amplified-import-export' ),
				'loadingYoastFields'         => __( 'Loading Yoast SEO fields...', 'amplified-import-export' ),
				'noFunctionsAvailableYet'    => __( 'No functions available yet.', 'amplified-import-export' ),
				'createFirstFunction'        => __( 'Create your first custom function to get started.', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'noFunctionsFound'           => __( 'No %s functions found.', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'errorLabel'                 => __( 'Error: %s', 'amplified-import-export' ),

				// Export (UI strings used in export.js)
				'noDataAvailable'            => __( 'No Data Available', 'amplified-import-export' ),
				'adjustFiltersMessage'       => __( 'Adjust your filters or select a different content type to continue with the export.', 'amplified-import-export' ),
				'postTypeRequired'           => __( 'Post Type Required', 'amplified-import-export' ),
				'pleaseSelectPostType'       => __( 'Please select a specific post type from the dropdown to continue.', 'amplified-import-export' ),
				'taxonomyRequired'           => __( 'Taxonomy Required', 'amplified-import-export' ),
				'pleaseSelectTaxonomy'       => __( 'Please select a specific taxonomy from the dropdown to continue.', 'amplified-import-export' ),
				'tableRequired'              => __( 'Table Required', 'amplified-import-export' ),
				'pleaseSelectTable'          => __( 'Please select a database table from the dropdown to continue.', 'amplified-import-export' ),
				'enterNumberPlaceholder'     => __( 'Enter number...', 'amplified-import-export' ),
				'enterFilterValue'           => __( 'Enter value...', 'amplified-import-export' ),
				'enterCustomFieldName'       => __( 'Enter custom field name...', 'amplified-import-export' ),
				'taxonomyPlaceholderExamples'=> __( 'e.g., category, post_tag, product_cat...', 'amplified-import-export' ),
				'enterTermSlugs'             => __( 'Enter term slugs (comma-separated)...', 'amplified-import-export' ),
				'inFilter'                   => __( 'In', 'amplified-import-export' ),
				'notInFilter'                => __( 'Not In', 'amplified-import-export' ),
				'inComma'                    => __( 'In (comma-separated)', 'amplified-import-export' ),
				'notInComma'                 => __( 'Not In (comma-separated)', 'amplified-import-export' ),
				'hasTermsIn'                 => __( 'Has Term(s) - IN', 'amplified-import-export' ),
				'doesNotHaveTermsNotIn'      => __( 'Does Not Have Term(s) - NOT IN', 'amplified-import-export' ),
				'hasAllTermsAnd'             => __( 'Has All Terms - AND', 'amplified-import-export' ),
				'pleaseSelectFieldToExport'  => __( 'Please select at least one field to export', 'amplified-import-export' ),
				'pleaseUploadFile'           => __( 'Please upload a file', 'amplified-import-export' ),
				'pleaseEnterCustomDelimiter' => __( 'Please enter a custom delimiter', 'amplified-import-export' ),
				'pleaseSelectPostType'       => __( 'Please select a post type', 'amplified-import-export' ),
				'exportFailed'               => __( 'Export failed', 'amplified-import-export' ),
				'unknownError'               => __( 'Unknown error', 'amplified-import-export' ),
				'enterValuesCommaSeparated'  => __( 'Enter values separated by comma (e.g., 1,5,8 or test1,test2)', 'amplified-import-export' ),
				'enterTwoNumbersCommaSeparated' => __( 'Enter two numbers separated by comma (e.g., 10,100)', 'amplified-import-export' ),

				// Function Categories
				'categoryStringOperations'   => __( 'String Operations', 'amplified-import-export' ),
				'categoryDateTime'           => __( 'Date & Time', 'amplified-import-export' ),
				'categoryNumericOperations'  => __( 'Numeric Operations', 'amplified-import-export' ),
				'categoryHtmlOperations'     => __( 'HTML Operations', 'amplified-import-export' ),
				'categoryWordPress'          => __( 'WordPress', 'amplified-import-export' ),
				'categoryValidation'         => __( 'Validation', 'amplified-import-export' ),
				'categoryAdvanced'           => __( 'Advanced', 'amplified-import-export' ),
				'categoryCustom'             => __( 'Custom', 'amplified-import-export' ),

				// Export Field Groups
				'fieldGroupStandard'          => __( 'Standard', 'amplified-import-export' ),
				'fieldGroupBasic'             => __( 'Basic', 'amplified-import-export' ),
				'fieldGroupOther'             => __( 'Other', 'amplified-import-export' ),
				'fieldGroupCustomFilters'     => __( 'Custom Filters', 'amplified-import-export' ),
				'fieldGroupFileInformation'   => __( 'File Information', 'amplified-import-export' ),
				'fieldGroupImageDimensions'   => __( 'Image Dimensions', 'amplified-import-export' ),
				'fieldGroupDates'             => __( 'Dates', 'amplified-import-export' ),
				'fieldGroupAuthor'            => __( 'Author', 'amplified-import-export' ),
				'fieldGroupAttachment'        => __( 'Attachment', 'amplified-import-export' ),
				'fieldGroupDetails'           => __( 'Details', 'amplified-import-export' ),
				'fieldGroupProfile'           => __( 'Profile', 'amplified-import-export' ),
				'fieldGroupRolePermissions'   => __( 'Role & Permissions', 'amplified-import-export' ),
				'fieldGroupPreferences'       => __( 'Preferences', 'amplified-import-export' ),
				'fieldGroupStats'             => __( 'Stats', 'amplified-import-export' ),
				'fieldGroupRelatedPost'       => __( 'Related Post', 'amplified-import-export' ),
				'fieldGroupHierarchy'         => __( 'Hierarchy', 'amplified-import-export' ),
				'fieldGroupBlockThemeComponents' => __( 'Block Theme Components', 'amplified-import-export' ),
				'fieldGroupPostTypeSelection' => __( 'Post Type Selection', 'amplified-import-export' ),
				'fieldGroupTaxonomySelection' => __( 'Taxonomy Selection', 'amplified-import-export' ),
				'fieldGroupTaxonomy'          => __( 'Taxonomy', 'amplified-import-export' ),
				'fieldGroupContent'           => __( 'Content', 'amplified-import-export' ),
				'fieldGroupPricing'           => __( 'Pricing', 'amplified-import-export' ),
				'fieldGroupInventory'         => __( 'Inventory', 'amplified-import-export' ),
				'fieldGroupProductType'       => __( 'Product Type', 'amplified-import-export' ),
				'fieldGroupShipping'          => __( 'Shipping', 'amplified-import-export' ),
				'fieldGroupMedia'             => __( 'Media', 'amplified-import-export' ),
				'fieldGroupFeaturedImage'     => __( 'Featured Image', 'amplified-import-export' ),
				'fieldGroupFile'              => __( 'File', 'amplified-import-export' ),
				'fieldGroupImage'             => __( 'Image', 'amplified-import-export' ),
				'fieldGroupRole'              => __( 'Role', 'amplified-import-export' ),
				'fieldGroupLinkedProducts'    => __( 'Linked Products', 'amplified-import-export' ),
				'fieldGroupAttributes'        => __( 'Attributes', 'amplified-import-export' ),
				'fieldGroupTotals'            => __( 'Totals', 'amplified-import-export' ),
				'fieldGroupStructure'         => __( 'Structure', 'amplified-import-export' ),
				'fieldGroupStatus'            => __( 'Status', 'amplified-import-export' ),
				'fieldGroupPost'              => __( 'Post', 'amplified-import-export' ),
				'fieldGroupCustomFieldsMeta'  => __( 'Custom Fields (Meta)', 'amplified-import-export' ),
				'fieldGroupCustomFieldsUserMeta' => __( 'Custom Fields (User Meta)', 'amplified-import-export' ),
				'fieldGroupCustomFieldsCommentMeta' => __( 'Custom Fields (Comment Meta)', 'amplified-import-export' ),
				'fieldGroupCustomFieldsTermMeta' => __( 'Custom Fields (Term Meta)', 'amplified-import-export' ),
				'fieldGroupCustomFields'      => __( 'Custom Fields', 'amplified-import-export' ),
				'fieldGroupTaxonomies'        => __( 'Taxonomies', 'amplified-import-export' ),
				'fieldGroupMenuItem'          => __( 'Menu Item', 'amplified-import-export' ),
				'fieldGroupAttribute'         => __( 'Attribute', 'amplified-import-export' ),
				'fieldGroupCommentData'       => __( 'Comment Data', 'amplified-import-export' ),
				'fieldGroupTermData'          => __( 'Term Data', 'amplified-import-export' ),
				'fieldGroupReviews'           => __( 'Reviews', 'amplified-import-export' ),
				'fieldGroupVisibility'        => __( 'Visibility', 'amplified-import-export' ),
				'fieldGroupAmounts'           => __( 'Amounts', 'amplified-import-export' ),
				'fieldGroupCustomer'          => __( 'Customer', 'amplified-import-export' ),
				'fieldGroupBillingAddress'    => __( 'Billing Address', 'amplified-import-export' ),
				'fieldGroupShippingAddress'   => __( 'Shipping Address', 'amplified-import-export' ),
				'fieldGroupOrderItems'        => __( 'Order Items', 'amplified-import-export' ),
				'fieldGroupPayment'           => __( 'Payment', 'amplified-import-export' ),
				'fieldGroupNotes'             => __( 'Notes', 'amplified-import-export' ),
				'fieldGroupDiscount'          => __( 'Discount', 'amplified-import-export' ),
				'fieldGroupUsageRestrictions' => __( 'Usage Restrictions', 'amplified-import-export' ),
				'fieldGroupProductRestrictions' => __( 'Product Restrictions', 'amplified-import-export' ),
				'fieldGroupEmailRestrictions' => __( 'Email Restrictions', 'amplified-import-export' ),
				'fieldGroupUsageLimits'       => __( 'Usage Limits', 'amplified-import-export' ),
				'fieldGroupSettings'          => __( 'Settings', 'amplified-import-export' ),
				'fieldGroupTerms'             => __( 'Terms', 'amplified-import-export' ),
				'fieldGroupTableColumns'      => __( 'Table Columns', 'amplified-import-export' ),
				'fieldGroupTableSelection'    => __( 'Table Selection', 'amplified-import-export' ),

				// Export Field Labels (Common)
				'fieldTitle'                  => __( 'Title', 'amplified-import-export' ),
				'fieldContent'                => __( 'Content', 'amplified-import-export' ),
				'fieldExcerpt'                => __( 'Excerpt', 'amplified-import-export' ),
				'fieldDate'                   => __( 'Date', 'amplified-import-export' ),
				'fieldStatus'                 => __( 'Status', 'amplified-import-export' ),
				'fieldCommentStatus'          => __( 'Comment Status', 'amplified-import-export' ),
				'fieldModifiedDate'           => __( 'Modified Date', 'amplified-import-export' ),
				'fieldTemplate'               => __( 'Template', 'amplified-import-export' ),
				'fieldCustomFieldMeta'        => __( '🔧 Custom Field (Meta)', 'amplified-import-export' ),
				'fieldTaxonomyFilter'         => __( '🏷️ Taxonomy Filter', 'amplified-import-export' ),
				'fieldDescription'            => __( 'Description', 'amplified-import-export' ),
				'fieldCaption'                => __( 'Caption', 'amplified-import-export' ),
				'fieldAltText'                => __( 'Alt Text', 'amplified-import-export' ),
				'fieldFileUrlGuid'            => __( 'File URL (GUID)', 'amplified-import-export' ),
				'fieldFileUrl'                => __( 'File URL', 'amplified-import-export' ),
				'fieldFilePathRelative'       => __( 'File Path (Relative)', 'amplified-import-export' ),
				'fieldFileName'               => __( 'File Name', 'amplified-import-export' ),
				'fieldFileExtension'          => __( 'File Extension', 'amplified-import-export' ),
				'fieldMimeType'               => __( 'MIME Type', 'amplified-import-export' ),
				'fieldFileSizeBytes'          => __( 'File Size (bytes)', 'amplified-import-export' ),
				'fieldWidthPx'                => __( 'Width (px)', 'amplified-import-export' ),
				'fieldHeightPx'               => __( 'Height (px)', 'amplified-import-export' ),
				'fieldUploadDate'             => __( 'Upload Date', 'amplified-import-export' ),
				'fieldAuthorId'               => __( 'Author ID', 'amplified-import-export' ),
				'fieldAuthorName'             => __( 'Author Name', 'amplified-import-export' ),
				'fieldAuthorEmail'            => __( 'Author Email', 'amplified-import-export' ),
				'fieldAttachedToPostId'       => __( 'Attached To (Post ID)', 'amplified-import-export' ),
				'fieldAttachedPostTitle'      => __( 'Attached Post Title', 'amplified-import-export' ),
				'fieldMenuName'               => __( 'Menu Name', 'amplified-import-export' ),
				'fieldMenuItemsArray'         => __( 'Menu Items (Array)', 'amplified-import-export' ),
				'fieldItemsCount'             => __( 'Items Count', 'amplified-import-export' ),
				'fieldThemeLocations'         => __( 'Theme Locations', 'amplified-import-export' ),
				'fieldUsername'               => __( 'Username', 'amplified-import-export' ),
				'fieldEmail'                  => __( 'Email', 'amplified-import-export' ),
				'fieldDisplayName'            => __( 'Display Name', 'amplified-import-export' ),
				'fieldNiceName'               => __( 'Nice Name', 'amplified-import-export' ),
				'fieldFirstName'              => __( 'First Name', 'amplified-import-export' ),
				'fieldLastName'               => __( 'Last Name', 'amplified-import-export' ),
				'fieldNickname'               => __( 'Nickname', 'amplified-import-export' ),
				'fieldBio'                    => __( 'Bio', 'amplified-import-export' ),
				'fieldWebsite'                => __( 'Website', 'amplified-import-export' ),
				'fieldAvatarUrl'              => __( 'Avatar URL', 'amplified-import-export' ),
				'fieldRole'                   => __( 'Role', 'amplified-import-export' ),
				'fieldCapabilitiesArray'      => __( 'Capabilities (Array)', 'amplified-import-export' ),
				'fieldLanguage'               => __( 'Language', 'amplified-import-export' ),
				'fieldAdminColorScheme'       => __( 'Admin Color Scheme', 'amplified-import-export' ),
				'fieldVisualEditor'           => __( 'Visual Editor', 'amplified-import-export' ),
				'fieldPostsCount'             => __( 'Posts Count', 'amplified-import-export' ),
				'fieldRegistrationDate'       => __( 'Registration Date', 'amplified-import-export' ),
				'fieldUserStatus'             => __( 'User Status', 'amplified-import-export' ),
				'fieldCommentId'              => __( 'Comment ID', 'amplified-import-export' ),
				'fieldPostId'                 => __( 'Post ID', 'amplified-import-export' ),
				'fieldCommentContent'         => __( 'Comment Content', 'amplified-import-export' ),
				'fieldCommentType'            => __( 'Comment Type', 'amplified-import-export' ),
				'fieldAuthorUrl'              => __( 'Author URL', 'amplified-import-export' ),
				'fieldAuthorIp'               => __( 'Author IP', 'amplified-import-export' ),
				'fieldUserId'                 => __( 'User ID', 'amplified-import-export' ),
				'fieldUserAgent'              => __( 'User Agent', 'amplified-import-export' ),
				'fieldPostTitle'              => __( 'Post Title', 'amplified-import-export' ),
				'fieldPostAuthorId'           => __( 'Post Author ID', 'amplified-import-export' ),
				'fieldCommentDate'            => __( 'Comment Date', 'amplified-import-export' ),
				'fieldCommentDateGmt'         => __( 'Comment Date (GMT)', 'amplified-import-export' ),
				'fieldParentCommentId'        => __( 'Parent Comment ID', 'amplified-import-export' ),
				'fieldKarma'                  => __( 'Karma', 'amplified-import-export' ),
				'fieldGlobalStylesThemeJson'  => __( 'Global Styles (theme.json)', 'amplified-import-export' ),
				'fieldCustomTemplates'        => __( 'Custom Templates', 'amplified-import-export' ),
				'fieldTemplateParts'          => __( 'Template Parts', 'amplified-import-export' ),
				'fieldThemeModifications'     => __( 'Theme Modifications', 'amplified-import-export' ),
				'fieldCustomCss'              => __( 'Custom CSS', 'amplified-import-export' ),
				'fieldPostTypeSelectSpecific' => __( 'Post Type (select specific)', 'amplified-import-export' ),
				'fieldId'                     => __( 'ID', 'amplified-import-export' ),
				'fieldSlug'                   => __( 'Slug', 'amplified-import-export' ),
				'fieldParentId'               => __( 'Parent ID', 'amplified-import-export' ),
				'fieldTermMetaField'          => __( '🔧 Term Meta Field', 'amplified-import-export' ),
				'fieldTaxonomySelectSpecific' => __( 'Taxonomy (select specific)', 'amplified-import-export' ),
				'fieldTermId'                 => __( 'Term ID', 'amplified-import-export' ),
				'fieldTermName'               => __( 'Term Name', 'amplified-import-export' ),
				'fieldTermSlug'               => __( 'Term Slug', 'amplified-import-export' ),
				'fieldTaxonomyType'           => __( 'Taxonomy Type', 'amplified-import-export' ),
				'fieldTaxonomyId'             => __( 'Taxonomy ID', 'amplified-import-export' ),
				'fieldParentTermId'           => __( 'Parent Term ID', 'amplified-import-export' ),
				'fieldProductId'              => __( 'Product ID', 'amplified-import-export' ),
				'fieldProductName'            => __( 'Product Name', 'amplified-import-export' ),
				'fieldSku'                    => __( 'SKU', 'amplified-import-export' ),
				'fieldShortDescription'       => __( 'Short Description', 'amplified-import-export' ),
				'fieldRegularPrice'           => __( 'Regular Price', 'amplified-import-export' ),
				'fieldSalePrice'              => __( 'Sale Price', 'amplified-import-export' ),
				'fieldTaxStatus'              => __( 'Tax Status', 'amplified-import-export' ),
				'fieldTaxClass'               => __( 'Tax Class', 'amplified-import-export' ),
				'fieldStockQuantity'          => __( 'Stock Quantity', 'amplified-import-export' ),
				'fieldStockStatus'            => __( 'Stock Status', 'amplified-import-export' ),
				'fieldManageStock'            => __( 'Manage Stock', 'amplified-import-export' ),
				'fieldBackorders'             => __( 'Backorders', 'amplified-import-export' ),
				'fieldProductType'            => __( 'Product Type', 'amplified-import-export' ),
				'fieldDownloadable'           => __( 'Downloadable', 'amplified-import-export' ),
				'fieldVirtual'                => __( 'Virtual', 'amplified-import-export' ),
				'fieldWeight'                 => __( 'Weight', 'amplified-import-export' ),
				'fieldLength'                 => __( 'Length', 'amplified-import-export' ),
				'fieldWidth'                  => __( 'Width', 'amplified-import-export' ),
				'fieldHeight'                 => __( 'Height', 'amplified-import-export' ),
				'fieldShippingClass'          => __( 'Shipping Class', 'amplified-import-export' ),
				'fieldFeaturedImage'          => __( 'Featured Image', 'amplified-import-export' ),
				'fieldFeaturedImageId'        => __( 'Featured Image ID', 'amplified-import-export' ),
				'fieldFeaturedImageUrl'       => __( 'Featured Image URL', 'amplified-import-export' ),
				'fieldFeaturedImageTitle'     => __( 'Featured Image Title', 'amplified-import-export' ),
				'fieldFeaturedImageCaption'   => __( 'Featured Image Caption', 'amplified-import-export' ),
				'fieldGalleryImages'          => __( 'Gallery Images', 'amplified-import-export' ),
				'fieldCategories'             => __( 'Categories', 'amplified-import-export' ),
				'fieldTags'                   => __( 'Tags', 'amplified-import-export' ),
				'fieldAverageRating'          => __( 'Average Rating', 'amplified-import-export' ),
				'fieldReviewCount'            => __( 'Review Count', 'amplified-import-export' ),
				'fieldReviewsEnabled'         => __( 'Reviews Enabled', 'amplified-import-export' ),
				'fieldFeatured'               => __( 'Featured', 'amplified-import-export' ),
				'fieldCatalogVisibility'      => __( 'Catalog Visibility', 'amplified-import-export' ),
				'fieldTotalSales'             => __( 'Total Sales', 'amplified-import-export' ),
				'fieldCreatedDate'            => __( 'Created Date', 'amplified-import-export' ),
				'fieldOrderId'                => __( 'Order ID', 'amplified-import-export' ),
				'fieldOrderNumber'            => __( 'Order Number', 'amplified-import-export' ),
				'fieldOrderKey'               => __( 'Order Key', 'amplified-import-export' ),
				'fieldCurrency'               => __( 'Currency', 'amplified-import-export' ),
				'fieldOrderTotal'             => __( 'Order Total', 'amplified-import-export' ),
				'fieldSubtotal'               => __( 'Subtotal', 'amplified-import-export' ),
				'fieldTax'                    => __( 'Tax', 'amplified-import-export' ),
				'fieldShipping'               => __( 'Shipping', 'amplified-import-export' ),
				'fieldDiscount'               => __( 'Discount', 'amplified-import-export' ),
				'fieldCustomerId'             => __( 'Customer ID', 'amplified-import-export' ),
				'fieldCustomerNote'           => __( 'Customer Note', 'amplified-import-export' ),
				'fieldCompany'                => __( 'Company', 'amplified-import-export' ),
				'fieldAddress1'               => __( 'Address 1', 'amplified-import-export' ),
				'fieldAddress2'               => __( 'Address 2', 'amplified-import-export' ),
				'fieldCity'                   => __( 'City', 'amplified-import-export' ),
				'fieldState'                  => __( 'State', 'amplified-import-export' ),
				'fieldPostcode'               => __( 'Postcode', 'amplified-import-export' ),
				'fieldCountry'                => __( 'Country', 'amplified-import-export' ),
				'fieldPhone'                  => __( 'Phone', 'amplified-import-export' ),
				'fieldOrderItemsArray'        => __( 'Order Items (Array)', 'amplified-import-export' ),
				'fieldItemCount'              => __( 'Item Count', 'amplified-import-export' ),
				'fieldPaymentMethod'          => __( 'Payment Method', 'amplified-import-export' ),
				'fieldPaymentMethodTitle'     => __( 'Payment Method Title', 'amplified-import-export' ),
				'fieldTransactionId'          => __( 'Transaction ID', 'amplified-import-export' ),
				'fieldShippingMethod'         => __( 'Shipping Method', 'amplified-import-export' ),
				'fieldOrderDate'              => __( 'Order Date', 'amplified-import-export' ),
				'fieldCompletedDate'          => __( 'Completed Date', 'amplified-import-export' ),
				'fieldPaidDate'               => __( 'Paid Date', 'amplified-import-export' ),
				'fieldOrderNotesArray'        => __( 'Order Notes (Array)', 'amplified-import-export' ),
				'fieldCouponId'               => __( 'Coupon ID', 'amplified-import-export' ),
				'fieldCouponCode'             => __( 'Coupon Code', 'amplified-import-export' ),
				'fieldDiscountType'           => __( 'Discount Type', 'amplified-import-export' ),
				'fieldCouponAmount'           => __( 'Coupon Amount', 'amplified-import-export' ),
				'fieldFreeShipping'           => __( 'Free Shipping', 'amplified-import-export' ),
				'fieldMinimumSpend'           => __( 'Minimum Spend', 'amplified-import-export' ),
				// translators: %s = content placeholder.
				'fieldMaximumSpend'           => __( 'Maximum Spend', 'amplified-import-export' ),
				'fieldIndividualUseOnly'      => __( 'Individual Use Only', 'amplified-import-export' ),
				'fieldExcludeSaleItems'       => __( 'Exclude Sale Items', 'amplified-import-export' ),
				'fieldAllowedProducts'        => __( 'Allowed Products', 'amplified-import-export' ),
				'fieldExcludedProducts'       => __( 'Excluded Products', 'amplified-import-export' ),
				'fieldAllowedCategories'      => __( 'Allowed Categories', 'amplified-import-export' ),
				// translators: %s = content placeholder.
				'fieldExcludedCategories'     => __( 'Excluded Categories', 'amplified-import-export' ),
				'fieldAllowedEmails'          => __( 'Allowed Emails', 'amplified-import-export' ),
				'fieldUsageCount'             => __( 'Usage Count', 'amplified-import-export' ),
				'fieldUsageLimitTotal'        => __( 'Usage Limit Total', 'amplified-import-export' ),
				'fieldUsageLimitPerUser'      => __( 'Usage Limit Per User', 'amplified-import-export' ),
				'fieldExpiryDate'             => __( 'Expiry Date', 'amplified-import-export' ),
				'fieldAttributeId'            => __( 'Attribute ID', 'amplified-import-export' ),
				'fieldAttributeName'          => __( 'Attribute Name', 'amplified-import-export' ),
				'fieldAttributeLabel'         => __( 'Attribute Label', 'amplified-import-export' ),
				'fieldAttributeType'          => __( 'Attribute Type', 'amplified-import-export' ),
				'fieldDefaultSortOrder'       => __( 'Default Sort Order', 'amplified-import-export' ),
				'fieldEnableArchives'         => __( 'Enable Archives', 'amplified-import-export' ),
				'fieldTermsCount'             => __( 'Terms Count', 'amplified-import-export' ),
				'fieldAllTermsArray'          => __( 'All Terms (Array)', 'amplified-import-export' ),
				'fieldSelectTableFirst'       => __( '⚠️ Please select a database table first', 'amplified-import-export' ),
				'fieldPleaseSelectTable'      => __( '⚠️ Please select a database table first', 'amplified-import-export' ),

			// Content Updater
			'confirmClearFields'         => __( 'Are you sure you want to clear all selected fields?', 'amplified-import-export' ),
			'confirmClearFunctions'      => __( 'Are you sure you want to clear all function assignments?', 'amplified-import-export' ),
			'confirmCancelUpdate'        => __( 'Are you sure you want to cancel the update?', 'amplified-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'processingItems'            => __( 'Processing items... (%1$s / %2$s)', 'amplified-import-export' ),
			'premiumOnlyFeature'         => __( 'This content type is only available in the Premium version. Upgrade to unlock this feature.', 'amplified-import-export' ),
			'fileValidationFailed'       => __( 'File Validation Failed', 'amplified-import-export' ),
			'goBackUploadValidFile'      => __( 'Go Back and Upload a Valid File', 'amplified-import-export' ),
			'pleaseSelectContentType'    => __( 'Please select a content type', 'amplified-import-export' ),
			'pleaseSelectAtLeastOneField' => __( 'Please select at least one field to update', 'amplified-import-export' ),
			'pleaseAssignFunction'       => __( 'Please assign at least one function to a field', 'amplified-import-export' ),
			// translators: %s = field name.
			'fieldAlreadySelected'       => __( 'Field "%s" is already selected', 'amplified-import-export' ),
			'noFieldsSelected'           => __( 'No fields selected. Please go back and select fields first.', 'amplified-import-export' ),
			'assignFunctions'            => __( 'Assign Functions', 'amplified-import-export' ),
			'noFunctionsAvailable'       => __( 'No functions available. Create a custom function first.', 'amplified-import-export' ),
			'add'                        => __( 'Add', 'amplified-import-export' ),
			'enterTestValue'             => __( 'Enter a test value:', 'amplified-import-export' ),
			'noFunctionAssigned'         => __( 'No function assigned to this field', 'amplified-import-export' ),
			'functionTestFailed'         => __( 'Function test failed', 'amplified-import-export' ),
			'updateStarted'              => __( 'Update started successfully', 'amplified-import-export' ),
			'failedStartUpdate'          => __( 'Failed to start update', 'amplified-import-export' ),
			'noFieldsSelectedError'      => __( 'No fields selected. Please go back and select fields to update.', 'amplified-import-export' ),
			'noFunctionsAssigned'        => __( 'No functions assigned. Please go back and assign functions to fields.', 'amplified-import-export' ),
			'updateCancelled'            => __( 'Update cancelled', 'amplified-import-export' ),
			'functionAssignmentsCleared' => __( 'All function assignments cleared', 'amplified-import-export' ),
			'enterFunctionId'            => __( 'Enter function ID to apply to all fields (or leave empty for none):', 'amplified-import-export' ),
			'functionAppliedToAll'       => __( 'Function applied to all fields', 'amplified-import-export' ),
			'pleaseEnterTestValue'       => __( 'Please enter a test value', 'amplified-import-export' ),
			'noFunctionsToTest'          => __( 'No functions to test', 'amplified-import-export' ),
			'testFailed'                 => __( 'Test failed', 'amplified-import-export' ),
			'configurationError'         => __( 'Configuration error: aieData not found', 'amplified-import-export' ),
			'errorTestingPipeline'       => __( 'Error testing pipeline', 'amplified-import-export' ),
			'input'                      => __( 'Input', 'amplified-import-export' ),
			// Filter conditions
			'equals'                     => __( 'Equals', 'amplified-import-export' ),
			'notEquals'                  => __( 'Not Equals', 'amplified-import-export' ),
			'contains'                   => __( 'Contains', 'amplified-import-export' ),
			'notContains'                => __( 'Not Contains', 'amplified-import-export' ),
			'startsWith'                 => __( 'Starts With', 'amplified-import-export' ),
			'endsWith'                   => __( 'Ends With', 'amplified-import-export' ),
			'isEmpty'                    => __( 'Is Empty', 'amplified-import-export' ),
			'isNotEmpty'                 => __( 'Is Not Empty', 'amplified-import-export' ),
			'greaterThan'                => __( 'Greater Than', 'amplified-import-export' ),
			'lessThan'                   => __( 'Less Than', 'amplified-import-export' ),
			'greaterOrEqual'             => __( 'Greater or Equal', 'amplified-import-export' ),
			'lessOrEqual'                => __( 'Less or Equal', 'amplified-import-export' ),
			'between'                    => __( 'Between', 'amplified-import-export' ),
			'onDate'                     => __( 'On Date', 'amplified-import-export' ),
			'before'                     => __( 'Before', 'amplified-import-export' ),
			'after'                      => __( 'After', 'amplified-import-export' ),				// Content Sync
				'addNewSite'                 => __( 'Add New Site', 'amplified-import-export' ),
				'editSite'                   => __( 'Edit Site', 'amplified-import-export' ),
				'saveConnection'             => __( 'Save Connection', 'amplified-import-export' ),
				'hideDetails'                => __( 'Hide Details', 'amplified-import-export' ),
				'showDetails'                => __( 'Show Details', 'amplified-import-export' ),
				'copied'                     => __( 'Copied!', 'amplified-import-export' ),
				// translators: %s = content placeholder.
				'regenerating'               => __( 'Regenerating...', 'amplified-import-export' ),
				'regenerated'                => __( 'Regenerated!', 'amplified-import-export' ),
				'updating'                   => __( 'Updating...', 'amplified-import-export' ),
				'validatingSaving'           => __( 'Validating & Saving...', 'amplified-import-export' ),
				'validatingApiKey'           => __( 'Validating API key...', 'amplified-import-export' ),
				'pleaseWaitVerifying'        => __( 'Please wait while we verify the connection to the remote site.', 'amplified-import-export' ),
				'operationCompleted'         => __( 'Operation completed successfully', 'amplified-import-export' ),
				'noChanges'                  => __( 'No Changes', 'amplified-import-export' ),
				'success'                    => __( 'Success!', 'amplified-import-export' ),
				'validationFailed'           => __( 'Validation Failed', 'amplified-import-export' ),
				'failedSaveSiteConnection'   => __( 'Failed to save site connection', 'amplified-import-export' ),
				'connectionError'            => __( 'Connection Error', 'amplified-import-export' ),
				'unexpectedError'            => __( 'An unexpected error occurred while trying to save the site connection.', 'amplified-import-export' ),
				'connectionFailed'           => __( 'Connection Failed', 'amplified-import-export' ),
				'possibleReasons'            => __( 'Possible reasons:', 'amplified-import-export' ),
				'urlIncorrect'               => __( '- The URL is incorrect or not accessible', 'amplified-import-export' ),
				'remoteSiteOffline'          => __( '- The remote site is offline', 'amplified-import-export' ),
				'networkFirewall'            => __( '- Network or firewall issues are blocking the connection', 'amplified-import-export' ),
				'invalidApiKey'              => __( 'Invalid API Key', 'amplified-import-export' ),
				'toResolveIssue'             => __( 'To resolve this issue:', 'amplified-import-export' ),
				'goToContentSync'            => __( '- Go to Content Sync page on the remote site', 'amplified-import-export' ),
				'clickShowDetails'           => __( '- Click "Show Details" to reveal the API key', 'amplified-import-export' ),
				// translators: %s = content placeholder.
				'copyEntireKey'              => __( '- Copy the entire key and paste it here', 'amplified-import-export' ),
				'pluginNotFound'             => __( 'Plugin Not Found', 'amplified-import-export' ),
				'duplicateConnection'        => __( 'Duplicate Connection', 'amplified-import-export' ),
				'siteAlreadyConnected'       => __( 'This site URL is already in your connected sites list.', 'amplified-import-export' ),
				'validationError'            => __( 'Validation Error', 'amplified-import-export' ),
				'networkError'               => __( 'Network Error', 'amplified-import-export' ),
				'unableConnectServer'        => __( 'Unable to connect to the server. Please check your internet connection.', 'amplified-import-export' ),
				'serverError'                => __( 'Server Error', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'serverReturnedError'        => __( 'The server returned an error (%s). Please try again later.', 'amplified-import-export' ),
				'notFound'                   => __( 'Not Found', 'amplified-import-export' ),
				'endpointNotFound'           => __( 'The requested endpoint was not found. Please check if the plugin is properly installed.', 'amplified-import-export' ),

				// translators: %s = content placeholder.
				// Functions Management
				'serverErrorPhpSyntax'       => __( 'Server error: The function code contains errors that prevent it from being saved. Please check your PHP syntax.', 'amplified-import-export' ),
				'serverErrorUnableToSave'    => __( 'Server error: Unable to save function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.', 'amplified-import-export' ),
				// translators: %s = content placeholder.
				'failedToLoadFunctions'      => __( 'Failed to load functions', 'amplified-import-export' ),
				'failedToLoadFunction'       => __( 'Failed to load function', 'amplified-import-export' ),
				'failedToSaveFunction'       => __( 'Failed to save function', 'amplified-import-export' ),
				'failedToDeleteFunction'     => __( 'Failed to delete function', 'amplified-import-export' ),
				'pleaseEnterFunctionCode'    => __( 'Please enter function code first', 'amplified-import-export' ),
				'serverErrorFunctionErrors'  => __( 'Server error: The function code contains errors. Please check your PHP syntax.', 'amplified-import-export' ),
				// translators: %s = content placeholder.
				'serverErrorUnableToTest'    => __( 'Server error: Unable to test function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.', 'amplified-import-export' ),
				'testFailed'                 => __( 'Test failed', 'amplified-import-export' ),
				'apiKeyNotConfigured'        => __( 'OpenAI API key is not configured. Please configure it in Plugin Options to use AI generation.\n\nDo you want to go to Plugin Options now?', 'amplified-import-export' ),
				'badgeLibrary'               => __( 'Library', 'amplified-import-export' ),
				'badgeCustom'                => __( 'Custom', 'amplified-import-export' ),
				'badgeActive'                => __( 'Active', 'amplified-import-export' ),
				'badgeInactive'              => __( 'Inactive', 'amplified-import-export' ),
				'noDescription'              => __( 'No description', 'amplified-import-export' ),
				'editButton'                 => __( 'Edit', 'amplified-import-export' ),
				'deleteButton'               => __( 'Delete', 'amplified-import-export' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'showingFunctions'           => __( 'Showing %1$s-%2$s of %3$s functions', 'amplified-import-export' ),
				'customizeFunction'          => __( 'Customize Function', 'amplified-import-export' ),

				// Media Sync
				'scanning'                   => __( 'Scanning...', 'amplified-import-export' ),
				'starting'                   => __( 'Starting...', 'amplified-import-export' ),
				'processing'                 => __( 'Processing...', 'amplified-import-export' ),
				'syncPaused'                 => __( 'Synchronization Paused', 'amplified-import-export' ),
				'paused'                     => __( 'Paused', 'amplified-import-export' ),
				'reresume'                     => __( 'Resume', 'amplified-import-export' ),
				'syncInProgress'             => __( 'Synchronization in Progress', 'amplified-import-export' ),
				'pause'                      => __( 'Pause', 'amplified-import-export' ),
				'startSync'                  => __( 'Start Sync', 'amplified-import-export' ),
				'scanFolder'                 => __( 'Scan Folder', 'amplified-import-export' ),
				// translators: %d is a dynamic value.
				'andMoreErrors'              => __( '... and %d more errors', 'amplified-import-export' ),
				
				// Completion messages
				'syncCompleteTitle'          => __( 'Synchronization Complete!', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'successfullyProcessed'      => __( 'Successfully processed %s file', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'successfullyProcessedPlural' => __( 'Successfully processed %s files', 'amplified-import-export' ),
				'imported'                   => __( 'Imported', 'amplified-import-export' ),
				'skipped'                    => __( 'Skipped', 'amplified-import-export' ),
				'syncFailedTitle'            => __( 'Synchronization Failed', 'amplified-import-export' ),
				'syncFailedDesc'             => __( 'The synchronization process encountered an error and could not complete.', 'amplified-import-export' ),
				'syncCancelledTitle'         => __( 'Synchronization Cancelled', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'processedBeforeCancellation' => __( 'Processed %s file before cancellation.', 'amplified-import-export' ),
				// translators: %s is a dynamic value.
				'processedBeforeCancellationPlural' => __( 'Processed %s files before cancellation.', 'amplified-import-export' ),
				
				// Folder browser
				'goUp'                       => __( 'Go Up', 'amplified-import-export' ),
				'useThisFolder'              => __( '. (Use this folder)', 'amplified-import-export' ),
				
				// Notifications
				'dismissNotice'              => __( 'Dismiss this notice.', 'amplified-import-export' ),

				// Jobs Log
				'noJobsFound'                => __( 'No jobs found.', 'amplified-import-export' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'showingJobs'                => __( 'Showing %1$s-%2$s of %3$s jobs', 'amplified-import-export' ),
				'retryRequiresPremium'       => __( 'A valid premium license is required to retry this job. Please activate or renew your license.', 'amplified-import-export' ),
			),
		)
	);		// Localize script for Content Sync page
		if ( 'amplified-import-export_page_wp-aie-content-sync' === $admin_page ) {
			wp_localize_script(
				'amplified-import-export-scripts',
				'aieContentSync',
				array(
					'nonce'     => wp_create_nonce( 'aie_nonce' ),
					'isPremium' => function_exists( 'aie_fs' ) && aie_fs()->can_use_premium_code(),
				)
			);
		}

		wp_enqueue_style(
			'amplified-import-export-styles',
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
		__( 'Amplified Import Export', 'amplified-import-export' ),
		__( 'Amplified Import Export', 'amplified-import-export' ),
		'manage_options',
		'amplified-import-export',
		array( $this, 'display_welcome_page' ),
		'dashicons-update-alt',
		99,
	);

	add_submenu_page(
		'amplified-import-export',
		__( 'Welcome', 'amplified-import-export' ),
		__( 'Welcome', 'amplified-import-export' ) . ' 🎉',
		'manage_options',
		'amplified-import-export',
		array( $this, 'display_welcome_page' )
	);

	add_submenu_page(
		'amplified-import-export',
		__( 'Import', 'amplified-import-export' ),
		__( 'Import', 'amplified-import-export' ),
		'manage_options',
		'wp-aie-import',
		array( $this, 'display_settings_import_page' )
	);

	add_submenu_page(
			'amplified-import-export',
			__( 'Export', 'amplified-import-export' ),
			__( 'Export', 'amplified-import-export' ),
			'manage_options',
			'wp-aie-export',
			array( $this, 'display_settings_export_page' )
		);

		add_submenu_page(
			'amplified-import-export',
			__( 'Content Sync', 'amplified-import-export' ),
			__( 'Content Sync', 'amplified-import-export' ),
			'manage_options',
			'wp-aie-content-sync',
			array( $this, 'display_content_sync_page' )
		);

		add_submenu_page(
			'amplified-import-export',
			__( 'Content Updater', 'amplified-import-export' ),
			__( 'Content Updater', 'amplified-import-export' ),
			'manage_options',
			'wp-aie-content-updater',
			array( $this, 'display_content_updater_page' )
		);

		add_submenu_page(
			'amplified-import-export',
			__( 'Media Sync', 'amplified-import-export' ),
			__( 'Media Sync', 'amplified-import-export' ),
			'manage_options',
			'wp-aie-media-sync',
			array( $this, 'display_media_sync_page' )
		);

		add_submenu_page(
			'amplified-import-export',
			__( 'AI URL Importer', 'amplified-import-export' ),
			__( 'AI URL Importer', 'amplified-import-export' ) . ' 🤖',
			'manage_options',
			'wp-aie-ai-url-importer',
			array( $this, 'display_ai_url_importer_page' )
		);

		add_submenu_page(
			'amplified-import-export',
			__( 'Functions', 'amplified-import-export' ),
			__( 'Functions', 'amplified-import-export' ),
			'manage_options',
			'wp-aie-functions',
			array( $this, 'display_settings_functions_page' )
		);

		add_submenu_page(
			'amplified-import-export',
			__( 'Jobs Log', 'amplified-import-export' ),
			__( 'Jobs Log', 'amplified-import-export' ),
			'manage_options',
			'wp-aie-jobs-log',
			array( $this, 'display_jobs_log_page' )
		);

		add_submenu_page(
			'amplified-import-export',
			__( 'Plugin Options', 'amplified-import-export' ),
			__( 'Plugin Options', 'amplified-import-export' ),
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
	 */ // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request().
	function display_settings_functions_page() {
		WP_AIE()->View->load( 'settings/functions' );
	}

	/**
	 * Display Plugin Options Page
	 */
	function display_plugin_options_page() {
		WP_AIE()->View->load( 'settings/plugin_options' );
	}

	/**
	 * Display Welcome Page
	 */
	function display_welcome_page() {
		include WP_AIE_PATH . 'app/View/settings/welcome.php';
	}

	/**
	 * Handle redirect to welcome page after activation
	 */
	function welcome_redirect() {
		// Check if we should redirect
		if ( get_transient( 'aie_activation_redirect' ) ) {
			delete_transient( 'aie_activation_redirect' );
			
			// Don't redirect if activating multiple plugins at once
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking for bulk activation, no nonce needed.
			if ( ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=amplified-import-export' ) );
				exit;
			}
		}
	}

	/**
	 * Filter wp_get_attachment_url to return the correct URL for attachments
	 * imported with "keep in current directory" mode where the file lives outside
	 * the uploads directory. WordPress would otherwise prepend the uploads base URL
	 * to the stored absolute path, producing double-slashes and a wrong URL.
	 *
	 * @param string $url     Current attachment URL.
	 * @param int    $post_id Attachment post ID.
	 * @return string Correct URL.
	 */
	public function fix_keep_mode_attachment_url( $url, $post_id ) {
		$custom_url = get_post_meta( $post_id, 'aie_file_url', true );
		if ( ! empty( $custom_url ) ) {
			return $custom_url;
		}
		return $url;
	}
}
