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
		
		console.log('ExportStep3 constructor: selectedFields initialized to:', this.selectedFields);
		
		this.init();
	}

	init() {
		// Check dependencies
		if (typeof jQuery === 'undefined') {
			console.error('jQuery is not loaded');
			return;
		}

		if (typeof aieData === 'undefined') {
			console.error('aieData is not defined. Make sure scripts are enqueued properly.');
		}

		this.initDragAndDrop();
		this.initFieldSearch();
		this.initCsvBuilderActions();
		this.initFieldFunctionsModal();
		this.initColumnActions();
		this.initCategoryToggle();
		
		// Load available functions
		this.loadFunctions();
		
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
		const dropzone = document.getElementById('aie-csv-dropzone');
		const columnsContainer = document.getElementById('aie-csv-columns');
		
		if (!dropzone || !columnsContainer) return;

		// Make field items draggable
		document.addEventListener('dragstart', (e) => {
			if (e.target.classList.contains('aie-field-item')) {
				this.isDragging = true;
				document.body.classList.add('aie-dragging');
				e.target.classList.add('dragging');
				e.dataTransfer.effectAllowed = 'copy';
				e.dataTransfer.setData('text/plain', JSON.stringify({
					field: e.target.dataset.field,
					label: e.target.dataset.label,
					type: e.target.dataset.type
				}));
			}

			// Handle column reordering
			if (e.target.classList.contains('aie-csv-column')) {
				this.isDragging = true;
				document.body.classList.add('aie-dragging');
				e.target.classList.add('dragging');
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('application/column-reorder', e.target.dataset.fieldKey);
			}
		});

		document.addEventListener('dragover', (e) => {
			if (this.isDragging) {
				this.handleAutoScroll(e);
			}
		});

		document.addEventListener('dragend', (e) => {
			if (e.target.classList.contains('aie-field-item') || 
				e.target.classList.contains('aie-csv-column')) {
				this.isDragging = false;
				document.body.classList.remove('aie-dragging');
				e.target.classList.remove('dragging');
				this.stopAutoScroll();
			}
		});

		// Drop zone events
		dropzone.addEventListener('dragover', (e) => {
			// Only prevent default if we're actually dragging over the dropzone
			// This allows scrolling to continue
			if (e.dataTransfer.types.includes('text/plain') || e.dataTransfer.types.includes('application/column-reorder')) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'copy';
				dropzone.classList.add('aie-drag-over');
			}
		});

		dropzone.addEventListener('dragleave', (e) => {
			if (e.target === dropzone) {
				dropzone.classList.remove('aie-drag-over');
			}
		});

		dropzone.addEventListener('drop', (e) => {
			// Only prevent default for actual drop events
			const data = e.dataTransfer.getData('text/plain');
			if (data) {
				e.preventDefault();
				e.stopPropagation();
				dropzone.classList.remove('aie-drag-over');

				try {
					const fieldData = JSON.parse(data);
					this.addFieldToCSV(fieldData);
				} catch (error) {
					console.error('Error adding field:', error);
				}
			}
		});

		// Column reordering
		columnsContainer.addEventListener('dragover', (e) => {
			const dragging = document.querySelector('.aie-csv-column.dragging');
			
			// Only prevent default when actually reordering columns
			if (dragging) {
				e.preventDefault();
				const afterElement = this.getDragAfterElement(columnsContainer, e.clientX);
				
				if (afterElement == null) {
					columnsContainer.appendChild(dragging);
				} else {
					columnsContainer.insertBefore(dragging, afterElement);
				}
			}
		});

		columnsContainer.addEventListener('drop', (e) => {
			const dragging = document.querySelector('.aie-csv-column.dragging');
			if (dragging) {
				e.preventDefault();
				e.stopPropagation();
				this.updateColumnOrder();
			}
		});
	}

	/**
	 * Get element after drag position
	 */
	getDragAfterElement(container, x) {
		const draggableElements = [...container.querySelectorAll('.aie-csv-column:not(.dragging)')];

		return draggableElements.reduce((closest, child) => {
			const box = child.getBoundingClientRect();
			const offset = x - box.left - box.width / 2;

			if (offset < 0 && offset > closest.offset) {
				return { offset: offset, element: child };
			} else {
				return closest;
			}
		}, { offset: Number.NEGATIVE_INFINITY }).element;
	}

	/**
	 * Add field to CSV structure
	 */
	addFieldToCSV(fieldData) {
		const { field, label, type } = fieldData;
		const fieldKey = `${field}_${Date.now()}`;

		// Check if field already exists (prevent duplicates for unique fields)
		const existingField = this.selectedFields.find(f => f.field === field);
		if (existingField && field === 'ID') {
			this.showNotice(window.aieData.i18n.fieldAlreadyAdded, 'warning');
			return;
		}

		// Add to selected fields
		this.selectedFields.push({
			key: fieldKey,
			field,
			label,
			type
		});

		// Render column
		this.renderColumn(fieldKey, field, label, type);

		// Update UI
		this.updateCSVStats();
		this.toggleNextButton();
		this.togglePlaceholder();
	}

	/**
	 * Render CSV column
	 */
	renderColumn(fieldKey, field, label, type) {
		const columnsContainer = document.getElementById('aie-csv-columns');
		if (!columnsContainer) return;

		const column = document.createElement('div');
		column.className = 'aie-csv-column';
		column.draggable = true;
		column.dataset.fieldKey = fieldKey;
		column.dataset.field = field;

		const iconClass = this.getFieldIcon(type);
		const hasFunctions = this.fieldFunctions[fieldKey] && this.fieldFunctions[fieldKey].length > 0;

		column.innerHTML = `
			<div class="aie-column-header">
				<span class="aie-column-icon dashicons ${iconClass}"></span>
				<div class="aie-column-actions">
					<button type="button" class="aie-edit-column-functions" title="Assign functions" data-field-key="${fieldKey}">
						<span class="dashicons dashicons-admin-generic"></span>
					</button>
					<button type="button" class="aie-remove-column" title="Remove" data-field-key="${fieldKey}">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>
			<div class="aie-column-label">${this.escapeHtml(label)}</div>
			<div class="aie-column-field">${this.escapeHtml(field)}</div>
			${hasFunctions ? `
				<div class="aie-column-badge">
					<span class="dashicons dashicons-admin-generic"></span>
					${this.fieldFunctions[fieldKey].length} function(s)
				</div>
			` : ''}
		`;

		if (hasFunctions) {
			column.classList.add('has-functions');
		}

		columnsContainer.appendChild(column);
	}

	/**
	 * Initialize CSV builder actions
	 */
	initCsvBuilderActions() {
		// Clear all fields
		document.querySelector('.aie-clear-all-fields')?.addEventListener('click', () => {
			if (confirm(window.aieData.i18n.confirmRemoveAllFields)) {
				this.clearAllFields();
			}
		});

		// Add custom column
		document.querySelector('.aie-add-custom-column')?.addEventListener('click', () => {
			this.addCustomColumn();
		});
	}

	/**
	 * Initialize column actions
	 */
	initColumnActions() {
		document.addEventListener('click', (e) => {
			// Remove column
			if (e.target.closest('.aie-remove-column')) {
				const btn = e.target.closest('.aie-remove-column');
				const fieldKey = btn.dataset.fieldKey;
				this.removeColumn(fieldKey);
			}

			// Edit column functions
			if (e.target.closest('.aie-edit-column-functions')) {
				const btn = e.target.closest('.aie-edit-column-functions');
				const fieldKey = btn.dataset.fieldKey;
				this.openFieldFunctionsModal(fieldKey);
			}
		});
	}

	/**
	 * Remove column
	 */
	removeColumn(fieldKey) {
		// Remove from array
		this.selectedFields = this.selectedFields.filter(f => f.key !== fieldKey);
		
		// Remove from DOM
		const column = document.querySelector(`[data-field-key="${fieldKey}"]`);
		if (column) {
			column.remove();
		}

		// Remove functions
		delete this.fieldFunctions[fieldKey];

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
		
		const columnsContainer = document.getElementById('aie-csv-columns');
		if (columnsContainer) {
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
		const label = prompt('Enter column name:');
		if (!label) return;

		const field = 'custom_' + label.toLowerCase().replace(/[^a-z0-9]/g, '_');
		
		this.addFieldToCSV({
			field,
			label,
			type: 'custom'
		});
	}

	/**
	 * Update column order after drag
	 */
	updateColumnOrder() {
		const columns = document.querySelectorAll('.aie-csv-column');
		const newOrder = [];

		columns.forEach(column => {
			const fieldKey = column.dataset.fieldKey;
			const field = this.selectedFields.find(f => f.key === fieldKey);
			if (field) {
				newOrder.push(field);
			}
		});

		this.selectedFields = newOrder;
	}

	/**
	 * Update CSV stats
	 */
	updateCSVStats() {
		const countElement = document.querySelector('.aie-step-3 .aie-columns-count');
		if (countElement) {
			console.log('Updating CSV stats. Selected fields:', this.selectedFields.length, this.selectedFields);
			countElement.textContent = this.selectedFields.length;
		}
	}

	/**
	 * Toggle placeholder visibility
	 */
	togglePlaceholder() {
		const dropzone = document.getElementById('aie-csv-dropzone');
		if (dropzone) {
			if (this.selectedFields.length > 0) {
				dropzone.classList.add('has-columns');
			} else {
				dropzone.classList.remove('has-columns');
			}
		}
	}

	/**
	 * Toggle next button
	 */
	toggleNextButton() {
		const nextBtn = document.querySelector('.aie-step-3 .aie-next-step');
		if (nextBtn) {
			const $nextBtn = jQuery(nextBtn);
			const isDisabled = this.selectedFields.length === 0;
			
			// Remove previous event handlers
			$nextBtn.off('mouseenter.tooltip mouseleave.tooltip');
			
			if (isDisabled) {
				nextBtn.disabled = true;
				
				// Show tooltip on hover
				$nextBtn.on('mouseenter.tooltip', () => {
					this.showNextButtonTooltip($nextBtn);
				});
				
				// Hide tooltip on mouse leave
				$nextBtn.on('mouseleave.tooltip', () => {
					this.hideNextButtonTooltip($nextBtn);
				});
			} else {
				nextBtn.disabled = false;
				// Hide tooltip if it's shown
				this.hideNextButtonTooltip($nextBtn);
			}
		}
	}

	/**
	 * Show custom tooltip on Next button
	 */
	showNextButtonTooltip($button) {
		// Remove any existing tooltips
		jQuery('.aie-custom-tooltip').remove();
		
		// Create tooltip element
		const $tooltip = jQuery('<div>')
			.addClass('aie-custom-tooltip aie-custom-pointer')
			.html(`
				<div class="aie-pointer-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="aie-pointer-content">
					<h3>No Fields Selected</h3>
					<p>Please select at least one field to continue with the export.</p>
				</div>
			`);
		
		// Append to body
		jQuery('body').append($tooltip);
		
		// Position tooltip
		const buttonOffset = $button.offset();
		const buttonWidth = $button.outerWidth();
		const tooltipWidth = $tooltip.outerWidth();
		const tooltipHeight = $tooltip.outerHeight();
		
		// Position above the button, centered
		const left = buttonOffset.left + (buttonWidth / 2) - (tooltipWidth / 2);
		const top = buttonOffset.top - tooltipHeight - 10; // 10px gap
		
		$tooltip.css({
			left: left + 'px',
			top: top + 'px',
			zIndex: 9999
		});
		
		// Fade in
		setTimeout(() => {
			$tooltip.addClass('aie-tooltip-visible');
		}, 10);
	}

	/**
	 * Hide custom tooltip
	 */
	hideNextButtonTooltip($button) {
		const $tooltip = jQuery('.aie-custom-tooltip');
		
		if ($tooltip.length) {
			$tooltip.removeClass('aie-tooltip-visible');
			
			// Remove after animation
			setTimeout(() => {
				$tooltip.remove();
			}, 200);
		}
	}

	/**
	 * Initialize field search
	 */
	initFieldSearch() {
		const searchInput = document.getElementById('aie-fields-search');
		if (!searchInput) return;

		searchInput.addEventListener('input', (e) => {
			const query = e.target.value.toLowerCase();
			this.filterFields(query);
		});
	}

	/**
	 * Initialize category toggle (collapse/expand)
	 */
	initCategoryToggle() {
		document.addEventListener('click', (e) => {
			// Handle "Add all" button
			if (e.target.classList.contains('aie-add-all-fields')) {
				e.stopPropagation();
				this.addAllFieldsFromCategory(e.target);
				return;
			}
			
			// Handle category toggle (only if not clicking the button)
			const categoryTitle = e.target.closest('.aie-field-category-title');
			if (!categoryTitle) return;
			
			// Don't toggle if clicking the "Add all" button
			if (e.target.classList.contains('aie-add-all-fields')) return;
			
			const category = categoryTitle.closest('.aie-field-category');
			if (category) {
				category.classList.toggle('aie-collapsed');
			}
		});
	}
	
	/**
	 * Add all fields from a category
	 */
	addAllFieldsFromCategory(button) {
		const category = button.closest('.aie-field-category');
		if (!category) return;
		
		const fieldItems = category.querySelectorAll('.aie-field-item:not([style*="display: none"])');
		
		fieldItems.forEach(item => {
			const fieldData = {
				field: item.dataset.field,
				label: item.dataset.label,
				type: item.dataset.type
			};
			
			// Check if field is not already added
			const exists = this.selectedFields.find(f => f.field === fieldData.field);
			if (!exists) {
				this.addFieldToCSV(fieldData);
			}
		});
	}

	/**
	 * Filter fields by search query
	 */
	filterFields(query) {
		const fieldItems = document.querySelectorAll('.aie-field-item');
		const categories = document.querySelectorAll('.aie-field-category');
		
		// If searching, expand all categories
		if (query.trim() !== '') {
			categories.forEach(category => {
				category.classList.remove('aie-collapsed');
			});
		}
		
		fieldItems.forEach(item => {
			const label = item.dataset.label.toLowerCase();
			const field = item.dataset.field.toLowerCase();
			
			if (label.includes(query) || field.includes(query)) {
				item.style.display = '';
			} else {
				item.style.display = 'none';
			}
		});
		
		// Hide empty categories when searching
		if (query.trim() !== '') {
			categories.forEach(category => {
				const visibleFields = category.querySelectorAll('.aie-field-item:not([style*="display: none"])');
				if (visibleFields.length === 0) {
					category.style.display = 'none';
				} else {
					category.style.display = '';
				}
			});
		} else {
			// Show all categories when not searching
			categories.forEach(category => {
				category.style.display = '';
			});
		}
	}

	/**
	 * Load fields for a specific group
	 */
	loadGroupFields(group) {
		if (group === 'wordpress') {
			// Already loaded in HTML
			return;
		}

		const content = document.querySelector(`[data-group="${group}"]`);
		if (!content) return;

		// Check if already loaded
		if (content.dataset.loaded === 'true') return;

		// Check if aieData is available
		if (typeof aieData === 'undefined') {
			console.error('aieData is not defined');
			return;
		}

		// Make AJAX request to load fields
		jQuery.ajax({
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_field_group',
				nonce: aieData.nonce,
				group: group,
				content_type: this.getCurrentContentType()
			},
			success: (response) => {
				if (response.success && response.data.fields) {
					this.renderGroupFields(content, response.data.fields);
					content.dataset.loaded = 'true';
				}
			}
		});
	}

	/**
	 * Render fields for a group
	 */
	renderGroupFields(container, fields) {
		const loadingEl = container.querySelector('.aie-acf-loading, .aie-yoast-loading, .aie-meta-loading');
		if (loadingEl) {
			loadingEl.remove();
		}

		const category = document.createElement('div');
		category.className = 'aie-field-category';

		const grid = document.createElement('div');
		grid.className = 'aie-fields-grid';

		fields.forEach(field => {
			const item = document.createElement('div');
			item.className = 'aie-field-item';
			item.draggable = true;
			item.dataset.field = field.key;
			item.dataset.label = field.label;
			item.dataset.type = field.type || 'text';

			const iconClass = this.getFieldIcon(field.type);

			item.innerHTML = `
				<span class="aie-field-icon dashicons ${iconClass}"></span>
				<span class="aie-field-label">${this.escapeHtml(field.label)}</span>
				<span class="aie-field-type">${this.escapeHtml(field.type)}</span>
			`;

			grid.appendChild(item);
		});

		category.appendChild(grid);
		container.appendChild(category);
	}

	/**
	 * Get current content type from step 1
	 */
	getCurrentContentType() {
		const selectedType = document.querySelector('input[name="content_type"]:checked');
		if (!selectedType) return 'post';
		
		const contentType = selectedType.value;
		
		// For custom_post_types, get the specific post type from the selector
		if (contentType === 'custom_post_types') {
			const postTypeSelector = document.querySelector('.aie-post-type-selector');
			if (postTypeSelector && postTypeSelector.value) {
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
		console.log('loadDynamicFields called. Current selectedFields:', this.selectedFields.length);
		
		// Get selected post type from step 1
		this.selectedPostType = this.getCurrentContentType();
		const contentType = this.getCurrentRealContentType();
		
		// Load static fields based on content type
		this.loadStaticFields();
		
		// Load taxonomies for this post type
		this.loadTaxonomies();
		
		// Load custom fields for this post type
		this.loadCustomFields();
		
		// Check if ACF is active and load ACF fields
		this.checkAndLoadACF();
		
		// Check if Yoast is active and load Yoast fields (skip for non-content types)
		const excludedTypes = [
			'media', 
			'user', 
			'menu', 
			'block_theme_settings', 
			'taxonomy',
			'database_table',
			'woo_attribute',
			'woo_coupon',
			'woo_order'
		];
		if (!excludedTypes.includes(contentType)) {
			this.checkAndLoadYoast();
		}
	}

	/**
	 * Reload dynamic fields (when post type changes)
	 */
	reloadDynamicFields() {
		console.log('reloadDynamicFields called - clearing and reloading...');
		
		// Hide and clear ALL dynamic categories (including static ones)
		const allCategories = document.querySelectorAll('.aie-field-category');
		allCategories.forEach(category => {
			// Skip custom fields, taxonomies, ACF, Yoast - they will be handled separately
			if (!category.classList.contains('aie-taxonomies-category') &&
				!category.classList.contains('aie-custom-fields-category') &&
				!category.classList.contains('aie-acf-fields-category') &&
				!category.classList.contains('aie-yoast-fields-category')) {
				// This is a static category, hide and clear it
				category.style.display = 'none';
				const grid = category.querySelector('.aie-fields-grid');
				if (grid) grid.innerHTML = '';
			}
		});
		
		// Hide and clear dynamic categories
		const taxonomiesCategory = document.querySelector('.aie-taxonomies-category');
		const customFieldsCategory = document.querySelector('.aie-custom-fields-category');
		const acfCategory = document.querySelector('.aie-acf-fields-category');
		const yoastCategory = document.querySelector('.aie-yoast-fields-category');
		
		if (taxonomiesCategory) {
			taxonomiesCategory.style.display = 'none';
			const grid = taxonomiesCategory.querySelector('.aie-taxonomies-grid');
			if (grid) grid.innerHTML = '';
		}
		
		if (customFieldsCategory) {
			customFieldsCategory.style.display = 'none';
			const grid = customFieldsCategory.querySelector('.aie-custom-fields-grid');
			if (grid) grid.innerHTML = '';
		}
		
		if (acfCategory) {
			acfCategory.style.display = 'none';
			const grid = acfCategory.querySelector('.aie-acf-fields-grid');
			if (grid) {
				grid.innerHTML = '<div class="aie-acf-loading"><span class="spinner is-active"></span><p>Loading ACF fields...</p></div>';
			}
		}
		
		if (yoastCategory) {
			yoastCategory.style.display = 'none';
			const grid = yoastCategory.querySelector('.aie-yoast-fields-grid');
			if (grid) {
				grid.innerHTML = '<div class="aie-yoast-loading"><span class="spinner is-active"></span><p>Loading Yoast SEO fields...</p></div>';
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
		if (typeof window.aieExportModule === 'undefined' || !window.aieExportModule.getFieldsByContentType) {
			console.error('Export module not found or getFieldsByContentType method missing');
			return;
		}
		
		const contentType = this.getCurrentRealContentType();
		const fieldGroups = window.aieExportModule.getFieldsByContentType(contentType);
		
		console.log('Loading static fields for content type:', contentType, fieldGroups);
		
		// Find the container for static fields
		const container = document.querySelector('.aie-fields-library-body');
		if (!container) return;
		
		// Clear existing static categories (keep dynamic ones)
		const existingStatic = container.querySelectorAll('.aie-field-category:not(.aie-taxonomies-category):not(.aie-custom-fields-category):not(.aie-acf-fields-category):not(.aie-yoast-fields-category)');
		existingStatic.forEach(cat => cat.remove());
		
		// Get reference to taxonomies category to insert before it
		const taxonomiesCategory = container.querySelector('.aie-taxonomies-category');
		
		// Render each field group as a category
		fieldGroups.forEach((group, index) => {
			// Skip Custom Filters group and selector groups (they're only for step 2)
			if (group.label === 'Custom Filters' || 
				group.label === 'Post Type Selection' || 
				group.label === 'Taxonomy Selection') {
				return;
			}
			
			const category = this.createFieldCategory(group, index === 0);
			
			// Insert before taxonomies category
			if (taxonomiesCategory) {
				container.insertBefore(category, taxonomiesCategory);
			} else {
				container.appendChild(category);
			}
		});
	}

	/**
	 * Create a field category element
	 */
	createFieldCategory(group, isOpen = false) {
		const category = document.createElement('div');
		category.className = 'aie-field-category' + (isOpen ? '' : ' aie-collapsed');
		
		const title = document.createElement('h4');
		title.className = 'aie-field-category-title';
		title.innerHTML = `
			<span class="dashicons dashicons-arrow-down-alt2 aie-category-toggle"></span>
			<span class="dashicons dashicons-admin-post"></span>
			${this.escapeHtml(group.label)}
			<button type="button" class="aie-add-all-fields" title="Add all fields from this category">
				Add all
			</button>
		`;
		
		const grid = document.createElement('div');
		grid.className = 'aie-fields-grid';
		
		// Add fields
		if (group.options && Array.isArray(group.options)) {
			group.options.forEach(option => {
				// Skip special filter types
				if (option.type === 'custom_field' || option.type === 'taxonomy_filter' || 
					option.type === 'post_type_selector' || option.type === 'taxonomy_selector' || 
					option.type === 'table_selector') {
					return;
				}
				
				const field = this.createFieldItem(option);
				grid.appendChild(field);
			});
		}
		
		category.appendChild(title);
		category.appendChild(grid);
		
		return category;
	}

	/**
	 * Create a field item element
	 */
	createFieldItem(option) {
		const item = document.createElement('div');
		item.className = 'aie-field-item';
		item.draggable = true;
		item.dataset.field = option.value;
		item.dataset.label = option.label;
		item.dataset.type = option.type || 'text';
		
		const iconClass = this.getFieldIcon(option.type);
		
		item.innerHTML = `
			<span class="aie-field-icon dashicons ${iconClass}"></span>
			<span class="aie-field-label">${this.escapeHtml(option.label)}</span>
			<span class="aie-field-type">${this.escapeHtml(option.type || 'text')}</span>
		`;
		
		return item;
	}

	/**
	 * Get real content type (for custom_post_types returns the radio value, not selector value)
	 */
	getCurrentRealContentType() {
		const selectedType = document.querySelector('input[name="content_type"]:checked');
		return selectedType ? selectedType.value : 'post';
	}

	/**
	 * Load taxonomies for selected post type
	 */
	loadTaxonomies() {
		if (typeof aieData === 'undefined') return;
		
		jQuery.ajax({
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_taxonomies',
				nonce: aieData.nonce,
				post_type: this.selectedPostType
			},
			success: (response) => {
				console.log('Taxonomies response:', response);
				if (response.success && response.data.taxonomies && response.data.taxonomies.length > 0) {
					this.renderTaxonomies(response.data.taxonomies);
					// Show the category
					const category = document.querySelector('.aie-taxonomies-category');
					if (category) {
						category.style.display = '';
					}
				} else {
					// Hide the category if no taxonomies
					const category = document.querySelector('.aie-taxonomies-category');
					if (category) {
						category.style.display = 'none';
					}
				}
			},
			error: (xhr, status, error) => {
				console.error('Taxonomies AJAX error:', error, xhr.responseText);
			}
		});
	}

	/**
	 * Render taxonomies
	 */
	renderTaxonomies(taxonomies) {
		const grid = document.querySelector('.aie-taxonomies-grid');
		if (!grid) return;
		
		grid.innerHTML = '';
		
		taxonomies.forEach(taxonomy => {
			const item = document.createElement('div');
			item.className = 'aie-field-item';
			item.draggable = true;
			item.dataset.field = 'taxonomy_' + taxonomy.name;
			item.dataset.label = taxonomy.label;
			item.dataset.type = 'taxonomy';

			item.innerHTML = `
				<span class="aie-field-icon dashicons dashicons-category"></span>
				<span class="aie-field-label">${this.escapeHtml(taxonomy.label)}</span>
				<span class="aie-field-type">taxonomy</span>
			`;

			grid.appendChild(item);
		});
	}

	/**
	 * Load custom fields for selected post type
	 */
	loadCustomFields() {
		if (typeof aieData === 'undefined') return;
		
		jQuery.ajax({
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_custom_fields',
				nonce: aieData.nonce,
				post_type: this.selectedPostType
			},
			success: (response) => {
				if (response.success && response.data.fields && response.data.fields.length > 0) {
					this.renderCustomFields(response.data.fields);
					// Show the category
					const category = document.querySelector('.aie-custom-fields-category');
					if (category) {
						category.style.display = '';
					}
				} else {
					// Hide the category if no custom fields
					const category = document.querySelector('.aie-custom-fields-category');
					if (category) {
						category.style.display = 'none';
					}
				}
			}
		});
	}

	/**
	 * Render custom fields
	 */
	renderCustomFields(fields) {
		const grid = document.querySelector('.aie-custom-fields-grid');
		if (!grid) return;
		
		grid.innerHTML = '';
		
		fields.forEach(field => {
			const item = document.createElement('div');
			item.className = 'aie-field-item';
			item.draggable = true;
			item.dataset.field = 'meta_' + field.name;
			item.dataset.label = field.name;
			item.dataset.type = 'meta';

			item.innerHTML = `
				<span class="aie-field-icon dashicons dashicons-admin-generic"></span>
				<span class="aie-field-label">${this.escapeHtml(field.name)}</span>
				<span class="aie-field-type">meta</span>
			`;

			grid.appendChild(item);
		});
	}

	/**
	 * Check if ACF is active and load ACF fields
	 */
	checkAndLoadACF() {
		if (typeof aieData === 'undefined') return;
		
		jQuery.ajax({
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_acf_fields',
				nonce: aieData.nonce,
				post_type: this.selectedPostType
			},
			success: (response) => {
				if (response.success && response.data.fields && response.data.fields.length > 0) {
					this.renderACFFields(response.data.fields);
					// Show the ACF category
					const category = document.querySelector('.aie-acf-fields-category');
					if (category) {
						category.style.display = '';
					}
				} else {
					// Hide the category if no ACF fields
					const category = document.querySelector('.aie-acf-fields-category');
					if (category) {
						category.style.display = 'none';
					}
				}
			}
		});
	}

	/**
	 * Render ACF fields
	 */
	renderACFFields(fields) {
		const grid = document.querySelector('.aie-acf-fields-grid');
		if (!grid) return;
		
		// Clear grid completely (removes loading spinner and any existing fields)
		grid.innerHTML = '';
		
		fields.forEach(field => {
			const item = document.createElement('div');
			item.className = 'aie-field-item';
			item.draggable = true;
			item.dataset.field = 'acf_' + field.name;
			item.dataset.label = field.label;
			item.dataset.type = 'acf';

			item.innerHTML = `
				<span class="aie-field-icon dashicons dashicons-admin-settings"></span>
				<span class="aie-field-label">${this.escapeHtml(field.label)}</span>
				<span class="aie-field-type">acf</span>
			`;

			grid.appendChild(item);
		});
	}

	/**
	 * Check if Yoast is active and load Yoast fields
	 */
	checkAndLoadYoast() {
		if (typeof aieData === 'undefined') return;
		
		jQuery.ajax({
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_yoast_fields',
				nonce: aieData.nonce,
				post_type: this.selectedPostType
			},
			success: (response) => {
				if (response.success && response.data.fields && response.data.fields.length > 0) {
					this.renderYoastFields(response.data.fields);
					// Show the Yoast category
					const category = document.querySelector('.aie-yoast-fields-category');
					if (category) {
						category.style.display = '';
					}
				} else {
					// Hide the category if no Yoast fields
					const category = document.querySelector('.aie-yoast-fields-category');
					if (category) {
						category.style.display = 'none';
					}
				}
			}
		});
	}

	/**
	 * Render Yoast fields
	 */
	renderYoastFields(fields) {
		const grid = document.querySelector('.aie-yoast-fields-grid');
		if (!grid) return;
		
		// Clear grid completely (removes loading spinner and any existing fields)
		grid.innerHTML = '';
		
		fields.forEach(field => {
			const item = document.createElement('div');
			item.className = 'aie-field-item';
			item.draggable = true;
			item.dataset.field = 'yoast_' + field.name;
			item.dataset.label = field.label;
			item.dataset.type = 'yoast';

			item.innerHTML = `
				<span class="aie-field-icon dashicons dashicons-chart-line"></span>
				<span class="aie-field-label">${this.escapeHtml(field.label)}</span>
				<span class="aie-field-type">yoast</span>
			`;

			grid.appendChild(item);
		});
	}

	/**
	 * Initialize Field Functions Modal
	 */
	initFieldFunctionsModal() {
		const modal = document.getElementById('aie-field-functions-modal');
		if (!modal) return;

		// Close modal
		modal.querySelector('.aie-modal-close')?.addEventListener('click', () => {
			this.closeFieldFunctionsModal();
		});

		modal.querySelector('.aie-modal-cancel')?.addEventListener('click', () => {
			this.closeFieldFunctionsModal();
		});

		// Save functions
		modal.querySelector('.aie-save-field-functions')?.addEventListener('click', () => {
			this.saveFieldFunctions();
		});

		// Test pipeline
		modal.querySelector('.aie-test-pipeline')?.addEventListener('click', () => {
			this.testFunctionPipeline();
		});

		// Functions search
		modal.querySelector('#aie-functions-search')?.addEventListener('input', (e) => {
			this.filterFunctions(e.target.value);
		});

		// Functions filter
		modal.querySelectorAll('input[name="functions-filter"]').forEach(radio => {
			radio.addEventListener('change', (e) => {
				this.filterFunctionsByCategory(e.target.value);
			});
		});

		// Create new function button
		modal.querySelector('.aie-create-new-function')?.addEventListener('click', (e) => {
			e.preventDefault();
			this.createNewFunction();
		});

		// Initialize sortable for function pipeline
		this.initFunctionPipelineSortable();
	}

	/**
	 * Open field functions modal
	 */
	openFieldFunctionsModal(fieldKey) {
		const field = this.selectedFields.find(f => f.key === fieldKey);
		if (!field) return;

		this.currentEditingField = fieldKey;

		const modal = document.getElementById('aie-field-functions-modal');
		if (!modal) return;

		// Set field info
		modal.querySelector('.aie-current-field-label').textContent = field.label;
		modal.querySelector('.aie-current-field-type').textContent = field.type;

		// Load current functions
		this.loadCurrentFunctions(fieldKey);

		// Load available functions
		this.renderAvailableFunctions();

		// Show modal
		modal.style.display = 'flex';
		document.body.classList.add('aie-modal-open');
	}

	/**
	 * Close field functions modal
	 */
	closeFieldFunctionsModal() {
		const modal = document.getElementById('aie-field-functions-modal');
		if (modal) {
			modal.style.display = 'none';
			document.body.classList.remove('aie-modal-open');
			
			// Hide preview results
			const previewResult = modal.querySelector('#aie-preview-result');
			if (previewResult) {
				previewResult.style.display = 'none';
			}
			
			// Clear preview input
			const previewInput = modal.querySelector('#aie-preview-input');
			if (previewInput) {
				previewInput.value = '';
			}
		}
		this.currentEditingField = null;
	}

	/**
	 * Load current functions for field
	 */
	loadCurrentFunctions(fieldKey) {
		const container = document.getElementById('aie-function-items');
		if (!container) return;

		container.innerHTML = '';

		const functions = this.fieldFunctions[fieldKey] || [];
		const noFunctionsEl = document.querySelector('.aie-no-functions');

		if (functions.length === 0) {
			if (noFunctionsEl) noFunctionsEl.style.display = 'block';
			this.updateFunctionsCount(0);
			return;
		}

		if (noFunctionsEl) noFunctionsEl.style.display = 'none';

		functions.forEach(funcId => {
			const func = this.availableFunctions.find(f => f.id == funcId);
			if (func) {
				this.addFunctionToPipeline(func, false);
			}
		});

		this.updateFunctionsCount(functions.length);
	}

	/**
	 * Add function to pipeline
	 */
	addFunctionToPipeline(func, updateArray = true) {
		const container = document.getElementById('aie-function-items');
		if (!container) return;

		const item = document.createElement('div');
		item.className = 'aie-function-item';
		item.dataset.functionId = func.id;

		item.innerHTML = `
			<span class="aie-function-handle dashicons dashicons-menu"></span>
			<div class="aie-function-info">
				<strong class="aie-function-name">${this.escapeHtml(func.name)}</strong>
				<span class="aie-function-desc">${this.escapeHtml(func.description || '')}</span>
			</div>
			<div class="aie-function-actions">
				<button type="button" class="button-small aie-remove-function" data-function-id="${func.id}">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		`;

		// Remove function event
		item.querySelector('.aie-remove-function').addEventListener('click', () => {
			item.remove();
			this.updatePipelineFunctions();
			this.updateFunctionsCount();
			this.toggleNoFunctionsMessage();
		});

		container.appendChild(item);

		if (updateArray) {
			this.updatePipelineFunctions();
			this.updateFunctionsCount();
		}

		this.toggleNoFunctionsMessage();
	}

	/**
	 * Update pipeline functions array
	 */
	updatePipelineFunctions() {
		if (!this.currentEditingField) return;

		const items = document.querySelectorAll('.aie-function-item');
		const functionIds = Array.from(items).map(item => item.dataset.functionId);

		this.fieldFunctions[this.currentEditingField] = functionIds;
	}

	/**
	 * Update functions count
	 */
	updateFunctionsCount(count = null) {
		const countEl = document.querySelector('.aie-functions-count');
		if (!countEl) return;

		if (count === null) {
			const items = document.querySelectorAll('.aie-function-item');
			count = items.length;
		}

		countEl.textContent = `(${count})`;
	}

	/**
	 * Toggle no functions message
	 */
	toggleNoFunctionsMessage() {
		const noFunctionsEl = document.querySelector('.aie-no-functions');
		const items = document.querySelectorAll('.aie-function-item');

		if (noFunctionsEl) {
			noFunctionsEl.style.display = items.length === 0 ? 'block' : 'none';
		}
	}

	/**
	 * Initialize function pipeline sortable
	 */
	initFunctionPipelineSortable() {
		const container = document.getElementById('aie-function-items');
		if (!container || !jQuery.fn.sortable) return;

		jQuery(container).sortable({
			handle: '.aie-function-handle',
			placeholder: 'aie-function-item-placeholder',
			update: () => {
				this.updatePipelineFunctions();
			}
		});
	}

	/**
	 * Save field functions
	 */
	saveFieldFunctions() {
		this.updatePipelineFunctions();
		
		// Update column badge
		const column = document.querySelector(`[data-field-key="${this.currentEditingField}"]`);
		if (column) {
			const functions = this.fieldFunctions[this.currentEditingField] || [];
			const hasFunctions = functions.length > 0;

			if (hasFunctions) {
				column.classList.add('has-functions');
				
				let badge = column.querySelector('.aie-column-badge');
				if (!badge) {
					badge = document.createElement('div');
					badge.className = 'aie-column-badge';
					column.appendChild(badge);
				}
				badge.innerHTML = `
					<span class="dashicons dashicons-admin-generic"></span>
					${functions.length} function(s)
				`;
			} else {
				column.classList.remove('has-functions');
				const badge = column.querySelector('.aie-column-badge');
				if (badge) badge.remove();
			}
		}

		this.closeFieldFunctionsModal();
		this.showNotice(window.aieData.i18n.functionsSavedSuccess, 'success');
	}

	/**
	 * Load available functions
	 */
	loadFunctions() {
		// Check if aieData is available
		if (typeof aieData === 'undefined') {
			console.error('aieData is not defined');
			return;
		}

		jQuery.ajax({
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_functions',
				nonce: aieData.nonce
			},
			success: (response) => {
				if (response.success && response.data.functions) {
					this.availableFunctions = response.data.functions;
					this.renderAvailableFunctions();
				}
			}
		});
	}

	/**
	 * Render available functions
	 */
	renderAvailableFunctions() {
		const container = document.getElementById('aie-functions-list');
		if (!container) return;

		const loadingEl = container.querySelector('.aie-functions-loading');
		if (loadingEl) loadingEl.remove();

		container.innerHTML = '';

		if (this.availableFunctions.length === 0) {
			// Show empty state
			const emptyState = document.createElement('div');
			emptyState.className = 'aie-functions-empty-state';
			emptyState.innerHTML = `
				<span class="dashicons dashicons-info"></span>
				<p>${this.escapeHtml('No functions available yet.')}</p>
				<p>${this.escapeHtml('Create your first custom function to get started.')}</p>
			`;
			container.appendChild(emptyState);
			return;
		}

		this.availableFunctions.forEach(func => {
			const item = document.createElement('div');
			item.className = 'aie-function-list-item';
			item.dataset.functionId = func.id;
			item.dataset.category = func.category || 'custom';

			item.innerHTML = `
				<div class="aie-function-list-info">
					<span class="aie-function-list-name">${this.escapeHtml(func.name)}</span>
					<span class="aie-function-list-desc">${this.escapeHtml(func.description || '')}</span>
				</div>
				<button type="button" class="button button-small">Add</button>
			`;

			item.querySelector('button').addEventListener('click', () => {
				this.addFunctionToPipeline(func);
			});

			container.appendChild(item);
		});
	}

	/**
	 * Filter functions by search query
	 */
	filterFunctions(query) {
		const items = document.querySelectorAll('.aie-function-list-item');
		const lowerQuery = query.toLowerCase();

		items.forEach(item => {
			const name = item.querySelector('.aie-function-list-name').textContent.toLowerCase();
			const desc = item.querySelector('.aie-function-list-desc').textContent.toLowerCase();

			if (name.includes(lowerQuery) || desc.includes(lowerQuery)) {
				item.style.display = '';
			} else {
				item.style.display = 'none';
			}
		});
	}

	/**
	 * Filter functions by category
	 */
	filterFunctionsByCategory(category) {
		const items = document.querySelectorAll('.aie-function-list-item');
		const emptyState = document.querySelector('.aie-functions-empty-state');

		// Don't filter if only empty state is shown
		if (emptyState && items.length === 0) {
			return;
		}

		let visibleCount = 0;

		items.forEach(item => {
			if (category === 'all' || item.dataset.category === category) {
				item.style.display = '';
				visibleCount++;
			} else {
				item.style.display = 'none';
			}
		});

		// Show/hide no results message for filtered category
		this.toggleNoResultsMessage(visibleCount, category);
	}

	/**
	 * Toggle no results message
	 */
	toggleNoResultsMessage(visibleCount, category) {
		const container = document.getElementById('aie-functions-list');
		if (!container) return;

		let noResults = container.querySelector('.aie-functions-no-results');

		if (visibleCount === 0 && category !== 'all') {
			if (!noResults) {
				noResults = document.createElement('div');
				noResults.className = 'aie-functions-no-results';
				container.appendChild(noResults);
			}
			
			const categoryLabel = category === 'library' ? 'library' : 'custom';
			noResults.innerHTML = `
				<span class="dashicons dashicons-info"></span>
				<p>${this.escapeHtml(`No ${categoryLabel} functions found.`)}</p>
			`;
			noResults.style.display = 'block';
		} else {
			if (noResults) {
				noResults.style.display = 'none';
			}
		}
	}

	/**
	 * Test function pipeline
	 */
	testFunctionPipeline() {
		const input = document.getElementById('aie-preview-input').value;
		if (!input) {
			this.showNotice(window.aieData.i18n.enterTestValue, 'warning');
			return;
		}

		const functionIds = this.fieldFunctions[this.currentEditingField] || [];
		if (functionIds.length === 0) {
			this.showNotice(window.aieData.i18n.noFunctionsToTest, 'warning');
			return;
		}

		// Check if aieData is available
		if (typeof aieData === 'undefined') {
			console.error('aieData is not defined');
			this.showNotice(window.aieData.i18n.configErrorAieData, 'error');
			return;
		}

		// Make AJAX request to test pipeline
		jQuery.ajax({
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_test_function_pipeline',
				nonce: aieData.nonce,
				value: input,
				functions: functionIds
			},
			success: (response) => {
				if (response.success) {
					this.renderPipelinePreview(input, response.data.steps);
				} else {
					this.showNotice(response.data.message || window.aieData.i18n.testFailed, 'error');
				}
			},
			error: () => {
				this.showNotice(window.aieData.i18n.errorTestingPipeline, 'error');
			}
		});
	}

	/**
	 * Render pipeline preview
	 */
	renderPipelinePreview(initialValue, steps) {
		const container = document.getElementById('aie-preview-result');
		if (!container) return;

		const stepsContainer = container.querySelector('.aie-preview-steps');
		stepsContainer.innerHTML = '';

		// Initial value
		stepsContainer.appendChild(this.createPreviewStep(0, 'Input', initialValue));

		// Each function step
		steps.forEach((step, index) => {
			stepsContainer.appendChild(
				this.createPreviewStep(
					index + 1,
					step.function_name,
					step.output,
					step.error
				)
			);
		});

		container.style.display = 'block';
	}

	/**
	 * Create preview step element
	 */
	createPreviewStep(number, name, value, error = false) {
		const step = document.createElement('div');
		step.className = 'aie-preview-step';

		step.innerHTML = `
			<div class="aie-preview-step-number">${number}</div>
			<div class="aie-preview-step-name">${this.escapeHtml(name)}</div>
			<span class="aie-preview-step-arrow dashicons dashicons-arrow-right-alt"></span>
			<div class="aie-preview-step-value ${error ? 'error' : ''}">
				${this.escapeHtml(error ? `Error: ${value}` : value)}
			</div>
		`;

		return step;
	}

	/**
	 * Get field icon class
	 */
	getFieldIcon(type) {
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
			custom: 'dashicons-admin-generic'
		};

		return icons[type] || 'dashicons-admin-generic';
	}

	/**
	 * Escape HTML
	 */
	escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, m => map[m]);
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
		if (this.autoScrollInterval) {
			clearInterval(this.autoScrollInterval);
			this.autoScrollInterval = null;
		}
	}

	/**
	 * Handle auto-scroll when dragging near edges
	 */
	handleAutoScroll(e) {
		const scrollSpeed = 15; // Increased from 10
		const scrollZone = 150; // Increased from 100 - larger trigger zone
		const viewportHeight = window.innerHeight;
		const mouseY = e.clientY;

		// Auto-scroll when mouse is near edges
		if (mouseY < scrollZone) {
			// Scroll up when near top
			const intensity = 1 - (mouseY / scrollZone);
			const scrollAmount = scrollSpeed * intensity;
			window.scrollBy(0, -scrollAmount);
		} else if (mouseY > viewportHeight - scrollZone) {
			// Scroll down when near bottom
			const intensity = (mouseY - (viewportHeight - scrollZone)) / scrollZone;
			const scrollAmount = scrollSpeed * intensity;
			window.scrollBy(0, scrollAmount);
		}
	}

	/**
	 * Show notice
	 */
	showNotice(message, type = 'info') {
		// You can implement a toast notification system here
		console.log(`[${type.toUpperCase()}] ${message}`);
	}

	/**
	 * Create new function
	 */
	createNewFunction() {
		// Redirect to Functions management page
		if (typeof aieData !== 'undefined' && aieData.functionsUrl) {
			window.open(aieData.functionsUrl, '_blank');
		} else {
			// Fallback - go to admin page
			window.open('/wp-admin/admin.php?page=wp-aie-functions', '_blank');
		}
	}

	/**
	 * Get selected fields data
	 */
	getSelectedFieldsData() {
		return {
			fields: this.selectedFields,
			functions: this.fieldFunctions
		};
	}

	/**
	 * Set selected fields (for loading saved state)
	 */
	setSelectedFieldsData(data) {
		if (data.fields) {
			this.selectedFields = [];
			data.fields.forEach(field => {
				this.addFieldToCSV(field);
			});
		}

		if (data.functions) {
			this.fieldFunctions = data.functions;
			
			// Update column badges
			Object.keys(this.fieldFunctions).forEach(fieldKey => {
				const column = document.querySelector(`[data-field-key="${fieldKey}"]`);
				if (column && this.fieldFunctions[fieldKey].length > 0) {
					column.classList.add('has-functions');
				}
			});
		}
	}
}
