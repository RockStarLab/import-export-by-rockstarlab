/**
 * Gutenberg Sync Button
 *
 * Adds sync button to Gutenberg editor sidebar
 */

( function ( $ ) {
	'use strict';

	const GutenbergSync = {
		/**
		 * Initialize
		 */
		init() {
			$( document ).ready( () => {
				this.addGutenbergSyncButton();
			} );
		},

		/**
		 * Add button to Gutenberg editor
		 */
		addGutenbergSyncButton() {
			// Check if we're in Gutenberg
			if ( typeof wp === 'undefined' || typeof wp.data === 'undefined' ) {
				return false;
			}

			// Try multiple selectors for the sidebar (different WordPress versions)
			let $sidebar = $(
				'.interface-interface-skeleton__sidebar .edit-post-sidebar'
			);

			if ( ! $sidebar.length ) {
				$sidebar = $( '.edit-post-sidebar' );
			}

			if ( ! $sidebar.length ) {
				$sidebar = $( '.editor-sidebar' );
			}

			if ( ! $sidebar.length ) {
				$sidebar = $( '.interface-complementary-area' );
			}

			// WP versions / layouts where only the skeleton sidebar is present.
			if ( ! $sidebar.length ) {
				$sidebar = $( '.interface-interface-skeleton__sidebar' );
			}

			if ( $sidebar.length && ! $( '#rsl-ie-sync-content-btn' ).length ) {
				// Create panel container (like Yoast SEO) - opened by default
				const $panel = $( '<div>' )
					.addClass(
						'components-panel__body rsl-ie-gutenberg-sync-panel is-opened'
					)
					.attr( 'id', 'rsl-ie-gutenberg-sync-panel' );

				// Create panel header with arrow (same as Yoast SEO)
				const $header = $( '<h2>' )
					.addClass( 'components-panel__body-title' )
					.html(
						'<button type="button" class="components-button components-panel__body-toggle" aria-expanded="true"><span aria-hidden="true"><svg class="components-panel__arrow" width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg></span>' +
							( window.rslIeData?.i18n?.syncContent ||
								'Sync Content' ) +
							'</button></h2>'
					);

				// Create panel content - visible by default
				const $content = $( '<div>' ).css( {
					padding: '16px',
				} );

				// Create sync button
				const $button = $( '<button>' )
					.attr( 'type', 'button' )
					.attr( 'id', 'rsl-ie-sync-content-btn' )
					.addClass( 'button button-secondary' )
					.css( 'width', '100%' )
					.html(
						window.rslIeData?.i18n?.syncThisPost || 'Sync This Post'
					);

				$content.append( $button );
				$panel.append( $header, $content );

				// Insert after the first panel or at the beginning
				const $firstPanel = $sidebar
					.find( '.components-panel__body' )
					.first();
				if ( $firstPanel.length ) {
					$firstPanel.after( $panel );
				} else {
					$sidebar.prepend( $panel );
				}

				// Add toggle functionality
				$header.find( 'button' ).on( 'click', function () {
					const $btn = $( this );
					const $panel = $btn.closest( '.components-panel__body' );
					const isExpanded = $btn.attr( 'aria-expanded' ) === 'true';

					$btn.attr( 'aria-expanded', ! isExpanded );
					$panel.toggleClass( 'is-opened' );
					$content.slideToggle( 200 );
				} );

				return true;
			}

			return false;
		},

		/**
		 * Retry adding button with increasing intervals
		 */
		retryAddButton( attempts = 0 ) {
			const maxAttempts = 10;
			const baseDelay = 500;

			if ( attempts >= maxAttempts ) {
				return;
			}

			const success = this.addGutenbergSyncButton();
			if ( ! success ) {
				const delay = baseDelay * Math.pow( 1.5, attempts );
				setTimeout( () => this.retryAddButton( attempts + 1 ), delay );
			}
		},
	};

	// Initialize
	GutenbergSync.init();

	// Try adding button with retries (for when Gutenberg loads slowly)
	setTimeout( () => GutenbergSync.retryAddButton(), 1000 );
} )( jQuery );
