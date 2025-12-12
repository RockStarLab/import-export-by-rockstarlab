/**
 * Import Module
 *
 * Handles the import wizard functionality
 */

import Utils from './utils';
import FileUploader from './FileUploader';

const ImportModule = {
	// Current wizard state
	currentStep: 1,
	totalSteps: 6,
	uploadedFile: null,
	fileData: null,
	jobId: null,
	progressInterval: null,
	fileUploader: null,

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#wp-aie-import' ).length ) {
			return;
		}

		this.bindEvents();
		this.showStep( 1 );
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#wp-aie-import' );

		// Step navigation
		$wizard.on( 'click', '.aie-next-step', () => this.nextStep() );
		$wizard.on( 'click', '.aie-prev-step', () => this.prevStep() );

		// Content type selection
		$wizard.on( 'change', 'input[name="content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

		// File upload
		jQuery( '#aie-select-file' ).on( 'click', () =>
			jQuery( '#aie-file-input' ).click()
		);
		jQuery( '#aie-file-input' ).on( 'change', ( e ) =>
			this.onFileSelect( e )
		);
		jQuery( '.aie-remove-file' ).on( 'click', () => this.removeFile() );

		// Drag & drop
		const $dropZone = jQuery( '#aie-upload-area' );
		$dropZone
			.on( 'dragover', ( e ) => {
				e.preventDefault();
				$dropZone.addClass( 'aie-dragover' );
			} )
			.on( 'dragleave', () => {
				$dropZone.removeClass( 'aie-dragover' );
			} )
			.on( 'drop', ( e ) => {
				e.preventDefault();
				$dropZone.removeClass( 'aie-dragover' );
				const files = e.originalEvent.dataTransfer.files;
				if ( files.length > 0 ) {
					this.handleFile( files[ 0 ] );
				}
			} );

		// Field mapping
		$wizard.on( 'click', '.aie-auto-map', () => this.autoMapFields() );
		$wizard.on( 'click', '.aie-clear-map', () => this.clearFieldMapping() );

		// Import actions
		$wizard.on( 'click', '.aie-start-import', () => this.startImport() );
		$wizard.on( 'click', '.aie-cancel-import', () => this.cancelImport() );
		$wizard.on( 'click', '.aie-new-import', () => this.resetWizard() );
		$wizard.on( 'click', '.aie-toggle-logs', () => this.toggleLogs() );
	},

	/**
	 * Show specific step
	 */
	showStep( step ) {
		const $wizard = jQuery( '#wp-aie-import' );

		// Hide all steps
		$wizard.find( '.aie-step' ).removeClass( 'active' );

		// Show current step
		$wizard.find( `.aie-step-${ step }` ).addClass( 'active' );

		// Update indicators
		$wizard.find( '.aie-step-indicator' ).removeClass( 'active completed' );
		$wizard
			.find( `.aie-step-indicator[data-step="${ step }"]` )
			.addClass( 'active' );
		$wizard
			.find( `.aie-step-indicator[data-step]` )
			.filter( function () {
				return jQuery( this ).data( 'step' ) < step;
			} )
			.addClass( 'completed' );

		this.currentStep = step;

		// Step-specific actions
		if ( step === 3 ) {
			this.loadPreview();
			
			// Check if there's an error from upload
			if ( this.fileData && this.fileData.hasError ) {
				// Show error message
				jQuery( '.aie-preview-table-container' ).hide();
				jQuery( '.aie-json-preview-container' ).hide();
				
				// Show error in preview area
				const errorHtml = `
					<div class="notice notice-error" style="padding: 20px; margin: 20px 0;">
						<h3 style="margin-top: 0;">❌ File Validation Failed</h3>
						<p style="font-size: 14px;">${ Utils.escapeHtml( this.fileData.error ) }</p>
						<p style="margin-bottom: 0;">
							<button type="button" class="button aie-prev-step">
								← Go Back and Upload a Valid File
							</button>
						</p>
					</div>
				`;
				jQuery( '.aie-step-3 .aie-step-content' ).prepend( errorHtml );
				
				// Disable next button
				jQuery( '.aie-step-3 .aie-next-step' ).prop( 'disabled', true );
			} else {
				// Enable next button if no error
				jQuery( '.aie-step-3 .aie-next-step' ).prop( 'disabled', false );
			}
		} else if ( step === 4 ) {
			this.buildFieldMapping();
		}
	},

	/**
	 * Go to next step
	 */
	nextStep() {
		if ( this.currentStep < this.totalSteps ) {
			// Validate current step
			if ( this.validateStep( this.currentStep ) ) {
				this.showStep( this.currentStep + 1 );
			}
		}
	},

	/**
	 * Go to previous step
	 */
	prevStep() {
		if ( this.currentStep > 1 ) {
			this.showStep( this.currentStep - 1 );
		}
	},

	/**
	 * Validate step
	 */
	validateStep( step ) {
		switch ( step ) {
			case 2:
				if ( ! this.uploadedFile ) {
					Utils.showNotice( 'Please upload a file', 'error' );
					return false;
				}
				break;
			case 4:
				// Validate field mapping
				const mappedFields = this.getFieldMapping();
				if ( Object.keys( mappedFields ).length === 0 ) {
					Utils.showNotice(
						'Please map at least one field',
						'error'
					);
					return false;
				}
				break;
		}
		return true;
	},

	/**
	 * Handle content type change
	 */
	onContentTypeChange( e ) {
		const contentType = jQuery( e.target ).val();

		// Show/hide content-specific options
		if ( contentType === 'media' ) {
			jQuery( '.aie-post-options' ).hide();
			jQuery( '.aie-media-options' ).show();
		} else {
			jQuery( '.aie-post-options' ).show();
			jQuery( '.aie-media-options' ).hide();
		}
	},

	/**
	 * Handle file selection
	 */
	onFileSelect( e ) {
		const file = e.target.files[ 0 ];
		if ( file ) {
			this.handleFile( file );
		}
	},

	/**
	 * Handle file upload
	 */
	handleFile( file ) {
		// Validate file extension only (no size limit with chunked upload)
		const allowedExtensions = [ '.csv', '.json' ];
		const fileExt = '.' + file.name.split( '.' ).pop().toLowerCase();
		
		if ( ! allowedExtensions.includes( fileExt ) ) {
			Utils.showNotice(
				'Invalid file type. Please upload CSV or JSON files only.',
				'error'
			);
			return;
		}

		this.uploadedFile = file;

		// Show file info
		jQuery( '.aie-upload-placeholder' ).hide();
		jQuery( '.aie-file-info' ).show();
		jQuery( '.aie-file-name' ).text( file.name );
		jQuery( '.aie-file-size' ).text( Utils.formatFileSize( file.size ) );

		// Detect format
		const format = this.detectFormat( file.name );
		jQuery( '.aie-file-format' ).text( format.toUpperCase() );

		// Show format options
		if ( format === 'csv' ) {
			jQuery( '.aie-format-options' ).show();
			jQuery( '.aie-csv-options' ).show();
		}

		// Start chunked upload
		this.uploadFileInChunks( file );
	},

	/**
	 * Upload file in chunks
	 */
	uploadFileInChunks( file ) {
		// Show upload progress
		jQuery( '.aie-upload-placeholder' ).hide();
		jQuery( '.aie-file-info' ).hide();
		jQuery( '.aie-upload-progress' ).show();

		// Create uploader instance
		this.fileUploader = new FileUploader( {
			chunkSize: 1024 * 1024, // 1MB chunks
			onProgress: ( progress ) => {
				// Update progress bar
				jQuery( '.aie-upload-progress .aie-progress-bar-fill' ).css(
					'width',
					progress.progress + '%'
				);
				jQuery( '.aie-upload-percentage' ).text(
					Math.round( progress.progress ) + '%'
				);
				jQuery( '.aie-upload-speed' ).text(
					FileUploader.formatSpeed( progress.speed )
				);
			},
			onComplete: ( result ) => {
				console.log( 'Upload complete, result:', result );
				
				// Check for validation errors
				if ( result.error ) {
					console.log( 'Validation error detected:', result.error );
					Utils.showNotice( result.error, 'error' );
					
					// Show file info but keep upload area visible
					jQuery( '.aie-upload-progress' ).hide();
					jQuery( '.aie-upload-placeholder' ).show();
					
					// Store error in fileData to show on step 3
					this.fileData = {
						error: result.error,
						hasError: true
					};
					
					// Disable next button due to validation error
					jQuery( '.aie-step-2 .aie-next-step' ).prop(
						'disabled',
						true
					);
					
					return;
				}

				// Upload complete
				this.fileData = result;
				console.log( 'File data stored:', this.fileData );
				
				// Hide upload area completely, show file info
				jQuery( '.aie-upload-area' ).hide();
				jQuery( '.aie-file-info' ).show();

				// Enable next button
				jQuery( '.aie-step-2 .aie-next-step' ).prop(
					'disabled',
					false
				);

				// Show success message
				Utils.showNotice( 'File uploaded successfully', 'success' );

				// Show warning if present
				if ( result.warning ) {
					Utils.showNotice( result.warning, 'warning' );
				}
			},
			onError: ( error ) => {
				// Upload failed
				Utils.showNotice(
					'Upload failed: ' + error.message,
					'error'
				);
				this.removeFile();
			},
		} );

		// Start upload
		this.fileUploader.upload( file );
	},

	/**
	 * Remove uploaded file
	 */
	removeFile() {
		// Abort upload if in progress
		if ( this.fileUploader ) {
			this.fileUploader.abort();
			this.fileUploader = null;
		}

		this.uploadedFile = null;
		this.fileData = null;

		jQuery( '.aie-file-info' ).hide();
		jQuery( '.aie-upload-area' ).show();
		jQuery( '.aie-format-options' ).hide();
		jQuery( '#aie-file-input' ).val( '' );
		jQuery( '.aie-step-2 .aie-next-step' ).prop( 'disabled', true );
	},

	/**
	 * Detect file format from filename
	 */
	detectFormat( filename ) {
		const ext = filename.split( '.' ).pop().toLowerCase();
		return [ 'csv', 'json' ].includes( ext ) ? ext : 'csv';
	},

	/**
	 * Load data preview
	 */
	async loadPreview() {
		console.log( 'loadPreview called, fileData:', this.fileData );
		
		if ( ! this.fileData ) {
			Utils.showNotice( 'No file data available', 'error' );
			return;
		}

		// Check if there's an error
		if ( this.fileData.hasError ) {
			console.log( 'File has validation error:', this.fileData.error );
			return; // Error display is handled in showStep
		}

		if ( ! this.fileData.preview ) {
			Utils.showNotice( 'No preview data available', 'error' );
			return;
		}

		const preview = this.fileData.preview;
		const format = this.fileData.format || 'csv';

		console.log( 'Preview data:', preview );
		console.log( 'Format:', format );

		// Update stats
		jQuery( '.aie-total-rows' ).text( this.fileData.total_rows || 0 );
		jQuery( '.aie-total-columns' ).text(
			this.fileData.columns?.length || 0
		);

		// Always show table preview (both CSV and JSON)
		this.showTablePreview( preview );
	},

	/**
	 * Show JSON preview (expanded first object)
	 */
	showJsonPreview( firstObject ) {
		// Hide table, show JSON preview
		jQuery( '.aie-preview-table-container' ).hide();
		jQuery( '.aie-json-preview-container' ).show();
		jQuery( '.aie-preview-note' ).text(
			'Showing first object from JSON file'
		);

		// Build JSON preview HTML
		let html = '<div class="aie-json-object">';
		html += '<table class="wp-list-table widefat striped">';
		html += '<thead><tr>';
		html += '<th style="width: 30%;">Field</th>';
		html += '<th style="width: 20%;">Type</th>';
		html += '<th style="width: 50%;">Value</th>';
		html += '</tr></thead><tbody>';

		// Iterate through all fields
		for ( const [ key, value ] of Object.entries( firstObject ) ) {
			const type = this.getValueType( value );
			const displayValue = this.formatJsonValue( value );

			html += '<tr>';
			html += `<td><strong>${ Utils.escapeHtml( key ) }</strong></td>`;
			html += `<td><code>${ type }</code></td>`;
			html += `<td>${ displayValue }</td>`;
			html += '</tr>';
		}

		html += '</tbody></table></div>';

		jQuery( '.aie-json-preview' ).html( html );
	},

	/**
	 * Show table preview for CSV
	 */
	showTablePreview( preview ) {
		// Show table, hide JSON preview
		jQuery( '.aie-preview-table-container' ).show();
		jQuery( '.aie-json-preview-container' ).hide();
		jQuery( '.aie-preview-note' ).text( 'Showing first 5 rows' );

		const $table = jQuery( '.aie-preview-table' );

		// Build table header
		let headerHtml = '<tr>';
		if ( preview.headers ) {
			preview.headers.forEach( ( header ) => {
				headerHtml += `<th>${ Utils.escapeHtml( header ) }</th>`;
			} );
		}
		headerHtml += '</tr>';
		$table.find( 'thead' ).html( headerHtml );

		// Build table body
		let bodyHtml = '';
		if ( preview.data ) {
			preview.data.forEach( ( row, index ) => {
				bodyHtml += '<tr>';
				row.forEach( ( cell ) => {
					// Limit cell content length for preview
					let cellContent = String( cell );
					
					// If it looks like JSON, format it nicely
					if ( cellContent.startsWith( '{' ) || cellContent.startsWith( '[' ) ) {
						cellContent = cellContent.substring( 0, 150 );
						if ( String( cell ).length > 150 ) {
							cellContent += '...';
						}
					} else {
						cellContent = cellContent.substring( 0, 100 );
						if ( String( cell ).length > 100 ) {
							cellContent += '...';
						}
					}
					
					bodyHtml += `<td>${ Utils.escapeHtml( cellContent ) }</td>`;
				} );
				bodyHtml += '</tr>';
			} );
		}
		$table.find( 'tbody' ).html( bodyHtml );

		// Check if table has horizontal scroll
		this.checkTableScroll();
	},

	/**
	 * Check if table container has horizontal scroll and add indicator
	 */
	checkTableScroll() {
		const $container = jQuery( '.aie-preview-table-container' );
		
		if ( $container.length ) {
			// Use setTimeout to ensure DOM is fully rendered
			setTimeout( () => {
				const container = $container[ 0 ];
				const hasScroll = container.scrollWidth > container.clientWidth;
				
				$container.toggleClass( 'has-scroll', hasScroll );

				// Add scroll event listener to hide shadow when scrolled to end
				$container.off( 'scroll.preview' ).on( 'scroll.preview', function () {
					const scrollLeft = jQuery( this ).scrollLeft();
					const scrollWidth = this.scrollWidth;
					const clientWidth = this.clientWidth;
					const isAtEnd = scrollLeft + clientWidth >= scrollWidth - 5;
					
					jQuery( this ).toggleClass( 'scrolled-to-end', isAtEnd );
				} );
			}, 100 );
		}
	},

	/**
	 * Get value type for display
	 */
	getValueType( value ) {
		if ( value === null ) return 'null';
		if ( Array.isArray( value ) ) return 'array';
		if ( typeof value === 'object' ) return 'object';
		if ( typeof value === 'number' ) return 'number';
		if ( typeof value === 'boolean' ) return 'boolean';
		return 'string';
	},

	/**
	 * Format JSON value for display
	 */
	formatJsonValue( value ) {
		if ( value === null ) {
			return '<em style="color: #999;">null</em>';
		}

		if ( Array.isArray( value ) ) {
			if ( value.length === 0 ) {
				return '<code>[]</code>';
			}
			const preview = value
				.slice( 0, 3 )
				.map( ( v ) => JSON.stringify( v ) )
				.join( ', ' );
			const more = value.length > 3 ? ` ... +${ value.length - 3 }` : '';
			return `<code>[ ${ Utils.escapeHtml( preview ) }${ more } ]</code>`;
		}

		if ( typeof value === 'object' ) {
			const keys = Object.keys( value );
			if ( keys.length === 0 ) {
				return '<code>{}</code>';
			}
			const preview = keys.slice( 0, 2 ).join( ', ' );
			const more = keys.length > 2 ? ` ... +${ keys.length - 2 }` : '';
			return `<code>{ ${ Utils.escapeHtml( preview ) }${ more } }</code>`;
		}

		if ( typeof value === 'boolean' ) {
			return `<code style="color: #0073aa;">${ value }</code>`;
		}

		if ( typeof value === 'number' ) {
			return `<code style="color: #d63638;">${ value }</code>`;
		}

		// String
		const strValue = String( value );
		const displayValue =
			strValue.length > 200
				? strValue.substring( 0, 200 ) + '...'
				: strValue;
		return Utils.escapeHtml( displayValue );
	},

	/**
	 * Build field mapping interface (Drag & Drop)
	 */
	buildFieldMapping() {
		console.log( 'buildFieldMapping called' );
		
		if ( ! this.fileData || ! this.fileData.columns ) {
			console.log( 'No file data or columns' );
			return;
		}

		const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
		console.log( 'Content type:', contentType );
		
		// Build source fields (from file)
		this.buildSourceFields();
		
		// Build target fields (WordPress fields)
		this.buildTargetFields( contentType );
		
		// Initialize drag & drop
		this.initializeDragDrop();
		
		// Initialize search
		this.initializeFieldSearch();
		
		// Update stats
		this.updateMappingStats();
	},

	/**
	 * Build source fields from uploaded file
	 */
	buildSourceFields() {
		const $container = jQuery( '#aie-source-fields' );
		let html = '';

		this.fileData.columns.forEach( ( column, index ) => {
			const sampleData = this.fileData.preview?.data?.[ 0 ]?.[ index ] || '';
			const sampleDisplay = String( sampleData ).substring( 0, 30 );

			html += `
				<div class="aie-field-card" draggable="true" data-source-field="${ Utils.escapeHtml( column ) }" data-source-index="${ index }">
					<div class="aie-field-icon">
						<span class="dashicons dashicons-media-spreadsheet"></span>
					</div>
					<div class="aie-field-info">
						<div class="aie-field-name">${ Utils.escapeHtml( column ) }</div>
						${ sampleDisplay ? `<div class="aie-field-sample">${ Utils.escapeHtml( sampleDisplay ) }...</div>` : '' }
					</div>
				</div>
			`;
		} );

		$container.html( html );
	},

	/**
	 * Build target WordPress fields
	 */
	buildTargetFields( contentType ) {
		const $container = jQuery( '#aie-target-fields' );
		
		// Get fields for content type
		const fieldGroups = this.getFieldsByContentType( contentType );
		console.log( 'Field groups:', fieldGroups );
		
		let html = '';

		fieldGroups.forEach( ( group ) => {
			html += `<div class="aie-field-group">`;
			html += `<div class="aie-field-group-label">${ group.label }</div>`;
			
			group.options.forEach( ( field ) => {
				// Skip special fields
				if ( field.value.startsWith( '_' ) ) {
					return;
				}

				html += `
					<div class="aie-target-field" data-target-field="${ field.value }" data-field-type="${ field.type || 'string' }">
						<div class="aie-field-icon">
							<span class="dashicons dashicons-wordpress"></span>
						</div>
						<div class="aie-field-info">
							<div class="aie-field-label">${ field.label }</div>
							<span class="aie-field-type-badge">${ field.type || 'string' }</span>
						</div>
					</div>
				`;
			} );
			
			html += `</div>`;
		} );

		$container.html( html );
	},

	/**
	 * Get fields by content type (import-compatible fields)
	 */
	getFieldsByContentType( contentType ) {
		// Posts
		if ( contentType === 'post' || contentType === 'page' ) {
			return [
				{
					label: 'Standard',
					options: [
						{ value: 'post_title', label: 'Title', type: 'string' },
						{ value: 'post_content', label: 'Content', type: 'string' },
						{ value: 'post_excerpt', label: 'Excerpt', type: 'string' },
						{ value: 'post_status', label: 'Status', type: 'string' },
						{ value: 'post_date', label: 'Date', type: 'date' },
						{ value: 'post_name', label: 'Slug', type: 'string' },
					],
				},
				{
					label: 'Author',
					options: [
						{ value: 'post_author', label: 'Author ID', type: 'number' },
						{ value: 'author_email', label: 'Author Email', type: 'string' },
					],
				},
				{
					label: 'Taxonomy',
					options: [
						{ value: 'categories', label: 'Categories', type: 'array' },
						{ value: 'tags', label: 'Tags', type: 'array' },
					],
				},
				{
					label: 'Media',
					options: [
						{ value: 'featured_image', label: 'Featured Image', type: 'string' },
					],
				},
			];
		}

		// Users
		if ( contentType === 'user' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'user_login', label: 'Username', type: 'string' },
						{ value: 'user_email', label: 'Email', type: 'string' },
						{ value: 'display_name', label: 'Display Name', type: 'string' },
						{ value: 'user_nicename', label: 'Nice Name', type: 'string' },
					],
				},
				{
					label: 'Profile',
					options: [
						{ value: 'first_name', label: 'First Name', type: 'string' },
						{ value: 'last_name', label: 'Last Name', type: 'string' },
						{ value: 'nickname', label: 'Nickname', type: 'string' },
						{ value: 'description', label: 'Bio', type: 'string' },
						{ value: 'user_url', label: 'Website', type: 'string' },
					],
				},
				{
					label: 'Role & Permissions',
					options: [
						{ value: 'role', label: 'Role', type: 'string' },
					],
				},
			];
		}

		// Media
		if ( contentType === 'media' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'post_title', label: 'Title', type: 'string' },
						{ value: 'post_content', label: 'Description', type: 'string' },
						{ value: 'post_excerpt', label: 'Caption', type: 'string' },
						{ value: 'alt_text', label: 'Alt Text', type: 'string' },
					],
				},
				{
					label: 'File',
					options: [
						{ value: 'file_url', label: 'File URL', type: 'string' },
						{ value: 'file_path', label: 'File Path', type: 'string' },
					],
				},
			];
		}

		// WooCommerce Products
		if ( contentType === 'woo_product' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'post_title', label: 'Product Name', type: 'string' },
						{ value: 'post_content', label: 'Description', type: 'string' },
						{ value: 'post_excerpt', label: 'Short Description', type: 'string' },
						{ value: 'sku', label: 'SKU', type: 'string' },
					],
				},
				{
					label: 'Pricing',
					options: [
						{ value: 'regular_price', label: 'Regular Price', type: 'number' },
						{ value: 'sale_price', label: 'Sale Price', type: 'number' },
					],
				},
				{
					label: 'Inventory',
					options: [
						{ value: 'stock_quantity', label: 'Stock Quantity', type: 'number' },
						{ value: 'stock_status', label: 'Stock Status', type: 'string' },
					],
				},
			];
		}

		// Default - return post fields
		return [
			{
				label: 'Standard',
				options: [
					{ value: 'post_title', label: 'Title', type: 'string' },
					{ value: 'post_content', label: 'Content', type: 'string' },
					{ value: 'post_status', label: 'Status', type: 'string' },
				],
			},
		];
	},

	/**
	 * Initialize drag & drop functionality
	 */
	initializeDragDrop() {
		const self = this;
		let draggedElement = null;

		// Drag start on source fields
		jQuery( document ).on( 'dragstart', '.aie-field-card', function ( e ) {
			if ( jQuery( this ).hasClass( 'mapped' ) ) {
				e.preventDefault();
				return;
			}

			draggedElement = jQuery( this );
			jQuery( this ).addClass( 'dragging' );
			
			e.originalEvent.dataTransfer.effectAllowed = 'move';
			e.originalEvent.dataTransfer.setData( 'text/html', this.innerHTML );
		} );

		// Drag end
		jQuery( document ).on( 'dragend', '.aie-field-card', function () {
			jQuery( this ).removeClass( 'dragging' );
			jQuery( '.aie-target-field' ).removeClass( 'drag-over' );
		} );

		// Drag over target fields
		jQuery( document ).on( 'dragover', '.aie-target-field', function ( e ) {
			e.preventDefault();
			jQuery( this ).addClass( 'drag-over' );
		} );

		// Drag leave
		jQuery( document ).on( 'dragleave', '.aie-target-field', function () {
			jQuery( this ).removeClass( 'drag-over' );
		} );

		// Drop on target field
		jQuery( document ).on( 'drop', '.aie-target-field', function ( e ) {
			e.preventDefault();
			jQuery( this ).removeClass( 'drag-over' );

			if ( ! draggedElement ) {
				return;
			}

			const sourceField = draggedElement.data( 'source-field' );
			const sourceIndex = draggedElement.data( 'source-index' );
			const targetField = jQuery( this ).data( 'target-field' );
			const fieldType = jQuery( this ).data( 'field-type' );

			// Create mapping
			self.createMapping( sourceField, sourceIndex, targetField, fieldType, jQuery( this ) );

			// Mark source as mapped
			draggedElement.addClass( 'mapped' );
			draggedElement = null;

			// Update stats
			self.updateMappingStats();
		} );

		// Remove mapping (from target field)
		jQuery( document ).on( 'click', '.aie-remove-mapping', function ( e ) {
			e.stopPropagation();
			const $targetField = jQuery( this ).closest( '.aie-target-field' );
			const sourceIndex = $targetField.data( 'mapped-source-index' );

			self.removeMapping( sourceIndex, $targetField );
		} );

		// Remove mapping (from mapped fields section)
		jQuery( document ).on( 'click', '.aie-remove-row-mapping', function ( e ) {
			e.stopPropagation();
			const sourceIndex = jQuery( this ).data( 'source-index' );
			const targetField = jQuery( this ).closest( '.aie-mapping-row' ).data( 'target-field' );
			const $targetField = jQuery( `.aie-target-field[data-target-field="${ targetField }"]` );

			self.removeMapping( sourceIndex, $targetField );
		} );
	},

	/**
	 * Remove mapping
	 */
	removeMapping( sourceIndex, $targetField ) {
		// Remove mapping from target
		$targetField.find( '.aie-mapped-source' ).remove();
		$targetField.removeClass( 'has-mapping' );
		$targetField.removeData( 'mapped-source-index' );
		$targetField.removeData( 'mapped-source-field' );

		// Unmark source
		jQuery( `.aie-field-card[data-source-index="${ sourceIndex }"]` ).removeClass( 'mapped' );

		// Remove from mapped fields section
		jQuery( `.aie-mapping-row[data-source-index="${ sourceIndex }"]` ).remove();

		// Show empty state if no mappings
		if ( jQuery( '.aie-mapping-row' ).length === 0 ) {
			jQuery( '.aie-mapped-fields .aie-empty-state' ).show();
		}

		// Update stats
		this.updateMappingStats();
	},

	/**
	 * Create field mapping
	 */
	createMapping( sourceField, sourceIndex, targetField, fieldType, $targetElement ) {
		// Remove existing mapping if any
		$targetElement.find( '.aie-mapped-source' ).remove();

		// Add mapped source indicator to target
		const mappedHtml = `
			<div class="aie-mapped-source">
				<span class="aie-source-name">${ Utils.escapeHtml( sourceField ) }</span>
				<span class="dashicons dashicons-no-alt aie-remove-mapping"></span>
			</div>
		`;
		$targetElement.find( '.aie-field-info' ).append( mappedHtml );
		$targetElement.addClass( 'has-mapping' );
		$targetElement.data( 'mapped-source-index', sourceIndex );
		$targetElement.data( 'mapped-source-field', sourceField );

		// Add to mapped fields section
		this.addToMappedFields( sourceField, sourceIndex, targetField, fieldType );
	},

	/**
	 * Add mapping to mapped fields section
	 */
	addToMappedFields( sourceField, sourceIndex, targetField, fieldType ) {
		const $container = jQuery( '.aie-mapped-fields' );
		
		// Hide empty state
		$container.find( '.aie-empty-state' ).hide();

		// Remove existing row if any
		jQuery( `.aie-mapping-row[data-source-index="${ sourceIndex }"]` ).remove();

		// Add new row
		const html = `
			<div class="aie-mapping-row" data-source-index="${ sourceIndex }" data-target-field="${ targetField }">
				<div class="aie-source-col">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<strong>${ Utils.escapeHtml( sourceField ) }</strong>
				</div>
				<div class="aie-arrow">→</div>
				<div class="aie-target-col">
					<span class="dashicons dashicons-wordpress"></span>
					<strong>${ targetField }</strong>
				</div>
				<div class="aie-mapping-actions">
					<button type="button" class="button button-small aie-add-function" data-source-index="${ sourceIndex }">
						<span class="dashicons dashicons-admin-tools"></span>
					</button>
					<button type="button" class="button button-small aie-remove-row-mapping" data-source-index="${ sourceIndex }">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
		`;

		$container.append( html );
	},

	/**
	 * Update mapping statistics
	 */
	updateMappingStats() {
		const totalFields = this.fileData?.columns?.length || 0;
		const mappedCount = jQuery( '.aie-field-card.mapped' ).length;

		jQuery( '.aie-mapped-count' ).text( mappedCount );
		jQuery( '.aie-total-fields' ).text( totalFields );
	},

	/**
	 * Initialize field search
	 */
	initializeFieldSearch() {
		// Search source fields
		jQuery( '.aie-search-source' ).on( 'input', function () {
			const query = jQuery( this ).val().toLowerCase();
			jQuery( '.aie-field-card' ).each( function () {
				const fieldName = jQuery( this ).find( '.aie-field-name' ).text().toLowerCase();
				jQuery( this ).toggle( fieldName.includes( query ) );
			} );
		} );

		// Search target fields
		jQuery( '.aie-search-target' ).on( 'input', function () {
			const query = jQuery( this ).val().toLowerCase();
			jQuery( '.aie-target-field' ).each( function () {
				const fieldName = jQuery( this ).find( '.aie-field-label' ).text().toLowerCase();
				jQuery( this ).toggle( fieldName.includes( query ) );
			} );
		} );
	},

	/**
	 * Get target fields for content type
	 */
	getTargetFields( contentType ) {
		const fields = {
			post: [
				{ value: 'post_title', label: 'Title' },
				{ value: 'post_content', label: 'Content' },
				{ value: 'post_excerpt', label: 'Excerpt' },
				{ value: 'post_status', label: 'Status' },
				{ value: 'post_author', label: 'Author' },
				{ value: 'post_date', label: 'Date' },
				{ value: 'post_name', label: 'Slug' },
				{ value: 'categories', label: 'Categories' },
				{ value: 'tags', label: 'Tags' },
				{ value: 'featured_image', label: 'Featured Image' },
			],
			media: [
				{ value: 'post_title', label: 'Title' },
				{ value: 'post_content', label: 'Description' },
				{ value: 'post_excerpt', label: 'Caption' },
				{ value: 'file_url', label: 'File URL' },
				{ value: 'alt_text', label: 'Alt Text' },
			],
		};

		return fields[ contentType ] || fields.post;
	},

	/**
	 * Auto-map fields
	 */
	autoMapFields() {
		let mappedCount = 0;

		// Clear existing mappings
		this.clearFieldMapping();

		// Try to auto-match source to target fields
		jQuery( '.aie-field-card' ).each( ( index, sourceCard ) => {
			const $sourceCard = jQuery( sourceCard );
			const sourceField = $sourceCard.data( 'source-field' ).toLowerCase();
			const sourceIndex = $sourceCard.data( 'source-index' );

			// Try to find matching target
			let matched = false;

			jQuery( '.aie-target-field' ).each( ( i, targetField ) => {
				if ( matched ) return;

				const $targetField = jQuery( targetField );
				const targetFieldValue = $targetField.data( 'target-field' ).toLowerCase();
				const targetLabel = $targetField.find( '.aie-field-label' ).text().toLowerCase();

				// Check for exact or partial match
				if (
					sourceField === targetFieldValue ||
					sourceField === targetLabel ||
					sourceField.includes( targetFieldValue ) ||
					targetFieldValue.includes( sourceField ) ||
					sourceField.replace( /_/g, ' ' ) === targetLabel
				) {
					// Create mapping
					this.createMapping(
						$sourceCard.data( 'source-field' ),
						sourceIndex,
						$targetField.data( 'target-field' ),
						$targetField.data( 'field-type' ),
						$targetField
					);

					$sourceCard.addClass( 'mapped' );
					matched = true;
					mappedCount++;
				}
			} );
		} );

		this.updateMappingStats();
		Utils.showNotice( `Auto-mapped ${ mappedCount } fields`, 'success' );
	},

	/**
	 * Clear field mapping
	 */
	clearFieldMapping() {
		// Clear all mappings
		jQuery( '.aie-target-field' ).each( function () {
			jQuery( this ).find( '.aie-mapped-source' ).remove();
			jQuery( this ).removeClass( 'has-mapping' );
			jQuery( this ).removeData( 'mapped-source-index' );
			jQuery( this ).removeData( 'mapped-source-field' );
		} );

		// Unmark all source fields
		jQuery( '.aie-field-card' ).removeClass( 'mapped' );

		// Clear mapped fields section
		jQuery( '.aie-mapped-fields' ).html( `
			<div class="aie-empty-state">
				<span class="dashicons dashicons-info"></span>
				<p>Drag source columns to WordPress fields to create mappings</p>
			</div>
		` );

		this.updateMappingStats();
	},

	/**
	 * Get field mapping
	 */
	getFieldMapping() {
		const mapping = [];

		jQuery( '.aie-mapping-row' ).each( function () {
			const $row = jQuery( this );
			const sourceIndex = $row.data( 'source-index' );
			const targetField = $row.data( 'target-field' );
			const sourceField = jQuery( `.aie-field-card[data-source-index="${ sourceIndex }"]` ).data( 'source-field' );

			if ( sourceField && targetField ) {
				mapping.push( {
					source_index: sourceIndex,
					source_field: sourceField,
					target_field: targetField,
					function_id: $row.data( 'function-id' ) || null,
				} );
			}
		} );

		console.log( 'Field mapping:', mapping );
		return mapping;
	},

	/**
	 * Start import
	 */
	async startImport() {
		try {
			const data = {
				file_path: this.fileData.file_path,
				content_type: jQuery(
					'input[name="content_type"]:checked'
				).val(),
				format: this.fileData.format,
				field_mapping: this.getFieldMapping(),
				duplicate_handling: jQuery(
					'input[name="duplicate_handling"]:checked'
				).val(),
				post_status: jQuery( '[name="post_status"]' ).val(),
				post_type: jQuery( '[name="post_type"]' ).val(),
				download_images: jQuery( '[name="download_images"]' ).is(
					':checked'
				),
				batch_size:
					parseInt( jQuery( '[name="batch_size"]' ).val() ) || 50,
			};

			const response = await Utils.ajax( 'aie_import_start', data );

			this.jobId = response.job_id;
			this.showStep( 6 );
			this.startProgressTracking();

			Utils.showNotice( 'Import started successfully', 'success' );
		} catch ( error ) {
			Utils.handleError( error, 'Start import' );
		}
	},

	/**
	 * Start progress tracking
	 */
	startProgressTracking() {
		this.progressInterval = setInterval( () => {
			this.updateProgress();
		}, 2000 );
	},

	/**
	 * Update import progress
	 */
	async updateProgress() {
		try {
			const response = await Utils.ajax( 'aie_import_get_progress', {
				job_id: this.jobId,
			} );

			Utils.updateProgressBar( jQuery( '.aie-step-6' ), response );

			if ( response.status === 'completed' ) {
				this.onImportComplete( response );
			} else if ( response.status === 'failed' ) {
				this.onImportFailed( response );
			}
		} catch ( error ) {
			console.error( 'Progress update error:', error );
		}
	},

	/**
	 * Handle import completion
	 */
	onImportComplete( result ) {
		clearInterval( this.progressInterval );

		jQuery( '.aie-import-results' ).show();
		jQuery( '.aie-result-processed' ).text( result.processed || 0 );
		jQuery( '.aie-result-success' ).text( result.success || 0 );
		jQuery( '.aie-result-failed' ).text( result.failed || 0 );
		jQuery( '.aie-result-duration' ).text(
			result.estimates?.elapsed_formatted || '0s'
		);

		jQuery( '.aie-cancel-import' ).hide();
		jQuery( '.aie-new-import' ).show();

		Utils.showNotice( 'Import completed successfully!', 'success' );
	},

	/**
	 * Handle import failure
	 */
	onImportFailed( result ) {
		clearInterval( this.progressInterval );
		Utils.showNotice(
			'Import failed: ' + ( result.error || 'Unknown error' ),
			'error'
		);
	},

	/**
	 * Cancel import
	 */
	async cancelImport() {
		if ( ! confirm( 'Are you sure you want to cancel this import?' ) ) {
			return;
		}

		try {
			await Utils.ajax( 'aie_import_cancel', { job_id: this.jobId } );
			clearInterval( this.progressInterval );
			Utils.showNotice( 'Import cancelled', 'info' );
			this.resetWizard();
		} catch ( error ) {
			Utils.handleError( error, 'Cancel import' );
		}
	},

	/**
	 * Toggle logs visibility
	 */
	toggleLogs() {
		jQuery( '.aie-logs-container' ).slideToggle();
	},

	/**
	 * Reset wizard
	 */
	resetWizard() {
		this.currentStep = 1;
		this.uploadedFile = null;
		this.fileData = null;
		this.jobId = null;
		clearInterval( this.progressInterval );

		jQuery(
			'#wp-aie-import input[type="text"], #wp-aie-import input[type="file"]'
		).val( '' );
		jQuery( '#wp-aie-import input[type="radio"]:first' ).prop(
			'checked',
			true
		);
		jQuery( '.aie-file-info' ).hide();
		jQuery( '.aie-upload-placeholder' ).show();
		jQuery( '.aie-import-results' ).hide();

		this.showStep( 1 );
	},
};

export default ImportModule;
