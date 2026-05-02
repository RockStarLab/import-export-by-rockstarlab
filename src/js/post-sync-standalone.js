/**
 * Post Sync Standalone Module
 *
 * Handles content synchronization from post list screens
 * This is a standalone version that doesn't require the main app.js
 */

import 'select2';
import 'select2/dist/css/select2.min.css';

const LEGACY_AJAX_PREFIX = 'aie_';
const AJAX_PREFIX = 'rsl_ie_';

const normalizeAjaxAction = ( action ) => {
	if ( ! action || typeof action !== 'string' ) return action;
	if ( action.startsWith( AJAX_PREFIX ) ) return action;
	if ( action.startsWith( LEGACY_AJAX_PREFIX ) ) {
		return AJAX_PREFIX + action.slice( LEGACY_AJAX_PREFIX.length );
	}
	return action;
};

// This standalone script is enqueued on core admin screens (edit.php/post.php)
// where the main bundle (and its ajaxPrefilter) isn't loaded. Normalize legacy
// `aie_` admin-ajax actions to the new `rsl_ie_` prefix.
if (
	typeof window !== 'undefined' &&
	window.jQuery &&
	! window.__rslIeAjaxPrefilterAdded
) {
	window.__rslIeAjaxPrefilterAdded = true;
	window.jQuery.ajaxPrefilter( ( options ) => {
		if ( ! options || ! options.data ) return;

		if ( typeof options.data === 'string' ) {
			options.data = options.data.replace(
				/(^|&)action=aie_/,
				`$1action=${ AJAX_PREFIX }`
			);
			return;
		}

		if ( typeof options.data === 'object' && options.data.action ) {
			options.data.action = normalizeAjaxAction( options.data.action );
		}
	} );
}

