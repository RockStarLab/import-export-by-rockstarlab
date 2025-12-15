<?php
/**
 * Export Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Check if resuming a job - hide steps initially to prevent flash
$resume_job_id = isset( $_GET['resume_job'] ) ? intval( $_GET['resume_job'] ) : 0;
?>

<div id="wp-aie-export" class="wp-advanced-import-export wrap">
<h1><?php esc_html_e( 'Export Data', 'wp-advanced-import-export' ); ?></h1>

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
