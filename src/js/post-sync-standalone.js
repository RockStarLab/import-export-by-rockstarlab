/**
 * Post Sync Standalone Module
 *
 * Handles content synchronization from post list screens
 * This is a standalone version that doesn't require the main app.js
 */

import 'select2';
import 'select2/dist/css/select2.min.css';

const AJAX_PREFIX = 'rsl_ie_';

const normalizeAjaxAction = ( action ) => {
	if ( ! action || typeof action !== 'string' ) return action;
	if ( action.startsWith( AJAX_PREFIX ) ) return action;
	return AJAX_PREFIX + action;
};

const getActionNonce = ( action ) =>
	window.rslIePostSyncData?.nonces?.[ normalizeAjaxAction( action ) ] || '';

( function ( $ ) {
	'use strict';

	$.ajaxPrefilter( ( options, originalOptions ) => {
		const data = originalOptions.data;
		const action =
			data && typeof data === 'object' ? data.action || '' : '';
		const nonce = getActionNonce( action );
		if ( nonce && options.data && typeof options.data === 'object' ) {
			options.data.nonce = nonce;
		}
	} );

	const PostSync = {
		// Flag to track if sync is in progress
		isSyncing: false,

		/**
		 * Initialize the module
		 */
		init() {
			this.positionQuickActionButtons();
			this.bindEvents();
			this.updateExportButtonState();
		},

		/**
		 * Ensure quick action buttons sit next to the Filter button.
		 */
		positionQuickActionButtons() {
			const $buttons = $(
				'#rsl-ie-export-selected-btn, #rsl-ie-sync-content-btn'
			);
			const $filterBtn = $( '#post-query-submit' );

			if ( $buttons.length && $filterBtn.length ) {
				$buttons.insertAfter( $filterBtn );
			}
		},

		/**
		 * Format remote post option labels. Keep direction-specific action labels
		 * clear in the mapping dropdown.
		 */
		getRemotePostOptionText( postTitle, remoteId ) {
			const template =
				this.currentSyncDirection === 'pull'
					? 'Pull: %1$s (ID: %2$s)'
					: rslIePostSyncData?.i18n?.updatePost ||
					  '🔄 Update: %1$s (ID: %2$s)';

			return template
				.replace( '%1$s', postTitle || '' )
				.replace( '%2$s', remoteId );
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			$( document ).on(
				'change',
				'tbody .check-column input[type="checkbox"], thead .check-column input[type="checkbox"], tfoot .check-column input[type="checkbox"]',
				() => {
					window.setTimeout(
						() => this.updateExportButtonState(),
						0
					);
				}
			);

			$( document ).on( 'click', '#rsl-ie-export-selected-btn', ( e ) => {
				e.preventDefault();
				e.stopImmediatePropagation();
				this.openSelectedPostsExport();
			} );

			// Open modal when sync button is clicked
			$( document ).on( 'click', '#rsl-ie-sync-content-btn', ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				this.openSyncModal();
			} );

			// Close modal
			$( document ).on( 'click', '.rsl-ie-modal-close', ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				const $modal = $( e.currentTarget ).closest( '.rsl-ie-modal' );
				const modalId = $modal.attr( 'id' );

				if ( modalId === 'rsl-ie-browse-modal' ) {
					this.closeBrowseModal( false ); // Close everything when clicking X button
				} else if ( modalId === 'rsl-ie-mapping-modal' ) {
					this.closeMappingModal();
				} else if ( modalId === 'rsl-ie-sync-modal' ) {
					this.closeSyncModal();
				}
			} );

			$( document ).on( 'click', '.rsl-ie-modal', ( e ) => {
				if ( e.target === e.currentTarget ) {
					const modalId = $( e.target ).attr( 'id' );
					if ( modalId === 'rsl-ie-browse-modal' ) {
						this.closeBrowseModal( false ); // Close everything when clicking outside modal
					} else if ( modalId === 'rsl-ie-mapping-modal' ) {
						this.closeMappingModal();
					} else {
						this.closeSyncModal();
					}
				}
			} );

			// Close modal on backdrop click
			$( document ).on( 'click', '.rsl-ie-modal-backdrop', ( e ) => {
				const $modal = $( e.target ).closest( '.rsl-ie-modal' );
				if ( $modal.attr( 'id' ) === 'rsl-ie-browse-modal' ) {
					this.closeBrowseModal( false ); // Close everything when clicking backdrop
				}
			} ); // Enable/disable sync buttons based on site selection
			$( document ).on( 'change', '#rsl-ie-sync-site-select', () => {
				this.updateSyncButtons();
			} );

			// Handle Push button
			$( document ).on( 'click', '#rsl-ie-sync-push-btn', ( e ) => {
				e.preventDefault();
				this.syncContent( 'push' );
			} );

			// Handle Pull button
			$( document ).on( 'click', '#rsl-ie-sync-pull-btn', ( e ) => {
				e.preventDefault();
				this.syncContent( 'pull' );
			} );

			// Handle Browse Remote button
			$( document ).on(
				'click',
				'#rsl-ie-browse-remote-btn, #rsl-ie-browse-remote-btn-alt',
				( e ) => {
					e.preventDefault();
					this.openBrowseModal();
				}
			);

			// Browse modal - Search (debounced)
			let searchTimeout;
			$( document ).on( 'input', '#rsl-ie-browse-search', ( e ) => {
				clearTimeout( searchTimeout );
				searchTimeout = setTimeout( () => {
					this.browseState.searchQuery = $( e.target ).val();
					this.browseState.currentPage = 1;
					this.loadRemotePosts();
				}, 500 );
			} );

			// Browse modal - Status filter
			$( document ).on( 'click', '.rsl-ie-filter-item', ( e ) => {
				const $item = $( e.currentTarget );
				$( '.rsl-ie-filter-item' ).removeClass( 'active' );
				$item.addClass( 'active' );

				this.browseState.currentFilter = $item.data( 'status' );
				this.browseState.currentPage = 1;
				this.loadRemotePosts();
			} );

			// Browse modal - Post toggle (expand/collapse)
			$( document ).on( 'click', '.rsl-ie-post-toggle', ( e ) => {
				e.stopPropagation();
				const $toggle = $( e.currentTarget );
				const $item = $toggle.closest( '.rsl-ie-post-item' );
				const postId = parseInt( $item.data( 'post-id' ) );
				const $children = $item.next( '.rsl-ie-post-children' );

				if ( $toggle.hasClass( 'expanded' ) ) {
					// Collapse
					$toggle.removeClass( 'expanded' );
					$children.slideUp( 200 );
					this.browseState.expandedPosts.delete( postId );
				} else {
					// Expand
					$toggle.addClass( 'expanded' );

					// Load children if not loaded yet
					if ( $children.children().length === 0 ) {
						this.loadChildrenPosts( postId, $children );
					} else {
						$children.slideDown( 200 );
					}

					this.browseState.expandedPosts.add( postId );
				}
			} );

			// Browse modal - Post checkbox
			$( document ).on( 'change', '.rsl-ie-post-checkbox', ( e ) => {
				const $checkbox = $( e.currentTarget );
				const postId = parseInt( $checkbox.val() );
				const $item = $checkbox.closest( '.rsl-ie-post-item' );

				if ( $checkbox.prop( 'checked' ) ) {
					this.browseState.selectedPosts.add( postId );
					$item.addClass( 'selected' );
				} else {
					this.browseState.selectedPosts.delete( postId );
					$item.removeClass( 'selected' );
				}

				this.updateBrowseSelection();
			} );

			// Browse modal - Bulk selection for currently visible posts
			$( document ).on( 'click', '#rsl-ie-browse-select-all', ( e ) => {
				e.preventDefault();
				this.setVisibleBrowsePostsSelected( true );
			} );

			$( document ).on( 'click', '#rsl-ie-browse-deselect-all', ( e ) => {
				e.preventDefault();
				this.setVisibleBrowsePostsSelected( false );
			} );

			// Browse modal - Pagination
			$( document ).on( 'click', '#rsl-ie-browse-prev-page', ( e ) => {
				e.preventDefault();
				if ( this.browseState.currentPage > 1 ) {
					this.browseState.currentPage--;
					this.loadRemotePosts();
				}
			} );

			$( document ).on( 'click', '#rsl-ie-browse-next-page', ( e ) => {
				e.preventDefault();
				if (
					this.browseState.currentPage < this.browseState.totalPages
				) {
					this.browseState.currentPage++;
					this.loadRemotePosts();
				}
			} );

			// Browse modal - Cancel button
			$( document ).on( 'click', '#rsl-ie-browse-cancel-btn', ( e ) => {
				e.preventDefault();
				this.closeBrowseModal();
			} );

			// Browse modal - Pull button
			$( document ).on( 'click', '#rsl-ie-browse-pull-btn', ( e ) => {
				e.preventDefault();
				this.pullSelectedPosts();
			} );

			// Close modal on Escape key
			$( document ).on( 'keydown', ( e ) => {
				if (
					e.key === 'Escape' &&
					$( '#rsl-ie-sync-modal' ).is( ':visible' )
				) {
					this.closeSyncModal();
				}
				if (
					e.key === 'Escape' &&
					$( '#rsl-ie-browse-modal' ).is( ':visible' )
				) {
					this.closeBrowseModal( false ); // Close everything when pressing Escape
				}
			} );
		},

		/**
		 * Open sync modal
		 */
		openSyncModal() {
			if ( this.redirectToContentSyncIfNoSites() ) {
				return;
			}

			const selectedIds = this.getSelectedPostIds();

			if ( selectedIds.length === 0 ) {
				const isEditPage = $( '#post_ID' ).length > 0;
				// If we're on the post edit page, keep the original behavior (ask to save)
				if ( isEditPage ) {
					const message =
						typeof rslIePostSyncData !== 'undefined' &&
						rslIePostSyncData.i18n
							? rslIePostSyncData.i18n.pleaseSavePost
							: 'Please save the post first';
					alert( message );
					return;
				}
				// If we're on the posts list (no selection), open the Browse & Pull modal directly
				this.openBrowseModal();
				return;
			}

			// Update selected count with proper text
			const countText =
				selectedIds.length === 1
					? rslIePostSyncData?.i18n?.onePost || '1 post'
					: (
							rslIePostSyncData?.i18n?.postsCount || '%s posts'
					  ).replace( '%s', selectedIds.length );
			$( '#rsl-ie-selected-count' ).text( countText );

			// Reset form
			$( '#rsl-ie-sync-site-select' ).val( '' );
			$( '#rsl-ie-sync-progress' ).hide();
			$( '#rsl-ie-sync-result' ).hide();
			this.updateSyncButtons();

			// Show modal
			$( '#rsl-ie-sync-modal' ).fadeIn( 200 );
		},

		/**
		 * Redirect to Content Sync settings when no remote sites are configured.
		 *
		 * @return {boolean} True when a redirect was started.
		 */
		redirectToContentSyncIfNoSites() {
			const config =
				typeof window.rslIePostSyncData === 'object'
					? window.rslIePostSyncData
					: {};
			const sites =
				config.connectedSites &&
				typeof config.connectedSites === 'object'
					? config.connectedSites
					: {};

			if ( Object.keys( sites ).length > 0 ) {
				return false;
			}

			window.location.href = this.getContentSyncUrl();
			return true;
		},

		/**
		 * Build a safe admin.php URL for Content Sync settings.
		 *
		 * @return {string} Content Sync admin URL.
		 */
		getContentSyncUrl() {
			const config =
				typeof window.rslIePostSyncData === 'object'
					? window.rslIePostSyncData
					: {};

			if (
				typeof config.contentSyncUrl === 'string' &&
				config.contentSyncUrl
			) {
				return config.contentSyncUrl;
			}

			if ( typeof window.ajaxurl === 'string' && window.ajaxurl ) {
				return (
					window.ajaxurl.replace(
						/admin-ajax\.php.*$/,
						'admin.php'
					) + '?page=rsl-ie-content-sync'
				);
			}

			if ( typeof config.ajaxurl === 'string' && config.ajaxurl ) {
				return (
					config.ajaxurl.replace(
						/admin-ajax\.php.*$/,
						'admin.php'
					) + '?page=rsl-ie-content-sync'
				);
			}

			if ( typeof config.adminUrl === 'string' && config.adminUrl ) {
				return `${ config.adminUrl.replace(
					/\/?$/,
					'/'
				) }admin.php?page=rsl-ie-content-sync`;
			}

			return 'admin.php?page=rsl-ie-content-sync';
		},

		/**
		 * Close sync modal
		 */
		closeSyncModal( keepSiteSelection = false ) {
			// Reset syncing flag
			this.isSyncing = false;

			$( '#rsl-ie-sync-modal' ).fadeOut( 200, () => {
				// Reset site selection (unless specified to keep it)
				if ( ! keepSiteSelection ) {
					$( '#rsl-ie-sync-site-select' ).val( '' );
				}

				// Reset modal state - show initial sections again
				$(
					'.rsl-ie-sync-info, .rsl-ie-form-group, .rsl-ie-sync-direction, .rsl-ie-browse-section'
				).css( 'display', '' );
				$(
					'#rsl-ie-sync-progress, #rsl-ie-sync-result, .rsl-ie-no-selection-message'
				).css( 'display', 'none' );
				$( '.rsl-ie-progress-fill' ).css( 'width', '0%' );

				// Update button states (unless we're keeping site selection for browse modal)
				if ( ! keepSiteSelection ) {
					this.updateSyncButtons();
				}
			} );
		},

		/**
		 * Get selected post IDs
		 */
		getSelectedPostIds() {
			const ids = [];

			// Check if we're on post edit page
			const postIdInput = $( '#post_ID' );
			if ( postIdInput.length && postIdInput.val() ) {
				// Single post edit page
				ids.push( postIdInput.val() );
			} else {
				// Post list page - get checked items
				$( 'tbody .check-column input[type="checkbox"]:checked' ).each(
					function () {
						const id = $( this ).val();
						if ( id ) {
							ids.push( id );
						}
					}
				);
			}

			return ids;
		},

		/**
		 * Enable the Export button only when one or more rows are selected.
		 */
		updateExportButtonState() {
			const ids = this.getSelectedPostIds();
			const $button = $( '#rsl-ie-export-selected-btn' );

			if ( ! $button.length ) {
				return;
			}

			$button
				.prop( 'disabled', ids.length === 0 )
				.attr( 'aria-disabled', ids.length === 0 ? 'true' : 'false' )
				.text( ids.length > 0 ? `Export (${ ids.length })` : 'Export' );
		},

		/**
		 * Open the export wizard prefilled with selected post IDs.
		 */
		openSelectedPostsExport() {
			const ids = this.getSelectedPostIds();
			const $button = $( '#rsl-ie-export-selected-btn' );
			const postType =
				$button.data( 'postType' ) || this.getCurrentPostType();

			if ( ids.length === 0 || ! postType ) {
				this.updateExportButtonState();
				return;
			}

			const exportUrl = this.getExportWizardUrl();
			const separator = exportUrl.indexOf( '?' ) === -1 ? '?' : '&';
			const params = new URLSearchParams( {
				rsl_ie_prefill: 'post_list',
				post_type: postType,
				post_ids: ids.join( ',' ),
			} );

			window.open(
				`${ exportUrl }${ separator }${ params.toString() }`,
				'_blank',
				'noopener'
			);
		},

		/**
		 * Build a safe admin.php URL for the export wizard.
		 */
		getExportWizardUrl() {
			const config =
				typeof window.rslIePostSyncData === 'object'
					? window.rslIePostSyncData
					: {};
			const currentAdminUrl = window.location.href.replace(
				/\/wp-admin\/.*$/,
				'/wp-admin/admin.php?page=rsl-ie-export'
			);

			if ( currentAdminUrl.indexOf( '/wp-admin/admin.php' ) !== -1 ) {
				return currentAdminUrl;
			}

			if (
				typeof config.exportUrl === 'string' &&
				config.exportUrl.indexOf( 'admin.php' ) !== -1
			) {
				return config.exportUrl;
			}

			if ( typeof window.ajaxurl === 'string' && window.ajaxurl ) {
				return (
					window.ajaxurl.replace(
						/admin-ajax\.php.*$/,
						'admin.php'
					) + '?page=rsl-ie-export'
				);
			}

			if ( typeof config.ajaxurl === 'string' && config.ajaxurl ) {
				return (
					config.ajaxurl.replace(
						/admin-ajax\.php.*$/,
						'admin.php'
					) + '?page=rsl-ie-export'
				);
			}

			if ( typeof config.adminUrl === 'string' && config.adminUrl ) {
				return `${ config.adminUrl.replace(
					/\/?$/,
					'/'
				) }admin.php?page=rsl-ie-export`;
			}

			return 'admin.php?page=rsl-ie-export';
		},

		/**
		 * Read current post type from the list table URL.
		 */
		getCurrentPostType() {
			const params = new URLSearchParams( window.location.search );
			if ( window.location.pathname.indexOf( '/upload.php' ) !== -1 ) {
				return 'attachment';
			}

			return params.get( 'post_type' ) || 'post';
		},

		/**
		 * Update sync button states
		 */
		updateSyncButtons() {
			// Don't update UI if sync is in progress
			if ( this.isSyncing ) {
				return;
			}

			const siteSelected = $( '#rsl-ie-sync-site-select' ).val() !== '';
			const selectedIds = this.getSelectedPostIds();

			// Show/hide sections based on post selection
			if ( selectedIds.length > 0 ) {
				// Has selected posts - show push/pull and browse sections
				$( '.rsl-ie-sync-direction' ).show();
				$( '.rsl-ie-browse-section' ).show();
				$( '.rsl-ie-sync-info' ).show();
				$( '.rsl-ie-no-selection-message' ).hide();
				$(
					'#rsl-ie-sync-push-btn, #rsl-ie-sync-pull-btn, #rsl-ie-browse-remote-btn'
				).prop( 'disabled', ! siteSelected );
			} else {
				// No posts selected - hide push/pull, show only browse message
				$( '.rsl-ie-sync-direction' ).hide();
				$( '.rsl-ie-browse-section' ).hide();
				$( '.rsl-ie-sync-info' ).hide();
				$( '.rsl-ie-no-selection-message' ).show();
				$( '#rsl-ie-browse-remote-btn-alt' ).prop(
					'disabled',
					! siteSelected
				);
			}

			// If we have a pending browse modal request and site is now selected, open it
			if ( this.pendingBrowseModal && siteSelected ) {
				this.pendingBrowseModal = false;
				this.closeSyncModal( true ); // Keep site selection when transitioning to browse modal
				// Small delay to allow modal close animation
				setTimeout( () => {
					this.openBrowseModal();
				}, 250 );
			}
		},

		/**
		 * Sync content (push or pull)
		 */
		syncContent( direction ) {
			const siteId = $( '#rsl-ie-sync-site-select' ).val();
			const postIds = this.getSelectedPostIds();

			if ( ! siteId ) {
				const message =
					rslIePostSyncData?.i18n?.pleaseSelectSite ||
					'Please select a site';
				alert( message );
				return;
			}

			if ( postIds.length === 0 ) {
				const message =
					rslIePostSyncData?.i18n?.noPostsSelected ||
					'No posts selected';
				alert( message );
				return;
			}

			// Store sync direction and open mapping modal
			this.currentSyncDirection = direction;
			this.currentSiteId = siteId;
			this.currentPostIds = postIds;

			// Close site selection modal
			this.closeSyncModal();

			// Open mapping modal
			this.openMappingModal( direction, siteId, postIds );
		},

		/**
		 * Open mapping modal
		 */
		openMappingModal( direction, siteId, postIds ) {
			$( '#rsl-ie-mapping-modal' ).fadeIn( 200 );
			$( '#rsl-ie-mapping-loading' ).show();
			$( '#rsl-ie-mapping-table-container' ).hide();
			$( '#rsl-ie-mapping-confirm-btn' ).prop( 'disabled', true );

			// Load local posts info and remote posts list
			this.loadMappingData( direction, siteId, postIds );
		},

		/**
		 * Close mapping modal
		 */
		closeMappingModal() {
			$( '#rsl-ie-mapping-modal' ).fadeOut( 200 );
		},

		/**
		 * Load mapping data
		 */
		loadMappingData( direction, siteId, postIds ) {
			const localPostsNonce = getActionNonce(
				'rsl_ie_content_sync_get_local_posts_info'
			);
			const remotePostsNonce = getActionNonce(
				'rsl_ie_content_sync_get_remote_posts'
			);
			const ajaxUrl =
				typeof rslIePostSyncData !== 'undefined' &&
				rslIePostSyncData.ajaxurl
					? rslIePostSyncData.ajaxurl
					: ajaxurl;

			// First, get local posts info
			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'rsl_ie_content_sync_get_local_posts_info',
					nonce: localPostsNonce,
					post_ids: postIds,
				},
				success: ( localResponse ) => {
					if ( localResponse.success && localResponse.data.posts ) {
						// Store local posts info
						this.localPostsInfo = {};
						localResponse.data.posts.forEach( ( post ) => {
							this.localPostsInfo[ post.ID ] = post;
						} );

						// Now get remote posts list
						$.ajax( {
							url: ajaxUrl,
							type: 'POST',
							data: {
								action: 'rsl_ie_content_sync_get_remote_posts',
								nonce: remotePostsNonce,
								site_id: siteId,
								post_type: 'any',
								language: this.getCurrentWpmlLanguage(),
							},
							success: ( response ) => {
								if ( response.success && response.data.posts ) {
									this.remotePosts = response.data.posts;
									this.renderMappingTable(
										postIds,
										response.data.posts
									);
									$( '#rsl-ie-mapping-loading' ).hide();
									$(
										'#rsl-ie-mapping-table-container'
									).fadeIn( 200 );
									$( '#rsl-ie-mapping-confirm-btn' ).prop(
										'disabled',
										false
									);
									// Auto-match by title on open (expected UX), but keep the
									// button for re-running / adjusting mappings.
									setTimeout( () => {
										this.autoMatchByTitle();
									}, 150 );
								} else {
									const errorMsg =
										response.data?.message ||
										rslIePostSyncData?.i18n?.unknownError ||
										'Unknown error';
									const message =
										( rslIePostSyncData?.i18n
											?.failedLoadRemotePosts ||
											'Failed to load remote posts' ) +
										': ' +
										errorMsg;
									alert( message );
									this.closeMappingModal();
								}
							},
							error: ( xhr ) => {
								const message =
									rslIePostSyncData?.i18n
										?.failedConnectRemote ||
									'Failed to connect to remote site';
								alert( message );
								this.closeMappingModal();
							},
						} );
					} else {
						const errorMsg =
							localResponse.data?.message ||
							rslIePostSyncData?.i18n?.unknownError ||
							'Unknown error';
						const message =
							( rslIePostSyncData?.i18n?.failedLoadLocalPosts ||
								'Failed to load local posts info' ) +
							': ' +
							errorMsg;
						alert( message );
						this.closeMappingModal();
					}
				},
				error: ( xhr ) => {
					const message =
						rslIePostSyncData?.i18n?.failedLoadLocalPosts ||
						'Failed to load local posts info';
					alert( message );
					this.closeMappingModal();
				},
			} );
		},

		/**
		 * Render mapping table
		 */
		renderMappingTable( localPostIds, remotePosts ) {
			const $tbody = $( '#rsl-ie-mapping-tbody' );
			$tbody.empty();

			localPostIds.forEach( ( postId ) => {
				const row = this.createMappingRow( postId, remotePosts );
				$tbody.append( row );
			} );

			// Bind events
			this.bindMappingEvents();
		},

		/**
		 * Create mapping table row
		 */
		createMappingRow( postId, remotePosts ) {
			// Get local post info from AJAX response or fallback to DOM
			const postHashText =
				rslIePostSyncData?.i18n?.postHash || 'Post #%s';
			let postTitle = postHashText.replace( '%s', postId );
			let postType = 'post';
			let originalRemoteId = 0;

			if ( this.localPostsInfo && this.localPostsInfo[ postId ] ) {
				postTitle =
					this.localPostsInfo[ postId ].post_title || postTitle;
				postType = this.localPostsInfo[ postId ].post_type || postType;
				originalRemoteId =
					this.localPostsInfo[ postId ].original_id || 0;
			} else {
				// Fallback: Try multiple selectors for different editor contexts
				postTitle =
					$( `#post-${ postId } .row-title` ).text() ||
					$( '.editor-post-title__input' ).val() ||
					$( '#title' ).val() ||
					$( 'h1.wp-heading-inline' )
						.next( 'a.page-title-action' )
						.prev()
						.text() ||
					postTitle;
				postType =
					$( 'body' )
						.attr( 'class' )
						.match( /post-type-(\S+)/ )?.[ 1 ] || postType;
			}

			const $row = $( '<tr>' ).attr( 'data-local-id', postId );

			// Local post column
			const idLabel = rslIePostSyncData?.i18n?.idLabel || 'ID:';
			const $localCol = $( '<td>' ).addClass( 'rsl-ie-local-post' )
				.html( `
				<div class="rsl-ie-local-post-info">
					<h4>${ postTitle }</h4>
					<div class="rsl-ie-post-meta">
						<span class="rsl-ie-post-type">${ postType }</span>
						<span class="rsl-ie-post-id">${ idLabel } ${ postId }</span>
					</div>
				</div>
			` );

			// Arrow column
			const $arrowCol = $( '<td>' )
				.addClass( 'rsl-ie-sync-arrow' )
				.html( '→' );

			// Remote action column
			const $remoteCol = $( '<td>' ).addClass( 'rsl-ie-remote-post' );
			const $select = $( '<select>' )
				.addClass( 'rsl-ie-remote-select' )
				.attr( 'data-local-id', postId );
			if ( originalRemoteId ) {
				$select.attr( 'data-original-remote-id', originalRemoteId );
			}

			const isPull = this.currentSyncDirection === 'pull';

			if ( isPull ) {
				const selectRemoteText =
					rslIePostSyncData?.i18n?.selectRemotePostForPull ||
					'Please select a remote post to pull.';
				$select.append(
					`<option value="" selected>${ selectRemoteText }</option>`
				);
			} else {
				// Add "Create New" option for push only.
				const createNewText =
					rslIePostSyncData?.i18n?.createNewPost ||
					'➕ Create New Post';
				$select.append(
					`<option value="new" selected class="rsl-ie-option-new">${ createNewText }</option>`
				);
			}

			const $wrapper = $( '<div>' ).addClass(
				`rsl-ie-remote-select-wrapper ${
					isPull ? '' : 'rsl-ie-action-new'
				}`
			);
			$wrapper.append( $select );
			$remoteCol.append( $wrapper );

			$row.append( $localCol, $arrowCol, $remoteCol );

			// Initialize Select2 after row is added to DOM
			setTimeout( () => {
				this.initializeSelect2( $select, postTitle, postType );
			}, 100 );

			return $row;
		},

		/**
		 * Initialize Select2 on a select element with AJAX
		 */
		initializeSelect2( $select, localPostTitle, localPostType ) {
			const siteId = this.currentSiteId;
			const siteInfo =
				typeof rslIePostSyncData !== 'undefined' &&
				rslIePostSyncData.connectedSites
					? rslIePostSyncData.connectedSites[ siteId ]
					: null;

			if ( ! siteInfo ) {
				return;
			}

			const ajaxUrl =
				typeof rslIePostSyncData !== 'undefined' &&
				rslIePostSyncData.ajaxurl
					? rslIePostSyncData.ajaxurl
					: ajaxurl;
			const nonce = getActionNonce(
				'rsl_ie_content_sync_search_remote_posts'
			);

			// Get human-readable post type label
			const postTypeLabel =
				localPostType === 'post'
					? 'post'
					: localPostType === 'page'
					? 'page'
					: localPostType;

			const searchPlaceholder =
				rslIePostSyncData?.i18n?.searchForUpdate ||
				'Search for a %s to update...';
			$select.select2( {
				placeholder: searchPlaceholder.replace( '%s', postTypeLabel ),
				allowClear: false,
				width: '100%',
				minimumInputLength: 0,
				ajax: {
					url: ajaxUrl,
					dataType: 'json',
					delay: 300,
					data: ( params ) => {
						return {
							action: 'rsl_ie_content_sync_search_remote_posts',
							nonce: nonce,
							site_id: siteId,
							search: params.term || '',
							page: params.page || 1,
							per_page: 10,
							post_type: localPostType || 'post',
							language: this.getCurrentWpmlLanguage(),
						};
					},
					processResults: ( response, params ) => {
						params.page = params.page || 1;

						if ( ! response.success || ! response.data ) {
							return { results: [] };
						}

						const results = response.data.posts || [];
						const formattedResults = results.map( ( post ) => ( {
							id: post.ID,
							text: this.getRemotePostOptionText(
								post.post_title,
								post.ID
							),
							title: post.post_title,
							post_type: post.post_type,
							post_date: post.post_date,
						} ) );

						// Add "Create New" option at the beginning for push only.
						if (
							params.page === 1 &&
							this.currentSyncDirection !== 'pull'
						) {
							const createNewText =
								rslIePostSyncData?.i18n?.createNewPost ||
								'➕ Create New Post';
							formattedResults.unshift( {
								id: 'new',
								text: createNewText,
							} );
						}

						return {
							results: formattedResults,
							pagination: {
								more:
									params.page * 10 <
									( response.data.total || 0 ),
							},
						};
					},
					cache: true,
				},
				escapeMarkup: ( markup ) => markup,
				templateResult: ( item ) => {
					if ( ! item.id ) return item.text;
					return $( '<span>' ).html( item.text );
				},
				templateSelection: ( item ) => {
					// Update wrapper class based on selection
					const $wrapper = $select.closest(
						'.rsl-ie-remote-select-wrapper'
					);
					if ( item.id === 'new' ) {
						$wrapper
							.removeClass( 'rsl-ie-action-update' )
							.addClass( 'rsl-ie-action-new' );
					} else {
						$wrapper
							.removeClass( 'rsl-ie-action-new' )
							.addClass( 'rsl-ie-action-update' );
					}
					return item.text;
				},
			} );

			// If the local post was previously synced, preselect its original remote ID.
			const originalRemoteId = parseInt(
				$select.attr( 'data-original-remote-id' ) || '0',
				10
			);
			if ( originalRemoteId ) {
				const optionText = this.getRemotePostOptionText(
					localPostTitle,
					originalRemoteId
				);
				const opt = new Option(
					optionText,
					String( originalRemoteId ),
					true,
					true
				);
				$select.append( opt ).trigger( 'change' );
			}
		},

		/**
		 * Bind mapping events
		 */
		bindMappingEvents() {
			// Close mapping modal
			$( document )
				.off( 'click', '.rsl-ie-modal-close' )
				.on( 'click', '.rsl-ie-modal-close', ( e ) => {
					this.closeMappingModal();
				} );

			// Cancel button
			$( document )
				.off( 'click', '#rsl-ie-mapping-cancel-btn' )
				.on( 'click', '#rsl-ie-mapping-cancel-btn', ( e ) => {
					e.preventDefault();
					this.closeMappingModal();
					// Reopen site selection modal
					this.openSyncModal();
				} );

			// Confirm button
			$( document )
				.off( 'click', '#rsl-ie-mapping-confirm-btn' )
				.on( 'click', '#rsl-ie-mapping-confirm-btn', ( e ) => {
					e.preventDefault();
					this.confirmMapping();
				} );

			// Auto-match button
			$( document )
				.off( 'click', '#rsl-ie-auto-match-btn' )
				.on( 'click', '#rsl-ie-auto-match-btn', ( e ) => {
					e.preventDefault();
					this.autoMatchByTitle();
				} );

			// Select change
			$( document )
				.off( 'change', '.rsl-ie-remote-select' )
				.on( 'change', '.rsl-ie-remote-select', function () {
					const value = $( this ).val();
					const $wrapper = $( this ).closest(
						'.rsl-ie-remote-select-wrapper'
					);

					$wrapper.removeClass(
						'rsl-ie-action-new rsl-ie-action-update'
					);
					if ( value === 'new' ) {
						$wrapper.addClass( 'rsl-ie-action-new' );
					} else {
						$wrapper.addClass( 'rsl-ie-action-update' );
					}
				} );
		},

		/**
		 * Auto-match posts by title
		 */
		autoMatchByTitle() {
			const siteId = this.currentSiteId;
			const ajaxUrl =
				typeof rslIePostSyncData !== 'undefined' &&
				rslIePostSyncData.ajaxurl
					? rslIePostSyncData.ajaxurl
					: ajaxurl;
			const nonce = getActionNonce(
				'rsl_ie_content_sync_auto_map_by_title'
			);
			const $button = $( '#rsl-ie-auto-match-btn' );
			const originalText = $button.text();
			const matchingText =
				rslIePostSyncData?.i18n?.matchingByTitle || 'Matching...';

			$button.prop( 'disabled', true ).text( matchingText );

			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'rsl_ie_content_sync_auto_map_by_title',
					nonce: nonce,
					site_id: siteId,
					post_ids: this.currentPostIds,
				},
				success: ( response ) => {
					if ( ! response.success || ! response.data ) {
						return;
					}

					const mapping = response.data.mapping || {};
					const matches = response.data.matches || {};

					$( '.rsl-ie-remote-select' ).each( ( i, select ) => {
						const $select = $( select );
						const localId = String( $select.data( 'local-id' ) );
						const remoteId = mapping[ localId ];
						const post = matches[ localId ];

						if ( remoteId && post ) {
							const optionExists =
								$select.find( `option[value="${ remoteId }"]` )
									.length > 0;

							if ( ! optionExists ) {
								const optionText = this.getRemotePostOptionText(
									post.post_title,
									remoteId
								);
								const newOption = new Option(
									optionText,
									remoteId,
									false,
									true
								);
								$select.append( newOption );
							}

							$select
								.val( String( remoteId ) )
								.trigger( 'change' );
						} else {
							$select
								.val(
									this.currentSyncDirection === 'pull'
										? ''
										: 'new'
								)
								.trigger( 'change' );
						}
					} );
				},
				error: () => {
					$( '.rsl-ie-remote-select' )
						.val(
							this.currentSyncDirection === 'pull' ? '' : 'new'
						)
						.trigger( 'change' );
				},
				complete: () => {
					$button.prop( 'disabled', false ).text( originalText );
				},
			} );
		},

		/**
		 * Confirm mapping and start sync
		 */
		confirmMapping() {
			// Collect mapping
			const postMapping = {};
			$( '.rsl-ie-remote-select' ).each( function () {
				const localId = $( this ).data( 'local-id' );
				const remoteId = $( this ).val();

				if ( remoteId === 'new' ) {
					postMapping[ localId ] = null;
				} else if ( remoteId ) {
					postMapping[ localId ] = parseInt( remoteId );
				}
			} );

			// Close mapping modal
			this.closeMappingModal();

			// For PULL: we need to send remote IDs, not local IDs
			// For PUSH: we send local IDs
			let postIdsToSync = this.currentPostIds;

			if ( this.currentSyncDirection === 'pull' ) {
				// Extract remote IDs from mapping for pull
				postIdsToSync = [];
				Object.keys( postMapping ).forEach( ( localId ) => {
					const remoteId = postMapping[ localId ];
					if ( remoteId && remoteId !== 'new' && remoteId !== null ) {
						postIdsToSync.push( remoteId );
					}
				} );

				if ( postIdsToSync.length === 0 ) {
					alert(
						rslIePostSyncData?.i18n?.selectRemotePostForPull ||
							'Please select at least one remote post to pull.'
					);
					return;
				}
			}

			// Start actual sync with mapping
			this.performSync(
				this.currentSyncDirection,
				this.currentSiteId,
				postIdsToSync,
				postMapping
			);
		},

		/**
		 * Perform actual sync
		 */
		performSync( direction, siteId, postIds, postMapping ) {
			// Set syncing flag
			this.isSyncing = true;

			// Open sync modal to show progress
			$( '#rsl-ie-sync-modal' ).fadeIn( 200 );

			// Hide initial content and show progress
			$(
				'.rsl-ie-sync-info, .rsl-ie-form-group, .rsl-ie-sync-direction, .rsl-ie-browse-section, .rsl-ie-no-selection-message'
			).css( 'display', 'none' );
			$( '#rsl-ie-sync-progress' ).show();
			$( '#rsl-ie-sync-result' ).hide();
			const progressInterval =
				this.startOptimisticSyncProgress( direction );

			// Disable buttons
			$(
				'#rsl-ie-sync-push-btn, #rsl-ie-sync-pull-btn, #rsl-ie-sync-site-select'
			).prop( 'disabled', true );

			// Make AJAX request
			const action = `rsl_ie_content_sync_${ direction }`;
			const nonce = getActionNonce( action );
			const ajaxUrl =
				typeof rslIePostSyncData !== 'undefined' &&
				rslIePostSyncData.ajaxurl
					? rslIePostSyncData.ajaxurl
					: ajaxurl;

			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: action,
					nonce: nonce,
					site_id: siteId,
					post_ids: postIds,
					post_mapping: JSON.stringify( postMapping ),
				},
				success: ( response ) => {
					clearInterval( progressInterval );

					if ( response.success ) {
						$( '.rsl-ie-progress-fill' ).css( 'width', '100%' );
						const completedText =
							rslIePostSyncData?.i18n?.completed || 'Completed!';
						$( '.rsl-ie-progress-text' ).text( completedText );

						setTimeout( () => {
							$( '#rsl-ie-sync-progress' ).hide();
							const successMsg =
								response.data.message ||
								rslIePostSyncData?.i18n?.syncCompletedSuccess ||
								'Sync completed successfully';
							this.showResult( 'success', successMsg );
						}, 500 );
					} else {
						$( '#rsl-ie-sync-progress' ).hide();
						const errorMsg =
							response.data.message ||
							rslIePostSyncData?.i18n?.syncFailed ||
							'Sync failed';
						this.showResult( 'error', errorMsg );
					}
				},
				error: ( xhr ) => {
					clearInterval( progressInterval );
					$( '#rsl-ie-sync-progress' ).hide();

					let errorMessage =
						rslIePostSyncData?.i18n?.errorDuringSync ||
						'An error occurred during sync';
					if (
						xhr.responseJSON &&
						xhr.responseJSON.data &&
						xhr.responseJSON.data.message
					) {
						errorMessage = xhr.responseJSON.data.message;
					}

					this.showResult( 'error', errorMessage );
				},
				complete: () => {
					clearInterval( progressInterval );

					// Re-enable buttons
					$(
						'#rsl-ie-sync-push-btn, #rsl-ie-sync-pull-btn, #rsl-ie-sync-site-select'
					).prop( 'disabled', false );
					this.updateSyncButtons();
				},
			} );
		},

		/**
		 * Show optimistic progress while a sync AJAX request is running.
		 *
		 * Content Sync currently performs one long request, so there is no server-side
		 * per-item progress stream. This keeps the UI alive without claiming completion.
		 *
		 * @param {string} direction Sync direction: push or pull.
		 * @return {number} Interval ID.
		 */
		startOptimisticSyncProgress( direction ) {
			let progress = 8;
			const isPush = direction === 'push';
			const preparingText = isPush
				? rslIePostSyncData?.i18n?.preparingToPush ||
				  'Preparing to push content...'
				: rslIePostSyncData?.i18n?.preparingToPull ||
				  'Preparing to pull content...';
			const activeText = isPush
				? rslIePostSyncData?.i18n?.uploadingContent ||
				  'Uploading content...'
				: rslIePostSyncData?.i18n?.downloadingContent ||
				  'Downloading content...';

			$( '.rsl-ie-progress-fill' ).css( 'width', `${ progress }%` );
			$( '.rsl-ie-progress-text' ).text( preparingText );

			window.setTimeout( () => {
				if ( progress < 18 ) {
					progress = 18;
					$( '.rsl-ie-progress-fill' ).css(
						'width',
						`${ progress }%`
					);
					$( '.rsl-ie-progress-text' ).text( activeText );
				}
			}, 250 );

			return window.setInterval( () => {
				if ( progress >= 50 ) {
					return;
				}

				progress = Math.min( 50, progress + ( progress < 30 ? 4 : 2 ) );
				$( '.rsl-ie-progress-fill' ).css( 'width', `${ progress }%` );
				$( '.rsl-ie-progress-text' ).text( activeText );
			}, 900 );
		},

		/**
		 * Open browse remote posts modal
		 */
		openBrowseModal() {
			const siteId = $( '#rsl-ie-sync-site-select' ).val();
			if ( ! siteId ) {
				// If no site selected, open the main sync modal to select a site first
				$( '#rsl-ie-sync-site-select' ).val( '' );
				$( '#rsl-ie-sync-progress' ).hide();
				$( '#rsl-ie-sync-result' ).hide();
				this.updateSyncButtons();

				$( '.rsl-ie-sync-direction' ).hide();
				$( '.rsl-ie-sync-info' ).hide();

				$( '#rsl-ie-sync-modal' ).fadeIn( 200 );

				// Store flag that we want to open browse modal after site selection
				this.pendingBrowseModal = true;
				return;
			}

			// Initialize browse state
			this.browseState = {
				siteId: siteId,
				postType: this.getCurrentPostType(),
				currentPage: 1,
				totalPages: 1,
				selectedPosts: new Set(),
				currentFilter: '',
				searchQuery: '',
				expandedPosts: new Set(),
			};

			// Reset modal UI
			$( '#rsl-ie-browse-search' ).val( '' );
			$( '#rsl-ie-browse-posts-tree' ).empty().hide();
			$( '#rsl-ie-browse-loading' ).show();
			$( '#rsl-ie-browse-pagination' ).hide();
			$( '#rsl-ie-browse-bulk-actions' ).hide();
			$( '#rsl-ie-browse-pull-btn' ).prop( 'disabled', true );
			$( '#rsl-ie-browse-selected-count' ).text( '0' );

			// Reset filters
			$( '.rsl-ie-filter-item' ).removeClass( 'active' );
			$( '.rsl-ie-filter-item[data-status=""]' ).addClass( 'active' );

			// Show browse modal
			$( '#rsl-ie-browse-modal' ).fadeIn( 200 );

			// Load remote posts
			this.loadRemotePosts();
		},

		/**
		 * Close browse modal
		 * @param {boolean} returnToChooseSite - If true, return to Choose Site modal; if false, close everything
		 */
		closeBrowseModal( returnToChooseSite = true ) {
			$( '#rsl-ie-browse-modal' ).fadeOut( 200, () => {
				if ( returnToChooseSite ) {
					// Return back to Choose Site modal with site selection preserved
					$( '#rsl-ie-sync-modal' ).fadeIn( 200, () => {
						// Update UI to reflect current state (hide Push/Pull if no posts selected)
						this.updateSyncButtons();
					} );
				} else {
					// Close everything and reset
					$( '#rsl-ie-sync-site-select' )
						.val( '' )
						.trigger( 'change' );
					this.updateSyncButtons();
				}
			} );
		},

		/**
		 * Get current post type from screen
		 */
		getCurrentPostType() {
			if ( window.location.pathname.indexOf( '/upload.php' ) !== -1 ) {
				return 'attachment';
			}

			// Try to get from post edit screen
			if ( $( '#post_type' ).length ) {
				return $( '#post_type' ).val();
			}

			// Try to get from post list screen
			if ( window.typenow ) {
				return window.typenow;
			}

			// Try to get from URL
			const urlParams = new URLSearchParams( window.location.search );
			if ( urlParams.has( 'post_type' ) ) {
				return urlParams.get( 'post_type' );
			}

			// Default to post
			return 'post';
		},

		/**
		 * Get current WPML admin language from the list-table URL first.
		 */
		getCurrentWpmlLanguage() {
			const urlParams = new URLSearchParams( window.location.search );
			if ( urlParams.has( 'lang' ) ) {
				return urlParams.get( 'lang' ) || '';
			}

			const cookieMatch = document.cookie.match(
				/(?:^|;\s*)(?:_icl_current_language|wpml_current_language|wpml_admin_language)=([^;]+)/
			);
			if ( cookieMatch && cookieMatch[ 1 ] ) {
				return decodeURIComponent( cookieMatch[ 1 ] );
			}

			return rslIePostSyncData.wpmlLanguage || '';
		},

		/**
		 * Load remote posts with pagination and filters
		 */
		loadRemotePosts() {
			if ( typeof rslIePostSyncData === 'undefined' ) {
				const errorMsg =
					rslIePostSyncData?.i18n?.pluginDataNotLoaded ||
					'Plugin data not loaded. Please refresh the page.';
				this.showBrowseError( errorMsg );
				return;
			}

			const ajaxUrl = rslIePostSyncData.ajaxurl; // lowercase 'ajaxurl' to match PHP localization
			const nonce = getActionNonce(
				'rsl_ie_content_sync_get_remote_posts'
			);

			$( '#rsl-ie-browse-loading' ).show();
			$( '#rsl-ie-browse-posts-tree' ).hide();

			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'rsl_ie_content_sync_get_remote_posts',
					nonce: nonce,
					site_id: this.browseState.siteId,
					post_type: this.browseState.postType,
					search: this.browseState.searchQuery,
					status: this.browseState.currentFilter,
					page: this.browseState.currentPage,
					per_page: 20,
					language: this.getCurrentWpmlLanguage(),
				},
				success: ( response ) => {
					if (
						response.success &&
						response.data &&
						response.data.posts
					) {
						this.renderPostsTree( response.data.posts );
						this.updatePagination( response.data );
						this.updateFilterCounts( response.data.status_counts );
					} else {
						const errorMsg =
							response.data && response.data.message
								? response.data.message
								: 'Failed to load posts';
						this.showBrowseError( errorMsg );
					}
				},
				error: ( xhr ) => {
					let errorMessage =
						rslIePostSyncData?.i18n?.errorLoadingPosts ||
						'An error occurred while loading posts';
					if (
						xhr.responseJSON &&
						xhr.responseJSON.data &&
						xhr.responseJSON.data.message
					) {
						errorMessage = xhr.responseJSON.data.message;
					}
					this.showBrowseError( errorMessage );
				},
			} );
		} /**
		 * Render posts tree
		 */,
		renderPostsTree( posts ) {
			$( '#rsl-ie-browse-loading' ).hide();

			if ( ! posts || posts.length === 0 ) {
				const noPostsText =
					rslIePostSyncData?.i18n?.noPostsFound || 'No posts found';
				$( '#rsl-ie-browse-posts-tree' )
					.html(
						`
					<div class="rsl-ie-loading-posts">
						<span class="dashicons dashicons-admin-post" style="font-size: 48px; opacity: 0.3; width: auto; height: auto;"></span>
						<p>${ noPostsText }</p>
					</div>
				`
					)
					.show();
				$( '#rsl-ie-browse-bulk-actions' ).hide();
				return;
			}

			const $tree = $( '#rsl-ie-browse-posts-tree' );
			$tree.empty();

			posts.forEach( ( post ) => {
				const $item = this.createPostItem( post );
				$tree.append( $item );
			} );

			$tree.show();
			$( '#rsl-ie-browse-bulk-actions' ).show();
			this.updateBrowseSelection();
		},

		/**
		 * Create post item element
		 */
		createPostItem( post ) {
			const hasChildren = post.children_count > 0;
			const isSelected = this.browseState.selectedPosts.has( post.ID );
			const isExpanded = this.browseState.expandedPosts.has( post.ID );

			const date = new Date( post.post_modified );
			const formattedDate = date.toLocaleDateString();
			const wpmlLanguage =
				post.wpml_language ||
				( post.wpml && post.wpml.language_code
					? post.wpml.language_code
					: '' );
			const wpmlLanguageBadge = wpmlLanguage
				? `<span class="rsl-ie-post-language-badge">${ this.escapeHtml(
						wpmlLanguage.toUpperCase()
				  ) }</span>`
				: '';

			const $wrapper = $( '<div class="rsl-ie-post-wrapper"></div>' );

			const $item = $( `
				<div class="rsl-ie-post-item ${ isSelected ? 'selected' : '' } ${
					hasChildren ? 'has-children' : ''
				}" data-post-id="${ post.ID }">
					${
						hasChildren
							? `<button type="button" class="rsl-ie-post-toggle ${
									isExpanded ? 'expanded' : ''
							  }">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>`
							: '<span style="width: 28px; display: inline-block;"></span>'
					}
					<input type="checkbox" class="rsl-ie-post-checkbox" value="${ post.ID }" ${
						isSelected ? 'checked' : ''
					} />
					<span class="rsl-ie-post-icon">
						<span class="dashicons dashicons-admin-post"></span>
					</span>
					<div class="rsl-ie-post-info">
						<div class="rsl-ie-post-title">${ this.escapeHtml(
							post.post_title ||
								rslIePostSyncData?.i18n?.noTitle ||
								'(No title)'
						) }</div>
						<div class="rsl-ie-post-meta">
							${ wpmlLanguageBadge }
							<span class="rsl-ie-post-status ${ post.post_status }">${
								post.post_status
							}</span>
							<span class="rsl-ie-post-date">${ formattedDate }</span>
							${
								hasChildren
									? ( () => {
											const count = post.children_count;
											const childText =
												count === 1
													? rslIePostSyncData?.i18n
															?.child || 'child'
													: rslIePostSyncData?.i18n
															?.children ||
													  'children';
											return `<span class="rsl-ie-post-children-count">${ count } ${ childText }</span>`;
									  } )()
									: ''
							}
						</div>
					</div>
				</div>
			` );

			$wrapper.append( $item );

			// Add children container if has children
			if ( hasChildren ) {
				const $children = $(
					'<div class="rsl-ie-post-children" style="display: none;"></div>'
				);
				$wrapper.append( $children );
			}

			return $wrapper;
		},

		/**
		 * Update pagination controls
		 */
		updatePagination( data ) {
			const currentPage = parseInt( data.current_page || 1, 10 );
			const totalPages = parseInt( data.pages || 1, 10 );

			this.browseState.currentPage = Number.isNaN( currentPage )
				? 1
				: currentPage;
			this.browseState.totalPages = Number.isNaN( totalPages )
				? 1
				: totalPages;

			$( '#rsl-ie-browse-current-page' ).text(
				this.browseState.currentPage
			);
			$( '#rsl-ie-browse-total-pages' ).text(
				this.browseState.totalPages
			);

			if ( this.browseState.totalPages <= 1 ) {
				$( '#rsl-ie-browse-pagination' ).hide();
				return;
			}

			$( '#rsl-ie-browse-prev-page' ).prop(
				'disabled',
				this.browseState.currentPage <= 1
			);
			$( '#rsl-ie-browse-next-page' ).prop(
				'disabled',
				this.browseState.currentPage >= this.browseState.totalPages
			);

			$( '#rsl-ie-browse-pagination' ).show();
		},

		/**
		 * Update filter counts
		 */
		updateFilterCounts( counts ) {
			if ( ! counts ) return;

			$( '.rsl-ie-filter-item[data-status=""]' )
				.find( '.rsl-ie-filter-count' )
				.text( counts.all || 0 );
			$( '.rsl-ie-filter-item[data-status="publish"]' )
				.find( '.rsl-ie-filter-count' )
				.text( counts.publish || 0 );
			$( '.rsl-ie-filter-item[data-status="draft"]' )
				.find( '.rsl-ie-filter-count' )
				.text( counts.draft || 0 );
			$( '.rsl-ie-filter-item[data-status="pending"]' )
				.find( '.rsl-ie-filter-count' )
				.text( counts.pending || 0 );
		},

		/**
		 * Load children posts
		 */
		loadChildrenPosts( parentId, $childrenContainer ) {
			if ( typeof rslIePostSyncData === 'undefined' ) {
				const errorMsg =
					rslIePostSyncData?.i18n?.pluginDataNotLoaded ||
					'Plugin data not loaded';
				$childrenContainer.html(
					`<div style="padding: 10px; color: #d63638;">${ errorMsg }</div>`
				);
				return;
			}

			const ajaxUrl = rslIePostSyncData.ajaxurl; // lowercase 'ajaxurl' to match PHP localization
			const nonce = getActionNonce(
				'rsl_ie_content_sync_get_children_posts'
			);

			// Show loading
			$childrenContainer
				.html(
					'<div style="padding: 10px; text-align: center;"><span class="spinner is-active"></span></div>'
				)
				.show();

			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'rsl_ie_content_sync_get_children_posts',
					nonce: nonce,
					site_id: this.browseState.siteId,
					parent_id: parentId,
					post_type: this.browseState.postType,
					language: this.getCurrentWpmlLanguage(),
				},
				success: ( response ) => {
					if ( response.success && response.data.children ) {
						$childrenContainer.empty();
						response.data.children.forEach( ( child ) => {
							const $childItem = this.createPostItem( child );
							$childrenContainer.append( $childItem );
						} );
					} else {
						const errorMsg =
							rslIePostSyncData?.i18n?.failedLoadChildren ||
							'Failed to load children';
						$childrenContainer.html(
							`<div style="padding: 10px; color: #d63638;">${ errorMsg }</div>`
						);
					}
				},
				error: () => {
					const errorMsg =
						rslIePostSyncData?.i18n?.errorLoadingChildren ||
						'Error loading children';
					$childrenContainer.html(
						`<div style="padding: 10px; color: #d63638;">${ errorMsg }</div>`
					);
				},
			} );
		},

		/**
		 * Show browse error
		 */
		showBrowseError( message ) {
			$( '#rsl-ie-browse-loading' ).hide();
			$( '#rsl-ie-browse-posts-tree' )
				.html(
					`
				<div class="rsl-ie-loading-posts">
					<span class="dashicons dashicons-warning" style="font-size: 48px; opacity: 0.3; width: auto; height: auto;"></span>
					<p>${ this.escapeHtml( message ) }</p>
				</div>
			`
				)
				.show();
		},

		/**
		 * Update browse selection count and button state
		 */
		updateBrowseSelection() {
			const count = this.browseState.selectedPosts.size;

			// Update count display
			$( '#rsl-ie-browse-selected-count' ).text( count );

			// Enable/disable pull button
			$( '#rsl-ie-browse-pull-btn' ).prop( 'disabled', count === 0 );
		},

		/**
		 * Select or deselect all currently visible posts in the browse modal.
		 *
		 * This intentionally works on posts currently rendered in the modal,
		 * including expanded children, so paginated remote lists do not
		 * unexpectedly select records the user has not reviewed yet.
		 *
		 * @param {boolean} selected Whether visible posts should be selected.
		 */
		setVisibleBrowsePostsSelected( selected ) {
			if ( ! this.browseState || ! this.browseState.selectedPosts ) {
				return;
			}

			$( '#rsl-ie-browse-posts-tree .rsl-ie-post-checkbox:visible' ).each(
				( index, checkbox ) => {
					const $checkbox = $( checkbox );
					const postId = parseInt( $checkbox.val(), 10 );
					const $item = $checkbox.closest( '.rsl-ie-post-item' );

					if ( Number.isNaN( postId ) ) {
						return;
					}

					$checkbox.prop( 'checked', selected );

					if ( selected ) {
						this.browseState.selectedPosts.add( postId );
						$item.addClass( 'selected' );
					} else {
						this.browseState.selectedPosts.delete( postId );
						$item.removeClass( 'selected' );
					}
				}
			);

			this.updateBrowseSelection();
		},

		/**
		 * Pull selected posts from remote site
		 */
		pullSelectedPosts() {
			if ( this.browseState.selectedPosts.size === 0 ) {
				const message =
					rslIePostSyncData?.i18n?.pleaseSelectOnePost ||
					'Please select at least one post';
				alert( message );
				return;
			}

			const remoteIds = Array.from( this.browseState.selectedPosts );
			const siteId = this.browseState.siteId;

			// Create post mapping - all selected posts will be created as new
			const postMapping = {};
			remoteIds.forEach( ( remoteId ) => {
				postMapping[ remoteId ] = 'new';
			} );

			// Close browse modal
			this.closeBrowseModal();

			// Show main sync modal with progress
			$( '#rsl-ie-sync-modal' ).fadeIn( 200 );

			// Hide initial content and show only progress
			$(
				'.rsl-ie-sync-info, .rsl-ie-form-group, .rsl-ie-sync-direction, .rsl-ie-browse-section, .rsl-ie-no-selection-message'
			).css( 'display', 'none' );
			$( '#rsl-ie-sync-progress' ).show();
			$( '.rsl-ie-progress-fill' ).css( 'width', '0%' );
			const pullingText =
				rslIePostSyncData?.i18n?.pullingPosts || 'Pulling posts...';
			$( '.rsl-ie-progress-text' ).text( pullingText );
			$( '#rsl-ie-sync-result' ).hide();

			// Disable buttons during sync
			$(
				'#rsl-ie-sync-push-btn, #rsl-ie-sync-pull-btn, #rsl-ie-sync-site-select'
			).prop( 'disabled', true );

			// Perform pull with mapping
			this.performSync( 'pull', siteId, remoteIds, postMapping );
		},

		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml( text ) {
			const div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Show sync result
		 */
		showResult( type, message ) {
			// Reset syncing flag
			this.isSyncing = false;

			const $result = $( '#rsl-ie-sync-result' );

			// Make sure initial sections stay hidden when showing result
			$(
				'.rsl-ie-sync-info, .rsl-ie-form-group, .rsl-ie-sync-direction, .rsl-ie-browse-section, .rsl-ie-no-selection-message'
			).css( 'display', 'none' );

			$result
				.removeClass( 'notice-success notice-error' )
				.addClass( `notice notice-${ type }` )
				.html( `<p>${ message }</p>` )
				.fadeIn( 200 );

			// Auto-hide success messages
			if ( type === 'success' ) {
				setTimeout( () => {
					$result.fadeOut( 200 );
					this.closeSyncModal();
					// Reload page to show updated content
					location.reload();
				}, 2000 );
			}
		},
	};

	// Initialize on document ready
	$( document ).ready( () => {
		PostSync.init();
	} );

	// Make it globally accessible
	window.rslIePostSync = PostSync;
} )( jQuery );
