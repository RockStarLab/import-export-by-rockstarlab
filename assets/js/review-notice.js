/* global ajaxurl */

jQuery( function ( $ ) {
	$( document ).on( 'click', '.rsl-ie-review-dismiss', function ( e ) {
		// Allow anchor tags to open the link normally, but still dismiss
		if ( ! $( this ).is( 'a' ) ) {
			e.preventDefault();
		}

		var nonce = $( '#rsl-ie-review-notice' ).data( 'nonce' );

		$.post( ajaxurl, {
			action: 'rsl_ie_dismiss_review_notice',
			nonce: nonce,
		} );

		$( '#rsl-ie-review-notice' ).fadeOut( 350, function () {
			$( this ).remove();
		} );
	} );
} );
