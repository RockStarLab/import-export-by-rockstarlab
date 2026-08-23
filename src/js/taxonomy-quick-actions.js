/**
 * Taxonomy Quick Actions
 *
 * Adds an Export button to taxonomy term list screens and opens the export
 * wizard with selected term IDs pre-filled.
 */

( function ( $ ) {
	'use strict';

	const config = window.rslIeTaxonomyQuickActions || {};
	const exportButtonId = 'rsl-ie-export-selected-terms-btn';
	const syncButtonId = 'rsl-ie-sync-selected-terms-btn';
	const modalId = 'rsl-ie-taxonomy-sync-modal';
	const browseModalId = 'rsl-ie-taxonomy-sync-browse-modal';
	let browseMode = 'create';
	let remoteTermsCache = [];

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

	function getSelectedTermIds() {
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

	function getSelectedTerms() {
		const terms = [];

		$( 'tbody .check-column input[type="checkbox"]:checked' ).each(
			function () {
				const id = $( this ).val();
				if ( ! id || ! /^\d+$/.test( String( id ) ) ) {
					return;
				}

				const $row = $( this ).closest( 'tr' );
				terms.push( {
					id: String( id ),
					name: $.trim( $row.find( '.row-title' ).first().text() ),
					slug: $.trim( $row.find( '.slug' ).first().text() ),
				} );
			}
		);

		return terms.filter(
			( term, index, allTerms ) =>
				term.id &&
				allTerms.findIndex( ( item ) => item.id === term.id ) === index
		);
	}

	function getToolbarTarget() {
		return $( '.tablenav.top .alignleft.actions.bulkactions' ).first();
	}

	function ensureButtons() {
		const $toolbar = getToolbarTarget();

		if ( ! $toolbar.length ) {
			return;
		}

		if ( config.exportEnabled && ! $( `#${ exportButtonId }` ).length ) {
			$toolbar.append(
				$( '<button>', {
					type: 'button',
					id: exportButtonId,
					class: 'button action',
					text: config.exportLabel || 'Export',
					disabled: true,
					'aria-disabled': 'true',
					title:
						config.disabledTitle ||
						'Select one or more terms to enable export.',
				} ).css( 'margin-left', '5px' )
			);
		}

		if ( config.syncEnabled && ! $( `#${ syncButtonId }` ).length ) {
			$toolbar.append(
				$( '<button>', {
					type: 'button',
					id: syncButtonId,
					class: 'button action',
					text: config.syncLabel || 'Sync',
					disabled: false,
					'aria-disabled': 'false',
					title:
						config.i18n?.syncTerms ||
						'Sync terms. Select terms first to push, or browse remote terms to pull.',
				} ).css( 'margin-left', '5px' )
			);
		}
	}

	function updateButtonState() {
		ensureButtons();

		const ids = getSelectedTermIds();
		const hasSelection = ids.length > 0;

		$( `#${ exportButtonId }` )
			.prop( 'disabled', ! hasSelection )
			.attr( 'aria-disabled', hasSelection ? 'false' : 'true' )
			.text(
				hasSelection
					? `${ config.exportLabel || 'Export' } (${ ids.length })`
					: config.exportLabel || 'Export'
			);

		$( `#${ syncButtonId }` )
			.prop( 'disabled', false )
			.attr( 'aria-disabled', 'false' )
			.text(
				hasSelection
					? `${ config.syncLabel || 'Sync' } (${ ids.length })`
					: config.syncLabel || 'Sync'
			);
	}

	function getExportUrl( ids ) {
		const exportUrl =
			config.exportUrl ||
			`${
				window.ajaxurl
					? window.ajaxurl.replace( 'admin-ajax.php', 'admin.php' )
					: 'admin.php'
			}?page=rsl-ie-export`;
		const separator = exportUrl.indexOf( '?' ) === -1 ? '?' : '&';
		const params = new URLSearchParams( {
			rsl_ie_prefill: 'taxonomy_terms',
			taxonomy: config.taxonomy || '',
			term_ids: ids.join( ',' ),
		} );

		return `${ exportUrl }${ separator }${ params.toString() }`;
	}

	function getSitesOptions() {
		const sites = config.connectedSites || {};
		const options = [
			`<option value="">${
				config.i18n?.selectSite || 'Please select a site.'
			}</option>`,
		];

		Object.keys( sites ).forEach( ( id ) => {
			const site = sites[ id ];
			const siteId = getScalarValue( site?.id, id );
			const siteName = getScalarValue( site?.name, '' );
			const siteUrl = getScalarValue( site?.remote_url, '' );
			options.push(
				`<option value="${ escapeHtml( siteId || id ) }">${ escapeHtml(
					siteName || id
				) }${ siteUrl ? ` (${ escapeHtml( siteUrl ) })` : '' }</option>`
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

	function escapeHtml( value ) {
		return String( getScalarValue( value, '' ) || '' )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function getScalarValue( value, fallback = '' ) {
		if ( value === null || typeof value === 'undefined' ) {
			return fallback;
		}

		if ( typeof value === 'function' ) {
			return fallback;
		}

		if ( typeof value !== 'object' ) {
			return value;
		}

		const candidates = [
			'raw',
			'rendered',
			'value',
			'label',
			'text',
			'title',
			'name',
			'slug',
			'id',
			'term_id',
			'ID',
		];
		for ( let index = 0; index < candidates.length; index++ ) {
			const key = candidates[ index ];
			if ( typeof value[ key ] === 'undefined' ) {
				continue;
			}

			if ( typeof value[ key ] === 'function' ) {
				continue;
			}

			if ( typeof value[ key ] !== 'object' ) {
				return value[ key ];
			}

			const nested = getScalarValue( value[ key ], '' );
			if ( nested !== '' ) {
				return nested;
			}
		}

		return fallback;
	}

	function extractTerms( data ) {
		let terms = data?.terms || data?.data?.terms || data?.items || [];

		if ( terms && ! Array.isArray( terms ) && typeof terms === 'object' ) {
			terms = Object.values( terms );
		}

		return Array.isArray( terms ) ? terms.map( normalizeRemoteTerm ) : [];
	}

	function normalizeRemoteTerm( term ) {
		if ( typeof term !== 'object' || term === null ) {
			const value = getScalarValue( term, '' );
			return {
				term_id: String( value || '' ),
				name: String( value || '' ),
				slug: '',
				count: '',
				parent_term_id: '0',
			};
		}

		const id = getScalarValue( term?.term_id ?? term?.id ?? term?.ID, '' );
		const name = getScalarValue(
			term?.name ?? term?.title,
			id ? `#${ id }` : ''
		);
		const slug = getScalarValue( term?.slug, '' );
		const count = getScalarValue( term?.count, '' );
		const parentId = getScalarValue(
			term?.parent_term_id ?? term?.parent_id ?? term?.parent,
			'0'
		);
		const parentSlug = getScalarValue( term?.parent_slug, '' );
		const parentPath = getScalarValue( term?.parent_path, '' );

		return {
			...term,
			term_id: String( id || '' ),
			name: String( name || '' ),
			slug: String( slug || '' ),
			count: String( count || '' ),
			parent_term_id: String( parentId || '0' ),
			parent_slug: String( parentSlug || '' ),
			parent_path: String( parentPath || '' ),
		};
	}

	function getTermParentId( term ) {
		const parentId = String(
			term?.parent_term_id || term?.parent_id || term?.parent || '0'
		);
		return /^-?\d+$/.test( parentId ) && parseInt( parentId, 10 ) > 0
			? parentId
			: '0';
	}

	function getHierarchicalTerms( terms ) {
		const byId = {};
		const childrenByParent = {};
		const roots = [];

		terms.forEach( ( term ) => {
			if ( term?.term_id ) {
				byId[ String( term.term_id ) ] = term;
			}
		} );

		terms.forEach( ( term ) => {
			const parentId = getTermParentId( term );
			if ( parentId !== '0' && byId[ parentId ] ) {
				if ( ! childrenByParent[ parentId ] ) {
					childrenByParent[ parentId ] = [];
				}
				childrenByParent[ parentId ].push( term );
			} else {
				roots.push( term );
			}
		} );

		const sortTerms = ( first, second ) =>
			String( first.name || '' ).localeCompare(
				String( second.name || '' )
			);

		Object.keys( childrenByParent ).forEach( ( parentId ) => {
			childrenByParent[ parentId ].sort( sortTerms );
		} );
		roots.sort( sortTerms );

		const flattened = [];
		const visited = new Set();

		const appendTerm = ( term, depth ) => {
			const termId = String( term.term_id || '' );
			if ( ! termId || visited.has( termId ) ) {
				return;
			}

			visited.add( termId );
			flattened.push( { term, depth } );

			( childrenByParent[ termId ] || [] ).forEach( ( child ) => {
				appendTerm( child, depth + 1 );
			} );
		};

		roots.forEach( ( term ) => appendTerm( term, 0 ) );
		terms.forEach( ( term ) => appendTerm( term, 0 ) );

		return flattened;
	}

	function ensureSyncModal() {
		if ( $( `#${ modalId }` ).length ) {
			return;
		}

		$( 'body' ).append( `
			<div id="${ modalId }" class="rsl-ie-modal rsl-ie-mapping-modal" style="display:none;">
				<div class="rsl-ie-modal-content">
					<div class="rsl-ie-modal-header">
						<h2>${ escapeHtml( config.i18n?.syncTerms || 'Sync Terms' ) }</h2>
						<button type="button" class="rsl-ie-modal-close" aria-label="${ escapeHtml(
							config.i18n?.close || 'Close'
						) }">&times;</button>
					</div>
					<div class="rsl-ie-modal-body" style="text-align:center;">
						<div class="rsl-ie-sync-info" id="rsl-ie-taxonomy-selected-info">
							<p>
								<strong>${ escapeHtml(
									config.i18n?.selectedTerms ||
										'Selected terms:'
								) }</strong>
								<span id="rsl-ie-taxonomy-selected-count">0</span>
							</p>
						</div>
						<div class="rsl-ie-form-group" style="max-width:360px;margin:0 auto 16px;">
							<label for="rsl-ie-taxonomy-sync-site" style="display:block;margin-bottom:6px;">${ escapeHtml(
								config.i18n?.selectSiteLabel || 'Select Site'
							) }</label>
							<select id="rsl-ie-taxonomy-sync-site" class="rsl-ie-form-control" style="display:block;width:100%;">${ getSitesOptions() }</select>
						</div>
						<div class="rsl-ie-taxonomy-sync-actions" style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap;">
							<button type="button" class="button button-primary" id="rsl-ie-taxonomy-push-btn" style="width:auto;flex:0 0 auto;">${ escapeHtml(
								config.i18n?.pushTerms || 'Push selected terms'
							) }</button>
							<button type="button" class="button" id="rsl-ie-taxonomy-browse-btn" style="width:auto;flex:0 0 auto;">${ escapeHtml(
								config.i18n?.pullTerms || 'Pull from remote'
							) }</button>
						</div>
						<div id="rsl-ie-taxonomy-sync-message" style="margin-top:12px;"></div>
					</div>
				</div>
			</div>
		` );
	}

	function ensureBrowseModal() {
		if ( $( `#${ browseModalId }` ).length ) {
			return;
		}

		$( 'body' ).append( `
			<div id="${ browseModalId }" class="rsl-ie-modal rsl-ie-browse-library-modal" style="display:none;">
				<div class="rsl-ie-modal-content rsl-ie-modal-large">
					<div class="rsl-ie-modal-header">
						<h2>${ escapeHtml( config.i18n?.remoteTerms || 'Remote Terms' ) }</h2>
						<button type="button" class="rsl-ie-modal-close" aria-label="${ escapeHtml(
							config.i18n?.close || 'Close'
						) }">&times;</button>
					</div>
					<div class="rsl-ie-browse-search-bar">
						<input type="search" id="rsl-ie-taxonomy-term-search" class="regular-text" placeholder="${ escapeHtml(
							config.i18n?.searchTerms || 'Search terms...'
						) }">
					</div>
					<div class="rsl-ie-browse-body">
						<div class="rsl-ie-browse-main">
							<div id="rsl-ie-taxonomy-remote-terms" class="rsl-ie-posts-tree"></div>
						</div>
					</div>
					<div class="rsl-ie-browse-footer">
						<button type="button" class="button" id="rsl-ie-taxonomy-browse-cancel">${ escapeHtml(
							config.i18n?.cancel || 'Cancel'
						) }</button>
						<button type="button" class="button button-primary" id="rsl-ie-taxonomy-pull-selected-btn">${ escapeHtml(
							config.i18n?.pullSelected || 'Pull selected terms'
						) }</button>
					</div>
				</div>
			</div>
		` );
	}

	function showMessage( message, type = 'info' ) {
		if ( ! message ) {
			$( '#rsl-ie-taxonomy-sync-message' ).empty();
			return;
		}

		$( '#rsl-ie-taxonomy-sync-message' ).html(
			`<div class="notice notice-${ type } inline"><p>${ escapeHtml(
				message
			) }</p></div>`
		);
	}

	function showRemoteTermsMessage( message, type = 'info' ) {
		const fallback =
			type === 'error'
				? config.i18n?.syncFailed || 'Failed to load remote terms.'
				: config.i18n?.noTermsFound || 'No terms found.';
		const text = getErrorMessage( message, fallback );

		$( '#rsl-ie-taxonomy-remote-terms' ).html(
			`<div class="notice notice-${ type } inline"><p>${ escapeHtml(
				text
			) }</p></div>`
		);
	}

	function getErrorMessage( value, fallback ) {
		if ( typeof value === 'string' && value.trim() ) {
			return value;
		}

		if ( value && typeof value === 'object' ) {
			const candidates = [
				value.responseJSON?.data?.message,
				value.responseJSON?.message,
				value.message,
				value.error,
				value.code,
				value.statusText,
				value.responseText,
				value.data?.message,
			];

			for ( let index = 0; index < candidates.length; index++ ) {
				const message = getScalarValue( candidates[ index ], '' );
				if ( String( message || '' ).trim() ) {
					return message;
				}
			}
		}

		return fallback;
	}

	function request( action, data ) {
		const normalizedAction = normalizeAjaxAction( action );
		const nonce = getNonce( normalizedAction );

		return $.ajax( {
			url: config.ajaxurl || window.ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: normalizedAction,
				nonce,
				...data,
			},
		} ).then(
			( response ) => {
				if ( ! response || ! response.success ) {
					const message = getErrorMessage(
						response,
						config.i18n?.syncFailed || 'Sync failed.'
					);
					return $.Deferred().reject( message ).promise();
				}

				return response.data || response;
			},
			( jqXHR, textStatus, errorThrown ) => {
				const message = getErrorMessage(
					errorThrown || jqXHR,
					getErrorMessage(
						jqXHR,
						textStatus || config.i18n?.syncFailed || 'Sync failed.'
					)
				);
				return $.Deferred().reject( message ).promise();
			}
		);
	}

	function openSyncModal() {
		if ( redirectToContentSyncIfNoSites() ) {
			return;
		}

		ensureSyncModal();
		const selectedCount = getSelectedTermIds().length;
		const hasSelection = selectedCount > 0;

		$( '#rsl-ie-taxonomy-selected-count' ).text( selectedCount );
		$( '#rsl-ie-taxonomy-selected-info' ).toggle( hasSelection );
		$( '#rsl-ie-taxonomy-push-btn' ).toggle( hasSelection );
		$( '#rsl-ie-taxonomy-browse-btn' ).text(
			hasSelection
				? config.i18n?.pullUpdateTerms || 'Pull / update selected terms'
				: config.i18n?.pullTerms || 'Browse remote terms'
		);

		$( `#${ modalId }` ).css( 'display', 'flex' );
		showMessage( '' );
	}

	function closeModals() {
		$( `#${ modalId }, #${ browseModalId }` ).hide();
	}

	function pushTerms() {
		const siteId = $( '#rsl-ie-taxonomy-sync-site' ).val();
		const ids = getSelectedTermIds();

		if ( ! siteId ) {
			showMessage(
				config.i18n?.selectSite || 'Please select a site.',
				'warning'
			);
			return;
		}

		if ( ! ids.length ) {
			showMessage(
				config.i18n?.selectTerms || 'Please select one or more terms.',
				'warning'
			);
			return;
		}

		const $button = $( '#rsl-ie-taxonomy-push-btn' );
		$button
			.prop( 'disabled', true )
			.text( config.i18n?.loading || 'Loading...' );

		request( 'content_sync_push_terms', {
			site_id: siteId,
			taxonomy: config.taxonomy || '',
			term_ids: ids,
		} )
			.done( ( data ) => {
				showMessage(
					data.message ||
						config.i18n?.termsSynced ||
						'Terms synced successfully.',
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
					.text( config.i18n?.pushTerms || 'Push selected terms' );
			} );
	}

	function loadRemoteTerms() {
		const siteId = $( '#rsl-ie-taxonomy-sync-site' ).val();
		const search = $( '#rsl-ie-taxonomy-term-search' ).val() || '';
		const $list = $( '#rsl-ie-taxonomy-remote-terms' );

		$list.html(
			`<p>${ escapeHtml( config.i18n?.loading || 'Loading...' ) }</p>`
		);

		request( 'content_sync_get_remote_terms', {
			site_id: siteId,
			taxonomy: config.taxonomy || '',
			search,
			page: 1,
			per_page: 100,
		} )
			.done( ( data ) => {
				const terms = extractTerms( data );
				remoteTermsCache = terms;
				if ( ! terms.length ) {
					showRemoteTermsMessage(
						config.i18n?.noTermsFound || 'No terms found.',
						'warning'
					);
					return;
				}

				if ( browseMode === 'map' ) {
					renderMappingTerms( terms );
				} else {
					renderRemoteCheckboxTerms( terms );
				}
			} )
			.fail( ( message ) => {
				showRemoteTermsMessage( message, 'error' );
			} );
	}

	function renderRemoteCheckboxTerms( terms ) {
		const hierarchicalTerms = getHierarchicalTerms( terms );

		$( '#rsl-ie-taxonomy-remote-terms' ).html(
			`
				<div class="rsl-ie-browse-bulk-actions">
					<div class="rsl-ie-browse-bulk-info">
						<span class="dashicons dashicons-list-view"></span>
						<span>
							${ escapeHtml( config.i18n?.bulkActions || 'Bulk actions for visible terms' ) }
							(<span id="rsl-ie-taxonomy-remote-selected-count">0</span>)
						</span>
					</div>
					<div class="rsl-ie-browse-bulk-buttons">
						<button type="button" id="rsl-ie-taxonomy-select-all-remote" class="button button-small">${ escapeHtml(
							config.i18n?.selectAll || 'Select all'
						) }</button>
						<button type="button" id="rsl-ie-taxonomy-deselect-all-remote" class="button button-small">${ escapeHtml(
							config.i18n?.deselectAll || 'Deselect all'
						) }</button>
					</div>
				</div>
				${ hierarchicalTerms
					.map( ( item ) => {
						const term = item.term;
						const depth = Math.min(
							parseInt( item.depth, 10 ) || 0,
							8
						);
						const indent = depth * 24;

						return `
						<div class="rsl-ie-post-wrapper">
							<label class="rsl-ie-post-item" style="cursor:pointer;">
								<input type="checkbox" class="rsl-ie-post-checkbox rsl-ie-remote-term-checkbox" value="${ escapeHtml(
									term.term_id
								) }">
								<span class="rsl-ie-taxonomy-tree-indent" style="flex:0 0 ${ indent }px;"></span>
								${
									depth
										? '<span class="rsl-ie-taxonomy-tree-branch" style="color:#8c8f94;flex:0 0 auto;">↳</span>'
										: ''
								}
								<span class="rsl-ie-post-icon">
									<span class="dashicons dashicons-category"></span>
								</span>
								<div class="rsl-ie-post-info">
									<div class="rsl-ie-post-title">${ escapeHtml( term.name ) }</div>
									<div class="rsl-ie-post-meta">
										<span>${ escapeHtml( term.slug ) }</span>
										<span>ID: ${ escapeHtml( term.term_id ) }</span>
										${ term.count !== '' ? `<span>${ escapeHtml( term.count ) } items</span>` : '' }
									</div>
								</div>
							</label>
						</div>
					`;
					} )
					.join( '' ) }
			`
		);

		updateRemoteTermSelection();
	}

	function updateRemoteTermSelection() {
		const $checkboxes = $( '.rsl-ie-remote-term-checkbox' );
		const count = $checkboxes.filter( ':checked' ).length;

		$checkboxes.each( function () {
			$( this )
				.closest( '.rsl-ie-post-item' )
				.toggleClass( 'selected', $( this ).prop( 'checked' ) );
		} );

		$( '#rsl-ie-taxonomy-remote-selected-count' ).text( count );
		$( '#rsl-ie-taxonomy-pull-selected-btn' ).prop(
			'disabled',
			count === 0
		);
	}

	function renderMappingTerms( terms ) {
		const selectedTerms = getSelectedTerms();
		const options = [
			`<option value="">${ escapeHtml(
				config.i18n?.selectRemoteTerm || 'Select remote term...'
			) }</option>`,
		].concat(
			terms.map(
				( term ) =>
					`<option value="${ escapeHtml(
						term.term_id
					) }" data-slug="${ escapeHtml(
						term.slug
					) }" data-name="${ escapeHtml( term.name ) }">${ escapeHtml(
						term.name
					) } (${ escapeHtml( term.slug ) })</option>`
			)
		);

		$( '#rsl-ie-taxonomy-remote-terms' ).html( `
			<div class="rsl-ie-mapping-table-container">
			<table class="widefat striped rsl-ie-mapping-table">
				<thead>
					<tr>
						<th class="rsl-ie-local-post">${ escapeHtml(
							config.i18n?.localTerm || 'Local term'
						) }</th>
						<th class="rsl-ie-sync-arrow"></th>
						<th class="rsl-ie-remote-post">${ escapeHtml(
							config.i18n?.remoteTerm || 'Remote term to pull'
						) }</th>
					</tr>
				</thead>
				<tbody>
					${ selectedTerms
						.map( ( term ) => {
							const match = findRemoteTermMatch( term, terms );
							return `
								<tr>
									<td>
										<strong>${ escapeHtml( term.name || `#${ term.id }` ) }</strong>
										<div style="color:#646970;">${ escapeHtml(
											term.slug || ''
										) } · ID: ${ escapeHtml(
											term.id
										) }</div>
									</td>
									<td class="rsl-ie-sync-arrow">←</td>
									<td>
										<div class="rsl-ie-remote-select-wrapper rsl-ie-action-update">
											<select class="regular-text rsl-ie-taxonomy-term-map" data-local-term-id="${ escapeHtml(
												term.id
											) }">
												${ options.join( '' ) }
											</select>
										</div>
									</td>
								</tr>
							`.replace(
								`value="${ escapeHtml(
									match ? match.term_id : ''
								) }"`,
								`value="${ escapeHtml(
									match ? match.term_id : ''
								) }" selected`
							);
						} )
						.join( '' ) }
				</tbody>
			</table>
			</div>
			<p class="description" style="margin-top:8px;">${ escapeHtml(
				config.i18n?.mappingHelp ||
					'Choose which remote term should update each selected local term.'
			) }</p>
		` );

		$( '#rsl-ie-taxonomy-pull-selected-btn' ).prop( 'disabled', false );
	}

	function findRemoteTermMatch( localTerm, remoteTerms ) {
		const localSlug = String( localTerm.slug || '' ).toLowerCase();
		const localName = String( localTerm.name || '' ).toLowerCase();

		return (
			remoteTerms.find(
				( term ) =>
					String( term.slug || '' ).toLowerCase() === localSlug
			) ||
			remoteTerms.find(
				( term ) =>
					String( term.name || '' ).toLowerCase() === localName
			) ||
			null
		);
	}

	function openBrowseModal() {
		const siteId = $( '#rsl-ie-taxonomy-sync-site' ).val();
		if ( ! siteId ) {
			showMessage(
				config.i18n?.selectSite || 'Please select a site.',
				'warning'
			);
			return;
		}

		ensureBrowseModal();
		browseMode = getSelectedTermIds().length ? 'map' : 'create';
		remoteTermsCache = [];
		$( '#rsl-ie-taxonomy-term-search' ).val( '' );
		$( '#rsl-ie-taxonomy-pull-selected-btn' ).text(
			browseMode === 'map'
				? config.i18n?.pullUpdateTerms || 'Pull / update selected terms'
				: config.i18n?.pullSelected || 'Pull selected terms'
		);
		$( `#${ browseModalId }` ).css( 'display', 'flex' );
		loadRemoteTerms();
	}

	function pullSelectedTerms() {
		const siteId = $( '#rsl-ie-taxonomy-sync-site' ).val();
		const ids = [];
		const mapping = {};

		if ( browseMode === 'map' ) {
			$( '.rsl-ie-taxonomy-term-map' ).each( function () {
				const remoteId = $( this ).val();
				const localId = $( this ).data( 'local-term-id' );
				if ( remoteId && localId ) {
					ids.push( remoteId );
					mapping[ remoteId ] = localId;
				}
			} );
		} else {
			$( '.rsl-ie-remote-term-checkbox:checked' ).each( function () {
				ids.push( $( this ).val() );
			} );
		}

		if ( ! ids.length ) {
			alert(
				config.i18n?.selectRemoteTerm ||
					'Please select one or more remote terms.'
			);
			return;
		}

		const $button = $( '#rsl-ie-taxonomy-pull-selected-btn' );
		$button
			.prop( 'disabled', true )
			.text( config.i18n?.loading || 'Loading...' );

		request( 'content_sync_pull_terms', {
			site_id: siteId,
			taxonomy: config.taxonomy || '',
			term_ids: ids,
			term_mapping: mapping,
		} )
			.done( ( data ) => {
				$( `#${ modalId }, #${ browseModalId }` ).hide();
				showMessage(
					data.message ||
						config.i18n?.termsSynced ||
						'Terms synced successfully.',
					'success'
				);
				window.setTimeout( () => {
					window.location.reload();
				}, 300 );
			} )
			.fail( ( message ) => {
				alert( message || config.i18n?.syncFailed || 'Sync failed.' );
			} )
			.always( () => {
				$button
					.prop( 'disabled', false )
					.text( config.i18n?.pullSelected || 'Pull selected terms' );
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

		const ids = getSelectedTermIds();
		if ( ! ids.length ) {
			updateButtonState();
			return;
		}

		window.open( getExportUrl( ids ), '_blank', 'noopener' );
	} );

	$( document ).on( 'click', `#${ syncButtonId }`, function ( event ) {
		event.preventDefault();
		openSyncModal();
	} );

	$( document ).on(
		'click',
		'.rsl-ie-modal-close, #rsl-ie-taxonomy-browse-cancel',
		function ( event ) {
			event.preventDefault();
			closeModals();
		}
	);

	$( document ).on( 'click', '#rsl-ie-taxonomy-push-btn', function ( event ) {
		event.preventDefault();
		pushTerms();
	} );

	$( document ).on(
		'click',
		'#rsl-ie-taxonomy-browse-btn',
		function ( event ) {
			event.preventDefault();
			openBrowseModal();
		}
	);

	let searchTimeout;
	$( document ).on( 'input', '#rsl-ie-taxonomy-term-search', function () {
		window.clearTimeout( searchTimeout );
		searchTimeout = window.setTimeout( loadRemoteTerms, 350 );
	} );

	$( document ).on(
		'click',
		'#rsl-ie-taxonomy-pull-selected-btn',
		function ( event ) {
			event.preventDefault();
			pullSelectedTerms();
		}
	);

	$( document ).on(
		'change',
		'.rsl-ie-remote-term-checkbox',
		updateRemoteTermSelection
	);

	$( document ).on(
		'click',
		'#rsl-ie-taxonomy-select-all-remote',
		function ( event ) {
			event.preventDefault();
			$( '.rsl-ie-remote-term-checkbox:visible' ).prop( 'checked', true );
			updateRemoteTermSelection();
		}
	);

	$( document ).on(
		'click',
		'#rsl-ie-taxonomy-deselect-all-remote',
		function ( event ) {
			event.preventDefault();
			$( '.rsl-ie-remote-term-checkbox:visible' ).prop(
				'checked',
				false
			);
			updateRemoteTermSelection();
		}
	);

	$( () => {
		ensureButtons();
		updateButtonState();
	} );
} )( jQuery );
