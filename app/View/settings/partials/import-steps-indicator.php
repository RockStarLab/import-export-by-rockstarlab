<?php
/**
 * Import Steps Indicator
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Progress Steps Indicator -->
<div class="aie-steps-indicator">
	<div class="aie-step-indicator active" data-step="1">
		<div class="aie-step-number">1</div>
		<div class="aie-step-label"><?php esc_html_e( 'Content Type', 'advanced-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="2">
		<div class="aie-step-number">2</div>
		<div class="aie-step-label"><?php esc_html_e( 'Upload', 'advanced-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="3">
		<div class="aie-step-number">3</div>
		<div class="aie-step-label"><?php esc_html_e( 'Preview', 'advanced-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="4">
		<div class="aie-step-number">4</div>
		<div class="aie-step-label"><?php esc_html_e( 'Mapping', 'advanced-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="5">
		<div class="aie-step-number">5</div>
		<div class="aie-step-label"><?php esc_html_e( 'Options', 'advanced-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="6">
		<div class="aie-step-number">6</div>
		<div class="aie-step-label"><?php esc_html_e( 'Import', 'advanced-import-export' ); ?></div>
	</div>
</div>
