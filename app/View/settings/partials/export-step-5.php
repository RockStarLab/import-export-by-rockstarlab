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
		<h2><?php esc_html_e( 'Export in Progress', 'amplified-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Please wait while your data is being exported', 'amplified-import-export' ); ?></p>
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
					<?php esc_html_e( 'items', 'amplified-import-export' ); ?>
				</div>
			</div>
			
			<div class="aie-progress-estimates">
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Elapsed:', 'amplified-import-export' ); ?></span>
					<span class="value aie-elapsed-time">0s</span>
				</div>
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Remaining:', 'amplified-import-export' ); ?></span>
					<span class="value aie-remaining-time">-</span>
				</div>
				<div class="aie-estimate">
					<span class="label"><?php esc_html_e( 'Speed:', 'amplified-import-export' ); ?></span>
					<span class="value aie-items-per-second">-</span>
				</div>
			</div>
		</div>

		<div class="aie-export-results" style="display:none;">
			<div class="aie-export-complete-card" style="display:none;">
				<div class="aie-complete-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<h3 class="aie-complete-title"><?php esc_html_e( 'Export Completed Successfully!', 'amplified-import-export' ); ?></h3>
				<p class="aie-complete-subtitle"><?php esc_html_e( 'Your data has been exported and is ready to download', 'amplified-import-export' ); ?></p>
				
				<div class="aie-results-grid">
					<div class="aie-result-item">
						<div class="aie-result-icon">
							<span class="dashicons dashicons-database-export"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'Items Exported', 'amplified-import-export' ); ?></span>
							<strong class="aie-result-value aie-result-processed">0</strong>
						</div>
					</div>
					<div class="aie-result-item">
						<div class="aie-result-icon">
							<span class="dashicons dashicons-media-document"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'File Size', 'amplified-import-export' ); ?></span>
							<strong class="aie-result-value aie-result-filesize">0 KB</strong>
						</div>
					</div>
					<div class="aie-result-item">
						<div class="aie-result-icon">
							<span class="dashicons dashicons-clock"></span>
						</div>
						<div class="aie-result-details">
							<span class="aie-result-label"><?php esc_html_e( 'Duration', 'amplified-import-export' ); ?></span>
							<strong class="aie-result-value aie-result-duration">0s</strong>
						</div>
					</div>
				</div>
				
				<p class="aie-download-action">
					<button type="button" class="button button-primary button-hero aie-download-file">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download Export File', 'amplified-import-export' ); ?>
					</button>
				</p>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-cancel-export">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Cancel Export', 'amplified-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary aie-new-export" style="display:none;">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'New Export', 'amplified-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
