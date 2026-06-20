/**
 * Plugin Options Module
 *
 * Handles the Plugin Options settings page interactions.
 */

import Utils from './utils';

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
		$( document ).on( 'click', '.rsl-ie-toggle-password', function () {
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
		$( document ).on( 'submit', '#rsl-ie-settings-form', function ( e ) {
			e.preventDefault();

			const $form = $( this );
			const $submitBtn = $form.find( '.rsl-ie-save-settings' );
			const $status = $form.find( '.rsl-ie-settings-status' );

			$submitBtn.prop( 'disabled', true );
			$status.html( '<span class="spinner is-active"></span>' );

			$.ajax( {
				url: window.rslIeData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rsl_ie_settings_save',
					nonce: window.rslIeData.nonce,
					openai_api_key: $( '#rsl-ie-openai-api-key' ).val(),
				},
			} )
				.done( function ( response ) {
					if ( response && response.success ) {
						$status.html(
							'<span class="rsl-ie-success-message"><span class="dashicons dashicons-yes-alt"></span> ' +
								( response.data && response.data.message
									? response.data.message
									: window.rslIeData.i18n.saved ) +
								'</span>'
						);
						setTimeout( function () {
							location.reload();
						}, 1500 );
					} else {
						const message =
							( response && response.message ) ||
							( response && response.data ) ||
							window.rslIeData.i18n.errorOccurred;

						$status.html(
							'<span class="rsl-ie-error-message"><span class="dashicons dashicons-warning"></span> ' +
								message +
								'</span>'
						);
						$submitBtn.prop( 'disabled', false );
					}
				} )
				.fail( function () {
					$status.html(
						'<span class="rsl-ie-error-message"><span class="dashicons dashicons-warning"></span> ' +
							window.rslIeData.i18n.errorOccurred +
							'</span>'
					);
					$submitBtn.prop( 'disabled', false );
				} );
		} );

		// Test the configured credential against the OpenAI API.
		$( document ).on( 'click', '.rsl-ie-test-api-key', function () {
			const $btn = $( this );
			const $result = $( '#rsl-ie-api-test-result' );
			const apiKey = ( $( '#rsl-ie-openai-api-key' ).val() || '' ).trim();

			$btn.prop( 'disabled', true );
			$result
				.html(
					'<div class="rsl-ie-info-box"><span class="spinner is-active"></span> ' +
						window.rslIeData.i18n.testingConnection +
						'</div>'
				)
				.show();

			Utils.ajax( 'test_openai_connection', { api_key: apiKey } )
				.then( function () {
					$result
						.empty()
						.append(
							$( '<div>' )
								.addClass( 'rsl-ie-info-box rsl-ie-success' )
								.text(
									window.rslIeData.i18n.connectionSuccessful
								)
						);
				} )
				.catch( function ( error ) {
					$result.empty().append(
						$( '<div>' )
							.addClass( 'rsl-ie-info-box rsl-ie-error' )
							.text(
								String(
									error || window.rslIeData.i18n.errorOccurred
								)
							)
					);
				} )
				.then( function () {
					$btn.prop( 'disabled', false );
				} );
		} );
	},
};

export default PluginOptionsModule;
