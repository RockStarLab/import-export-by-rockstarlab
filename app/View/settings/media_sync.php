<?php
/**
 * Media Sync Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="rsl-ie-media-sync" class="import-export-by-rockstarlab wrap">
<h1><?php esc_html_e( 'Media Sync', 'import-export-by-rockstarlab' ); ?></h1>
<p class="description">
<?php esc_html_e( 'Synchronize media files from server folders to WordPress Media Library. Helpful to migrate Media Library from non-WP sites and keeping previous Media Library structure.', 'import-export-by-rockstarlab' ); ?>
</p>

<div class="rsl-ie-media-sync-container">

<?php
	require_once __DIR__ . '/partials/media-sync-step-1.php';
	require_once __DIR__ . '/partials/media-sync-step-2.php';
	require_once __DIR__ . '/partials/media-sync-step-3.php';
	require_once __DIR__ . '/partials/media-sync-completion.php';
?>

</div>

<?php require_once __DIR__ . '/partials/media-sync-folder-browser.php'; ?>
</div>
