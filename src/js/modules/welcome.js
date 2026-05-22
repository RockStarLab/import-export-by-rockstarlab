/**
 * Welcome Module
 *
 * Handles interactions on the Welcome page (promo code copy, etc).
 */

const WelcomeModule = {
	/**
	 * Initialize module.
	 */
	init() {
		if ( ! jQuery( '.rsl-ie-card--promo' ).length ) {
			return;
		}

		this.bindEvents();
	},

	/**
	 * Bind event handlers.
	 */
	bindEvents() {
		const $ = jQuery;

		$( document ).on( 'click', '.rsl-ie-copy-promo-code', function ( e ) {
			e.preventDefault();

			const $btn = $( this );
			const codeEl = document.getElementById( 'rsl-ie-promo-code' );
			const promoCode = codeEl ? ( codeEl.textContent || '' ).trim() : '';

			if ( ! promoCode ) {
				return;
			}

			const copiedLabel =
				'<span class="dashicons dashicons-yes"></span> ' +
				( window.rslIeData &&
				window.rslIeData.i18n &&
				window.rslIeData.i18n.copied
					? window.rslIeData.i18n.copied
					: 'Copied!' );

			const original = $btn.html();

			const onSuccess = function () {
				$btn.html( copiedLabel );
				$btn.addClass( 'rsl-ie-copied' );
				setTimeout( function () {
					$btn.html( original );
					$btn.removeClass( 'rsl-ie-copied' );
				}, 2000 );
			};

			const fallbackCopy = function ( text, callback ) {
				const ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.style.cssText =
					'position:fixed;top:-9999px;left:-9999px;opacity:0;';
				document.body.appendChild( ta );
				ta.focus();
				ta.select();
				try {
					document.execCommand( 'copy' );
					if ( callback ) {
						callback();
					}
				} catch ( err ) {
					// Ignore.
				}
				document.body.removeChild( ta );
			};

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard
					.writeText( promoCode )
					.then( onSuccess )
					.catch( function () {
						fallbackCopy( promoCode, onSuccess );
					} );
			} else {
				fallbackCopy( promoCode, onSuccess );
			}
		} );
	},
};

export default WelcomeModule;
