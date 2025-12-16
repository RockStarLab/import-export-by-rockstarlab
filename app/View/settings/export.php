<?php
/**
 * Export Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Check if resuming a job - hide steps initially to prevent flash
$resume_job_id = isset( $_GET['resume_job'] ) ? intval( $_GET['resume_job'] ) : 0;

// Check if premium is active
$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
?>

<div id="wp-aie-export" class="wp-advanced-import-export wrap">
<h1><?php esc_html_e( 'Export Data', 'wp-advanced-import-export' ); ?></h1>

<?php if ( ! $is_premium ) : ?>
<!-- Premium Notice -->
<div class="aie-premium-notice">
	<div class="aie-premium-notice-icon">
		<span class="dashicons dashicons-lock"></span>
	</div>
	<div class="aie-premium-notice-content">
		<h3><?php esc_html_e( 'Premium Feature Available', 'wp-advanced-import-export' ); ?></h3>
		<p><?php esc_html_e( 'Export advanced content types with Premium version. Unlock the ability to export Custom Post Types, Media, Menus, Users, Comments, Block Theme Settings, Taxonomy Terms, WooCommerce content and Any Database Table.', 'wp-advanced-import-export' ); ?></p>
		<?php if ( function_exists( 'waie_fs' ) ) : ?>
			<a href="<?php echo esc_url( waie_fs()->get_upgrade_url() ); ?>" class="button button-primary button-large">
				<span class="dashicons dashicons-star-filled"></span>
				<?php esc_html_e( 'Upgrade to Premium', 'wp-advanced-import-export' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<div class="aie-export-wizard">

<?php if ( $resume_job_id ) : ?>
	<style>
		/* Hide all steps except step 5 when resuming */
		.aie-step-1, .aie-step-2, .aie-step-3, .aie-step-4 {
			display: none !important;
		}
		.aie-step-5 {
			display: block !important;
		}
	</style>
<?php endif; ?>

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
