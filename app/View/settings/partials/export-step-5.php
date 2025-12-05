<?php
/**
 * Export Step 5: Export Progress
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 5: Export Progress -->
<div class="aie-step aie-step-5" data-step="5">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Export in Progress', 'wp-aie' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Please wait while your data is being exported', 'wp-aie' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-progress-container">
			<div class="aie-progress-bar">
				<div class="aie-progress-bar-fill" style="width: 0%;"></div>
			</div>
			<div class="aie-progress-stats">
				<div class="aie-progress-percentage">0%</div>
				<div class="aie-progress-details">
					<span class="aie-processed">0</span> / <span class="aie-total">0</span>
					<?php esc_html_e( 'items', 'wp-aie' ); ?>
				</div>
			</div>
			
			<div class="aie-progress-estimates">
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Elapsed:', 'wp-aie' ); ?></span>
					<span class="value aie-elapsed-time">0s</span>
				</div>
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Remaining:', 'wp-aie' ); ?></span>
					<span class="value aie-remaining-time">-</span>
				</div>
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Speed:', 'wp-aie' ); ?></span>
					<span class="value aie-items-per-second">-</span>
				</div>
			</div>
		</div>

		<div class="aie-export-results" style="display:none;">
			<div class="notice notice-success" style="display:none;">
				<h3><?php esc_html_e( 'Export Completed!', 'wp-aie' ); ?></h3>
				<ul class="aie-results-list">
					<li>
						<?php esc_html_e( 'Total Exported:', 'wp-aie' ); ?>
						<strong class="aie-result-processed">0</strong>
					</li>
					<li>
						<?php esc_html_e( 'File Size:', 'wp-aie' ); ?>
						<strong class="aie-result-filesize">0 KB</strong>
					</li>
					<li>
						<?php esc_html_e( 'Duration:', 'wp-aie' ); ?>
						<strong class="aie-result-duration">0s</strong>
					</li>
				</ul>
				<p>
					<button type="button" class="button button-primary button-large aie-download-file">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download Export File', 'wp-aie' ); ?>
					</button>
				</p>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-cancel-export">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Cancel Export', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button button-primary aie-new-export" style="display:none;">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'New Export', 'wp-aie' ); ?>
			</button>
		</div>
	</div>
</div>
