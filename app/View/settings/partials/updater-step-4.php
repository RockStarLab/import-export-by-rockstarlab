<?php
/**
 * Content Updater Step 4: Start Update
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 4: Start Update -->
<div class="aie-step aie-updater-step-4" data-step="4">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 4: Start Update', 'wp-advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Review settings and start the update process', 'wp-advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		<!-- Pre-Update: Configuration -->
		<div class="aie-updater-config" id="aie-updater-config">
			<!-- Summary Card -->
			<div class="aie-update-summary">
				<div class="aie-summary-header">
					<h3>
						<span class="dashicons dashicons-info"></span>
						<?php esc_html_e( 'Update Summary', 'wp-advanced-import-export' ); ?>
					</h3>
				</div>
				<div class="aie-summary-body">
					<div class="aie-summary-row">
						<span class="aie-summary-label"><?php esc_html_e( 'Content Type:', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-summary-value aie-content-type-summary">-</span>
					</div>
					<div class="aie-summary-row">
						<span class="aie-summary-label"><?php esc_html_e( 'Total Items:', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-summary-value aie-total-items-summary">
							<span class="spinner" style="float:none;margin:0;"></span>
						</span>
					</div>
					<div class="aie-summary-row">
						<span class="aie-summary-label"><?php esc_html_e( 'Fields to Update:', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-summary-value aie-fields-summary">-</span>
					</div>
					<div class="aie-summary-row">
						<span class="aie-summary-label"><?php esc_html_e( 'Functions Applied:', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-summary-value aie-functions-summary">-</span>
					</div>
				</div>
			</div>

			<!-- Update Settings -->
			<div class="aie-update-settings">
				<div class="aie-settings-header">
					<h3>
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Update Settings', 'wp-advanced-import-export' ); ?>
					</h3>
				</div>
				<div class="aie-settings-body">
					<div class="aie-setting-row">
						<label for="aie-updater-items-per-iteration">
							<?php esc_html_e( 'Items per Iteration:', 'wp-advanced-import-export' ); ?>
							<span class="aie-tooltip" title="<?php esc_attr_e( 'Number of items to process in each batch. Lower values are safer for large datasets.', 'wp-advanced-import-export' ); ?>">
								<span class="dashicons dashicons-editor-help"></span>
							</span>
						</label>
						<input 
							type="number" 
							id="aie-updater-items-per-iteration" 
							name="items_per_iteration" 
							value="10" 
							min="1" 
							max="100" 
							class="small-text"
						>
						<p class="description"><?php esc_html_e( 'Recommended: 10-50 items', 'wp-advanced-import-export' ); ?></p>
					</div>

					<div class="aie-setting-row">
						<label>
							<input type="checkbox" id="aie-updater-dry-run" name="dry_run">
							<?php esc_html_e( 'Test Mode (Dry Run)', 'wp-advanced-import-export' ); ?>
							<span class="aie-tooltip" title="<?php esc_attr_e( 'Process items without actually updating them. Useful for testing functions.', 'wp-advanced-import-export' ); ?>">
								<span class="dashicons dashicons-editor-help"></span>
							</span>
						</label>
						<p class="description"><?php esc_html_e( 'Enable to test the update without making actual changes', 'wp-advanced-import-export' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Warning Notice -->
			<div class="notice notice-warning aie-update-warning">
				<p>
					<span class="dashicons dashicons-warning"></span>
					<strong><?php esc_html_e( 'Important:', 'wp-advanced-import-export' ); ?></strong>
					<?php esc_html_e( 'This will modify your content. Please backup your database before proceeding.', 'wp-advanced-import-export' ); ?>
				</p>
			</div>

			<!-- Start Button -->
			<div class="aie-updater-start-actions">
				<button type="button" class="button button-primary button-large button-hero aie-start-update-btn">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Start Update', 'wp-advanced-import-export' ); ?>
				</button>
			</div>
		</div>

		<!-- During Update: Progress -->
		<div class="aie-updater-progress" id="aie-updater-progress" style="display:none;">
			<div class="aie-progress-header">
				<h3>
					<span class="dashicons dashicons-update aie-spin"></span>
					<?php esc_html_e( 'Updating Content...', 'wp-advanced-import-export' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Please wait while your content is being updated', 'wp-advanced-import-export' ); ?></p>
			</div>

			<!-- Progress Bar -->
			<div class="aie-progress-bar-container">
				<div class="aie-progress-bar">
					<div class="aie-progress-bar-fill" style="width: 0%;">
						<span class="aie-progress-percentage">0%</span>
					</div>
				</div>
			</div>

			<!-- Progress Stats -->
			<div class="aie-progress-stats">
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-database"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'Processed', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-stat-value">
							<span class="aie-processed-count">0</span> / <span class="aie-total-count">0</span>
						</span>
					</div>
				</div>
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-yes-alt"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'Updated', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-stat-value aie-updated-count">0</span>
					</div>
				</div>
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-dismiss"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'Skipped', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-stat-value aie-skipped-count">0</span>
					</div>
				</div>
				<div class="aie-stat-card">
					<span class="aie-stat-icon dashicons dashicons-warning"></span>
					<div class="aie-stat-content">
						<span class="aie-stat-label"><?php esc_html_e( 'Errors', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-stat-value aie-errors-count">0</span>
					</div>
				</div>
			</div>

			<!-- Current Status -->
			<div class="aie-progress-status">
				<p class="aie-status-message">
					<span class="dashicons dashicons-update aie-spin"></span>
					<span class="aie-status-text"><?php esc_html_e( 'Initializing...', 'wp-advanced-import-export' ); ?></span>
				</p>
			</div>

			<!-- Cancel Button -->
			<div class="aie-progress-actions">
				<button type="button" class="button button-secondary aie-cancel-update-btn">
					<span class="dashicons dashicons-no"></span>
					<?php esc_html_e( 'Cancel Update', 'wp-advanced-import-export' ); ?>
				</button>
			</div>
		</div>

		<!-- After Update: Results -->
		<div class="aie-updater-results" id="aie-updater-results" style="display:none;">
			<div class="aie-results-header">
				<h3>
					<span class="dashicons dashicons-yes-alt aie-success-icon"></span>
					<?php esc_html_e( 'Update Complete!', 'wp-advanced-import-export' ); ?>
				</h3>
			</div>

			<!-- Results Summary -->
			<div class="aie-results-summary">
				<div class="aie-result-card">
					<span class="aie-result-icon dashicons dashicons-database"></span>
					<div class="aie-result-content">
						<span class="aie-result-label"><?php esc_html_e( 'Total Processed', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-result-value aie-final-processed">0</span>
					</div>
				</div>
				<div class="aie-result-card aie-success">
					<span class="aie-result-icon dashicons dashicons-yes-alt"></span>
					<div class="aie-result-content">
						<span class="aie-result-label"><?php esc_html_e( 'Successfully Updated', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-result-value aie-final-updated">0</span>
					</div>
				</div>
				<div class="aie-result-card aie-warning">
					<span class="aie-result-icon dashicons dashicons-dismiss"></span>
					<div class="aie-result-content">
						<span class="aie-result-label"><?php esc_html_e( 'Skipped', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-result-value aie-final-skipped">0</span>
					</div>
				</div>
				<div class="aie-result-card aie-error">
					<span class="aie-result-icon dashicons dashicons-warning"></span>
					<div class="aie-result-content">
						<span class="aie-result-label"><?php esc_html_e( 'Errors', 'wp-advanced-import-export' ); ?></span>
						<span class="aie-result-value aie-final-errors">0</span>
					</div>
				</div>
			</div>

			<!-- Action Buttons -->
			<div class="aie-results-actions">
				<button type="button" class="button button-primary aie-start-new-update">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Start New Update', 'wp-advanced-import-export' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-jobs-log' ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'View Job Log', 'wp-advanced-import-export' ); ?>
				</a>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-updater-prev-step" id="aie-updater-prev-from-step-4">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'wp-advanced-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
