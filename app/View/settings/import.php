<?php
/**
 * Import Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-import" class="wp-advanced-import-export wrap">
	<h1><?php esc_html_e( 'Import Data', 'wp-advanced-import-export' ); ?></h1>

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
