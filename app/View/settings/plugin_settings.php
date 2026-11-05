<?php
/**
 * General Plugin Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

use RockStarLab\ImportExport\Helper\Admin_Menu_Settings;
use RockStarLab\ImportExport\Helper\Pro_Addon;

defined( 'ABSPATH' ) || exit;

$rsl_ie_admin_menu_settings = Admin_Menu_Settings::get_settings();
$rsl_ie_admin_menu_items    = Admin_Menu_Settings::get_menu_items();
$rsl_ie_notice_nonce        = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified before reading the notice flag.
$rsl_ie_notice_verified     = wp_verify_nonce( $rsl_ie_notice_nonce, 'rsl_ie_plugin_settings_notice' );
?>

<div id="rsl-ie-plugin-settings" class="wrap">
	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Plugin Options', 'import-export-by-rockstarlab' ); ?>
	</h1>
	<?php
	$rsl_ie_options_active_tab = 'settings';
	require RSL_IE_PATH . 'app/View/settings/partials/plugin-options-tabs.php';
	?>

	<?php if ( $rsl_ie_notice_verified && isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'import-export-by-rockstarlab' ); ?></p></div>
	<?php endif; ?>

	<div class="rsl-ie-settings-container">
		<div class="rsl-ie-settings-section">
			<div class="rsl-ie-settings-section-header">
				<h2><?php esc_html_e( 'Admin menu', 'import-export-by-rockstarlab' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Customize the plugin title and choose which submenu links are visible. Hidden pages remain available by direct URL.', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<div class="rsl-ie-settings-section-body">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rsl_ie_save_admin_menu_settings">
					<?php wp_nonce_field( 'rsl_ie_save_admin_menu_settings' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="rsl-ie-menu-title"><?php esc_html_e( 'Plugin menu title', 'import-export-by-rockstarlab' ); ?></label></th>
							<td>
								<input id="rsl-ie-menu-title" name="menu_title" type="text" class="regular-text" maxlength="100" value="<?php echo esc_attr( $rsl_ie_admin_menu_settings['menu_title'] ); ?>">
								<p class="description"><?php esc_html_e( 'Replaces “Import Export by RockStarLab” in the WordPress admin menu.', 'import-export-by-rockstarlab' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Visible submenu items', 'import-export-by-rockstarlab' ); ?></th>
							<td>
								<fieldset>
										<legend class="screen-reader-text"><span><?php esc_html_e( 'Visible submenu items', 'import-export-by-rockstarlab' ); ?></span></legend>
										<?php foreach ( $rsl_ie_admin_menu_items as $rsl_ie_item_key => $rsl_ie_item_label ) : ?>
											<?php if ( in_array( $rsl_ie_item_key, [ 'functions', 'content_updater' ], true ) && ! Pro_Addon::is_pro_active() ) : ?>
												<?php continue; ?>
											<?php endif; ?>
										<label class="rsl-ie-menu-visibility-option">
											<input type="checkbox" name="visible_items[]" value="<?php echo esc_attr( $rsl_ie_item_key ); ?>" <?php checked( ! empty( $rsl_ie_admin_menu_settings['visible_items'][ $rsl_ie_item_key ] ) ); ?>>
											<?php echo esc_html( $rsl_ie_item_label ); ?>
										</label><br>
									<?php endforeach; ?>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Plugin Options always remains visible so these settings cannot lock you out.', 'import-export-by-rockstarlab' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'import-export-by-rockstarlab' ) ); ?>
				</form>
			</div>
		</div>

		<div class="rsl-ie-settings-section">
			<div class="rsl-ie-settings-section-header">
				<h2><?php esc_html_e( 'Hierarchical content lists', 'import-export-by-rockstarlab' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Add a quick filter to Pages and hierarchical custom post types.', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<div class="rsl-ie-settings-section-body">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rsl_ie_save_content_list_settings">
					<?php wp_nonce_field( 'rsl_ie_save_content_list_settings' ); ?>

					<label for="rsl-ie-show-tree-action">
						<input id="rsl-ie-show-tree-action" name="show_tree_action" type="checkbox" value="1" <?php checked( ! empty( $rsl_ie_admin_menu_settings['show_tree_action'] ) ); ?>>
						<strong><?php esc_html_e( 'Show the “Show tree” row action', 'import-export-by-rockstarlab' ); ?></strong>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, hovering a Page or hierarchical custom post type row shows “Show tree” next to Edit. Clicking it filters the table to that item and all descendants at every depth.', 'import-export-by-rockstarlab' ); ?>
					</p>

					<?php submit_button( __( 'Save Settings', 'import-export-by-rockstarlab' ) ); ?>
				</form>
			</div>
		</div>
	</div>
</div>
