<?php
/**
 * Import Step 2: Upload File
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Upload File -->
<div class="rsl-ie-step rsl-ie-step-2" data-step="2">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 2: Upload File', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Upload your data file (CSV, XML, XLSX, or ODS)', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<div class="rsl-ie-upload-area" id="rsl-ie-upload-area">
			<div class="rsl-ie-upload-placeholder">
				<span class="dashicons dashicons-upload"></span>
				<h3><?php esc_html_e( 'Drag & Drop your file here', 'import-export-by-rockstarlab' ); ?></h3>
				<p><?php esc_html_e( 'or', 'import-export-by-rockstarlab' ); ?></p>
				<button type="button" class="button button-secondary" id="rsl-ie-select-file">
					<?php esc_html_e( 'Select File', 'import-export-by-rockstarlab' ); ?>
				</button>
				<input type="file" id="rsl-ie-file-input" accept=".csv,.xml,.xlsx,.ods,.zip" style="display:none;">
				<p class="description">
					<?php esc_html_e( 'Supported formats: CSV, XML, XLSX, ODS, or ZIP containing one supported import file', 'import-export-by-rockstarlab' ); ?><br>
					<?php esc_html_e( 'No file size limit - large files supported via chunked upload', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>

			<!-- Upload Progress Bar -->
			<div class="rsl-ie-upload-progress" style="display:none;">
				<div class="rsl-ie-upload-status">
					<span class="dashicons dashicons-upload"></span>
					<span class="rsl-ie-upload-status-text"><?php esc_html_e( 'Uploading...', 'import-export-by-rockstarlab' ); ?></span>
				</div>
				<div class="rsl-ie-progress-bar">
					<div class="rsl-ie-progress-bar-fill" style="width: 0%;"></div>
				</div>
				<div class="rsl-ie-upload-details">
					<span class="rsl-ie-upload-percentage">0%</span>
					<span class="rsl-ie-upload-speed"></span>
				</div>
			</div>
		</div>

		<div class="rsl-ie-file-info" style="display:none;">
			<div class="rsl-ie-file-details">
				<span class="dashicons dashicons-media-document"></span>
				<div class="rsl-ie-file-meta">
					<strong class="rsl-ie-file-name"></strong>
					<span class="rsl-ie-file-size"></span>
					<span class="rsl-ie-file-format"></span>
				</div>
				<button type="button" class="button button-link-delete rsl-ie-remove-file">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>
		</div>

		<div class="rsl-ie-format-options" style="display:none;">
			<h3><?php esc_html_e( 'Format Options', 'import-export-by-rockstarlab' ); ?></h3>
			
			<div class="rsl-ie-csv-options" style="display:none;">
				<label>
					<?php esc_html_e( 'Delimiter', 'import-export-by-rockstarlab' ); ?>
					<select name="csv_delimiter" id="csv_delimiter" class="regular-text">
						<option value=",">,<?php esc_html_e( ' (Comma)', 'import-export-by-rockstarlab' ); ?></option>
						<option value=";">; <?php esc_html_e( ' (Semicolon)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="tab">	<?php esc_html_e( ' (Tab)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="|">| <?php esc_html_e( ' (Pipe)', 'import-export-by-rockstarlab' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom', 'import-export-by-rockstarlab' ); ?></option>
					</select>
				</label>

				<label class="rsl-ie-custom-delimiter-wrapper" style="display:none;">
					<?php esc_html_e( 'Custom Delimiter', 'import-export-by-rockstarlab' ); ?>
						<input type="text" name="csv_custom_delimiter" id="csv_custom_delimiter" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom delimiter (any string)', 'import-export-by-rockstarlab' ); ?>">
				</label>
				<label>
					<input type="checkbox" name="csv_has_header" checked>
					<?php esc_html_e( 'First row contains column names', 'import-export-by-rockstarlab' ); ?>
				</label>
			</div>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large rsl-ie-next-step" disabled>
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
