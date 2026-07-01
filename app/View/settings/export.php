<?php
/**
 * Export Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_field_transformations_enabled = \RockStarLab\ImportExport\Helper\Field_Transformation_Bridge::is_enabled();
?>

<div id="rsl-ie-export" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Export Data', 'import-export-by-rockstarlab' ); ?></h1>

	<div class="rsl-ie-export-wizard">

<?php
	require_once __DIR__ . '/partials/export-step-1.php';
	require_once __DIR__ . '/partials/export-step-2.php';
	require_once __DIR__ . '/partials/export-step-3.php';
	require_once __DIR__ . '/partials/export-step-4.php';
	require_once __DIR__ . '/partials/export-step-5.php';
	require_once __DIR__ . '/partials/export-steps-indicator.php';
if ( $rsl_ie_field_transformations_enabled ) {
	require_once __DIR__ . '/partials/export-field-functions-modal.php';
}
?>

</div>
</div>
