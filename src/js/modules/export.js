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
			Utils.debounce( () => this.refreshCount( false ), 500 )
		);
		$wizard.on( 'click', '.aie-refresh-count', () => this.refreshCount( true ) );

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

		// Dynamic Filters
		$wizard.on( 'click', '.aie-add-filter', () => this.addFilterRow() );
		$wizard.on( 'click', '.aie-remove-filter', ( e ) =>
			this.removeFilterRow( e )
		);
		$wizard.on( 'change', '.aie-filter-field', ( e ) =>
			this.onFilterFieldChange( e )
		);
		
		// Dynamic filter value changes - auto refresh count when filter is complete
		$wizard.on( 'change', '.aie-filter-condition', ( e ) => {
			const $row = jQuery( e.target ).closest( '.aie-filter-row' );
			
			// Update input type based on condition
			this.updateValueInputType( $row );
			
			if ( this.isFilterRowComplete( $row ) ) {
				Utils.debounce( () => this.refreshCount( false ), 500 )();
			}
		} );
		$wizard.on( 'input', '.aie-filter-value', ( e ) => {
			const $row = jQuery( e.target ).closest( '.aie-filter-row' );
			if ( this.isFilterRowComplete( $row ) ) {
				Utils.debounce( () => this.refreshCount( false ), 1000 )();
			}
		} );
		$wizard.on( 'change', '.aie-filter-value', ( e ) => {
			const $row = jQuery( e.target ).closest( '.aie-filter-row' );
			if ( this.isFilterRowComplete( $row ) ) {
				Utils.debounce( () => this.refreshCount( false ), 500 )();
			}
		} );
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
			this.refreshCount( false ); // Don't show spinner on auto-refresh
		} else {
			// Reset count when leaving step 2
			this.resetCount();
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

		// Clear existing filters
		jQuery( '#aie-filters-list' ).empty();

		// Show/hide custom filters section
		const filterableTypes = [ 
			'post', 'page', 'media', 'menu', 'user', 'comment', 
			'block_theme_settings', 'custom_post_types', 'taxonomy',
			'woo_product', 'woo_order', 'woo_coupon', 'woo_attribute',
			'custom_table'
		];
		if ( filterableTypes.includes( contentType ) ) {
			jQuery( '.aie-custom-filters-section' ).show();
		} else {
			jQuery( '.aie-custom-filters-section' ).hide();
		}

		// Update field groups visibility if needed
		if ( contentType === 'media' ) {
			jQuery( '.aie-post-field-group' ).hide();
			jQuery( '.aie-media-field-group' ).show();
		} else {
			jQuery( '.aie-post-field-group' ).show();
			jQuery( '.aie-media-field-group' ).hide();
		}

		// Refresh count (without spinner)
		this.refreshCount( false );
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
	async refreshCount( showSpinner = true ) {
		const $count = jQuery( '.aie-count-value' );
		const $spinner = jQuery( '.aie-item-count .spinner' );
		const $refreshBtn = jQuery( '.aie-refresh-count' );

		if ( showSpinner ) {
			$spinner.addClass( 'is-active' );
		}
		$refreshBtn.addClass( 'is-refreshing' );

		try {
			const contentType = jQuery(
				'input[name="content_type"]:checked'
			).val();

			// Prepare options based on content type
			let options = {};
			
			if ( contentType === 'custom_table' ) {
				// For custom tables, get table name from first filter row
				const $tableSelector = jQuery( '.aie-table-selector' );
				options = {
					table_name: $tableSelector.val(),
					filters: this.getDynamicFilters(),
				};
			} else {
				// For other types, use dynamic filters
				const dynamicFilters = this.getDynamicFilters();
				
				// Map content type to post_type for post-based exporters
				const postType = this.getPostTypeForContentType( contentType );
				if ( postType ) {
					options.post_type = postType;
				}
				
				// Add dynamic filters as query parameters
				if ( dynamicFilters.length > 0 ) {
					options.filters = dynamicFilters;
				}
			}

			const response = await Utils.ajax( 'aie_export_get_count', {
				export_type: contentType,
				options: options,
			} );

			$count.text( response.count || 0 );
		} catch ( error ) {
			$count.text( '-' );
			console.error( 'Count error:', error );
		} finally {
			$spinner.removeClass( 'is-active' );
			$refreshBtn.removeClass( 'is-refreshing' );
		}
	},

	/**
	 * Reset count display
	 */
	resetCount() {
		const $count = jQuery( '.aie-count-value' );
		const $spinner = jQuery( '.aie-item-count .spinner' );
		const $refreshBtn = jQuery( '.aie-refresh-count' );
		
		$count.text( '-' );
		$spinner.removeClass( 'is-active' );
		$refreshBtn.removeClass( 'is-refreshing' );
	},

	/**
	 * Map content type to WordPress post_type
	 */
	getPostTypeForContentType( contentType ) {
		const postTypeMap = {
			post: 'post',
			page: 'page',
			media: 'attachment',
			menu: 'nav_menu_item',
			comment: null, // Comments are not post type
			user: null, // Users are not post type
			block_theme_settings: 'wp_template',
			taxonomy: null, // Taxonomies are not post type
			custom_post_types: null, // Will be determined dynamically
			woo_product: 'product',
			woo_order: 'shop_order',
			woo_coupon: 'shop_coupon',
			woo_attribute: null, // Attributes are taxonomy-based
			custom_table: null, // Not a post type
		};

		return postTypeMap[ contentType ] || null;
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
	 * Get dynamic filters from filter rows
	 */
	getDynamicFilters() {
		const filters = [];
		
		jQuery( '.aie-filter-row' ).each( ( index, row ) => {
			const $row = jQuery( row );
			const field = $row.find( '.aie-filter-field' ).val();
			const condition = $row.find( '.aie-filter-condition' ).val();
			const value = $row.find( '.aie-filter-value' ).val();

			// Skip empty or incomplete filters
			if ( ! field || ! condition ) {
				return;
			}

			// Skip table selector for custom_table type
			const fieldType = $row.find( '.aie-filter-field option:selected' ).data( 'type' );
			if ( fieldType === 'table_selector' ) {
				return;
			}

			// For conditions that don't need value
			const noValueConditions = [ 'is_empty', 'is_not_empty' ];
			if ( noValueConditions.includes( condition ) || ( value && value.trim() !== '' ) ) {
				filters.push( {
					field: field,
					condition: condition,
					value: value || '',
				} );
			}
		} );

		return filters;
	},

	/**
	 * Get custom table filters (deprecated - use getDynamicFilters instead)
	 */
	getCustomTableFilters() {
		const filters = [];
		jQuery( '.aie-filter-row' ).each( ( index, row ) => {
			const $row = jQuery( row );
			const field = $row.find( '.aie-filter-field' ).val();
			const condition = $row.find( '.aie-filter-condition' ).val();
			const value = $row.find( '.aie-filter-value' ).val();

			// Skip table selector row and empty filters
			if ( ! field || field === 'table_name' || ! condition ) {
				return;
			}

			filters.push( {
				field: field,
				condition: condition,
				value: value,
			} );
		} );

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
				export_type: jQuery(
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

	/**
	 * Add new filter row
	 */
	addFilterRow() {
		const template = document.getElementById( 'aie-filter-row-template' );
		const clone = template.content.cloneNode( true );
		const contentType = jQuery( 'input[name="content_type"]:checked' ).val();

		// Populate field options based on content type
		const $fieldSelect = jQuery( clone ).find( '.aie-filter-field' );
		const fields = this.getFieldsByContentType( contentType );

		fields.forEach( ( group ) => {
			const $optgroup = jQuery( '<optgroup>' ).attr(
				'label',
				group.label
			);
			group.options.forEach( ( option ) => {
				$optgroup.append(
					jQuery( '<option>' )
						.val( option.value )
						.text( option.label )
						.data( 'type', option.type )
				);
			} );
			$fieldSelect.append( $optgroup );
		} );

		jQuery( '#aie-filters-list' ).append( clone );

		// Trigger count refresh (without spinner)
		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Remove filter row
	 */
	removeFilterRow( e ) {
		jQuery( e.target ).closest( '.aie-filter-row' ).remove();

		// Trigger count refresh (without spinner)
		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Handle filter field change
	 */
	onFilterFieldChange( e ) {
		const $field = jQuery( e.target );
		const $row = $field.closest( '.aie-filter-row' );
		const $condition = $row.find( '.aie-filter-condition' );
		const $valueWrap = $row.find( '.aie-filter-value-wrap' );
		const $value = $row.find( '.aie-filter-value' );

		const selectedOption = $field.find( 'option:selected' );
		const fieldType = selectedOption.data( 'type' ) || 'string';

		// Special handling for table_selector
		if ( fieldType === 'table_selector' ) {
			// Hide condition dropdown for table selector
			$condition.closest( '.aie-filter-condition-wrap' ).hide();
			
			// Replace value input with table selector
			$valueWrap.find( 'label' ).text( 'Select Table' );
			
			// Create a select dropdown for tables
			const $select = jQuery( '<select>' )
				.addClass( 'aie-filter-value aie-table-selector' )
				.attr( 'name', 'filter_value[]' );
			
			// Fetch database tables via AJAX
			Utils.ajax( {
				action: 'get_database_tables',
			} ).then( ( response ) => {
				if ( response.success && response.data ) {
					$select.append( jQuery( '<option>' ).val( '' ).text( 'Select Table...' ) );
					
					response.data.forEach( ( table ) => {
						$select.append(
							jQuery( '<option>' )
								.val( table.name )
								.text( table.name )
						);
					} );

					// When table is selected, reload filter fields
					$select.on( 'change', () => {
						const tableName = $select.val();
						if ( tableName ) {
							this.loadTableColumns( tableName );
						}
					} );
				}
			} );
			
			$value.replaceWith( $select );
			return;
		}

		// Special handling for post_type_selector
		if ( fieldType === 'post_type_selector' ) {
			// Hide condition dropdown for post type selector
			$condition.closest( '.aie-filter-condition-wrap' ).hide();
			
			// Replace value input with post type selector
			$valueWrap.find( 'label' ).text( 'Select Post Type' );
			
			// Create a select dropdown for post types
			const $select = jQuery( '<select>' )
				.addClass( 'aie-filter-value aie-post-type-selector' )
				.attr( 'name', 'filter_value[]' );
			
			// Fetch post types via AJAX
			Utils.ajax( {
				action: 'aie_get_post_types',
				include_hidden: true,
			} ).then( ( response ) => {
				if ( response.success && response.data ) {
					$select.append( jQuery( '<option>' ).val( '' ).text( 'Select Post Type...' ) );
					
					response.data.forEach( ( postType ) => {
						$select.append(
							jQuery( '<option>' )
								.val( postType.name )
								.text( postType.label + ' (' + postType.name + ')' )
						);
					} );
				}
			} );
			
			$value.replaceWith( $select );
			return;
		}

		// Show condition dropdown for normal fields
		$condition.closest( '.aie-filter-condition-wrap' ).show();
		$valueWrap.find( 'label' ).text( 'Value' );

		// If current input is a select (from post_type_selector or table_selector), replace with input
		if ( $value.is( 'select' ) ) {
			const $input = jQuery( '<input>' )
				.attr( 'type', 'text' )
				.addClass( 'aie-filter-value' )
				.attr( 'name', 'filter_value[]' )
				.attr( 'placeholder', 'Enter value...' );
			$value.replaceWith( $input );
			// Update reference
			$row.find( '.aie-filter-value' ).attr( 'type', fieldType === 'date' ? 'date' : ( fieldType === 'number' ? 'number' : 'text' ) );
		} else {
			// Clear existing conditions
			$condition.empty();

			// Populate conditions based on field type
			const conditions = this.getConditionsByFieldType( fieldType );
			
			// Get the actual field name to filter out inappropriate conditions
			const fieldName = $field.val();
			
			// Filter conditions based on field
			const filteredConditions = conditions.filter( ( condition ) => {
				// For ID fields, exclude is_empty and is_not_empty (ID cannot be empty)
				if ( fieldName === 'ID' || fieldName === 'comment_ID' || fieldName === 'term_id' || fieldName === 'user_id' || fieldName === 'attribute_id' ) {
					return condition.value !== 'is_empty' && condition.value !== 'is_not_empty';
				}
				return true;
			} );
			
			filteredConditions.forEach( ( condition ) => {
				$condition.append(
					jQuery( '<option>' )
						.val( condition.value )
						.text( condition.label )
				);
			} );

			// Change value input type based on field type
			if ( fieldType === 'date' ) {
				$value.attr( 'type', 'date' );
			} else if ( fieldType === 'number' ) {
				$value.attr( 'type', 'number' );
			} else {
				$value.attr( 'type', 'text' );
			}
			
			// Update input type based on current condition
			this.updateValueInputType( $row );
		}

		// Trigger count refresh (without spinner)
		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Load table columns dynamically
	 */
	loadTableColumns( tableName ) {
		Utils.ajax( {
			action: 'get_table_columns',
			table_name: tableName,
		} ).then( ( response ) => {
			if ( response.success && response.data ) {
				// Store columns for later use
				this.tableColumns = response.data;
				
				// Update all filter field dropdowns
				jQuery( '.aie-filter-field' ).each( ( index, element ) => {
					const $fieldSelect = jQuery( element );
					const currentValue = $fieldSelect.val();
					
					// Clear and rebuild options
					$fieldSelect.empty();
					$fieldSelect.append( jQuery( '<option>' ).val( '' ).text( 'Select Field...' ) );
					
					// Add columns as options
					response.data.forEach( ( column ) => {
						$fieldSelect.append(
							jQuery( '<option>' )
								.val( column.name )
								.text( column.name + ' (' + column.type + ')' )
								.data( 'type', column.data_type )
						);
					} );
					
					// Restore previous value if exists
					if ( currentValue ) {
						$fieldSelect.val( currentValue );
					}
				} );
			}
		} );
	},

	/**
	 * Get fields by content type
	 */
	getFieldsByContentType( contentType ) {
		const baseFields = [
			{
				label: 'Standard',
				options: [
					{ value: 'ID', label: 'ID', type: 'number' },
					{ value: 'post_title', label: 'Title', type: 'string' },
					{ value: 'post_content', label: 'Content', type: 'string' },
					{ value: 'post_excerpt', label: 'Excerpt', type: 'string' },
					{ value: 'post_date', label: 'Date', type: 'date' },
					{ value: 'post_name', label: 'Slug', type: 'string' },
					{ value: 'post_status', label: 'Status', type: 'string' },
				],
			},
			{
				label: 'Taxonomy',
				options: [
					{ value: 'categories', label: 'Categories', type: 'string' },
					{ value: 'tags', label: 'Tags', type: 'string' },
				],
			},
			{
				label: 'Author',
				options: [
					{ value: 'post_author', label: 'Author ID', type: 'number' },
					{ value: 'author_name', label: 'Author Name', type: 'string' },
					{ value: 'author_email', label: 'Author Email', type: 'string' },
				],
			},
			{
				label: 'Other',
				options: [
					{ value: 'post_parent', label: 'Parent ID', type: 'number' },
					{ value: 'comment_status', label: 'Comment Status', type: 'string' },
					{ value: 'post_modified', label: 'Modified Date', type: 'date' },
				],
			},
		];

		// Customize based on content type
		if ( contentType === 'media' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'ID', type: 'number' },
						{ value: 'post_title', label: 'Title', type: 'string' },
						{ value: 'post_content', label: 'Description', type: 'string' },
						{ value: 'post_excerpt', label: 'Caption', type: 'string' },
						{ value: 'post_name', label: 'Slug', type: 'string' },
					],
				},
				{
					label: 'File',
					options: [
						{ value: 'post_mime_type', label: 'MIME Type', type: 'string' },
						{ value: 'file_size', label: 'File Size (bytes)', type: 'number' },
						{ value: 'file_name', label: 'File Name', type: 'string' },
						{ value: 'file_extension', label: 'File Extension', type: 'string' },
						{ value: 'file_path', label: 'File Path', type: 'string' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'post_date', label: 'Upload Date', type: 'date' },
						{ value: 'post_modified', label: 'Modified Date', type: 'date' },
					],
				},
				{
					label: 'Author',
					options: [
						{ value: 'post_author', label: 'Author ID', type: 'number' },
						{ value: 'author_name', label: 'Author Name', type: 'string' },
					],
				},
				{
					label: 'Other',
					options: [
						{ value: 'post_parent', label: 'Attached To (Post ID)', type: 'number' },
						{ value: 'alt_text', label: 'Alt Text', type: 'string' },
					],
				},
			];
		}

		// Pages don't have taxonomy
		if ( contentType === 'page' ) {
			return baseFields.filter( group => group.label !== 'Taxonomy' );
		}

		// Menus
		if ( contentType === 'menu' ) {
			return [
				{
					label: 'Menu',
					options: [
						{ value: 'term_id', label: 'Menu ID', type: 'number' },
						{ value: 'name', label: 'Menu Name', type: 'string' },
						{ value: 'slug', label: 'Menu Slug', type: 'string' },
						{ value: 'count', label: 'Item Count', type: 'number' },
					],
				},
				{
					label: 'Menu Items',
					options: [
						{ value: 'item_type', label: 'Item Type', type: 'string' },
						{ value: 'item_title', label: 'Item Title', type: 'string' },
						{ value: 'item_url', label: 'Item URL', type: 'string' },
						{ value: 'item_parent', label: 'Parent Item ID', type: 'number' },
						{ value: 'item_position', label: 'Position', type: 'number' },
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
						{ value: 'ID', label: 'User ID', type: 'number' },
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
					label: 'Role & Status',
					options: [
						{ value: 'role', label: 'Role', type: 'string' },
						{ value: 'user_status', label: 'Status', type: 'number' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'user_registered', label: 'Registration Date', type: 'date' },
					],
				},
			];
		}

		// Comments
		if ( contentType === 'comment' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'comment_ID', label: 'Comment ID', type: 'number' },
						{ value: 'comment_post_ID', label: 'Post ID', type: 'number' },
						{ value: 'comment_content', label: 'Comment Content', type: 'string' },
						{ value: 'comment_approved', label: 'Status', type: 'string' },
					],
				},
				{
					label: 'Author',
					options: [
						{ value: 'comment_author', label: 'Author Name', type: 'string' },
						{ value: 'comment_author_email', label: 'Author Email', type: 'string' },
						{ value: 'comment_author_url', label: 'Author URL', type: 'string' },
						{ value: 'comment_author_IP', label: 'Author IP', type: 'string' },
						{ value: 'user_id', label: 'User ID', type: 'number' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'comment_date', label: 'Comment Date', type: 'date' },
					],
				},
				{
					label: 'Other',
					options: [
						{ value: 'comment_parent', label: 'Parent Comment ID', type: 'number' },
						{ value: 'comment_karma', label: 'Karma', type: 'number' },
						{ value: 'comment_type', label: 'Comment Type', type: 'string' },
					],
				},
			];
		}

		// Block Theme Settings
		if ( contentType === 'block_theme_settings' ) {
			return [
				{
					label: 'Settings',
					options: [
						{ value: 'setting_name', label: 'Setting Name', type: 'string' },
						{ value: 'setting_type', label: 'Setting Type', type: 'string' },
						{ value: 'setting_value', label: 'Setting Value', type: 'string' },
					],
				},
				{
					label: 'Theme',
					options: [
						{ value: 'theme_slug', label: 'Theme Slug', type: 'string' },
						{ value: 'theme_version', label: 'Theme Version', type: 'string' },
					],
				},
				{
					label: 'Templates',
					options: [
						{ value: 'template_name', label: 'Template Name', type: 'string' },
						{ value: 'template_slug', label: 'Template Slug', type: 'string' },
						{ value: 'template_type', label: 'Template Type', type: 'string' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'modified_date', label: 'Modified Date', type: 'date' },
					],
				},
			];
		}

		// Custom Post Types
		if ( contentType === 'custom_post_types' ) {
			return [
				{
					label: 'Post Type Selection',
					options: [
						{ value: '_post_type', label: 'Post Type (select specific)', type: 'post_type_selector' },
					],
				},
				{
					label: 'Standard',
					options: [
						{ value: 'ID', label: 'ID', type: 'number' },
						{ value: 'post_title', label: 'Title', type: 'string' },
						{ value: 'post_content', label: 'Content', type: 'string' },
						{ value: 'post_excerpt', label: 'Excerpt', type: 'string' },
						{ value: 'post_date', label: 'Date', type: 'date' },
						{ value: 'post_name', label: 'Slug', type: 'string' },
						{ value: 'post_status', label: 'Status', type: 'string' },
					],
				},
				{
					label: 'Author',
					options: [
						{ value: 'post_author', label: 'Author ID', type: 'number' },
						{ value: 'author_name', label: 'Author Name', type: 'string' },
						{ value: 'author_email', label: 'Author Email', type: 'string' },
					],
				},
				{
					label: 'Other',
					options: [
						{ value: 'post_parent', label: 'Parent ID', type: 'number' },
						{ value: 'post_modified', label: 'Modified Date', type: 'date' },
						{ value: 'menu_order', label: 'Menu Order', type: 'number' },
					],
				},
			];
		}

		// Taxonomy
		if ( contentType === 'taxonomy' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'term_id', label: 'Term ID', type: 'number' },
						{ value: 'name', label: 'Term Name', type: 'string' },
						{ value: 'slug', label: 'Term Slug', type: 'string' },
						{ value: 'taxonomy', label: 'Taxonomy', type: 'string' },
						{ value: 'description', label: 'Description', type: 'string' },
					],
				},
				{
					label: 'Hierarchy',
					options: [
						{ value: 'parent', label: 'Parent Term ID', type: 'number' },
						{ value: 'count', label: 'Post Count', type: 'number' },
					],
				},
				{
					label: 'Meta',
					options: [
						{ value: 'term_group', label: 'Term Group', type: 'number' },
						{ value: 'term_order', label: 'Term Order', type: 'number' },
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
						{ value: 'ID', label: 'Product ID', type: 'number' },
						{ value: 'post_title', label: 'Product Name', type: 'string' },
						{ value: 'post_name', label: 'Slug', type: 'string' },
						{ value: 'post_status', label: 'Status', type: 'string' },
						{ value: 'sku', label: 'SKU', type: 'string' },
					],
				},
				{
					label: 'Product Data',
					options: [
						{ value: 'product_type', label: 'Product Type', type: 'string' },
						{ value: 'regular_price', label: 'Regular Price', type: 'number' },
						{ value: 'sale_price', label: 'Sale Price', type: 'number' },
						{ value: 'stock_quantity', label: 'Stock Quantity', type: 'number' },
						{ value: 'stock_status', label: 'Stock Status', type: 'string' },
						{ value: 'manage_stock', label: 'Manage Stock', type: 'string' },
					],
				},
				{
					label: 'Taxonomy',
					options: [
						{ value: 'product_cat', label: 'Categories', type: 'string' },
						{ value: 'product_tag', label: 'Tags', type: 'string' },
						{ value: 'product_brand', label: 'Brands', type: 'string' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'post_date', label: 'Created Date', type: 'date' },
						{ value: 'post_modified', label: 'Modified Date', type: 'date' },
					],
				},
				{
					label: 'Other',
					options: [
						{ value: 'featured', label: 'Featured', type: 'string' },
						{ value: 'visibility', label: 'Visibility', type: 'string' },
						{ value: 'total_sales', label: 'Total Sales', type: 'number' },
						{ value: 'weight', label: 'Weight', type: 'number' },
					],
				},
			];
		}

		// WooCommerce Orders
		if ( contentType === 'woo_order' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'Order ID', type: 'number' },
						{ value: 'order_number', label: 'Order Number', type: 'string' },
						{ value: 'order_status', label: 'Status', type: 'string' },
						{ value: 'order_key', label: 'Order Key', type: 'string' },
					],
				},
				{
					label: 'Amounts',
					options: [
						{ value: 'order_total', label: 'Order Total', type: 'number' },
						{ value: 'order_subtotal', label: 'Subtotal', type: 'number' },
						{ value: 'order_tax', label: 'Tax', type: 'number' },
						{ value: 'order_shipping', label: 'Shipping', type: 'number' },
						{ value: 'order_discount', label: 'Discount', type: 'number' },
					],
				},
				{
					label: 'Customer',
					options: [
						{ value: 'customer_id', label: 'Customer ID', type: 'number' },
						{ value: 'billing_email', label: 'Billing Email', type: 'string' },
						{ value: 'billing_first_name', label: 'First Name', type: 'string' },
						{ value: 'billing_last_name', label: 'Last Name', type: 'string' },
						{ value: 'billing_country', label: 'Country', type: 'string' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'order_date', label: 'Order Date', type: 'date' },
						{ value: 'completed_date', label: 'Completed Date', type: 'date' },
						{ value: 'paid_date', label: 'Paid Date', type: 'date' },
					],
				},
				{
					label: 'Other',
					options: [
						{ value: 'payment_method', label: 'Payment Method', type: 'string' },
						{ value: 'transaction_id', label: 'Transaction ID', type: 'string' },
						{ value: 'currency', label: 'Currency', type: 'string' },
					],
				},
			];
		}

		// WooCommerce Coupons
		if ( contentType === 'woo_coupon' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'Coupon ID', type: 'number' },
						{ value: 'post_title', label: 'Coupon Code', type: 'string' },
						{ value: 'post_excerpt', label: 'Description', type: 'string' },
						{ value: 'post_status', label: 'Status', type: 'string' },
					],
				},
				{
					label: 'Discount',
					options: [
						{ value: 'discount_type', label: 'Discount Type', type: 'string' },
						{ value: 'coupon_amount', label: 'Coupon Amount', type: 'number' },
						{ value: 'minimum_amount', label: 'Minimum Amount', type: 'number' },
						{ value: 'maximum_amount', label: 'Maximum Amount', type: 'number' },
					],
				},
				{
					label: 'Usage',
					options: [
						{ value: 'usage_count', label: 'Usage Count', type: 'number' },
						{ value: 'usage_limit', label: 'Usage Limit', type: 'number' },
						{ value: 'usage_limit_per_user', label: 'Usage Limit Per User', type: 'number' },
						{ value: 'individual_use', label: 'Individual Use', type: 'string' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'date_expires', label: 'Expiry Date', type: 'date' },
						{ value: 'post_date', label: 'Created Date', type: 'date' },
						{ value: 'post_modified', label: 'Modified Date', type: 'date' },
					],
				},
				{
					label: 'Other',
					options: [
						{ value: 'free_shipping', label: 'Free Shipping', type: 'string' },
						{ value: 'exclude_sale_items', label: 'Exclude Sale Items', type: 'string' },
					],
				},
			];
		}

		// WooCommerce Attributes
		if ( contentType === 'woo_attribute' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'attribute_id', label: 'Attribute ID', type: 'number' },
						{ value: 'attribute_name', label: 'Attribute Name', type: 'string' },
						{ value: 'attribute_label', label: 'Attribute Label', type: 'string' },
						{ value: 'attribute_type', label: 'Attribute Type', type: 'string' },
					],
				},
				{
					label: 'Settings',
					options: [
						{ value: 'attribute_orderby', label: 'Order By', type: 'string' },
						{ value: 'attribute_public', label: 'Public', type: 'string' },
					],
				},
				{
					label: 'Terms',
					options: [
						{ value: 'term_count', label: 'Term Count', type: 'number' },
						{ value: 'term_name', label: 'Term Name', type: 'string' },
						{ value: 'term_slug', label: 'Term Slug', type: 'string' },
					],
				},
			];
		}

		// Custom MySQL Table
		if ( contentType === 'custom_table' ) {
			return [
				{
					label: 'Table Selection',
					options: [
						{ value: '_table_name', label: 'Table Name (select specific)', type: 'table_selector' },
					],
				},
				{
					label: 'Dynamic Fields',
					options: [
						{ value: '_dynamic_field', label: 'Select table first to load fields', type: 'string' },
					],
				},
			];
		}

		return baseFields;
	},

	/**
	 * Check if filter row is complete (has all required values)
	 */
	isFilterRowComplete( $row ) {
		const field = $row.find( '.aie-filter-field' ).val();
		const condition = $row.find( '.aie-filter-condition' ).val();
		const value = $row.find( '.aie-filter-value' ).val();

		// Field and condition must be selected
		if ( ! field || ! condition ) {
			return false;
		}

		// Check if condition requires a value
		const noValueConditions = [ 'is_empty', 'is_not_empty' ];
		if ( noValueConditions.includes( condition ) ) {
			return true; // These conditions don't need a value
		}

		// Value must be filled
		return value && value.trim() !== '';
	},
	
	/**
	 * Update value input type based on condition and field type
	 */
	updateValueInputType( $row ) {
		const $field = $row.find( '.aie-filter-field' );
		const $condition = $row.find( '.aie-filter-condition' );
		const $value = $row.find( '.aie-filter-value' );
		
		const selectedOption = $field.find( 'option:selected' );
		const fieldType = selectedOption.data( 'type' ) || 'string';
		const condition = $condition.val();
		
		// Skip if value is not an input field
		if ( ! $value.is( 'input' ) ) {
			return;
		}
		
		// For 'in' and 'not_in' conditions, always use text input to allow comma-separated values
		if ( condition === 'in' || condition === 'not_in' ) {
			$value.attr( 'type', 'text' );
			$value.attr( 'placeholder', 'Enter values separated by comma (e.g., 1,5,8 or test1,test2)' );
			return;
		}
		
		// For 'between' condition on numbers, use text to allow comma-separated range
		if ( condition === 'between' && fieldType === 'number' ) {
			$value.attr( 'type', 'text' );
			$value.attr( 'placeholder', 'Enter two numbers separated by comma (e.g., 10,100)' );
			return;
		}
		
		// For 'is_empty' and 'is_not_empty', hide the value input
		const noValueConditions = [ 'is_empty', 'is_not_empty' ];
		if ( noValueConditions.includes( condition ) ) {
			$value.closest( '.aie-filter-value-wrap' ).hide();
			return;
		} else {
			$value.closest( '.aie-filter-value-wrap' ).show();
		}
		
		// Otherwise, set type based on field type
		if ( fieldType === 'date' ) {
			$value.attr( 'type', 'date' );
			$value.attr( 'placeholder', '' );
		} else if ( fieldType === 'number' ) {
			$value.attr( 'type', 'number' );
			$value.attr( 'placeholder', 'Enter number...' );
		} else {
			$value.attr( 'type', 'text' );
			$value.attr( 'placeholder', 'Enter value...' );
		}
	},

	/**
	 * Get conditions by field type
	 */
	getConditionsByFieldType( fieldType ) {
		const conditions = {
			string: [
				{ value: 'equals', label: 'Equals' },
				{ value: 'not_equals', label: "Doesn't Equal" },
				{ value: 'in', label: 'In' },
				{ value: 'not_in', label: 'Not In' },
				{ value: 'contains', label: 'Contains' },
				{ value: 'not_contains', label: "Doesn't Contain" },
				{ value: 'is_empty', label: 'Is Empty' },
				{ value: 'is_not_empty', label: 'Is Not Empty' },
			],
			number: [
				{ value: 'equals', label: 'Equals' },
				{ value: 'not_equals', label: "Doesn't Equal" },
				{ value: 'in', label: 'In' },
				{ value: 'not_in', label: 'Not In' },
				{ value: 'greater', label: 'Greater Than' },
				{ value: 'equals_or_greater', label: 'Equal To Or Greater Than' },
				{ value: 'less', label: 'Less Than' },
				{ value: 'equals_or_less', label: 'Equal To Or Less Than' },
				{ value: 'is_empty', label: 'Is Empty' },
				{ value: 'is_not_empty', label: 'Is Not Empty' },
			],
			date: [
				{ value: 'equals', label: 'Equals' },
				{ value: 'not_equals', label: "Doesn't Equal" },
				{ value: 'greater', label: 'Newer Than' },
				{ value: 'equals_or_greater', label: 'Equal To Or Newer Than' },
				{ value: 'less', label: 'Older Than' },
				{ value: 'equals_or_less', label: 'Equal To Or Older Than' },
				{ value: 'is_empty', label: 'Is Empty' },
				{ value: 'is_not_empty', label: 'Is Not Empty' },
			],
		};

		return conditions[ fieldType ] || conditions.string;
	},
};

export default ExportModule;
