/**
 * Export Module
 *
 * Handles the export wizard functionality
 */

import Utils from './utils';

const ExportModule = {
	currentStep: 1,
	totalSteps: 5,
	jobId: null,
	progressInterval: null,

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#wp-aie-export' ).length ) {
			return;
		}

		this.bindEvents();
		this.showStep( 1 );
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#wp-aie-export' );

		// Content type filter/search
		$wizard.on( 'input', '#aie-content-type-search', ( e ) =>
			this.filterContentTypes( e )
		);

		// Step navigation
		$wizard.on( 'click', '.aie-next-step', () => this.nextStep() );
		$wizard.on( 'click', '.aie-prev-step', () => this.prevStep() );

		// Content type
		$wizard.on( 'change', 'input[name="content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

		// Filters
		$wizard.on(
			'change',
			'.aie-export-filters input, .aie-export-filters select',
			Utils.debounce( () => this.refreshCount(), 500 )
		);
		$wizard.on( 'click', '.aie-refresh-count', () => this.refreshCount() );

		// Field selection
		$wizard.on( 'click', '.aie-select-all-fields', () =>
			this.selectAllFields( true )
		);
		$wizard.on( 'click', '.aie-deselect-all-fields', () =>
			this.selectAllFields( false )
		);
		$wizard.on( 'click', '.aie-select-common-fields', () =>
			this.selectCommonFields()
		);

		// Format selection
		$wizard.on( 'change', 'input[name="format"]', ( e ) =>
			this.onFormatChange( e )
		);

		// Export actions
		$wizard.on( 'click', '.aie-start-export', () => this.startExport() );
		$wizard.on( 'click', '.aie-cancel-export', () => this.cancelExport() );
		$wizard.on( 'click', '.aie-download-file', () => this.downloadFile() );
		$wizard.on( 'click', '.aie-new-export', () => this.resetWizard() );
	},

	/**
	 * Show specific step
	 */
	showStep( step ) {
		const $wizard = jQuery( '#wp-aie-export' );

		$wizard.find( '.aie-step' ).removeClass( 'active' );
		$wizard.find( `.aie-step-${ step }` ).addClass( 'active' );

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

		if ( step === 2 ) {
			this.refreshCount();
		}
	},

	nextStep() {
		if ( this.currentStep < this.totalSteps ) {
			this.showStep( this.currentStep + 1 );
		}
	},

	prevStep() {
		if ( this.currentStep > 1 ) {
			this.showStep( this.currentStep - 1 );
		}
	},

	/**
	 * Handle content type change
	 */
	onContentTypeChange( e ) {
		const contentType = jQuery( e.target ).val();

		if ( contentType === 'media' ) {
			jQuery( '.aie-post-filters' ).hide();
			jQuery( '.aie-media-filters' ).show();
			jQuery( '.aie-post-field-group' ).hide();
			jQuery( '.aie-media-field-group' ).show();
		} else {
			jQuery( '.aie-post-filters' ).show();
			jQuery( '.aie-media-filters' ).hide();
			jQuery( '.aie-post-field-group' ).show();
			jQuery( '.aie-media-field-group' ).hide();
		}
	},

	/**
	 * Filter content types based on search input
	 */
	filterContentTypes( e ) {
		const searchTerm = jQuery( e.target ).val().toLowerCase().trim();
		const $contentTypes = jQuery( '.aie-content-type' );
		const $filterCount = jQuery( '.aie-filter-count' );
		const $filterCountValue = jQuery( '.aie-filter-count-value' );
		const $noResults = jQuery( '.aie-no-results' );
		let visibleCount = 0;

		if ( searchTerm === '' ) {
			// Show all if search is empty
			$contentTypes.show();
			$filterCount.hide();
			$noResults.hide();
			return;
		}

		// Filter content types
		$contentTypes.each( function () {
			const $this = jQuery( this );
			const title = $this.find( 'h3' ).text().toLowerCase();
			const description = $this.find( 'p' ).text().toLowerCase();

			// Check if search term matches title or description
			if ( title.includes( searchTerm ) || description.includes( searchTerm ) ) {
				$this.show();
				visibleCount++;
			} else {
				$this.hide();
			}
		} );

		// Update and show count
		$filterCountValue.text( visibleCount );
		$filterCount.show();

		// Show/hide no results message
		if ( visibleCount === 0 ) {
			$noResults.show();
		} else {
			$noResults.hide();
		}
	},

	/**
	 * Refresh item count
	 */
	async refreshCount() {
		const filters = this.getFilters();
		const $count = jQuery( '.aie-count-value' );
		const $spinner = jQuery( '.aie-filter-summary .spinner' );

		$spinner.addClass( 'is-active' );

		try {
			const response = await Utils.ajax( 'aie_export_get_count', {
				content_type: jQuery(
					'input[name="content_type"]:checked'
				).val(),
				filters: filters,
			} );

			$count.text( response.count || 0 );
		} catch ( error ) {
			$count.text( '-' );
			console.error( 'Count error:', error );
		} finally {
			$spinner.removeClass( 'is-active' );
		}
	},

	/**
	 * Get filter values
	 */
	getFilters() {
		const filters = {};
		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();

		if ( contentType === 'post' ) {
			filters.post_type = jQuery( '[name="post_type"]' ).val();
			filters.post_status =
				jQuery( '[name="post_status[]"]' ).val() || [];
			filters.date_from = jQuery( '[name="date_from"]' ).val();
			filters.date_to = jQuery( '[name="date_to"]' ).val();
			filters.author = jQuery( '[name="author"]' ).val();
			filters.category = jQuery( '[name="category"]' ).val();
			filters.tag = jQuery( '[name="tag"]' ).val();
			filters.search = jQuery( '[name="search"]' ).val();
		} else if ( contentType === 'media' ) {
			filters.mime_type = jQuery( '[name="mime_type"]' ).val();
			filters.date_from = jQuery( '[name="media_date_from"]' ).val();
			filters.date_to = jQuery( '[name="media_date_to"]' ).val();
		}

		return filters;
	},

	/**
	 * Select/deselect all fields
	 */
	selectAllFields( checked ) {
		jQuery( 'input[name="fields[]"]:visible' ).prop( 'checked', checked );
	},

	/**
	 * Select common fields only
	 */
	selectCommonFields() {
		this.selectAllFields( false );
		const commonFields = [
			'ID',
			'post_title',
			'post_content',
			'post_status',
		];
		commonFields.forEach( ( field ) => {
			jQuery( `input[name="fields[]"][value="${ field }"]` ).prop(
				'checked',
				true
			);
		} );
	},

	/**
	 * Handle format change
	 */
	onFormatChange( e ) {
		const format = jQuery( e.target ).val();

		jQuery( '.aie-format-options > div' ).hide();
		jQuery( `.aie-${ format }-options` ).show();
	},

	/**
	 * Get selected fields
	 */
	getSelectedFields() {
		const fields = [];
		jQuery( 'input[name="fields[]"]:checked' ).each( function () {
			fields.push( jQuery( this ).val() );
		} );
		return fields;
	},

	/**
	 * Start export
	 */
	async startExport() {
		const fields = this.getSelectedFields();

		if ( fields.length === 0 ) {
			Utils.showNotice(
				'Please select at least one field to export',
				'error'
			);
			return;
		}

		try {
			const data = {
				content_type: jQuery(
					'input[name="content_type"]:checked'
				).val(),
				filters: this.getFilters(),
				fields: fields,
				format: jQuery( 'input[name="format"]:checked' ).val(),
				format_options: {
					csv_delimiter: jQuery( '[name="csv_delimiter"]' ).val(),
					csv_encoding: jQuery( '[name="csv_encoding"]' ).val(),
					csv_include_header: jQuery(
						'[name="csv_include_header"]'
					).is( ':checked' ),
					json_pretty_print: jQuery(
						'[name="json_pretty_print"]'
					).is( ':checked' ),
					xml_root: jQuery( '[name="xml_root"]' ).val(),
					xml_item: jQuery( '[name="xml_item"]' ).val(),
				},
			};

			const response = await Utils.ajax( 'aie_export_start', data );

			this.jobId = response.job_id;
			this.showStep( 5 );
			this.startProgressTracking();

			Utils.showNotice( 'Export started successfully', 'success' );
		} catch ( error ) {
			Utils.handleError( error, 'Start export' );
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
	 * Update progress
	 */
	async updateProgress() {
		try {
			const response = await Utils.ajax( 'aie_export_get_progress', {
				job_id: this.jobId,
			} );

			Utils.updateProgressBar( jQuery( '.aie-step-5' ), response );

			if ( response.status === 'completed' ) {
				this.onExportComplete( response );
			} else if ( response.status === 'failed' ) {
				this.onExportFailed( response );
			}
		} catch ( error ) {
			console.error( 'Progress update error:', error );
		}
	},

	/**
	 * Handle export completion
	 */
	onExportComplete( result ) {
		clearInterval( this.progressInterval );

		jQuery( '.aie-export-results' ).show();
		jQuery( '.aie-result-processed' ).text( result.processed || 0 );
		jQuery( '.aie-result-filesize' ).text(
			Utils.formatFileSize( result.file_size || 0 )
		);
		jQuery( '.aie-result-duration' ).text(
			result.estimates?.elapsed_formatted || '0s'
		);

		jQuery( '.aie-cancel-export' ).hide();
		jQuery( '.aie-new-export' ).show();

		Utils.showNotice( 'Export completed successfully!', 'success' );
	},

	/**
	 * Handle export failure
	 */
	onExportFailed( result ) {
		clearInterval( this.progressInterval );
		Utils.showNotice(
			'Export failed: ' + ( result.error || 'Unknown error' ),
			'error'
		);
	},

	/**
	 * Download export file
	 */
	async downloadFile() {
		try {
			const response = await Utils.ajax( 'aie_export_download', {
				job_id: this.jobId,
			} );

			if ( response.download_url ) {
				Utils.downloadFile( response.download_url, response.filename );
			}
		} catch ( error ) {
			Utils.handleError( error, 'Download file' );
		}
	},

	/**
	 * Cancel export
	 */
	async cancelExport() {
		if ( ! confirm( 'Are you sure you want to cancel this export?' ) ) {
			return;
		}

		try {
			await Utils.ajax( 'aie_export_cancel', { job_id: this.jobId } );
			clearInterval( this.progressInterval );
			Utils.showNotice( 'Export cancelled', 'info' );
			this.resetWizard();
		} catch ( error ) {
			Utils.handleError( error, 'Cancel export' );
		}
	},

	/**
	 * Reset wizard
	 */
	resetWizard() {
		this.currentStep = 1;
		this.jobId = null;
		clearInterval( this.progressInterval );

		jQuery(
			'#wp-aie-export input[type="text"], #wp-aie-export input[type="date"]'
		).val( '' );
		jQuery( '#wp-aie-export input[type="radio"]:first' ).prop(
			'checked',
			true
		);
		jQuery( '.aie-export-results' ).hide();

		this.showStep( 1 );
	},
};

export default ExportModule;
