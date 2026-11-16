import syncButtonBusy from '../utils/sync-button-busy';
import TabProgress from '../utils/tab-progress';
import { __ } from '@wordpress/i18n';
/**
 * Post Sync Module
 *
 * Handles content synchronization from post list screens
 */

const PostSync = {
	/**
	 * Initialize the module
	 */
	init() {
		this.bindEvents();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $ = jQuery;

		// Open modal when sync button is clicked
		$( document ).on( 'click', '#rsl-ie-sync-content-btn', ( e ) => {
			e.preventDefault();
			e.stopPropagation();
			this.openSyncModal();
		} );

		// Close modal
		$( document ).on(
			'click',
			'.rsl-ie-modal-close, .rsl-ie-modal',
			( e ) => {
				if ( e.target === e.currentTarget ) {
					this.closeSyncModal();
				}
			}
		);

		// Enable/disable sync buttons based on site selection
		$( document ).on( 'change', '#rsl-ie-sync-site-select', () => {
			this.updateSyncButtons();
		} );

		// Handle Push button
		$( document ).on( 'click', '#rsl-ie-sync-push-btn', () => {
			this.syncContent( 'push' );
		} );

		// Handle Pull button
		$( document ).on( 'click', '#rsl-ie-sync-pull-btn', () => {
			this.syncContent( 'pull' );
		} );

		// Close modal on Escape key
		$( document ).on( 'keydown', ( e ) => {
			if (
				e.key === 'Escape' &&
				$( '#rsl-ie-sync-modal' ).is( ':visible' )
			) {
				this.closeSyncModal();
			}
		} );
	},

	/**
	 * Open sync modal
	 */
	openSyncModal() {
		const $ = jQuery;
		const selectedIds = this.getSelectedPostIds();

		if ( selectedIds.length === 0 ) {
			alert( window.rslIeData.i18n.selectAtLeastOnePost );
			return;
		}

		// Update selected count
		$( '#rsl-ie-selected-count' ).text( selectedIds.length );

		// Reset form
		$( '#rsl-ie-sync-site-select' ).val( '' );
		$( '#rsl-ie-sync-progress' ).hide();
		$( '#rsl-ie-sync-result' ).hide();
		this.updateSyncButtons();

		// Show modal
		$( '#rsl-ie-sync-modal' ).fadeIn( 200 );
	},

	/**
	 * Close sync modal
	 */
	closeSyncModal() {
		jQuery( '#rsl-ie-sync-modal' ).fadeOut( 200 );
	},

	/**
	 * Get selected post IDs
	 */
	getSelectedPostIds() {
		const $ = jQuery;
		const ids = [];

		$( 'tbody .check-column input[type="checkbox"]:checked' ).each(
			function () {
				const id = $( this ).val();
				if ( id ) {
					ids.push( id );
				}
			}
		);

		return ids;
	},

	/**
	 * Update sync button states
	 */
	updateSyncButtons() {
		const $ = jQuery;
		const siteSelected = $( '#rsl-ie-sync-site-select' ).val() !== '';

		$( '#rsl-ie-sync-push-btn, #rsl-ie-sync-pull-btn' ).prop(
			'disabled',
			! siteSelected
		);
	},

	/**
	 * Sync content (push or pull)
	 */
	syncContent( direction ) {
		const $ = jQuery;
		const siteId = $( '#rsl-ie-sync-site-select' ).val();
		const postIds = this.getSelectedPostIds();

		if ( ! siteId ) {
			alert( window.rslIeData.i18n.selectSite );
			return;
		}

		if ( postIds.length === 0 ) {
			alert( window.rslIeData.i18n.noPostsSelected );
			return;
		}

		// Confirm action
		const siteName = $( '#rsl-ie-sync-site-select option:selected' ).text();
		const actionText =
			direction === 'push'
				? window.rslIeData?.i18n?.pushTo ||
				  __( 'push to', 'import-export-by-rockstarlab' )
				: window.rslIeData?.i18n?.pullFrom ||
				  __( 'pull from', 'import-export-by-rockstarlab' );

		const message = (
			window.rslIeData?.i18n?.confirmSyncAction ||
			__(
				'Are you sure you want to %1$s %2$s?\n\nThis will affect %3$s post(s).',
				'import-export-by-rockstarlab'
			)
		)
			.replace( '%1$s', actionText )
			.replace( '%2$s', siteName )
			.replace( '%3$s', postIds.length );

		if ( ! confirm( message ) ) {
			return;
		}

		// Show enhanced progress
		$( '#rsl-ie-sync-progress' ).show();
		$( '#rsl-ie-sync-result' ).hide();

		const preparingMsg =
			direction === 'push'
				? window.rslIeData?.i18n?.preparingToPush ||
				  __(
						'Preparing to push content...',
						'import-export-by-rockstarlab'
				  )
				: window.rslIeData?.i18n?.preparingToPull ||
				  __(
						'Preparing to pull content...',
						'import-export-by-rockstarlab'
				  );

		this.updateProgress( 0, preparingMsg, {
			posts: 0,
			images: 0,
			total: postIds.length,
		} );

		const stopButtonBusy = syncButtonBusy(
			$( `#rsl-ie-sync-${ direction }-btn` ),
			window.rslIeData?.i18n?.loading ||
				__( 'Loading...', 'import-export-by-rockstarlab' )
		);

		// Disable buttons
		$(
			'#rsl-ie-sync-push-btn, #rsl-ie-sync-pull-btn, #rsl-ie-sync-site-select'
		).prop( 'disabled', true );

		// Simulate progress for better UX
		let simulatedProgress = 10;
		const progressInterval = setInterval( () => {
			if ( simulatedProgress < 90 ) {
				simulatedProgress += 5;
				const progressMsg =
					direction === 'push'
						? window.rslIeData?.i18n?.uploadingContent ||
						  __(
								'Uploading content...',
								'import-export-by-rockstarlab'
						  )
						: window.rslIeData?.i18n?.downloadingContent ||
						  __(
								'Downloading content...',
								'import-export-by-rockstarlab'
						  );

				this.updateProgress( simulatedProgress, progressMsg, {
					posts: Math.floor(
						( postIds.length * simulatedProgress ) / 100
					),
					total: postIds.length,
				} );
			}
		}, 300 );

		// Make AJAX request
		const nonce =
			typeof rslIePostSyncData !== 'undefined' && rslIePostSyncData.nonce
				? rslIePostSyncData.nonce
				: typeof rslIeContentSync !== 'undefined' &&
				  rslIeContentSync.nonce
				? rslIeContentSync.nonce
				: typeof rslIeData !== 'undefined' && rslIeData.nonce
				? rslIeData.nonce
				: '';

		const ajaxUrl =
			typeof ajaxurl !== 'undefined'
				? ajaxurl
				: typeof rslIePostSyncData !== 'undefined' &&
				  rslIePostSyncData.ajaxurl
				? rslIePostSyncData.ajaxurl
				: typeof rslIeData !== 'undefined' && rslIeData.ajaxUrl
				? rslIeData.ajaxUrl
				: '';

		const ajaxData = {
			action: `rsl_ie_content_sync_${ direction }`,
			nonce: nonce,
			site_id: siteId,
			post_ids: postIds,
		};

		$.ajax( {
			url: ajaxUrl,
			type: 'POST',
			data: ajaxData,
			success: ( response ) => {
				clearInterval( progressInterval );
				if ( response.success ) {
					const data = response.data || {};
					const imageCount = data.images_synced || 0;

					this.updateProgress(
						100,
						window.rslIeData?.i18n?.operationCompleted ||
							__(
								'Completed successfully!',
								'import-export-by-rockstarlab'
							),
						{
							posts: postIds.length,
							images: imageCount,
							total: postIds.length,
						}
					);

					setTimeout( () => {
						$( '#rsl-ie-sync-progress' ).hide();

						// Build detailed success message
						let successMsg =
							response.message ||
							response.data?.message ||
							window.rslIeData?.i18n?.syncCompletedSuccessfully ||
							__(
								'Sync completed successfully',
								'import-export-by-rockstarlab'
							);
						if ( data.created && data.updated ) {
							const createdUpdatedMsg = (
								window.rslIeData?.i18n?.createdPosts ||
								__(
									'✓ Created %d post(s), Updated %d post(s)',
									'import-export-by-rockstarlab'
								)
							)
								.replace( '%d', data.created )
								.replace( '%d', data.updated );
							successMsg = createdUpdatedMsg;
						}
						if ( imageCount > 0 ) {
							const syncedImagesMsg = (
								window.rslIeData?.i18n?.syncedImages ||
								__(
									'✓ Synced %d image(s)',
									'import-export-by-rockstarlab'
								)
							).replace( '%d', imageCount );
							successMsg += `<br>${ syncedImagesMsg }`;
						}

						this.showResult( 'success', successMsg );
					}, 800 );
				} else {
					this.updateProgress(
						0,
						window.rslIeData?.i18n?.syncFailed ||
							__( 'Sync failed', 'import-export-by-rockstarlab' ),
						{}
					);
					setTimeout( () => {
						$( '#rsl-ie-sync-progress' ).hide();
						this.showResult(
							'error',
							response.message ||
								response.data?.message ||
								window.rslIeData?.i18n?.syncFailed ||
								__(
									'Sync failed',
									'import-export-by-rockstarlab'
								)
						);
					}, 500 );
				}
			},
			error: ( xhr ) => {
				clearInterval( progressInterval );
				$( '#rsl-ie-sync-progress' ).hide();

				let errorMessage =
					window.rslIeData?.i18n?.errorOccurredDuringSync ||
					__(
						'An error occurred during sync',
						'import-export-by-rockstarlab'
					);
				if (
					xhr.responseJSON?.message ||
					xhr.responseJSON?.data?.message
				) {
					errorMessage =
						xhr.responseJSON.message ||
						xhr.responseJSON.data.message;
				}

				this.showResult( 'error', errorMessage );
			},
			complete: () => {
				stopButtonBusy();
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
	 * Update progress bar with details
	 */
	updateProgress( percent, message, details = {} ) {
		TabProgress.update( 'sync', percent );
		const $ = jQuery;

		$( '.rsl-ie-progress-fill' ).css( 'width', `${ percent }%` );

		// Build detailed progress message
		let progressText = `<strong>${ message }</strong>`;

		if ( details.posts !== undefined && details.total ) {
			const postsMsg = (
				window.rslIeData?.i18n?.postsProgress ||
				__( 'Posts: %1$s/%2$s', 'import-export-by-rockstarlab' )
			)
				.replace( '%1$s', details.posts )
				.replace( '%2$s', details.total );
			progressText += `<br><span class="progress-details">${ postsMsg }</span>`;
		}

		if ( details.images !== undefined && details.images > 0 ) {
			const imagesMsg = (
				window.rslIeData?.i18n?.imagesSyncedProgress ||
				__( 'Images synced: %d', 'import-export-by-rockstarlab' )
			).replace( '%d', details.images );
			progressText += `<br><span class="progress-details">${ imagesMsg }</span>`;
		}

		if ( percent > 0 && percent < 100 ) {
			progressText += `<br><span class="progress-percentage">${ Math.round(
				percent
			) }%</span>`;
		}

		$( '.rsl-ie-progress-text' ).html( progressText );
	},

	/**
	 * Show sync result
	 */
	showResult( type, message ) {
		TabProgress.finish(
			'sync',
			type === 'success' ? 'completed' : 'failed'
		);
		const $ = jQuery;
		const $result = $( '#rsl-ie-sync-result' );

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

export default PostSync;
