<?php
/**
 * Content Updater Step 4: Start Update
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 5: Start Update -->
<div class="rsl-ie-step rsl-ie-updater-step-5" data-step="5">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 5: Start Update', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Review settings and start the update process', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<!-- Pre-Update: Configuration -->
		<div class="rsl-ie-updater-config" id="rsl-ie-updater-config">
			<!-- Summary Card -->
			<div class="rsl-ie-update-summary">
				<div class="rsl-ie-summary-header">
					<h3>
						<span class="dashicons dashicons-info"></span>
						<?php esc_html_e( 'Update Summary', 'import-export-by-rockstarlab' ); ?>
					</h3>
				</div>
				<div class="rsl-ie-summary-body">
					<div class="rsl-ie-summary-row">
						<span class="rsl-ie-summary-label"><?php esc_html_e( 'Content Type:', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-summary-value rsl-ie-content-type-summary">-</span>
					</div>
					<div class="rsl-ie-summary-row">
						<span class="rsl-ie-summary-label"><?php esc_html_e( 'Total Items:', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-summary-value rsl-ie-total-items-summary">
							<span class="spinner" style="float:none;margin:0;"></span>
						</span>
					</div>
					<div class="rsl-ie-summary-row">
						<span class="rsl-ie-summary-label"><?php esc_html_e( 'Fields to Update:', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-summary-value rsl-ie-fields-summary">-</span>
					</div>
					<div class="rsl-ie-summary-row">
						<span class="rsl-ie-summary-label"><?php esc_html_e( 'Functions Applied:', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-summary-value rsl-ie-functions-summary">-</span>
					</div>
				</div>
			</div>

			<!-- Update Settings -->
			<div class="rsl-ie-update-settings">
				<div class="rsl-ie-settings-header">
					<h3>
						<span class="dashicons dashicons-admin-settings"></span>
						<?php esc_html_e( 'Update Settings', 'import-export-by-rockstarlab' ); ?>
					</h3>
				</div>
				<div class="rsl-ie-settings-body">
					<div class="rsl-ie-setting-row">
						<label for="rsl-ie-updater-items-per-iteration">
							<?php esc_html_e( 'Items per Iteration:', 'import-export-by-rockstarlab' ); ?>
							<span class="rsl-ie-tooltip" title="<?php esc_attr_e( 'Number of items to process in each batch. Lower values are safer for large datasets.', 'import-export-by-rockstarlab' ); ?>">
								<span class="dashicons dashicons-editor-help"></span>
							</span>
						</label>
						<input 
							type="number" 
							id="rsl-ie-updater-items-per-iteration" 
							name="items_per_iteration" 
							value="10" 
							min="1" 
							max="100" 
							class="small-text"
						>
						<p class="description"><?php esc_html_e( 'Recommended: 10-50 items', 'import-export-by-rockstarlab' ); ?></p>
					</div>

				</div>
			</div>

			<!-- Start Button -->
			<div class="rsl-ie-updater-start-actions">
				<button type="button" class="button button-primary button-large button-hero rsl-ie-start-update-btn">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Start Update', 'import-export-by-rockstarlab' ); ?>
				</button>
			</div>
		</div>

		<!-- During Update: Progress -->
		<div class="rsl-ie-updater-progress" id="rsl-ie-updater-progress" style="display:none;">
			<div class="rsl-ie-progress-header">
				<h3>
					<span class="dashicons dashicons-update rsl-ie-spin"></span>
					<?php esc_html_e( 'Updating Content...', 'import-export-by-rockstarlab' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Please wait while your content is being updated', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<!-- Progress Bar -->
			<div class="rsl-ie-progress-bar-container">
				<div class="rsl-ie-progress-bar">
					<div class="rsl-ie-progress-bar-fill" style="width: 0%;">
						<span class="rsl-ie-progress-percentage">0%</span>
					</div>
				</div>
			</div>

			<!-- Progress Stats -->
			<div class="rsl-ie-progress-stats">
				<div class="rsl-ie-stat-card">
					<span class="rsl-ie-stat-icon dashicons dashicons-database"></span>
					<div class="rsl-ie-stat-content">
						<span class="rsl-ie-stat-label"><?php esc_html_e( 'Processed', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-stat-value">
							<span class="rsl-ie-processed-count">0</span> / <span class="rsl-ie-total-count">0</span>
						</span>
					</div>
				</div>
				<div class="rsl-ie-stat-card">
					<span class="rsl-ie-stat-icon dashicons dashicons-yes-alt"></span>
					<div class="rsl-ie-stat-content">
						<span class="rsl-ie-stat-label"><?php esc_html_e( 'Updated', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-stat-value rsl-ie-updated-count">0</span>
					</div>
				</div>
				<div class="rsl-ie-stat-card">
					<span class="rsl-ie-stat-icon dashicons dashicons-dismiss"></span>
					<div class="rsl-ie-stat-content">
						<span class="rsl-ie-stat-label"><?php esc_html_e( 'Skipped', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-stat-value rsl-ie-skipped-count">0</span>
					</div>
				</div>
				<div class="rsl-ie-stat-card">
					<span class="rsl-ie-stat-icon dashicons dashicons-warning"></span>
					<div class="rsl-ie-stat-content">
						<span class="rsl-ie-stat-label"><?php esc_html_e( 'Errors', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-stat-value rsl-ie-errors-count">0</span>
					</div>
				</div>
			</div>

			<!-- Current Status -->
			<div class="rsl-ie-progress-status">
				<p class="rsl-ie-status-message">
					<span class="dashicons dashicons-update rsl-ie-spin"></span>
					<span class="rsl-ie-status-text"><?php esc_html_e( 'Initializing...', 'import-export-by-rockstarlab' ); ?></span>
				</p>
			</div>

			<!-- Cancel Button -->
			<div class="rsl-ie-progress-actions">
				<button type="button" class="button button-secondary rsl-ie-cancel-update-btn">
					<span class="dashicons dashicons-no"></span>
					<?php esc_html_e( 'Cancel Update', 'import-export-by-rockstarlab' ); ?>
				</button>
			</div>
		</div>

		<!-- After Update: Results -->
		<div class="rsl-ie-updater-results" id="rsl-ie-updater-results" style="display:none;">
			<div class="rsl-ie-results-header">
				<h3>
					<span class="dashicons dashicons-yes-alt rsl-ie-success-icon"></span>
					<?php esc_html_e( 'Update Complete!', 'import-export-by-rockstarlab' ); ?>
				</h3>
			</div>

			<!-- Results Summary -->
			<div class="rsl-ie-results-summary">
				<div class="rsl-ie-result-card">
					<span class="rsl-ie-result-icon dashicons dashicons-database"></span>
					<div class="rsl-ie-result-content">
						<span class="rsl-ie-result-label"><?php esc_html_e( 'Total Processed', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-result-value rsl-ie-final-processed">0</span>
					</div>
				</div>
				<div class="rsl-ie-result-card rsl-ie-success">
					<span class="rsl-ie-result-icon dashicons dashicons-yes-alt"></span>
					<div class="rsl-ie-result-content">
						<span class="rsl-ie-result-label"><?php esc_html_e( 'Successfully Updated', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-result-value rsl-ie-final-updated">0</span>
					</div>
				</div>
				<div class="rsl-ie-result-card rsl-ie-warning">
					<span class="rsl-ie-result-icon dashicons dashicons-dismiss"></span>
					<div class="rsl-ie-result-content">
						<span class="rsl-ie-result-label"><?php esc_html_e( 'Skipped', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-result-value rsl-ie-final-skipped">0</span>
					</div>
				</div>
				<div class="rsl-ie-result-card rsl-ie-error">
					<span class="rsl-ie-result-icon dashicons dashicons-warning"></span>
					<div class="rsl-ie-result-content">
						<span class="rsl-ie-result-label"><?php esc_html_e( 'Errors', 'import-export-by-rockstarlab' ); ?></span>
						<span class="rsl-ie-result-value rsl-ie-final-errors">0</span>
					</div>
				</div>
			</div>

			<!-- Action Buttons -->
			<div class="rsl-ie-results-actions">
				<button type="button" class="button button-primary rsl-ie-start-new-update">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Start New Update', 'import-export-by-rockstarlab' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-jobs-log' ) ); ?>" class="button button-secondary">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'View Job Log', 'import-export-by-rockstarlab' ); ?>
				</a>
			</div>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-updater-prev-step" id="rsl-ie-updater-prev-from-step-4">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
