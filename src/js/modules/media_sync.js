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
			this.pauseSync();
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
		let folderPath = jQuery( '#aie-folder-path' ).val().trim();

		if ( ! folderPath ) {
			Utils.showNotice( 'Please enter a folder path', 'error' );
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
				url: aieData.ajaxUrl,
				method: 'POST',
				data: {
					action: 'aie_scan_folder',
					nonce: aieData.nonce,
					folder_path: folderPath,
					options: options,
				},
			} )
			.done( ( response ) => {
				if ( response.success ) {
					this.scannedFiles = response.data.files || [];
					this.displayFiles( this.scannedFiles );
					Utils.showNotice(
						`Found ${ this.scannedFiles.length } files`,
						'success'
					);

					// Show sync options
					jQuery( '#aie-sync-options' ).slideDown();
				} else {
					Utils.showNotice(
						response.data?.message || 'Scan failed',
						'error'
					);
				}
			} )
			.fail( () => {
				Utils.showNotice( 'Request failed', 'error' );
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
	 * Display scanned files
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
		jQuery( '.aie-file-checkbox:checked' ).each( function () {
			const path = jQuery( this ).val();
			const fileData = MediaSyncModule.scannedFiles.find(
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
		return {
			duplicate_check: jQuery( '#aie-duplicate-check' ).val(),
			duplicate_handling: jQuery( '#aie-duplicate-handling' ).val(),
			copy_files: jQuery( '#aie-copy-files' ).val() === 'copy',
			preserve_structure: jQuery( '#aie-preserve-structure' ).is(
				':checked'
			),
			generate_thumbnails: jQuery( '#aie-generate-thumbnails' ).is(
				':checked'
			),
			post_id: parseInt( jQuery( '#aie-assign-to-post' ).val() ) || 0,
		};
	},

	/**
	 * Start media sync
	 */
	startSync() {
		const files = this.getSelectedFiles();

		if ( files.length === 0 ) {
			Utils.showNotice( 'Please select at least one file', 'error' );
			return;
		}

		const options = this.getOptions();

		jQuery( '#aie-start-sync-btn' ).prop( 'disabled', true );

		jQuery
			.ajax( {
				url: aieData.ajaxUrl,
				method: 'POST',
				data: {
					action: 'aie_start_media_sync',
					nonce: aieData.nonce,
					files: files,
					options: options,
				},
			} )
			.done( ( response ) => {
				if ( response.success ) {
					this.jobId = response.data.job_id;

					// Hide scan and options sections
					jQuery( '.aie-scan-section, .aie-options-section' ).slideUp();

					// Show progress section
					jQuery( '#aie-sync-progress-section' ).slideDown();

					// Start tracking progress
					this.startProgressTracking();

					Utils.showNotice( 'Synchronization started', 'success' );
				} else {
					Utils.showNotice(
						response.data?.message || 'Failed to start sync',
						'error'
					);
					jQuery( '#aie-start-sync-btn' ).prop( 'disabled', false );
				}
			} )
			.fail( () => {
				Utils.showNotice( 'Request failed', 'error' );
				jQuery( '#aie-start-sync-btn' ).prop( 'disabled', false );
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
	 * Check sync progress
	 */
	checkProgress() {
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_sync_progress',
				nonce: aieData.nonce,
				job_id: this.jobId,
			},
		} ).done( ( response ) => {
			if ( response.success ) {
				this.updateProgress( response.data );
			}
		} );
	},

	/**
	 * Update progress UI
	 */
	updateProgress( data ) {
		const progress = data.progress || 0;
		const status = data.status || 'processing';

		// Update progress bar
		jQuery( '#aie-progress-fill' ).css( 'width', progress + '%' );
		jQuery( '#aie-progress-percentage' ).text( progress + '%' );

		// Update stats
		const result = data.result || {};
		jQuery( '#aie-stat-processed' ).text( result.processed || 0 );
		jQuery( '#aie-stat-success' ).text( result.success || 0 );
		jQuery( '#aie-stat-skipped' ).text( result.skipped || 0 );
		jQuery( '#aie-stat-failed' ).text( result.failed || 0 );

		// Update status text
		const statusTexts = {
			pending: 'Waiting to start...',
			processing: 'Processing files...',
			completed: 'Completed',
			failed: 'Failed',
			cancelled: 'Cancelled',
		};
		jQuery( '#aie-progress-status' ).text( statusTexts[ status ] );

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

		const result = data.result || {};
		const message = `
			Successfully processed ${ result.processed || 0 } files:
			<strong>${ result.success || 0 }</strong> imported,
			<strong>${ result.skipped || 0 }</strong> skipped,
			<strong>${ result.failed || 0 }</strong> failed.
		`;

		jQuery( '#aie-completion-message' ).html( message );
	},

	/**
	 * Pause sync
	 */
	pauseSync() {
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_pause_media_sync',
				nonce: aieData.nonce,
				job_id: this.jobId,
			},
		} ).done( ( response ) => {
			if ( response.success ) {
				clearInterval( this.progressInterval );
				Utils.showNotice( 'Sync paused', 'info' );
			}
		} );
	},

	/**
	 * Cancel sync
	 */
	cancelSync() {
		if (
			! confirm(
				'Are you sure you want to cancel? This cannot be undone.'
			)
		) {
			return;
		}

		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_cancel_media_sync',
				nonce: aieData.nonce,
				job_id: this.jobId,
			},
		} ).done( ( response ) => {
			if ( response.success ) {
				clearInterval( this.progressInterval );
				Utils.showNotice( 'Sync cancelled', 'warning' );
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
		jQuery( '#aie-start-sync-btn' ).prop( 'disabled', false );

		// Reset data
		this.jobId = null;
		this.scannedFiles = [];

		if ( this.progressInterval ) {
			clearInterval( this.progressInterval );
		}
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
		this.browseFolders( '' ); // Load root uploads directory
	},

	/**
	 * Close folder browser modal
	 */
	closeFolderBrowser() {
		jQuery( '#aie-folder-browser-modal' ).fadeOut( 200 );
		jQuery( 'body' ).removeClass( 'aie-modal-open' );
		jQuery( '#aie-selected-folder-path' ).val( '' );
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
					// Debug output
					console.error( 'Browse folders failed:', response );
					
					this.showBrowserError(
						response.data?.message || 'Failed to load folders'
					);
				}
			} )
			.fail( ( jqXHR, textStatus, errorThrown ) => {
				// Debug output
				console.error( 'AJAX request failed:', {
					status: jqXHR.status,
					statusText: jqXHR.statusText,
					responseJSON: jqXHR.responseJSON,
					responseText: jqXHR.responseText,
					textStatus: textStatus,
					errorThrown: errorThrown,
				} );

				// Log the full response text for debugging
				if ( jqXHR.responseText ) {
					console.log( 'Full response text:', jqXHR.responseText );
				}

				let errorMsg = 'Request failed';
				
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
				
				if ( errorThrown && errorMsg === 'Request failed' ) {
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

		// Update current path display
		const uploadDir = $currentPath.text().split( '/' );
		const baseDir = uploadDir.slice( 0, -1 ).join( '/' ) || uploadDir[ 0 ];
		
		if ( currentPath ) {
			$currentPath.text( baseDir + '/' + currentPath );
		} else {
			$currentPath.text( baseDir );
		}
		
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
