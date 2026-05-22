<?php
/**
 * Import Step 6: Import Progress
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 6: Import Progress -->
<div class="rsl-ie-step rsl-ie-step-6" data-step="6">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Import in Progress', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Please wait while your data is being imported', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<div class="rsl-ie-progress-container">
			<div class="rsl-ie-progress-bar">
				<div class="rsl-ie-progress-bar-fill" style="width: 0%;"></div>
			</div>
			<div class="rsl-ie-progress-stats">
				<div class="rsl-ie-progress-percentage">0%</div>
				<div class="rsl-ie-progress-details">
					<span class="rsl-ie-processed">0</span> / <span class="rsl-ie-total">0</span>
					<?php esc_html_e( 'items', 'import-export-by-rockstarlab' ); ?>
				</div>
			</div>
			
			<div class="rsl-ie-progress-estimates">
				<div class="rsl-ie-estimate">
					<span class="label"><?php esc_html_e( 'Elapsed:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="value rsl-ie-elapsed-time">0s</span>
				</div>
				<div class="rsl-ie-estimate">
					<span class="label"><?php esc_html_e( 'Remaining:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="value rsl-ie-remaining-time">-</span>
				</div>
				<div class="rsl-ie-estimate">
					<span class="label"><?php esc_html_e( 'Speed:', 'import-export-by-rockstarlab' ); ?></span>
					<span class="value rsl-ie-items-per-second">-</span>
				</div>
			</div>
		</div>

		<div class="rsl-ie-import-results" style="display:none;">
			<div class="rsl-ie-import-complete-card" style="display:none;">
				<div class="rsl-ie-complete-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<h3 class="rsl-ie-complete-title"><?php esc_html_e( 'Import Completed Successfully!', 'import-export-by-rockstarlab' ); ?></h3>
				<p class="rsl-ie-complete-subtitle"><?php esc_html_e( 'Your data has been imported successfully', 'import-export-by-rockstarlab' ); ?></p>
				
				<div class="rsl-ie-results-grid">
					<div class="rsl-ie-result-item">
						<div class="rsl-ie-result-icon rsl-ie-icon-success">
							<span class="dashicons dashicons-yes"></span>
						</div>
						<div class="rsl-ie-result-details">
							<span class="rsl-ie-result-label"><?php esc_html_e( 'Successful', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="rsl-ie-result-value rsl-ie-result-success">0</strong>
						</div>
					</div>
					<div class="rsl-ie-result-item">
						<div class="rsl-ie-result-icon rsl-ie-icon-updated">
							<span class="dashicons dashicons-update"></span>
						</div>
						<div class="rsl-ie-result-details">
							<span class="rsl-ie-result-label"><?php esc_html_e( 'Updated', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="rsl-ie-result-value rsl-ie-result-updated">0</strong>
						</div>
					</div>
					<div class="rsl-ie-result-item">
						<div class="rsl-ie-result-icon rsl-ie-icon-created">
							<span class="dashicons dashicons-plus-alt"></span>
						</div>
						<div class="rsl-ie-result-details">
							<span class="rsl-ie-result-label"><?php esc_html_e( 'Created', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="rsl-ie-result-value rsl-ie-result-created">0</strong>
						</div>
					</div>
					<div class="rsl-ie-result-item">
						<div class="rsl-ie-result-icon rsl-ie-icon-skipped">
							<span class="dashicons dashicons-minus"></span>
						</div>
						<div class="rsl-ie-result-details">
							<span class="rsl-ie-result-label"><?php esc_html_e( 'Skipped', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="rsl-ie-result-value rsl-ie-result-skipped">0</strong>
						</div>
					</div>
					<div class="rsl-ie-result-item">
						<div class="rsl-ie-result-icon rsl-ie-icon-failed">
							<span class="dashicons dashicons-no"></span>
						</div>
						<div class="rsl-ie-result-details">
							<span class="rsl-ie-result-label"><?php esc_html_e( 'Failed', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="rsl-ie-result-value rsl-ie-result-failed">0</strong>
						</div>
					</div>
					<div class="rsl-ie-result-item">
						<div class="rsl-ie-result-icon">
							<span class="dashicons dashicons-clock"></span>
						</div>
						<div class="rsl-ie-result-details">
							<span class="rsl-ie-result-label"><?php esc_html_e( 'Duration', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="rsl-ie-result-value rsl-ie-result-duration">0s</strong>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-cancel-import">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Cancel Import', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary rsl-ie-new-import" style="display:none;">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'New Import', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
