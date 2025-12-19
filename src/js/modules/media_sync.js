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
		if ( ! jQuery( '#wp-aie-media-sync' ).length ) {
			return;
		}

		this.bindEvents();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $page = jQuery( '#wp-aie-media-sync' );

		// File type selection
		$page.on( 'change', '#aie-file-types', ( e ) => {
			const val = jQuery( e.target ).val();
			if ( val === 'custom' ) {
				jQuery( '#aie-custom-extensions' ).show();
			} else {
				jQuery( '#aie-custom-extensions' ).hide();
			}
		} );

		// Scan folder button
		$page.on( 'click', '#aie-scan-folder-btn', ( e ) => {
			e.preventDefault();
			this.scanFolder();
		} );

		// Select all files
		$page.on( 'change', '#aie-select-all-files', ( e ) => {
			const checked = jQuery( e.target ).is( ':checked' );
			jQuery( '.aie-file-checkbox' ).prop( 'checked', checked );
			this.updateSelectedCount();
		} );

		// Individual file selection
		$page.on( 'change', '.aie-file-checkbox', () => {
			this.updateSelectedCount();
		} );

		// Start sync button
		$page.on( 'click', '#aie-start-sync-btn', ( e ) => {
			e.preventDefault();
			this.startSync();
		} );

		// Pause sync
		$page.on( 'click', '#aie-pause-sync-btn', ( e ) => {
			e.preventDefault();
			if ( this.isPaused ) {
				this.resumeSync();
			} else {
				this.pauseSync();
			}
		} );

		// Cancel sync
		$page.on( 'click', '#aie-cancel-sync-btn', ( e ) => {
			e.preventDefault();
			this.cancelSync();
		} );

		// Sync another folder
		$page.on( 'click', '#aie-sync-another-btn', ( e ) => {
			e.preventDefault();
			this.resetPage();
		} );

		// Browse folders button
		$page.on( 'click', '#aie-browse-folders-btn', ( e ) => {
			e.preventDefault();
			this.openFolderBrowser();
		} );

		// Close modal
		$page.on( 'click', '.aie-modal-close, .aie-modal-overlay', ( e ) => {
			e.preventDefault();
			this.closeFolderBrowser();
		} );

		// Folder item click
		$page.on( 'click', '.aie-folder-item', ( e ) => {
			e.preventDefault();
			const $item = jQuery( e.currentTarget );
			
			// Toggle selection
			jQuery( '.aie-folder-item' ).removeClass( 'selected' );
			$item.addClass( 'selected' );

			// Enable choose button and update path
			const path = $item.data( 'path' );
			jQuery( '#aie-selected-folder-path' ).val( path );
			jQuery( '#aie-choose-folder-btn' ).prop( 'disabled', false );
		} );

		// Folder double-click to navigate
		$page.on( 'dblclick', '.aie-folder-item', ( e ) => {
			e.preventDefault();
			const $item = jQuery( e.currentTarget );
			const path = $item.data( 'path' );
			this.browseFolders( path );
		} );

		// Go up button
		$page.on( 'click', '#aie-folder-up-btn', ( e ) => {
			e.preventDefault();
			const currentPath = jQuery( '#aie-current-path' ).data( 'relative-path' );
			if ( currentPath ) {
				const parts = currentPath.split( '/' ).filter( ( p ) => p );
				parts.pop();
				const parentPath = parts.join( '/' );
				this.browseFolders( parentPath );
			}
		} );

		// Choose folder button
		$page.on( 'click', '#aie-choose-folder-btn', ( e ) => {
			e.preventDefault();
			const path = jQuery( '#aie-selected-folder-path' ).val();
			if ( path ) {
				jQuery( '#aie-folder-path' ).val( path );
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
		jQuery( '#aie-sync-progress-section, #aie-sync-completion' ).hide();
		
		let folderPath = jQuery( '#aie-folder-path' ).val().trim();

		if ( ! folderPath ) {
			Utils.showNotice( window.aieData.i18n.enterFolderPath, 'error' );
			return;
		}

		// If path doesn't start with /, add it
		if ( ! folderPath.startsWith( '/' ) ) {
			folderPath = '/' + folderPath;
		}

		// Remove trailing slash if present
		if ( folderPath.length > 1 && folderPath.endsWith( '/' ) ) {
			folderPath = folderPath.slice( 0, -1 );
		}

		const options = {
			recursive: jQuery( '#aie-scan-recursive' ).is( ':checked' ),
			file_types: jQuery( '#aie-file-types' ).val(),
		};

		if ( options.file_types === 'custom' ) {
			options.custom_types = jQuery( '#aie-custom-extensions-input' )
				.val()
				.split( ',' )
				.map( ( ext ) => ext.trim() )
				.filter( ( ext ) => ext );
		}

		jQuery( '#aie-scan-folder-btn' ).prop( 'disabled', true ).text( 'Scanning...' );

		jQuery
			.ajax( {
				url: window.aieData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'aie_scan_folder',
					nonce: window.aieData?.nonce || '',
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
							'No files found matching the criteria',
							'info'
						);
					} else {
						// Show summary instead of file list
						this.displayScanSummary( this.scannedFiles );
						Utils.showNotice(
							`Found ${ this.scannedFiles.length } files ready to sync`,
							'success'
						);
						
						// Show sync options
						jQuery( '#aie-sync-options' ).slideDown();
					}
				} else {
					Utils.showNotice(
						response.data?.message || 'Scan failed',
						'error'
					);
				}
			} )
			.fail( () => {
				Utils.showNotice( window.aieData.i18n.requestFailed, 'error' );
			} )
			.always( () => {
				jQuery( '#aie-scan-folder-btn' )
					.prop( 'disabled', false )
					.html(
						'<span class="dashicons dashicons-search"></span> Scan Folder'
					);
			} );
	},

	/**
	 * Show empty state when no files found
	 */
	showEmptyState() {
		const $list = jQuery( '#aie-file-list' );
		$list.html( `
			<div class="aie-empty-state">
				<span class="dashicons dashicons-search"></span>
				<h3>No Files Found</h3>
				<p>No files matching your criteria were found in the selected folder.</p>
				<div class="aie-empty-suggestions">
					<strong>Suggestions:</strong>
					<ul>
						<li>Check if the folder path is correct</li>
						<li>Try enabling "Scan Recursive" to search in subfolders</li>
						<li>Change the file type filter</li>
						<li>Make sure the folder contains supported media files</li>
					</ul>
				</div>
			</div>
		` );
		
		// Show scan results section but hide stats
		jQuery( '#aie-scan-results .aie-scan-stats' ).hide();
		jQuery( '#aie-scan-results' ).slideDown();
		
		// Hide sync options
		jQuery( '#aie-sync-options' ).hide();
	},

	/**
	 * Display scan summary (instead of full file list)
	 */
	displayScanSummary( files ) {
		if ( ! files || files.length === 0 ) {
			jQuery( '#aie-scan-results' ).hide();
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
		const $list = jQuery( '#aie-file-list' );
		$list.html( `
			<div class="aie-scan-summary">
				<div class="aie-summary-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="aie-summary-content">
					<h3>Scan Complete</h3>
					<p>Found <strong>${ files.length } files</strong> ready for synchronization (Total: <strong>${ Utils.formatBytes( totalSize ) }</strong>)</p>
					<div class="aie-file-types">
						<strong>File Types:</strong>
						${ Object.entries( fileTypes ).map( ( [ ext, count ] ) => 
							`<span class="aie-type-badge">${ ext.toUpperCase() } (${ count })</span>`
						).join( '' ) }
					</div>
					<p class="aie-summary-note">
						<span class="dashicons dashicons-info"></span>
						All files will be processed in batches. Click "Start Sync" below to begin.
					</p>
				</div>
			</div>
		` );

		// Update stats
		jQuery( '#aie-total-files' ).text( files.length );
		jQuery( '#aie-total-size' ).text( Utils.formatBytes( totalSize ) );
		
		// Show stats and scan results
		jQuery( '#aie-scan-results .aie-scan-stats' ).show();
		jQuery( '#aie-scan-results' ).slideDown();
	},

	/**
	 * Display scanned files (old method - keeping for compatibility)
	 */
	displayFiles( files ) {
		const $list = jQuery( '#aie-file-list' );
		$list.empty();

		if ( ! files || files.length === 0 ) {
			jQuery( '#aie-scan-results' ).hide();
			return;
		}

		let totalSize = 0;

		files.forEach( ( file ) => {
			totalSize += file.size || 0;

			const icon = this.getFileIcon( file.name );
			const $item = jQuery( `
				<div class="aie-file-item">
					<input type="checkbox" class="aie-file-checkbox" value="${ file.path }" checked>
					<div class="aie-file-icon">
						<span class="dashicons ${ icon }"></span>
					</div>
					<div class="aie-file-info">
						<div class="aie-file-name">${ this.escapeHtml( file.name ) }</div>
						<div class="aie-file-meta">
							<span>${ Utils.formatBytes( file.size ) }</span>
							<span>${ this.escapeHtml( file.path ) }</span>
						</div>
					</div>
				</div>
			` );

			$list.append( $item );
		} );

		// Update stats
		jQuery( '#aie-total-files' ).text( files.length );
		jQuery( '#aie-total-size' ).text( Utils.formatBytes( totalSize ) );
		
		// Show stats and scan results
		jQuery( '#aie-scan-results .aie-scan-stats' ).show();
		jQuery( '#aie-scan-results' ).slideDown();

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
		const count = jQuery( '.aie-file-checkbox:checked' ).length;
		jQuery( '#aie-selected-count' ).text( count );

		// Update select all checkbox
		const total = jQuery( '.aie-file-checkbox' ).length;
		jQuery( '#aie-select-all-files' ).prop( 'checked', count === total );
	},

	/**
	 * Get selected files
	 */
	getSelectedFiles() {
		const files = [];
		const self = this;
		jQuery( '.aie-file-checkbox:checked' ).each( function () {
			const path = jQuery( this ).val();
			const fileData = self.scannedFiles.find(
				( f ) => f.path === path
			);
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
		const fileOperation = jQuery( '#aie-copy-files' ).val();
		const batchSize = parseInt( jQuery( '#aie-batch-size' ).val() ) || 3;
		
		return {
			duplicate_check: jQuery( '#aie-duplicate-check' ).val(),
			duplicate_handling: jQuery( '#aie-duplicate-handling' ).val(),
			file_operation: fileOperation,
			copy_files: fileOperation === 'copy',
			generate_thumbnails: true, // Always generate thumbnails
			rml_integration: jQuery( '#aie-rml-integration' ).is( ':checked' ),
			batch_size: batchSize,
		};
	},

	/**
	 * Start media sync
	 */
	startSync() {
		if ( ! this.scannedFiles || this.scannedFiles.length === 0 ) {
			Utils.showNotice( window.aieData.i18n.noFilesToSync, 'error' );
			return;
		}

		// Clear any previous progress interval
		if ( this.progressInterval ) {
			clearInterval( this.progressInterval );
			this.progressInterval = null;
		}

		// Reset state
		this.isPaused = false;
		
		const folderPath = jQuery( '#aie-folder-path' ).val().trim();
		
		if ( ! folderPath ) {
			Utils.showNotice( window.aieData.i18n.invalidFolderPath, 'error' );
			return;
		}

		// Get scan options (for filtering on backend)
		const scanOptions = {
			recursive: jQuery( '#aie-scan-recursive' ).is( ':checked' ),
			file_types: jQuery( '#aie-file-types' ).val(),
		};
		
		if ( scanOptions.file_types === 'custom' ) {
			scanOptions.custom_types = jQuery( '#aie-custom-extensions-input' )
				.val()
				.split( ',' )
				.map( ( ext ) => ext.trim() )
				.filter( ( ext ) => ext );
		}

		const syncOptions = this.getOptions();

		// Disable button and show loading state
		const $btn = jQuery( '#aie-start-sync-btn' );
		const originalText = $btn.html();
		$btn.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update aie-spin"></span> Starting...' );

		jQuery
			.ajax( {
				url: window.aieData?.ajaxUrl || window.ajaxurl,
				method: 'POST',
				data: {
					action: 'aie_start_media_sync',
					nonce: window.aieData?.nonce || '',
					folder_path: folderPath,
					scan_options: scanOptions,
					sync_options: syncOptions,
				},
			} )
			.done( ( response ) => {
				if ( response.success ) {
					this.jobId = response.data.job_id;

					// Hide scan and options sections
					jQuery( '.aie-scan-section, .aie-options-section' ).slideUp();

					// Show progress section immediately
					jQuery( '#aie-sync-progress-section' ).slideDown();
					
					// Initialize progress at 0%
					jQuery( '#aie-progress-fill' ).css( 'width', '0%' );
					jQuery( '#aie-progress-percentage' ).text( '0%' );
					jQuery( '#aie-sync-status' ).text( 'Processing...' );

					// Start tracking progress
					this.startProgressTracking();

					// Trigger first batch processing immediately
					this.triggerBatchProcessing();

					Utils.showNotice( window.aieData.i18n.syncStarted, 'success' );
				} else {
					Utils.showNotice(
						response.data?.message || 'Failed to start sync',
						'error'
					);
					$btn.prop( 'disabled', false ).html( originalText );
				}
			} )
			.fail( () => {
				Utils.showNotice( window.aieData.i18n.requestFailed, 'error' );
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
		jQuery.ajax( {
			url: window.aieData?.ajaxUrl || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aie_process_media_sync_batch',
				nonce: window.aieData?.nonce || '',
				job_id: this.jobId,
			},
		} ).done( ( response ) => {
			
			// If not completed, process next batch after small delay
			if ( response.success && response.data && ! response.data.completed ) {
				setTimeout( () => {
					this.triggerBatchProcessing();
				}, 100 );
			}
		} ).fail( ( xhr, status, error ) => {
		} );
	},

	/**
	 * Check sync progress
	 */
	checkProgress() {
		jQuery.ajax( {
			url: window.aieData?.ajaxUrl || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aie_get_sync_progress',
				nonce: window.aieData?.nonce || '',
				job_id: this.jobId,
			},
		} ).done( ( response ) => {
			if ( response.success && response.data ) {
				this.updateProgress( response.data );
			} else {
			}
		} ).fail( ( xhr, status, error ) => {
		} );
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
		
		jQuery( '#aie-stat-processed' ).text( processed );
		jQuery( '#aie-stat-success' ).text( success );
		jQuery( '#aie-stat-skipped' ).text( skipped );
		jQuery( '#aie-stat-failed' ).text( failed );
	},

	/**
	 * Update progress UI
	 */
	updateProgress( data ) {
		
		// Parse progress as integer (remove decimals)
		const progress = Math.round( parseFloat( data.progress ) || 0 );
		const status = data.status || 'processing';
		

		// Update progress bar
		jQuery( '#aie-progress-fill' ).css( 'width', progress + '%' );
		jQuery( '#aie-progress-percentage' ).text( progress + '%' );

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
		
		
		jQuery( '#aie-stat-processed' ).text( processed );
		jQuery( '#aie-stat-success' ).text( success );
		jQuery( '#aie-stat-skipped' ).text( skipped );
		jQuery( '#aie-stat-failed' ).text( failed );
		

		// Update status text (fix selector - was #aie-progress-status, should be #aie-sync-status)
		const statusTexts = {
			pending: 'Starting...',
			processing: 'Synchronization in Progress',
			completed: 'Completed',
			failed: 'Failed',
			cancelled: 'Cancelled',
			paused: 'Paused',
		};
		
		const statusText = statusTexts[ status ] || 'Processing...';
		
		// Update both possible selectors to be safe
		jQuery( '#aie-sync-status' ).text( statusText );
		jQuery( '#aie-progress-status' ).text( statusText );

		// Show errors if any
		if ( result.errors && result.errors.length > 0 ) {
			this.displayErrors( result.errors );
		}

		// Check if completed
		if ( status === 'completed' || status === 'failed' || status === 'cancelled' ) {
			clearInterval( this.progressInterval );
			this.showCompletion( data );
		}
	},

	/**
	 * Display errors
	 */
	displayErrors( errors ) {
		const $errorLog = jQuery( '#aie-error-log' );
		const $errorList = jQuery( '#aie-error-list' );

		$errorList.empty();

		errors.slice( 0, 20 ).forEach( ( error ) => {
			$errorList.append( `<li>${ this.escapeHtml( error ) }</li>` );
		} );

		if ( errors.length > 20 ) {
			$errorList.append(
				`<li>... and ${ errors.length - 20 } more errors</li>`
			);
		}

		$errorLog.show();
	},

	/**
	 * Show completion
	 */
	showCompletion( data ) {
		// Hide progress section
		jQuery( '#aie-sync-progress-section' ).slideUp();

		// Show completion section
		jQuery( '#aie-sync-completion' ).slideDown();

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
			messageHtml = `
				<div style="text-align: center; padding: 20px;">
					<div style="font-size: 64px; margin-bottom: 15px;">🎉</div>
					<h3 style="color: #00a32a; margin: 0 0 15px; font-size: 24px;">Synchronization Complete!</h3>
					<p style="font-size: 16px; color: #1d2327; margin-bottom: 20px;">
						Successfully processed <strong>${ processed }</strong> file${ processed !== 1 ? 's' : '' }
					</p>
					<div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;">
						<div style="text-align: center;">
							<div style="font-size: 32px; color: #00a32a; font-weight: 600;">${ success }</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">✅ Imported</div>
						</div>
						${ skipped > 0 ? `
						<div style="text-align: center;">
							<div style="font-size: 32px; color: #dba617; font-weight: 600;">${ skipped }</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">⏭️ Skipped</div>
						</div>
						` : '' }
						${ failed > 0 ? `
						<div style="text-align: center;">
							<div style="font-size: 32px; color: #d63638; font-weight: 600;">${ failed }</div>
							<div style="font-size: 12px; color: #646970; text-transform: uppercase;">❌ Failed</div>
						</div>
						` : '' }
					</div>
				</div>
			`;
		} else if ( data.status === 'failed' ) {
			messageHtml = `
				<div style="text-align: center; padding: 20px;">
					<div style="font-size: 64px; margin-bottom: 15px;">⚠️</div>
					<h3 style="color: #d63638; margin: 0 0 15px; font-size: 24px;">Synchronization Failed</h3>
					<p style="font-size: 16px; color: #646970;">
						The synchronization process encountered an error and could not complete.
					</p>
				</div>
			`;
		} else if ( data.status === 'cancelled' ) {
			messageHtml = `
				<div style="text-align: center; padding: 20px;">
					<div style="font-size: 64px; margin-bottom: 15px;">🛑</div>
					<h3 style="color: #dba617; margin: 0 0 15px; font-size: 24px;">Synchronization Cancelled</h3>
					<p style="font-size: 16px; color: #646970;">
						Processed <strong>${ processed }</strong> file${ processed !== 1 ? 's' : '' } before cancellation.
					</p>
				</div>
			`;
		}

		jQuery( '#aie-completion-message' ).html( messageHtml );
	},

	/**
	 * Pause sync
	 */
	pauseSync() {
		jQuery.ajax( {
			url: window.aieData?.ajaxUrl || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aie_pause_media_sync',
				nonce: window.aieData?.nonce || '',
				job_id: this.jobId,
			},
		} ).done( ( response ) => {
			if ( response.success ) {
				this.isPaused = true;
				clearInterval( this.progressInterval );
				
				// Update UI
				const $header = jQuery( '#aie-sync-progress-section .aie-card-header h2' );
				$header.html( '<span class="dashicons dashicons-controls-pause"></span> Synchronization Paused' );
				
				// Update status text
				jQuery( '#aie-progress-status' ).text( 'Paused' );
				jQuery( '#aie-sync-status' ).text( 'Paused' );
				
				const $pauseBtn = jQuery( '#aie-pause-sync-btn' );
				$pauseBtn.html( '<span class="dashicons dashicons-controls-play"></span> Resume' );
				
				Utils.showNotice( window.aieData.i18n.syncPaused, 'info' );
			}
		} );
	},

	/**
	 * Resume sync
	 */
	resumeSync() {
		jQuery.ajax( {
			url: window.aieData?.ajaxUrl || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aie_resume_media_sync',
				nonce: window.aieData?.nonce || '',
				job_id: this.jobId,
			},
		} ).done( ( response ) => {
			if ( response.success ) {
				this.isPaused = false;
				
				// Update UI
				const $header = jQuery( '#aie-sync-progress-section .aie-card-header h2' );
				$header.html( '<span class="dashicons dashicons-update aie-spin"></span> Synchronization in Progress' );
				
				// Update status text
				jQuery( '#aie-progress-status' ).text( 'Synchronization in Progress' );
				jQuery( '#aie-sync-status' ).text( 'Synchronization in Progress' );
				
				const $pauseBtn = jQuery( '#aie-pause-sync-btn' );
				$pauseBtn.html( '<span class="dashicons dashicons-controls-pause"></span> Pause' );
				
				// Restart progress monitoring
				this.startProgressTracking();
				
				// Trigger batch processing to continue
				this.triggerBatchProcessing();
				
				Utils.showNotice( window.aieData.i18n.syncResumed, 'success' );
			}
		} );
	},

	/**
	 * Cancel sync
	 */
	cancelSync() {
		if (
			! confirm( window.aieData.i18n.confirmCancelSync )
		) {
			return;
		}

		jQuery.ajax( {
			url: window.aieData?.ajaxUrl || window.ajaxurl,
			method: 'POST',
			data: {
				action: 'aie_cancel_media_sync',
				nonce: window.aieData?.nonce || '',
				job_id: this.jobId,
			},
		} ).done( ( response ) => {
			if ( response.success ) {
				clearInterval( this.progressInterval );
				Utils.showNotice( window.aieData.i18n.syncCancelled, 'warning' );
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
			'#aie-scan-results, #aie-sync-options, #aie-sync-progress-section, #aie-sync-completion'
		).hide();

		// Show scan section
		jQuery( '.aie-scan-section' ).show();

		// Reset form
		jQuery( '#aie-folder-path' ).val( '' );
		jQuery( '#aie-file-list' ).empty();
		
		// Reset Start button
		const $startBtn = jQuery( '#aie-start-sync-btn' );
		$startBtn.prop( 'disabled', false );
		$startBtn.html( '<span class="dashicons dashicons-controls-play"></span> Start Sync' );
		
		// Reset Scan button
		const $scanBtn = jQuery( '#aie-scan-folder-btn' );
		$scanBtn.prop( 'disabled', false ).text( 'Scan Folder' );

		// Reset progress bar and stats
		jQuery( '#aie-progress-fill' ).css( 'width', '0%' );
		jQuery( '#aie-progress-percentage' ).text( '0%' );
		jQuery( '#aie-stat-processed' ).text( '0' );
		jQuery( '#aie-stat-success' ).text( '0' );
		jQuery( '#aie-stat-skipped' ).text( '0' );
		jQuery( '#aie-stat-failed' ).text( '0' );

		// Reset data
		this.jobId = null;
		this.scannedFiles = [];
		this.isPaused = false;

		if ( this.progressInterval ) {
			clearInterval( this.progressInterval );
		}
		
		// Reset pause button to default state
		const $pauseBtn = jQuery( '#aie-pause-sync-btn' );
		$pauseBtn.html( '<span class="dashicons dashicons-controls-pause"></span> Pause' );
		
		// Reset header to default state
		const $header = jQuery( '#aie-sync-progress-section .aie-card-header h2' );
		$header.html( '<span class="dashicons dashicons-update aie-spin"></span> Synchronization in Progress' );
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
		jQuery( '#aie-folder-browser-modal' ).fadeIn( 200 );
		jQuery( 'body' ).addClass( 'aie-modal-open' );
		
		// Load current folder from input or start from root
		const currentFolder = jQuery( '#aie-folder-path' ).val().trim();
		this.browseFolders( currentFolder ); // Load current or root uploads directory
	},

	/**
	 * Close folder browser modal
	 */
	closeFolderBrowser() {
		jQuery( '#aie-folder-browser-modal' ).fadeOut( 200 );
		jQuery( 'body' ).removeClass( 'aie-modal-open' );
		// Don't clear selected path - it's just a temporary selection within modal
		jQuery( '#aie-choose-folder-btn' ).prop( 'disabled', true );
		jQuery( '.aie-folder-item' ).removeClass( 'selected' );
	},

	/**
	 * Browse folders via AJAX
	 */
	browseFolders( relativePath ) {
		jQuery( '#aie-folder-browser-loading' ).show();
		jQuery( '#aie-folder-browser-list' ).empty();
		jQuery( '#aie-folder-browser-empty' ).hide();
		jQuery( '#aie-folder-browser-error' ).hide();
		jQuery( '#aie-selected-folder-path' ).val( '' );
		jQuery( '#aie-choose-folder-btn' ).prop( 'disabled', true );

		jQuery
			.ajax( {
				url: window.ajaxurl || window.aieData?.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_browse_folders',
					nonce: window.aieData?.nonce,
					path: relativePath,
				},
			} )
			.done( ( response ) => {
				if ( response.success && response.data ) {
					this.displayFolders( response.data.folders, response.data.current_path );
				} else {
					
					this.showBrowserError(
						response.data?.message || 'Failed to load folders'
					);
				}
			} )
			.fail( ( jqXHR, textStatus, errorThrown ) => {

				if ( jqXHR.responseText ) {
				}

				let errorMsg = window.aieData.i18n.requestFailed;
				
				// Check for WP_Error response
				if ( jqXHR.responseJSON ) {
					if ( jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ) {
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
						const firstLine = jqXHR.responseText.split( '\n' )[ 0 ].substring( 0, 100 );
						if ( firstLine ) {
							errorMsg = 'Server Error: ' + firstLine;
						}
					}
				}
				
				if ( errorThrown && errorMsg === window.aieData.i18n.requestFailed ) {
					errorMsg = 'Request failed: ' + errorThrown;
				}

				// Add status code to message
				if ( jqXHR.status && jqXHR.status !== 200 ) {
					errorMsg += ' (Status: ' + jqXHR.status + ')';
				}
				
				this.showBrowserError( errorMsg );
			} )
			.always( () => {
				jQuery( '#aie-folder-browser-loading' ).hide();
			} );
	},

	/**
	 * Show error in folder browser
	 */
	showBrowserError( message ) {
		jQuery( '#aie-folder-browser-error-message' ).text( message );
		jQuery( '#aie-folder-browser-error' ).slideDown();
	},

	/**
	 * Display folders in browser
	 */
	displayFolders( folders, currentPath ) {
		const $list = jQuery( '#aie-folder-browser-list' );
		const $currentPath = jQuery( '#aie-current-path' );
		const $upBtn = jQuery( '#aie-folder-up-btn' );

		$list.empty();

		// Store base uploads directory path once
		if ( ! $currentPath.data( 'base-dir' ) ) {
			const basePath = $currentPath.text().trim();
			$currentPath.data( 'base-dir', basePath );
		}

		const baseDir = $currentPath.data( 'base-dir' );
		
		// Update current path display
		if ( currentPath ) {
			$currentPath.text( baseDir + '/' + currentPath );
		} else {
			$currentPath.text( baseDir );
		}
		
		// Store current relative path for navigation
		$currentPath.data( 'relative-path', currentPath );

		// Show/hide up button
		if ( currentPath ) {
			if ( ! $upBtn.length ) {
				const $upButton = jQuery( `
					<button type="button" id="aie-folder-up-btn" class="button" style="margin-bottom: 10px;">
						<span class="dashicons dashicons-arrow-up-alt"></span>
						Go Up
					</button>
				` );
				$upButton.insertBefore( $list );
			}
		} else {
			$upBtn.remove();
		}

		// Add "Use this folder" option
		const $rootOption = jQuery( `
			<div class="aie-folder-item aie-folder-current" data-path="${ this.escapeHtml( currentPath ) }">
				<span class="dashicons dashicons-location"></span>
				<span class="aie-folder-name">
					<strong>. (Use this folder)</strong>
				</span>
			</div>
		` );
		$list.append( $rootOption );

		if ( ! folders || folders.length === 0 ) {
			jQuery( '#aie-folder-browser-empty' ).show();
			return;
		}

		// Display folders
		folders.forEach( ( folder ) => {
			const $item = jQuery( `
				<div class="aie-folder-item" data-path="${ this.escapeHtml( folder.path ) }">
					<span class="dashicons dashicons-category"></span>
					<span class="aie-folder-name">${ this.escapeHtml( folder.name ) }</span>
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</div>
			` );
			$list.append( $item );
		} );
	},

};

export default MediaSyncModule;
