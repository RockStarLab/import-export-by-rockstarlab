<?php
/**
 * Content Updater Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// Check if premium is active
$is_premium = function_exists( 'rsl_ie_fs' ) && rsl_ie_fs()->can_use_premium_code();

$activate_license_url = add_query_arg( 'rsl-ie-activate-license', '1', admin_url( 'plugins.php' ) );
$activate_license_url = wp_nonce_url( $activate_license_url, 'rsl_ie_activate_license', '_wpnonce' );
?>

<div id="rsl-ie-content-updater" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Content Updater', 'import-export-by-rockstarlab' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Bulk update your content by applying custom functions to selected fields', 'import-export-by-rockstarlab' ); ?>
	</p>

	<?php if ( ! $is_premium ) : ?>
	<!-- Premium Notice -->
	<div class="aie-premium-notice">
		<div class="aie-premium-notice-icon">
			<span class="dashicons dashicons-lock"></span>
		</div>
		<div class="aie-premium-notice-content">
			<h3><?php esc_html_e( 'Premium Feature', 'import-export-by-rockstarlab' ); ?></h3>			<p><?php esc_html_e( 'Content Updater with advanced content types is a premium feature. Upgrade to unlock the ability to bulk update Pages, Custom Post Types, Media, Users, Taxonomy Terms, WooCommerce content and even Any Database Table.', 'import-export-by-rockstarlab' ); ?></p>
				<?php if ( function_exists( 'rsl_ie_fs' ) ) : ?>
					<a href="<?php echo esc_url( rsl_ie_fs()->get_upgrade_url() ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade to Premium', 'import-export-by-rockstarlab' ); ?>
				</a>
				<a href="<?php echo esc_url( $activate_license_url ); ?>" class="button button-secondary button-large">
					<span class="dashicons dashicons-admin-network"></span>
					<?php esc_html_e( 'Activate License', 'import-export-by-rockstarlab' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="aie-updater-wizard">
		<?php
		require_once __DIR__ . '/partials/updater-step-1.php';
		require_once __DIR__ . '/partials/updater-step-2.php';
		require_once __DIR__ . '/partials/updater-step-3.php';
		require_once __DIR__ . '/partials/updater-step-4.php';
		require_once __DIR__ . '/partials/updater-step-5.php';
		require_once __DIR__ . '/partials/updater-steps-indicator.php';
		require_once __DIR__ . '/partials/updater-field-functions-modal.php';
		?>
	</div>
</div>
