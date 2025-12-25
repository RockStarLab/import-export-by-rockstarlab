<?php
/**
 * Import Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Check if premium is active
$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
?>

<div id="wp-aie-import" class="wp-advanced-import-export wrap">
	<h1><?php esc_html_e( 'Import Data', 'wp-advanced-import-export' ); ?></h1>

	<?php if ( ! $is_premium ) : ?>
	<!-- Premium Notice -->
	<div class="aie-premium-notice">
		<div class="aie-premium-notice-icon">
			<span class="dashicons dashicons-lock"></span>
		</div>
		<div class="aie-premium-notice-content">
			<h3><?php esc_html_e( 'Premium Feature Available', 'wp-advanced-import-export' ); ?></h3>
			<p><?php esc_html_e( 'Import advanced content types with Premium version. Unlock the ability to import Custom Post Types, Media, Menus, Users, Comments, Taxonomy Terms, WooCommerce content and Any Database Table.', 'wp-advanced-import-export' ); ?></p>
			<?php if ( function_exists( 'waie_fs' ) ) : ?>
				<a href="<?php echo esc_url( waie_fs()->get_upgrade_url() ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Upgrade to Premium', 'wp-advanced-import-export' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="aie-import-wizard">

<?php
	require_once __DIR__ . '/partials/import-step-1.php';
	require_once __DIR__ . '/partials/import-step-2.php';
	require_once __DIR__ . '/partials/import-step-3.php';
	require_once __DIR__ . '/partials/import-step-4.php';
	require_once __DIR__ . '/partials/import-step-5.php';
	require_once __DIR__ . '/partials/import-step-6.php';
	require_once __DIR__ . '/partials/import-steps-indicator.php';
?>

	</div>
</div>
