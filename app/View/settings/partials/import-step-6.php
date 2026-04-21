<?php
/**
 * Import Step 6: Import Progress
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 6: Import Progress -->
<div class="aie-step aie-step-6" data-step="6">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Import in Progress', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Please wait while your data is being imported', 'import-export-by-rockstarlab' ); ?></p>
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
					<?php esc_html_e( 'items', 'import-export-by-rockstarlab' ); ?>
				</div>
			</div>
			
			<div class="aie-progress-estimates">
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Elapsed:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="value aie-elapsed-time">0s</span>
				</div>
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Remaining:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="value aie-remaining-time">-</span>
				</div>
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Speed:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="value aie-items-per-second">-</span>
				</div>
			</div>
		</div>

		<div class="aie-import-results" style="display:none;">
			<div class="aie-import-complete-card" style="display:none;">
				<div class="aie-complete-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<h3 class="aie-complete-title"><?php esc_html_e( 'Import Completed Successfully!', 'import-export-by-rockstarlab' ); ?></h3>
				<p class="aie-complete-subtitle"><?php esc_html_e( 'Your data has been imported successfully', 'import-export-by-rockstarlab' ); ?></p>
				
				<div class="aie-results-grid">
					<div class="aie-result-item">
						<div class="aie-result-icon aie-icon-success">
							<span class="dashicons dashicons-yes"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'Successful', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="aie-result-value aie-result-success">0</strong>
						</div>
					</div>
					<div class="aie-result-item">
						<div class="aie-result-icon aie-icon-updated">
							<span class="dashicons dashicons-update"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'Updated', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="aie-result-value aie-result-updated">0</strong>
						</div>
					</div>
					<div class="aie-result-item">
						<div class="aie-result-icon aie-icon-created">
							<span class="dashicons dashicons-plus-alt"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'Created', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="aie-result-value aie-result-created">0</strong>
						</div>
					</div>
					<div class="aie-result-item">
						<div class="aie-result-icon aie-icon-skipped">
							<span class="dashicons dashicons-minus"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'Skipped', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="aie-result-value aie-result-skipped">0</strong>
						</div>
					</div>
					<div class="aie-result-item">
						<div class="aie-result-icon aie-icon-failed">
							<span class="dashicons dashicons-no"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'Failed', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="aie-result-value aie-result-failed">0</strong>
						</div>
					</div>
					<div class="aie-result-item">
						<div class="aie-result-icon">
							<span class="dashicons dashicons-clock"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'Duration', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="aie-result-value aie-result-duration">0s</strong>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-cancel-import">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Cancel Import', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary aie-new-import" style="display:none;">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'New Import', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
