/**
 * Plugin Options Module
 *
 * Handles the Plugin Options settings page interactions.
 */

const PluginOptionsModule = {
	/**
	 * Initialize module.
	 */
	init() {
		if ( ! jQuery( '#rsl-ie-plugin-options' ).length ) {
			return;
		}

		this.bindEvents();
	},

	/**
	 * Bind event handlers.
	 */
	bindEvents() {
		const $ = jQuery;

		// Toggle password visibility.
		$( document ).on( 'click', '.aie-toggle-password', function () {
			const targetId = $( this ).data( 'target' );
			const $input = $( `#${ targetId }` );
			const $icon = $( this ).find( '.dashicons' );

			if ( $input.attr( 'type' ) === 'password' ) {
				$input.attr( 'type', 'text' );
				$icon
					.removeClass( 'dashicons-visibility' )
					.addClass( 'dashicons-hidden' );
			} else {
				$input.attr( 'type', 'password' );
				$icon
					.removeClass( 'dashicons-hidden' )
					.addClass( 'dashicons-visibility' );
			}
		} );

		// Save settings.
		$( document ).on( 'submit', '#aie-settings-form', function ( e ) {
			e.preventDefault();

			const $form = $( this );
			const $submitBtn = $form.find( '.aie-save-settings' );
			const $status = $form.find( '.aie-settings-status' );

			$submitBtn.prop( 'disabled', true );
			$status.html( '<span class="spinner is-active"></span>' );

			$.ajax( {
				url: window.aieData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_settings_save',
					nonce: window.aieData.nonce,
					openai_api_key: $( '#aie-openai-api-key' ).val(),
				},
			} )
				.done( function ( response ) {
					if ( response && response.success ) {
						$status.html(
							'<span class="aie-success-message"><span class="dashicons dashicons-yes-alt"></span> ' +
								( response.data && response.data.message
									? response.data.message
									: window.aieData.i18n.saved ) +
								'</span>'
						);
						setTimeout( function () {
							location.reload();
						}, 1500 );
					} else {
						const message =
							( response && response.message ) ||
							( response && response.data ) ||
							window.aieData.i18n.errorOccurred;

						$status.html(
							'<span class="aie-error-message"><span class="dashicons dashicons-warning"></span> ' +
								message +
								'</span>'
						);
						$submitBtn.prop( 'disabled', false );
					}
				} )
				.fail( function () {
					$status.html(
						'<span class="aie-error-message"><span class="dashicons dashicons-warning"></span> ' +
							window.aieData.i18n.errorOccurred +
							'</span>'
					);
					$submitBtn.prop( 'disabled', false );
				} );
		} );

		// Test API connection (lightweight check: configured vs not configured).
		$( document ).on( 'click', '.aie-test-api-key', function () {
			const $btn = $( this );
			const $result = $( '#aie-api-test-result' );
			const apiKey = ( $( '#aie-openai-api-key' ).val() || '' ).trim();

			$btn.prop( 'disabled', true );
			$result
				.html(
					'<div class="aie-info-box"><span class="spinner is-active"></span> ' +
						window.aieData.i18n.testingConnection +
						'</div>'
				)
				.show();

			setTimeout( function () {
				if ( apiKey ) {
					$result.html(
						'<div class="aie-info-box aie-success"><span class="dashicons dashicons-yes-alt"></span> <strong>' +
							window.aieData.i18n.apiKeyConfiguredTitle +
							'</strong><br>' +
							window.aieData.i18n.apiKeyConfiguredDesc +
							'</div>'
					);
				} else {
					$result.html(
						'<div class="aie-info-box aie-error"><span class="dashicons dashicons-warning"></span> <strong>' +
							window.aieData.i18n.noApiKeyTitle +
							'</strong><br>' +
							window.aieData.i18n.noApiKeyDesc +
							'</div>'
					);
				}
				$btn.prop( 'disabled', false );
			}, 1000 );
		} );
	},
};

export default PluginOptionsModule;

