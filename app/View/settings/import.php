<?php
/**
 * Import Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
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
