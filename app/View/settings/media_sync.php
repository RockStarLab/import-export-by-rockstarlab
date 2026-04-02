<?php
/**
 * Media Sync Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-media-sync" class="advanced-import-export wrap">
<h1><?php esc_html_e( 'Media Sync', 'advanced-import-export' ); ?></h1>
<p class="description">
<?php esc_html_e( 'Synchronize media files from server folders to WordPress Media Library. Helpful to migrate Media Library from non-WP sites and keeping previous Media Library structure.', 'advanced-import-export' ); ?>
</p>

<div class="aie-media-sync-container">

<?php
	require_once __DIR__ . '/partials/media-sync-step-1.php';
	require_once __DIR__ . '/partials/media-sync-step-2.php';
	require_once __DIR__ . '/partials/media-sync-step-3.php';
	require_once __DIR__ . '/partials/media-sync-completion.php';
?>

</div>

<?php require_once __DIR__ . '/partials/media-sync-folder-browser.php'; ?>
</div>
