/**
 * Admin Object Quick Actions.
 *
 * Adds Export shortcuts and Sync for admin object screens.
 */

import 'select2';
import 'select2/dist/css/select2.min.css';

( function ( $ ) {
	'use strict';

	const config = window.rslIeProAdminObjectQuickActions || {};
	const exportButtonId = 'rsl-ie-pro-export-admin-object-btn';
	const syncButtonId = 'rsl-ie-pro-sync-admin-object-btn';
	const modalId = 'rsl-ie-pro-admin-object-sync-modal';
	const browseModalId = 'rsl-ie-pro-admin-object-browse-modal';
	const pushModalId = 'rsl-ie-pro-admin-object-push-modal';
	let remoteObjectsById = {};
	let commentPostMappingVisible = false;

	function applyExportPrefillIfNeeded() {
		if ( config.mode !== 'export_prefill' ) {
			return false;
		}

		const params = new URLSearchParams( window.location.search );
		if ( params.get( 'rsl_ie_prefill' ) !== 'admin_object' ) {
			return false;
		}

		const objectType = String( params.get( 'object_type' ) || '' )
			.trim()
			.replace( /[^a-zA-Z0-9_-]/g, '' );
		const objectSubtype = String( params.get( 'object_subtype' ) || '' )
			.trim()
			.replace( /[^a-zA-Z0-9_-]/g, '' );
		const ids = String( params.get( 'object_ids' ) || '' )
			.split( ',' )
			.map( ( id ) => id.trim() )
			.filter( ( id ) => /^\d+$/.test( id ) && parseInt( id, 10 ) > 0 );

		const module = window.rslIeExportModule;
		if ( ! module ) {
			window.setTimeout( applyExportPrefillIfNeeded, 100 );
			return true;
		}

		const contentTypeMap = {
			user: 'user',
			comment: 'comment',
			menu: 'menu',
			woo_attribute: 'woo_attribute',
		};
		const contentType = contentTypeMap[ objectType ] || '';
		const $contentType = $(
			`input[name="content_type"][value="${ contentType }"]`
		);

		if ( ! contentType || ! $contentType.length ) {
			if ( typeof module.showStep === 'function' ) {
				module.showStep( 1 );
			}
			return true;
		}

		$contentType.prop( 'checked', true ).trigger( 'change' );

		if ( objectType === 'woo_attribute' && ! ids.length ) {
			module.showStep( 3 );
			return true;
		}

		module.showStep( 2 );
		$( '#rsl-ie-filters-list' ).empty();

		if ( objectType === 'user' && ids.length ) {
			addExportFilter( module, 'ID', 'in', ids.join( ',' ) );
		}

		if ( objectType === 'comment' ) {
			if ( ids.length ) {
				addExportFilter( module, 'comment_ID', 'in', ids.join( ',' ) );
			}
			if ( objectSubtype === 'review' ) {
				addExportFilter( module, 'comment_type', 'equals', 'review' );
			}
		}

		if ( objectType === 'woo_attribute' && ids.length ) {
			addExportFilter( module, 'attribute_id', 'in', ids.join( ',' ) );
		}

		if ( objectType === 'menu' ) {
			const menuName = String( params.get( 'menu_name' ) || '' ).trim();
			if ( menuName ) {
				addExportFilter( module, 'name', 'equals', menuName );
			}
		}

		if ( typeof module.refreshCount === 'function' ) {
			module.refreshCount( false ).finally( () => module.showStep( 3 ) );
		} else {
			module.showStep( 3 );
		}

		return true;
	}

	function addExportFilter( module, field, condition, value ) {
		if ( typeof module.addFilterRow !== 'function' ) {
			return;
		}

		module.addFilterRow();
		const $row = $( '#rsl-ie-filters-list .rsl-ie-filter-row' ).last();
		$row.find( '.rsl-ie-filter-field' ).val( field ).trigger( 'change' );
		$row.find( '.rsl-ie-filter-condition' )
			.val( condition )
			.trigger( 'change' );
		$row.find( '.rsl-ie-filter-value' ).val( value ).trigger( 'change' );
	}

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

	function getSelectedIds() {
		if ( config.objectType === 'menu' && config.currentMenuId ) {
			return [ String( config.currentMenuId ) ];
		}

		if ( config.selectionMode === 'always' ) {
			return [];
		}

		const ids = [];
		$( 'tbody .check-column input[type="checkbox"]:checked' ).each(
			function () {
				const id = $( this ).val();
				if ( id ) {
					ids.push( String( id ) );
				}
			}
		);

		return ids
			.filter( ( id ) => /^\d+$/.test( id ) && parseInt( id, 10 ) > 0 )
			.filter( ( id, index, allIds ) => allIds.indexOf( id ) === index );
	}

	function syncRequiresSelection() {
		return ! [ 'comment', 'user', 'woo_attribute' ].includes(
			config.objectType
		);
	}

	function getToolbarTarget() {
		const selectors = String( config.toolbarSelector || '' ).split( ',' );
		for ( let index = 0; index < selectors.length; index++ ) {
			const $target = $( selectors[ index ].trim() ).first();
			if ( $target.length ) {
				return $target;
			}
		}

		const $bulkActions = $(
			'.tablenav.top .alignleft.actions.bulkactions'
		).first();
		if ( $bulkActions.length ) {
			return $bulkActions;
		}

		const $pageAction = $( '.wrap .page-title-action' ).first();
		if ( $pageAction.length ) {
			return $pageAction;
		}

		return $( '.wrap .wp-heading-inline' ).first();
	}

	function appendButtonToToolbar( $toolbar, $button ) {
		if (
			$toolbar.hasClass( 'page-title-action' ) ||
			$toolbar.hasClass( 'wp-heading-inline' )
		) {
			$button.insertAfter( $toolbar );
			return;
		}

		$toolbar.append( $button );
	}

	function ensureButtons() {
		const $toolbar = getToolbarTarget();
		if ( ! $toolbar.length ) {
			return;
		}

		if ( config.exportEnabled && ! $( `#${ exportButtonId }` ).length ) {
			appendButtonToToolbar(
				$toolbar,
				$( '<button>', {
					type: 'button',
					id: exportButtonId,
					class: 'button action',
					text: config.exportLabel || 'Export',
					disabled: config.selectionMode !== 'always',
					'aria-disabled':
						config.selectionMode === 'always' ? 'false' : 'true',
					title:
						config.i18n?.selectItems || 'Select one or more items.',
				} ).css( 'margin-left', '5px' )
			);
		}

		if ( config.syncEnabled && ! $( `#${ syncButtonId }` ).length ) {
			const needsSelection =
				config.selectionMode !== 'always' && syncRequiresSelection();
			appendButtonToToolbar(
				$toolbar,
				$( '<button>', {
					type: 'button',
					id: syncButtonId,
					class: 'button action',
					text: config.syncLabel || 'Sync',
					disabled: needsSelection,
					'aria-disabled': needsSelection ? 'true' : 'false',
					title:
						config.i18n?.selectItems || 'Select one or more items.',
				} ).css( 'margin-left', '5px' )
			);
		}
	}

	function updateButtonState() {
		ensureButtons();

		if ( config.selectionMode === 'always' ) {
			return;
		}

		const ids = getSelectedIds();
		const hasSelection = ids.length > 0;

		$( `#${ exportButtonId }` )
			.prop( 'disabled', ! hasSelection )
			.attr( 'aria-disabled', hasSelection ? 'false' : 'true' )
			.text(
				hasSelection
					? `${ config.exportLabel || 'Export' } (${ ids.length })`
					: config.exportLabel || 'Export'
			);

		const disableSync = syncRequiresSelection() && ! hasSelection;
		$( `#${ syncButtonId }` )
			.prop( 'disabled', disableSync )
			.attr( 'aria-disabled', disableSync ? 'true' : 'false' )
			.text(
				hasSelection
					? `${ config.syncLabel || 'Sync' } (${ ids.length })`
					: config.syncLabel || 'Sync'
			);

		updateModalPushButtonState();
	}

	function updateModalPushButtonState() {
		const $button = $( '#rsl-ie-pro-object-push-btn' );
		if ( ! $button.length || config.objectType !== 'comment' ) {
			return;
		}

		const hasSelection = getSelectedIds().length > 0;
		$button
			.prop( 'disabled', ! hasSelection )
			.attr( 'aria-disabled', hasSelection ? 'false' : 'true' );
	}

	function getExportUrl() {
		const ids = getSelectedIds();
		const exportUrl =
			config.exportUrl ||
			`${
				window.ajaxurl
					? window.ajaxurl.replace( 'admin-ajax.php', 'admin.php' )
					: 'admin.php'
			}?page=rsl-ie-export`;
		const separator = exportUrl.indexOf( '?' ) === -1 ? '?' : '&';
		const params = new URLSearchParams( {
			rsl_ie_prefill: 'admin_object',
			object_type: config.objectType || '',
			object_subtype: config.objectSubtype || '',
		} );

		if ( ids.length ) {
			params.set( config.idParam || 'object_ids', ids.join( ',' ) );
		}

		if ( config.objectType === 'menu' && config.currentMenuName ) {
			params.set( 'menu_name', config.currentMenuName );
		}

		return `${ exportUrl }${ separator }${ params.toString() }`;
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

	function getObjectText( key ) {
		const defaults = {
			comment: {
				syncItems: 'Sync Comments',
				push: 'Push selected comments',
				browse: 'Browse remote comments',
				remote: 'Remote Comments',
				searchLabel: 'Search comments',
				search: 'Search comments...',
				pull: 'Pull selected comments',
			},
			user: {
				syncItems: 'Sync Users',
				push: 'Push selected users',
				browse: 'Browse remote users',
				remote: 'Remote Users',
				searchLabel: 'Search users',
				search: 'Search users...',
				pull: 'Pull selected users',
			},
			menu: {
				syncItems: 'Sync Menus',
				push: 'Push current menu',
				browse: 'Browse remote menus',
				remote: 'Remote Menus',
				searchLabel: 'Search menus',
				search: 'Search menus...',
				pull: 'Pull selected menus',
			},
			woo_attribute: {
				syncItems: 'Sync WooCommerce Attributes',
				push: 'Push attributes',
				browse: 'Browse remote attributes',
				remote: 'Remote WooCommerce Attributes',
				searchLabel: 'Search attributes',
				search: 'Search attributes...',
				pull: 'Pull selected attributes',
			},
		};

		return (
			defaults[ config.objectType ]?.[ key ] ||
			defaults.comment[ key ] ||
			''
		);
	}

	function ensureSyncModal() {
		if ( $( `#${ modalId }` ).length ) {
			return;
		}

		$( 'body' ).append( `
			<div id="${ modalId }" class="rsl-ie-modal rsl-ie-mapping-modal" style="display:none;">
				<div class="rsl-ie-modal-content">
					<div class="rsl-ie-modal-header">
						<h2>${ escapeHtml(
							config.i18n?.syncItems ||
								getObjectText( 'syncItems' )
						) }</h2>
						<button type="button" class="rsl-ie-modal-close" aria-label="${ escapeHtml(
							config.i18n?.close || 'Close'
						) }">&times;</button>
					</div>
					<div class="rsl-ie-modal-body" style="text-align:center;">
						<div class="rsl-ie-form-group" style="max-width:360px;margin:0 auto 16px;">
							<label for="rsl-ie-pro-object-sync-site" style="display:block;margin-bottom:6px;">${ escapeHtml(
								config.i18n?.selectSiteLabel || 'Select Site'
							) }</label>
							<select id="rsl-ie-pro-object-sync-site" class="rsl-ie-form-control" style="display:block;width:100%;">${ getSitesOptions() }</select>
						</div>
						<div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap;">
							<button type="button" class="button button-primary" id="rsl-ie-pro-object-push-btn">${ escapeHtml(
								config.i18n?.pushComments ||
									getObjectText( 'push' )
							) }</button>
							<button type="button" class="button" id="rsl-ie-pro-object-browse-btn">${ escapeHtml(
								config.i18n?.browseComments ||
									getObjectText( 'browse' )
							) }</button>
						</div>
						<div id="rsl-ie-pro-object-sync-message" style="margin-top:12px;"></div>
					</div>
				</div>
			</div>
		` );
	}

	function showMessage( message, type = 'info' ) {
		$( '#rsl-ie-pro-object-sync-message' ).html(
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

	function getPushAction() {
		if ( config.objectType === 'menu' ) {
			return 'pro_sync_push_menus';
		}
		if ( config.objectType === 'user' ) {
			return 'pro_sync_push_users';
		}
		if ( config.objectType === 'woo_attribute' ) {
			return 'pro_sync_push_woo_attributes';
		}
		return 'content_sync_push_comments';
	}

	function getBrowseAction() {
		if ( config.objectType === 'menu' ) {
			return 'pro_sync_get_remote_menus';
		}
		if ( config.objectType === 'user' ) {
			return 'pro_sync_get_remote_users';
		}
		if ( config.objectType === 'woo_attribute' ) {
			return 'pro_sync_get_remote_woo_attributes';
		}
		return 'content_sync_get_remote_comments';
	}

	function getPullAction() {
		if ( config.objectType === 'menu' ) {
			return 'pro_sync_pull_menus';
		}
		if ( config.objectType === 'user' ) {
			return 'pro_sync_pull_users';
		}
		if ( config.objectType === 'woo_attribute' ) {
			return 'pro_sync_pull_woo_attributes';
		}
		return 'content_sync_pull_comments';
	}

	function getRemoteEmptyIcon() {
		if ( config.objectType === 'menu' ) {
			return 'dashicons-menu';
		}
		if ( config.objectType === 'user' ) {
			return 'dashicons-admin-users';
		}
		if ( config.objectType === 'woo_attribute' ) {
			return 'dashicons-tag';
		}
		return 'dashicons-admin-comments';
	}

	function getCommentPostTypeFilter() {
		return config.objectSubtype === 'review' ? 'product' : 'any';
	}

	function getPushPayload( siteId, ids ) {
		if ( config.objectType === 'menu' ) {
			return {
				site_id: siteId,
				menu_ids: ids,
				item_ids: getSelectedRemoteMenuItemIds(),
				remote_menu_id:
					$( '#rsl-ie-pro-menu-push-remote-menu' ).val() || '',
				object_mapping: JSON.stringify(
					getMenuObjectMapping( '#rsl-ie-pro-menu-push-mapping' )
				),
			};
		}
		if ( config.objectType === 'user' ) {
			return { site_id: siteId, user_ids: ids };
		}
		if ( config.objectType === 'woo_attribute' ) {
			return { site_id: siteId };
		}
		return {
			site_id: siteId,
			comment_ids: ids,
			comment_type: config.objectSubtype || '',
		};
	}

	function ensurePushModal() {
		if ( $( `#${ pushModalId }` ).length ) {
			return;
		}

		$( 'body' ).append( `
			<div id="${ pushModalId }" class="rsl-ie-modal rsl-ie-browse-library-modal" style="display:none;">
				<div class="rsl-ie-modal-content rsl-ie-modal-large">
					<div class="rsl-ie-modal-header">
						<h2>${ escapeHtml( config.i18n?.pushComments || 'Push current menu' ) }</h2>
						<button type="button" class="rsl-ie-modal-close" aria-label="${ escapeHtml(
							config.i18n?.close || 'Close'
						) }">&times;</button>
					</div>
					<div class="rsl-ie-modal-body" style="padding-top:18px;">
							<div class="rsl-ie-pro-menu-push-wrap" style="max-width:360px;margin:0 auto 16px;text-align:center;">
								<label for="rsl-ie-pro-menu-push-remote-menu" style="display:flex;margin-bottom:6px;align-items:center;justify-content:center;gap:8px;">${ escapeHtml(
									config.i18n?.remoteMenuLabel ||
										'Remote menu'
								) }<span class="spinner rsl-ie-pro-remote-menu-spinner" style="float:none;margin:0;display:none;"></span></label>
								<select id="rsl-ie-pro-menu-push-remote-menu" class="regular-text rsl-ie-pro-object-select2" style="display:block;width:100%;"></select>
							</div>
						<div class="rsl-ie-pro-comment-push-map-wrap" style="max-width:420px;margin:0 auto 16px;text-align:center;display:none;">
							<label for="rsl-ie-pro-comment-push-map-post" style="display:block;margin-bottom:6px;">${ escapeHtml(
								config.i18n?.mapToRemotePostLabel ||
									'Map to remote post'
							) }</label>
							<select id="rsl-ie-pro-comment-push-map-post" class="regular-text rsl-ie-pro-object-select2" style="display:block;width:100%;"></select>
						</div>
						<div id="rsl-ie-pro-menu-push-mapping" style="margin-top:16px;"></div>
						<div id="rsl-ie-pro-comment-push-message" style="margin-top:12px;"></div>
					</div>
					<div class="rsl-ie-browse-footer">
						<button type="button" class="button" id="rsl-ie-pro-object-push-cancel">${ escapeHtml(
							config.i18n?.cancel || 'Cancel'
						) }</button>
						<button type="button" class="button button-primary" id="rsl-ie-pro-object-push-confirm">${ escapeHtml(
							config.i18n?.pushComments || 'Push current menu'
						) }</button>
					</div>
				</div>
			</div>
		` );
	}

	function getBrowsePayload( siteId, search ) {
		if ( config.objectType === 'menu' ) {
			return { site_id: siteId };
		}
		if ( config.objectType === 'user' ) {
			return { site_id: siteId, search };
		}
		if ( config.objectType === 'woo_attribute' ) {
			return { site_id: siteId, search };
		}
		return {
			site_id: siteId,
			comment_type: config.objectSubtype || '',
			search,
			post_id: $( '#rsl-ie-pro-object-post-filter' ).val() || '',
			page: 1,
			per_page: 100,
		};
	}

	function getPullPayload( siteId, ids ) {
		if ( config.objectType === 'menu' ) {
			return {
				site_id: siteId,
				menu_ids: ids,
				local_menu_id: config.currentMenuId || '',
				item_ids: getSelectedRemoteMenuItemIds(),
				object_mapping: JSON.stringify(
					getMenuObjectMapping( '#rsl-ie-pro-menu-pull-mapping' )
				),
			};
		}
		if ( config.objectType === 'user' ) {
			return { site_id: siteId, user_ids: ids };
		}
		if ( config.objectType === 'woo_attribute' ) {
			return { site_id: siteId, attribute_ids: ids };
		}
		return {
			site_id: siteId,
			comment_ids: ids,
			comment_type: config.objectSubtype || '',
		};
	}

	function getRemoteMenuLoadingElement( $select ) {
		let $loading = $select.siblings( '.rsl-ie-pro-remote-menu-loading' );
		if ( $loading.length ) {
			return $loading;
		}

		$loading = $( `
			<div class="rsl-ie-pro-remote-menu-loading" style="display:none;margin-top:10px;padding:12px 14px;border:1px solid #dcdcde;background:#f6f7f7;border-radius:6px;color:#3c434a;align-items:center;justify-content:center;gap:10px;">
				<span class="spinner is-active" style="float:none;margin:0;"></span>
				<span>${ escapeHtml(
					config.i18n?.loadingRemoteMenus || 'Loading remote menus...'
				) }</span>
			</div>
		` );
		$select.after( $loading );
		return $loading;
	}

	function openSyncModal() {
		ensureSyncModal();
		resetSyncFlow( false );
		$( `#${ modalId }` ).show();
		updateModalPushButtonState();
		showMessage( '' );
	}

	function closeModals() {
		$( `#${ modalId }, #${ browseModalId }, #${ pushModalId }` ).hide();
		resetSyncFlow( true );
	}

	function resetSyncFlow( resetSite = true ) {
		if ( resetSite ) {
			$( '#rsl-ie-pro-object-sync-site' ).val( '' );
		}
		$( '.rsl-ie-pro-comment-push-map-wrap' ).hide();
		$( '.rsl-ie-pro-menu-push-wrap' ).show();
		$( '#rsl-ie-pro-comment-push-message' ).empty();
		$(
			'#rsl-ie-pro-menu-push-mapping, #rsl-ie-pro-menu-pull-mapping, #rsl-ie-pro-remote-objects'
		).empty();
		$( '#rsl-ie-pro-object-search' ).val( '' );
		$(
			'#rsl-ie-pro-menu-push-remote-menu, #rsl-ie-pro-menu-pull-remote-menu, #rsl-ie-pro-comment-map-post, #rsl-ie-pro-comment-push-map-post'
		).each( function () {
			const $select = $( this );
			if ( $select.data( 'select2' ) ) {
				$select.select2( 'destroy' );
			}
			$select.empty().val( null );
		} );
		showMessage( '' );
	}

	function openMenuPushModal() {
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		if ( ! siteId ) {
			showMessage(
				config.i18n?.selectSite || 'Please select a site.',
				'warning'
			);
			return;
		}

		ensurePushModal();
		$( `#${ modalId }` ).hide();
		$( `#${ pushModalId }` )
			.addClass( 'rsl-ie-browse-library-modal' )
			.removeClass( 'rsl-ie-pro-comment-push-modal' )
			.show();
		$( `#${ pushModalId } .rsl-ie-modal-content` ).removeClass(
			'rsl-ie-pro-comment-push-compact'
		);
		$( '.rsl-ie-pro-menu-push-wrap, #rsl-ie-pro-menu-push-mapping' ).show();
		$( '.rsl-ie-pro-comment-push-map-wrap' ).hide();
		$( '#rsl-ie-pro-comment-push-message' ).empty();
		initializeRemoteMenuSelect2(
			'#rsl-ie-pro-menu-push-remote-menu',
			`#${ pushModalId } .rsl-ie-modal-content`
		);
		renderMenuObjectMapping(
			'#rsl-ie-pro-menu-push-mapping',
			config.currentMenuItems || [],
			'remote'
		);
	}

	function openCommentPushModal() {
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		const ids = getSelectedIds();
		if ( ! siteId ) {
			showMessage(
				config.i18n?.selectSite || 'Please select a site.',
				'warning'
			);
			return;
		}
		if ( ! ids.length ) {
			showMessage(
				config.i18n?.selectItems || 'Select one or more items.',
				'warning'
			);
			updateModalPushButtonState();
			return;
		}

		ensurePushModal();
		$( `#${ modalId }` ).hide();
		$( `#${ pushModalId }` )
			.removeClass( 'rsl-ie-browse-library-modal' )
			.addClass( 'rsl-ie-pro-comment-push-modal' )
			.show();
		$( `#${ pushModalId } .rsl-ie-modal-content` ).addClass(
			'rsl-ie-pro-comment-push-compact'
		);
		$( '.rsl-ie-pro-menu-push-wrap, #rsl-ie-pro-menu-push-mapping' ).hide();
		$( '.rsl-ie-pro-comment-push-map-wrap' ).show();
		$( '#rsl-ie-pro-comment-push-message' ).empty();
		$( '#rsl-ie-pro-object-push-confirm' )
			.prop( 'disabled', false )
			.text( config.i18n?.pushComments || getObjectText( 'push' ) );
		initializePushMapToRemotePostSelect2();
	}

	function pushComments() {
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		const ids =
			config.objectType === 'menu'
				? [ String( config.currentMenuId || '' ) ].filter( Boolean )
				: config.objectType === 'woo_attribute'
				? [ 'all' ]
				: getSelectedIds();

		if ( ! siteId ) {
			showMessage(
				config.i18n?.selectSite || 'Please select a site.',
				'warning'
			);
			return;
		}

		if ( ! ids.length ) {
			showMessage(
				config.i18n?.selectItems || 'Select one or more items.',
				'warning'
			);
			updateModalPushButtonState();
			return;
		}

		if (
			config.objectType === 'comment' &&
			! $( `#${ pushModalId }` ).is( ':visible' )
		) {
			openCommentPushModal();
			return;
		}

		const remotePostId = String(
			$( '#rsl-ie-pro-comment-push-map-post' ).val() || ''
		);
		if ( config.objectType === 'comment' && ! remotePostId ) {
			$( '#rsl-ie-pro-comment-push-message' ).html(
				`<div class="notice notice-warning inline"><p>${ escapeHtml(
					config.i18n?.mapCommentPostsRequired ||
						'Please select a destination post before pushing comments.'
				) }</p></div>`
			);
			return;
		}
		if (
			config.objectType === 'menu' &&
			! $( '#rsl-ie-pro-menu-push-remote-menu' ).val()
		) {
			showMessage(
				config.i18n?.selectRemoteMenu || 'Select a remote menu.',
				'warning'
			);
			return;
		}

		const $button =
			config.objectType === 'menu'
				? $( '#rsl-ie-pro-object-push-confirm' )
				: config.objectType === 'comment'
				? $( '#rsl-ie-pro-object-push-confirm' )
				: $( '#rsl-ie-pro-object-push-btn' );
		$button
			.prop( 'disabled', true )
			.text( config.i18n?.loading || 'Loading...' );
		const payload = getPushPayload( siteId, ids );
		if ( config.objectType === 'comment' ) {
			payload.target_post_id = remotePostId;
		}

		request( getPushAction(), payload )
			.done( ( data ) => {
				if (
					config.objectType === 'menu' ||
					config.objectType === 'comment'
				) {
					$( `#${ pushModalId }` ).hide();
					$( `#${ modalId }` ).show();
					resetSyncFlow( false );
				}
				showMessage(
					data.message ||
						config.i18n?.syncComplete ||
						'Sync completed successfully.',
					'success'
				);
			} )
			.fail( ( message ) => {
				showMessage(
					message || config.i18n?.syncFailed || 'Sync failed.',
					'error'
				);
			} )
			.always( () => {
				$button
					.prop( 'disabled', false )
					.text(
						config.i18n?.pushComments || getObjectText( 'push' )
					);
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
						<h2>${ escapeHtml(
							config.i18n?.remoteComments ||
								getObjectText( 'remote' )
						) }</h2>
						<button type="button" class="rsl-ie-modal-close" aria-label="${ escapeHtml(
							config.i18n?.close || 'Close'
						) }">&times;</button>
					</div>
						<div class="rsl-ie-browse-search-bar" style="display:grid;grid-template-columns:minmax(180px,1fr) minmax(220px,1fr) minmax(220px,1fr) auto;gap:10px;align-items:end;">
							<div>
								<label for="rsl-ie-pro-object-search" style="display:block;margin-bottom:6px;">${ escapeHtml(
									config.i18n?.searchCommentsLabel ||
										getObjectText( 'searchLabel' )
								) }</label>
								<input type="search" id="rsl-ie-pro-object-search" class="regular-text" style="width:100%;min-width:0;" placeholder="${ escapeHtml(
									config.i18n?.searchComments ||
										getObjectText( 'search' )
								) }">
							</div>
							<div>
								<label for="rsl-ie-pro-object-post-filter" style="display:block;margin-bottom:6px;">${ escapeHtml(
									config.i18n?.searchPostsLabel ||
										'Search posts'
								) }</label>
								<select id="rsl-ie-pro-object-post-filter" class="regular-text rsl-ie-pro-object-select2" style="width:100%;min-width:0;">
									<option value="">${ escapeHtml(
										config.i18n?.allPosts || 'All posts'
									) }</option>
								</select>
							</div>
							<div>
								<label for="rsl-ie-pro-comment-map-post" style="display:block;margin-bottom:6px;">${ escapeHtml(
									config.i18n?.mapToPostLabel || 'Map to post'
								) }</label>
								<select id="rsl-ie-pro-comment-map-post" class="regular-text rsl-ie-pro-object-select2" style="width:100%;min-width:0;"></select>
							</div>
							<button type="button" class="button" id="rsl-ie-pro-comment-automap-post">${ escapeHtml(
								config.i18n?.automap || 'Automap'
							) }</button>
						</div>
					<div id="rsl-ie-pro-menu-pull-controls" class="rsl-ie-browse-search-bar" style="display:none;">
						<div style="max-width:360px;margin:0 auto;text-align:center;">
								<label for="rsl-ie-pro-menu-pull-remote-menu" style="display:flex;margin-bottom:6px;align-items:center;justify-content:center;gap:8px;">${ escapeHtml(
									config.i18n?.remoteMenuLabel ||
										'Remote menu'
								) }<span class="spinner rsl-ie-pro-remote-menu-spinner" style="float:none;margin:0;display:none;"></span></label>
								<select id="rsl-ie-pro-menu-pull-remote-menu" class="regular-text rsl-ie-pro-object-select2" style="width:100%;"></select>
						</div>
					</div>
					<div class="rsl-ie-browse-search-bar" style="padding-top:12px;display:flex;gap:8px;justify-content:space-between;align-items:center;flex-wrap:wrap;">
						<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
							<button type="button" class="button" id="rsl-ie-pro-object-select-all">${ escapeHtml(
								config.i18n?.selectAll || 'Select all'
							) }</button>
							<button type="button" class="button" id="rsl-ie-pro-object-deselect-all">${ escapeHtml(
								config.i18n?.deselectAll || 'Deselect all'
							) }</button>
						</div>
						<span id="rsl-ie-pro-object-selected-count" style="opacity:.72;line-height:30px;display:inline-flex;align-items:center;margin-left:auto;"></span>
					</div>
					<div class="rsl-ie-browse-body">
						<div class="rsl-ie-browse-main">
							<div id="rsl-ie-pro-remote-objects" class="rsl-ie-posts-tree"></div>
							<div id="rsl-ie-pro-menu-pull-mapping" style="margin-top:16px;"></div>
						</div>
					</div>
					<div class="rsl-ie-browse-footer">
						<button type="button" class="button" id="rsl-ie-pro-object-browse-cancel">${ escapeHtml(
							config.i18n?.cancel || 'Cancel'
						) }</button>
						<button type="button" class="button button-primary" id="rsl-ie-pro-object-pull-selected-btn">${ escapeHtml(
							config.i18n?.pullSelected || getObjectText( 'pull' )
						) }</button>
					</div>
				</div>
			</div>
		` );

		if ( config.objectType !== 'comment' ) {
			$(
				'#rsl-ie-pro-object-post-filter, #rsl-ie-pro-comment-map-post, #rsl-ie-pro-comment-automap-post'
			)
				.closest( 'div, button' )
				.hide();
		}
		if ( config.objectType === 'menu' ) {
			$( '#rsl-ie-pro-object-search' )
				.closest( '.rsl-ie-browse-search-bar' )
				.hide();
			$( '#rsl-ie-pro-menu-pull-controls' ).show();
		}
	}

	function updateRemoteObjectSelectionCount() {
		const selected = $(
			'.rsl-ie-pro-remote-object-checkbox:checked'
		).length;
		const loaded = $( '.rsl-ie-pro-remote-object-checkbox' ).length;
		$( '#rsl-ie-pro-object-selected-count' ).text(
			loaded ? `Loaded: ${ loaded } · Selected: ${ selected }` : ''
		);
	}

	function loadRemotePostsForCommentFilter() {
		if ( config.objectType !== 'comment' ) {
			return;
		}

		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		const $select = $( '#rsl-ie-pro-object-post-filter' );

		if ( ! siteId ) {
			return;
		}

		if ( typeof $select.select2 === 'function' ) {
			initializePostFilterSelect2();
			return;
		}

		request( 'content_sync_get_remote_posts', {
			site_id: siteId,
			post_type: getCommentPostTypeFilter(),
			comment_type: config.objectSubtype || '',
			commentable_only: 1,
			status: 'any',
			page: 1,
			per_page: 50,
		} ).done( ( data ) => {
			const current = String( $select.val() || '' );
			const options = [
				`<option value="">${ escapeHtml(
					config.i18n?.allPosts || 'All posts'
				) }</option>`,
			];

			( data.posts || [] ).forEach( ( post ) => {
				options.push(
					`<option value="${ escapeHtml( post.ID ) }">${ escapeHtml(
						post.title || post.post_title || `#${ post.ID }`
					) } — ID: ${ escapeHtml( post.ID ) }</option>`
				);
			} );

			$select.html( options.join( '' ) );
			if (
				current &&
				$select.find( `option[value="${ current }"]` ).length
			) {
				$select.val( current );
			}
		} );
	}

	function initializePostFilterSelect2() {
		const $select = $( '#rsl-ie-pro-object-post-filter' );
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();

		if ( ! siteId || typeof $select.select2 !== 'function' ) {
			return;
		}

		if ( $select.data( 'select2' ) ) {
			$select.select2( 'destroy' );
		}

		$select.select2( {
			placeholder: config.i18n?.allPosts || 'All posts',
			allowClear: true,
			width: '100%',
			dropdownParent: $( `#${ browseModalId } .rsl-ie-modal-content` ),
			ajax: {
				url: config.ajaxurl || window.ajaxurl,
				dataType: 'json',
				delay: 300,
				data: ( params ) => ( {
					action: normalizeAjaxAction(
						'content_sync_get_remote_posts'
					),
					nonce: getNonce( 'content_sync_get_remote_posts' ),
					site_id: siteId,
					post_type: getCommentPostTypeFilter(),
					comment_type: config.objectSubtype || '',
					status: 'any',
					commentable_only: 1,
					search: params.term || '',
					page: params.page || 1,
					per_page: 20,
				} ),
				processResults: ( response, params ) => {
					params.page = params.page || 1;

					if ( ! response || ! response.success || ! response.data ) {
						return { results: [], pagination: { more: false } };
					}

					const posts = response.data.posts || [];
					return {
						results: [
							...( params.page === 1
								? [
										{
											id: '',
											text:
												config.i18n?.allPosts ||
												'All posts',
										},
								  ]
								: [] ),
							...posts.map( ( post ) => ( {
								id: post.ID,
								text: `${
									post.title ||
									post.post_title ||
									`#${ post.ID }`
								} — ${ post.post_type } — ID: ${ post.ID }`,
								title: post.title || post.post_title || '',
								post_type: post.post_type || '',
							} ) ),
						],
						pagination: {
							more:
								params.page <
								parseInt( response.data.pages || 1, 10 ),
						},
					};
				},
			},
		} );
		fixSelect2FieldHeight( $select );
	}

	function initializePushMapToRemotePostSelect2() {
		const $select = $( '#rsl-ie-pro-comment-push-map-post' );
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();

		if (
			config.objectType !== 'comment' ||
			! siteId ||
			typeof $select.select2 !== 'function'
		) {
			return;
		}

		if ( $select.data( 'select2' ) ) {
			$select.select2( 'destroy' );
		}

		$select.select2( {
			placeholder: config.i18n?.selectPost || 'Select destination post',
			allowClear: true,
			width: '100%',
			dropdownParent: $( 'body' ),
			ajax: {
				url: config.ajaxurl || window.ajaxurl,
				dataType: 'json',
				delay: 300,
				data: ( params ) => ( {
					action: normalizeAjaxAction(
						'content_sync_get_remote_posts'
					),
					nonce: getNonce( 'content_sync_get_remote_posts' ),
					site_id: siteId,
					post_type: getCommentPostTypeFilter(),
					comment_type: config.objectSubtype || '',
					status: 'any',
					commentable_only: 1,
					search: params.term || '',
					page: params.page || 1,
					per_page: 20,
				} ),
				processResults: ( response, params ) => {
					params.page = params.page || 1;

					if ( ! response || ! response.success || ! response.data ) {
						return { results: [], pagination: { more: false } };
					}

					const posts = response.data.posts || [];
					return {
						results: posts.map( ( post ) => ( {
							id: post.ID,
							text: `${
								post.title || post.post_title || `#${ post.ID }`
							} — ${ post.post_type } — ID: ${ post.ID }`,
						} ) ),
						pagination: {
							more:
								params.page <
								parseInt( response.data.pages || 1, 10 ),
						},
					};
				},
			},
		} );
		fixSelect2FieldHeight( $select );
	}

	function fixSelect2FieldHeight( $select ) {
		window.setTimeout( () => {
			const $container = $select.next( '.select2-container' );
			const $selection = $container.find( '.select2-selection--single' );
			const $rendered = $container.find( '.select2-selection__rendered' );
			const $arrow = $container.find( '.select2-selection__arrow' );

			$container.css( {
				width: '100%',
				'min-width': 0,
			} );
			$selection.css( {
				height: '40px',
				'min-height': '40px',
				display: 'flex',
				'align-items': 'center',
				'background-color': '#fff',
				border: '1px solid #c3c4c7',
				'border-radius': '4px',
				'box-shadow': 'none',
			} );
			$rendered.css( {
				'line-height': '38px',
				color: '#2c3338',
				padding: '0 28px 0 12px',
			} );
			$arrow.css( {
				height: '38px',
				right: '6px',
			} );
		}, 0 );
	}

	function initializeRemoteMenuSelect2( selector, dropdownParentSelector ) {
		const $select = $( selector );
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();

		if ( config.objectType !== 'menu' || ! siteId ) {
			return;
		}

		if ( $select.data( 'select2' ) ) {
			$select.select2( 'destroy' );
		}

		const $loading = getRemoteMenuLoadingElement( $select );
		$select
			.closest( 'div' )
			.find( '.rsl-ie-pro-remote-menu-spinner' )
			.addClass( 'is-active' )
			.show();
		$loading.css( 'display', 'flex' );

		$select
			.html(
				`<option value="">${ escapeHtml(
					config.i18n?.loading || 'Loading...'
				) }</option>`
			)
			.prop( 'disabled', true );

		request( 'pro_sync_get_remote_menus', { site_id: siteId } )
			.done( ( data ) => {
				const menus = data.menus || [];
				const options = [
					`<option value="">${ escapeHtml(
						config.i18n?.selectRemoteMenu || 'Select a remote menu.'
					) }</option>`,
				];

				menus.forEach( ( menu ) => {
					options.push(
						`<option value="${ escapeHtml(
							menu.term_id
						) }">${ escapeHtml(
							`${ menu.name || `#${ menu.term_id }` }${
								menu.slug ? ` — ${ menu.slug }` : ''
							}`
						) }</option>`
					);
				} );

				if ( ! menus.length ) {
					options.push(
						`<option value="" disabled>${ escapeHtml(
							config.i18n?.noItemsFound || 'No items found.'
						) }</option>`
					);
				}

				$select.html( options.join( '' ) ).prop( 'disabled', false );
				$loading.hide();
				$select
					.closest( 'div' )
					.find( '.rsl-ie-pro-remote-menu-spinner' )
					.removeClass( 'is-active' )
					.hide();
				if ( typeof $select.select2 === 'function' ) {
					if ( $select.data( 'select2' ) ) {
						$select.select2( 'destroy' );
					}
					$select.select2( {
						placeholder:
							config.i18n?.selectRemoteMenu ||
							'Select a remote menu.',
						allowClear: true,
						width: '100%',
						dropdownParent: $( dropdownParentSelector ),
					} );
					fixSelect2FieldHeight( $select );
				}
			} )
			.fail( ( message ) => {
				if ( $select.data( 'select2' ) ) {
					$select.select2( 'destroy' );
				}
				$select
					.html(
						`<option value="">${ escapeHtml(
							message || config.i18n?.syncFailed || 'Sync failed.'
						) }</option>`
					)
					.prop( 'disabled', true );
				$loading.hide();
				$select
					.closest( 'div' )
					.find( '.rsl-ie-pro-remote-menu-spinner' )
					.removeClass( 'is-active' )
					.hide();
			} );

		if ( typeof $select.select2 !== 'function' ) {
			return;
		}

		$select.select2( {
			placeholder:
				config.i18n?.selectRemoteMenu || 'Select a remote menu.',
			allowClear: true,
			width: '100%',
			dropdownParent: $( dropdownParentSelector ),
		} );
		fixSelect2FieldHeight( $select );
	}

	function renderMenuObjectMapping( containerSelector, items, direction ) {
		const $container = $( containerSelector );
		const hasObjectItems = ( items || [] ).some( ( item ) =>
			isMappableMenuItem( item )
		);
		const selectable = direction === 'local';

		if ( ! hasObjectItems && ! selectable ) {
			$container.empty();
			return;
		}

		const childrenByParent = {};
		( items || [] ).forEach( ( item ) => {
			const parent = String( item.menu_item_parent || 0 );
			if ( ! childrenByParent[ parent ] ) {
				childrenByParent[ parent ] = [];
			}
			childrenByParent[ parent ].push( item );
		} );

		const renderRows = ( parentId, depth = 0 ) =>
			( childrenByParent[ String( parentId ) ] || [] )
				.map( ( item ) => {
					const needsMapping = isMappableMenuItem( item );
					return `
						<div class="rsl-ie-menu-map-row" style="display:grid;grid-template-columns:minmax(260px,1.1fr) minmax(260px,1fr) auto;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f1;">
							<div style="padding-left:${ depth * 22 }px;">
								<label style="display:flex;gap:8px;align-items:flex-start;margin:0;">
									${
										selectable
											? `<input type="checkbox" class="rsl-ie-post-checkbox rsl-ie-pro-remote-menu-item-checkbox rsl-ie-pro-remote-object-checkbox" value="${ escapeHtml(
													item.ID
											  ) }" checked style="margin-top:2px;">`
											: ''
									}
									<span>
										<span style="display:block;font-weight:600;color:#1d2327;">${ escapeHtml(
											item.title || `#${ item.ID }`
										) }</span>
										<span style="display:block;color:#646970;font-size:12px;margin-top:3px;">${ escapeHtml(
											`${ item.type || 'custom' } · ${
												item.object || 'custom'
											}${
												item.object_id
													? ` · source ID: ${ item.object_id }`
													: ''
											}`
										) }</span>
									</span>
								</label>
							</div>
							<div>
								${
									needsMapping
										? `<select class="regular-text rsl-ie-pro-menu-object-map" data-source-object-id="${ escapeHtml(
												item.object_id
										  ) }" data-object-kind="${ escapeHtml(
												item.type || ''
										  ) }" data-post-type="${ escapeHtml(
												item.object ||
													item.object_type ||
													'any'
										  ) }" data-taxonomy="${ escapeHtml(
												item.object || ''
										  ) }" data-title="${ escapeHtml(
												item.object_title ||
													item.title ||
													''
										  ) }" data-slug="${ escapeHtml(
												item.term_slug ||
													item.object_slug ||
													item.object_name ||
													''
										  ) }" data-direction="${ escapeHtml(
												direction
										  ) }" style="width:100%;"></select>`
										: `<span style="color:#787c82;font-size:12px;">${ escapeHtml(
												'No mapping needed'
										  ) }</span>`
								}
							</div>
							<div>
								${
									needsMapping
										? `<button type="button" class="button rsl-ie-pro-menu-automap-one">${ escapeHtml(
												config.i18n?.automap ||
													'Automap'
										  ) }</button>`
										: ''
								}
							</div>
						</div>
						${ renderRows( item.ID, depth + 1 ) }
					`;
				} )
				.join( '' );

		$container.html( `
			<div class="rsl-ie-menu-object-mapping" style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px;text-align:left;">
				<div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px;">
					<div>
						<h3 style="margin:0 0 4px;font-size:14px;">${ escapeHtml(
							selectable
								? config.i18n?.pullSelected ||
										'Pull selected menu items'
								: config.i18n?.mapMenuItems ||
										'Map menu item objects'
						) }</h3>
						<p class="description" style="margin:0;">${ escapeHtml(
							config.i18n?.mapMenuItemsHelp ||
								'Map post/page menu items to matching objects on the destination site.'
						) }</p>
					</div>
					<button type="button" class="button rsl-ie-pro-menu-automap-all">${ escapeHtml(
						config.i18n?.automap || 'Automap'
					) }</button>
				</div>
				<div class="rsl-ie-menu-map-header" style="display:grid;grid-template-columns:minmax(260px,1.1fr) minmax(260px,1fr) auto;gap:10px;padding:8px 0;border-bottom:1px solid #dcdcde;color:#646970;font-size:12px;font-weight:600;text-transform:uppercase;">
					<div>${ escapeHtml( selectable ? 'Select menu item' : 'Menu item' ) }</div>
					<div>${ escapeHtml( 'Map to' ) }</div>
					<div></div>
				</div>
				${ renderRows( 0 ) }
			</div>
		` );

		$container.find( '.rsl-ie-pro-menu-object-map' ).each( function () {
			initializeMenuObjectMapSelect2( $( this ) );
		} );
	}

	function isMappableMenuItem( item ) {
		return (
			!! item?.object_id &&
			[ 'post_type', 'taxonomy' ].includes( String( item.type || '' ) )
		);
	}

	function initializeMenuObjectMapSelect2( $select ) {
		if ( typeof $select.select2 !== 'function' ) {
			return;
		}

		const direction = String( $select.data( 'direction' ) || 'local' );
		const remote = direction === 'remote';
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		const objectKind = String(
			$select.data( 'object-kind' ) || 'post_type'
		);
		const postType = String( $select.data( 'post-type' ) || 'any' );
		const taxonomy = String( $select.data( 'taxonomy' ) || '' );
		const title = String( $select.data( 'title' ) || '' );

		$select.select2( {
			placeholder: getMenuMappingPlaceholder(
				objectKind,
				postType,
				taxonomy
			),
			allowClear: true,
			width: '100%',
			dropdownParent: $select.closest( '.rsl-ie-modal-content' ),
			ajax: {
				url: config.ajaxurl || window.ajaxurl,
				dataType: 'json',
				delay: 300,
				data: ( params ) => {
					const action = getMenuMappingSearchAction(
						objectKind,
						remote
					);
					const payload = {
						action: normalizeAjaxAction( action ),
						nonce: getNonce( action ),
						search: params.term || '',
						page: params.page || 1,
						per_page: 20,
					};
					if ( objectKind === 'taxonomy' ) {
						payload.taxonomy = taxonomy;
					} else {
						payload.post_type = postType;
						payload.status = 'any';
					}
					if ( remote ) {
						payload.site_id = siteId;
					}
					return payload;
				},
				processResults: ( response, params ) => {
					params.page = params.page || 1;
					if ( ! response || ! response.success || ! response.data ) {
						return { results: [], pagination: { more: false } };
					}

					return {
						results: getMenuMappingResults(
							response.data,
							objectKind,
							postType,
							taxonomy
						),
						pagination: {
							more:
								params.page <
								parseInt( response.data.pages || 1, 10 ),
						},
					};
				},
			},
		} );
		fixSelect2FieldHeight( $select );
	}

	function getMenuMappingSearchAction( objectKind, remote ) {
		if ( objectKind === 'taxonomy' ) {
			return remote
				? 'content_sync_get_remote_terms'
				: 'content_sync_search_local_terms';
		}
		return remote
			? 'content_sync_get_remote_posts'
			: 'content_sync_search_local_posts';
	}

	function getMenuMappingPlaceholder( objectKind, postType, taxonomy ) {
		if ( objectKind === 'taxonomy' ) {
			return `Select ${ taxonomy || 'taxonomy' } term`;
		}

		if ( postType === 'page' ) {
			return 'Select page';
		}

		if ( postType === 'post' ) {
			return 'Select post';
		}

		return `Select ${ postType || 'post type' } item`;
	}

	function getMenuMappingResults( data, objectKind, postType, taxonomy ) {
		if ( objectKind === 'taxonomy' ) {
			return ( data.terms || [] ).map( ( term ) => ( {
				id: term.term_id,
				text: `${ term.name || `#${ term.term_id }` } — ${
					term.taxonomy || taxonomy
				} — ID: ${ term.term_id }`,
				title: term.name || '',
				slug: term.slug || '',
			} ) );
		}

		return ( data.posts || [] ).map( ( post ) => ( {
			id: post.ID,
			text: `${ post.title || post.post_title || `#${ post.ID }` } — ${
				post.post_type || postType
			} — ID: ${ post.ID }`,
			title: post.title || post.post_title || '',
		} ) );
	}

	function automapMenuObjectSelect( $select ) {
		const direction = String( $select.data( 'direction' ) || 'local' );
		const remote = direction === 'remote';
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		const objectKind = String(
			$select.data( 'object-kind' ) || 'post_type'
		);
		const postType = String( $select.data( 'post-type' ) || 'any' );
		const taxonomy = String( $select.data( 'taxonomy' ) || '' );
		const title = String( $select.data( 'title' ) || '' );
		const slug = String( $select.data( 'slug' ) || '' );

		if ( ! title && ! slug ) {
			return $.Deferred().resolve().promise();
		}

		const action = getMenuMappingSearchAction( objectKind, remote );
		const payload = {
			search: title || slug,
			page: 1,
			per_page: 10,
		};
		if ( objectKind === 'taxonomy' ) {
			payload.taxonomy = taxonomy;
		} else {
			payload.post_type = postType;
			payload.status = 'any';
		}
		if ( remote ) {
			payload.site_id = siteId;
		}

		return request( action, payload ).done( ( data ) => {
			const needle = ( title || slug ).trim().toLowerCase();
			const rows =
				objectKind === 'taxonomy' ? data.terms || [] : data.posts || [];
			const match =
				rows.find(
					( row ) =>
						String( row.post_type || row.taxonomy || '' ) ===
							( objectKind === 'taxonomy'
								? taxonomy
								: postType ) &&
						String( row.title || row.post_title || row.name || '' )
							.trim()
							.toLowerCase() === needle
				) ||
				rows.find(
					( row ) =>
						String( row.slug || row.post_name || '' )
							.trim()
							.toLowerCase() === slug.trim().toLowerCase()
				) ||
				rows.find(
					( row ) =>
						String( row.title || row.post_title || row.name || '' )
							.trim()
							.toLowerCase() === needle
				) ||
				rows[ 0 ];

			if ( ! match ) {
				return;
			}

			const id = objectKind === 'taxonomy' ? match.term_id : match.ID;
			const text =
				objectKind === 'taxonomy'
					? `${ match.name || `#${ id }` } — ${
							match.taxonomy || taxonomy
					  } — ID: ${ id }`
					: `${ match.title || match.post_title || `#${ id }` } — ${
							match.post_type || postType
					  } — ID: ${ id }`;
			if ( ! $select.find( `option[value="${ id }"]` ).length ) {
				$select.append( new Option( text, id, true, true ) );
			}
			$select.val( String( id ) ).trigger( 'change' );
		} );
	}

	function getMenuObjectMapping( containerSelector ) {
		const mapping = {};
		$( `${ containerSelector } .rsl-ie-pro-menu-object-map` ).each(
			function () {
				const sourceId = String(
					$( this ).data( 'source-object-id' ) || ''
				);
				const objectKind = String(
					$( this ).data( 'object-kind' ) || ''
				);
				const objectName = String(
					$( this ).data(
						objectKind === 'taxonomy' ? 'taxonomy' : 'post-type'
					) || ''
				);
				const targetId = String( $( this ).val() || '' );
				if ( objectKind && objectName && sourceId && targetId ) {
					mapping[ `${ objectKind }:${ objectName }:${ sourceId }` ] =
						targetId;
				}
			}
		);
		return mapping;
	}

	function getSelectedRemoteMenuItemIds() {
		const ids = [];
		$( '.rsl-ie-pro-remote-menu-item-checkbox:checked' ).each( function () {
			ids.push( $( this ).val() );
		} );
		return ids;
	}

	function initializeMapToPostSelect2() {
		const $select = $( '#rsl-ie-pro-comment-map-post' );
		if ( typeof $select.select2 !== 'function' ) {
			return;
		}

		if ( $select.data( 'select2' ) ) {
			$select.select2( 'destroy' );
		}

		$select.select2( {
			placeholder: config.i18n?.selectPost || 'Select destination post',
			allowClear: true,
			width: '100%',
			dropdownParent: $( `#${ browseModalId } .rsl-ie-modal-content` ),
			ajax: {
				url: config.ajaxurl || window.ajaxurl,
				dataType: 'json',
				delay: 300,
				data: ( params ) => ( {
					action: normalizeAjaxAction(
						'content_sync_search_local_posts'
					),
					nonce: getNonce( 'content_sync_search_local_posts' ),
					post_type: getCommentPostTypeFilter(),
					comment_type: config.objectSubtype || '',
					commentable_only: 1,
					search: params.term || '',
					page: params.page || 1,
					per_page: 20,
				} ),
				processResults: ( response, params ) => {
					params.page = params.page || 1;
					if ( ! response || ! response.success || ! response.data ) {
						return { results: [], pagination: { more: false } };
					}

					return {
						results: ( response.data.posts || [] ).map(
							( post ) => ( {
								id: post.ID,
								text: `${
									post.title ||
									post.post_title ||
									`#${ post.ID }`
								} — ${ post.post_type } — ID: ${ post.ID }`,
							} )
						),
						pagination: {
							more:
								params.page <
								parseInt( response.data.pages || 1, 10 ),
						},
					};
				},
			},
		} );
		fixSelect2FieldHeight( $select );
	}

	function getAutomapRemotePost() {
		const selectedRemotePostId = String(
			$( '#rsl-ie-pro-object-post-filter' ).val() || ''
		);
		if ( selectedRemotePostId ) {
			const selectedData =
				$( '#rsl-ie-pro-object-post-filter' ).select2?.(
					'data'
				)?.[ 0 ] || {};
			const selectedText =
				selectedData.title ||
				$( '#rsl-ie-pro-object-post-filter option:selected' ).text();
			return {
				ID: selectedRemotePostId,
				title: String( selectedText ).replace(
					/\s+—\s+[^—]+?\s+—\s+ID:\s+\d+$/,
					''
				),
				post_type: selectedData.post_type || 'any',
			};
		}

		const firstCommentId = String(
			$( '.rsl-ie-pro-remote-object-checkbox:checked' ).first().val() ||
				''
		);
		const item = firstCommentId
			? remoteObjectsById[ firstCommentId ]
			: null;
		return item?.post || null;
	}

	function automapCommentPost() {
		const remotePost = getAutomapRemotePost();
		if ( ! remotePost?.title ) {
			alert(
				config.i18n?.selectRemoteItem ||
					'Please select a remote comment or remote post first.'
			);
			return;
		}

		const $button = $( '#rsl-ie-pro-comment-automap-post' );
		$button
			.prop( 'disabled', true )
			.text( config.i18n?.loading || 'Loading...' );

		request( 'content_sync_search_local_posts', {
			post_type: remotePost.post_type || 'any',
			comment_type: config.objectSubtype || '',
			commentable_only: 1,
			search: remotePost.title,
			page: 1,
			per_page: 10,
		} )
			.done( ( data ) => {
				const remoteTitle = String( remotePost.title || '' )
					.trim()
					.toLowerCase();
				const posts = data.posts || [];
				const match =
					posts.find(
						( post ) =>
							String( post.post_type || '' ) ===
								String( remotePost.post_type || '' ) &&
							String( post.title || post.post_title || '' )
								.trim()
								.toLowerCase() === remoteTitle
					) ||
					posts.find(
						( post ) =>
							String( post.title || post.post_title || '' )
								.trim()
								.toLowerCase() === remoteTitle
					) ||
					posts[ 0 ];

				if ( ! match ) {
					alert( config.i18n?.noItemsFound || 'No items found.' );
					return;
				}

				const $map = $( '#rsl-ie-pro-comment-map-post' );
				const text = `${
					match.title || match.post_title || `#${ match.ID }`
				} — ${ match.post_type } — ID: ${ match.ID }`;
				if ( ! $map.find( `option[value="${ match.ID }"]` ).length ) {
					$map.append( new Option( text, match.ID, true, true ) );
				}
				$map.val( String( match.ID ) ).trigger( 'change' );
			} )
			.always( () => {
				$button
					.prop( 'disabled', false )
					.text( config.i18n?.automap || 'Automap' );
			} );
	}

	function renderRemoteObjectItem( item ) {
		let id = '';
		let title = '';
		let meta = '';
		let content = '';

		if ( config.objectType === 'menu' ) {
			id = item.term_id;
			title = item.name || '';
			meta = `ID: ${ item.term_id || '' } · ${ item.slug || '' }`;
		} else if ( config.objectType === 'user' ) {
			id = item.ID;
			title = item.display_name || item.user_login || '';
			meta = `ID: ${ item.ID || '' } · ${ item.user_email || '' }`;
		} else if ( config.objectType === 'woo_attribute' ) {
			id = item.attribute_id;
			title = item.attribute_label || item.attribute_name || '';
			meta = `ID: ${ item.attribute_id || '' } · ${
				item.attribute_name || ''
			} · ${ item.term_count || 0 } terms`;
		} else {
			id = item.comment_ID;
			title = item.comment_author || 'Anonymous';
			meta = `ID: ${ item.comment_ID || '' } · ${
				item.post?.title || ''
			}`;
			content = String( item.comment_content || '' ).slice( 0, 180 );
		}

		return `
			<div class="rsl-ie-post-wrapper">
				<label class="rsl-ie-post-item" style="cursor:pointer;">
					<input type="checkbox" class="rsl-ie-post-checkbox rsl-ie-pro-remote-object-checkbox" value="${ escapeHtml(
						id
					) }">
					<div class="rsl-ie-post-info">
						<div class="rsl-ie-post-title">${ escapeHtml( title ) }</div>
						<div class="rsl-ie-post-meta">${ escapeHtml( meta ) }</div>
						${
							content
								? `<div style="margin-top:6px;">${ escapeHtml(
										content
								  ) }</div>`
								: ''
						}
					</div>
				</label>
			</div>
		`;
	}

	function initializeLocalPostMappingSelect( $select, remotePost ) {
		if ( typeof $select.select2 !== 'function' ) {
			return;
		}

		$select.select2( {
			placeholder: config.i18n?.selectPost || 'Select destination post',
			allowClear: true,
			width: '100%',
			dropdownParent: $( `#${ browseModalId } .rsl-ie-modal-content` ),
			ajax: {
				url: config.ajaxurl || window.ajaxurl,
				dataType: 'json',
				delay: 300,
				data: ( params ) => ( {
					action: normalizeAjaxAction(
						'content_sync_search_local_posts'
					),
					nonce: getNonce( 'content_sync_search_local_posts' ),
					post_type: remotePost.post_type || 'any',
					comment_type: config.objectSubtype || '',
					search: params.term || remotePost.title || '',
					page: params.page || 1,
					per_page: 20,
				} ),
				processResults: ( response, params ) => {
					params.page = params.page || 1;
					if ( ! response || ! response.success || ! response.data ) {
						return { results: [], pagination: { more: false } };
					}

					return {
						results: ( response.data.posts || [] ).map(
							( post ) => ( {
								id: post.ID,
								text: `${
									post.title ||
									post.post_title ||
									`#${ post.ID }`
								} — ID: ${ post.ID }`,
							} )
						),
						pagination: {
							more:
								params.page <
								parseInt( response.data.pages || 1, 10 ),
						},
					};
				},
			},
		} );
	}

	function showCommentPostMapping( ids ) {
		const posts = {};
		ids.forEach( ( id ) => {
			const item = remoteObjectsById[ id ];
			if ( item?.post?.ID ) {
				posts[ item.post.ID ] = item.post;
			}
		} );

		const postIds = Object.keys( posts );
		if ( ! postIds.length ) {
			return false;
		}

		const $mapping = $( '#rsl-ie-pro-comment-post-mapping' );
		$mapping
			.html(
				`
			<div class="notice notice-info inline"><p>${ escapeHtml(
				config.i18n?.mapCommentPosts ||
					'Map remote comment posts to destination posts, then click Pull selected comments again.'
			) }</p></div>
			${ postIds
				.map( ( remotePostId ) => {
					const post = posts[ remotePostId ];
					return `
						<div class="rsl-ie-form-group" style="margin:12px 0;">
							<label style="display:block;margin-bottom:6px;">${ escapeHtml(
								post.title || `Remote post #${ remotePostId }`
							) } <span style="opacity:.65;">ID: ${ escapeHtml(
								remotePostId
							) }</span></label>
							<select class="rsl-ie-pro-comment-post-map" data-remote-post-id="${ escapeHtml(
								remotePostId
							) }" data-post-type="${ escapeHtml(
								post.post_type || 'any'
							) }" data-title="${ escapeHtml(
								post.title || ''
							) }" style="width:100%;"></select>
						</div>
					`;
				} )
				.join( '' ) }
		`
			)
			.show();

		$mapping.find( '.rsl-ie-pro-comment-post-map' ).each( function () {
			initializeLocalPostMappingSelect( $( this ), {
				post_type: $( this ).data( 'post-type' ),
				title: $( this ).data( 'title' ),
			} );
		} );

		commentPostMappingVisible = true;
		return true;
	}

	function getCommentPostMapping() {
		const mapping = {};
		$( '.rsl-ie-pro-comment-post-map' ).each( function () {
			const remotePostId = String(
				$( this ).data( 'remote-post-id' ) || ''
			);
			const localPostId = String( $( this ).val() || '' );
			if ( remotePostId && localPostId ) {
				mapping[ remotePostId ] = localPostId;
			}
		} );
		return mapping;
	}

	function getSelectedCommentPostMapping( ids, localPostId ) {
		const mapping = {};
		ids.forEach( ( id ) => {
			const item = remoteObjectsById[ id ];
			if ( item?.post?.ID && localPostId ) {
				mapping[ item.post.ID ] = localPostId;
			}
		} );
		return mapping;
	}

	function isCommentPostMappingComplete() {
		let complete = true;
		$( '.rsl-ie-pro-comment-post-map' ).each( function () {
			if ( ! $( this ).val() ) {
				complete = false;
			}
		} );
		return complete;
	}

	function loadRemoteComments() {
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		const search = $( '#rsl-ie-pro-object-search' ).val() || '';
		const $list = $( '#rsl-ie-pro-remote-objects' );

		if ( config.objectType === 'menu' ) {
			const menuId = $( '#rsl-ie-pro-menu-pull-remote-menu' ).val();
			if ( ! menuId ) {
				$list.html(
					`<p style="text-align:center;color:#646970;">${ escapeHtml(
						config.i18n?.selectRemoteMenu || 'Select a remote menu.'
					) }</p>`
				);
				updateRemoteObjectSelectionCount();
				return;
			}

			updateRemoteObjectSelectionCount();
			$list.html( `
				<div class="rsl-ie-loading-posts">
					<span class="spinner is-active"></span>
					<p>${ escapeHtml( config.i18n?.loading || 'Loading...' ) }</p>
				</div>
			` );

			request( getBrowseAction(), { site_id: siteId, menu_id: menuId } )
				.done( ( data ) => {
					const menu = ( data.menus || [] )[ 0 ];
					const items = menu?.items || [];
					remoteObjectsById = {};
					items.forEach( ( item ) => {
						if ( item.ID ) {
							remoteObjectsById[ String( item.ID ) ] = item;
						}
					} );

					if ( ! items.length ) {
						$list.html( `
							<div class="rsl-ie-loading-posts">
								<span class="dashicons dashicons-menu" style="font-size:48px;opacity:.3;width:auto;height:auto;"></span>
								<p>${ escapeHtml( config.i18n?.noItemsFound || 'No items found.' ) }</p>
							</div>
						` );
						$( '#rsl-ie-pro-menu-pull-mapping' ).empty();
						updateRemoteObjectSelectionCount();
						return;
					}

					$list.empty();
					renderMenuObjectMapping(
						'#rsl-ie-pro-menu-pull-mapping',
						items,
						'local'
					);
					updateRemoteObjectSelectionCount();
				} )
				.fail( ( message ) => {
					$list.html(
						`<p class="notice notice-error inline">${ escapeHtml(
							message
						) }</p>`
					);
				} );
			return;
		}

		updateRemoteObjectSelectionCount();
		$list.html( `
			<div class="rsl-ie-loading-posts">
				<span class="spinner is-active"></span>
				<p>${ escapeHtml( config.i18n?.loading || 'Loading...' ) }</p>
			</div>
		` );

		request( getBrowseAction(), getBrowsePayload( siteId, search ) )
			.done( ( data ) => {
				const items =
					config.objectType === 'menu'
						? data.menus || []
						: config.objectType === 'user'
						? data.users || []
						: config.objectType === 'woo_attribute'
						? data.attributes || []
						: data.comments || [];
				if ( ! items.length ) {
					$list.html( `
						<div class="rsl-ie-loading-posts">
							<span class="dashicons ${ escapeHtml(
								getRemoteEmptyIcon()
							) }" style="font-size:48px;opacity:.3;width:auto;height:auto;"></span>
							<p>${ escapeHtml( config.i18n?.noItemsFound || 'No items found.' ) }</p>
						</div>
					` );
					updateRemoteObjectSelectionCount();
					return;
				}

				remoteObjectsById = {};
				items.forEach( ( item ) => {
					const id =
						config.objectType === 'comment'
							? item.comment_ID
							: item.ID || item.term_id || item.attribute_id;
					if ( id ) {
						remoteObjectsById[ String( id ) ] = item;
					}
				} );
				commentPostMappingVisible = false;
				$( '#rsl-ie-pro-comment-post-mapping' ).hide().empty();
				$list.html( items.map( renderRemoteObjectItem ).join( '' ) );
				updateRemoteObjectSelectionCount();
			} )
			.fail( ( message ) => {
				$list.html(
					`<p class="notice notice-error inline">${ escapeHtml(
						message
					) }</p>`
				);
			} );
	}

	function openBrowseModal() {
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		if ( ! siteId ) {
			showMessage(
				config.i18n?.selectSite || 'Please select a site.',
				'warning'
			);
			return;
		}

		ensureBrowseModal();
		$( `#${ browseModalId }` ).show();
		if ( config.objectType === 'menu' ) {
			initializeRemoteMenuSelect2(
				'#rsl-ie-pro-menu-pull-remote-menu',
				`#${ browseModalId } .rsl-ie-modal-content`
			);
		} else {
			loadRemotePostsForCommentFilter();
			initializeMapToPostSelect2();
		}
		loadRemoteComments();
	}

	function pullSelectedComments() {
		const siteId = $( '#rsl-ie-pro-object-sync-site' ).val();
		const ids = [];
		if ( config.objectType === 'menu' ) {
			const remoteMenuId = $( '#rsl-ie-pro-menu-pull-remote-menu' ).val();
			if ( remoteMenuId ) {
				ids.push( remoteMenuId );
			}
		} else {
			$( '.rsl-ie-pro-remote-object-checkbox:checked' ).each(
				function () {
					ids.push( $( this ).val() );
				}
			);
		}

		if ( ! ids.length ) {
			alert(
				config.i18n?.selectRemoteItem ||
					'Please select one or more remote items.'
			);
			return;
		}

		const mappedPostId = String(
			$( '#rsl-ie-pro-comment-map-post' ).val() || ''
		);
		if ( config.objectType === 'comment' && ! mappedPostId ) {
			alert(
				config.i18n?.mapCommentPostsRequired ||
					'Please select a destination post before pulling comments.'
			);
			return;
		}

		const $button = $( '#rsl-ie-pro-object-pull-selected-btn' );
		$button
			.prop( 'disabled', true )
			.text( config.i18n?.loading || 'Loading...' );

		request( getPullAction(), {
			...getPullPayload( siteId, ids ),
			target_post_id: mappedPostId,
			post_mapping: JSON.stringify(
				getSelectedCommentPostMapping( ids, mappedPostId )
			),
		} )
			.done( ( data ) => {
				$( `#${ browseModalId }` ).hide();
				showMessage(
					data.message ||
						config.i18n?.syncComplete ||
						'Sync completed successfully.',
					'success'
				);
				window.setTimeout( () => window.location.reload(), 900 );
			} )
			.fail( ( message ) => {
				alert( message || config.i18n?.syncFailed || 'Sync failed.' );
			} )
			.always( () => {
				$button
					.prop( 'disabled', false )
					.text(
						config.i18n?.pullSelected || getObjectText( 'pull' )
					);
			} );
	}

	$( document ).on(
		'change',
		'tbody .check-column input[type="checkbox"], thead .check-column input[type="checkbox"], tfoot .check-column input[type="checkbox"]',
		() => {
			window.setTimeout( updateButtonState, 0 );
		}
	);

	$( document ).on( 'click', `#${ exportButtonId }`, function ( event ) {
		event.preventDefault();
		if ( config.selectionMode !== 'always' && ! getSelectedIds().length ) {
			updateButtonState();
			return;
		}
		window.open( getExportUrl(), '_blank', 'noopener' );
	} );

	$( document ).on( 'click', `#${ syncButtonId }`, function ( event ) {
		event.preventDefault();
		if (
			! [ 'comment', 'menu', 'user', 'woo_attribute' ].includes(
				config.objectType
			)
		) {
			return;
		}
		if ( redirectToContentSyncIfNoSites() ) {
			return;
		}
		if (
			config.selectionMode !== 'always' &&
			syncRequiresSelection() &&
			! getSelectedIds().length
		) {
			updateButtonState();
			return;
		}
		openSyncModal();
	} );

	$( document ).on(
		'click',
		'.rsl-ie-modal-close, #rsl-ie-pro-object-browse-cancel, #rsl-ie-pro-object-push-cancel',
		function ( event ) {
			event.preventDefault();
			closeModals();
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-object-push-btn',
		function ( event ) {
			event.preventDefault();
			if ( config.objectType === 'menu' ) {
				openMenuPushModal();
				return;
			}
			if ( config.objectType === 'comment' ) {
				openCommentPushModal();
				return;
			}
			pushComments();
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-object-push-confirm',
		function ( event ) {
			event.preventDefault();
			pushComments();
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-object-browse-btn',
		function ( event ) {
			event.preventDefault();
			openBrowseModal();
		}
	);

	$( document ).on( 'change', '#rsl-ie-pro-object-sync-site', function () {
		if ( config.objectType === 'menu' ) {
			$(
				'#rsl-ie-pro-menu-push-mapping, #rsl-ie-pro-menu-pull-mapping, #rsl-ie-pro-remote-objects'
			).empty();
			return;
		}

		if ( config.objectType !== 'comment' ) {
			return;
		}

		const $pushMap = $( '#rsl-ie-pro-comment-push-map-post' );
		if ( $pushMap.data( 'select2' ) ) {
			$pushMap.val( null ).trigger( 'change' );
		}
		if ( $( '.rsl-ie-pro-comment-push-map-wrap' ).is( ':visible' ) ) {
			initializePushMapToRemotePostSelect2();
		}
	} );

	let searchTimeout;
	$( document ).on( 'input', '#rsl-ie-pro-object-search', function () {
		window.clearTimeout( searchTimeout );
		searchTimeout = window.setTimeout( loadRemoteComments, 350 );
	} );

	$( document ).on( 'change', '#rsl-ie-pro-object-post-filter', function () {
		loadRemoteComments();
	} );

	$( document ).on(
		'change',
		'#rsl-ie-pro-menu-pull-remote-menu',
		function () {
			loadRemoteComments();
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-comment-automap-post',
		function ( event ) {
			event.preventDefault();
			automapCommentPost();
		}
	);

	$( document ).on(
		'click',
		'.rsl-ie-pro-menu-automap-one',
		function ( event ) {
			event.preventDefault();
			const $button = $( this );
			const $select = $button
				.closest( '.rsl-ie-menu-map-row' )
				.find( '.rsl-ie-pro-menu-object-map' );
			$button
				.prop( 'disabled', true )
				.text( config.i18n?.loading || 'Loading...' );
			automapMenuObjectSelect( $select ).always( () => {
				$button
					.prop( 'disabled', false )
					.text( config.i18n?.automap || 'Automap' );
			} );
		}
	);

	$( document ).on(
		'click',
		'.rsl-ie-pro-menu-automap-all',
		function ( event ) {
			event.preventDefault();
			const $button = $( this );
			const $selects = $button
				.closest( '.rsl-ie-menu-object-mapping' )
				.find( '.rsl-ie-pro-menu-object-map' );
			const tasks = [];
			$button
				.prop( 'disabled', true )
				.text( config.i18n?.loading || 'Loading...' );
			$selects.each( function () {
				tasks.push( automapMenuObjectSelect( $( this ) ) );
			} );
			$.when( ...tasks ).always( () => {
				$button
					.prop( 'disabled', false )
					.text( config.i18n?.automap || 'Automap' );
			} );
		}
	);

	$( document ).on(
		'change',
		'.rsl-ie-pro-remote-object-checkbox',
		function () {
			$( this )
				.closest( '.rsl-ie-post-item' )
				.toggleClass( 'selected', $( this ).prop( 'checked' ) );
			updateRemoteObjectSelectionCount();
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-object-select-all',
		function ( event ) {
			event.preventDefault();
			$( '.rsl-ie-pro-remote-object-checkbox' )
				.prop( 'checked', true )
				.trigger( 'change' );
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-object-deselect-all',
		function ( event ) {
			event.preventDefault();
			$( '.rsl-ie-pro-remote-object-checkbox' )
				.prop( 'checked', false )
				.trigger( 'change' );
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-pro-object-pull-selected-btn',
		function ( event ) {
			event.preventDefault();
			pullSelectedComments();
		}
	);

	$( () => {
		if ( applyExportPrefillIfNeeded() ) {
			return;
		}

		ensureButtons();
		updateButtonState();
	} );
} )( jQuery );
