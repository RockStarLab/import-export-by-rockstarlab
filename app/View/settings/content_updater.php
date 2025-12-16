<?php
/**
 * Content Updater Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Check if premium is active
$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
?>

<div id="wp-aie-content-updater" class="wp-advanced-import-export wrap">
	<h1><?php esc_html_e( 'Content Updater', 'wp-advanced-import-export' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Bulk update your content by applying custom functions to selected fields', 'wp-advanced-import-export' ); ?>
	</p>

	<?php if ( ! $is_premium ) : ?>
	<!-- Premium Notice -->
	<div class="aie-premium-notice">
		<div class="aie-premium-notice-icon">
			<span class="dashicons dashicons-lock"></span>
		</div>
		<div class="aie-premium-notice-content">
			<h3><?php esc_html_e( 'Premium Feature', 'wp-advanced-import-export' ); ?></h3>
			<p><?php esc_html_e( 'Content Updater with advanced content types is a premium feature. Upgrade to unlock the ability to bulk update Pages, Custom Post Types, Media, Users, Taxonomy Terms, WooCommerce content and even Any Database Table.', 'wp-advanced-import-export' ); ?></p>
			<?php if ( function_exists( 'waie_fs' ) ) : ?>
				<a href="<?php echo esc_url( waie_fs()->get_upgrade_url() ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade to Premium', 'wp-advanced-import-export' ); ?>
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
