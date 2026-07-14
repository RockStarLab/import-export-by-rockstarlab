<?php
/**
 * Export Steps Indicator
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Steps Indicator -->
<div class="rsl-ie-steps-indicator">
	<div class="rsl-ie-step-indicator active" data-step="1">
		<div class="rsl-ie-step-number">1</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Content Type', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="2">
		<div class="rsl-ie-step-number">2</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Filters', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="3">
		<div class="rsl-ie-step-number">3</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Fields', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="4">
		<div class="rsl-ie-step-number">4</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Format', 'import-export-by-rockstarlab' ); ?></div>
	</div>
	<div class="rsl-ie-step-indicator" data-step="5">
		<div class="rsl-ie-step-number">5</div>
		<div class="rsl-ie-step-label"><?php esc_html_e( 'Export', 'import-export-by-rockstarlab' ); ?></div>
	</div>
</div>
