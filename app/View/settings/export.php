<?php
/**
 * Export Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// Check if resuming a job - hide steps initially to prevent flash
$resume_job_id = isset( $_GET['resume_job'] ) ? intval( $_GET['resume_job'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request().

// Check if premium is active
$is_premium = function_exists( 'rsl_ie_fs' ) && rsl_ie_fs()->can_use_premium_code();

$activate_license_url = add_query_arg( 'rsl-ie-activate-license', '1', admin_url( 'plugins.php' ) );
$activate_license_url = wp_nonce_url( $activate_license_url, 'rsl_ie_activate_license', '_wpnonce' );
?>

<div id="rsl-ie-export" class="import-export-by-rockstarlab wrap<?php echo $resume_job_id ? ' aie-resuming-job' : ''; ?>">
	<h1><?php esc_html_e( 'Export Data', 'import-export-by-rockstarlab' ); ?></h1>

	<?php if ( ! $is_premium ) : ?>
	<!-- Premium Notice -->
	<div class="aie-premium-notice">
		<div class="aie-premium-notice-icon">
			<span class="dashicons dashicons-lock"></span>
		</div>
		<div class="aie-premium-notice-content">
			<h3><?php esc_html_e( 'Premium Feature Available', 'import-export-by-rockstarlab' ); ?></h3>
			<p><?php esc_html_e( 'Export advanced content types with Premium version. Unlock the ability to export Custom Post Types, Media, Menus, Users, Comments, Taxonomy Terms, WooCommerce content and Any Database Table.', 'import-export-by-rockstarlab' ); ?></p>
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

	<div class="aie-export-wizard">

<?php
	require_once __DIR__ . '/partials/export-step-1.php';
	require_once __DIR__ . '/partials/export-step-2.php';
	require_once __DIR__ . '/partials/export-step-3.php';
	require_once __DIR__ . '/partials/export-step-4.php';
	require_once __DIR__ . '/partials/export-step-5.php';
	require_once __DIR__ . '/partials/export-steps-indicator.php';
	require_once __DIR__ . '/partials/export-field-functions-modal.php';
?>

</div>
</div>
