import syncButtonBusy from './utils/sync-button-busy';
/**
 * PRO Media Library Sync quick action.
 */

( function ( $ ) {
	'use strict';

	const config = window.rslIeProMediaLibrarySync || {};
	const buttonClass = 'rsl-ie-pro-media-library-sync-button';
	const modalId = 'rsl-ie-pro-media-sync-modal';
	const browseModalId = 'rsl-ie-pro-media-sync-browse-modal';
	const remoteMediaState = {
		page: 1,
		pages: 1,
		loading: false,
		search: '',
	};

	const normalizeAjaxAction = ( action ) =>
		action && action.indexOf( 'rsl_ie_' ) === 0
			? action
			: `rsl_ie_${ action }`;

	const getNonce = ( action ) =>
		config.nonces?.[ normalizeAjaxAction( action ) ] || '';

	$.ajaxPrefilter( ( options, originalOptions ) => {
		const data = originalOptions.data;
		const action =
			data && typeof data === 'object' ? data.action || '' : '';
		const nonce = getNonce( action );
		if ( nonce && options.data && typeof options.data === 'object' ) {
			options.data.nonce = nonce;
		}
	} );

	function escapeHtml( value ) {
		return String( value || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function getErrorMessage( error, fallback ) {
		if ( ! error ) {
			return fallback || config.i18n?.syncFailed || 'Sync failed.';
		}

		if ( typeof error === 'string' ) {
			return error;
		}

		if ( error.responseJSON ) {
			return getErrorMessage( error.responseJSON, fallback );
		}

		if ( error.data ) {
			return getErrorMessage( error.data, fallback );
		}

		if ( error.message ) {
			return error.message;
		}

		if ( error.error ) {
			return getErrorMessage( error.error, fallback );
		}

		if ( error.responseText ) {
			try {
				return getErrorMessage(
					JSON.parse( error.responseText ),
					fallback
				);
			} catch ( exception ) {
				return error.responseText;
			}
		}

		if ( error.statusText ) {
			return error.statusText;
		}

		return fallback || config.i18n?.syncFailed || 'Sync failed.';
	}

	function isImageMedia( item ) {
		return (
			item && item.mime_type && item.mime_type.indexOf( 'image/' ) === 0
		);
	}

	function getRemoteMediaGridStyles() {
		return [
			'display:grid',
			'grid-template-columns:repeat(auto-fit,minmax(180px,1fr))',
			'gap:14px',
			'align-items:stretch',
		].join( ';' );
	}

	function getSelectedMediaIds() {
		const ids = [];

		if (
			window.wp &&
			window.wp.media &&
			window.wp.media.frame &&
			typeof window.wp.media.frame.state === 'function'
		) {
			const state = window.wp.media.frame.state();
			const selection =
				state && typeof state.get === 'function'
					? state.get( 'selection' )
					: null;
			if ( selection && typeof selection.each === 'function' ) {
				selection.each( ( attachment ) => {
					const id =
						attachment && typeof attachment.get === 'function'
							? attachment.get( 'id' )
							: attachment && attachment.id;
					if ( id ) {
						ids.push( String( id ) );
					}
				} );
			}
		}

		$( '.attachments .attachment.selected' ).each( function () {
			const id = $( this ).data( 'id' );
			if ( id ) {
				ids.push( String( id ) );
			}
		} );

		// List mode (upload.php?mode=list): rows are picked with table checkboxes.
		$( '#the-list input[name="media[]"]:checked' ).each( function () {
			const id = $( this ).val();
			if ( id ) {
				ids.push( String( id ) );
			}
		} );

		return ids
			.filter( ( id ) => /^\d+$/.test( id ) && parseInt( id, 10 ) > 0 )
			.filter( ( id, index, allIds ) => allIds.indexOf( id ) === index );
	}

	function getToolbarTarget() {
		const selectors = [ '.wrap .page-title-action', '.wp-heading-inline' ];

		for ( let index = 0; index < selectors.length; index++ ) {
			const $target = $( selectors[ index ] ).first();
			if ( $target.length ) {
				return $target;
			}
		}

		return $();
	}

	function ensureButton() {
		const $toolbar = getToolbarTarget();
		const $existing = $( `.${ buttonClass }` );
		if ( ! $toolbar.length ) {
			return;
		}

		if ( $existing.length ) {
			if ( ! $.contains( $toolbar[ 0 ], $existing[ 0 ] ) ) {
				$existing.detach();
				insertButton( $toolbar, $existing );
			}
			return;
		}

		insertButton(
			$toolbar,
			$( '<button>', {
				type: 'button',
				class: `page-title-action ${ buttonClass }`,
				text: config.i18n?.sync || 'Sync',
				title: config.i18n?.syncMedia || 'Sync Media',
			} )
		);
	}

	function insertButton( $toolbar, $button ) {
		if ( $toolbar.hasClass( 'page-title-action' ) ) {
			$button.insertAfter( $toolbar );
		} else {
			$button.insertAfter( $toolbar );
		}
	}

	function updateButtonState() {
		ensureButton();
	}

	function getSitesOptions() {
		const sites = config.connectedSites || {};
		const options = [
			`<option value="">${ escapeHtml(
				config.i18n?.selectSite || 'Please select a site.'
			) }</option>`,
		];

		Object.keys( sites ).forEach( ( id ) => {
			const site = sites[ id ];
			options.push(
				`<option value="${ escapeHtml( id ) }">${ escapeHtml(
					site.name || site.remote_url || id
				) }</option>`
			);
		} );

		return options.join( '' );
	}

	function hasConnectedSites() {
		const sites =
			config.connectedSites && typeof config.connectedSites === 'object'
				? config.connectedSites
				: {};

		return Object.keys( sites ).length > 0;
	}

	function getContentSyncUrl() {
		if (
			typeof config.contentSyncUrl === 'string' &&
			config.contentSyncUrl
		) {
			return config.contentSyncUrl;
		}

		if ( typeof window.ajaxurl === 'string' && window.ajaxurl ) {
			return (
				window.ajaxurl.replace( /admin-ajax\.php.*$/, 'admin.php' ) +
				'?page=rsl-ie-content-sync'
			);
		}

		if ( typeof config.ajaxurl === 'string' && config.ajaxurl ) {
			return (
				config.ajaxurl.replace( /admin-ajax\.php.*$/, 'admin.php' ) +
				'?page=rsl-ie-content-sync'
			);
		}

		return 'admin.php?page=rsl-ie-content-sync';
	}

	function redirectToContentSyncIfNoSites() {
		if ( hasConnectedSites() ) {
			return false;
		}

		window.location.href = getContentSyncUrl();
		return true;
	}

	function ensureSyncModal() {
		if ( $( `#${ modalId }` ).length ) {
			return;
		}

		$( 'body' ).append( `
			<div id="${ modalId }" class="rsl-ie-modal rsl-ie-mapping-modal" style="display:none;">
				<div class="rsl-ie-modal-content">
					<div class="rsl-ie-modal-header">
						<h2>${ escapeHtml( config.i18n?.syncMedia || 'Sync Media' ) }</h2>
						<button type="button" class="rsl-ie-modal-close" aria-label="${ escapeHtml(
							config.i18n?.close || 'Close'
						) }">&times;</button>
					</div>
					<div class="rsl-ie-modal-body" style="text-align:center;">
						<div class="rsl-ie-form-group" style="max-width:360px;margin:0 auto 16px;">
							<label for="rsl-ie-pro-media-sync-site" style="display:block;margin-bottom:6px;">${ escapeHtml(
								config.i18n?.selectSiteLabel || 'Select Site'
							) }</label>
							<select id="rsl-ie-pro-media-sync-site" class="rsl-ie-form-control" style="display:block;width:100%;">${ getSitesOptions() }</select>
						</div>
						<div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap;">
							<button type="button" class="button button-primary" id="rsl-ie-pro-media-push-btn" style="width:auto;flex:0 0 auto;">${ escapeHtml(
								config.i18n?.pushMedia || 'Push selected media'
							) }</button>
							<button type="button" class="button" id="rsl-ie-pro-media-browse-btn" style="width:auto;flex:0 0 auto;">${ escapeHtml(
								config.i18n?.browseMedia ||
									'Browse remote media'
							) }</button>
						</div>
						<div id="rsl-ie-pro-media-sync-message" style="margin-top:12px;"></div>
					</div>
				</div>
			</div>
		` );
	}

	function showMessage( message, type = 'info' ) {
		$( '#rsl-ie-pro-media-sync-message' ).html(
			message
				? `<div class="notice notice-${ type } inline"><p>${ escapeHtml(
						message
				  ) }</p></div>`
				: ''
		);
	}

	function request( action, data ) {
		const normalizedAction = normalizeAjaxAction( action );
		return $.ajax( {
			url: config.ajaxurl || window.ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: normalizedAction,
				nonce: getNonce( normalizedAction ),
				...data,
			},
		} ).then(
			( response ) => {
				if ( ! response || ! response.success ) {
					return $.Deferred()
						.reject(
							getErrorMessage(
								response,
								config.i18n?.syncFailed || 'Sync failed.'
							)
						)
						.promise();
				}
				return response.data || response;
			},
			( error ) =>
				$.Deferred()
					.reject(
						getErrorMessage(
							error,
							config.i18n?.syncFailed || 'Sync failed.'
						)
					)
					.promise()
		);
	}

	function openSyncModal() {
		if ( redirectToContentSyncIfNoSites() ) {
			return;
		}

		ensureSyncModal();
		$( `#${ modalId }` ).show();
		showMessage( '' );
	}

	function closeModals() {
		$( `#${ modalId }, #${ browseModalId }` ).hide();
	}

	function trackMediaJob( data, $button, options = {} ) {
		const finished = $.Deferred();
		const batchLabel = options.batchLabel || '';
		const isLast = options.last !== false;
		const summary = options.summary || { failed: 0 };
		closeModals();
		$( '#rsl-ie-media-progress-modal' ).remove();
		const $modal =
			$( `<div id="rsl-ie-media-progress-modal" class="rsl-ie-modal" role="dialog" aria-modal="true" aria-labelledby="rsl-ie-media-progress-title">
			<div class="rsl-ie-modal-content">
				<div class="rsl-ie-modal-header"><h2 id="rsl-ie-media-progress-title">${ escapeHtml(
					config.i18n?.syncMedia || 'Sync Media'
				) }</h2>
				<button type="button" class="rsl-ie-media-progress-close" aria-label="${ escapeHtml(
					config.i18n?.close || 'Close'
				) }">&times;</button></div>
				<div class="rsl-ie-modal-body"><div class="rsl-ie-media-transfer-progress" role="status" aria-live="polite">
					<div class="rsl-ie-media-progress-summary"><strong class="rsl-ie-media-progress-label"></strong><span class="rsl-ie-media-progress-percent">0%</span></div>
					<div class="rsl-ie-progress-bar" role="progressbar" aria-label="${ escapeHtml(
						config.i18n?.syncMedia || 'Sync Media'
					) }" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="rsl-ie-progress-fill"></div></div>
					<p class="rsl-ie-media-progress-count"></p>
				</div></div>
			</div></div>` )
				.appendTo( 'body' )
				.fadeIn( 200 );
		const $progress = $modal.find( '.rsl-ie-media-transfer-progress' );
		$modal.on( 'click', '.rsl-ie-media-progress-close', () => {
			$modal.hide();
			$button.trigger( 'focus' );
		} );
		$modal.find( 'button' ).trigger( 'focus' );
		const fail = ( message ) => {
			$progress.addClass( 'is-error' );
			$progress
				.find( '.rsl-ie-media-progress-label' )
				.text( config.i18n?.syncFailed || 'Sync failed.' );
			$progress.find( '.rsl-ie-media-progress-count' ).text( message );
			$modal.show();
			finished.reject( message );
		};
		$progress
			.find( '.rsl-ie-media-progress-label' )
			.text( config.i18n?.loading || 'Loading...' );
		const poll = () => {
			request( 'pro_sync_media_step', { job_id: data.job_id } )
				.done( ( state ) => {
					const total = state.total || 0;
					const processed = state.processed || 0;
					const percent = total
						? Math.min(
								100,
								Math.round( ( processed / total ) * 100 )
						  )
						: 0;
					$progress
						.find( '.rsl-ie-progress-fill' )
						.css( 'width', percent + '%' );
					$progress
						.find( '.rsl-ie-progress-bar' )
						.attr( 'aria-valuenow', percent );
					$progress
						.find( '.rsl-ie-media-progress-percent' )
						.text( percent + '%' );
					$progress
						.find( '.rsl-ie-media-progress-label' )
						.text(
							( config.i18n?.syncMedia || 'Sync Media' ) +
								( batchLabel ? ' · ' + batchLabel : '' )
						);
					$progress
						.find( '.rsl-ie-media-progress-count' )
						.text( processed + ' / ' + total );
					// A batch where some source files were missing still finished:
					// the other files were copied, so continue with the next batch.
					const partial =
						state.status === 'failed' &&
						state.error_code === 'rsl_ie_sync_job_partial_failure';
					const done = state.status === 'completed' || partial;
					if ( done ) {
						summary.failed += Number( state.result?.failed || 0 );
						summary.skipped =
							Number( summary.skipped || 0 ) +
							Number( state.result?.skipped || 0 );
						summary.created =
							Number( summary.created || 0 ) +
							Number( state.result?.created || 0 );
					}
					if ( done && ! isLast ) {
						finished.resolve( state );
					} else if ( done ) {
						$progress.addClass( 'is-complete' );
						$progress.find( '.rsl-ie-media-progress-label' ).text(
							summary.failed
								? (
										config.i18n?.syncCompleteMissing ||
										'Sync completed. %s file(s) could not be copied because the file is missing on the source site.'
								  ).replace( '%s', summary.failed )
								: summary.skipped
								? // "Completed successfully" after copying nothing read like a copy.
								  (
										config.i18n?.syncCompleteSkipped ||
										'Sync completed. Copied: %1$s. Skipped, already there: %2$s.'
								  )
										.replace( '%1$s', summary.created || 0 )
										.replace( '%2$s', summary.skipped )
								: config.i18n?.syncComplete ||
								  'Sync completed successfully.'
						);
						if ( summary.failed || summary.skipped ) {
							// Keep the message on screen; closing it reloads the library.
							$modal.on(
								'click',
								'.rsl-ie-media-progress-close',
								() => window.location.reload()
							);
						} else {
							window.setTimeout( () => {
								closeModals();
								$modal.hide();
								window.location.reload();
							}, 900 );
						}
						finished.resolve( state );
					} else if (
						[ 'pending', 'processing' ].includes( state.status )
					) {
						window.setTimeout( poll, 100 );
					} else {
						fail( state.error_code || state.status );
					}
				} )
				.fail( ( message ) => {
					fail( message );
				} );
		};
		poll();
		return finished.promise();
	}

	// The server accepts at most 50 media files per job. Larger selections run
	// as consecutive batches of 50, one job after the other.
	const MEDIA_BATCH_SIZE = 50;
	function runMediaBatches( action, siteId, ids, $button ) {
		const batches = [];
		for ( let i = 0; i < ids.length; i += MEDIA_BATCH_SIZE ) {
			batches.push( ids.slice( i, i + MEDIA_BATCH_SIZE ) );
		}
		const batchText = config.i18n?.batchOf || 'batch %1$s of %2$s';
		const summary = { failed: 0 };
		let chain = $.Deferred().resolve().promise();
		batches.forEach( ( batch, index ) => {
			chain = chain.then( () =>
				request( action, { site_id: siteId, media_ids: batch } ).then(
					( data ) =>
						trackMediaJob( data, $button, {
							batchLabel:
								batches.length > 1
									? batchText
											.replace( '%1$s', index + 1 )
											.replace( '%2$s', batches.length )
									: '',
							last: index === batches.length - 1,
							summary,
						} )
				)
			);
		} );
		return chain;
	}

	function pushMedia() {
		const siteId = $( '#rsl-ie-pro-media-sync-site' ).val();
		const ids = getSelectedMediaIds();
		if ( ! siteId ) {
			showMessage(
				config.i18n?.selectSite || 'Please select a site.',
				'warning'
			);
			return;
		}
		if ( ! ids.length ) {
			showMessage(
				config.i18n?.selectMedia || 'Select one or more media files.',
				'warning'
			);
			return;
		}

		const $button = $( '#rsl-ie-pro-media-push-btn' );
		const stopButtonBusy = syncButtonBusy(
			$button,
			config.i18n?.loading || 'Loading...'
		);
		runMediaBatches( 'pro_sync_push_media', siteId, ids, $button )
			.fail( ( message ) => {
				showMessage(
					message || config.i18n?.syncFailed || 'Sync failed.',
					'error'
				);
			} )
			.always( () => {
				stopButtonBusy();
				$button
					.prop( 'disabled', false )
					.text( config.i18n?.pushMedia || 'Push selected media' );
			} );
	}

	function ensureBrowseModal() {
		if ( $( `#${ browseModalId }` ).length ) {
			return;
		}

		$( 'body' ).append( `
			<div id="${ browseModalId }" class="rsl-ie-modal rsl-ie-browse-library-modal" style="display:none;">
				<div class="rsl-ie-modal-content rsl-ie-modal-large">
					<div class="rsl-ie-modal-header">
						<h2>${ escapeHtml( config.i18n?.remoteMedia || 'Remote Media' ) }</h2>
						<button type="button" class="rsl-ie-modal-close" aria-label="${ escapeHtml(
							config.i18n?.close || 'Close'
						) }">&times;</button>
					</div>
					<div class="rsl-ie-browse-search-bar">
						<input type="search" id="rsl-ie-pro-media-search" class="regular-text" placeholder="${ escapeHtml(
							config.i18n?.searchMedia || 'Search media...'
						) }">
					</div>
					<div class="rsl-ie-browse-search-bar" style="padding-top:12px;gap:8px;justify-content:space-between;align-items:center;flex-wrap:wrap;">
						<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
							<button type="button" class="button" id="rsl-ie-pro-media-select-all">${ escapeHtml(
								config.i18n?.selectAll || 'Select all'
							) }</button>
							<button type="button" class="button" id="rsl-ie-pro-media-deselect-all">${ escapeHtml(
								config.i18n?.deselectAll || 'Deselect all'
							) }</button>
						</div>
						<span id="rsl-ie-pro-media-loaded-count" style="opacity:.72;line-height:30px;display:inline-flex;align-items:center;margin-left:auto;"></span>
					</div>
					<div class="rsl-ie-browse-body">
						<div class="rsl-ie-browse-main">
							<div id="rsl-ie-pro-remote-media" class="rsl-ie-posts-tree" style="${ getRemoteMediaGridStyles() }"></div>
							<div id="rsl-ie-pro-media-load-more" style="display:none;text-align:center;padding:16px;">
								<span class="spinner is-active"></span>
								<span>${ escapeHtml( config.i18n?.loading || 'Loading...' ) }</span>
							</div>
						</div>
					</div>
					<div class="rsl-ie-browse-footer">
						<button type="button" class="button" id="rsl-ie-pro-media-browse-cancel">${ escapeHtml(
							config.i18n?.cancel || 'Cancel'
						) }</button>
						<button type="button" class="button button-primary" id="rsl-ie-pro-media-pull-selected-btn">${ escapeHtml(
							config.i18n?.pullSelected || 'Pull selected media'
						) }</button>
					</div>
				</div>
			</div>
		` );
	}

	function updateRemoteMediaCount( data ) {
		const total = parseInt( data?.total || 0, 10 );
		const loaded = $( '.rsl-ie-pro-remote-media-checkbox' ).length;
		const selected = $(
			'.rsl-ie-pro-remote-media-checkbox:checked'
		).length;

		$( '#rsl-ie-pro-media-loaded-count' )
			.data( 'total', total )
			.text(
				total
					? `Loaded: ${ loaded } / ${ total } · Selected: ${ selected }`
					: ''
			);
	}

	function renderRemoteMediaItem( item ) {
		const previewUrl = item.preview_url || item.thumbnail_url || '';
		const preview =
			previewUrl && isImageMedia( item )
				? `<img src="${ escapeHtml(
						previewUrl
				  ) }" alt="" loading="lazy" style="width:100%;height:128px;object-fit:cover;display:block;background:#f0f0f1;">`
				: '<span class="dashicons dashicons-admin-media" style="font-size:48px;width:48px;height:48px;margin:40px auto;display:block;opacity:.45;"></span>';

		return `
			<div class="rsl-ie-post-wrapper" style="margin:0;min-width:0;">
				<label class="rsl-ie-post-item" style="cursor:pointer;height:100%;display:flex;flex-direction:column;padding:0;overflow:hidden;border:1px solid #dcdcde;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04);transition:border-color .15s ease,box-shadow .15s ease,transform .15s ease;">
					<div style="position:relative;background:#f6f7f7;border-bottom:1px solid #f0f0f1;">
						${ preview }
						<input type="checkbox" class="rsl-ie-post-checkbox rsl-ie-pro-remote-media-checkbox" value="${ escapeHtml(
							item.ID
						) }" style="position:absolute;top:10px;left:10px;margin:0;width:18px;height:18px;box-shadow:0 1px 4px rgba(0,0,0,.18);">
					</div>
					<div class="rsl-ie-post-info" style="min-width:0;padding:10px 12px 12px;">
						<div class="rsl-ie-post-title" style="font-weight:600;line-height:1.35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px;">${ escapeHtml(
							item.title || item.file_name || item.ID
						) }</div>
						<div class="rsl-ie-post-meta" style="display:flex;gap:6px;flex-wrap:wrap;font-size:11px;line-height:1.4;color:#646970;">
							<span style="background:#f6f7f7;border-radius:999px;padding:2px 7px;">ID: ${ escapeHtml(
								item.ID
							) }</span>
							${
								item.mime_type
									? `<span style="background:#f6f7f7;border-radius:999px;padding:2px 7px;">${ escapeHtml(
											item.mime_type
									  ) }</span>`
									: ''
							}
							${
								item.file_name
									? `<span title="${ escapeHtml(
											item.file_name
									  ) }" style="max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${ escapeHtml(
											item.file_name
									  ) }</span>`
									: ''
							}
						</div>
					</div>
				</label>
			</div>
		`;
	}

	function loadRemoteMedia( options = {} ) {
		const siteId = $( '#rsl-ie-pro-media-sync-site' ).val();
		const search = $( '#rsl-ie-pro-media-search' ).val() || '';
		const $list = $( '#rsl-ie-pro-remote-media' );
		const append = !! options.append;
		const page = append ? remoteMediaState.page + 1 : 1;

		if ( remoteMediaState.loading ) {
			return;
		}

		if ( append && remoteMediaState.page >= remoteMediaState.pages ) {
			return;
		}

		remoteMediaState.loading = true;
		remoteMediaState.search = search;

		if ( append ) {
			$( '#rsl-ie-pro-media-load-more' ).show();
		} else {
			remoteMediaState.page = 1;
			remoteMediaState.pages = 1;
			updateRemoteMediaCount( {} );
			$list.attr( 'style', getRemoteMediaGridStyles() ).html( `
					<div class="rsl-ie-loading-posts" style="grid-column:1 / -1;">
						<span class="spinner is-active"></span>
						<p>${ escapeHtml( config.i18n?.loading || 'Loading...' ) }</p>
					</div>
				` );
		}

		request( 'pro_sync_get_remote_media', {
			site_id: siteId,
			search,
			page,
		} )
			.done( ( data ) => {
				const items = data.media || [];
				remoteMediaState.page = parseInt(
					data.current_page || page,
					10
				);
				remoteMediaState.pages = parseInt( data.pages || 1, 10 );

				if ( ! append && ! items.length ) {
					$list.html( `
						<div class="rsl-ie-loading-posts" style="grid-column:1 / -1;">
							<span class="dashicons dashicons-admin-media" style="font-size:48px;opacity:.3;width:auto;height:auto;"></span>
							<p>${ escapeHtml( config.i18n?.noItemsFound || 'No media found.' ) }</p>
						</div>
					` );
					updateRemoteMediaCount( data );
					return;
				}

				if ( append ) {
					$list.append(
						items.map( renderRemoteMediaItem ).join( '' )
					);
				} else {
					$list.html( items.map( renderRemoteMediaItem ).join( '' ) );
				}

				updateRemoteMediaCount( data );
			} )
			.fail( ( message ) => {
				const errorHtml = `<div class="notice notice-error inline" style="grid-column:1 / -1;"><p>${ escapeHtml(
					message
				) }</p></div>`;
				if ( append ) {
					$list.append( errorHtml );
				} else {
					$list.html( errorHtml );
				}
			} )
			.always( () => {
				remoteMediaState.loading = false;
				$( '#rsl-ie-pro-media-load-more' ).hide();
			} );
	}

	function maybeLoadMoreRemoteMedia( scrollElement ) {
		const element =
			scrollElement ||
			$( `#${ browseModalId } .rsl-ie-browse-main` )[ 0 ];

		if ( ! element ) {
			return;
		}

		if (
			element.scrollTop + element.clientHeight >=
			element.scrollHeight - 120
		) {
			loadRemoteMedia( { append: true } );
		}
	}

	function openBrowseModal() {
		const siteId = $( '#rsl-ie-pro-media-sync-site' ).val();
		if ( ! siteId ) {
			showMessage(
				config.i18n?.selectSite || 'Please select a site.',
				'warning'
			);
			return;
		}

		ensureBrowseModal();
		$( `#${ browseModalId }` ).show();
		loadRemoteMedia();
	}

	function pullSelectedMedia() {
		const siteId = $( '#rsl-ie-pro-media-sync-site' ).val();
		const ids = [];
		$( '.rsl-ie-pro-remote-media-checkbox:checked' ).each( function () {
			ids.push( $( this ).val() );
		} );
		if ( ! ids.length ) {
			alert(
				config.i18n?.selectRemoteItem ||
					'Please select one or more remote media files.'
			);
			return;
		}

		const $button = $( '#rsl-ie-pro-media-pull-selected-btn' );
		const stopButtonBusy = syncButtonBusy(
			$button,
			config.i18n?.loading || 'Loading...'
		);
		runMediaBatches( 'pro_sync_pull_media', siteId, ids, $button )
			.fail( ( message ) => {
				if ( ! $( '#rsl-ie-media-progress-modal' ).is( ':visible' ) ) {
					alert(
						message || config.i18n?.syncFailed || 'Sync failed.'
					);
				}
			} )
			.always( () => {
				stopButtonBusy();
				$button
					.prop( 'disabled', false )
					.text( config.i18n?.pullSelected || 'Pull selected media' );
			} );
	}

	$( document ).on(
		'click',
		'.select-mode-toggle-button, .attachment, .attachment .check, .delete-selected-button, .media-button-delete-selected',
		() => window.setTimeout( updateButtonState, 50 )
	);

	$( document ).on( 'click', `.${ buttonClass }`, function ( event ) {
		event.preventDefault();
		openSyncModal();
	} );

	$( document ).on(
		'click',
		'.rsl-ie-modal-close, #rsl-ie-pro-media-browse-cancel',
		function ( event ) {
			event.preventDefault();
			closeModals();
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-media-push-btn',
		function ( event ) {
			event.preventDefault();
			pushMedia();
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-media-browse-btn',
		function ( event ) {
			event.preventDefault();
			openBrowseModal();
		}
	);

	let searchTimeout;
	$( document ).on( 'input', '#rsl-ie-pro-media-search', function () {
		window.clearTimeout( searchTimeout );
		searchTimeout = window.setTimeout( () => loadRemoteMedia(), 350 );
	} );

	$( document ).on(
		'change',
		'.rsl-ie-pro-remote-media-checkbox',
		function () {
			$( this )
				.closest( '.rsl-ie-post-item' )
				.toggleClass( 'selected', $( this ).prop( 'checked' ) );
			updateRemoteMediaCount( {
				total:
					$( '#rsl-ie-pro-media-loaded-count' ).data( 'total' ) || 0,
			} );
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-media-select-all',
		function ( event ) {
			event.preventDefault();
			$( '.rsl-ie-pro-remote-media-checkbox' )
				.prop( 'checked', true )
				.trigger( 'change' );
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-media-deselect-all',
		function ( event ) {
			event.preventDefault();
			$( '.rsl-ie-pro-remote-media-checkbox' )
				.prop( 'checked', false )
				.trigger( 'change' );
		}
	);

	$( document ).on(
		'scroll',
		`#${ browseModalId } .rsl-ie-browse-main, #${ browseModalId } .rsl-ie-browse-body, #${ browseModalId } .rsl-ie-modal-content`,
		function () {
			maybeLoadMoreRemoteMedia( this );
		}
	);

	// Scroll events do not bubble, so the delegated handler above never runs for
	// the list. Listen in the capture phase to load more after scrollbar drags
	// and keyboard scrolling too, not only after wheel or touch moves.
	document.addEventListener(
		'scroll',
		( event ) => {
			const target = event.target;
			if (
				target instanceof Element &&
				target.closest( `#${ browseModalId }` )
			) {
				maybeLoadMoreRemoteMedia( target );
			}
		},
		true
	);

	$( document ).on( 'wheel touchmove', `#${ browseModalId }`, function () {
		window.setTimeout( () => maybeLoadMoreRemoteMedia(), 0 );
	} );

	$( document ).on(
		'click',
		'#rsl-ie-pro-media-pull-selected-btn',
		function ( event ) {
			event.preventDefault();
			pullSelectedMedia();
		}
	);

	$( () => {
		ensureButton();
	} );
} )( jQuery );
