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
		if (
			! jQuery( '#rsl-ie-plugin-options' ).length &&
			! jQuery( '#rsl-ie-button-settings' ).length
		) {
			return;
		}

		this.bindEvents();
		this.initButtonLocationToggles();
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

	/**
	 * Initialize group-level toggle controls for the button settings page.
	 */
	initButtonLocationToggles() {
		const $ = jQuery;
		const $page = $( '#rsl-ie-button-settings' );

		if ( ! $page.length ) {
			return;
		}

		const updateToggleState = function ( $group, inputName ) {
			const $items = $group.find(
				`input[type="checkbox"][name="${ inputName }"]`
			);
			const $toggle = $group.find(
				`input[type="checkbox"][data-rsl-ie-toggle-group="${ inputName }"]`
			);

			if ( ! $items.length || ! $toggle.length ) {
				return;
			}

			const checkedCount = $items.filter( ':checked' ).length;

			$toggle
				.prop( 'checked', checkedCount === $items.length )
				.prop(
					'indeterminate',
					checkedCount > 0 && checkedCount < $items.length
				);
		};

		const updateAllToggleStates = function () {
			$page.find( '.rsl-ie-button-location-group' ).each( function () {
				const $group = $( this );
				updateToggleState( $group, 'export_button_locations[]' );
				updateToggleState( $group, 'sync_button_locations[]' );
			} );
		};

		updateAllToggleStates();

		$( document ).on(
			'change',
			'#rsl-ie-button-settings [data-rsl-ie-toggle-group]',
			function () {
				const $toggle = $( this );
				const inputName = $toggle.attr( 'data-rsl-ie-toggle-group' );
				const $group = $toggle.closest(
					'.rsl-ie-button-location-group'
				);

				$group
					.find( `input[type="checkbox"][name="${ inputName }"]` )
					.prop( 'checked', $toggle.prop( 'checked' ) );

				updateToggleState( $group, inputName );
			}
		);

		$( document ).on(
			'change',
			'#rsl-ie-button-settings input[name="export_button_locations[]"], #rsl-ie-button-settings input[name="sync_button_locations[]"]',
			function () {
				const $item = $( this );
				updateToggleState(
					$item.closest( '.rsl-ie-button-location-group' ),
					$item.attr( 'name' )
				);
			}
		);
	},
};

export default PluginOptionsModule;
