<?php
/**
 * Content Updater Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Check if premium is active
$is_premium = function_exists( 'aie_fs' ) && aie_fs()->can_use_premium_code();
?>

<div id="wp-aie-content-updater" class="amplified-import-export wrap">
	<h1><?php esc_html_e( 'Content Updater', 'amplified-import-export' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Bulk update your content by applying custom functions to selected fields', 'amplified-import-export' ); ?>
	</p>

	<?php if ( ! $is_premium ) : ?>
	<!-- Premium Notice -->
	<div class="aie-premium-notice">
		<div class="aie-premium-notice-icon">
			<span class="dashicons dashicons-lock"></span>
		</div>
		<div class="aie-premium-notice-content">
			<h3><?php esc_html_e( 'Premium Feature', 'amplified-import-export' ); ?></h3>			<p><?php esc_html_e( 'Content Updater with advanced content types is a premium feature. Upgrade to unlock the ability to bulk update Pages, Custom Post Types, Media, Users, Taxonomy Terms, WooCommerce content and even Any Database Table.', 'amplified-import-export' ); ?></p>
				<?php if ( function_exists( 'aie_fs' ) ) : ?>
					<a href="<?php echo esc_url( aie_fs()->get_upgrade_url() ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade to Premium', 'amplified-import-export' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'plugins.php?aie-activate-license=1' ) ); ?>" class="button button-secondary button-large">
					<span class="dashicons dashicons-admin-network"></span>
					<?php esc_html_e( 'Activate License', 'amplified-import-export' ); ?>
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
