/**
 * Gutenberg Sync Button
 * 
 * Adds sync button to Gutenberg editor sidebar
 */

(function($) {
	'use strict';

	const GutenbergSync = {
		/**
		 * Initialize
		 */
		init() {
			console.log('AIE: Loading Gutenberg sync button script');
			console.log('AIE: wp object exists:', typeof wp !== 'undefined');
			console.log('AIE: wp.data exists:', typeof wp !== 'undefined' && typeof wp.data !== 'undefined');
			
			$(document).ready(() => {
				console.log('AIE: Document ready');
				this.addGutenbergSyncButton();
			});
		},

		/**
		 * Add button to Gutenberg editor
		 */
		addGutenbergSyncButton() {
			console.log('AIE: addGutenbergSyncButton called');
			
			// Check if we're in Gutenberg
			if (typeof wp === 'undefined' || typeof wp.data === 'undefined') {
				console.log('AIE: WordPress editor not detected');
				return false;
			}
			
			console.log('AIE: WordPress editor detected');
			
			// Try multiple selectors for the sidebar (different WordPress versions)
			let $sidebar = $('.interface-interface-skeleton__sidebar .edit-post-sidebar');
			
			if (!$sidebar.length) {
				$sidebar = $('.edit-post-sidebar');
			}
			
			if (!$sidebar.length) {
				$sidebar = $('.editor-sidebar');
			}
			
			if (!$sidebar.length) {
				$sidebar = $('.interface-complementary-area');
			}
			
			console.log('AIE: Sidebar elements found:', $sidebar.length);
			console.log('AIE: Sidebar HTML:', $sidebar.length ? $sidebar[0].className : 'none');
			
			if ($sidebar.length && !$('#aie-sync-content-btn').length) {
				console.log('AIE: Sidebar found, creating sync panel');
				
				// Create panel container (like Yoast SEO) - opened by default
				const $panel = $('<div>')
					.addClass('components-panel__body aie-gutenberg-sync-panel is-opened')
					.attr('id', 'aie-gutenberg-sync-panel');
				
				// Create panel header with arrow (same as Yoast SEO)
				const $header = $('<h2>')
					.addClass('components-panel__body-title')
					.html('<button type="button" class="components-button components-panel__body-toggle" aria-expanded="true"><span aria-hidden="true"><svg class="components-panel__arrow" width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg></span>' + (window.aieData?.i18n?.syncContent || 'Sync Content') + '</button></h2>');
					
				// Create panel content - visible by default
				const $content = $('<div>').css({
					'padding': '16px'
				});
				
				// Create sync button
				const $button = $('<button>')
					.attr('type', 'button')
					.attr('id', 'aie-sync-content-btn')
					.addClass('button button-secondary')
					.css('width', '100%')
					.html( window.aieData?.i18n?.syncThisPost || 'Sync This Post' );
				
				$content.append($button);
				$panel.append($header, $content);
				
				// Insert after the first panel or at the beginning
				const $firstPanel = $sidebar.find('.components-panel__body').first();
				if ($firstPanel.length) {
					$firstPanel.after($panel);
				} else {
					$sidebar.prepend($panel);
				}
				
				// Add toggle functionality
				$header.find('button').on('click', function() {
					const $btn = $(this);
					const $panel = $btn.closest('.components-panel__body');
					const isExpanded = $btn.attr('aria-expanded') === 'true';
					
					$btn.attr('aria-expanded', !isExpanded);
					$panel.toggleClass('is-opened');
					$content.slideToggle(200);
				});
				
				console.log('AIE: Sync button added successfully');
				return true;
			}
			
			console.log('AIE: Sidebar not found or button already exists');
			return false;
		},

		/**
		 * Retry adding button with increasing intervals
		 */
		retryAddButton(attempts = 0) {
			const maxAttempts = 10;
			const baseDelay = 500;
			
			if (attempts >= maxAttempts) {
				console.log('AIE: Max retry attempts reached');
				return;
			}
			
			const success = this.addGutenbergSyncButton();
			if (!success) {
				const delay = baseDelay * Math.pow(1.5, attempts);
				console.log(`AIE: Retry attempt ${attempts + 1}/${maxAttempts} after ${delay}ms`);
				setTimeout(() => this.retryAddButton(attempts + 1), delay);
			}
		}
	};

	// Initialize
	GutenbergSync.init();
	
	// Try adding button with retries (for when Gutenberg loads slowly)
	setTimeout(() => GutenbergSync.retryAddButton(), 1000);

})(jQuery);
