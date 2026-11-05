<?php
/**
 * Export Step 5: Export Progress
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_zip_supported = class_exists( 'ZipArchive' );
?>

<!-- Step 5: Export Progress -->
<div class="rsl-ie-step rsl-ie-step-5" data-step="5">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Export in Progress', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Please wait while your data is being exported', 'import-export-by-rockstarlab' ); ?></p>
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

		<div class="rsl-ie-export-results" style="display:none;">
			<div class="rsl-ie-export-complete-card" style="display:none;">
				<div class="rsl-ie-complete-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<h3 class="rsl-ie-complete-title"><?php esc_html_e( 'Export Completed Successfully!', 'import-export-by-rockstarlab' ); ?></h3>
				<p class="rsl-ie-complete-subtitle"><?php esc_html_e( 'Your data has been exported and is ready to download', 'import-export-by-rockstarlab' ); ?></p>
				
				<div class="rsl-ie-results-grid">
					<div class="rsl-ie-result-item">
						<div class="rsl-ie-result-icon">
							<span class="dashicons dashicons-database-export"></span>
						</div>
						<div class="rsl-ie-result-details">
							<span class="rsl-ie-result-label"><?php esc_html_e( 'Items Exported', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="rsl-ie-result-value rsl-ie-result-processed">0</strong>
						</div>
					</div>
					<div class="rsl-ie-result-item">
						<div class="rsl-ie-result-icon">
							<span class="dashicons dashicons-media-document"></span>
						</div>
						<div class="rsl-ie-result-details">
							<span class="rsl-ie-result-label"><?php esc_html_e( 'File Size', 'import-export-by-rockstarlab' ); ?></span>
							<strong class="rsl-ie-result-value rsl-ie-result-filesize">0 KB</strong>
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
				
				<div class="rsl-ie-download-options">
					<label class="rsl-ie-download-zip-option<?php echo $rsl_ie_zip_supported ? '' : ' is-disabled'; ?>">
						<input
							type="checkbox"
							name="download_zip"
							value="1"
							<?php disabled( ! $rsl_ie_zip_supported ); ?>
						/>
						<span><?php esc_html_e( 'Download as ZIP', 'import-export-by-rockstarlab' ); ?></span>
					</label>
					<p class="description">
						<?php
						if ( $rsl_ie_zip_supported ) {
							esc_html_e( 'Package the export file into a ZIP archive before downloading.', 'import-export-by-rockstarlab' );
						} else {
							esc_html_e( 'ZIP downloads are not available because the ZipArchive PHP extension is not enabled on this server.', 'import-export-by-rockstarlab' );
						}
						?>
					</p>
				</div>

				<p class="rsl-ie-download-action">
					<button type="button" class="button button-primary button-hero rsl-ie-download-file">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download Export File', 'import-export-by-rockstarlab' ); ?>
					</button>
				</p>
			</div>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-cancel-export">
				<span class="dashicons dashicons-no"></span>
				<?php esc_html_e( 'Cancel Export', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary rsl-ie-new-export" style="display:none;">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'New Export', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
