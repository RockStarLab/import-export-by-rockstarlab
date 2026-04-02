<?php
/**
 * Export Steps Indicator
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Steps Indicator -->
<div class="aie-steps-indicator">
	<div class="aie-step-indicator active" data-step="1">
		<div class="aie-step-number">1</div>
		<div class="aie-step-label"><?php esc_html_e( 'Content Type', 'amplified-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="2">
		<div class="aie-step-number">2</div>
		<div class="aie-step-label"><?php esc_html_e( 'Filters', 'amplified-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="3">
		<div class="aie-step-number">3</div>
		<div class="aie-step-label"><?php esc_html_e( 'Fields', 'amplified-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="4">
		<div class="aie-step-number">4</div>
		<div class="aie-step-label"><?php esc_html_e( 'Format', 'amplified-import-export' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="5">
		<div class="aie-step-number">5</div>
		<div class="aie-step-label"><?php esc_html_e( 'Export', 'amplified-import-export' ); ?></div>
	</div>
</div>
