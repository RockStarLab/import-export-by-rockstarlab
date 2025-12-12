<?php
/**
 * Import Step 6: Import Progress
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 6: Import Progress -->
<div class="aie-step aie-step-6" data-step="6">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Import in Progress', 'wp-aie' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Please wait while your data is being imported', 'wp-aie' ); ?></p>
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

		<div class="aie-import-results" style="display:none;">
			<div class="notice notice-success" style="display:none;">
				<h3><?php esc_html_e( 'Import Completed!', 'wp-aie' ); ?></h3>
				<ul class="aie-results-list">
					<li>
						<?php esc_html_e( 'Total Processed:', 'wp-aie' ); ?>
						<strong class="aie-result-processed">0</strong>
					</li>
					<li>
						<?php esc_html_e( 'Successful:', 'wp-aie' ); ?>
						<strong class="aie-result-success">0</strong>
					</li>
					<li>
						<?php esc_html_e( 'Failed:', 'wp-aie' ); ?>
						<strong class="aie-result-failed">0</strong>
					</li>
					<li>
						<?php esc_html_e( 'Duration:', 'wp-aie' ); ?>
						<strong class="aie-result-duration">0s</strong>
					</li>
				</ul>
			</div>
		</div>

		<div class="aie-import-logs">
			<h3>
				<?php esc_html_e( 'Recent Logs', 'wp-aie' ); ?>
				<button type="button" class="button button-small aie-toggle-logs">
					<?php esc_html_e( 'Show/Hide', 'wp-aie' ); ?>
				</button>
			</h3>
			<div class="aie-logs-container" style="display:none;">
				<ul class="aie-logs-list"></ul>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-cancel-import">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Cancel Import', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button button-primary aie-new-import" style="display:none;">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'New Import', 'wp-aie' ); ?>
			</button>
		</div>
	</div>
</div>
