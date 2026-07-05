/**
 * Export Step 3: Field Selection with Drag & Drop
 */

export default class ExportStep3 {
	constructor() {
		this.selectedFields = [];
		this.fieldFunctions = {}; // { fieldKey: [functionId1, functionId2] }
		this.currentEditingField = null;
		this.availableFunctions = [];
		this.isDragging = false;
		this.autoScrollInterval = null;
		this.selectedPostType = null;
		this.dynamicFieldsLoading = false;
		this.dynamicFieldsLoadToken = 0;

		this.init();
	}

	areFieldTransformationsEnabled() {
		return !! window.rslIeData?.fieldTransformationsEnabled;
	}

	getFieldTransformationAction( key ) {
		return window.rslIeData?.fieldTransformationActions?.[ key ] || '';
	}

	init() {
		// Check dependencies
		if ( typeof jQuery === 'undefined' ) {
			return;
		}

		if ( typeof rslIeData === 'undefined' ) {
		}

		this.initDragAndDrop();
		this.initFieldSearch();
		this.initCsvBuilderActions();
		if ( this.areFieldTransformationsEnabled() ) {
			this.initFieldFunctionsModal();
		}
		this.initColumnActions();
		this.initCategoryToggle();

		if ( this.areFieldTransformationsEnabled() ) {
			this.loadFunctions();
		}

		// Initialize tooltip for next button
		this.toggleNextButton();

		// Don't load dynamic fields immediately
		// They will be loaded when step 3 becomes active
		// this.loadDynamicFields();
	}

	/**
	 * Initialize Drag and Drop functionality
	 */
	initDragAndDrop() {
		const dropzone = document.getElementById( 'rsl-ie-csv-dropzone' );
		const columnsContainer =
			document.getElementById( 'rsl-ie-csv-columns' );

		if ( ! dropzone || ! columnsContainer ) return;

		// Make field items draggable
		document.addEventListener( 'dragstart', ( e ) => {
			if ( e.target.classList.contains( 'rsl-ie-field-item' ) ) {
				this.isDragging = true;
				document.body.classList.add( 'rsl-ie-dragging' );
				e.target.classList.add( 'dragging' );
				e.dataTransfer.effectAllowed = 'copy';
				e.dataTransfer.setData(
					'text/plain',
					JSON.stringify( {
						field: e.target.dataset.field,
						label: e.target.dataset.label,
						type: e.target.dataset.type,
					} )
				);
			}

			// Handle column reordering
			if ( e.target.classList.contains( 'rsl-ie-csv-column' ) ) {
				this.isDragging = true;
				document.body.classList.add( 'rsl-ie-dragging' );
				e.target.classList.add( 'dragging' );
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData(
					'application/column-reorder',
					e.target.dataset.fieldKey
				);
			}
		} );

		document.addEventListener( 'dragover', ( e ) => {
			if ( this.isDragging ) {
				this.handleAutoScroll( e );
			}
		} );

		document.addEventListener( 'dragend', ( e ) => {
			if (
				e.target.classList.contains( 'rsl-ie-field-item' ) ||
				e.target.classList.contains( 'rsl-ie-csv-column' )
			) {
				this.isDragging = false;
				document.body.classList.remove( 'rsl-ie-dragging' );
				e.target.classList.remove( 'dragging' );
				this.stopAutoScroll();
			}
		} );

		// Drop zone events
		dropzone.addEventListener( 'dragover', ( e ) => {
			// Only prevent default if we're actually dragging over the dropzone
			// This allows scrolling to continue
			if (
				e.dataTransfer.types.includes( 'text/plain' ) ||
				e.dataTransfer.types.includes( 'application/column-reorder' )
			) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'copy';
				dropzone.classList.add( 'rsl-ie-drag-over' );
			}
		} );

		dropzone.addEventListener( 'dragleave', ( e ) => {
			if ( e.target === dropzone ) {
				dropzone.classList.remove( 'rsl-ie-drag-over' );
			}
		} );

		dropzone.addEventListener( 'drop', ( e ) => {
			// Only prevent default for actual drop events
			const data = e.dataTransfer.getData( 'text/plain' );
			if ( data ) {
				e.preventDefault();
				e.stopPropagation();
				dropzone.classList.remove( 'rsl-ie-drag-over' );

				try {
					const fieldData = JSON.parse( data );
					this.addFieldToCSV( fieldData );
				} catch ( error ) {}
			}
		} );

		// Column reordering
		columnsContainer.addEventListener( 'dragover', ( e ) => {
			const dragging = document.querySelector(
				'.rsl-ie-csv-column.dragging'
			);

			// Only prevent default when actually reordering columns
			if ( dragging ) {
				e.preventDefault();
				const afterElement = this.getDragAfterElement(
					columnsContainer,
					e.clientX
				);

				if ( afterElement == null ) {
					columnsContainer.appendChild( dragging );
				} else {
					columnsContainer.insertBefore( dragging, afterElement );
				}
			}
		} );

