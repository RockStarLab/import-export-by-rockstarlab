<?php
/**
 * Media Sync Step 3: Progress
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 3: Progress -->
<div class="aie-card aie-progress-section" id="aie-sync-progress-section" style="display: none;">
	<div class="aie-card-header">
		<h2>
			<span class="dashicons dashicons-update aie-spin"></span>
			<?php esc_html_e( 'Synchronization in Progress', 'import-export-by-rockstarlab' ); ?>
		</h2>
	</div>

	<div class="aie-card-body">
		<div class="aie-progress-bar-container">
			<div class="aie-progress-bar">
				<div id="aie-progress-fill" class="aie-progress-fill" style="width: 0%"></div>
			</div>
			<div class="aie-progress-text">
				<span id="aie-progress-percentage">0%</span>
				<span id="aie-progress-status"><?php esc_html_e( 'Starting...', 'import-export-by-rockstarlab' ); ?></span>
			</div>
		</div>

		<div class="aie-sync-stats">
			<div class="aie-stat-item">
				<span class="aie-stat-label"><?php esc_html_e( 'Processed:', 'import-export-by-rockstarlab' ); ?></span>
				<span id="aie-stat-processed" class="aie-stat-value">0</span>
			</div>
			<div class="aie-stat-item aie-stat-success">
				<span class="aie-stat-label"><?php esc_html_e( 'Success:', 'import-export-by-rockstarlab' ); ?></span>
				<span id="aie-stat-success" class="aie-stat-value">0</span>
			</div>
			<div class="aie-stat-item aie-stat-skipped">
				<span class="aie-stat-label"><?php esc_html_e( 'Skipped:', 'import-export-by-rockstarlab' ); ?></span>
				<span id="aie-stat-skipped" class="aie-stat-value">0</span>
			</div>
			<div class="aie-stat-item aie-stat-failed">
				<span class="aie-stat-label"><?php esc_html_e( 'Failed:', 'import-export-by-rockstarlab' ); ?></span>
				<span id="aie-stat-failed" class="aie-stat-value">0</span>
			</div>
		</div>

		<div class="aie-progress-actions">
			<button type="button" id="aie-pause-sync-btn" class="button">
				<span class="dashicons dashicons-controls-pause"></span>
				<?php esc_html_e( 'Pause', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" id="aie-cancel-sync-btn" class="button">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>

		<!-- Error Log -->
		<div id="aie-error-log" style="display: none;">
			<hr>
			<h3><?php esc_html_e( 'Errors', 'import-export-by-rockstarlab' ); ?></h3>
			<ul id="aie-error-list" class="aie-error-list"></ul>
		</div>
	</div>
</div>
