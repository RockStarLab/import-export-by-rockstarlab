/**
 * Custom Functions Management Module
 *
 * Handles CRUD operations for custom transformation functions
 */

import {
	showNotice,
	showError,
	showModalError,
	confirmDialog,
} from '../utils/notifications';
import FunctionLibrary from './function_library';

const FunctionsModule = {
	currentPage: 1,
	perPage: 20,
	totalPages: 1,
	totalItems: 0,
	filters: {
		status: '',
		category: '',
		search: '',
	},
	codeEditor: null, // CodeMirror instance

	/**
	 * Initialize the module
	 */
	init() {
		if ( ! document.getElementById( 'wp-aie-functions' ) ) {
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

		// Filter controls
		document
			.getElementById( 'aie-filter-status' )
			?.addEventListener( 'change', ( e ) => {
				this.filters.status = e.target.value;
				this.currentPage = 1;
				this.loadFunctions();
			} );

		document
			.getElementById( 'aie-filter-category' )
			?.addEventListener( 'change', ( e ) => {
				this.filters.category = e.target.value;
				this.currentPage = 1;
				this.loadFunctions();
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

		// Test function
		document
			.querySelector( '.aie-test-function' )
			?.addEventListener( 'click', () => {
				this.testFunction();
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
				<td colspan="7" style="text-align:center;">
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
					action: 'aie_functions_get_all',
					nonce: window.aieData?.nonce || '',
					status: this.filters.status,
					category: this.filters.category,
					search: this.filters.search,
					page: this.currentPage,
					per_page: this.perPage,
				} ),
			} );

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error(
					data.data?.message || 'Failed to load functions'
				);
			}

			this.totalPages = data.data.total_pages || 1;
			this.totalItems = data.data.total || 0;

			this.renderTable( data.data.functions || [] );
			this.updatePagination();
		} catch ( error ) {
			console.error( 'Error loading functions:', error );
			tbody.innerHTML = `
				<tr>
					<td colspan="7" style="text-align:center; color:#dc3232;">
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
					<td colspan="7" style="text-align:center; padding:40px;">
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
							: '<em style="color:#999;">No description</em>'
					}
				</td>
				<td class="column-category">
					${ this.getCategoryLabel( func.category ) }
				</td>
				<td class="column-source">
					${ this.getSourceBadge( func.source ) }
				</td>
				<td class="column-status">
					${ this.getStatusBadge( func.status ) }
				</td>
				<td class="column-usage">
					${ func.usage_count || 0 }
				</td>
				<td class="column-actions">
					<button type="button" class="button button-small aie-edit-function" data-id="${
						func.id
					}" title="Edit">
						<span class="dashicons dashicons-edit"></span>
					</button>
					<button type="button" class="button button-small aie-delete-function" data-id="${
						func.id
					}" title="Delete">
						<span class="dashicons dashicons-trash"></span>
					</button>
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
			paginationInfo.textContent = `Showing ${ start }-${ end } of ${ this.totalItems } functions`;
		}
	},

	/**
	 * Open function editor modal
	 */
	async openEditorModal( functionId = null ) {
		const modal = document.getElementById( 'aie-function-editor-modal' );
		const title = modal.querySelector( '.aie-modal-title' );
		const form = document.getElementById( 'aie-function-form' );

		if ( ! modal || ! form ) {
			return;
		}

		// Reset form
		form.reset();
		document.getElementById( 'aie-function-id' ).value = '';
		document.querySelector( '.aie-test-results' ).style.display = 'none';

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
						action: 'aie_functions_get',
						nonce: window.aieData?.nonce || '',
						id: functionId,
					} ),
				} );

				const data = await response.json();

				if ( ! data.success ) {
					throw new Error(
						data.data?.message || 'Failed to load function'
					);
				}

				const func = data.data;
				document.getElementById( 'aie-function-id' ).value = func.id;
				document.getElementById( 'aie-function-name' ).value =
					func.name;
				document.getElementById( 'aie-function-description' ).value =
					func.description || '';
				document.getElementById( 'aie-function-category' ).value =
					func.category;
				document.getElementById( 'aie-function-code' ).value =
					func.code;
				document.getElementById( 'aie-function-status' ).value =
					func.status;

				// Update CodeMirror if initialized
				if ( this.codeEditor ) {
					this.codeEditor.codemirror.setValue( func.code );
				}
			} catch ( error ) {
				console.error( 'Error loading function:', error );
				showModalError( error.message, modal );
				return;
			}
		} else {
			// Create mode
			title.textContent =
				window.aieData?.i18n?.new_function || 'New Function';
		}

		modal.style.display = 'flex';
		document.body.style.overflow = 'hidden';

		// Initialize CodeMirror for code editor
		if ( ! this.codeEditor && window.wp && window.wp.codeEditor ) {
			const codeTextarea = document.getElementById( 'aie-function-code' );
			if ( codeTextarea ) {
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
		}
	},

	/**
	 * Close modal
	 */
	closeModal( modal ) {
		modal.style.display = 'none';
		document.body.style.overflow = '';
	},

	/**
	 * Save function
	 */
	async saveFunction() {
		const form = document.getElementById( 'aie-function-form' );
		if ( ! form.checkValidity() ) {
			form.reportValidity();
			return;
		}

		// Get code from CodeMirror if initialized
		let code = document.getElementById( 'aie-function-code' ).value;
		if ( this.codeEditor && this.codeEditor.codemirror ) {
			code = this.codeEditor.codemirror.getValue();
		}

		const functionId = document.getElementById( 'aie-function-id' ).value;
		const functionData = {
			action: functionId
				? 'aie_functions_update'
				: 'aie_functions_create',
			nonce: window.aieData?.nonce || '',
			name: document.getElementById( 'aie-function-name' ).value,
			description: document.getElementById( 'aie-function-description' )
				.value,
			category: document.getElementById( 'aie-function-category' ).value,
			code: code,
			status: document.getElementById( 'aie-function-status' ).value,
		};

		if ( functionId ) {
			functionData.id = functionId;
		}

		try {
			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( functionData ),
			} );

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error(
					data.data?.message || 'Failed to save function'
				);
			}

			showNotice(
				window.aieData?.i18n?.function_saved ||
					'Function saved successfully'
			);
			this.closeModal(
				document.getElementById( 'aie-function-editor-modal' )
			);
			this.loadFunctions();
		} catch ( error ) {
			console.error( 'Error saving function:', error );
			const modal = document.getElementById(
				'aie-function-editor-modal'
			);
			if ( modal && modal.style.display === 'flex' ) {
				showModalError( error.message, modal );
			} else {
				showError( error.message );
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
					action: 'aie_functions_delete',
					nonce: window.aieData?.nonce || '',
					id: functionId,
				} ),
			} );

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error(
					data.data?.message || 'Failed to delete function'
				);
			}

			showNotice(
				window.aieData?.i18n?.function_deleted ||
					'Function deleted successfully'
			);
			this.loadFunctions();
		} catch ( error ) {
			console.error( 'Error deleting function:', error );
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
				showModalError( 'Please enter function code first', modal );
			} else {
				showError( 'Please enter function code first' );
			}
			return;
		}

		// Check if test value is empty or only whitespace
		if ( ! testValue || ! testValue.trim() ) {
			testValueInput.focus();
			testValueInput.select();
			return;
		}

		try {
			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'aie_functions_test',
					nonce: window.aieData?.nonce || '',
					code: code,
					value: testValue,
				} ),
			} );

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error( data.data?.message || 'Test failed' );
			}

			// Show results
			document.querySelector( '.aie-test-input' ).textContent =
				data.data.input !== undefined ? data.data.input : testValue;
			document.querySelector( '.aie-test-output' ).textContent =
				data.data.output !== undefined ? data.data.output : '';
			resultsDiv.style.display = 'block';
		} catch ( error ) {
			console.error( 'Error testing function:', error );
			if ( modal && modal.style.display === 'flex' ) {
				showModalError( error.message, modal );
			} else {
				showError( error.message );
			}
		}
	},

	/**
	 * Clear all filters
	 */
	clearFilters() {
		this.filters = { status: '', category: '', search: '' };
		document.getElementById( 'aie-filter-status' ).value = '';
		document.getElementById( 'aie-filter-category' ).value = '';
		document.getElementById( 'aie-filter-search' ).value = '';
		this.currentPage = 1;
		this.loadFunctions();
	},

	/**
	 * Get category label
	 */
	getCategoryLabel( category ) {
		const labels = {
			string: 'String Operations',
			date: 'Date & Time',
			numeric: 'Numeric Operations',
			html: 'HTML Operations',
			wordpress: 'WordPress',
			validation: 'Validation',
			advanced: 'Advanced',
			custom: 'Custom',
		};
		return labels[ category ] || category;
	},

	/**
	 * Get source badge HTML
	 */
	getSourceBadge( source ) {
		if ( source.startsWith( 'library:' ) ) {
			return '<span class="aie-badge aie-badge-library">Library</span>';
		}
		return '<span class="aie-badge aie-badge-custom">Custom</span>';
	},

	/**
	 * Get status badge HTML
	 */
	getStatusBadge( status ) {
		if ( status === 'active' ) {
			return '<span class="aie-badge aie-badge-active">Active</span>';
		}
		return '<span class="aie-badge aie-badge-inactive">Inactive</span>';
	},

	/**
	 * Escape HTML
	 */
	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	},
};

export default FunctionsModule;
