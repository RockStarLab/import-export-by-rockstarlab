<?php
/**
 * Content Updater Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="rsl-ie-content-updater" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Content Updater', 'import-export-by-rockstarlab' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Bulk update your content by applying custom functions to selected fields', 'import-export-by-rockstarlab' ); ?>
	</p>

	<div class="aie-updater-wizard">
		<?php
		require_once __DIR__ . '/partials/updater-step-1.php';
		require_once __DIR__ . '/partials/updater-step-2.php';
		require_once __DIR__ . '/partials/updater-step-3.php';
		require_once __DIR__ . '/partials/updater-step-4.php';
		require_once __DIR__ . '/partials/updater-step-5.php';
		require_once __DIR__ . '/partials/updater-steps-indicator.php';
		require_once __DIR__ . '/partials/updater-field-functions-modal.php';
		?>
	</div>
</div>
