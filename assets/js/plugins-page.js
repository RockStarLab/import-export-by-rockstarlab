/* global jQuery, rslIePluginsPage */

jQuery( function ( $ ) {
	'use strict';

	const slug = ( window.rslIePluginsPage && window.rslIePluginsPage.slug ) || '';
	if ( ! slug ) {
		return;
	}

	// Wait for Plugins table to fully render, then trigger Freemius activation link.
	setTimeout( function () {
		const $row = $( `tr[data-slug="${ slug }"]` );
		if ( ! $row.length ) {
			return;
		}

		const $activateLink = $row.find( '.fs-activate-license-trigger' ).first();
		if ( $activateLink.length ) {
			$activateLink.get( 0 ).click();
			return;
		}

		// Fallback: attempt to find any activation link in the plugin row.
		$row.find( 'a' ).each( function () {
			const $link = $( this );
			const text = ( $link.text() || '' ).toLowerCase();
			if ( text.indexOf( 'activate' ) !== -1 ) {
				this.click();
				return false;
			}
			return undefined;
		} );
	}, 500 );
} );
