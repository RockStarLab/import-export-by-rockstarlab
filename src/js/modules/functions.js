/**
 * Custom Functions Management Module
 *
 * Handles CRUD operations for custom transformation functions
 */

import {
	showNotice,
	showError,
	showModalError,
	clearModalErrors,
	confirmDialog,
} from '../utils/notifications';
import FunctionLibrary from './function_library';

const FunctionsModule = {
	currentPage: 1,
	perPage: 20,
	totalPages: 1,
	totalItems: 0,
	filters: {
		search: '',
	},
	codeEditor: null, // CodeMirror instance

	/**
	 * Initialize the module
	 */
	init() {
		if ( ! document.getElementById( 'rsl-ie-functions' ) ) {
			return;
		}

		this.bindEvents();
		this.loadFunctions();

		// Initialize library module
		FunctionLibrary.init( this );
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		// New function button
		document
			.querySelector( '.aie-new-function' )
			?.addEventListener( 'click', () => {
				this.openEditorModal();
			} );

		// Browse library button
		document
			.querySelector( '.aie-browse-library' )
			?.addEventListener( 'click', () => {
				FunctionLibrary.openLibrary();
			} );

		// Search with debounce
		let searchTimeout;
		document
			.getElementById( 'aie-filter-search' )
			?.addEventListener( 'input', ( e ) => {
				clearTimeout( searchTimeout );
				searchTimeout = setTimeout( () => {
					this.filters.search = e.target.value;
					this.currentPage = 1;
					this.loadFunctions();
				}, 500 );
			} );

		// Clear filters
		document
			.querySelector( '.aie-filter-clear' )
			?.addEventListener( 'click', () => {
				this.clearFilters();
			} );

		// Pagination
		document
			.querySelector( '.aie-prev-page' )
			?.addEventListener( 'click', () => {
				if ( this.currentPage > 1 ) {
					this.currentPage--;
					this.loadFunctions();
				}
			} );

		document
			.querySelector( '.aie-next-page' )
			?.addEventListener( 'click', () => {
				if ( this.currentPage < this.totalPages ) {
					this.currentPage++;
					this.loadFunctions();
				}
			} );

		// Modal controls
		document
			.querySelectorAll( '.aie-modal-close, .aie-modal-cancel' )
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', ( e ) => {
					const modal = e.target.closest( '.aie-modal' );
					if ( modal ) {
						this.closeModal( modal );
					}
				} );
			} );

		// Save function
		document
			.querySelector( '.aie-save-function' )
			?.addEventListener( 'click', () => {
				this.saveFunction();
			} );

		document
			.querySelector( '.aie-test-function' )
			?.addEventListener( 'click', () => {
				this.testFunction();
			} );

		// AI Generate button
		document
			.querySelector( '.aie-generate-with-ai' )
			?.addEventListener( 'click', () => {
				this.openAIPromptModal();
			} );

		// Close modal on backdrop click
		document
			.querySelectorAll( '.aie-modal-backdrop' )
			.forEach( ( backdrop ) => {
				backdrop.addEventListener( 'click', ( e ) => {
					const modal = e.target.closest( '.aie-modal' );
					if ( modal ) {
						this.closeModal( modal );
					}
				} );
			} );
	},

	/**
	 * Load functions from server
	 */
	async loadFunctions() {
		const tbody = document.getElementById( 'aie-functions-tbody' );
		if ( ! tbody ) {
			return;
		}

		// Show loading
		tbody.innerHTML = `
			<tr class="aie-loading-row">
				<td colspan="3" style="text-align:center;">
					<span class="spinner is-active"></span>
					${ window.aieData?.i18n?.loading || 'Loading...' }
				</td>
			</tr>
		`;

		try {
			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
					body: new URLSearchParams( {
						action: 'rsl_ie_functions_get_all',
						nonce: window.aieData?.nonce || '',
						search: this.filters.search,
						page: this.currentPage,
						per_page: this.perPage,
				} ),
			} );

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error(
					data.message || data.data?.message || window.aieData?.i18n?.failedToLoadFunctions || 'Failed to load functions'
				);
			}

			this.totalPages = data.data.total_pages || 1;
			this.totalItems = data.data.total || 0;

			this.renderTable( data.data.functions || [] );
			this.updatePagination();
		} catch ( error ) {
			tbody.innerHTML = `
				<tr>
					<td colspan="4" style="text-align:center; color:#dc3232;">
						<span class="dashicons dashicons-warning"></span>
						${ error.message }
					</td>
				</tr>
			`;
		}
	},

	/**
	 * Render functions table
	 */
	renderTable( functions ) {
		const tbody = document.getElementById( 'aie-functions-tbody' );
		if ( ! tbody ) {
			return;
		}

		if ( functions.length === 0 ) {
			tbody.innerHTML = `
				<tr>
					<td colspan="4" style="text-align:center; padding:40px;">
						<div style="display:flex; flex-direction:column; align-items:center; gap:10px;">
							<span class="dashicons dashicons-info" style="font-size:48px; opacity:0.3;"></span>
							<p style="margin:23px 0 0 0; color:#666;">
								${
									window.aieData?.i18n?.no_functions ||
									'No functions found. Create your first function or browse the library.'
								}
							</p>
						</div>
					</td>
				</tr>
			`;
			return;
		}

		tbody.innerHTML = functions
			.map(
				( func ) => `
			<tr data-function-id="${ func.id }">
				<td class="column-name">
					<strong>${ this.escapeHtml( func.name ) }</strong>
				</td>
				<td class="column-description">
					${
						func.description
							? this.escapeHtml( func.description )
							: `<em style="color:#999;">${ window.aieData?.i18n?.noDescription || 'No description' }</em>`
					}
				</td>
				<td class="column-actions">
					<button type="button" class="button button-small aie-edit-function" data-id="${
						func.id
					}" title="${ window.aieData?.i18n?.editButton || 'Edit' }">
						<span class="dashicons dashicons-edit"></span>
					</button>
					${
						// Hide delete button for library snippets
						// Check type='library' or source starts with 'library:' or id starts with 'snippet_'
						func.type !== 'library' && 
						! ( func.source && func.source.startsWith( 'library:' ) ) &&
						! ( func.id && func.id.toString().startsWith( 'snippet_' ) )
							? `<button type="button" class="button button-small aie-delete-function" data-id="${
									func.id
								}" title="${ window.aieData?.i18n?.deleteButton || 'Delete' }">
								<span class="dashicons dashicons-trash"></span>
							</button>`
							: ''
					}
				</td>
			</tr>
		`
			)
			.join( '' );

		// Bind action buttons
		tbody.querySelectorAll( '.aie-edit-function' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				const id = e.currentTarget.dataset.id;
				this.openEditorModal( id );
			} );
		} );

		tbody.querySelectorAll( '.aie-delete-function' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', async ( e ) => {
				const id = e.currentTarget.dataset.id;
				const confirmed = await confirmDialog(
					window.aieData?.i18n?.confirm_delete ||
						'Are you sure you want to delete this function?'
				);
				if ( confirmed ) {
					this.deleteFunction( id );
				}
			} );
		} );
	},

	/**
	 * Update pagination controls
	 */
	updatePagination() {
		const currentPageEl = document.querySelector( '.aie-current-page' );
		const totalPagesEl = document.querySelector( '.aie-total-pages' );
		const prevBtn = document.querySelector( '.aie-prev-page' );
		const nextBtn = document.querySelector( '.aie-next-page' );
		const paginationInfo = document.querySelector( '.aie-pagination-info' );

		if ( currentPageEl ) {
			currentPageEl.textContent = this.currentPage;
		}
		if ( totalPagesEl ) {
			totalPagesEl.textContent = this.totalPages;
		}

		if ( prevBtn ) {
			prevBtn.disabled = this.currentPage <= 1;
		}
		if ( nextBtn ) {
			nextBtn.disabled = this.currentPage >= this.totalPages;
		}

		if ( paginationInfo ) {
			const start = ( this.currentPage - 1 ) * this.perPage + 1;
			const end = Math.min(
				this.currentPage * this.perPage,
				this.totalItems
			);
			const text = window.aieData?.i18n?.showingFunctions || 'Showing %1$s-%2$s of %3$s functions';
			paginationInfo.textContent = text
				.replace( '%1$s', start )
				.replace( '%2$s', end )
				.replace( '%3$s', this.totalItems );
		}
	},

	/**
	 * Open function editor modal
	 */
	async openEditorModal( functionId = null ) {
		const modal = document.getElementById( 'aie-function-editor-modal' );
		const title = modal.querySelector( '.aie-modal-title' );
		const form = document.getElementById( 'aie-function-form' );
		const codeTextarea = document.getElementById( 'aie-function-code' );

		if ( ! modal || ! form || ! codeTextarea ) {
			return;
		}

		// Clear previous errors
		clearModalErrors( modal );

		// Reset form
		form.reset();
		document.getElementById( 'aie-function-id' ).value = '';
		document.querySelector( '.aie-test-results' ).style.display = 'none';

		// Clear textarea directly
		codeTextarea.value = '';

		// Show modal and initialize CodeMirror FIRST (before loading data)
		modal.style.display = 'flex';
		document.body.style.overflow = 'hidden';

		// Initialize CodeMirror for code editor if not already initialized
		if ( ! this.codeEditor && window.wp && window.wp.codeEditor ) {
			this.codeEditor = window.wp.codeEditor.initialize(
				codeTextarea,
				{
					codemirror: {
						mode: 'php',
						lineNumbers: true,
						lineWrapping: true,
						indentUnit: 4,
						indentWithTabs: true,
						autoCloseBrackets: true,
						matchBrackets: true,
						styleActiveLine: true,
						continueComments: true,
					},
				}
			);
		}

		// Clear CodeMirror content after initialization
		if ( this.codeEditor && this.codeEditor.codemirror ) {
			this.codeEditor.codemirror.setValue( '' );
			// Force refresh
			this.codeEditor.codemirror.clearHistory();
		}

		if ( functionId ) {
			// Edit mode - load function data
			title.textContent =
				window.aieData?.i18n?.edit_function || 'Edit Function';

			try {
				const response = await fetch( window.aieData.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
						body: new URLSearchParams( {
							action: 'rsl_ie_functions_get',
							nonce: window.aieData?.nonce || '',
							id: functionId,
						} ),
				} );

				const data = await response.json();

				if ( ! data.success ) {
					throw new Error(
						data.message || data.data?.message || window.aieData?.i18n?.failedToLoadFunction || 'Failed to load function'
					);
				}

				const func = data.data;
				
				// Check if this is a library snippet
				const isLibrarySnippet = functionId.toString().startsWith( 'snippet_' );
				
				if ( isLibrarySnippet ) {
					// Show info that editing a snippet will create a new custom function
					title.textContent =
						window.aieData?.i18n?.customize_snippet || 'Customize Snippet';
					
					const infoBox = document.createElement( 'div' );
					infoBox.className = 'notice notice-info';
					infoBox.style.marginTop = '15px';
					infoBox.innerHTML = `
						<p><strong>${ window.aieData?.i18n?.snippet_customize_title || 'Customizing Library Snippet' }</strong></p>
						<p>${ window.aieData?.i18n?.snippet_customize_info || 
							'You are customizing a library snippet. Your changes will be saved as a new custom function.' }</p>
					`;
					
					const modalBody = modal.querySelector( '.aie-modal-body' );
					const form = modalBody.querySelector( 'form' );
					modalBody.insertBefore( infoBox, form );
				}
				
				document.getElementById( 'aie-function-id' ).value = func.id;
				document.getElementById( 'aie-function-name' ).value =
					func.name;
				document.getElementById( 'aie-function-description' ).value =
					func.description || '';
				// Category is now computed (library/custom) - don't set from data
				// document.getElementById( 'aie-function-category' ).value = func.category;
				document.getElementById( 'aie-function-status' ).value =
					func.status;

				// Update CodeMirror with the loaded code
				if ( this.codeEditor && this.codeEditor.codemirror ) {
					this.codeEditor.codemirror.setValue( func.code || '' );
				} else {
					// Fallback to textarea if CodeMirror not initialized
					document.getElementById( 'aie-function-code' ).value =
						func.code || '';
				}
			} catch ( error ) {
				showModalError( error.message, modal );
				return;
			}
		} else {
			// Create mode - add default PHP opening tag
			title.textContent =
				window.aieData?.i18n?.new_function || 'New Function';
			
			// Set default PHP code template
			const defaultCode = '<?php\n\n';
			if ( this.codeEditor && this.codeEditor.codemirror ) {
				this.codeEditor.codemirror.setValue( defaultCode );
				// Position cursor after the opening tag and empty lines
				setTimeout( () => {
					this.codeEditor.codemirror.setCursor( { line: 2, ch: 0 } );
					this.codeEditor.codemirror.focus();
				}, 100 );
			} else {
				// Fallback to textarea if CodeMirror not initialized
				codeTextarea.value = defaultCode;
			}
		}
	},

	/**
	 * Open editor modal with snippet data for customization
	 */
	async openEditorWithSnippet( snippetData ) {
		const modal = document.getElementById( 'aie-function-editor-modal' );
		const title = modal.querySelector( '.aie-modal-title' );
		const form = document.getElementById( 'aie-function-form' );
		const codeTextarea = document.getElementById( 'aie-function-code' );

		if ( ! modal || ! form || ! codeTextarea ) {
			return;
		}

		// Clear previous errors
		clearModalErrors( modal );

		// Reset form
		form.reset();
		document.getElementById( 'aie-function-id' ).value = '';
		document.querySelector( '.aie-test-results' ).style.display = 'none';

		// Show modal FIRST
		modal.style.display = 'flex';
		document.body.style.overflow = 'hidden';

		// Initialize CodeMirror if not already initialized
		if ( ! this.codeEditor && window.wp && window.wp.codeEditor ) {
			this.codeEditor = window.wp.codeEditor.initialize(
				codeTextarea,
				{
					codemirror: {
						mode: 'php',
						lineNumbers: true,
						lineWrapping: true,
						indentUnit: 4,
						indentWithTabs: true,
						autoCloseBrackets: true,
						matchBrackets: true,
						styleActiveLine: true,
						continueComments: true,
					},
				}
			);
		}

		// Set title
		title.textContent = window.aieData?.i18n?.customizeFunction || 'Customize Function';

		// Fill form with snippet data - always use 'custom' category
		document.getElementById( 'aie-function-name' ).value = snippetData.name || '';
		document.getElementById( 'aie-function-description' ).value = snippetData.description || '';
		document.getElementById( 'aie-function-category' ).value = 'custom';
		document.getElementById( 'aie-function-status' ).value = 'active';

		// Prepare code with <?php opening tag
		let code = snippetData.code || '';
		// Only add <?php if it doesn't already start with it
		if ( code && ! code.trim().startsWith( '<?php' ) && ! code.trim().startsWith( '<?' ) ) {
			code = '<?php\n\n' + code;
		}

		// Set code in CodeMirror
		if ( this.codeEditor && this.codeEditor.codemirror ) {
			this.codeEditor.codemirror.setValue( code );
			// Force refresh to ensure proper rendering
			setTimeout( () => {
				this.codeEditor.codemirror.refresh();
			}, 100 );
		} else {
			// Fallback to textarea if CodeMirror not initialized
			codeTextarea.value = code;
		}
	},

	/**
	 * Close modal
	 */
	closeModal( modal ) {
		modal.style.display = 'none';
		document.body.style.overflow = '';
		
		// Remove any info boxes that were added (e.g., snippet customization info)
		const infoBoxes = modal.querySelectorAll( '.aie-info-box, .notice' );
		infoBoxes.forEach( box => {
			// Only remove dynamically added info boxes, not permanent ones
			if ( !box.hasAttribute( 'data-permanent' ) ) {
				box.remove();
			}
		} );
	},

	/**
	 * Save function
	 */
	async saveFunction() {
		// Clear any previous modal errors
		clearModalErrors();

		// Get code from CodeMirror if initialized and sync with textarea
		const codeTextarea = document.getElementById( 'aie-function-code' );
		let code = codeTextarea.value;

		if ( this.codeEditor && this.codeEditor.codemirror ) {
			code = this.codeEditor.codemirror.getValue();
			// Sync CodeMirror value back to textarea for validation
			codeTextarea.value = code;
		}

		// Manual validation with user-friendly messages
		const name = document.getElementById( 'aie-function-name' ).value.trim();
		const category = document.getElementById( 'aie-function-category' ).value;

		if ( ! name ) {
			showModalError( window.aieData?.i18n?.name_required || 'Please enter a function name.' );
			document.getElementById( 'aie-function-name' ).focus();
			return;
		}

		if ( ! code.trim() ) {
			showModalError( window.aieData?.i18n?.code_required || 'Please enter the PHP code for your function.' );
			// Focus on CodeMirror if available, otherwise on textarea
			if ( this.codeEditor && this.codeEditor.codemirror ) {
				this.codeEditor.codemirror.focus();
			} else {
				codeTextarea.focus();
			}
			return;
		}

		if ( ! category ) {
			showModalError( window.aieData?.i18n?.category_required || 'Please select a category.' );
			document.getElementById( 'aie-function-category' ).focus();
			return;
		}

		// Normalize PHP code (add <?php if missing and wrap if needed)
		code = this.normalizePhpCode( code );

		const functionId = document.getElementById( 'aie-function-id' ).value;
		
			// Use FormData instead of URLSearchParams to preserve newlines
			const formData = new FormData();
			formData.append( 'action', functionId ? 'rsl_ie_functions_update' : 'rsl_ie_functions_create' );
			formData.append( 'nonce', window.aieData?.nonce || '' );
			formData.append( 'name', document.getElementById( 'aie-function-name' ).value );
			formData.append( 'description', document.getElementById( 'aie-function-description' ).value );
			formData.append( 'category', document.getElementById( 'aie-function-category' ).value );
		formData.append( 'code', code );
		formData.append( 'status', document.getElementById( 'aie-function-status' ).value );

		if ( functionId ) {
			formData.append( 'id', functionId );
		}

		try {
			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );

			// Check if response is JSON
			const contentType = response.headers.get( 'content-type' );
			if (
				! contentType ||
				! contentType.includes( 'application/json' )
			) {
				// Response is not JSON, probably a PHP error page
				const text = await response.text();
				throw new Error(
					window.aieData?.i18n?.serverErrorPhpSyntax ||
					'Server error: The function code contains errors that prevent it from being saved. Please check your PHP syntax.'
				);
			}

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error(
					data.message || data.data?.message || window.aieData?.i18n?.failedToSaveFunction || 'Failed to save function'
				);
			}

			// Check if the function name was automatically changed
			const originalName = document.getElementById( 'aie-function-name' ).value.trim();
			const savedFunction = data.data?.function;
			
			let successMessage = window.aieData?.i18n?.function_saved || 'Function saved successfully';
			
			if ( savedFunction && savedFunction.name && savedFunction.name !== originalName ) {
				successMessage = ( window.aieData?.i18n?.function_saved_with_new_name || 
					'Function saved successfully. Name was automatically changed to "{name}" to avoid conflicts.' )
					.replace( '{name}', savedFunction.name );
			}

			showNotice( successMessage );
			this.closeModal(
				document.getElementById( 'aie-function-editor-modal' )
			);
			this.loadFunctions();
		} catch ( error ) {
			const modal = document.getElementById(
				'aie-function-editor-modal'
			);

			// Improve error message for JSON parse errors
			let errorMessage = error.message;
			if (
				errorMessage.includes( 'Unexpected token' ) ||
				errorMessage.includes( 'is not valid JSON' )
			) {
				errorMessage =
					window.aieData?.i18n?.serverErrorUnableToSave ||
					'Server error: Unable to save function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.';
			}

			if ( modal && modal.style.display === 'flex' ) {
				showModalError( errorMessage, modal );
			} else {
				showError( errorMessage );
			}
		}
	},

	/**
	 * Delete function
	 */
	async deleteFunction( functionId ) {
		try {
			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
					body: new URLSearchParams( {
						action: 'rsl_ie_functions_delete',
						nonce: window.aieData?.nonce || '',
						id: functionId,
					} ),
			} );

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error(
					data.message || data.data?.message || window.aieData?.i18n?.failedToDeleteFunction || 'Failed to delete function'
				);
			}

			showNotice(
				window.aieData?.i18n?.function_deleted ||
					'Function deleted successfully'
			);
			this.loadFunctions();
		} catch ( error ) {
			showError( error.message );
		}
	},

	/**
	 * Test function with sample value
	 */
	async testFunction() {
		// Get code from CodeMirror if initialized
		let code = document.getElementById( 'aie-function-code' ).value;
		if ( this.codeEditor && this.codeEditor.codemirror ) {
			code = this.codeEditor.codemirror.getValue();
		}

		const testValueInput = document.getElementById( 'aie-test-value' );
		const testValue = testValueInput.value;
		const resultsDiv = document.querySelector( '.aie-test-results' );
		const modal = document.getElementById( 'aie-function-editor-modal' );

		if ( ! code ) {
			if ( modal && modal.style.display === 'flex' ) {
				showModalError( window.aieData?.i18n?.pleaseEnterFunctionCode || 'Please enter function code first', modal );
			} else {
				showError( window.aieData?.i18n?.pleaseEnterFunctionCode || 'Please enter function code first' );
			}
			return;
		}

		if ( ! testValue || ! testValue.trim() ) {
			testValueInput.focus();
			testValueInput.select();
			return;
		}

		// Normalize PHP code (add <?php if missing)
		code = this.normalizePhpCode( code );

		try {
			// Use FormData to preserve newlines
				const formData = new FormData();
				formData.append( 'action', 'rsl_ie_functions_test' );
				formData.append( 'nonce', window.aieData?.nonce || '' );
				formData.append( 'code', code );
				formData.append( 'value', testValue );

			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				body: formData,
			} );

			// Check if response is JSON
			const contentType = response.headers.get( 'content-type' );
			if (
				! contentType ||
				! contentType.includes( 'application/json' )
			) {
				// Response is not JSON, probably a PHP error page
				const text = await response.text();
				throw new Error(
					window.aieData?.i18n?.serverErrorFunctionErrors ||
					'Server error: The function code contains errors. Please check your PHP syntax.'
				);
			}

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error( data.message || data.data?.message || window.aieData?.i18n?.testFailed || 'Test failed' );
			}

			// Show results
			document.querySelector( '.aie-test-input' ).textContent =
				data.data.input !== undefined ? data.data.input : testValue;
			document.querySelector( '.aie-test-output' ).textContent =
				data.data.output !== undefined ? data.data.output : '';
			resultsDiv.style.display = 'block';
		} catch ( error ) {

			// Improve error message for JSON parse errors
			let errorMessage = error.message;
			if (
				errorMessage.includes( 'Unexpected token' ) ||
				errorMessage.includes( 'is not valid JSON' )
			) {
				errorMessage =
					window.aieData?.i18n?.serverErrorUnableToTest ||
					'Server error: Unable to test function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.';
			}

			if ( modal && modal.style.display === 'flex' ) {
				showModalError( errorMessage, modal );
			} else {
				showError( errorMessage );
			}
		}
	},

	/**
	 * Clear all filters
	 */
	clearFilters() {
		this.filters = { search: '' };
		document.getElementById( 'aie-filter-search' ).value = '';
		this.currentPage = 1;
		this.loadFunctions();
	},

	/**
	 * Get category label
	 */
	getCategoryLabel( category ) {
		const labels = {
			string: window.aieData?.i18n?.categoryStringOperations || 'String Operations',
			date: window.aieData?.i18n?.categoryDateTime || 'Date & Time',
			numeric: window.aieData?.i18n?.categoryNumericOperations || 'Numeric Operations',
			html: window.aieData?.i18n?.categoryHtmlOperations || 'HTML Operations',
			wordpress: window.aieData?.i18n?.categoryWordPress || 'WordPress',
			validation: window.aieData?.i18n?.categoryValidation || 'Validation',
			advanced: window.aieData?.i18n?.categoryAdvanced || 'Advanced',
			custom: window.aieData?.i18n?.categoryCustom || 'Custom',
		};
		return labels[ category ] || category;
	},

	/**
	 * Get category badge HTML
	 */
	getCategoryBadge( category ) {
		if ( category === 'library' ) {
			return `<span class="aie-badge aie-badge-library">${ window.aieData?.i18n?.badgeLibrary || 'Library' }</span>`;
		}
		return `<span class="aie-badge aie-badge-custom">${ window.aieData?.i18n?.badgeCustom || 'Custom' }</span>`;
	},

	/**
	 * Normalize PHP code - ensure it starts with <?php and wrap in function if needed
	 */
	normalizePhpCode( code ) {
		if ( ! code || ! code.trim() ) {
			return code;
		}

		let trimmedCode = code.trim();

		// Check if code already starts with <?php or <?
		if (
			trimmedCode.startsWith( '<?php' ) ||
			trimmedCode.startsWith( '<?' )
		) {
			// Code already has PHP tags, just return as-is
			return trimmedCode;
		}

		// No PHP tags, add them
		return '<?php\n' + trimmedCode;
	},

	/**
	 * Get source badge HTML
	 */
	getSourceBadge( source ) {
		if ( source.startsWith( 'library:' ) ) {
			return `<span class="aie-badge aie-badge-library">${ window.aieData?.i18n?.badgeLibrary || 'Library' }</span>`;
		}
		return `<span class="aie-badge aie-badge-custom">${ window.aieData?.i18n?.badgeCustom || 'Custom' }</span>`;
	},

	/**
	 * Get status badge HTML
	 */
	getStatusBadge( status ) {
		if ( status === 'active' ) {
			return `<span class="aie-badge aie-badge-active">${ window.aieData?.i18n?.badgeActive || 'Active' }</span>`;
		}
		return `<span class="aie-badge aie-badge-inactive">${ window.aieData?.i18n?.badgeInactive || 'Inactive' }</span>`;
	},

	/**
	 * Escape HTML
	 */
	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	},

	/**
	 * Open AI prompt modal
	 */
	openAIPromptModal() {
		// Check if API key is configured
		if ( ! window.aieData?.hasOpenAIApiKey ) {
			const optionsUrl = window.aieData?.optionsUrl || 'admin.php?page=rsl-ie-plugin-options';
			const message = window.aieData?.i18n?.apiKeyNotConfigured ||
				'OpenAI API key is not configured. Please configure it in Plugin Options to use AI generation.\n\nDo you want to go to Plugin Options now?';
			
			if ( confirm( message ) ) {
				window.location.href = optionsUrl;
			}
			return;
		}

		const modal = document.getElementById( 'aie-ai-prompt-modal' );
		if ( ! modal ) {
			return;
		}

		// Clear previous prompt
		document.getElementById( 'aie-ai-prompt' ).value = '';
		
		// Hide generating state
		const generatingDiv = modal.querySelector( '.aie-ai-generating' );
		if ( generatingDiv ) {
			generatingDiv.style.display = 'none';
		}

		// Show modal
		modal.style.display = 'flex';
		document.body.classList.add( 'aie-modal-open' );

		// Focus on prompt textarea
		setTimeout( () => {
			document.getElementById( 'aie-ai-prompt' )?.focus();
		}, 100 );

		// Bind AI modal events (if not already bound)
		this.bindAIModalEvents();
	},

	/**
	 * Bind AI modal events
	 */
	bindAIModalEvents() {
		// Prevent multiple bindings
		if ( this.aiModalEventsBound ) {
			return;
		}
		this.aiModalEventsBound = true;

		const modal = document.getElementById( 'aie-ai-prompt-modal' );
		if ( ! modal ) {
			return;
		}

		// Example prompt links
		modal.querySelectorAll( '.aie-use-example' ).forEach( ( link ) => {
			link.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				const prompt = e.target.dataset.prompt;
				document.getElementById( 'aie-ai-prompt' ).value = prompt;
			} );
		} );

		// Generate code button
		const generateBtn = modal.querySelector( '.aie-generate-code' );
		if ( generateBtn && ! generateBtn.dataset.bound ) {
			generateBtn.dataset.bound = 'true';
			generateBtn.addEventListener( 'click', () => {
				this.generateFunctionWithAI();
			} );
		}
	},

	/**
	 * Generate function with AI
	 */
	async generateFunctionWithAI() {
		const modal = document.getElementById( 'aie-ai-prompt-modal' );
		const prompt = document.getElementById( 'aie-ai-prompt' ).value.trim();

		if ( ! prompt ) {
			showModalError(
				window.aieData?.i18n?.prompt_required ||
					'Please describe what you want the function to do.',
				modal
			);
			document.getElementById( 'aie-ai-prompt' ).focus();
			return;
		}

		clearModalErrors();

		// Show generating state
		const generatingDiv = modal.querySelector( '.aie-ai-generating' );
		const generateBtn = modal.querySelector( '.aie-generate-code' );
		
		if ( generatingDiv ) {
			generatingDiv.style.display = 'block';
		}
		
		if ( generateBtn ) {
			generateBtn.disabled = true;
		}

		try {
			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
					body: new URLSearchParams( {
						action: 'rsl_ie_functions_generate_with_ai',
						nonce: window.aieData?.nonce || '',
						prompt: prompt,
					} ),
			} );

			// Check HTTP status
			if ( ! response.ok ) {
				throw new Error(
					window.aieData?.i18n?.ai_server_error ||
					`Server error: ${response.status} ${response.statusText}`
				);
			}

			// Check if response is JSON
			const contentType = response.headers.get( 'content-type' );
			if ( ! contentType || ! contentType.includes( 'application/json' ) ) {
				throw new Error(
					window.aieData?.i18n?.ai_invalid_response ||
					'Invalid server response. Please try again or contact support.'
				);
			}

			const data = await response.json();

			if ( ! data.success ) {
				// Extract error message with fallback
				const errorMessage = data.message || data.data?.message || data.data || 
					window.aieData?.i18n?.ai_generation_failed ||
					'Failed to generate function with AI. Please try again.';
				throw new Error( errorMessage );
			}

			// Validate response data
			if ( ! data.data || ! data.data.code ) {
				throw new Error(
					window.aieData?.i18n?.ai_no_code ||
					'AI did not return any code. Please try a different prompt.'
				);
			}

			// Insert generated code into the function editor
			const codeTextarea = document.getElementById( 'aie-function-code' );
			if ( codeTextarea ) {
				// Prepend <?php tag and comment before generated code
				const codeWithPhp = '<?php\n\n' + data.data.code;
				
				codeTextarea.value = codeWithPhp;
				
				// Update CodeMirror if available
				if ( this.codeEditor && this.codeEditor.codemirror ) {
					this.codeEditor.codemirror.setValue( codeWithPhp );
				}
			}

			// Optionally set function name if provided
			if ( data.data.name ) {
				const nameInput = document.getElementById( 'aie-function-name' );
				if ( nameInput && ! nameInput.value ) {
					nameInput.value = data.data.name;
				}
			}

			// Optionally set description if provided
			if ( data.data.description ) {
				const descInput = document.getElementById( 'aie-function-description' );
				if ( descInput && ! descInput.value ) {
					descInput.value = data.data.description;
				}
			}

			// Close AI modal
			this.closeModal( modal );

			// Show success message
			showNotice(
				window.aieData?.i18n?.ai_generated ||
					'AI has generated your function code! Please review and test it before saving.'
			);

		} catch ( error ) {
			console.error( 'AI generation error:', error );
			
			// Determine appropriate error message
			let errorMessage = error.message;
			
			// Handle network errors
			if ( error.name === 'TypeError' && error.message.includes( 'fetch' ) ) {
				errorMessage = window.aieData?.i18n?.ai_network_error ||
					'Network error. Please check your internet connection and try again.';
			}
			
			// Handle timeout errors
			if ( error.name === 'AbortError' || error.message.includes( 'timeout' ) ) {
				errorMessage = window.aieData?.i18n?.ai_timeout_error ||
					'Request timed out. The AI service may be busy. Please try again.';
			}
			
			// Handle JSON parse errors
			if ( error.name === 'SyntaxError' && error.message.includes( 'JSON' ) ) {
				errorMessage = window.aieData?.i18n?.ai_invalid_response ||
					'Received invalid response from server. Please try again.';
			}

			showModalError( errorMessage, modal );
		} finally {
			// Hide generating state
			if ( generatingDiv ) {
				generatingDiv.style.display = 'none';
			}
			
			if ( generateBtn ) {
				generateBtn.disabled = false;
			}
		}
	},
};

export default FunctionsModule;
