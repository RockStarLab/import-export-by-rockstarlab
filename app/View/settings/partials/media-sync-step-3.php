<?php
/**
 * Media Sync Step 3: Progress
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 3: Progress -->
<div class="rsl-ie-card rsl-ie-progress-section" id="rsl-ie-sync-progress-section" style="display: none;">
	<div class="rsl-ie-card-header">
		<h2>
			<span class="dashicons dashicons-update rsl-ie-spin"></span>
			<?php esc_html_e( 'Synchronization in Progress', 'import-export-by-rockstarlab' ); ?>
		</h2>
	</div>

	<div class="rsl-ie-card-body">
		<div class="rsl-ie-progress-bar-container">
			<div class="rsl-ie-progress-bar">
				<div id="rsl-ie-progress-fill" class="rsl-ie-progress-fill" style="width: 0%"></div>
			</div>
			<div class="rsl-ie-progress-text">
				<span id="rsl-ie-progress-percentage">0%</span>
				<span id="rsl-ie-progress-status"><?php esc_html_e( 'Starting...', 'import-export-by-rockstarlab' ); ?></span>
			</div>
		</div>

		<div class="rsl-ie-sync-stats">
			<div class="rsl-ie-stat-item">
				<span class="rsl-ie-stat-label"><?php esc_html_e( 'Processed:', 'import-export-by-rockstarlab' ); ?></span>
				<span id="rsl-ie-stat-processed" class="rsl-ie-stat-value">0</span>
			</div>
			<div class="rsl-ie-stat-item rsl-ie-stat-success">
				<span class="rsl-ie-stat-label"><?php esc_html_e( 'Success:', 'import-export-by-rockstarlab' ); ?></span>
				<span id="rsl-ie-stat-success" class="rsl-ie-stat-value">0</span>
			</div>
			<div class="rsl-ie-stat-item rsl-ie-stat-skipped">
				<span class="rsl-ie-stat-label"><?php esc_html_e( 'Skipped:', 'import-export-by-rockstarlab' ); ?></span>
				<span id="rsl-ie-stat-skipped" class="rsl-ie-stat-value">0</span>
			</div>
			<div class="rsl-ie-stat-item rsl-ie-stat-failed">
				<span class="rsl-ie-stat-label"><?php esc_html_e( 'Failed:', 'import-export-by-rockstarlab' ); ?></span>
				<span id="rsl-ie-stat-failed" class="rsl-ie-stat-value">0</span>
			</div>
		</div>

		<div class="rsl-ie-progress-actions">
			<button type="button" id="rsl-ie-pause-sync-btn" class="button">
				<span class="dashicons dashicons-controls-pause"></span>
				<?php esc_html_e( 'Pause', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" id="rsl-ie-cancel-sync-btn" class="button">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>

		<!-- Error Log -->
		<div id="rsl-ie-error-log" style="display: none;">
			<hr>
			<h3><?php esc_html_e( 'Errors', 'import-export-by-rockstarlab' ); ?></h3>
			<ul id="rsl-ie-error-list" class="rsl-ie-error-list"></ul>
		</div>
	</div>
</div>
