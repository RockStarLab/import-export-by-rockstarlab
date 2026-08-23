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

		if ( ! isBulkSelectMode() || ! $toolbar.length ) {
			$existingButton.remove();
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
			class: `button button-primary media-button ${ buttonClass }`,
			text: getButtonLabel(),
			disabled: true,
			'aria-disabled': 'true',
			title:
				config.disabledTitle ||
				'Select one or more media files to enable export.',
		} );

		insertButton( $toolbar, $button );
	}

	function getButtonLabel( count = 0 ) {
		const label =
			count > 0
				? `${ config.exportLabel || 'Export' } (${ count })`
				: config.exportLabel || 'Export';

		return label;
	}

	function getToolbarTarget() {
		const selectors = [
			'.media-toolbar-primary:visible',
			'.media-toolbar-secondary:visible',
			'.media-toolbar-primary',
			'.media-toolbar-secondary',
		];

		for ( let index = 0; index < selectors.length; index++ ) {
			const $target = $( selectors[ index ] ).first();

			if ( $target.length ) {
				return $target;
			}
		}

		return $();
	}

	function isBulkSelectMode() {
		return (
			$( 'body' ).hasClass( 'mode-select' ) ||
			$( '.media-frame' ).hasClass( 'mode-select' ) ||
			$(
				'.delete-selected-button:visible, .media-button-delete-selected:visible'
			).length > 0
		);
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

		if ( ! $button.length ) {
			return;
		}

		const hasSelection = ids.length > 0;

		$button
			.prop( 'disabled', ! hasSelection )
			.attr( 'aria-disabled', hasSelection ? 'false' : 'true' )
			.text( getButtonLabel( hasSelection ? ids.length : 0 ) );
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
	} );
} )( jQuery );
