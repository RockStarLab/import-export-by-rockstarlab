/**
 * Media Library Export Shortcut
 *
 * Adds an Export button to the Media Library grid bulk-select toolbar and
 * opens the plugin export wizard pre-filled with selected attachment IDs.
 */

( function ( $ ) {
	'use strict';

	const config = window.rslIeMediaLibraryExport || {};
	const buttonClass = 'rsl-ie-media-library-export-button';

	function getSelectedAttachmentIds() {
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

		return ids
			.filter( ( id ) => /^\d+$/.test( id ) && parseInt( id, 10 ) > 0 )
			.filter( ( id, index, allIds ) => allIds.indexOf( id ) === index );
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
			rsl_ie_prefill: 'media_library',
			media_ids: ids.join( ',' ),
		} );

		return `${ exportUrl }${ separator }${ params.toString() }`;
	}

	function ensureButton() {
		const $existingButton = $( `.${ buttonClass }` );
		const $toolbar = getToolbarTarget();

		if ( ! $toolbar.length ) {
			return;
		}

		if ( $existingButton.length ) {
			if ( ! $.contains( $toolbar[ 0 ], $existingButton[ 0 ] ) ) {
				$existingButton.detach();
				insertButton( $toolbar, $existingButton );
			}

			return;
		}

		const $button = $( '<button>', {
			type: 'button',
			class: `button button-primary media-button button-large ${ buttonClass }`,
			text: config.exportLabel || 'Export',
			disabled: true,
			'aria-disabled': 'true',
			title:
				config.disabledTitle ||
				'Select one or more media files to enable export.',
		} ).css( {
			marginLeft: '8px',
			minHeight: '40px',
			lineHeight: '38px',
			padding: '0 18px',
		} );

		insertButton( $toolbar, $button );
	}

	function getToolbarTarget() {
		const selectors = [
			'.media-toolbar-primary:visible',
			'.media-toolbar-secondary:visible',
			'.wp-filter .actions:visible',
			'.wp-filter:visible',
			'.media-toolbar-primary',
			'.media-toolbar-secondary',
			'.wp-filter .actions',
			'.wp-filter',
		];

		for ( let index = 0; index < selectors.length; index++ ) {
			const $target = $( selectors[ index ] ).first();

			if ( $target.length ) {
				return $target;
			}
		}

		return $();
	}

	function insertButton( $toolbar, $button ) {
		const $insertAfter = $toolbar
			.find(
				'.delete-selected-button, .media-button-delete-selected, .select-mode-toggle-button'
			)
			.first();

		if ( $insertAfter.length ) {
			$button.insertAfter( $insertAfter );
			return;
		}

		$toolbar.append( $button );
	}

	function updateButtonState() {
		ensureButton();

		const ids = getSelectedAttachmentIds();
		const $button = $( `.${ buttonClass }` );
		const hasSelection = ids.length > 0;

		$button
			.prop( 'disabled', ! hasSelection )
			.attr( 'aria-disabled', hasSelection ? 'false' : 'true' )
			.text(
				hasSelection
					? `${ config.exportLabel || 'Export' } (${ ids.length })`
					: config.exportLabel || 'Export'
			);
	}

	function bindMediaSelectionEvents() {
		if (
			! window.wp ||
			! window.wp.media ||
			! window.wp.media.frame ||
			typeof window.wp.media.frame.state !== 'function'
		) {
			return;
		}

		const state = window.wp.media.frame.state();
		const selection =
			state && typeof state.get === 'function'
				? state.get( 'selection' )
				: null;

		if ( selection && typeof selection.on === 'function' ) {
			selection.on( 'add remove reset', updateButtonState );
		}
	}

	$( document ).on(
		'click',
		'.select-mode-toggle-button, .attachment, .attachment .check, .delete-selected-button, .media-button-delete-selected',
		() => {
			window.setTimeout( updateButtonState, 50 );
		}
	);

	$( document ).on( 'click', `.${ buttonClass }`, function ( event ) {
		event.preventDefault();

		const ids = getSelectedAttachmentIds();

		if ( ! ids.length ) {
			updateButtonState();
			return;
		}

		window.open( getExportUrl( ids ), '_blank', 'noopener' );
	} );

	$( () => {
		ensureButton();
		bindMediaSelectionEvents();
		updateButtonState();

		const target =
			document.querySelector( '.media-frame' ) || document.body;
		const observer = new window.MutationObserver( () => {
			window.requestAnimationFrame( updateButtonState );
		} );

		observer.observe( target, {
			attributes: true,
			attributeFilter: [ 'class', 'aria-checked' ],
			childList: true,
			subtree: true,
		} );
	} );
} )( jQuery );
