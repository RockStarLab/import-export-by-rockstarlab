<?php
/**
 * Export Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-export" class="wp-advanced-import-export wrap">
<h1><?php esc_html_e( 'Export Data', 'wp-aie' ); ?></h1>

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
