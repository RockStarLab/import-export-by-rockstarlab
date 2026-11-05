<?php
/**
 * Import Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

// This query only selects a view; it does not execute an import.
$rsl_ie_media_sync_import = isset( $_GET['import_mode'] ) && is_string( $_GET['import_mode'] ) && 'media-sync' === $_GET['import_mode']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
if ( $rsl_ie_media_sync_import ) {
	require __DIR__ . '/media_sync.php';
	return;
}
$rsl_ie_ai_url_import = isset( $_GET['import_mode'] ) && is_string( $_GET['import_mode'] ) && 'ai-url' === $_GET['import_mode']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
if ( $rsl_ie_ai_url_import ) {
	require __DIR__ . '/ai_url_importer.php';
	return;
}
?>

<div id="rsl-ie-import" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Import Data', 'import-export-by-rockstarlab' ); ?></h1>

	<div class="rsl-ie-import-wizard">

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
