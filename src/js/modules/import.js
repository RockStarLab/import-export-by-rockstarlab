/**
 * Import Module
 *
 * Handles the import wizard functionality
 */

import Utils from './utils';

const ImportModule = {
	// Current wizard state
	currentStep: 1,
	totalSteps: 6,
	uploadedFile: null,
	fileData: null,
	jobId: null,
	progressInterval: null,

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
		// Validate file
		const validation = Utils.validateFile(
			file,
			[ '.csv', '.json', '.xml' ],
			50 * 1024 * 1024
		);

		if ( ! validation.valid ) {
			Utils.showNotice( validation.errors.join( '<br>' ), 'error' );
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

		// Enable next button
		jQuery( '.aie-step-2 .aie-next-step' ).prop( 'disabled', false );

		// Upload file
		this.uploadFile( file );
	},

	/**
	 * Upload file to server
	 */
	async uploadFile( file ) {
		const formData = new FormData();
		formData.append( 'file', file );
		formData.append( 'action', 'aie_import_upload_file' );
		formData.append( 'nonce', window.aieData?.nonce || '' );
		formData.append(
			'content_type',
			jQuery( 'input[name="content_type"]:checked' ).val()
		);

		try {
			const response = await jQuery.ajax( {
				url: window.aieData?.ajaxUrl || '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
			} );

			if ( response.success ) {
				this.fileData = response.data;
				Utils.showNotice( 'File uploaded successfully', 'success' );
			} else {
				throw new Error( response.data?.message || 'Upload failed' );
			}
		} catch ( error ) {
			Utils.handleError( error, 'File upload' );
			this.removeFile();
		}
	},

	/**
	 * Remove uploaded file
	 */
	removeFile() {
		this.uploadedFile = null;
		this.fileData = null;

		jQuery( '.aie-file-info' ).hide();
		jQuery( '.aie-upload-placeholder' ).show();
		jQuery( '.aie-format-options' ).hide();
		jQuery( '#aie-file-input' ).val( '' );
		jQuery( '.aie-step-2 .aie-next-step' ).prop( 'disabled', true );
	},

	/**
	 * Detect file format from filename
	 */
	detectFormat( filename ) {
		const ext = filename.split( '.' ).pop().toLowerCase();
		return [ 'csv', 'json', 'xml' ].includes( ext ) ? ext : 'csv';
	},

	/**
	 * Load data preview
	 */
	async loadPreview() {
		if ( ! this.fileData || ! this.fileData.preview ) {
			return;
		}

		const preview = this.fileData.preview;
		const $table = jQuery( '.aie-preview-table' );

		// Update stats
		jQuery( '.aie-total-rows' ).text( this.fileData.total_rows || 0 );
		jQuery( '.aie-total-columns' ).text(
			this.fileData.columns?.length || 0
		);

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
					const cellContent = Utils.escapeHtml(
						String( cell ).substring( 0, 100 )
					);
					bodyHtml += `<td>${ cellContent }</td>`;
				} );
				bodyHtml += '</tr>';
			} );
		}
		$table.find( 'tbody' ).html( bodyHtml );
	},

	/**
	 * Build field mapping interface
	 */
	buildFieldMapping() {
		if ( ! this.fileData || ! this.fileData.columns ) {
			return;
		}

		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();
		const targetFields = this.getTargetFields( contentType );
		const $tbody = jQuery( '.aie-mapping-body' );

		let html = '';
		this.fileData.columns.forEach( ( column, index ) => {
			const sampleData =
				this.fileData.preview?.data?.[ 0 ]?.[ index ] || '';

			html += `
				<tr>
					<td><strong>${ Utils.escapeHtml( column ) }</strong></td>
					<td>
						<select name="field_map[${ index }]" class="regular-text">
							<option value="">-- ${ window.aieData?.i18n?.skip || 'Skip' } --</option>
							${ targetFields
								.map(
									( field ) =>
										`<option value="${ field.value }">${ field.label }</option>`
								)
								.join( '' ) }
						</select>
					</td>
					<td><code>${ Utils.escapeHtml(
						String( sampleData ).substring( 0, 50 )
					) }</code></td>
				</tr>
			`;
		} );

		$tbody.html( html );
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
		const $selects = jQuery( '.aie-mapping-body select' );

		$selects.each( function () {
			const $select = jQuery( this );
			const columnName = $select
				.closest( 'tr' )
				.find( 'strong' )
				.text()
				.toLowerCase();

			// Try to find matching field
			$select.find( 'option' ).each( function () {
				const optionValue = jQuery( this ).val().toLowerCase();
				const optionLabel = jQuery( this ).text().toLowerCase();

				if (
					columnName === optionValue ||
					columnName === optionLabel ||
					columnName.includes( optionValue ) ||
					optionValue.includes( columnName )
				) {
					$select.val( jQuery( this ).val() );
					return false;
				}
			} );
		} );

		Utils.showNotice( 'Auto-mapping completed', 'success' );
	},

	/**
	 * Clear field mapping
	 */
	clearFieldMapping() {
		jQuery( '.aie-mapping-body select' ).val( '' );
	},

	/**
	 * Get field mapping
	 */
	getFieldMapping() {
		const mapping = {};
		jQuery( '.aie-mapping-body select' ).each( function () {
			const $select = jQuery( this );
			const sourceIndex = $select
				.attr( 'name' )
				.match( /\[(\d+)\]/ )?.[ 1 ];
			const targetField = $select.val();

			if ( targetField && sourceIndex !== undefined ) {
				mapping[ sourceIndex ] = targetField;
			}
		} );
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
