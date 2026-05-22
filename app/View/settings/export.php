<?php
/**
 * Export Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// Check if resuming a job - hide steps initially to prevent flash
$rsl_ie_resume_job_id = isset( $_GET['resume_job'] ) ? intval( $_GET['resume_job'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request().
?>

<div id="rsl-ie-export" class="import-export-by-rockstarlab wrap<?php echo $rsl_ie_resume_job_id ? ' rsl-ie-resuming-job' : ''; ?>">
	<h1><?php esc_html_e( 'Export Data', 'import-export-by-rockstarlab' ); ?></h1>

	<div class="rsl-ie-export-wizard">

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
