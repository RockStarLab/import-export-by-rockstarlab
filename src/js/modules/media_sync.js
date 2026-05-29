/**
 * Media Sync Module
 *
 * Handles media folder sync functionality
 */

import Utils from './utils';

const MediaSyncModule = {
	jobId: null,
	progressInterval: null,
	scannedFiles: [],
	isPaused: false,

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#rsl-ie-media-sync' ).length ) {
			return;
		}

		this.bindEvents();

		// Resume job from Jobs Log (admin.php?page=rsl-ie-media-sync&resume_job=<id>)
		const urlParams = new URLSearchParams( window.location.search );
		const resumeJobIdRaw = urlParams.get( 'resume_job' );
		if ( resumeJobIdRaw ) {
			const resumeJobId = parseInt( resumeJobIdRaw, 10 );
			if ( Number.isFinite( resumeJobId ) && resumeJobId > 0 ) {
				this.jobId = resumeJobId;
				this.isPaused = false;

				// Hide scan + options and show progress section.
				jQuery(
					'.rsl-ie-scan-section, .rsl-ie-options-section'
				).hide();
				jQuery( '#rsl-ie-sync-progress-section' ).show();

				// Start processing and progress tracking.
				if ( this.progressInterval ) {
					clearInterval( this.progressInterval );
					this.progressInterval = null;
				}
				this.startProgressTracking();
				this.triggerBatchProcessing();
			}
		}
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $page = jQuery( '#rsl-ie-media-sync' );

		// File type selection
		$page.on( 'change', '#rsl-ie-file-types', ( e ) => {
			const val = jQuery( e.target ).val();
			if ( val === 'custom' ) {
				jQuery( '#rsl-ie-custom-extensions' ).show();
			} else {
				jQuery( '#rsl-ie-custom-extensions' ).hide();
			}

			// Re-scan if files already scanned
			if ( this.scannedFiles.length > 0 ) {
				this.scanFolder();
			}
		} );

		// Custom extensions change - auto re-scan with debounce
		$page.on(
			'input',
			'#rsl-ie-custom-extensions-input',
			Utils.debounce( () => {
				// Re-scan if files already scanned and custom type selected
				if (
					this.scannedFiles.length > 0 &&
					jQuery( '#rsl-ie-file-types' ).val() === 'custom'
				) {
					this.scanFolder();
				}
			}, 500 )
		);

		// Scan folder button
		$page.on( 'click', '#rsl-ie-scan-folder-btn', ( e ) => {
			e.preventDefault();
			this.scanFolder();
		} );

		// Select all files
		$page.on( 'change', '#rsl-ie-select-all-files', ( e ) => {
			const checked = jQuery( e.target ).is( ':checked' );
			jQuery( '.rsl-ie-file-checkbox' ).prop( 'checked', checked );
			this.updateSelectedCount();
		} );

		// Individual file selection
		$page.on( 'change', '.rsl-ie-file-checkbox', () => {
			this.updateSelectedCount();
		} );

		// Start sync button
		$page.on( 'click', '#rsl-ie-start-sync-btn', ( e ) => {
			e.preventDefault();
			this.startSync();
		} );

		// Pause sync
		$page.on( 'click', '#rsl-ie-pause-sync-btn', ( e ) => {
			e.preventDefault();
			if ( this.isPaused ) {
				this.resumeSync();
			} else {
				this.pauseSync();
			}
		} );

		// Cancel sync
		$page.on( 'click', '#rsl-ie-cancel-sync-btn', ( e ) => {
			e.preventDefault();
			this.cancelSync();
		} );

		// Sync another folder
		$page.on( 'click', '#rsl-ie-sync-another-btn', ( e ) => {
			e.preventDefault();
			this.resetPage();
		} );

		// Browse folders button
		$page.on( 'click', '#rsl-ie-browse-folders-btn', ( e ) => {
			e.preventDefault();
			this.openFolderBrowser();
		} );

		// Close modal
		$page.on(
			'click',
			'.rsl-ie-modal-close, .rsl-ie-modal-overlay',
			( e ) => {
				e.preventDefault();
				this.closeFolderBrowser();
			}
		);

		// Folder item click
		$page.on( 'click', '.rsl-ie-folder-item', ( e ) => {
			e.preventDefault();
			const $item = jQuery( e.currentTarget );

			// Toggle selection
			jQuery( '.rsl-ie-folder-item' ).removeClass( 'selected' );
			$item.addClass( 'selected' );

			// Enable choose button and update path
			const path = $item.data( 'path' );
			jQuery( '#rsl-ie-selected-folder-path' ).val( path );
			jQuery( '#rsl-ie-choose-folder-btn' ).prop( 'disabled', false );
		} );

		// Folder double-click to navigate
		$page.on( 'dblclick', '.rsl-ie-folder-item', ( e ) => {
			e.preventDefault();
			const $item = jQuery( e.currentTarget );
			const path = $item.data( 'path' );
			this.browseFolders( path );
		} );

		// Go up button
		$page.on( 'click', '#rsl-ie-folder-up-btn', ( e ) => {
			e.preventDefault();
			const parentPath = jQuery( '#rsl-ie-folder-up-btn' ).data(
				'parent'
			);
			if ( parentPath ) {
				this.browseFolders( parentPath );
			}
		} );

		// Choose folder button
		$page.on( 'click', '#rsl-ie-choose-folder-btn', ( e ) => {
			e.preventDefault();
			const path = jQuery( '#rsl-ie-selected-folder-path' ).val();
			if ( path ) {
				jQuery( '#rsl-ie-folder-path' ).val( path );
				this.closeFolderBrowser();
			}
		} );
	},

	/**
	 * Scan folder for media files
	 */
	scanFolder() {
		// Reset any previous sync state
		this.jobId = null;
		this.isPaused = false;

		// Hide progress and completion sections if they were visible
		jQuery(
			'#rsl-ie-sync-progress-section, #rsl-ie-sync-completion'
		).hide();

		let folderPath = jQuery( '#rsl-ie-folder-path' ).val().trim();

		if ( ! folderPath ) {
			Utils.showNotice( window.rslIeData.i18n.enterFolderPath, 'error' );
			return;
		}

		// Remove trailing slash if present (keep leading slash for absolute paths)
		if ( folderPath.length > 1 && folderPath.endsWith( '/' ) ) {
			folderPath = folderPath.slice( 0, -1 );
		}

		const options = {
			recursive: jQuery( '#rsl-ie-scan-recursive' ).is( ':checked' ),
			file_types: jQuery( '#rsl-ie-file-types' ).val(),
		};

		if ( options.file_types === 'custom' ) {
			options.custom_types = jQuery( '#rsl-ie-custom-extensions-input' )
				.val()
				.split( ',' )
				.map( ( ext ) => ext.trim() )
				.filter( ( ext ) => ext );
		}

		jQuery( '#rsl-ie-scan-folder-btn' )
			.prop( 'disabled', true )
			.text( window.rslIeData.i18n.scanning );

		jQuery
			.ajax( {
				url: window.rslIeData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'rsl_ie_scan_folder',
					nonce: window.rslIeData?.nonce || '',
					folder_path: folderPath,
					options: options,
				},
			} )
			.done( ( response ) => {
				if ( response.success ) {
					this.scannedFiles = response.data.files || [];

					if ( this.scannedFiles.length === 0 ) {
						// Show empty state message
						this.showEmptyState();
						Utils.showNotice(
							window.rslIeData.i18n.noFilesFoundCriteria ||
								'No files found matching the criteria',
							'info'
						);
					} else {
						// Show file list with checkboxes for selection
						this.displayFiles( this.scannedFiles );
						const message = (
							window.rslIeData.i18n.foundFilesReadyToSync ||
							'Found %d files ready to sync'
						).replace( '%d', this.scannedFiles.length );
						Utils.showNotice( message, 'success' );

						// Show sync options
						jQuery( '#rsl-ie-sync-options' ).slideDown();
					}
				} else {
					Utils.showNotice(
						response.data?.message || 'Scan failed',
						'error'
					);
				}
			} )
			.fail( () => {
				Utils.showNotice(
					window.rslIeData.i18n.requestFailed,
					'error'
				);
			} )
			.always( () => {
				jQuery( '#rsl-ie-scan-folder-btn' )
					.prop( 'disabled', false )
					.html(
						`<span class="dashicons dashicons-search"></span> ${
							window.rslIeData.i18n.scanFolder || 'Scan Folder'
						}`
					);
			} );
	},

	/**
	 * Show empty state when no files found
	 */
	showEmptyState() {
		const $list = jQuery( '#rsl-ie-file-list' );
		$list.html( `
			<div class="rsl-ie-empty-state">
				<span class="dashicons dashicons-search"></span>
				<h3>${ window.rslIeData.i18n.noFilesFoundTitle || 'No Files Found' }</h3>
				<p>${
					window.rslIeData.i18n.noFilesFoundDesc ||
					'No files matching your criteria were found in the selected folder.'
				}</p>
				<div class="rsl-ie-empty-suggestions">
					<strong>${ window.rslIeData.i18n.suggestions || 'Suggestions' }:</strong>
					<ul>
						<li>${
							window.rslIeData.i18n.checkFolderPath ||
							'Check if the folder path is correct'
						}</li>
						<li>${
							window.rslIeData.i18n.enableScanRecursive ||
							'Try enabling "Scan Recursive" to search in subfolders'
						}</li>
						<li>${
							window.rslIeData.i18n.changeFileTypeFilter ||
							'Change the file type filter'
						}</li>
						<li>${
							window.rslIeData.i18n.makeSureFolderContains ||
							'Make sure the folder contains supported media files'
						}</li>
					</ul>
				</div>
			</div>
		` );

		// Hide file selection controls
		jQuery( '.rsl-ie-file-list-controls' ).hide();

		// Show scan results section but hide stats
		jQuery( '#rsl-ie-scan-results .rsl-ie-scan-stats' ).hide();
		jQuery( '#rsl-ie-scan-results' ).slideDown();

		// Hide sync options
		jQuery( '#rsl-ie-sync-options' ).hide();
	},

	/**
	 * Display scan summary (instead of full file list)
	 */
	displayScanSummary( files ) {
		if ( ! files || files.length === 0 ) {
			jQuery( '#rsl-ie-scan-results' ).hide();
			return;
		}

		let totalSize = 0;
		const fileTypes = {};

		files.forEach( ( file ) => {
			totalSize += file.size || 0;

			// Count file types
			const ext = file.name.split( '.' ).pop().toLowerCase();
			fileTypes[ ext ] = ( fileTypes[ ext ] || 0 ) + 1;
		} );

		// Display summary
		const $list = jQuery( '#rsl-ie-file-list' );
		const foundMessage = (
			window.rslIeData.i18n.foundFilesReadySync ||
			'Found %1$s files ready for synchronization (Total: %2$s)'
		)
			.replace( '%1$s', `<strong>${ files.length }</strong>` )
			.replace(
				'%2$s',
				`<strong>${ Utils.formatBytes( totalSize ) }</strong>`
			);

		$list.html( `
			<div class="rsl-ie-scan-summary">
				<div class="rsl-ie-summary-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="rsl-ie-summary-content">
					<h3>${ window.rslIeData.i18n.scanComplete || 'Scan Complete' }</h3>
					<p>${ foundMessage }</p>
					<div class="rsl-ie-file-types">
						<strong>${ window.rslIeData.i18n.fileTypes || 'File Types' }:</strong>
						${ Object.entries( fileTypes )
							.map(
								( [ ext, count ] ) =>
									`<span class="rsl-ie-type-badge">${ ext.toUpperCase() } (${ count })</span>`
							)
							.join( '' ) }
					</div>
					<p class="rsl-ie-summary-note">
						<span class="dashicons dashicons-info"></span>
						${
							window.rslIeData.i18n.filesProcessedBatches ||
							'All files will be processed in batches. Click "Start Sync" below to begin.'
						}
					</p>
				</div>
			</div>
		` );

		// Update stats
		jQuery( '#rsl-ie-total-files' ).text( files.length );
		jQuery( '#rsl-ie-total-size' ).text( Utils.formatBytes( totalSize ) );
		jQuery( '#rsl-ie-selected-count' ).text( files.length );

		// Show stats and scan results
		jQuery( '#rsl-ie-scan-results .rsl-ie-scan-stats' ).show();
		jQuery( '#rsl-ie-scan-results' ).slideDown();
	},

	/**
	 * Display scanned files with checkboxes for selection
	 */
	displayFiles( files ) {
		const $list = jQuery( '#rsl-ie-file-list' );
		$list.empty();

		if ( ! files || files.length === 0 ) {
			jQuery( '#rsl-ie-scan-results' ).hide();
			return;
		}

		let totalSize = 0;

		files.forEach( ( file ) => {
			totalSize += file.size || 0;

			const icon = this.getFileIcon( file.name );
			// Use data-path to safely store the full path (supports spaces, quotes, etc.)
			// value attribute is set to escaped path for form compatibility
			const $item = jQuery( '<div class="rsl-ie-file-item"></div>' );
			const $checkbox = jQuery(
				'<input type="checkbox" class="rsl-ie-file-checkbox" checked>'
			);
			$checkbox.val( file.path ); // jQuery .val() sets DOM property, safe for any string
			$checkbox.attr( 'data-path', file.path );
			$item.append( $checkbox );
			$item.append(
				jQuery( `
					<div class="rsl-ie-file-icon">
						<span class="dashicons ${ icon }"></span>
					</div>
					<div class="rsl-ie-file-info">
						<div class="rsl-ie-file-name">${ this.escapeHtml( file.name ) }</div>
						<div class="rsl-ie-file-meta">
							<span>${ Utils.formatBytes( file.size ) }</span>
							<span>${ this.escapeHtml( file.path ) }</span>
						</div>
					</div>
			` )
			);

			$list.append( $item );
		} );

		// Update stats
		jQuery( '#rsl-ie-total-files' ).text( files.length );
		jQuery( '#rsl-ie-total-size' ).text( Utils.formatBytes( totalSize ) );

		// Show file selection controls
		jQuery( '.rsl-ie-file-list-controls' ).show();

		// Show stats and scan results
		jQuery( '#rsl-ie-scan-results .rsl-ie-scan-stats' ).show();
		jQuery( '#rsl-ie-scan-results' ).slideDown();

		this.updateSelectedCount();
	},

	/**
	 * Get file icon based on extension
	 */
	getFileIcon( filename ) {
		const ext = filename.split( '.' ).pop().toLowerCase();

		const icons = {
			// Images
			jpg: 'dashicons-format-image',
			jpeg: 'dashicons-format-image',
			png: 'dashicons-format-image',
			gif: 'dashicons-format-image',
			webp: 'dashicons-format-image',
			svg: 'dashicons-format-image',
			// Videos
			mp4: 'dashicons-format-video',
			avi: 'dashicons-format-video',
			mov: 'dashicons-format-video',
			wmv: 'dashicons-format-video',
			// Audio
			mp3: 'dashicons-format-audio',
			wav: 'dashicons-format-audio',
			ogg: 'dashicons-format-audio',
			// Documents
			pdf: 'dashicons-pdf',
			doc: 'dashicons-media-document',
			docx: 'dashicons-media-document',
			xls: 'dashicons-media-spreadsheet',
			xlsx: 'dashicons-media-spreadsheet',
		};

		return icons[ ext ] || 'dashicons-media-default';
	},

	/**
	 * Update selected files count
	 */
	updateSelectedCount() {
		const count = jQuery( '.rsl-ie-file-checkbox:checked' ).length;
		jQuery( '#rsl-ie-selected-count' ).text( count );

		// Update select all checkbox
		const total = jQuery( '.rsl-ie-file-checkbox' ).length;
		jQuery( '#rsl-ie-select-all-files' ).prop( 'checked', count === total );
	},

	/**
	 * Get selected files
	 */
	getSelectedFiles() {
		const files = [];
		const self = this;
		jQuery( '.rsl-ie-file-checkbox:checked' ).each( function () {
			// Prefer data-path (set as DOM attribute, preserves spaces/special chars)
			// Fall back to .val() for legacy rendered checkboxes
			const path = jQuery( this ).data( 'path' ) || jQuery( this ).val();
			const fileData = self.scannedFiles.find( ( f ) => f.path === path );
			if ( fileData ) {
				files.push( fileData );
			}
		} );
		return files;
	},

	/**
	 * Get sync options
	 */
	getOptions() {
		const fileOperation = jQuery( '#rsl-ie-copy-files' ).val();
		const batchSize = parseInt( jQuery( '#rsl-ie-batch-size' ).val() ) || 3;

		return {
			duplicate_check: jQuery( '#rsl-ie-duplicate-check' ).val(),
			duplicate_handling: jQuery( '#rsl-ie-duplicate-handling' ).val(),
			file_operation: fileOperation,
			copy_files: fileOperation === 'copy',
			generate_thumbnails: true, // Always generate thumbnails
			rml_integration: jQuery( '#rsl-ie-rml-integration' ).is(
				':checked'
			),
			batch_size: batchSize,
		};
	},

	/**
	 * Start media sync
	 */
	startSync() {
		if ( ! this.scannedFiles || this.scannedFiles.length === 0 ) {
			Utils.showNotice( window.rslIeData.i18n.noFilesToSync, 'error' );
			return;
		}

		// Get selected files
		const selectedFiles = this.getSelectedFiles();

		if ( selectedFiles.length === 0 ) {
			Utils.showNotice(
				window.rslIeData.i18n.noFilesSelected ||
					'Please select at least one file to sync',
				'error'
			);
			return;
		}

		// Clear any previous progress interval
		if ( this.progressInterval ) {
			clearInterval( this.progressInterval );
			this.progressInterval = null;
		}

		// Reset state
		this.isPaused = false;

		const folderPath = jQuery( '#rsl-ie-folder-path' ).val().trim();

		if ( ! folderPath ) {
			Utils.showNotice(
				window.rslIeData.i18n.invalidFolderPath,
				'error'
			);
			return;
		}

		// Get scan options (for filtering on backend)
		const scanOptions = {
			recursive: jQuery( '#rsl-ie-scan-recursive' ).is( ':checked' ),
			file_types: jQuery( '#rsl-ie-file-types' ).val(),
		};

		if ( scanOptions.file_types === 'custom' ) {
			scanOptions.custom_types = jQuery(
				'#rsl-ie-custom-extensions-input'
			)
				.val()
				.split( ',' )
				.map( ( ext ) => ext.trim() )
				.filter( ( ext ) => ext );
		}

		const syncOptions = this.getOptions();

		// Disable button and show loading state
		const $btn = jQuery( '#rsl-ie-start-sync-btn' );
		const originalText = $btn.html();
		$btn.prop( 'disabled', true ).html(
			`<span class="dashicons dashicons-update rsl-ie-spin"></span> ${ window.rslIeData.i18n.starting }`
		);

		jQuery
			.ajax( {
				url: window.rslIeData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'rsl_ie_start_media_sync',
					nonce: window.rslIeData?.nonce || '',
					folder_path: folderPath,
					selected_files: selectedFiles, // Send selected files
					scan_options: scanOptions,
					sync_options: syncOptions,
				},
			} )
			.done( ( response ) => {
				if ( response.success ) {
					this.jobId = response.data.job_id;

					// Hide scan and options sections
					jQuery(
						'.rsl-ie-scan-section, .rsl-ie-options-section'
					).slideUp();

					// Show progress section immediately
					jQuery( '#rsl-ie-sync-progress-section' ).slideDown();

					// Initialize progress at 0%
					jQuery( '#rsl-ie-progress-fill' ).css( 'width', '0%' );
					jQuery( '#rsl-ie-progress-percentage' ).text( '0%' );
					jQuery( '#rsl-ie-sync-status' ).text(
						window.rslIeData.i18n.processing
					);

					// Start tracking progress
					this.startProgressTracking();

					// Trigger first batch processing immediately
					this.triggerBatchProcessing();

					Utils.showNotice(
						window.rslIeData.i18n.syncStarted,
						'success'
					);
				} else {
					Utils.showNotice(
						response.data?.message || 'Failed to start sync',
						'error'
					);
					$btn.prop( 'disabled', false ).html( originalText );
				}
			} )
			.fail( () => {
				Utils.showNotice(
					window.rslIeData.i18n.requestFailed,
					'error'
				);
				$btn.prop( 'disabled', false ).html( originalText );
			} );
	},

	/**
	 * Start progress tracking
	 */
	startProgressTracking() {
		// Check progress every 2 seconds
		this.progressInterval = setInterval( () => {
			this.checkProgress();
		}, 2000 );

		// Check immediately
		this.checkProgress();
	},

	/**
	 * Trigger batch processing (starts the actual work)
	 */
	triggerBatchProcessing() {
		jQuery
			.ajax( {
				url: window.rslIeData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'rsl_ie_process_media_sync_batch',
					nonce: window.rslIeData?.nonce || '',
					job_id: this.jobId,
				},
			} )
			.done( ( response ) => {
				// If not completed, process next batch after small delay
				if (
					response.success &&
					response.data &&
					! response.data.completed
				) {
					setTimeout( () => {
						this.triggerBatchProcessing();
					}, 100 );
				}
			} )
			.fail( ( xhr, status, error ) => {} );
	},

	/**
	 * Check sync progress
	 */
	checkProgress() {
		jQuery
			.ajax( {
				url: window.rslIeData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'rsl_ie_get_sync_progress',
					nonce: window.rslIeData?.nonce || '',
					job_id: this.jobId,
				},
			} )
			.done( ( response ) => {
				if ( response.success && response.data ) {
					this.updateProgress( response.data );
				} else {
				}
			} )
			.fail( ( xhr, status, error ) => {} );
	},

	/**
	 * Update stats only (helper method)
	 */
	updateStats( result ) {
		// Ensure result is an object
		if ( typeof result === 'string' ) {
			try {
				result = JSON.parse( result );
			} catch ( e ) {
				result = {};
			}
		}

		result = result || {};

		const processed = result.processed !== undefined ? result.processed : 0;
		const success = result.success !== undefined ? result.success : 0;
		const skipped = result.skipped !== undefined ? result.skipped : 0;
		const failed = result.failed !== undefined ? result.failed : 0;

		jQuery( '#rsl-ie-stat-processed' ).text( processed );
		jQuery( '#rsl-ie-stat-success' ).text( success );
		jQuery( '#rsl-ie-stat-skipped' ).text( skipped );
		jQuery( '#rsl-ie-stat-failed' ).text( failed );
	},

	/**
	 * Update progress UI
	 */
	updateProgress( data ) {
		// Parse progress as integer (remove decimals)
		const progress = Math.round( parseFloat( data.progress ) || 0 );
		const status = data.status || 'processing';

		// Update progress bar
		jQuery( '#rsl-ie-progress-fill' ).css( 'width', progress + '%' );
		jQuery( '#rsl-ie-progress-percentage' ).text( progress + '%' );

		// Update stats - handle both object and null
		let result = data.result;

		// If result is a string, try to parse it
		if ( typeof result === 'string' ) {
			try {
				result = JSON.parse( result );
			} catch ( e ) {
				result = {};
			}
		}

		// Ensure result is an object
		result = result || {};

		// Update stats with explicit checks
		// Show 0 if undefined (processing hasn't generated results yet)
		const processed = result.processed !== undefined ? result.processed : 0;
		const success = result.success !== undefined ? result.success : 0;
		const skipped = result.skipped !== undefined ? result.skipped : 0;
		const failed = result.failed !== undefined ? result.failed : 0;

		jQuery( '#rsl-ie-stat-processed' ).text( processed );
		jQuery( '#rsl-ie-stat-success' ).text( success );
		jQuery( '#rsl-ie-stat-skipped' ).text( skipped );
		jQuery( '#rsl-ie-stat-failed' ).text( failed );

		// Update status text (fix selector - was #rsl-ie-progress-status, should be #rsl-ie-sync-status)
		const statusTexts = {
			pending: window.rslIeData.i18n.starting || 'Starting...',
			processing:
				window.rslIeData.i18n.syncInProgress ||
				'Synchronization in Progress',
			completed: window.rslIeData.i18n.statusCompleted || 'Completed',
			failed: window.rslIeData.i18n.statusFailed || 'Failed',
			cancelled: window.rslIeData.i18n.statusCancelled || 'Cancelled',
			paused: window.rslIeData.i18n.statusPaused || 'Paused',
		};

		const statusText = statusTexts[ status ] || 'Processing...';

		// Update both possible selectors to be safe
		jQuery( '#rsl-ie-sync-status' ).text( statusText );
		jQuery( '#rsl-ie-progress-status' ).text( statusText );

		// Show errors if any
		if ( result.errors && result.errors.length > 0 ) {
			this.displayErrors( result.errors );
		}

		// Check if completed
		if (
			status === 'completed' ||
			status === 'failed' ||
			status === 'cancelled'
		) {
			clearInterval( this.progressInterval );
			this.showCompletion( data );
		}
	},

	/**
	 * Display errors
	 */
	displayErrors( errors ) {
		const $errorLog = jQuery( '#rsl-ie-error-log' );
		const $errorList = jQuery( '#rsl-ie-error-list' );

		$errorList.empty();

		errors.slice( 0, 20 ).forEach( ( error ) => {
			$errorList.append( `<li>${ this.escapeHtml( error ) }</li>` );
		} );

		if ( errors.length > 20 ) {
			const moreErrorsMsg = (
				window.rslIeData.i18n.andMoreErrors || '... and %d more errors'
			).replace( '%d', errors.length - 20 );
			$errorList.append( `<li>${ moreErrorsMsg }</li>` );
		}

		$errorLog.show();
	},

	/**
	 * Show completion
	 */
	showCompletion( data ) {
		// Hide progress section
		jQuery( '#rsl-ie-sync-progress-section' ).slideUp();

		// Show completion section
		jQuery( '#rsl-ie-sync-completion' ).slideDown();

		// Parse result if needed
		let result = data.result;
		if ( typeof result === 'string' ) {
			try {
				result = JSON.parse( result );
			} catch ( e ) {
				result = {};
			}
		}
		result = result || {};

		// Get stats
		const processed = result.processed || 0;
		const success = result.success || 0;
		const skipped = result.skipped || 0;
		const failed = result.failed || 0;

		// Create beautiful completion message
		let messageHtml = '';

		if ( data.status === 'completed' ) {
			// Success message with emoji and stats
			const processedMsg =
				processed !== 1
					? (
							window.rslIeData.i18n.successfullyProcessedPlural ||
							'Successfully processed %s files'
					  ).replace( '%s', `<strong>${ processed }</strong>` )
					: (
							window.rslIeData.i18n.successfullyProcessed ||
							'Successfully processed %s file'
					  ).replace( '%s', `<strong>${ processed }</strong>` );

			messageHtml = `
				<div style="text-align: center; padding: 20px;">
					<div style="font-size: 64px; margin-bottom: 15px;">🎉</div>
					<h3 style="color: #00a32a; margin: 0 0 15px; font-size: 24px;">${
						window.rslIeData.i18n.syncCompleteTitle ||
						'Synchronization Complete!'
					}</h3>
					<p style="font-size: 16px; color: #1d2327; margin-bottom: 20px;">
						${ processedMsg }
					</p>
					<div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;">
						<div style="text-align: center;">
							<div style="font-size: 32px; color: #00a32a; font-weight: 600;">${ success }</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">✅ ${
								window.rslIeData.i18n.imported || 'Imported'
							}</div>
						</div>
						${
							skipped > 0
								? `
						<div style="text-align: center;">
							<div style="font-size: 32px; color: #dba617; font-weight: 600;">${ skipped }</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">⏭️ ${
								window.rslIeData.i18n.skipped || 'Skipped'
							}</div>
						</div>
						`
								: ''
						}
						${
							failed > 0
								? `
						<div style="text-align: center;">
							<div style="font-size: 32px; color: #d63638; font-weight: 600;">${ failed }</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">❌ ${
								window.rslIeData.i18n.statusFailed || 'Failed'
							}</div>
						</div>
						`
								: ''
						}
					</div>
				</div>
			`;
		} else if ( data.status === 'failed' ) {
			messageHtml = `
				<div style="text-align: center; padding: 20px;">
					<div style="font-size: 64px; margin-bottom: 15px;">⚠️</div>
					<h3 style="color: #d63638; margin: 0 0 15px; font-size: 24px;">${
						window.rslIeData.i18n.syncFailedTitle ||
						'Synchronization Failed'
					}</h3>
					<p style="font-size: 16px; color: #646970;">
						${
							window.rslIeData.i18n.syncFailedDesc ||
							'The synchronization process encountered an error and could not complete.'
						}
					</p>
				</div>
			`;
		} else if ( data.status === 'cancelled' ) {
			const cancelledMsg =
				processed !== 1
					? (
							window.rslIeData.i18n
								.processedBeforeCancellationPlural ||
							'Processed %s files before cancellation.'
					  ).replace( '%s', `<strong>${ processed }</strong>` )
					: (
							window.rslIeData.i18n.processedBeforeCancellation ||
							'Processed %s file before cancellation.'
					  ).replace( '%s', `<strong>${ processed }</strong>` );

			messageHtml = `
				<div style="text-align: center; padding: 20px;">
					<div style="font-size: 64px; margin-bottom: 15px;">🛑</div>
					<h3 style="color: #dba617; margin: 0 0 15px; font-size: 24px;">${
						window.rslIeData.i18n.syncCancelledTitle ||
						'Synchronization Cancelled'
					}</h3>
					<p style="font-size: 16px; color: #646970;">
						${ cancelledMsg }
					</p>
				</div>
			`;
		}

		jQuery( '#rsl-ie-completion-message' ).html( messageHtml );
	},

	/**
	 * Pause sync
	 */
	pauseSync() {
		jQuery
			.ajax( {
				url: window.rslIeData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'rsl_ie_pause_media_sync',
					nonce: window.rslIeData?.nonce || '',
					job_id: this.jobId,
				},
			} )
			.done( ( response ) => {
				if ( response.success ) {
					this.isPaused = true;
					clearInterval( this.progressInterval );

					// Update UI
					const $header = jQuery(
						'#rsl-ie-sync-progress-section .rsl-ie-card-header h2'
					);
					$header.html(
						`<span class="dashicons dashicons-controls-pause"></span> ${ window.rslIeData.i18n.syncPaused }`
					);

					// Update status text
					jQuery( '#rsl-ie-progress-status' ).text(
						window.rslIeData.i18n.paused
					);
					jQuery( '#rsl-ie-sync-status' ).text(
						window.rslIeData.i18n.paused
					);
					const $pauseBtn = jQuery( '#rsl-ie-pause-sync-btn' );
					$pauseBtn.html(
						`<span class="dashicons dashicons-controls-play"></span> ${ window.rslIeData.i18n.resume }`
					);

					Utils.showNotice(
						window.rslIeData.i18n.syncPaused,
						'info'
					);
				}
			} );
	},

	/**
	 * Resume sync
	 */
	resumeSync() {
		jQuery
			.ajax( {
				url: window.rslIeData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'rsl_ie_resume_media_sync',
					nonce: window.rslIeData?.nonce || '',
					job_id: this.jobId,
				},
			} )
			.done( ( response ) => {
				if ( response.success ) {
					this.isPaused = false;

					// Update UI
					const $header = jQuery(
						'#rsl-ie-sync-progress-section .rsl-ie-card-header h2'
					);
					$header.html(
						`<span class="dashicons dashicons-update rsl-ie-spin"></span> ${ window.rslIeData.i18n.syncInProgress }`
					);

					// Update status text
					jQuery( '#rsl-ie-progress-status' ).text(
						window.rslIeData.i18n.syncInProgress
					);
					jQuery( '#rsl-ie-sync-status' ).text(
						window.rslIeData.i18n.syncInProgress
					);
					const $pauseBtn = jQuery( '#rsl-ie-pause-sync-btn' );
					$pauseBtn.html(
						`<span class="dashicons dashicons-controls-pause"></span> ${ window.rslIeData.i18n.pause }`
					);

					// Restart progress monitoring
					this.startProgressTracking();

					// Trigger batch processing to continue
					this.triggerBatchProcessing();

					Utils.showNotice(
						window.rslIeData.i18n.syncResumed,
						'success'
					);
				}
			} );
	},

	/**
	 * Cancel sync
	 */
	cancelSync() {
		if ( ! confirm( window.rslIeData.i18n.confirmCancelSync ) ) {
			return;
		}

		jQuery
			.ajax( {
				url: window.rslIeData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'rsl_ie_cancel_media_sync',
					nonce: window.rslIeData?.nonce || '',
					job_id: this.jobId,
				},
			} )
			.done( ( response ) => {
				if ( response.success ) {
					clearInterval( this.progressInterval );
					Utils.showNotice(
						window.rslIeData.i18n.syncCancelled,
						'warning'
					);
					this.resetPage();
				}
			} );
	},

	/**
	 * Reset page to initial state
	 */
	resetPage() {
		// Hide all sections
		jQuery(
			'#rsl-ie-scan-results, #rsl-ie-sync-options, #rsl-ie-sync-progress-section, #rsl-ie-sync-completion'
		).hide();

		// Show scan section
		jQuery( '.rsl-ie-scan-section' ).show();

		// Reset form
		jQuery( '#rsl-ie-folder-path' ).val( '' );
		jQuery( '#rsl-ie-file-list' ).empty();

		// Show file selection controls again
		jQuery( '.rsl-ie-file-list-controls' ).show();

		// Reset Start button
		const $startBtn = jQuery( '#rsl-ie-start-sync-btn' );
		$startBtn.prop( 'disabled', false );
		$startBtn.html(
			`<span class="dashicons dashicons-controls-play"></span> ${ window.rslIeData.i18n.startSync }`
		);

		// Reset Scan button
		const $scanBtn = jQuery( '#rsl-ie-scan-folder-btn' );
		$scanBtn
			.prop( 'disabled', false )
			.text( window.rslIeData.i18n.scanFolder );

		// Reset progress bar and stats
		jQuery( '#rsl-ie-progress-fill' ).css( 'width', '0%' );
		jQuery( '#rsl-ie-progress-percentage' ).text( '0%' );
		jQuery( '#rsl-ie-stat-processed' ).text( '0' );
		jQuery( '#rsl-ie-stat-success' ).text( '0' );
		jQuery( '#rsl-ie-stat-skipped' ).text( '0' );
		jQuery( '#rsl-ie-stat-failed' ).text( '0' );

		// Reset data
		this.jobId = null;
		this.scannedFiles = [];
		this.isPaused = false;

		if ( this.progressInterval ) {
			clearInterval( this.progressInterval );
		}

		// Reset pause button to default state
		const $pauseBtn = jQuery( '#rsl-ie-pause-sync-btn' );
		$pauseBtn.html(
			`<span class="dashicons dashicons-controls-pause"></span> ${
				window.rslIeData.i18n.pause || 'Pause'
			}`
		);

		// Reset header to default state
		const $header = jQuery(
			'#rsl-ie-sync-progress-section .rsl-ie-card-header h2'
		);
		$header.html(
			`<span class="dashicons dashicons-update rsl-ie-spin"></span> ${
				window.rslIeData.i18n.syncInProgress ||
				'Synchronization in Progress'
			}`
		);
	},

	/**
	 * Escape HTML
	 */
	escapeHtml( text ) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;',
		};
		return String( text ).replace( /[&<>"']/g, ( m ) => map[ m ] );
	},

	/**
	 * Open folder browser modal
	 */
	openFolderBrowser() {
		jQuery( '#rsl-ie-folder-browser-modal' ).fadeIn( 200 );
		jQuery( 'body' ).addClass( 'rsl-ie-modal-open' );

		// Load current folder from input or start from root
		const currentFolder = jQuery( '#rsl-ie-folder-path' ).val().trim();
		this.browseFolders( currentFolder ); // Load current or root uploads directory
	},

	/**
	 * Close folder browser modal
	 */
	closeFolderBrowser() {
		jQuery( '#rsl-ie-folder-browser-modal' ).fadeOut( 200 );
		jQuery( 'body' ).removeClass( 'rsl-ie-modal-open' );
		// Don't clear selected path - it's just a temporary selection within modal
		jQuery( '#rsl-ie-choose-folder-btn' ).prop( 'disabled', true );
		jQuery( '.rsl-ie-folder-item' ).removeClass( 'selected' );
	},

	/**
	 * Browse folders via AJAX
	 */
	browseFolders( relativePath ) {
		jQuery( '#rsl-ie-folder-browser-loading' ).show();
		jQuery( '#rsl-ie-folder-browser-list' ).empty();
		jQuery( '#rsl-ie-folder-browser-empty' ).hide();
		jQuery( '#rsl-ie-folder-browser-error' ).hide();
		jQuery( '#rsl-ie-selected-folder-path' ).val( '' );
		jQuery( '#rsl-ie-choose-folder-btn' ).prop( 'disabled', true );

		jQuery
			.ajax( {
				url: window.ajaxurl || window.rslIeData?.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rsl_ie_browse_folders',
					nonce: window.rslIeData?.nonce,
					path: relativePath,
				},
			} )
			.done( ( response ) => {
				if ( response.success && response.data ) {
					this.displayFolders(
						response.data.folders,
						response.data.current_path,
						response.data.can_go_up,
						response.data.parent_path
					);
				} else {
					this.showBrowserError(
						response.data?.message || 'Failed to load folders'
					);
				}
			} )
			.fail( ( jqXHR, textStatus, errorThrown ) => {
				if ( jqXHR.responseText ) {
				}

				let errorMsg = window.rslIeData.i18n.requestFailed;

				// Check for WP_Error response
				if ( jqXHR.responseJSON ) {
					if (
						jqXHR.responseJSON.data &&
						jqXHR.responseJSON.data.message
					) {
						errorMsg = jqXHR.responseJSON.data.message;
					} else if ( jqXHR.responseJSON.message ) {
						errorMsg = jqXHR.responseJSON.message;
					}
				} else if ( jqXHR.responseText ) {
					// Try to parse HTML error
					const $html = jQuery( '<div>' ).html( jqXHR.responseText );
					const title = $html.find( 'title' ).text();
					if ( title ) {
						errorMsg = 'Server Error: ' + title;
					} else {
						// Show first line of error
						const firstLine = jqXHR.responseText
							.split( '\n' )[ 0 ]
							.substring( 0, 100 );
						if ( firstLine ) {
							errorMsg = 'Server Error: ' + firstLine;
						}
					}
				}

				if (
					errorThrown &&
					errorMsg === window.rslIeData.i18n.requestFailed
				) {
					errorMsg = 'Request failed: ' + errorThrown;
				}

				// Add status code to message
				if ( jqXHR.status && jqXHR.status !== 200 ) {
					errorMsg += ' (Status: ' + jqXHR.status + ')';
				}

				this.showBrowserError( errorMsg );
			} )
			.always( () => {
				jQuery( '#rsl-ie-folder-browser-loading' ).hide();
			} );
	},

	/**
	 * Show error in folder browser
	 */
	showBrowserError( message ) {
		jQuery( '#rsl-ie-folder-browser-error-message' ).text( message );
		jQuery( '#rsl-ie-folder-browser-error' ).slideDown();
	},

	/**
	 * Display folders in browser
	 */
	displayFolders( folders, currentPath, canGoUp, parentPath ) {
		const $list = jQuery( '#rsl-ie-folder-browser-list' );
		const $currentPath = jQuery( '#rsl-ie-current-path' );

		$list.empty();

		// Update current path display (currentPath is now an absolute server path)
		$currentPath.text( currentPath );

		// Remove any existing up button before re-rendering
		jQuery( '#rsl-ie-folder-up-btn' ).remove();

		// Show "Go Up" button when parent directory is accessible within WordPress
		if ( canGoUp && parentPath ) {
			const $upButton = jQuery( `
				<button type="button" id="rsl-ie-folder-up-btn" class="button" data-parent="${ this.escapeHtml(
					parentPath
				) }" style="margin-bottom: 10px;">
					<span class="dashicons dashicons-arrow-up-alt"></span>
					${ window.rslIeData.i18n.goUp || 'Go Up' }
				</button>
			` );
			$upButton.insertBefore( $list );
		}

		// Add "Use this folder" option — currentPath is always an absolute path (truthy),
		// fixing the bug where selecting the root uploads folder did nothing.
		const $rootOption = jQuery( `
			<div class="rsl-ie-folder-item rsl-ie-folder-current" data-path="${ this.escapeHtml(
				currentPath
			) }">
				<span class="dashicons dashicons-location"></span>
				<span class="rsl-ie-folder-name">
					<strong>${
						window.rslIeData.i18n.useThisFolder ||
						'. (Use this folder)'
					}</strong>
				</span>
			</div>
		` );
		$list.append( $rootOption );

		if ( ! folders || folders.length === 0 ) {
			jQuery( '#rsl-ie-folder-browser-empty' ).show();
			return;
		}

		// Display subfolders
		folders.forEach( ( folder ) => {
			const $item = jQuery( `
				<div class="rsl-ie-folder-item" data-path="${ this.escapeHtml( folder.path ) }">
					<span class="dashicons dashicons-category"></span>
					<span class="rsl-ie-folder-name">${ this.escapeHtml( folder.name ) }</span>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</div>
			` );
			$list.append( $item );
		} );
	},
};

export default MediaSyncModule;
