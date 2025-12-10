<?php
/**
 * Content Updater Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-content-updater" class="wp-advanced-import-export wrap">
	<h1><?php esc_html_e( 'Content Updater', 'wp-advanced-import-export' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Bulk update your content by applying custom functions to selected fields', 'wp-advanced-import-export' ); ?>
	</p>

	<div class="aie-updater-wizard">
		<?php
		require_once __DIR__ . '/partials/updater-step-1.php';
		require_once __DIR__ . '/partials/updater-step-2.php';
		require_once __DIR__ . '/partials/updater-step-3.php';
		require_once __DIR__ . '/partials/updater-step-4.php';
		require_once __DIR__ . '/partials/updater-steps-indicator.php';
		require_once __DIR__ . '/partials/updater-field-functions-modal.php';
		?>
	</div>
</div>
