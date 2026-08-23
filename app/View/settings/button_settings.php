<?php
/**
 * Export and sync button settings page.
 *
 * @package RockStarLab\ImportExport\View
 */

use RockStarLab\ImportExport\Helper\Button_Location_Settings;

defined( 'ABSPATH' ) || exit;

$rsl_ie_button_settings = Button_Location_Settings::get_settings();
$rsl_ie_button_groups   = Button_Location_Settings::get_location_groups();
$rsl_ie_notice_nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified before reading the notice flag.
$rsl_ie_notice_verified = wp_verify_nonce( $rsl_ie_notice_nonce, 'rsl_ie_plugin_settings_notice' );
?>

<div id="rsl-ie-button-settings" class="wrap">
	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Plugin Options', 'import-export-by-rockstarlab' ); ?>
	</h1>
	<?php
	$rsl_ie_options_active_tab = 'buttons';
	require RSL_IE_PATH . 'app/View/settings/partials/plugin-options-tabs.php';
	?>

	<?php if ( $rsl_ie_notice_verified && isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'import-export-by-rockstarlab' ); ?></p></div>
	<?php endif; ?>

	<div class="rsl-ie-settings-container">
		<div class="rsl-ie-settings-section">
			<div class="rsl-ie-settings-section-header">
				<h2><?php esc_html_e( 'Export and sync buttons', 'import-export-by-rockstarlab' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose where quick Export and Sync buttons should be available in the WordPress admin. All locations are enabled by default for existing installs until these settings are saved.', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<div class="rsl-ie-settings-section-body">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rsl_ie_save_button_location_settings">
					<?php wp_nonce_field( 'rsl_ie_save_button_location_settings' ); ?>

					<div class="rsl-ie-button-location-settings">
						<?php foreach ( $rsl_ie_button_groups as $rsl_ie_group ) : ?>
							<div class="rsl-ie-button-location-group">
								<div class="rsl-ie-button-location-group-header">
									<strong><?php echo esc_html( $rsl_ie_group['label'] ); ?></strong>
									<p class="description"><?php echo esc_html( $rsl_ie_group['description'] ); ?></p>
								</div>

								<div class="rsl-ie-button-location-table">
									<div class="rsl-ie-button-location-table-head">
										<span><?php esc_html_e( 'Location', 'import-export-by-rockstarlab' ); ?></span>
										<label class="rsl-ie-button-location-toggle-all">
											<input type="checkbox" data-rsl-ie-toggle-group="export_button_locations[]">
											<span><?php esc_html_e( 'Export', 'import-export-by-rockstarlab' ); ?></span>
										</label>
										<label class="rsl-ie-button-location-toggle-all">
											<input type="checkbox" data-rsl-ie-toggle-group="sync_button_locations[]">
											<span><?php esc_html_e( 'Sync', 'import-export-by-rockstarlab' ); ?></span>
										</label>
									</div>

									<?php foreach ( $rsl_ie_group['items'] as $rsl_ie_location_id => $rsl_ie_location ) : ?>
										<div class="rsl-ie-button-location-row">
											<div class="rsl-ie-button-location-label">
												<strong><?php echo esc_html( $rsl_ie_location['label'] ); ?></strong>
												<small><?php echo esc_html( $rsl_ie_location['description'] ); ?></small>
											</div>
											<label class="rsl-ie-button-location-check">
												<span class="screen-reader-text">
													<?php
													printf(
														/* translators: %s: admin location label. */
														esc_html__( 'Show Export button on %s', 'import-export-by-rockstarlab' ),
														esc_html( $rsl_ie_location['label'] )
													);
													?>
												</span>
												<input type="checkbox" name="export_button_locations[]" value="<?php echo esc_attr( $rsl_ie_location_id ); ?>" <?php checked( ! empty( $rsl_ie_button_settings['export_button_locations'][ $rsl_ie_location_id ] ) ); ?>>
											</label>
											<label class="rsl-ie-button-location-check">
												<span class="screen-reader-text">
													<?php
													printf(
														/* translators: %s: admin location label. */
														esc_html__( 'Show Sync button on %s', 'import-export-by-rockstarlab' ),
														esc_html( $rsl_ie_location['label'] )
													);
													?>
												</span>
												<input type="checkbox" name="sync_button_locations[]" value="<?php echo esc_attr( $rsl_ie_location_id ); ?>" <?php checked( ! empty( $rsl_ie_button_settings['sync_button_locations'][ $rsl_ie_location_id ] ) ); ?>>
											</label>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<?php submit_button( __( 'Save Settings', 'import-export-by-rockstarlab' ) ); ?>
				</form>
			</div>
		</div>
	</div>
</div>
