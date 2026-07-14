<?php
/**
 * Import Steps Indicator
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Progress Steps Indicator -->
<div class="rsl-ie-steps-indicator">
	<div class="rsl-ie-step-indicator active" data-step="1">
		<div class="rsl-ie-step-number">1</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Content Type', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="2">
		<div class="rsl-ie-step-number">2</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Upload', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="3">
		<div class="rsl-ie-step-number">3</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Preview', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="4">
		<div class="rsl-ie-step-number">4</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Mapping', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="5">
		<div class="rsl-ie-step-number">5</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Options', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="6">
		<div class="rsl-ie-step-number">6</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Import', 'import-export-by-rockstarlab' ); ?></div>
	</div>
</div>
