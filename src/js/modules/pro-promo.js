import Utils from './utils';

const ProPromoModule = {
	init() {
		this.bindDismissPromoCard();
	},

	bindDismissPromoCard() {
		const $ = jQuery;

		$( document ).on(
			'click',
			'.rsl-ie-pro-addon-hide[data-context]',
			function ( e ) {
				e.preventDefault();

				jQuery( this ).closest( '.rsl-ie-pro-addon-card' ).hide();
			}
		);

		$( document ).on(
			'click',
			'.rsl-ie-pro-addon-dismiss-forever[data-context]',
			function ( e ) {
				e.preventDefault();

				const $btn = $( this );
				const context = ( $btn.data( 'context' ) || '' ).toString();

				$btn.prop( 'disabled', true );

				Utils.ajax( 'dismiss_pro_promo', { context } )
					.then( function () {
						$btn.closest( '.rsl-ie-pro-addon-card' ).hide();
					} )
					.catch( function () {
						$btn.prop( 'disabled', false );
					} );
			}
		);
	},
};

export default ProPromoModule;
