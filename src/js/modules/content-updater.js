/**
 * Content Updater Module
 *
 * Handles the content updater wizard functionality
 */

import Utils from './utils';

const ContentUpdater = {
	currentStep: 1,
	totalSteps: 4,
	jobId: null,
	progressInterval: null,
	selectedFields: [],
	fieldFunctions: {},
	availableFunctions: [],

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#wp-aie-content-updater' ).length ) {
			return;
		}

		this.bindEvents();
		this.showStep( 1 );
		this.loadAvailableFunctions();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#wp-aie-content-updater' );

		// Content type search
		$wizard.on( 'input', '#aie-updater-content-type-search', ( e ) =>
			this.filterContentTypes( e )
		);

		// Step navigation
		$wizard.on( 'click', '.aie-updater-next-step', () => this.nextStep() );
		$wizard.on( 'click', '.aie-updater-prev-step', () => this.prevStep() );

		// Content type selection
		$wizard.on( 'change', 'input[name="updater_content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

		// Field selection (Step 2)
		$wizard.on( 'click', '.aie-updater-clear-all-fields', () => this.clearAllFields() );
		$wizard.on( 'input', '#aie-updater-fields-search', ( e ) => this.filterFields( e ) );

		// Function assignment (Step 3) - old handlers kept for compatibility
		$wizard.on( 'change', '.aie-field-function-select', ( e ) => this.onFunctionChange( e ) );
		$wizard.on( 'click', '.aie-apply-function-to-all', () => this.applyFunctionToAll() );
		$wizard.on( 'click', '.aie-clear-all-functions', () => this.clearAllFunctions() );
		$wizard.on( 'click', '.aie-test-function', ( e ) => this.testFunction( e ) );

		// Start update (Step 4)
		$wizard.on( 'click', '.aie-start-update-btn', () => this.startUpdate() );
		$wizard.on( 'click', '.aie-cancel-update-btn', () => this.cancelUpdate() );
		$wizard.on( 'click', '.aie-start-new-update', () => this.startNewUpdate() );

		// Setup drag and drop for field selection
		this.setupDragAndDrop();

		// Modal events
		this.initFieldFunctionsModal();
	},

	/**
	 * Initialize field functions modal
	 */
	initFieldFunctionsModal() {
		const $modal = jQuery( '#aie-updater-functions-modal' );
		if ( ! $modal.length ) return;

		// Close modal
		$modal.find( '.aie-modal-close' ).on( 'click', () => {
			this.closeFieldFunctionsModal();
		} );

		$modal.find( '.aie-modal-cancel' ).on( 'click', () => {
			this.closeFieldFunctionsModal();
		} );

		// Save functions
		$modal.find( '.aie-save-updater-functions' ).on( 'click', () => {
			this.saveFieldFunctions();
		} );

		// Test pipeline
		$modal.find( '.aie-test-updater-pipeline' ).on( 'click', () => {
			this.testFunctionPipeline();
		} );

		// Functions search
		$modal.find( '#aie-updater-functions-search' ).on( 'input', ( e ) => {
			this.filterFunctions( e.target.value );
		} );

		// Functions filter
		$modal.find( 'input[name="updater-functions-filter"]' ).on( 'change', ( e ) => {
			this.filterFunctionsByCategory( e.target.value );
		} );

		// Create new function button
		$modal.find( '.aie-create-new-function' ).on( 'click', ( e ) => {
			e.preventDefault();
			this.createNewFunction();
		} );
	},

	/**
	 * Show specific step
	 */
	showStep( step ) {
		const $wizard = jQuery( '#wp-aie-content-updater' );

		// Hide all steps
		$wizard.find( '.aie-step' ).removeClass( 'active' );

		// Show target step
		$wizard.find( `.aie-updater-step-${ step }` ).addClass( 'active' );

		// Update indicator
		$wizard.find( '.aie-step-indicator' ).removeClass( 'active completed' );
		$wizard.find( `.aie-step-indicator[data-step="${ step }"]` ).addClass( 'active' );
		
		// Mark previous steps as completed
		for ( let i = 1; i < step; i++ ) {
			$wizard.find( `.aie-step-indicator[data-step="${ i }"]` ).addClass( 'completed' );
		}

		this.currentStep = step;

		// Step-specific actions
		switch ( step ) {
			case 2:
				this.loadFieldsLibrary();
				break;
			case 3:
				this.buildFunctionsTable();
				break;
			case 4:
				this.prepareUpdateSummary();
				break;
		}
	},

	/**
	 * Navigate to next step
	 */
	nextStep() {
		if ( ! this.validateCurrentStep() ) {
			return;
		}

		if ( this.currentStep < this.totalSteps ) {
			this.showStep( this.currentStep + 1 );
		}
	},

	/**
	 * Navigate to previous step
	 */
	prevStep() {
		if ( this.currentStep > 1 ) {
			this.showStep( this.currentStep - 1 );
		}
	},

	/**
	 * Validate current step before moving to next
	 */
	validateCurrentStep() {
		switch ( this.currentStep ) {
			case 1:
				// Content type must be selected
				if ( ! jQuery( 'input[name="updater_content_type"]:checked' ).length ) {
					Utils.showNotice( 'error', 'Please select a content type' );
					return false;
				}
				return true;

			case 2:
				// At least one field must be selected
				if ( this.selectedFields.length === 0 ) {
					Utils.showNotice( 'error', 'Please select at least one field to update' );
					return false;
				}
				return true;

			case 3:
				// At least one function must be assigned
				const hasFunction = Object.values( this.fieldFunctions ).some( functions => {
					return Array.isArray( functions ) && functions.length > 0;
				} );
				if ( ! hasFunction ) {
					Utils.showNotice( 'error', 'Please assign at least one function to a field' );
					return false;
				}
				return true;

			default:
				return true;
		}
	},

	/**
	 * Filter content types by search
	 */
	filterContentTypes( e ) {
		const searchTerm = jQuery( e.target ).val().toLowerCase();
		const $contentTypes = jQuery( '.aie-content-type' );
		let visibleCount = 0;

		$contentTypes.each( function() {
			const $card = jQuery( this );
			const text = $card.find( 'h3' ).text().toLowerCase() + 
			             $card.find( 'p' ).text().toLowerCase();

			if ( text.includes( searchTerm ) ) {
				$card.show();
				visibleCount++;
			} else {
				$card.hide();
			}
		} );

		// Show/hide no results message
		if ( visibleCount === 0 ) {
			jQuery( '.aie-no-results' ).show();
		} else {
			jQuery( '.aie-no-results' ).hide();
		}
	},

	/**
	 * Handle content type change
	 */
	onContentTypeChange( e ) {
		const contentType = jQuery( e.target ).val();
		console.log( 'Content type changed:', contentType );
		
		// Reset selections for new content type
		this.selectedFields = [];
		this.fieldFunctions = {};
	},

	/**
	 * Load fields library for selected content type
	 */
	loadFieldsLibrary() {
		console.log( 'loadFieldsLibrary called' );
		
		// Get selected content type
		const contentType = jQuery( 'input[name="updater_content_type"]:checked' ).val();
		if ( ! contentType ) {
			console.error( 'No content type selected' );
			return;
		}
		
		console.log( 'Loading fields for content type:', contentType );
		
		// Load static fields based on content type
		this.loadStaticFields( contentType );
		
		// Load taxonomies for this content type (for posts)
		if ( contentType === 'post' || contentType.startsWith( 'post_type_' ) ) {
			const postType = contentType === 'post' ? 'post' : contentType.replace( 'post_type_', '' );
			this.loadTaxonomies( postType );
			this.loadCustomFields( postType );
			this.checkAndLoadACF( postType );
			this.checkAndLoadYoast( postType );
		}
	},

	/**
	 * Load static fields based on content type
	 */
	loadStaticFields( contentType ) {
		// Get field definitions from export module
		if ( typeof window.aieExportModule === 'undefined' || ! window.aieExportModule.getFieldsByContentType ) {
			console.error( 'Export module not found or getFieldsByContentType method missing' );
			return;
		}
		
		const fieldGroups = window.aieExportModule.getFieldsByContentType( contentType );
		console.log( 'Loading static fields for content type:', contentType, fieldGroups );
		
		// Find the container
		const $library = jQuery( '#aie-updater-fields-library' );
		if ( ! $library.length ) {
			console.error( 'Fields library container not found' );
			return;
		}
		
		// Clear existing content
		$library.empty();
		
		// Add container for fields
		$library.append( '<div class="aie-fields-library-body"></div>' );
		const $body = $library.find( '.aie-fields-library-body' );
		
		// Render each field group as a category
		fieldGroups.forEach( ( group, index ) => {
			// Skip Custom Filters group and selector groups
			if ( group.label === 'Custom Filters' || 
				group.label === 'Post Type Selection' || 
				group.label === 'Taxonomy Selection' ) {
				return;
			}
			
			const $category = this.createFieldCategory( group, index === 0 );
			$body.append( $category );
		} );
		
		// Add placeholder categories for dynamic fields
		$body.append( `
			<div class="aie-field-category aie-taxonomies-category" style="display: none;">
				<h4 class="aie-field-category-title">
					<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
					<span class="dashicons dashicons-category"></span>
					Taxonomies
				</h4>
				<div class="aie-fields-grid aie-taxonomies-grid"></div>
			</div>
			<div class="aie-field-category aie-custom-fields-category" style="display: none;">
				<h4 class="aie-field-category-title">
					<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
					<span class="dashicons dashicons-admin-generic"></span>
					Custom Fields
				</h4>
				<div class="aie-fields-grid aie-custom-fields-grid"></div>
			</div>
			<div class="aie-field-category aie-acf-fields-category" style="display: none;">
				<h4 class="aie-field-category-title">
					<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
					<span class="dashicons dashicons-admin-settings"></span>
					ACF Fields
				</h4>
				<div class="aie-fields-grid aie-acf-fields-grid">
					<div class="aie-acf-loading"><span class="spinner is-active"></span><p>Loading ACF fields...</p></div>
				</div>
			</div>
			<div class="aie-field-category aie-yoast-fields-category" style="display: none;">
				<h4 class="aie-field-category-title">
					<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
					<span class="dashicons dashicons-chart-line"></span>
					Yoast SEO
				</h4>
				<div class="aie-fields-grid aie-yoast-fields-grid">
					<div class="aie-yoast-loading"><span class="spinner is-active"></span><p>Loading Yoast SEO fields...</p></div>
				</div>
			</div>
		` );
		
		// Setup drag and drop
		this.setupFieldsDragAndDrop();
		
		// Setup category toggle
		this.setupCategoryToggle();
	},

	/**
	 * Create a field category element
	 */
	createFieldCategory( group, isOpen = false ) {
		const $category = jQuery( '<div>' ).addClass( 'aie-field-category' );
		if ( ! isOpen ) {
			$category.addClass( 'aie-collapsed' );
		}
		
		const $title = jQuery( '<h4>' ).addClass( 'aie-field-category-title' ).html( `
			<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
			<span class="dashicons dashicons-admin-post"></span>
			${ this.escapeHtml( group.label ) }
		` );
		
		const $grid = jQuery( '<div>' ).addClass( 'aie-fields-grid' );
		
		// Add fields
		if ( group.options && Array.isArray( group.options ) ) {
			group.options.forEach( option => {
				// Skip special filter types
				if ( option.type === 'custom_field' || option.type === 'taxonomy_filter' || 
					option.type === 'post_type_selector' || option.type === 'taxonomy_selector' || 
					option.type === 'table_selector' ) {
					return;
				}
				
				const $field = this.createFieldItem( option );
				$grid.append( $field );
			} );
		}
		
		$category.append( $title ).append( $grid );
		return $category;
	},

	/**
	 * Create a field item element
	 */
	createFieldItem( option ) {
		const iconClass = this.getFieldIcon( option.type );
		
		const $item = jQuery( '<div>' )
			.addClass( 'aie-field-item' )
			.attr( 'draggable', true )
			.attr( 'data-field', option.value )
			.attr( 'data-label', option.label )
			.attr( 'data-type', option.type || 'text' )
			.html( `
				<span class="aie-field-icon dashicons ${ iconClass }"></span>
				<span class="aie-field-label">${ this.escapeHtml( option.label ) }</span>
				<span class="aie-field-type">${ this.escapeHtml( option.type || 'text' ) }</span>
			` );
		
		return $item;
	},

	/**
	 * Get field icon based on type
	 */
	getFieldIcon( type ) {
		const iconMap = {
			'text': 'dashicons-text',
			'number': 'dashicons-calculator',
			'date': 'dashicons-calendar-alt',
			'email': 'dashicons-email',
			'url': 'dashicons-admin-links',
			'textarea': 'dashicons-text',
			'select': 'dashicons-menu',
			'checkbox': 'dashicons-yes',
			'radio': 'dashicons-marker',
			'taxonomy': 'dashicons-category',
			'meta': 'dashicons-admin-generic',
			'acf': 'dashicons-admin-settings',
			'yoast': 'dashicons-chart-line'
		};
		
		return iconMap[ type ] || 'dashicons-admin-generic';
	},

	/**
	 * Setup category toggle
	 */
	setupCategoryToggle() {
		jQuery( document ).off( 'click.categoryToggle', '.aie-field-category-title' );
		jQuery( document ).on( 'click.categoryToggle', '.aie-field-category-title', function( e ) {
			// Don't toggle if clicking on "Add all" button
			if ( jQuery( e.target ).closest( '.aie-add-all-fields' ).length ) {
				return;
			}
			
			jQuery( this ).closest( '.aie-field-category' ).toggleClass( 'aie-collapsed' );
		} );
	},

	/**
	 * Load taxonomies for selected post type
	 */
	loadTaxonomies( postType ) {
		if ( typeof aieData === 'undefined' ) return;
		
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_taxonomies',
				nonce: aieData.nonce,
				post_type: postType
			},
			success: ( response ) => {
				console.log( 'Taxonomies response:', response );
				if ( response.success && response.data.taxonomies && response.data.taxonomies.length > 0 ) {
					this.renderTaxonomies( response.data.taxonomies );
					jQuery( '.aie-taxonomies-category' ).show();
				} else {
					jQuery( '.aie-taxonomies-category' ).hide();
				}
			},
			error: ( xhr, status, error ) => {
				console.error( 'Taxonomies AJAX error:', error, xhr.responseText );
			}
		} );
	},

	/**
	 * Render taxonomies
	 */
	renderTaxonomies( taxonomies ) {
		const $grid = jQuery( '.aie-taxonomies-grid' );
		if ( ! $grid.length ) return;
		
		$grid.empty();
		
		taxonomies.forEach( taxonomy => {
			const $item = jQuery( '<div>' )
				.addClass( 'aie-field-item' )
				.attr( 'draggable', true )
				.attr( 'data-field', 'taxonomy_' + taxonomy.name )
				.attr( 'data-label', taxonomy.label )
				.attr( 'data-type', 'taxonomy' )
				.html( `
					<span class="aie-field-icon dashicons dashicons-category"></span>
					<span class="aie-field-label">${ this.escapeHtml( taxonomy.label ) }</span>
					<span class="aie-field-type">taxonomy</span>
				` );
			
			$grid.append( $item );
		} );
	},

	/**
	 * Load custom fields for selected post type
	 */
	loadCustomFields( postType ) {
		if ( typeof aieData === 'undefined' ) return;
		
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_custom_fields',
				nonce: aieData.nonce,
				post_type: postType
			},
			success: ( response ) => {
				console.log( 'Custom fields response:', response );
				if ( response.success && response.data.fields && response.data.fields.length > 0 ) {
					this.renderCustomFields( response.data.fields );
					jQuery( '.aie-custom-fields-category' ).show();
				} else {
					jQuery( '.aie-custom-fields-category' ).hide();
				}
			},
			error: ( xhr, status, error ) => {
				console.error( 'Custom fields AJAX error:', error, xhr.responseText );
			}
		} );
	},

	/**
	 * Render custom fields
	 */
	renderCustomFields( fields ) {
		const $grid = jQuery( '.aie-custom-fields-grid' );
		if ( ! $grid.length ) return;
		
		$grid.empty();
		
		fields.forEach( field => {
			const $item = jQuery( '<div>' )
				.addClass( 'aie-field-item' )
				.attr( 'draggable', true )
				.attr( 'data-field', 'meta_' + field.name )
				.attr( 'data-label', field.name )
				.attr( 'data-type', 'meta' )
				.html( `
					<span class="aie-field-icon dashicons dashicons-admin-generic"></span>
					<span class="aie-field-label">${ this.escapeHtml( field.name ) }</span>
					<span class="aie-field-type">meta</span>
				` );
			
			$grid.append( $item );
		} );
	},

	/**
	 * Check if ACF is active and load ACF fields
	 */
	checkAndLoadACF( postType ) {
		if ( typeof aieData === 'undefined' ) return;
		
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_acf_fields',
				nonce: aieData.nonce,
				post_type: postType
			},
			success: ( response ) => {
				console.log( 'ACF fields response:', response );
				if ( response.success && response.data.fields && response.data.fields.length > 0 ) {
					this.renderACFFields( response.data.fields );
					jQuery( '.aie-acf-fields-category' ).show();
				} else {
					jQuery( '.aie-acf-fields-category' ).hide();
				}
			},
			error: ( xhr, status, error ) => {
				console.error( 'ACF fields AJAX error:', error, xhr.responseText );
			}
		} );
	},

	/**
	 * Render ACF fields
	 */
	renderACFFields( fields ) {
		const $grid = jQuery( '.aie-acf-fields-grid' );
		if ( ! $grid.length ) return;
		
		$grid.empty();
		
		fields.forEach( field => {
			const $item = jQuery( '<div>' )
				.addClass( 'aie-field-item' )
				.attr( 'draggable', true )
				.attr( 'data-field', 'acf_' + field.name )
				.attr( 'data-label', field.label )
				.attr( 'data-type', 'acf' )
				.html( `
					<span class="aie-field-icon dashicons dashicons-admin-settings"></span>
					<span class="aie-field-label">${ this.escapeHtml( field.label ) }</span>
					<span class="aie-field-type">acf</span>
				` );
			
			$grid.append( $item );
		} );
	},

	/**
	 * Check if Yoast is active and load Yoast fields
	 */
	checkAndLoadYoast( postType ) {
		if ( typeof aieData === 'undefined' ) return;
		
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_yoast_fields',
				nonce: aieData.nonce,
				post_type: postType
			},
			success: ( response ) => {
				console.log( 'Yoast fields response:', response );
				if ( response.success && response.data.fields && response.data.fields.length > 0 ) {
					this.renderYoastFields( response.data.fields );
					jQuery( '.aie-yoast-fields-category' ).show();
				} else {
					jQuery( '.aie-yoast-fields-category' ).hide();
				}
			},
			error: ( xhr, status, error ) => {
				console.error( 'Yoast fields AJAX error:', error, xhr.responseText );
			}
		} );
	},

	/**
	 * Render Yoast fields
	 */
	renderYoastFields( fields ) {
		const $grid = jQuery( '.aie-yoast-fields-grid' );
		if ( ! $grid.length ) return;
		
		$grid.empty();
		
		fields.forEach( field => {
			const $item = jQuery( '<div>' )
				.addClass( 'aie-field-item' )
				.attr( 'draggable', true )
				.attr( 'data-field', 'yoast_' + field.name )
				.attr( 'data-label', field.label )
				.attr( 'data-type', 'yoast' )
				.html( `
					<span class="aie-field-icon dashicons dashicons-chart-line"></span>
					<span class="aie-field-label">${ this.escapeHtml( field.label ) }</span>
					<span class="aie-field-type">yoast</span>
				` );
			
			$grid.append( $item );
		} );
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
			"'": '&#039;'
		};
		return String( text ).replace( /[&<>"']/g, m => map[ m ] );
	},

	/**
	 * Setup drag and drop for fields
	 */
	setupDragAndDrop() {
		// Will be implemented with field selection
	},

	/**
	 * Setup drag and drop handlers for field items
	 */
	setupFieldsDragAndDrop() {
		const $items = jQuery( '.aie-field-item' );
		const $dropzone = jQuery( '#aie-updater-dropzone' );

		$items.on( 'dragstart', ( e ) => {
			const field = jQuery( e.currentTarget ).data( 'field' );
			e.originalEvent.dataTransfer.setData( 'field', field );
			e.originalEvent.dataTransfer.setData( 'label', jQuery( e.currentTarget ).find( '.aie-field-label' ).text() );
		} );

		$items.on( 'click', ( e ) => {
			const $item = jQuery( e.currentTarget );
			this.addField( $item.data( 'field' ), $item.find( '.aie-field-label' ).text() );
		} );

		$dropzone.on( 'dragover', ( e ) => {
			e.preventDefault();
			$dropzone.addClass( 'aie-drag-over' );
		} );

		$dropzone.on( 'dragleave', () => {
			$dropzone.removeClass( 'aie-drag-over' );
		} );

		$dropzone.on( 'drop', ( e ) => {
			e.preventDefault();
			$dropzone.removeClass( 'aie-drag-over' );

			const field = e.originalEvent.dataTransfer.getData( 'field' );
			const label = e.originalEvent.dataTransfer.getData( 'label' );

			if ( field ) {
				this.addField( field, label );
			}
		} );
	},

	/**
	 * Add field to selected fields list
	 */
	addField( field, label ) {
		// Check if already added
		if ( this.selectedFields.includes( field ) ) {
			Utils.showNotice( 'warning', `Field "${ label }" is already selected` );
			return;
		}

		this.selectedFields.push( field );
		
		const $list = jQuery( '#aie-updater-fields-list' );
		const $placeholder = jQuery( '.aie-updater-dropzone-placeholder' );

		$placeholder.hide();

		const fieldHtml = `
			<div class="aie-selected-field" data-field="${ field }">
				<span class="aie-field-drag-handle dashicons dashicons-menu"></span>
				<span class="aie-field-name">${ label }</span>
				<button type="button" class="aie-remove-field" title="Remove">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		`;

		$list.append( fieldHtml );
		this.updateFieldCount();

		// Bind remove handler
		$list.find( '.aie-remove-field' ).last().on( 'click', ( e ) => {
			this.removeField( jQuery( e.currentTarget ).closest( '.aie-selected-field' ).data( 'field' ) );
		} );
	},

	/**
	 * Remove field from selected fields
	 */
	removeField( field ) {
		const index = this.selectedFields.indexOf( field );
		if ( index > -1 ) {
			this.selectedFields.splice( index, 1 );
			delete this.fieldFunctions[ field ];
		}

		jQuery( `.aie-selected-field[data-field="${ field }"]` ).remove();
		this.updateFieldCount();

		if ( this.selectedFields.length === 0 ) {
			jQuery( '.aie-updater-dropzone-placeholder' ).show();
		}
	},

	/**
	 * Clear all selected fields
	 */
	clearAllFields() {
		if ( ! confirm( 'Are you sure you want to clear all selected fields?' ) ) {
			return;
		}

		this.selectedFields = [];
		this.fieldFunctions = {};
		jQuery( '#aie-updater-fields-list' ).empty();
		jQuery( '.aie-updater-dropzone-placeholder' ).show();
		this.updateFieldCount();
	},

	/**
	 * Update field count display
	 */
	updateFieldCount() {
		jQuery( '.aie-fields-count' ).text( this.selectedFields.length );
	},

	/**
	 * Filter fields in library
	 */
	filterFields( e ) {
		const searchTerm = jQuery( e.target ).val().toLowerCase();
		const $fields = jQuery( '.aie-field-item' );

		$fields.each( function() {
			const $field = jQuery( this );
			const label = $field.find( '.aie-field-label' ).text().toLowerCase();

			if ( label.includes( searchTerm ) ) {
				$field.show();
			} else {
				$field.hide();
			}
		} );
	},

	/**
	 * Load available functions from server
	 */
	loadAvailableFunctions() {
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_functions_get_all',
				nonce: aieData.nonce
			},
			success: ( response ) => {
				if ( response.success ) {
					this.availableFunctions = response.data.functions || [];
				}
			}
		} );
	},

	/**
	 * Build functions assignment table
	 */
	buildFunctionsTable() {
		const $tbody = jQuery( '#aie-updater-functions-tbody' );
		$tbody.empty();

		if ( this.selectedFields.length === 0 ) {
			$tbody.html( `
				<tr class="aie-no-fields-row">
					<td colspan="4" class="aie-no-fields-message">
						<span class="dashicons dashicons-info"></span>
						No fields selected. Please go back and select fields first.
					</td>
				</tr>
			` );
			return;
		}

		this.selectedFields.forEach( ( field, index ) => {
			const functions = this.fieldFunctions[ field ] || [];
			const functionsCount = Array.isArray( functions ) ? functions.length : 0;
			const fieldLabel = jQuery( `.aie-selected-field[data-field="${ field }"] .aie-field-name` ).text() || field;

			let functionsText = 'None';
			if ( functionsCount > 0 ) {
				functionsText = `${ functionsCount } function${ functionsCount > 1 ? 's' : '' }`;
			}

			let html = `
				<tr data-field="${ field }">
					<td class="aie-field-name-col">
						<strong>${ this.escapeHtml( fieldLabel ) }</strong>
						<br><code>${ this.escapeHtml( field ) }</code>
					</td>
					<td class="aie-field-type-col">
						<span class="aie-field-type-badge">Text</span>
					</td>
					<td class="aie-functions-col">
						<span class="aie-functions-count-badge">${ functionsText }</span>
					</td>
					<td class="aie-actions-col">
						<button type="button" class="button button-small aie-assign-functions" data-field="${ field }">
							<span class="dashicons dashicons-admin-generic"></span>
							Assign Functions
						</button>
					</td>
				</tr>
			`;

			$tbody.append( html );
		} );

		// Bind assign functions button
		$tbody.find( '.aie-assign-functions' ).on( 'click', ( e ) => {
			const field = jQuery( e.currentTarget ).data( 'field' );
			this.openFieldFunctionsModal( field );
		} );

		this.updateFunctionStats();
	},

	/**
	 * Open field functions modal
	 */
	openFieldFunctionsModal( fieldKey ) {
		const fieldLabel = jQuery( `.aie-selected-field[data-field="${ fieldKey }"] .aie-field-name` ).text() || fieldKey;
		const fieldType = 'text'; // Can be enhanced later
		
		this.currentEditingField = fieldKey;

		const $modal = jQuery( '#aie-updater-functions-modal' );
		if ( ! $modal.length ) {
			console.error( 'Functions modal not found' );
			return;
		}

		// Set field info
		$modal.find( '.aie-current-field-label' ).text( fieldLabel );
		$modal.find( '.aie-current-field-type' ).text( fieldType );

		// Load current functions
		this.loadCurrentFunctions( fieldKey );

		// Load available functions
		this.renderAvailableFunctions();

		// Show modal
		$modal.css( 'display', 'flex' );
		jQuery( 'body' ).addClass( 'aie-modal-open' );

		// Initialize sortable if not already done
		this.initFunctionPipelineSortable();
	},

	/**
	 * Close field functions modal
	 */
	closeFieldFunctionsModal() {
		const $modal = jQuery( '#aie-updater-functions-modal' );
		if ( $modal.length ) {
			$modal.css( 'display', 'none' );
			jQuery( 'body' ).removeClass( 'aie-modal-open' );
			
			// Hide preview results
			const $previewResult = $modal.find( '#aie-updater-preview-result' );
			if ( $previewResult.length ) {
				$previewResult.css( 'display', 'none' );
			}
			
			// Clear preview input
			const $previewInput = $modal.find( '#aie-updater-preview-input' );
			if ( $previewInput.length ) {
				$previewInput.val( '' );
			}
		}
		this.currentEditingField = null;
	},

	/**
	 * Load current functions for field
	 */
	loadCurrentFunctions( fieldKey ) {
		const $container = jQuery( '#aie-updater-function-items' );
		if ( ! $container.length ) return;

		$container.empty();

		const functions = this.fieldFunctions[ fieldKey ] || [];
		const $noFunctionsEl = jQuery( '.aie-no-functions' );

		if ( ! Array.isArray( functions ) || functions.length === 0 ) {
			if ( $noFunctionsEl.length ) $noFunctionsEl.show();
			this.updateFunctionsCount( 0 );
			return;
		}

		if ( $noFunctionsEl.length ) $noFunctionsEl.hide();

		functions.forEach( funcId => {
			const func = this.availableFunctions.find( f => f.id == funcId );
			if ( func ) {
				this.addFunctionToPipeline( func, false );
			}
		} );

		this.updateFunctionsCount( functions.length );
	},

	/**
	 * Add function to pipeline
	 */
	addFunctionToPipeline( func, updateArray = true ) {
		const $container = jQuery( '#aie-updater-function-items' );
		if ( ! $container.length ) return;

		const $item = jQuery( '<div>' )
			.addClass( 'aie-function-item' )
			.attr( 'data-function-id', func.id )
			.html( `
				<span class="aie-function-handle dashicons dashicons-menu"></span>
				<div class="aie-function-info">
					<strong class="aie-function-name">${ this.escapeHtml( func.name ) }</strong>
					<span class="aie-function-desc">${ this.escapeHtml( func.description || '' ) }</span>
				</div>
				<div class="aie-function-actions">
					<button type="button" class="button-small aie-remove-function" data-function-id="${ func.id }">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			` );

		// Remove function event
		$item.find( '.aie-remove-function' ).on( 'click', () => {
			$item.remove();
			this.updatePipelineFunctions();
			this.updateFunctionsCount();
			this.toggleNoFunctionsMessage();
		} );

		$container.append( $item );

		if ( updateArray ) {
			this.updatePipelineFunctions();
			this.updateFunctionsCount();
		}

		this.toggleNoFunctionsMessage();
	},

	/**
	 * Update pipeline functions array
	 */
	updatePipelineFunctions() {
		if ( ! this.currentEditingField ) return;

		const functionIds = [];
		jQuery( '.aie-function-item' ).each( function() {
			functionIds.push( jQuery( this ).data( 'function-id' ) );
		} );

		this.fieldFunctions[ this.currentEditingField ] = functionIds;
	},

	/**
	 * Update functions count
	 */
	updateFunctionsCount( count = null ) {
		const $countEl = jQuery( '.aie-functions-count' );
		if ( ! $countEl.length ) return;

		if ( count === null ) {
			count = jQuery( '.aie-function-item' ).length;
		}

		$countEl.text( `(${ count })` );
	},

	/**
	 * Toggle no functions message
	 */
	toggleNoFunctionsMessage() {
		const $noFunctionsEl = jQuery( '.aie-no-functions' );
		const count = jQuery( '.aie-function-item' ).length;

		if ( count === 0 ) {
			$noFunctionsEl.show();
		} else {
			$noFunctionsEl.hide();
		}
	},

	/**
	 * Render available functions
	 */
	renderAvailableFunctions() {
		const $list = jQuery( '#aie-updater-functions-list' );
		if ( ! $list.length ) return;

		$list.empty();

		if ( this.availableFunctions.length === 0 ) {
			$list.html( `
				<div class="aie-no-functions-available">
					<span class="dashicons dashicons-info"></span>
					<p>No functions available. Create a custom function first.</p>
				</div>
			` );
			return;
		}

		this.availableFunctions.forEach( func => {
			const $funcItem = jQuery( '<div>' )
				.addClass( 'aie-function-available' )
				.attr( 'data-function-id', func.id )
				.attr( 'data-category', func.type || 'custom' )
				.html( `
					<div class="aie-function-info">
						<strong class="aie-function-name">${ this.escapeHtml( func.name ) }</strong>
						<span class="aie-function-desc">${ this.escapeHtml( func.description || '' ) }</span>
					</div>
					<button type="button" class="button-small aie-add-function-btn" data-function-id="${ func.id }">
						<span class="dashicons dashicons-plus-alt"></span>
						Add
					</button>
				` );

			// Add function event
			$funcItem.find( '.aie-add-function-btn' ).on( 'click', () => {
				this.addFunctionToPipeline( func, true );
			} );

			$list.append( $funcItem );
		} );
	},

	/**
	 * Initialize function pipeline sortable
	 */
	initFunctionPipelineSortable() {
		const $container = jQuery( '#aie-updater-function-items' );
		if ( ! $container.length ) return;

		// Check if already initialized
		if ( $container.data( 'ui-sortable' ) ) {
			return;
		}

		$container.sortable( {
			handle: '.aie-function-handle',
			placeholder: 'aie-function-item-placeholder',
			axis: 'y',
			update: () => {
				this.updatePipelineFunctions();
			}
		} );
	},

	/**
	 * Save field functions
	 */
	saveFieldFunctions() {
		this.updatePipelineFunctions();
		this.closeFieldFunctionsModal();
		this.buildFunctionsTable(); // Rebuild table to show updated function counts
	},

	/**
	 * Handle function selection change
	 */
	onFunctionChange( e ) {
		const $select = jQuery( e.target );
		const field = $select.data( 'field' );
		const functionId = $select.val();

		this.fieldFunctions[ field ] = functionId;
		this.updateFunctionStats();
	},

	/**
	 * Update function statistics
	 */
	updateFunctionStats() {
		const total = this.selectedFields.length;
		let assigned = 0;
		let noFunction = 0;

		Object.values( this.fieldFunctions ).forEach( functions => {
			if ( Array.isArray( functions ) && functions.length > 0 ) {
				assigned++;
			} else {
				noFunction++;
			}
		} );

		noFunction = total - assigned;

		jQuery( '.aie-total-fields-stat' ).text( total );
		jQuery( '.aie-functions-assigned-stat' ).text( assigned );
		jQuery( '.aie-no-function-stat' ).text( noFunction );
	},

	/**
	 * Apply function to all fields
	 */
	applyFunctionToAll() {
		// Show a dialog to select the function
		const functionId = prompt( 'Enter function ID to apply to all fields (or leave empty for none):' );
		
		if ( functionId === null ) {
			return; // Cancelled
		}

		const finalId = functionId.trim() || 'none';

		this.selectedFields.forEach( field => {
			this.fieldFunctions[ field ] = finalId;
			jQuery( `.aie-field-function-select[data-field="${ field }"]` ).val( finalId );
		} );

		this.updateFunctionStats();
		Utils.showNotice( 'success', 'Function applied to all fields' );
	},

	/**
	 * Clear all function assignments
	 */
	clearAllFunctions() {
		if ( ! confirm( 'Are you sure you want to clear all function assignments?' ) ) {
			return;
		}

		this.selectedFields.forEach( field => {
			this.fieldFunctions[ field ] = 'none';
			jQuery( `.aie-field-function-select[data-field="${ field }"]` ).val( 'none' );
		} );

		this.updateFunctionStats();
		Utils.showNotice( 'success', 'All function assignments cleared' );
	},

	/**
	 * Test function with sample input
	 */
	testFunction( e ) {
		const field = jQuery( e.currentTarget ).data( 'field' );
		const functionId = this.fieldFunctions[ field ];

		if ( ! functionId || functionId === 'none' ) {
			Utils.showNotice( 'warning', 'No function assigned to this field' );
			return;
		}

		// Show preview popup (simplified for now)
		const testValue = prompt( 'Enter a test value:' );
		if ( testValue === null ) {
			return;
		}

		// Test the function
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_functions_execute',
				nonce: aieData.nonce,
				function_id: functionId,
				input_value: testValue
			},
			success: ( response ) => {
				if ( response.success ) {
					alert( `Output: ${ response.data.output }` );
				} else {
					Utils.showNotice( 'error', response.data.message || 'Function test failed' );
				}
			}
		} );
	},

	/**
	 * Prepare update summary
	 */
	prepareUpdateSummary() {
		const contentType = jQuery( 'input[name="updater_content_type"]:checked' ).val();
		const contentTypeLabel = jQuery( 'input[name="updater_content_type"]:checked' )
			.closest( '.aie-content-type' )
			.find( 'h3' )
			.text();

		// Update summary display
		jQuery( '.aie-content-type-summary' ).text( contentTypeLabel );
		jQuery( '.aie-fields-summary' ).text( this.selectedFields.length );
		
		const functionsCount = Object.values( this.fieldFunctions ).filter( fn => fn && fn !== 'none' ).length;
		jQuery( '.aie-functions-summary' ).text( functionsCount );

		// Get count of items
		this.getItemCount();
	},

	/**
	 * Get count of items to update
	 */
	getItemCount() {
		const contentType = jQuery( 'input[name="updater_content_type"]:checked' ).val();
		const $countValue = jQuery( '.aie-total-items-summary' );

		$countValue.html( '<span class="spinner" style="float:none;margin:0;"></span>' );

		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_updater_get_count',
				nonce: aieData.nonce,
				content_type: contentType,
				options: {}
			},
			success: ( response ) => {
				if ( response.success ) {
					$countValue.text( response.data.count );
				} else {
					$countValue.text( 'Error' );
				}
			},
			error: () => {
				$countValue.text( 'Error' );
			}
		} );
	},

	/**
	 * Start update process
	 */
	startUpdate() {
		const contentType = jQuery( 'input[name="updater_content_type"]:checked' ).val();
		const itemsPerIteration = parseInt( jQuery( '#aie-updater-items-per-iteration' ).val() ) || 10;
		const dryRun = jQuery( '#aie-updater-dry-run' ).is( ':checked' );

		// Prepare field functions array (indexed by field position)
		const fieldFunctionsArray = this.selectedFields.map( field => this.fieldFunctions[ field ] || 'none' );

		// Show progress section
		jQuery( '#aie-updater-config' ).hide();
		jQuery( '#aie-updater-progress' ).show();
		jQuery( '#aie-updater-prev-from-step-4' ).hide();

		// Start update job
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_updater_start',
				nonce: aieData.nonce,
				content_type: contentType,
				fields: this.selectedFields,
				field_functions: fieldFunctionsArray,
				options: {
					items_per_iteration: itemsPerIteration,
					dry_run: dryRun
				}
			},
			success: ( response ) => {
				if ( response.success ) {
					this.jobId = response.data.job_id;
					Utils.showNotice( 'success', 'Update started successfully' );
					
					// Start processing
					this.startProgressTracking();
					this.processNextBatch();
				} else {
					Utils.showNotice( 'error', response.data.message || 'Failed to start update' );
					this.showResults( 'error' );
				}
			},
			error: () => {
				Utils.showNotice( 'error', 'Failed to start update' );
				this.showResults( 'error' );
			}
		} );
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
	 * Update progress display
	 */
	updateProgress() {
		return jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_updater_get_progress',
				nonce: aieData.nonce,
				job_id: this.jobId
			},
			success: ( response ) => {
				if ( response.success ) {
					const progress = response.data;

					// Update progress bar
					jQuery( '.aie-progress-bar-fill' ).css( 'width', progress.percentage + '%' );
					jQuery( '.aie-progress-percentage' ).text( progress.percentage + '%' );

					// Update stats
					jQuery( '.aie-processed-count' ).text( progress.processed_items );
					jQuery( '.aie-total-count' ).text( progress.total_items );
					jQuery( '.aie-updated-count' ).text( progress.updated_items );
					jQuery( '.aie-skipped-count' ).text( progress.skipped_items );
					jQuery( '.aie-errors-count' ).text( progress.error_items );

					// Update status
					jQuery( '.aie-status-text' ).text( `Processing items... (${ progress.processed_items } / ${ progress.total_items })` );

					// Check if completed
					if ( progress.status === 'completed' || progress.status === 'failed' || progress.status === 'cancelled' ) {
						this.stopProgressTracking();
						this.showResults( progress.status, progress );
					}
				}
			}
		} );
	},

	/**
	 * Process next batch
	 */
	processNextBatch() {
		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_updater_process_batch',
				nonce: aieData.nonce,
				job_id: this.jobId
			},
			success: ( response ) => {
				if ( response.success ) {
					if ( ! response.data.completed ) {
						// Process next batch
						setTimeout( () => this.processNextBatch(), 500 );
					}
				} else {
					console.error( 'Batch processing error:', response.data );
				}
			},
			error: ( xhr ) => {
				console.error( 'AJAX error:', xhr );
			}
		} );
	},

	/**
	 * Stop progress tracking
	 */
	stopProgressTracking() {
		if ( this.progressInterval ) {
			clearInterval( this.progressInterval );
			this.progressInterval = null;
		}
	},

	/**
	 * Cancel update
	 */
	cancelUpdate() {
		if ( ! confirm( 'Are you sure you want to cancel the update?' ) ) {
			return;
		}

		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_updater_cancel',
				nonce: aieData.nonce,
				job_id: this.jobId
			},
			success: ( response ) => {
				if ( response.success ) {
					this.stopProgressTracking();
					Utils.showNotice( 'info', 'Update cancelled' );
					this.showResults( 'cancelled' );
				}
			}
		} );
	},

	/**
	 * Show results
	 */
	showResults( status, progress = {} ) {
		jQuery( '#aie-updater-progress' ).hide();
		jQuery( '#aie-updater-results' ).show();

		// Update final stats
		jQuery( '.aie-final-processed' ).text( progress.processed_items || 0 );
		jQuery( '.aie-final-updated' ).text( progress.updated_items || 0 );
		jQuery( '.aie-final-skipped' ).text( progress.skipped_items || 0 );
		jQuery( '.aie-final-errors' ).text( progress.error_items || 0 );

		// Update icon based on status
		const $icon = jQuery( '.aie-results-header .dashicons' );
		if ( status === 'completed' ) {
			$icon.removeClass( 'dashicons-warning' ).addClass( 'dashicons-yes-alt aie-success-icon' );
		} else {
			$icon.removeClass( 'dashicons-yes-alt' ).addClass( 'dashicons-warning' );
		}
	},

	/**
	 * Start new update
	 */
	startNewUpdate() {
		window.location.reload();
	},

	/**
	 * Filter functions by search term
	 */
	filterFunctions( searchTerm ) {
		const term = searchTerm.toLowerCase();
		jQuery( '.aie-function-available' ).each( function() {
			const $item = jQuery( this );
			const name = $item.find( '.aie-function-name' ).text().toLowerCase();
			const desc = $item.find( '.aie-function-desc' ).text().toLowerCase();
			
			if ( name.includes( term ) || desc.includes( term ) ) {
				$item.show();
			} else {
				$item.hide();
			}
		} );
	},

	/**
	 * Filter functions by category
	 */
	filterFunctionsByCategory( category ) {
		if ( category === 'all' ) {
			jQuery( '.aie-function-available' ).show();
		} else {
			jQuery( '.aie-function-available' ).each( function() {
				const $item = jQuery( this );
				const itemCategory = $item.data( 'category' );
				
				if ( itemCategory === category ) {
					$item.show();
				} else {
					$item.hide();
				}
			} );
		}
	},

	/**
	 * Test function pipeline
	 */
	testFunctionPipeline() {
		const $input = jQuery( '#aie-updater-preview-input' );
		const testValue = $input.val();

		if ( ! testValue ) {
			Utils.showNotice( 'error', 'Please enter a test value' );
			return;
		}

		const functionIds = [];
		jQuery( '.aie-function-item' ).each( function() {
			functionIds.push( jQuery( this ).data( 'function-id' ) );
		} );

		if ( functionIds.length === 0 ) {
			Utils.showNotice( 'error', 'No functions to test' );
			return;
		}

		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_functions_test_pipeline',
				nonce: aieData.nonce,
				functions: functionIds,
				test_value: testValue
			},
			success: ( response ) => {
				if ( response.success ) {
					this.showPipelinePreview( response.data );
				} else {
					Utils.showNotice( 'error', response.data.message || 'Test failed' );
				}
			},
			error: () => {
				Utils.showNotice( 'error', 'Error testing pipeline' );
			}
		} );
	},

	/**
	 * Show pipeline preview
	 */
	showPipelinePreview( data ) {
		const $result = jQuery( '#aie-updater-preview-result' );
		const $steps = $result.find( '.aie-preview-steps' );

		$steps.empty();

		// Add initial value
		$steps.append( `
			<div class="aie-preview-step">
				<div class="aie-preview-step-label">Input</div>
				<div class="aie-preview-step-value">${ this.escapeHtml( data.initial_value ) }</div>
			</div>
		` );

		// Add each transformation step
		if ( data.steps && data.steps.length > 0 ) {
			data.steps.forEach( ( step, index ) => {
				$steps.append( `
					<div class="aie-preview-arrow">
						<span class="dashicons dashicons-arrow-down-alt"></span>
					</div>
					<div class="aie-preview-step">
						<div class="aie-preview-step-label">${ this.escapeHtml( step.function_name ) }</div>
						<div class="aie-preview-step-value">${ this.escapeHtml( step.value ) }</div>
					</div>
				` );
			} );
		}

		// Add final value
		$steps.append( `
			<div class="aie-preview-arrow">
				<span class="dashicons dashicons-arrow-down-alt"></span>
			</div>
			<div class="aie-preview-step aie-preview-final">
				<div class="aie-preview-step-label">Final Output</div>
				<div class="aie-preview-step-value">${ this.escapeHtml( data.final_value ) }</div>
			</div>
		` );

		$result.show();
	},

	/**
	 * Create new function
	 */
	createNewFunction() {
		// Redirect to custom functions page
		window.location.href = aieData.adminUrl + 'admin.php?page=wp-aie-custom-functions';
	}
};

export default ContentUpdater;
