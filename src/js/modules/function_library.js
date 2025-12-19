/**
 * Function Library Browser Module
 *
 * Handles browsing, searching, and importing snippet functions
 */

import { showNotice, showError, clearModalErrors } from '../utils/notifications';

const FunctionLibrary = {
	functionsModule: null,
	allSnippets: {},
	categories: {},
	currentCategory: '',
	currentSnippet: null,

	/**
	 * Initialize the module
	 */
	init( functionsModule ) {
		this.functionsModule = functionsModule;
	},

	/**
	 * Open library modal
	 */
	async openLibrary() {
		const modal = document.getElementById( 'aie-snippets-library-modal' );
		if ( ! modal ) {
			return;
		}

		modal.style.display = 'flex';
		document.body.style.overflow = 'hidden';

		// Load snippets if not loaded
		if ( Object.keys( this.allSnippets ).length === 0 ) {
			await this.loadSnippets();
		} else {
			this.renderSnippets();
		}

		this.bindLibraryEvents();
	},

	/**
	 * Bind library modal events
	 */
	bindLibraryEvents() {
		// Category filtering
		const categoryItems = document.querySelectorAll( '.aie-category-item' );
		categoryItems.forEach( ( item ) => {
			item.addEventListener( 'click', ( e ) => {
				const category = e.currentTarget.dataset.category;
				this.filterByCategory( category );
			} );
		} );

		// Search
		const searchInput = document.getElementById( 'aie-snippet-search' );
		if ( searchInput ) {
			let searchTimeout;
			searchInput.addEventListener( 'input', ( e ) => {
				clearTimeout( searchTimeout );
				searchTimeout = setTimeout( () => {
					this.searchSnippets( e.target.value );
				}, 300 );
			} );
		}

		// Preview modal events
		const previewModal = document.getElementById(
			'aie-snippet-preview-modal'
		);
		if ( previewModal ) {
			previewModal
				.querySelector( '.aie-customize-snippet' )
				?.addEventListener( 'click', () => {
					this.importSnippet( this.currentSnippet, true );
				} );
		}
	},

	/**
	 * Load snippets from server
	 */
	async loadSnippets( category = '' ) {
		const grid = document.getElementById( 'aie-snippets-grid' );
		if ( ! grid ) {
			return;
		}

		// Show loading
		grid.innerHTML = `
			<div class="aie-loading-snippets">
				<span class="spinner is-active"></span>
				<p>${ window.aieData?.i18n?.loading || 'Loading snippets...' }</p>
			</div>
		`;

		try {
			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'aie_functions_get_snippets',
					nonce: window.aieData?.nonce || '',
					category: category,
				} ),
			} );

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error(
					data.data?.message || 'Failed to load snippets'
				);
			}

			this.allSnippets = data.data.snippets || {};
			this.categories = data.data.categories || {};

			this.renderCategories();
			this.renderSnippets();
		} catch ( error ) {
			grid.innerHTML = `
				<div class="aie-error-message">
					<span class="dashicons dashicons-warning"></span>
					<p>${ error.message }</p>
				</div>
			`;
		}
	},

	/**
	 * Render categories sidebar
	 */
	renderCategories() {
		const categoriesList = document.getElementById( 'aie-categories-list' );
		if ( ! categoriesList ) {
			return;
		}

		const totalSnippets = Object.keys( this.allSnippets ).length;

		let html = `
			<li class="aie-category-item ${
				this.currentCategory === '' ? 'active' : ''
			}" data-category="">
				<span class="dashicons dashicons-category"></span>
				<span class="aie-category-name">${
					window.aieData?.i18n?.all_snippets || 'All Snippets'
				}</span>
				<span class="aie-category-count">${ totalSnippets }</span>
			</li>
		`;

		Object.entries( this.categories ).forEach( ( [ category, info ] ) => {
			const count = Object.values( this.allSnippets ).filter(
				( s ) => s.category === category
			).length;
			const isActive = this.currentCategory === category;

			html += `
				<li class="aie-category-item ${
					isActive ? 'active' : ''
				}" data-category="${ category }">
					<span class="dashicons dashicons-${ info.icon }"></span>
					<span class="aie-category-name">${ info.name }</span>
					<span class="aie-category-count">${ count }</span>
				</li>
			`;
		} );

		categoriesList.innerHTML = html;
	},

	/**
	 * Render snippet cards
	 */
	renderSnippets() {
		const grid = document.getElementById( 'aie-snippets-grid' );
		if ( ! grid ) {
			return;
		}

		let snippets = Object.entries( this.allSnippets );

		// Filter by category
		if ( this.currentCategory ) {
			snippets = snippets.filter(
				( [ , snippet ] ) => snippet.category === this.currentCategory
			);
		}

		if ( snippets.length === 0 ) {
			grid.innerHTML = `
				<div class="aie-no-snippets">
					<span class="dashicons dashicons-info" style="width: auto; height: auto;"></span>
					<p>${ window.aieData?.i18n?.no_snippets || 'No snippets found' }</p>
				</div>
			`;
			return;
		}

		// Check if "Use" button should be shown
	const currentPage = window.aieData?.currentPage || '';
	const allowedPages = [
		'wp-advanced-import-export',
		'wp-aie-export',
		'wp-aie-content-sync',
		'wp-aie-functions', // Add Functions page
	];
	const showUseButton = allowedPages.includes( currentPage );		grid.innerHTML = snippets
			.map(
				( [ key, snippet ] ) => `
			<div class="aie-snippet-card" data-snippet-key="${ key }">
				<div class="aie-snippet-header">
					<h3 class="aie-snippet-name">${ this.escapeHtml( snippet.name ) }</h3>
					<span class="aie-snippet-category-badge">${ this.getCategoryLabel(
						snippet.category
					) }</span>
				</div>
				<p class="aie-snippet-description">${ this.escapeHtml(
					snippet.description
				) }</p>
				<div class="aie-snippet-tags">
					${
						snippet.tags
							? snippet.tags
									.map(
										( tag ) =>
											`<span class="aie-tag">${ this.escapeHtml(
												tag
											) }</span>`
									)
									.join( '' )
							: ''
					}
				</div>
				<div class="aie-snippet-actions">
					<button type="button" class="button button-small aie-preview-snippet" data-snippet-key="${ key }">
						<span class="dashicons dashicons-visibility"></span>
						${ window.aieData?.i18n?.preview || 'Preview' }
					</button>
					${
						showUseButton
							? `<button type="button" class="button button-primary button-small aie-quick-import" data-snippet-key="${ key }">
						<span class="dashicons dashicons-plus"></span>
						${ window.aieData?.i18n?.customize || 'Customize' }
					</button>`
							: ''
					}
				</div>
			</div>
		`
			)
			.join( '' );

		// Bind snippet card events
		grid.querySelectorAll( '.aie-preview-snippet' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				const key = e.currentTarget.dataset.snippetKey;
				this.previewSnippet( key );
			} );
		} );

		grid.querySelectorAll( '.aie-quick-import' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', ( e ) => {
				const key = e.currentTarget.dataset.snippetKey;
				this.importSnippet( key, true ); // Changed to true - always customize
			} );
		} );
	},

	/**
	 * Filter snippets by category
	 */
	filterByCategory( category ) {
		this.currentCategory = category;

		// Update active state
		document.querySelectorAll( '.aie-category-item' ).forEach( ( item ) => {
			item.classList.toggle(
				'active',
				item.dataset.category === category
			);
		} );

		this.renderSnippets();
	},

	/**
	 * Search snippets
	 */
	async searchSnippets( query ) {
		const grid = document.getElementById( 'aie-snippets-grid' );
		if ( ! grid ) {
			return;
		}

		if ( ! query.trim() ) {
			// Reset to current category
			this.renderSnippets();
			return;
		}

		// Show loading
		grid.innerHTML = `
			<div class="aie-loading-snippets">
				<span class="spinner is-active"></span>
				<p>${ window.aieData?.i18n?.searching || 'Searching...' }</p>
			</div>
		`;

		try {
			const response = await fetch( window.aieData.ajaxUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: new URLSearchParams( {
					action: 'aie_functions_search',
					nonce: window.aieData?.nonce || '',
					query: query,
				} ),
			} );

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error( data.data?.message || 'Search failed' );
			}

			// Temporarily replace snippets with search results
			const originalSnippets = this.allSnippets;
			this.allSnippets = data.data.snippets || {};
			this.currentCategory = ''; // Show all results
			this.renderSnippets();
			this.allSnippets = originalSnippets; // Restore
		} catch ( error ) {
			showError( error.message );
		}
	},

	/**
	 * Preview snippet in modal
	 */
	previewSnippet( snippetKey ) {
		const snippet = this.allSnippets[ snippetKey ];
		if ( ! snippet ) {
			return;
		}

		this.currentSnippet = snippetKey;

		const modal = document.getElementById( 'aie-snippet-preview-modal' );
		if ( ! modal ) {
			return;
		}

		// Fill modal with snippet details
		modal.querySelector( '.aie-snippet-title' ).textContent = snippet.name;
		modal.querySelector( '.aie-snippet-description' ).textContent =
			snippet.description;
		modal.querySelector( '.aie-snippet-category' ).textContent =
			this.getCategoryLabel( snippet.category );
		modal.querySelector( '.aie-snippet-code' ).textContent = snippet.code;

		if ( snippet.tags && snippet.tags.length > 0 ) {
			modal.querySelector( '.aie-snippet-tags' ).textContent =
				snippet.tags.join( ', ' );
		} else {
			modal.querySelector( '.aie-snippet-tags' ).textContent = 'None';
		}

		if ( snippet.example ) {
			modal.querySelector( '.aie-example-input-value' ).textContent =
				snippet.example.input !== undefined
					? snippet.example.input
					: 'N/A';
			modal.querySelector( '.aie-example-output-value' ).textContent =
				snippet.example.output !== undefined
					? snippet.example.output
					: 'N/A';
		}

		// Show/hide "Use" button based on current page
		// Only show on Import, Export, and Content Sync pages
		const useButton = modal.querySelector( '.aie-use-snippet' );
		if ( useButton ) {
			const currentPage = window.aieData?.currentPage || '';
			const allowedPages = [
				'wp-advanced-import-export',
				'wp-aie-export',
				'wp-aie-content-sync',
			];

			useButton.style.display = allowedPages.includes( currentPage )
				? ''
				: 'none';
		}

		modal.style.display = 'flex';
	},

	/**
	 * Import snippet as function
	 */
	async importSnippet( snippetKey, customize = false ) {
		const snippet = this.allSnippets[ snippetKey ];
		if ( ! snippet ) {
			return;
		}

		if ( customize ) {
			// Close library and preview modals
			const libraryModal = document.getElementById(
				'aie-snippets-library-modal'
			);
			const previewModal = document.getElementById(
				'aie-snippet-preview-modal'
			);

			if ( libraryModal ) {
				libraryModal.style.display = 'none';
			}
			if ( previewModal ) {
				previewModal.style.display = 'none';
			}
			document.body.style.overflow = '';

			// Use the FunctionsModule method to open editor with snippet data
			if ( this.functionsModule && this.functionsModule.openEditorWithSnippet ) {
				await this.functionsModule.openEditorWithSnippet( snippet );
			} else {
				// Fallback: Open editor directly (old method)
				const editorModal = document.getElementById(
					'aie-function-editor-modal'
				);
				if ( editorModal ) {
					// Clear any previous errors
					clearModalErrors();

					document.getElementById( 'aie-function-id' ).value = '';
					document.getElementById( 'aie-function-name' ).value =
						snippet.name;
					document.getElementById( 'aie-function-description' ).value =
						snippet.description;
					document.getElementById( 'aie-function-category' ).value =
						'custom'; // Always use 'custom' category
					document.getElementById( 'aie-function-code' ).value =
						snippet.code;
					document.getElementById( 'aie-function-status' ).value =
						'active';
					document.querySelector( '.aie-modal-title' ).textContent =
						'Customize Function';

					editorModal.style.display = 'flex';
					document.body.style.overflow = 'hidden';
				}
			}
		} else {
			// Import directly
			try {
				const response = await fetch( window.aieData.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams( {
						action: 'aie_functions_import',
						nonce: window.aieData?.nonce || '',
						snippet_key: snippetKey,
					} ),
				} );

				const data = await response.json();

				if ( ! data.success ) {
					throw new Error( data.data?.message || 'Import failed' );
				}

				showNotice(
					window.aieData?.i18n?.snippet_imported ||
						'Snippet imported successfully'
				);
				document.body.style.overflow = '';

				// Refresh functions list
				if ( this.functionsModule ) {
					this.functionsModule.loadFunctions();
				}
			} catch ( error ) {
				showError( error.message );
			}
		}
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
	 * Escape HTML
	 */
	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	},
};

export default FunctionLibrary;