( function ( $ ) {
	'use strict';

	const PostSync = {
		// Flag to track if sync is in progress
		isSyncing: false,

		/**
		 * Initialize the module
		 */
		init() {
			this.positionSyncButton();
			this.bindEvents();
		},

		/**
		 * Ensure the Sync button sits next to the Filter button.
		 */
		positionSyncButton() {
			const $btn = $( '#aie-sync-content-btn' );
			const $filterBtn = $( '#post-query-submit' );

			if ( $btn.length && $filterBtn.length ) {
				$btn.insertAfter( $filterBtn );
			}
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			// Open modal when sync button is clicked
			$( document ).on( 'click', '#aie-sync-content-btn', ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				this.openSyncModal();
			} );

			// Close modal
			$( document ).on( 'click', '.aie-modal-close', ( e ) => {
				e.preventDefault();
				e.stopPropagation();
				const $modal = $( e.currentTarget ).closest( '.aie-modal' );
				const modalId = $modal.attr( 'id' );

				if ( modalId === 'aie-browse-modal' ) {
					this.closeBrowseModal( false ); // Close everything when clicking X button
				} else if ( modalId === 'aie-mapping-modal' ) {
					this.closeMappingModal();
				} else if ( modalId === 'aie-sync-modal' ) {
					this.closeSyncModal();
				}
			} );

			$( document ).on( 'click', '.aie-modal', ( e ) => {
				if ( e.target === e.currentTarget ) {
					const modalId = $( e.target ).attr( 'id' );
					if ( modalId === 'aie-browse-modal' ) {
						this.closeBrowseModal( false ); // Close everything when clicking outside modal
					} else if ( modalId === 'aie-mapping-modal' ) {
						this.closeMappingModal();
					} else {
						this.closeSyncModal();
					}
				}
			} );

			// Close modal on backdrop click
			$( document ).on( 'click', '.aie-modal-backdrop', ( e ) => {
				const $modal = $( e.target ).closest( '.aie-modal' );
				if ( $modal.attr( 'id' ) === 'aie-browse-modal' ) {
					this.closeBrowseModal( false ); // Close everything when clicking backdrop
				}
			} ); // Enable/disable sync buttons based on site selection
			$( document ).on( 'change', '#aie-sync-site-select', () => {
				this.updateSyncButtons();
			} );

			// Handle Push button
			$( document ).on( 'click', '#aie-sync-push-btn', ( e ) => {
				e.preventDefault();
				this.syncContent( 'push' );
			} );

			// Handle Pull button
			$( document ).on( 'click', '#aie-sync-pull-btn', ( e ) => {
				e.preventDefault();
				this.syncContent( 'pull' );
			} );

			// Handle Browse Remote button
			$( document ).on(
				'click',
				'#aie-browse-remote-btn, #aie-browse-remote-btn-alt',
				( e ) => {
					e.preventDefault();
					this.openBrowseModal();
				}
			);

			// Browse modal - Search (debounced)
			let searchTimeout;
			$( document ).on( 'input', '#aie-browse-search', ( e ) => {
				clearTimeout( searchTimeout );
				searchTimeout = setTimeout( () => {
					this.browseState.searchQuery = $( e.target ).val();
					this.browseState.currentPage = 1;
					this.loadRemotePosts();
				}, 500 );
			} );

			// Browse modal - Status filter
			$( document ).on( 'click', '.aie-filter-item', ( e ) => {
				const $item = $( e.currentTarget );
				$( '.aie-filter-item' ).removeClass( 'active' );
				$item.addClass( 'active' );

				this.browseState.currentFilter = $item.data( 'status' );
				this.browseState.currentPage = 1;
				this.loadRemotePosts();
			} );

			// Browse modal - Post toggle (expand/collapse)
			$( document ).on( 'click', '.aie-post-toggle', ( e ) => {
				e.stopPropagation();
				const $toggle = $( e.currentTarget );
				const $item = $toggle.closest( '.aie-post-item' );
				const postId = parseInt( $item.data( 'post-id' ) );
				const $children = $item.next( '.aie-post-children' );

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
			$( document ).on( 'change', '.aie-post-checkbox', ( e ) => {
				const $checkbox = $( e.currentTarget );
				const postId = parseInt( $checkbox.val() );
				const $item = $checkbox.closest( '.aie-post-item' );

				if ( $checkbox.prop( 'checked' ) ) {
					this.browseState.selectedPosts.add( postId );
					$item.addClass( 'selected' );
				} else {
					this.browseState.selectedPosts.delete( postId );
					$item.removeClass( 'selected' );
				}

				this.updateBrowseSelection();
			} );

			// Browse modal - Pagination
			$( document ).on( 'click', '#aie-browse-prev-page', () => {
				if ( this.browseState.currentPage > 1 ) {
					this.browseState.currentPage--;
					this.loadRemotePosts();
				}
			} );

			$( document ).on( 'click', '#aie-browse-next-page', () => {
				if (
					this.browseState.currentPage < this.browseState.totalPages
				) {
					this.browseState.currentPage++;
					this.loadRemotePosts();
				}
			} );

			// Browse modal - Cancel button
			$( document ).on( 'click', '#aie-browse-cancel-btn', ( e ) => {
				e.preventDefault();
				this.closeBrowseModal();
			} );

			// Browse modal - Pull button
			$( document ).on( 'click', '#aie-browse-pull-btn', ( e ) => {
				e.preventDefault();
				this.pullSelectedPosts();
			} );

			// Close modal on Escape key
			$( document ).on( 'keydown', ( e ) => {
				if (
					e.key === 'Escape' &&
					$( '#aie-sync-modal' ).is( ':visible' )
				) {
					this.closeSyncModal();
				}
				if (
					e.key === 'Escape' &&
					$( '#aie-browse-modal' ).is( ':visible' )
				) {
					this.closeBrowseModal( false ); // Close everything when pressing Escape
				}
			} );
		},

		/**
		 * Open sync modal
		 */
		openSyncModal() {
			const selectedIds = this.getSelectedPostIds();

			if ( selectedIds.length === 0 ) {
				const isEditPage = $( '#post_ID' ).length > 0;
				// If we're on the post edit page, keep the original behavior (ask to save)
				if ( isEditPage ) {
					const message =
						typeof aiePostSyncData !== 'undefined' &&
						aiePostSyncData.i18n
							? aiePostSyncData.i18n.pleaseSavePost
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
					? aiePostSyncData?.i18n?.onePost || '1 post'
					: (
							aiePostSyncData?.i18n?.postsCount || '%s posts'
					  ).replace( '%s', selectedIds.length );
			$( '#aie-selected-count' ).text( countText );

			// Reset form
			$( '#aie-sync-site-select' ).val( '' );
			$( '#aie-sync-progress' ).hide();
			$( '#aie-sync-result' ).hide();
			this.updateSyncButtons();

			// Show modal
			$( '#aie-sync-modal' ).fadeIn( 200 );
		},

		/**
		 * Close sync modal
		 */
		closeSyncModal( keepSiteSelection = false ) {
			// Reset syncing flag
			this.isSyncing = false;

			$( '#aie-sync-modal' ).fadeOut( 200, () => {
				// Reset site selection (unless specified to keep it)
				if ( ! keepSiteSelection ) {
					$( '#aie-sync-site-select' ).val( '' );
				}

				// Reset modal state - show initial sections again
				$(
					'.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section'
				).css( 'display', '' );
				$(
					'#aie-sync-progress, #aie-sync-result, .aie-no-selection-message'
				).css( 'display', 'none' );
				$( '.aie-progress-fill' ).css( 'width', '0%' );

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
		 * Update sync button states
		 */
		updateSyncButtons() {
			// Don't update UI if sync is in progress
			if ( this.isSyncing ) {
				return;
			}

			const siteSelected = $( '#aie-sync-site-select' ).val() !== '';
			const selectedIds = this.getSelectedPostIds();

			// Show/hide sections based on post selection
			if ( selectedIds.length > 0 ) {
				// Has selected posts - show push/pull and browse sections
				$( '.aie-sync-direction' ).show();
				$( '.aie-browse-section' ).show();
				$( '.aie-sync-info' ).show();
				$( '.aie-no-selection-message' ).hide();
				$(
					'#aie-sync-push-btn, #aie-sync-pull-btn, #aie-browse-remote-btn'
				).prop( 'disabled', ! siteSelected );
			} else {
				// No posts selected - hide push/pull, show only browse message
				$( '.aie-sync-direction' ).hide();
				$( '.aie-browse-section' ).hide();
				$( '.aie-sync-info' ).hide();
				$( '.aie-no-selection-message' ).show();
				$( '#aie-browse-remote-btn-alt' ).prop(
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
			const siteId = $( '#aie-sync-site-select' ).val();
			const postIds = this.getSelectedPostIds();

			if ( ! siteId ) {
				const message =
					aiePostSyncData?.i18n?.pleaseSelectSite ||
					'Please select a site';
				alert( message );
				return;
			}

			if ( postIds.length === 0 ) {
				const message =
					aiePostSyncData?.i18n?.noPostsSelected ||
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
			$( '#aie-mapping-modal' ).fadeIn( 200 );
			$( '#aie-mapping-loading' ).show();
			$( '#aie-mapping-table-container' ).hide();
			$( '#aie-mapping-confirm-btn' ).prop( 'disabled', true );

			// Load local posts info and remote posts list
			this.loadMappingData( direction, siteId, postIds );
		},

		/**
		 * Close mapping modal
		 */
		closeMappingModal() {
			$( '#aie-mapping-modal' ).fadeOut( 200 );
		},

		/**
		 * Load mapping data
		 */
		loadMappingData( direction, siteId, postIds ) {
			const nonce =
				typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce
					? aiePostSyncData.nonce
					: '';
			const ajaxUrl =
				typeof aiePostSyncData !== 'undefined' &&
				aiePostSyncData.ajaxurl
					? aiePostSyncData.ajaxurl
					: ajaxurl;

			// First, get local posts info
			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_content_sync_get_local_posts_info',
					nonce: nonce,
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
								action: 'aie_content_sync_get_remote_posts',
								nonce: nonce,
								site_id: siteId,
								post_type: 'any',
							},
							success: ( response ) => {
								if ( response.success && response.data.posts ) {
									this.remotePosts = response.data.posts;
									this.renderMappingTable(
										postIds,
										response.data.posts
									);
									$( '#aie-mapping-loading' ).hide();
									$( '#aie-mapping-table-container' ).fadeIn(
										200
									);
									$( '#aie-mapping-confirm-btn' ).prop(
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
										aiePostSyncData?.i18n?.unknownError ||
										'Unknown error';
									const message =
										( aiePostSyncData?.i18n
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
									aiePostSyncData?.i18n
										?.failedConnectRemote ||
									'Failed to connect to remote site';
								alert( message );
								this.closeMappingModal();
							},
						} );
					} else {
						const errorMsg =
							localResponse.data?.message ||
							aiePostSyncData?.i18n?.unknownError ||
							'Unknown error';
						const message =
							( aiePostSyncData?.i18n?.failedLoadLocalPosts ||
								'Failed to load local posts info' ) +
							': ' +
							errorMsg;
						alert( message );
						this.closeMappingModal();
					}
				},
				error: ( xhr ) => {
					const message =
						aiePostSyncData?.i18n?.failedLoadLocalPosts ||
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
			const $tbody = $( '#aie-mapping-tbody' );
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
			const postHashText = aiePostSyncData?.i18n?.postHash || 'Post #%s';
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
			const idLabel = aiePostSyncData?.i18n?.idLabel || 'ID:';
			const $localCol = $( '<td>' ).addClass( 'aie-local-post' ).html( `
				<div class="aie-local-post-info">
					<h4>${ postTitle }</h4>
					<div class="aie-post-meta">
						<span class="aie-post-type">${ postType }</span>
						<span class="aie-post-id">${ idLabel } ${ postId }</span>
					</div>
				</div>
			` );

			// Arrow column
			const $arrowCol = $( '<td>' )
				.addClass( 'aie-sync-arrow' )
				.html( '→' );

			// Remote action column
			const $remoteCol = $( '<td>' ).addClass( 'aie-remote-post' );
			const $select = $( '<select>' )
				.addClass( 'aie-remote-select' )
				.attr( 'data-local-id', postId );
			if ( originalRemoteId ) {
				$select.attr( 'data-original-remote-id', originalRemoteId );
			}

			// Add "Create New" option
			const createNewText =
				aiePostSyncData?.i18n?.createNewPost || '➕ Create New Post';
			$select.append(
				`<option value="new" selected class="aie-option-new">${ createNewText }</option>`
			);

			const $wrapper = $( '<div>' ).addClass(
				'aie-remote-select-wrapper aie-action-new'
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
				typeof aiePostSyncData !== 'undefined' &&
				aiePostSyncData.connectedSites
					? aiePostSyncData.connectedSites[ siteId ]
					: null;

			if ( ! siteInfo ) {
				return;
			}

			const ajaxUrl =
				typeof aiePostSyncData !== 'undefined' &&
				aiePostSyncData.ajaxurl
					? aiePostSyncData.ajaxurl
					: ajaxurl;
			const nonce =
				typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce
					? aiePostSyncData.nonce
					: '';

			// Get human-readable post type label
			const postTypeLabel =
				localPostType === 'post'
					? 'post'
					: localPostType === 'page'
					? 'page'
					: localPostType;

			const searchPlaceholder =
				aiePostSyncData?.i18n?.searchForUpdate ||
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
							action: 'aie_content_sync_search_remote_posts',
							nonce: nonce,
							site_id: siteId,
							search: params.term || '',
							page: params.page || 1,
							per_page: 10,
							post_type: localPostType || 'post',
						};
					},
					processResults: ( response, params ) => {
						params.page = params.page || 1;

						if ( ! response.success || ! response.data ) {
							return { results: [] };
						}

						const results = response.data.posts || [];
						const updateTemplate =
							aiePostSyncData?.i18n?.updatePost ||
							'🔄 Update: %1$s (ID: %2$s)';
						const formattedResults = results.map( ( post ) => ( {
							id: post.ID,
							text: updateTemplate
								.replace( '%1$s', post.post_title )
								.replace( '%2$s', post.ID ),
							title: post.post_title,
							post_type: post.post_type,
							post_date: post.post_date,
						} ) );

						// Add "Create New" option at the beginning if it's the first page
						if ( params.page === 1 ) {
							const createNewText =
								aiePostSyncData?.i18n?.createNewPost ||
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
						'.aie-remote-select-wrapper'
					);
					if ( item.id === 'new' ) {
						$wrapper
							.removeClass( 'aie-action-update' )
							.addClass( 'aie-action-new' );
					} else {
						$wrapper
							.removeClass( 'aie-action-new' )
							.addClass( 'aie-action-update' );
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
				const updateTemplate =
					aiePostSyncData?.i18n?.updatePost ||
					'🔄 Update: %1$s (ID: %2$s)';
				const optionText = updateTemplate
					.replace( '%1$s', localPostTitle || '' )
					.replace( '%2$s', originalRemoteId );
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
				.off( 'click', '.aie-modal-close' )
				.on( 'click', '.aie-modal-close', ( e ) => {
					this.closeMappingModal();
				} );

			// Cancel button
			$( document )
				.off( 'click', '#aie-mapping-cancel-btn' )
				.on( 'click', '#aie-mapping-cancel-btn', ( e ) => {
					e.preventDefault();
					this.closeMappingModal();
					// Reopen site selection modal
					this.openSyncModal();
				} );

			// Confirm button
			$( document )
				.off( 'click', '#aie-mapping-confirm-btn' )
				.on( 'click', '#aie-mapping-confirm-btn', ( e ) => {
					e.preventDefault();
					this.confirmMapping();
				} );

			// Auto-match button
			$( document )
				.off( 'click', '#aie-auto-match-btn' )
				.on( 'click', '#aie-auto-match-btn', ( e ) => {
					e.preventDefault();
					this.autoMatchByTitle();
				} );

			// Select change
			$( document )
				.off( 'change', '.aie-remote-select' )
				.on( 'change', '.aie-remote-select', function () {
					const value = $( this ).val();
					const $wrapper = $( this ).closest(
						'.aie-remote-select-wrapper'
					);

					$wrapper.removeClass( 'aie-action-new aie-action-update' );
					if ( value === 'new' ) {
						$wrapper.addClass( 'aie-action-new' );
					} else {
						$wrapper.addClass( 'aie-action-update' );
					}
				} );
		},

		/**
		 * Auto-match posts by title
		 */
		autoMatchByTitle() {
			const siteId = this.currentSiteId;
			const ajaxUrl =
				typeof aiePostSyncData !== 'undefined' &&
				aiePostSyncData.ajaxurl
					? aiePostSyncData.ajaxurl
					: ajaxurl;
			const nonce =
				typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce
					? aiePostSyncData.nonce
					: '';

			// Process each select
			$( '.aie-remote-select' ).each( ( i, select ) => {
				const $select = $( select );
				const localId = $select.data( 'local-id' );
				const $row = $( `tr[data-local-id="${ localId }"]` );
				const localTitle = $row
					.find( '.aie-local-post-info h4' )
					.text()
					.trim();
				const localType = $row.find( '.aie-post-type' ).text().trim();

				// Search for remote post with exact title
				$.ajax( {
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'aie_content_sync_search_remote_posts',
						nonce: nonce,
						site_id: siteId,
						search: localTitle,
						page: 1,
						per_page: 5,
						post_type: localType || 'post',
					},
					success: ( response ) => {
						if (
							response.success &&
							response.data &&
							response.data.posts
						) {
							const posts = response.data.posts;
							let matchFound = false;

							// Try to find exact title match
							for ( const post of posts ) {
								if (
									post.post_title.toLowerCase() ===
									localTitle.toLowerCase()
								) {
									// Create new option if it doesn't exist
									const optionExists =
										$select.find(
											`option[value="${ post.ID }"]`
										).length > 0;
									if ( ! optionExists ) {
										const updateTemplate =
											aiePostSyncData?.i18n?.updatePost ||
											'🔄 Update: %1$s (ID: %2$s)';
										const optionText = updateTemplate
											.replace( '%1$s', post.post_title )
											.replace( '%2$s', post.ID );
										const newOption = new Option(
											optionText,
											post.ID,
											false,
											true
										);
										$select.append( newOption );
									}

									// Set value and trigger change
									$select.val( post.ID ).trigger( 'change' );
									matchFound = true;
									break;
								}
							}

							// If no exact match found, keep "Create New"
							if ( ! matchFound ) {
								$select.val( 'new' ).trigger( 'change' );
							}
						} else {
							// No results, keep "Create New"
							$select.val( 'new' ).trigger( 'change' );
						}
					},
					error: () => {
						// On error, keep "Create New"
						$select.val( 'new' ).trigger( 'change' );
					},
				} );
			} );
		},

		/**
		 * Confirm mapping and start sync
		 */
		confirmMapping() {
			// Collect mapping
			const postMapping = {};
			$( '.aie-remote-select' ).each( function () {
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

				// If no valid remote IDs found (all are "new"), that's fine
				// The backend will create new posts
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
			$( '#aie-sync-modal' ).fadeIn( 200 );

			// Hide initial content and show progress
			$(
				'.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section, .aie-no-selection-message'
			).css( 'display', 'none' );
			$( '#aie-sync-progress' ).show();
			$( '#aie-sync-result' ).hide();
			$( '.aie-progress-fill' ).css( 'width', '0%' );
			const startingText =
				aiePostSyncData?.i18n?.starting || 'Starting %s...';
			$( '.aie-progress-text' ).text(
				startingText.replace( '%s', direction )
			);

			// Disable buttons
			$(
				'#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select'
			).prop( 'disabled', true );

			// Make AJAX request
			const nonce =
				typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce
					? aiePostSyncData.nonce
					: '';
			const ajaxUrl =
				typeof aiePostSyncData !== 'undefined' &&
				aiePostSyncData.ajaxurl
					? aiePostSyncData.ajaxurl
					: ajaxurl;

			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: `aie_content_sync_${ direction }`,
					nonce: nonce,
					site_id: siteId,
					post_ids: postIds,
					post_mapping: JSON.stringify( postMapping ),
				},
				success: ( response ) => {
					if ( response.success ) {
						$( '.aie-progress-fill' ).css( 'width', '100%' );
						const completedText =
							aiePostSyncData?.i18n?.completed || 'Completed!';
						$( '.aie-progress-text' ).text( completedText );

						setTimeout( () => {
							$( '#aie-sync-progress' ).hide();
							const successMsg =
								response.data.message ||
								aiePostSyncData?.i18n?.syncCompletedSuccess ||
								'Sync completed successfully';
							this.showResult( 'success', successMsg );
						}, 500 );
					} else {
						$( '#aie-sync-progress' ).hide();
						const errorMsg =
							response.data.message ||
							aiePostSyncData?.i18n?.syncFailed ||
							'Sync failed';
						this.showResult( 'error', errorMsg );
					}
				},
				error: ( xhr ) => {
					$( '#aie-sync-progress' ).hide();

					let errorMessage =
						aiePostSyncData?.i18n?.errorDuringSync ||
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
					// Re-enable buttons
					$(
						'#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select'
					).prop( 'disabled', false );
					this.updateSyncButtons();
				},
			} );
		},

		/**
		 * Open browse remote posts modal
		 */
		openBrowseModal() {
			const siteId = $( '#aie-sync-site-select' ).val();
			if ( ! siteId ) {
				// If no site selected, open the main sync modal to select a site first
				$( '#aie-sync-site-select' ).val( '' );
				$( '#aie-sync-progress' ).hide();
				$( '#aie-sync-result' ).hide();
				this.updateSyncButtons();

				$( '.aie-sync-direction' ).hide();
				$( '.aie-sync-info' ).hide();

				$( '#aie-sync-modal' ).fadeIn( 200 );

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
			$( '#aie-browse-search' ).val( '' );
			$( '#aie-browse-posts-tree' ).empty().hide();
			$( '#aie-browse-loading' ).show();
			$( '#aie-browse-pagination' ).hide();
			$( '#aie-browse-pull-btn' ).prop( 'disabled', true );
			$( '#aie-browse-selected-count' ).text( '0' );

			// Reset filters
			$( '.aie-filter-item' ).removeClass( 'active' );
			$( '.aie-filter-item[data-status=""]' ).addClass( 'active' );

			// Show browse modal
			$( '#aie-browse-modal' ).fadeIn( 200 );

			// Load remote posts
			this.loadRemotePosts();
		},

		/**
		 * Close browse modal
		 * @param {boolean} returnToChooseSite - If true, return to Choose Site modal; if false, close everything
		 */
		closeBrowseModal( returnToChooseSite = true ) {
			$( '#aie-browse-modal' ).fadeOut( 200, () => {
				if ( returnToChooseSite ) {
					// Return back to Choose Site modal with site selection preserved
					$( '#aie-sync-modal' ).fadeIn( 200, () => {
						// Update UI to reflect current state (hide Push/Pull if no posts selected)
						this.updateSyncButtons();
					} );
				} else {
					// Close everything and reset
					$( '#aie-sync-site-select' ).val( '' ).trigger( 'change' );
					this.updateSyncButtons();
				}
			} );
		},

		/**
		 * Get current post type from screen
		 */
		getCurrentPostType() {
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
		 * Load remote posts with pagination and filters
		 */
		loadRemotePosts() {
			if ( typeof aiePostSyncData === 'undefined' ) {
				const errorMsg =
					aiePostSyncData?.i18n?.pluginDataNotLoaded ||
					'Plugin data not loaded. Please refresh the page.';
				this.showBrowseError( errorMsg );
				return;
			}

			const ajaxUrl = aiePostSyncData.ajaxurl; // lowercase 'ajaxurl' to match PHP localization
			const nonce = aiePostSyncData.nonce;

			$( '#aie-browse-loading' ).show();
			$( '#aie-browse-posts-tree' ).hide();

			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_content_sync_get_remote_posts',
					nonce: nonce,
					site_id: this.browseState.siteId,
					post_type: this.browseState.postType,
					search: this.browseState.searchQuery,
					status: this.browseState.currentFilter,
					page: this.browseState.currentPage,
					per_page: 20,
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
						aiePostSyncData?.i18n?.errorLoadingPosts ||
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
			$( '#aie-browse-loading' ).hide();

			if ( ! posts || posts.length === 0 ) {
				const noPostsText =
					aiePostSyncData?.i18n?.noPostsFound || 'No posts found';
				$( '#aie-browse-posts-tree' )
					.html(
						`
					<div class="aie-loading-posts">
						<span class="dashicons dashicons-admin-post" style="font-size: 48px; opacity: 0.3; width: auto; height: auto;"></span>
						<p>${ noPostsText }</p>
					</div>
				`
					)
					.show();
				return;
			}

			const $tree = $( '#aie-browse-posts-tree' );
			$tree.empty();

			posts.forEach( ( post ) => {
				const $item = this.createPostItem( post );
				$tree.append( $item );
			} );

			$tree.show();
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

			const $wrapper = $( '<div class="aie-post-wrapper"></div>' );

			const $item = $( `
				<div class="aie-post-item ${ isSelected ? 'selected' : '' } ${
					hasChildren ? 'has-children' : ''
				}" data-post-id="${ post.ID }">
					${
						hasChildren
							? `<button type="button" class="aie-post-toggle ${
									isExpanded ? 'expanded' : ''
							  }">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>`
							: '<span style="width: 28px; display: inline-block;"></span>'
					}
					<input type="checkbox" class="aie-post-checkbox" value="${ post.ID }" ${
						isSelected ? 'checked' : ''
					} />
					<span class="aie-post-icon">
						<span class="dashicons dashicons-admin-post"></span>
					</span>
					<div class="aie-post-info">
						<div class="aie-post-title">${ this.escapeHtml(
							post.post_title ||
								aiePostSyncData?.i18n?.noTitle ||
								'(No title)'
						) }</div>
						<div class="aie-post-meta">
							<span class="aie-post-status ${ post.post_status }">${ post.post_status }</span>
							<span class="aie-post-date">${ formattedDate }</span>
							${
								hasChildren
									? ( () => {
											const count = post.children_count;
											const childText =
												count === 1
													? aiePostSyncData?.i18n
															?.child || 'child'
													: aiePostSyncData?.i18n
															?.children ||
													  'children';
											return `<span class="aie-post-children-count">${ count } ${ childText }</span>`;
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
					'<div class="aie-post-children" style="display: none;"></div>'
				);
				$wrapper.append( $children );
			}

			return $wrapper;
		},

		/**
		 * Update pagination controls
		 */
		updatePagination( data ) {
			if ( ! data.pages || data.pages <= 1 ) {
				$( '#aie-browse-pagination' ).hide();
				return;
			}

			this.browseState.currentPage = data.current_page;
			this.browseState.totalPages = data.pages;

			$( '#aie-browse-current-page' ).text( data.current_page );
			$( '#aie-browse-total-pages' ).text( data.pages );

			$( '#aie-browse-prev-page' ).prop(
				'disabled',
				data.current_page <= 1
			);
			$( '#aie-browse-next-page' ).prop(
				'disabled',
				data.current_page >= data.pages
			);

			$( '#aie-browse-pagination' ).show();
		},

		/**
		 * Update filter counts
		 */
		updateFilterCounts( counts ) {
			if ( ! counts ) return;

			$( '.aie-filter-item[data-status=""]' )
				.find( '.aie-filter-count' )
				.text( counts.all || 0 );
			$( '.aie-filter-item[data-status="publish"]' )
				.find( '.aie-filter-count' )
				.text( counts.publish || 0 );
			$( '.aie-filter-item[data-status="draft"]' )
				.find( '.aie-filter-count' )
				.text( counts.draft || 0 );
			$( '.aie-filter-item[data-status="pending"]' )
				.find( '.aie-filter-count' )
				.text( counts.pending || 0 );
		},

		/**
		 * Load children posts
		 */
		loadChildrenPosts( parentId, $childrenContainer ) {
			if ( typeof aiePostSyncData === 'undefined' ) {
				const errorMsg =
					aiePostSyncData?.i18n?.pluginDataNotLoaded ||
					'Plugin data not loaded';
				$childrenContainer.html(
					`<div style="padding: 10px; color: #d63638;">${ errorMsg }</div>`
				);
				return;
			}

			const ajaxUrl = aiePostSyncData.ajaxurl; // lowercase 'ajaxurl' to match PHP localization
			const nonce = aiePostSyncData.nonce;

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
					action: 'aie_content_sync_get_children_posts',
					nonce: nonce,
					site_id: this.browseState.siteId,
					parent_id: parentId,
					post_type: this.browseState.postType,
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
							aiePostSyncData?.i18n?.failedLoadChildren ||
							'Failed to load children';
						$childrenContainer.html(
							`<div style="padding: 10px; color: #d63638;">${ errorMsg }</div>`
						);
					}
				},
				error: () => {
					const errorMsg =
						aiePostSyncData?.i18n?.errorLoadingChildren ||
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
			$( '#aie-browse-loading' ).hide();
			$( '#aie-browse-posts-tree' )
				.html(
					`
				<div class="aie-loading-posts">
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
			$( '#aie-browse-selected-count' ).text( count );

			// Enable/disable pull button
			$( '#aie-browse-pull-btn' ).prop( 'disabled', count === 0 );
		},

		/**
		 * Pull selected posts from remote site
		 */
		pullSelectedPosts() {
			if ( this.browseState.selectedPosts.size === 0 ) {
				const message =
					aiePostSyncData?.i18n?.pleaseSelectOnePost ||
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
			$( '#aie-sync-modal' ).fadeIn( 200 );

			// Hide initial content and show only progress
			$(
				'.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section, .aie-no-selection-message'
			).css( 'display', 'none' );
			$( '#aie-sync-progress' ).show();
			$( '.aie-progress-fill' ).css( 'width', '0%' );
			const pullingText =
				aiePostSyncData?.i18n?.pullingPosts || 'Pulling posts...';
			$( '.aie-progress-text' ).text( pullingText );
			$( '#aie-sync-result' ).hide();

			// Disable buttons during sync
			$(
				'#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select'
			).prop( 'disabled', true );

			// Animate progress
			setTimeout( () => {
				$( '.aie-progress-fill' ).css( 'width', '50%' );
			}, 100 );

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

			const $result = $( '#aie-sync-result' );

			// Make sure initial sections stay hidden when showing result
			$(
				'.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section, .aie-no-selection-message'
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
	window.aiePostSync = PostSync;
} )( jQuery );