		columnsContainer.addEventListener( 'drop', ( e ) => {
			const dragging = document.querySelector(
				'.rsl-ie-csv-column.dragging'
			);
			if ( dragging ) {
				e.preventDefault();
				e.stopPropagation();
				this.updateColumnOrder();
			}
		} );
	}

	/**
	 * Get element after drag position
	 */
	getDragAfterElement( container, x ) {
		const draggableElements = [
			...container.querySelectorAll(
				'.rsl-ie-csv-column:not(.dragging)'
			),
		];

		return draggableElements.reduce(
			( closest, child ) => {
				const box = child.getBoundingClientRect();
				const offset = x - box.left - box.width / 2;

				if ( offset < 0 && offset > closest.offset ) {
					return { offset: offset, element: child };
				} else {
					return closest;
				}
			},
			{ offset: Number.NEGATIVE_INFINITY }
		).element;
	}

	/**
	 * Add field to CSV structure
	 */
	addFieldToCSV( fieldData ) {
		const { field, label, type } = fieldData;
		const fieldKey = `${ field }_${ Date.now() }`;

		// Check if field already exists (prevent duplicates for unique fields)
		const existingField = this.selectedFields.find(
			( f ) => f.field === field
		);
		if ( existingField && field === 'ID' ) {
			this.showNotice(
				window.rslIeData.i18n.fieldAlreadyAdded,
				'warning'
			);
			return;
		}

		// Add to selected fields
		this.selectedFields.push( {
			key: fieldKey,
			field,
			label,
			type,
		} );

		// Render column
		this.renderColumn( fieldKey, field, label, type );

		// Update UI
		this.updateCSVStats();
		this.toggleNextButton();
		this.togglePlaceholder();
	}

	/**
	 * Render CSV column
	 */
	renderColumn( fieldKey, field, label, type ) {
		const columnsContainer =
			document.getElementById( 'rsl-ie-csv-columns' );
		if ( ! columnsContainer ) return;

		const column = document.createElement( 'div' );
		column.className = 'rsl-ie-csv-column';
		column.draggable = true;
		column.dataset.fieldKey = fieldKey;
		column.dataset.field = field;

		const iconClass = this.getFieldIcon( type );
		const hasFunctions =
			this.areFieldTransformationsEnabled() &&
			this.fieldFunctions[ fieldKey ] &&
			this.fieldFunctions[ fieldKey ].length > 0;
		const transformationButton = this.areFieldTransformationsEnabled()
			? `
					<button type="button" class="rsl-ie-edit-column-functions" title="${
						window.rslIeData.i18n.assignFunctionsTitle ||
						window.rslIeData.i18n.assignFunctions ||
						''
					}" data-field-key="${ fieldKey }">
						<span class="dashicons dashicons-admin-generic"></span>
					</button>
			`
			: '';

		column.innerHTML = `
			<div class="rsl-ie-column-header">
				<span class="rsl-ie-column-icon dashicons ${ iconClass }"></span>
				<div class="rsl-ie-column-actions">
					${ transformationButton }
					<button type="button" class="rsl-ie-remove-column" title="${
						window.rslIeData.i18n.remove
					}" data-field-key="${ fieldKey }">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			<div class="rsl-ie-column-label">${ this.escapeHtml( label ) }</div>
			<div class="rsl-ie-column-field">${ this.escapeHtml( field ) }</div>
			${
				hasFunctions
					? `
				<div class="rsl-ie-column-badge">
					<span class="dashicons dashicons-admin-generic"></span>
					${ this.fieldFunctions[ fieldKey ].length } ${ window.rslIeData.i18n.functions }
				</div>
			`
					: ''
			}
		`;

		if ( hasFunctions ) {
			column.classList.add( 'has-functions' );
		}

		columnsContainer.appendChild( column );
	}

	/**
	 * Initialize CSV builder actions
	 */
	initCsvBuilderActions() {
		// Clear all fields
		document
			.querySelector( '.rsl-ie-clear-all-fields' )
			?.addEventListener( 'click', () => {
				if ( confirm( window.rslIeData.i18n.confirmRemoveAllFields ) ) {
					this.clearAllFields();
				}
			} );

		// Add custom column
		document
			.querySelector( '.rsl-ie-add-custom-column' )
			?.addEventListener( 'click', () => {
				this.addCustomColumn();
			} );
	}

	/**
	 * Initialize column actions
	 */
	initColumnActions() {
		document.addEventListener( 'click', ( e ) => {
			// Remove column
			if ( e.target.closest( '.rsl-ie-remove-column' ) ) {
				const btn = e.target.closest( '.rsl-ie-remove-column' );
				const fieldKey = btn.dataset.fieldKey;
				this.removeColumn( fieldKey );
			}

			// Edit column functions
			if ( e.target.closest( '.rsl-ie-edit-column-functions' ) ) {
				if ( ! this.areFieldTransformationsEnabled() ) {
					return;
				}
				const btn = e.target.closest( '.rsl-ie-edit-column-functions' );
				const fieldKey = btn.dataset.fieldKey;
				this.openFieldFunctionsModal( fieldKey );
			}
		} );
	}

	/**
	 * Remove column
	 */
	removeColumn( fieldKey ) {
		// Remove from array
		this.selectedFields = this.selectedFields.filter(
			( f ) => f.key !== fieldKey
		);

		// Remove from DOM
		const column = document.querySelector(
			`[data-field-key="${ fieldKey }"]`
		);
		if ( column ) {
			column.remove();
		}

		// Remove functions
		delete this.fieldFunctions[ fieldKey ];

		// Update UI
		this.updateCSVStats();
		this.toggleNextButton();
		this.togglePlaceholder();
	}

	/**
	 * Clear all fields
	 */
	clearAllFields() {
		this.selectedFields = [];
		this.fieldFunctions = {};

		const columnsContainer =
			document.getElementById( 'rsl-ie-csv-columns' );
		if ( columnsContainer ) {
			columnsContainer.innerHTML = '';
		}

		this.updateCSVStats();
		this.toggleNextButton();
		this.togglePlaceholder();
	}

	/**
	 * Add custom column
	 */
	addCustomColumn() {
		const label = prompt( window.rslIeData.i18n.enterColumnName );
		if ( ! label ) return;

		const field =
			'custom_' + label.toLowerCase().replace( /[^a-z0-9]/g, '_' );

		this.addFieldToCSV( {
			field,
			label,
			type: 'custom',
		} );
	}

	/**
	 * Update column order after drag
	 */
	updateColumnOrder() {
		const columns = document.querySelectorAll( '.rsl-ie-csv-column' );
		const newOrder = [];

		columns.forEach( ( column ) => {
			const fieldKey = column.dataset.fieldKey;
			const field = this.selectedFields.find(
				( f ) => f.key === fieldKey
			);
			if ( field ) {
				newOrder.push( field );
			}
		} );

		this.selectedFields = newOrder;
	}

	/**
	 * Update CSV stats
	 */
	updateCSVStats() {
		const countElement = document.querySelector(
			'.rsl-ie-step-3 .rsl-ie-columns-count'
		);
		if ( countElement ) {
			countElement.textContent = this.selectedFields.length;
		}
	}

	/**
	 * Toggle placeholder visibility
	 */
	togglePlaceholder() {
		const dropzone = document.getElementById( 'rsl-ie-csv-dropzone' );
		if ( dropzone ) {
			if ( this.selectedFields.length > 0 ) {
				dropzone.classList.add( 'has-columns' );
			} else {
				dropzone.classList.remove( 'has-columns' );
			}
		}
	}

	/**
	 * Toggle next button
	 */
	toggleNextButton() {
		const nextBtn = document.querySelector(
			'.rsl-ie-step-3 .rsl-ie-next-step'
		);
		if ( nextBtn ) {
			const $nextBtn = jQuery( nextBtn );
			const isDisabled =
				this.selectedFields.length === 0 || this.dynamicFieldsLoading;

			// Remove previous event handlers
			$nextBtn.off( 'mouseenter.tooltip mouseleave.tooltip' );

			if ( isDisabled ) {
				nextBtn.disabled = true;

				// Show tooltip on hover
				$nextBtn.on( 'mouseenter.tooltip', () => {
					this.showNextButtonTooltip( $nextBtn );
				} );

				// Hide tooltip on mouse leave
				$nextBtn.on( 'mouseleave.tooltip', () => {
					this.hideNextButtonTooltip( $nextBtn );
				} );
			} else {
				nextBtn.disabled = false;
				// Hide tooltip if it's shown
				this.hideNextButtonTooltip( $nextBtn );
			}
		}
	}

	/**
	 * Show custom tooltip on Next button
	 */
	showNextButtonTooltip( $button ) {
		// Remove any existing tooltips
		jQuery( '.rsl-ie-custom-tooltip' ).remove();

		// Create tooltip element
		const $tooltip = jQuery( '<div>' ).addClass(
			'rsl-ie-custom-tooltip rsl-ie-custom-pointer'
		).html( `
				<div class="rsl-ie-pointer-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="rsl-ie-pointer-content">
					<h3>${ window.rslIeData.i18n.noFieldsSelected }</h3>
					<p>${ window.rslIeData.i18n.pleaseSelectFieldMessage }</p>
				</div>
			` );

		// Append to body
		jQuery( 'body' ).append( $tooltip );

		// Position tooltip
		const buttonOffset = $button.offset();
		const buttonWidth = $button.outerWidth();
		const tooltipWidth = $tooltip.outerWidth();
		const tooltipHeight = $tooltip.outerHeight();

		// Position above the button, centered
		const left = buttonOffset.left + buttonWidth / 2 - tooltipWidth / 2;
		const top = buttonOffset.top - tooltipHeight - 10; // 10px gap

		$tooltip.css( {
			left: left + 'px',
			top: top + 'px',
			zIndex: 9999,
		} );

		// Fade in
		setTimeout( () => {
			$tooltip.addClass( 'rsl-ie-tooltip-visible' );
		}, 10 );
	}

	/**
	 * Hide custom tooltip
	 */
	hideNextButtonTooltip( $button ) {
		const $tooltip = jQuery( '.rsl-ie-custom-tooltip' );

		if ( $tooltip.length ) {
			$tooltip.removeClass( 'rsl-ie-tooltip-visible' );

			// Remove after animation
			setTimeout( () => {
				$tooltip.remove();
			}, 200 );
		}
	}

	/**
	 * Initialize field search
	 */
	initFieldSearch() {
		const searchInput = document.getElementById( 'rsl-ie-fields-search' );
		if ( ! searchInput ) return;

		// Search input handler
		searchInput.addEventListener( 'input', ( e ) => {
			const query = e.target.value.toLowerCase();
			this.filterFields( query );
		} );

		// Clear button handler
		const clearBtn = searchInput.parentElement.querySelector(
			'.rsl-ie-clear-search'
		);
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				searchInput.value = '';
				searchInput.focus();
				this.filterFields( '' );
			} );
		}
	}

	/**
	 * Initialize category toggle (collapse/expand)
	 */
	initCategoryToggle() {
		document.addEventListener( 'click', ( e ) => {
			// Handle "Add all" button
			if ( e.target.classList.contains( 'rsl-ie-add-all-fields' ) ) {
				e.stopPropagation();
				this.addAllFieldsFromCategory( e.target );
				return;
			}

			// Handle category toggle (only if not clicking the button)
			const categoryTitle = e.target.closest(
				'.rsl-ie-field-category-title'
			);
			if ( ! categoryTitle ) return;

			// Don't toggle if clicking the "Add all" button
			if ( e.target.classList.contains( 'rsl-ie-add-all-fields' ) )
				return;

			const category = categoryTitle.closest( '.rsl-ie-field-category' );
			if ( category ) {
				category.classList.toggle( 'rsl-ie-collapsed' );
			}
		} );
	}

	/**
	 * Add all fields from a category
	 */
	addAllFieldsFromCategory( button ) {
		const category = button.closest( '.rsl-ie-field-category' );
		if ( ! category ) return;

		const fieldItems = category.querySelectorAll(
			'.rsl-ie-field-item:not([style*="display: none"])'
		);

		if (
			fieldItems.length === 0 &&
			this.isDynamicCategoryLoading( category )
		) {
			category.dataset.pendingAddAll = 'true';
			return;
		}

		delete category.dataset.pendingAddAll;

		fieldItems.forEach( ( item ) => {
			const fieldData = {
				field: item.dataset.field,
				label: item.dataset.label,
				type: item.dataset.type,
			};

			// Check if field is not already added
			const exists = this.selectedFields.find(
				( f ) => f.field === fieldData.field
			);
			if ( ! exists ) {
				this.addFieldToCSV( fieldData );
			}
		} );
	}

	isDynamicCategoryLoading( category ) {
		return !! category.querySelector(
			'.rsl-ie-taxonomies-loading, .rsl-ie-custom-fields-loading, .rsl-ie-acf-loading, .rsl-ie-yoast-loading, .rsl-ie-rank-math-loading, .rsl-ie-elementor-loading'
		);
	}

	flushPendingAddAll( category ) {
		if ( ! category || category.dataset.pendingAddAll !== 'true' ) {
			return;
		}

		const button = category.querySelector( '.rsl-ie-add-all-fields' );
		if ( button ) {
			this.addAllFieldsFromCategory( button );
		}
	}

	/**
	 * Filter fields by search query
	 */
	filterFields( query ) {
		const fieldItems = document.querySelectorAll( '.rsl-ie-field-item' );
		const categories = document.querySelectorAll(
			'.rsl-ie-field-category'
		);

		// If searching, expand all categories
		if ( query.trim() !== '' ) {
			categories.forEach( ( category ) => {
				category.classList.remove( 'rsl-ie-collapsed' );
			} );
		}

		fieldItems.forEach( ( item ) => {
			const label = item.dataset.label.toLowerCase();
			const field = item.dataset.field.toLowerCase();

			if ( label.includes( query ) || field.includes( query ) ) {
				item.style.display = '';
			} else {
				item.style.display = 'none';
			}
		} );

		// Hide empty categories when searching
		if ( query.trim() !== '' ) {
			categories.forEach( ( category ) => {
				const visibleFields = category.querySelectorAll(
					'.rsl-ie-field-item:not([style*="display: none"])'
				);
				if ( visibleFields.length === 0 ) {
					category.style.display = 'none';
				} else {
					category.style.display = '';
				}
			} );
		} else {
			// When clearing search, restore initial visibility state
			categories.forEach( ( category ) => {
				// Check if category has any field items
				const fieldItems =
					category.querySelectorAll( '.rsl-ie-field-item' );

				// If category has no field items (not loaded), keep it hidden
				if ( fieldItems.length === 0 ) {
					category.style.display = 'none';
				} else {
					// If category has items, show it
					category.style.display = '';
				}
			} );
		}
	}

	/**
	 * Render fields for a group
	 */
	renderGroupFields( container, fields ) {
		const loadingEl = container.querySelector(
			'.rsl-ie-acf-loading, .rsl-ie-yoast-loading, .rsl-ie-rank-math-loading, .rsl-ie-elementor-loading, .rsl-ie-meta-loading'
		);
		if ( loadingEl ) {
			loadingEl.remove();
		}

		const category = document.createElement( 'div' );
		category.className = 'rsl-ie-field-category';

		const grid = document.createElement( 'div' );
		grid.className = 'rsl-ie-fields-grid';

		fields.forEach( ( field ) => {
			const item = document.createElement( 'div' );
			item.className = 'rsl-ie-field-item';
			item.draggable = true;
			item.dataset.field = field.key;
			item.dataset.label = field.label;
			item.dataset.type = field.type || 'text';

			const iconClass = this.getFieldIcon( field.type );

			item.innerHTML = `
				<span class="rsl-ie-field-icon dashicons ${ iconClass }"></span>
				<span class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</span>
				<span class="rsl-ie-field-type">${ this.escapeHtml( field.type ) }</span>
			`;

			grid.appendChild( item );
		} );

		category.appendChild( grid );
		container.appendChild( category );
	}

	/**
	 * Get current content type from step 1
	 */
	getCurrentContentType() {
		const selectedType = document.querySelector(
			'input[name="content_type"]:checked'
		);
		if ( ! selectedType ) return 'post';

		const contentType = selectedType.value;

		// For custom_post_types, get the specific post type from the selector
		if ( contentType === 'custom_post_types' ) {
			const postTypeSelector = document.querySelector(
				'.rsl-ie-post-type-selector'
			);
			if ( postTypeSelector && postTypeSelector.value ) {
				return postTypeSelector.value;
			}
			// If no specific type selected yet, return a generic value
			return 'post';
		}

		return contentType;
	}

	/**
	 * Load dynamic fields (Taxonomies, Custom Fields, ACF, Yoast)
	 */
	loadDynamicFields() {
		// Get selected post type from step 1
		this.selectedPostType = this.getCurrentContentType();
		const contentType = this.getCurrentRealContentType();
		const loadToken = Date.now();
		const requests = [];
		const trackRequest = ( request ) => {
			if ( request && typeof request.always === 'function' ) {
				requests.push(
					new Promise( ( resolve ) => request.always( resolve ) )
				);
			}
		};

		this.dynamicFieldsLoading = true;
		this.dynamicFieldsLoadToken = loadToken;
		this.toggleNextButton();

		// Load static fields based on content type
		this.loadStaticFields();

		// Types that are not post types and should not load taxonomies/custom fields
		const nonPostTypes = [
			'taxonomy',
			'user',
			'menu',
			'comment',
			'database_table',
			'woo_attribute',
		];

		// Load taxonomies only for actual post types
		if ( ! nonPostTypes.includes( contentType ) ) {
			this.prepareDynamicCategory(
				'.rsl-ie-taxonomies-category',
				'.rsl-ie-taxonomies-grid',
				'rsl-ie-taxonomies-loading',
				window.rslIeData.i18n.loadingTaxonomies ||
					'Loading taxonomies...'
			);
			trackRequest( this.loadTaxonomies() );
		} else {
			this.hideDynamicCategory( '.rsl-ie-taxonomies-category' );
		}

		// Load custom fields only for actual post types
		if ( ! nonPostTypes.includes( contentType ) ) {
			this.prepareDynamicCategory(
				'.rsl-ie-custom-fields-category',
				'.rsl-ie-custom-fields-grid',
				'rsl-ie-custom-fields-loading',
				window.rslIeData.i18n.loadingCustomFields ||
					'Loading custom fields...'
			);
			trackRequest( this.loadCustomFields() );
		} else {
			this.hideDynamicCategory( '.rsl-ie-custom-fields-category' );
		}

		// Check if ACF is active and load ACF fields (skip for non-supported types)
		const acfExcludedTypes = [
			'database_table',
			'woo_attribute',
			'woo_coupon',
			'woo_order',
		];
		if ( ! acfExcludedTypes.includes( contentType ) ) {
			this.prepareDynamicCategory(
				'.rsl-ie-acf-fields-category',
				'.rsl-ie-acf-fields-grid',
				'rsl-ie-acf-loading',
				window.rslIeData.i18n.loadingAcfFields ||
					'Loading ACF fields...'
			);
			trackRequest( this.checkAndLoadACF() );
		} else {
			this.hideDynamicCategory( '.rsl-ie-acf-fields-category' );
		}

		// Check if Yoast is active and load Yoast fields (skip for non-content types)
		const excludedTypes = [
			'media',
			'user',
			'menu',
			'comment',
			'taxonomy',
			'database_table',
			'woo_attribute',
			'woo_coupon',
			'woo_order',
		];
		if ( ! excludedTypes.includes( contentType ) ) {
			this.prepareDynamicCategory(
				'.rsl-ie-yoast-fields-category',
				'.rsl-ie-yoast-fields-grid',
				'rsl-ie-yoast-loading',
				window.rslIeData.i18n.loadingYoastFields ||
					'Loading Yoast SEO fields...'
			);
			trackRequest( this.checkAndLoadYoast() );
		} else {
			this.hideDynamicCategory( '.rsl-ie-yoast-fields-category' );
		}

		// Check if Rank Math is active and load Rank Math fields (skip for non-content types)
		if ( ! excludedTypes.includes( contentType ) ) {
			this.prepareDynamicCategory(
				'.rsl-ie-rank-math-fields-category',
				'.rsl-ie-rank-math-fields-grid',
				'rsl-ie-rank-math-loading',
				'Loading Rank Math SEO fields...'
			);
			trackRequest( this.checkAndLoadRankMath() );
		} else {
			this.hideDynamicCategory( '.rsl-ie-rank-math-fields-category' );
		}

		// Check if Elementor is active and load Elementor fields (skip for non-content types)
		if ( ! excludedTypes.includes( contentType ) ) {
			this.prepareDynamicCategory(
				'.rsl-ie-elementor-fields-category',
				'.rsl-ie-elementor-fields-grid',
				'rsl-ie-elementor-loading',
				'Loading Elementor fields...'
			);
			trackRequest( this.checkAndLoadElementor() );
		} else {
			this.hideDynamicCategory( '.rsl-ie-elementor-fields-category' );
		}

		Promise.all( requests ).then( () => {
			if ( this.dynamicFieldsLoadToken !== loadToken ) {
				return;
			}
			this.dynamicFieldsLoading = false;
			this.toggleNextButton();
		} );
	}

	prepareDynamicCategory(
		categorySelector,
		gridSelector,
		loadingClass,
		message
	) {
		const category = document.querySelector( categorySelector );
		const grid = document.querySelector( gridSelector );
		if ( category ) {
			category.style.display = '';
		}
		if ( grid ) {
			grid.innerHTML = `
				<div class="${ loadingClass }">
					<span class="spinner is-active"></span>
					<p>${ this.escapeHtml( message ) }</p>
				</div>
			`;
		}
	}

	hideDynamicCategory( categorySelector ) {
		const category = document.querySelector( categorySelector );
		if ( ! category ) {
			return;
		}
		category.style.display = 'none';
		delete category.dataset.pendingAddAll;
	}

	/**
	 * Reload dynamic fields (when post type changes)
	 */
	reloadDynamicFields() {
		// Hide and clear ALL dynamic categories (including static ones)
		const allCategories = document.querySelectorAll(
			'.rsl-ie-field-category'
		);
		allCategories.forEach( ( category ) => {
			// Skip custom fields, taxonomies, ACF, Yoast, Rank Math - they will be handled separately
			if (
				! category.classList.contains( 'rsl-ie-taxonomies-category' ) &&
				! category.classList.contains(
					'rsl-ie-custom-fields-category'
				) &&
				! category.classList.contains( 'rsl-ie-acf-fields-category' ) &&
				! category.classList.contains(
					'rsl-ie-yoast-fields-category'
				) &&
				! category.classList.contains(
					'rsl-ie-rank-math-fields-category'
				) &&
				! category.classList.contains(
					'rsl-ie-elementor-fields-category'
				)
			) {
				// This is a static category, hide and clear it
				category.style.display = 'none';
				const grid = category.querySelector( '.rsl-ie-fields-grid' );
				if ( grid ) grid.innerHTML = '';
			}
		} );

		// Hide and clear dynamic categories
		const taxonomiesCategory = document.querySelector(
			'.rsl-ie-taxonomies-category'
		);
		const customFieldsCategory = document.querySelector(
			'.rsl-ie-custom-fields-category'
		);
		const acfCategory = document.querySelector(
			'.rsl-ie-acf-fields-category'
		);
		const yoastCategory = document.querySelector(
			'.rsl-ie-yoast-fields-category'
		);
		const rankMathCategory = document.querySelector(
			'.rsl-ie-rank-math-fields-category'
		);
		const elementorCategory = document.querySelector(
			'.rsl-ie-elementor-fields-category'
		);

		if ( taxonomiesCategory ) {
			taxonomiesCategory.style.display = 'none';
			const grid = taxonomiesCategory.querySelector(
				'.rsl-ie-taxonomies-grid'
			);
			if ( grid ) grid.innerHTML = '';
		}

		if ( customFieldsCategory ) {
			customFieldsCategory.style.display = 'none';
			const grid = customFieldsCategory.querySelector(
				'.rsl-ie-custom-fields-grid'
			);
			if ( grid ) grid.innerHTML = '';
		}

		if ( acfCategory ) {
			acfCategory.style.display = 'none';
			const grid = acfCategory.querySelector( '.rsl-ie-acf-fields-grid' );
			if ( grid ) {
				grid.innerHTML = `<div class="rsl-ie-acf-loading"><span class="spinner is-active"></span><p>${ window.rslIeData.i18n.loadingAcfFields }</p></div>`;
			}
		}

		if ( yoastCategory ) {
			yoastCategory.style.display = 'none';
			const grid = yoastCategory.querySelector(
				'.rsl-ie-yoast-fields-grid'
			);
			if ( grid ) {
				grid.innerHTML = `<div class="rsl-ie-yoast-loading"><span class="spinner is-active"></span><p>${ window.rslIeData.i18n.loadingYoastFields }</p></div>`;
			}
		}

		if ( rankMathCategory ) {
			rankMathCategory.style.display = 'none';
			const grid = rankMathCategory.querySelector(
				'.rsl-ie-rank-math-fields-grid'
			);
			if ( grid ) {
				grid.innerHTML =
					'<div class="rsl-ie-rank-math-loading"><span class="spinner is-active"></span><p>Loading Rank Math SEO fields...</p></div>';
			}
		}

		if ( elementorCategory ) {
			elementorCategory.style.display = 'none';
			const grid = elementorCategory.querySelector(
				'.rsl-ie-elementor-fields-grid'
			);
			if ( grid ) {
				grid.innerHTML =
					'<div class="rsl-ie-elementor-loading"><span class="spinner is-active"></span><p>Loading Elementor fields...</p></div>';
			}
		}

		// Reload fields
		this.loadDynamicFields();
	}

	/**
	 * Load static fields based on content type
	 */
	loadStaticFields() {
		// Get field definitions from parent export module
		if (
			typeof window.rslIeExportModule === 'undefined' ||
			! window.rslIeExportModule.getFieldsByContentType
		) {
			return;
		}

		const contentType = this.getCurrentRealContentType();
		const fieldGroups =
			window.rslIeExportModule.getFieldsByContentType( contentType );

		// Find the container for static fields
		const container = document.querySelector(
			'.rsl-ie-fields-library-body'
		);
		if ( ! container ) return;

		// Clear existing static categories (keep dynamic ones)
		const existingStatic = container.querySelectorAll(
			'.rsl-ie-field-category:not(.rsl-ie-taxonomies-category):not(.rsl-ie-custom-fields-category):not(.rsl-ie-acf-fields-category):not(.rsl-ie-yoast-fields-category):not(.rsl-ie-rank-math-fields-category):not(.rsl-ie-elementor-fields-category)'
		);
		existingStatic.forEach( ( cat ) => cat.remove() );

		// Get reference to taxonomies category to insert before it
		const taxonomiesCategory = container.querySelector(
			'.rsl-ie-taxonomies-category'
		);

		// Render each field group as a category
		fieldGroups.forEach( ( group, index ) => {
			// Skip Custom Filters group and selector groups (they're only for step 2)
			if (
				group.label === 'Custom Filters' ||
				group.label === 'Post Type Selection' ||
				group.label === 'Taxonomy Selection'
			) {
				return;
			}

			const category = this.createFieldCategory( group, index === 0 );

			// Insert before taxonomies category
			if ( taxonomiesCategory ) {
				container.insertBefore( category, taxonomiesCategory );
			} else {
				container.appendChild( category );
			}
		} );
	}

	/**
	 * Create a field category element
	 */
	createFieldCategory( group, isOpen = false ) {
		const category = document.createElement( 'div' );
		category.className =
			'rsl-ie-field-category' + ( isOpen ? '' : ' rsl-ie-collapsed' );

		const title = document.createElement( 'h4' );
		title.className = 'rsl-ie-field-category-title';
		title.innerHTML = `
			<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
			<span class="dashicons dashicons-admin-post"></span>
			${ this.escapeHtml( group.label ) }
			<button type="button" class="rsl-ie-add-all-fields" title="${
				window.rslIeData.i18n.addAllFieldsTitle
			}">
				${ window.rslIeData.i18n.addAll }
			</button>
		`;

		const grid = document.createElement( 'div' );
		grid.className = 'rsl-ie-fields-grid';

		// Add fields
		if ( group.options && Array.isArray( group.options ) ) {
			group.options.forEach( ( option ) => {
				// Skip special filter types
				if (
					option.type === 'custom_field' ||
					option.type === 'taxonomy_filter' ||
					option.type === 'post_type_selector' ||
					option.type === 'taxonomy_selector' ||
					option.type === 'table_selector'
				) {
					return;
				}

				const field = this.createFieldItem( option );
				grid.appendChild( field );
			} );
		}

		category.appendChild( title );
		category.appendChild( grid );

		return category;
	}

	/**
	 * Create a field item element
	 */
	createFieldItem( option ) {
		const item = document.createElement( 'div' );
		item.className = 'rsl-ie-field-item';
		item.draggable = true;
		item.dataset.field = option.value;
		item.dataset.label = option.label;
		item.dataset.type = option.type || 'text';

		const iconClass = this.getFieldIcon( option.type );

		item.innerHTML = `
			<span class="rsl-ie-field-icon dashicons ${ iconClass }"></span>
			<span class="rsl-ie-field-label">${ this.escapeHtml( option.label ) }</span>
			<span class="rsl-ie-field-type">${ this.escapeHtml(
				option.type || 'text'
			) }</span>
		`;

		return item;
	}

	/**
	 * Get real content type (for custom_post_types returns the radio value, not selector value)
	 */
	getCurrentRealContentType() {
		const selectedType = document.querySelector(
			'input[name="content_type"]:checked'
		);
		return selectedType ? selectedType.value : 'post';
	}

	/**
	 * Load taxonomies for selected post type
	 */
	loadTaxonomies() {
		if ( typeof rslIeData === 'undefined' ) return;

		return jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_taxonomies',
				nonce: rslIeData.nonce,
				post_type: this.selectedPostType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.taxonomies &&
					response.data.taxonomies.length > 0
				) {
					this.renderTaxonomies( response.data.taxonomies );
					// Show the category
					const category = document.querySelector(
						'.rsl-ie-taxonomies-category'
					);
					if ( category ) {
						category.style.display = '';
					}
				} else {
					// Hide the category if no taxonomies
					const category = document.querySelector(
						'.rsl-ie-taxonomies-category'
					);
					if ( category ) {
						category.style.display = 'none';
						delete category.dataset.pendingAddAll;
					}
				}
			},
			error: ( xhr, status, error ) => {
				this.hideDynamicCategory( '.rsl-ie-taxonomies-category' );
			},
		} );
	}

	/**
	 * Render taxonomies
	 */
	renderTaxonomies( taxonomies ) {
		const grid = document.querySelector( '.rsl-ie-taxonomies-grid' );
		if ( ! grid ) return;

		grid.innerHTML = '';

		taxonomies.forEach( ( taxonomy ) => {
			const item = document.createElement( 'div' );
			item.className = 'rsl-ie-field-item';
			item.draggable = true;
			item.dataset.field = 'taxonomy_' + taxonomy.name;
			item.dataset.label = taxonomy.label;
			item.dataset.type = 'taxonomy';

			item.innerHTML = `
				<span class="rsl-ie-field-icon dashicons dashicons-category"></span>
				<span class="rsl-ie-field-label">${ this.escapeHtml( taxonomy.label ) }</span>
				<span class="rsl-ie-field-type">taxonomy</span>
			`;

			grid.appendChild( item );
		} );

		this.flushPendingAddAll(
			document.querySelector( '.rsl-ie-taxonomies-category' )
		);
	}

	/**
	 * Load custom fields for selected post type
	 */
	loadCustomFields() {
		if ( typeof rslIeData === 'undefined' ) return;

		return jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_custom_fields',
				nonce: rslIeData.nonce,
				post_type: this.selectedPostType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderCustomFields( response.data.fields );
					// Show the category
					const category = document.querySelector(
						'.rsl-ie-custom-fields-category'
					);
					if ( category ) {
						category.style.display = '';
					}
				} else {
					// Hide the category if no custom fields
					const category = document.querySelector(
						'.rsl-ie-custom-fields-category'
					);
					if ( category ) {
						category.style.display = 'none';
						delete category.dataset.pendingAddAll;
					}
				}
			},
			error: ( xhr, status, error ) => {
				this.hideDynamicCategory( '.rsl-ie-custom-fields-category' );
			},
		} );
	}

	/**
	 * Render custom fields
	 */
	renderCustomFields( fields ) {
		const grid = document.querySelector( '.rsl-ie-custom-fields-grid' );
		if ( ! grid ) return;

		grid.innerHTML = '';

		fields.forEach( ( field ) => {
			const item = document.createElement( 'div' );
			item.className = 'rsl-ie-field-item';
			item.draggable = true;
			item.dataset.field = 'meta_' + field.name;
			item.dataset.label = field.name;
			item.dataset.type = 'meta';

			item.innerHTML = `
				<span class="rsl-ie-field-icon dashicons dashicons-admin-generic"></span>
				<span class="rsl-ie-field-label">${ this.escapeHtml( field.name ) }</span>
				<span class="rsl-ie-field-type">meta</span>
			`;

			grid.appendChild( item );
		} );

		this.flushPendingAddAll(
			document.querySelector( '.rsl-ie-custom-fields-category' )
		);
	}

	/**
	 * Check if ACF is active and load ACF fields
	 */
	checkAndLoadACF() {
		if ( typeof rslIeData === 'undefined' ) {
			return;
		}

		const contentType = this.getCurrentRealContentType();
		const requestData = {
			action: 'rsl_ie_get_acf_fields',
			nonce: rslIeData.nonce,
		};

		// For taxonomy content type, send taxonomy parameter
		if ( contentType === 'taxonomy' ) {
			const taxonomySelector = document.querySelector(
				'.rsl-ie-taxonomy-selector'
			);
			if ( taxonomySelector && taxonomySelector.value ) {
				requestData.taxonomy = taxonomySelector.value;
			} else {
				// If no taxonomy selected yet, hide ACF category
				const category = document.querySelector(
					'.rsl-ie-acf-fields-category'
				);
				if ( category ) {
					category.style.display = 'none';
				}
				return;
			}
		} else {
			// For other content types, send post_type parameter
			requestData.post_type = this.selectedPostType;
		}

		return jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: requestData,
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderACFFields( response.data.fields );
					// Show the ACF category
					const category = document.querySelector(
						'.rsl-ie-acf-fields-category'
					);
					if ( category ) {
						category.style.display = '';
					}
				} else {
					// Hide the category if no ACF fields
					const category = document.querySelector(
						'.rsl-ie-acf-fields-category'
					);
					if ( category ) {
						category.style.display = 'none';
						delete category.dataset.pendingAddAll;
					}
				}
			},
			error: ( xhr, status, error ) => {
				this.hideDynamicCategory( '.rsl-ie-acf-fields-category' );
			},
		} );
	}

	/**
	 * Render ACF fields
	 */
	renderACFFields( fields ) {
		const grid = document.querySelector( '.rsl-ie-acf-fields-grid' );
		if ( ! grid ) return;

		// Clear grid completely (removes loading spinner and any existing fields)
		grid.innerHTML = '';

		fields.forEach( ( field ) => {
			const item = document.createElement( 'div' );
			item.className = 'rsl-ie-field-item';
			item.draggable = true;
			item.dataset.field = 'acf_' + field.name;
			item.dataset.label = field.label;
			item.dataset.type = 'acf';

			item.innerHTML = `
				<span class="rsl-ie-field-icon dashicons dashicons-admin-settings"></span>
				<span class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</span>
				<span class="rsl-ie-field-type">acf</span>
			`;

			grid.appendChild( item );
		} );

		this.flushPendingAddAll(
			document.querySelector( '.rsl-ie-acf-fields-category' )
		);
	}

	/**
	 * Check if Yoast is active and load Yoast fields
	 */
	checkAndLoadYoast() {
		if ( typeof rslIeData === 'undefined' ) return;

		return jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_yoast_fields',
				nonce: rslIeData.nonce,
				post_type: this.selectedPostType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderYoastFields( response.data.fields );
					// Show the Yoast category
					const category = document.querySelector(
						'.rsl-ie-yoast-fields-category'
					);
					if ( category ) {
						category.style.display = '';
					}
				} else {
					// Hide the category if no Yoast fields
					const category = document.querySelector(
						'.rsl-ie-yoast-fields-category'
					);
					if ( category ) {
						category.style.display = 'none';
						delete category.dataset.pendingAddAll;
					}
				}
			},
			error: ( xhr, status, error ) => {
				this.hideDynamicCategory( '.rsl-ie-yoast-fields-category' );
			},
		} );
	}

	/**
	 * Render Yoast fields
	 */
	renderYoastFields( fields ) {
		const grid = document.querySelector( '.rsl-ie-yoast-fields-grid' );
		if ( ! grid ) return;

		// Clear grid completely (removes loading spinner and any existing fields)
		grid.innerHTML = '';

		fields.forEach( ( field ) => {
			const item = document.createElement( 'div' );
			item.className = 'rsl-ie-field-item';
			item.draggable = true;
			item.dataset.field = field.name; // Use field name as-is, it already includes the full meta key
			item.dataset.label = field.label;
			item.dataset.type = 'yoast';

			item.innerHTML = `
				<span class="rsl-ie-field-icon dashicons dashicons-chart-line"></span>
				<span class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</span>
				<span class="rsl-ie-field-type">yoast</span>
			`;

			grid.appendChild( item );
		} );

		this.flushPendingAddAll(
			document.querySelector( '.rsl-ie-yoast-fields-category' )
		);
	}

	/**
	 * Check if Rank Math is active and load Rank Math fields
	 */
	checkAndLoadRankMath() {
		if ( typeof rslIeData === 'undefined' ) return;

		return jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_rank_math_fields',
				nonce: rslIeData.nonce,
				post_type: this.selectedPostType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderRankMathFields( response.data.fields );
					const category = document.querySelector(
						'.rsl-ie-rank-math-fields-category'
					);
					if ( category ) {
						category.style.display = '';
					}
				} else {
					const category = document.querySelector(
						'.rsl-ie-rank-math-fields-category'
					);
					if ( category ) {
						category.style.display = 'none';
						delete category.dataset.pendingAddAll;
					}
				}
			},
			error: () => {
				this.hideDynamicCategory( '.rsl-ie-rank-math-fields-category' );
			},
		} );
	}

	/**
	 * Render Rank Math fields
	 */
	renderRankMathFields( fields ) {
		const grid = document.querySelector( '.rsl-ie-rank-math-fields-grid' );
		if ( ! grid ) return;

		grid.innerHTML = '';

		fields.forEach( ( field ) => {
			const item = document.createElement( 'div' );
			item.className = 'rsl-ie-field-item';
			item.draggable = true;
			item.dataset.field = field.name;
			item.dataset.label = field.label;
			item.dataset.type = 'rank_math';

			item.innerHTML = `
				<span class="rsl-ie-field-icon dashicons dashicons-chart-area"></span>
				<span class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</span>
				<span class="rsl-ie-field-type">rank math</span>
			`;

			grid.appendChild( item );
		} );

		this.flushPendingAddAll(
			document.querySelector( '.rsl-ie-rank-math-fields-category' )
		);
	}

	/**
	 * Check if Elementor is active and load Elementor fields
	 */
	checkAndLoadElementor() {
		if ( typeof rslIeData === 'undefined' ) return;

		return jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_elementor_fields',
				nonce: rslIeData.nonce,
				post_type: this.selectedPostType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderElementorFields( response.data.fields );
					const category = document.querySelector(
						'.rsl-ie-elementor-fields-category'
					);
					if ( category ) {
						category.style.display = '';
					}
				} else {
					const category = document.querySelector(
						'.rsl-ie-elementor-fields-category'
					);
					if ( category ) {
						category.style.display = 'none';
						delete category.dataset.pendingAddAll;
					}
				}
			},
			error: () => {
				this.hideDynamicCategory( '.rsl-ie-elementor-fields-category' );
			},
		} );
	}

	/**
	 * Render Elementor fields
	 */
	renderElementorFields( fields ) {
		const grid = document.querySelector( '.rsl-ie-elementor-fields-grid' );
		if ( ! grid ) return;

		grid.innerHTML = '';

		fields.forEach( ( field ) => {
			const item = document.createElement( 'div' );
			item.className = 'rsl-ie-field-item';
			item.draggable = true;
			item.dataset.field = field.name;
			item.dataset.label = field.label;
			item.dataset.type = 'elementor';

			item.innerHTML = `
				<span class="rsl-ie-field-icon dashicons dashicons-layout"></span>
				<span class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</span>
				<span class="rsl-ie-field-type">elementor</span>
			`;

			grid.appendChild( item );
		} );

		this.flushPendingAddAll(
			document.querySelector( '.rsl-ie-elementor-fields-category' )
		);
	}

	/**
	 * Initialize Field Functions Modal
	 */
	initFieldFunctionsModal() {
		const modal = document.getElementById( 'rsl-ie-field-functions-modal' );
		if ( ! modal ) return;

		// Close modal
		modal
			.querySelector( '.rsl-ie-modal-close' )
			?.addEventListener( 'click', () => {
				this.closeFieldFunctionsModal();
			} );

		modal
			.querySelector( '.rsl-ie-modal-cancel' )
			?.addEventListener( 'click', () => {
				this.closeFieldFunctionsModal();
			} );

		// Save functions
		modal
			.querySelector( '.rsl-ie-save-field-functions' )
			?.addEventListener( 'click', () => {
				this.saveFieldFunctions();
			} );

		modal
			.querySelector( '.rsl-ie-test-pipeline' )
			?.addEventListener( 'click', () => {
				this.testFunctionPipeline();
			} );

		// Functions search
		modal
			.querySelector( '#rsl-ie-functions-search' )
			?.addEventListener( 'input', ( e ) => {
				this.filterFunctions( e.target.value );
			} );

		// Functions filter
		modal
			.querySelectorAll( 'input[name="functions-filter"]' )
			.forEach( ( radio ) => {
				radio.addEventListener( 'change', ( e ) => {
					this.filterFunctionsByCategory( e.target.value );
				} );
			} );

		// Create new function button
		modal
			.querySelector( '.rsl-ie-create-new-function' )
			?.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				this.createNewFunction();
			} );

		// Initialize sortable for function pipeline
		this.initFunctionPipelineSortable();
	}

	/**
	 * Open field functions modal
	 */
	openFieldFunctionsModal( fieldKey ) {
		if ( ! this.areFieldTransformationsEnabled() ) {
			return;
		}

		const field = this.selectedFields.find( ( f ) => f.key === fieldKey );
		if ( ! field ) return;

		this.currentEditingField = fieldKey;

		const modal = document.getElementById( 'rsl-ie-field-functions-modal' );
		if ( ! modal ) return;

		// Set field info
		modal.querySelector( '.rsl-ie-current-field-label' ).textContent =
			field.label;
		modal.querySelector( '.rsl-ie-current-field-type' ).textContent =
			field.type;

		// Load current functions
		this.loadCurrentFunctions( fieldKey );

		// Load available functions
		this.renderAvailableFunctions();

		// Show modal
		modal.style.display = 'flex';
		document.body.classList.add( 'rsl-ie-modal-open' );
	}

	/**
	 * Close field functions modal
	 */
	closeFieldFunctionsModal() {
		const modal = document.getElementById( 'rsl-ie-field-functions-modal' );
		if ( modal ) {
			modal.style.display = 'none';
			document.body.classList.remove( 'rsl-ie-modal-open' );

			// Hide preview results
			const previewResult = modal.querySelector(
				'#rsl-ie-preview-result'
			);
			if ( previewResult ) {
				previewResult.style.display = 'none';
			}

			// Clear preview input
			const previewInput = modal.querySelector( '#rsl-ie-preview-input' );
			if ( previewInput ) {
				previewInput.value = '';
			}
		}
		this.currentEditingField = null;
	}

	/**
	 * Load current functions for field
	 */
	loadCurrentFunctions( fieldKey ) {
		const container = document.getElementById( 'rsl-ie-function-items' );
		if ( ! container ) return;

		container.innerHTML = '';

		const functions = this.fieldFunctions[ fieldKey ] || [];
		const noFunctionsEl = document.querySelector( '.rsl-ie-no-functions' );

		if ( functions.length === 0 ) {
			if ( noFunctionsEl ) noFunctionsEl.style.display = 'block';
			this.updateFunctionsCount( 0 );
			return;
		}

		if ( noFunctionsEl ) noFunctionsEl.style.display = 'none';

		functions.forEach( ( funcId ) => {
			const func = this.availableFunctions.find(
				( f ) => f.id == funcId
			);
			if ( func ) {
				this.addFunctionToPipeline( func, false );
			}
		} );

		this.updateFunctionsCount( functions.length );
	}

	/**
	 * Add function to pipeline
	 */
	addFunctionToPipeline( func, updateArray = true ) {
		const container = document.getElementById( 'rsl-ie-function-items' );
		if ( ! container ) return;

		const item = document.createElement( 'div' );
		item.className = 'rsl-ie-function-item';
		item.dataset.functionId = func.id;

		item.innerHTML = `
			<span class="rsl-ie-function-handle dashicons dashicons-menu"></span>
			<div class="rsl-ie-function-info">
				<strong class="rsl-ie-function-name">${ this.escapeHtml( func.name ) }</strong>
				<span class="rsl-ie-function-desc">${ this.escapeHtml(
					func.description || ''
				) }</span>
			</div>
			<div class="rsl-ie-function-actions">
				<button type="button" class="button-small rsl-ie-remove-function" data-function-id="${
					func.id
				}">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		`;

		// Remove function event
		item.querySelector( '.rsl-ie-remove-function' ).addEventListener(
			'click',
			() => {
				item.remove();
				this.updatePipelineFunctions();
				this.updateFunctionsCount();
				this.toggleNoFunctionsMessage();
			}
		);

		container.appendChild( item );

		if ( updateArray ) {
			this.updatePipelineFunctions();
			this.updateFunctionsCount();
		}

		this.toggleNoFunctionsMessage();
	}

	/**
	 * Update pipeline functions array
	 */
	updatePipelineFunctions() {
		if ( ! this.currentEditingField ) return;

		const items = document.querySelectorAll( '.rsl-ie-function-item' );
		const functionIds = Array.from( items ).map(
			( item ) => item.dataset.functionId
		);

		this.fieldFunctions[ this.currentEditingField ] = functionIds;
	}

	/**
	 * Update functions count
	 */
	updateFunctionsCount( count = null ) {
		const countEl = document.querySelector( '.rsl-ie-functions-count' );
		if ( ! countEl ) return;

		if ( count === null ) {
			const items = document.querySelectorAll( '.rsl-ie-function-item' );
			count = items.length;
		}

		countEl.textContent = `(${ count })`;
	}

	/**
	 * Toggle no functions message
	 */
	toggleNoFunctionsMessage() {
		const noFunctionsEl = document.querySelector( '.rsl-ie-no-functions' );
		const items = document.querySelectorAll( '.rsl-ie-function-item' );

		if ( noFunctionsEl ) {
			noFunctionsEl.style.display = items.length === 0 ? 'block' : 'none';
		}
	}

	/**
	 * Initialize function pipeline sortable
	 */
	initFunctionPipelineSortable() {
		const container = document.getElementById( 'rsl-ie-function-items' );
		if ( ! container || ! jQuery.fn.sortable ) return;

		jQuery( container ).sortable( {
			handle: '.rsl-ie-function-handle',
			placeholder: 'rsl-ie-function-item-placeholder',
			update: () => {
				this.updatePipelineFunctions();
			},
		} );
	}

	/**
	 * Save field functions
	 */
	saveFieldFunctions() {
		this.updatePipelineFunctions();

		// Update column badge
		const column = document.querySelector(
			`[data-field-key="${ this.currentEditingField }"]`
		);
		if ( column ) {
			const functions =
				this.fieldFunctions[ this.currentEditingField ] || [];
			const hasFunctions = functions.length > 0;

			if ( hasFunctions ) {
				column.classList.add( 'has-functions' );

				let badge = column.querySelector( '.rsl-ie-column-badge' );
				if ( ! badge ) {
					badge = document.createElement( 'div' );
					badge.className = 'rsl-ie-column-badge';
					column.appendChild( badge );
				}
				badge.innerHTML = `
					<span class="dashicons dashicons-admin-generic"></span>
					${ functions.length } ${ window.rslIeData.i18n.functions }
				`;
			} else {
				column.classList.remove( 'has-functions' );
				const badge = column.querySelector( '.rsl-ie-column-badge' );
				if ( badge ) badge.remove();
			}
		}

		this.closeFieldFunctionsModal();
		this.showNotice(
			window.rslIeData.i18n.functionsSavedSuccess,
			'success'
		);
	}

	/**
	 * Load available functions
	 */
	loadFunctions() {
		if ( ! this.areFieldTransformationsEnabled() ) {
			return;
		}
		const listAction = this.getFieldTransformationAction( 'list' );
		if ( ! listAction ) {
			return;
		}

		// Check if rslIeData is available
		if ( typeof rslIeData === 'undefined' ) {
			return;
		}

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: listAction,
				nonce: rslIeData.nonce,
			},
			success: ( response ) => {
				if ( response.success && response.data.functions ) {
					this.availableFunctions = response.data.functions;
					this.renderAvailableFunctions();
				}
			},
		} );
	}

	/**
	 * Render available functions
	 */
	renderAvailableFunctions() {
		const container = document.getElementById( 'rsl-ie-functions-list' );
		if ( ! container ) return;

		const loadingEl = container.querySelector(
			'.rsl-ie-functions-loading'
		);
		if ( loadingEl ) loadingEl.remove();

		container.innerHTML = '';

		if ( this.availableFunctions.length === 0 ) {
			// Show empty state
			const emptyState = document.createElement( 'div' );
			emptyState.className = 'rsl-ie-functions-empty-state';
			emptyState.innerHTML = `
				<span class="dashicons dashicons-info"></span>
				<p>${ window.rslIeData.i18n.noFunctionsAvailableYet }</p>
				<p>${ window.rslIeData.i18n.createFirstFunction }</p>
			`;
			container.appendChild( emptyState );
			return;
		}

		this.availableFunctions.forEach( ( func ) => {
			const item = document.createElement( 'div' );
			item.className = 'rsl-ie-function-list-item';
			item.dataset.functionId = func.id;
			item.dataset.category = func.category || 'custom';

			item.innerHTML = `
				<div class="rsl-ie-function-list-info">
					<span class="rsl-ie-function-list-name">${ this.escapeHtml( func.name ) }</span>
					<span class="rsl-ie-function-list-desc">${ this.escapeHtml(
						func.description || ''
					) }</span>
					</div>
					<button type="button" class="button button-small">${
						window.rslIeData.i18n.addFunction ||
						window.rslIeData.i18n.add ||
						'Assign function'
					}</button>
				`;

			item.querySelector( 'button' ).addEventListener( 'click', () => {
				this.addFunctionToPipeline( func );
			} );

			container.appendChild( item );
		} );
	}

	/**
	 * Filter functions by search query
	 */
	filterFunctions( query ) {
		const items = document.querySelectorAll( '.rsl-ie-function-list-item' );
		const lowerQuery = query.toLowerCase();

		items.forEach( ( item ) => {
			const name = item
				.querySelector( '.rsl-ie-function-list-name' )
				.textContent.toLowerCase();
			const desc = item
				.querySelector( '.rsl-ie-function-list-desc' )
				.textContent.toLowerCase();

			if ( name.includes( lowerQuery ) || desc.includes( lowerQuery ) ) {
				item.style.display = '';
			} else {
				item.style.display = 'none';
			}
		} );
	}

	/**
	 * Filter functions by category
	 */
	filterFunctionsByCategory( category ) {
		const items = document.querySelectorAll( '.rsl-ie-function-list-item' );
		const emptyState = document.querySelector(
			'.rsl-ie-functions-empty-state'
		);

		// Don't filter if only empty state is shown
		if ( emptyState && items.length === 0 ) {
			return;
		}

		let visibleCount = 0;

		items.forEach( ( item ) => {
			if ( category === 'all' || item.dataset.category === category ) {
				item.style.display = '';
				visibleCount++;
			} else {
				item.style.display = 'none';
			}
		} );

		// Show/hide no results message for filtered category
		this.toggleNoResultsMessage( visibleCount, category );
	}

	/**
	 * Toggle no results message
	 */
	toggleNoResultsMessage( visibleCount, category ) {
		const container = document.getElementById( 'rsl-ie-functions-list' );
		if ( ! container ) return;

		let noResults = container.querySelector(
			'.rsl-ie-functions-no-results'
		);

		if ( visibleCount === 0 && category !== 'all' ) {
			if ( ! noResults ) {
				noResults = document.createElement( 'div' );
				noResults.className = 'rsl-ie-functions-no-results';
				container.appendChild( noResults );
			}

			const categoryLabel = category === 'library' ? 'library' : 'custom';
			noResults.innerHTML = `
				<span class="dashicons dashicons-info"></span>
				<p>${ window.rslIeData.i18n.noFunctionsFound.replace(
					'%s',
					categoryLabel
				) }</p>
			`;
			noResults.style.display = 'block';
		} else {
			if ( noResults ) {
				noResults.style.display = 'none';
			}
		}
	}

	/**
	 * Test function pipeline
	 */
	testFunctionPipeline() {
		if ( ! this.areFieldTransformationsEnabled() ) {
			return;
		}
		const testAction = this.getFieldTransformationAction( 'test' );
		if ( ! testAction ) {
			return;
		}

		const input = document.getElementById( 'rsl-ie-preview-input' ).value;
		if ( ! input ) {
			this.showNotice( window.rslIeData.i18n.enterTestValue, 'warning' );
			return;
		}

		const functionIds =
			this.fieldFunctions[ this.currentEditingField ] || [];
		if ( functionIds.length === 0 ) {
			this.showNotice(
				window.rslIeData.i18n.noFunctionsToTest,
				'warning'
			);
			return;
		}

		// Check if rslIeData is available
		if ( typeof rslIeData === 'undefined' ) {
			this.showNotice(
				window.rslIeData.i18n.configErrorRslIeData,
				'error'
			);
			return;
		}

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: testAction,
				nonce: rslIeData.nonce,
				value: input,
				functions: functionIds,
			},
			success: ( response ) => {
				if ( response.success ) {
					this.renderPipelinePreview( input, response.data.steps );
				} else {
					this.showNotice(
						response.data.message ||
							window.rslIeData.i18n.testFailed,
						'error'
					);
				}
			},
			error: () => {
				this.showNotice(
					window.rslIeData.i18n.errorTestingPipeline,
					'error'
				);
			},
		} );
	}

	/**
	 * Render pipeline preview
	 */
	renderPipelinePreview( initialValue, steps ) {
		const container = document.getElementById( 'rsl-ie-preview-result' );
		if ( ! container ) return;

		const stepsContainer = container.querySelector(
			'.rsl-ie-preview-steps'
		);
		stepsContainer.innerHTML = '';

		// Initial value
		stepsContainer.appendChild(
			this.createPreviewStep(
				0,
				window.rslIeData.i18n.input,
				initialValue
			)
		);

		// Each function step
		steps.forEach( ( step, index ) => {
			stepsContainer.appendChild(
				this.createPreviewStep(
					index + 1,
					step.function_name,
					step.output,
					step.error
				)
			);
		} );

		container.style.display = 'block';
	}

	/**
	 * Create preview step element
	 */
	createPreviewStep( number, name, value, error = false ) {
		const step = document.createElement( 'div' );
		step.className = 'rsl-ie-preview-step';

		step.innerHTML = `
			<div class="rsl-ie-preview-step-number">${ number }</div>
			<div class="rsl-ie-preview-step-name">${ this.escapeHtml( name ) }</div>
			<span class="rsl-ie-preview-step-arrow dashicons dashicons-arrow-right-alt"></span>
			<div class="rsl-ie-preview-step-value ${ error ? 'error' : '' }">
				${ this.escapeHtml(
					error
						? window.rslIeData.i18n.errorLabel.replace(
								'%s',
								value
						  )
						: value
				) }
			</div>
		`;

		return step;
	}

	/**
	 * Get field icon class
	 */
	getFieldIcon( type ) {
		const icons = {
			post: 'dashicons-admin-post',
			text: 'dashicons-editor-textcolor',
			html: 'dashicons-editor-alignleft',
			number: 'dashicons-tag',
			date: 'dashicons-calendar',
			url: 'dashicons-admin-links',
			media: 'dashicons-format-image',
			taxonomy: 'dashicons-category',
			array: 'dashicons-list-view',
			custom: 'dashicons-admin-generic',
		};

		return icons[ type ] || 'dashicons-admin-generic';
	}

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
	}

	/**
	 * Start auto-scroll monitoring
	 */
	startAutoScroll() {
		this.stopAutoScroll();
	}

	/**
	 * Stop auto-scroll
	 */
	stopAutoScroll() {
		if ( this.autoScrollInterval ) {
			clearInterval( this.autoScrollInterval );
			this.autoScrollInterval = null;
		}
	}

	/**
	 * Handle auto-scroll when dragging near edges
	 */
	handleAutoScroll( e ) {
		const scrollSpeed = 15; // Increased from 10
		const scrollZone = 150; // Increased from 100 - larger trigger zone
		const viewportHeight = window.innerHeight;
		const mouseY = e.clientY;

		// Auto-scroll when mouse is near edges
		if ( mouseY < scrollZone ) {
			// Scroll up when near top
			const intensity = 1 - mouseY / scrollZone;
			const scrollAmount = scrollSpeed * intensity;
			window.scrollBy( 0, -scrollAmount );
		} else if ( mouseY > viewportHeight - scrollZone ) {
			// Scroll down when near bottom
			const intensity =
				( mouseY - ( viewportHeight - scrollZone ) ) / scrollZone;
			const scrollAmount = scrollSpeed * intensity;
			window.scrollBy( 0, scrollAmount );
		}
	}

	/**
	 * Show notice
	 */
	showNotice( message, type = 'info' ) {
		// You can implement a toast notification system here
	}

	/**
	 * Create new function
	 */
	createNewFunction() {
		if ( typeof rslIeData !== 'undefined' && rslIeData.functionsUrl ) {
			window.open( rslIeData.functionsUrl, '_blank' );
		}
	}

	/**
	 * Get selected fields data
	 */
	getSelectedFieldsData() {
		return {
			fields: this.selectedFields,
			functions: this.fieldFunctions,
		};
	}

	/**
	 * Set selected fields (for loading saved state)
	 */
	setSelectedFieldsData( data ) {
		if ( data.fields ) {
			this.selectedFields = [];
			data.fields.forEach( ( field ) => {
				this.addFieldToCSV( field );
			} );
		}

		if ( this.areFieldTransformationsEnabled() && data.functions ) {
			this.fieldFunctions = data.functions;

			// Update column badges
			Object.keys( this.fieldFunctions ).forEach( ( fieldKey ) => {
				const column = document.querySelector(
					`[data-field-key="${ fieldKey }"]`
				);
				if ( column && this.fieldFunctions[ fieldKey ].length > 0 ) {
					column.classList.add( 'has-functions' );
				}
			} );
		}
	}
}
