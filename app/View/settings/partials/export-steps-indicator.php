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
		<div class="aie-step-label"><?php esc_html_e( 'Content Type', 'wp-aie' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="2">
		<div class="aie-step-number">2</div>
		<div class="aie-step-label"><?php esc_html_e( 'Filters', 'wp-aie' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="3">
		<div class="aie-step-number">3</div>
		<div class="aie-step-label"><?php esc_html_e( 'Fields', 'wp-aie' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="4">
		<div class="aie-step-number">4</div>
		<div class="aie-step-label"><?php esc_html_e( 'Format', 'wp-aie' ); ?></div>
	</div>
	<div class="aie-step-indicator" data-step="5">
		<div class="aie-step-number">5</div>
		<div class="aie-step-label"><?php esc_html_e( 'Export', 'wp-aie' ); ?></div>
	</div>
</div>
