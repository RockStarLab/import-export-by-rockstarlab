<?php
/**
 * Import Step 2: Upload File
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 2: Upload File -->
<div class="aie-step aie-step-2" data-step="2">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 2: Upload File', 'advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Upload your data file (CSV)', 'advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-upload-area" id="aie-upload-area">
			<div class="aie-upload-placeholder">
				<span class="dashicons dashicons-upload"></span>
				<h3><?php esc_html_e( 'Drag & Drop your file here', 'advanced-import-export' ); ?></h3>
				<p><?php esc_html_e( 'or', 'advanced-import-export' ); ?></p>
				<button type="button" class="button button-secondary" id="aie-select-file">
					<?php esc_html_e( 'Select File', 'advanced-import-export' ); ?>
				</button>
				<input type="file" id="aie-file-input" accept=".csv" style="display:none;">
				<p class="description">
					<?php esc_html_e( 'Supported formats: CSV', 'advanced-import-export' ); ?><br>
					<?php esc_html_e( 'No file size limit - large files supported via chunked upload', 'advanced-import-export' ); ?>
				</p>
			</div>

			<!-- Upload Progress Bar -->
			<div class="aie-upload-progress" style="display:none;">
				<div class="aie-upload-status">
					<span class="dashicons dashicons-upload"></span>
					<span class="aie-upload-status-text"><?php esc_html_e( 'Uploading...', 'advanced-import-export' ); ?></span>
				</div>
				<div class="aie-progress-bar">
					<div class="aie-progress-bar-fill" style="width: 0%;"></div>
				</div>
				<div class="aie-upload-details">
					<span class="aie-upload-percentage">0%</span>
					<span class="aie-upload-speed"></span>
				</div>
			</div>
		</div>

		<div class="aie-file-info" style="display:none;">
			<div class="aie-file-details">
				<span class="dashicons dashicons-media-document"></span>
				<div class="aie-file-meta">
					<strong class="aie-file-name"></strong>
					<span class="aie-file-size"></span>
					<span class="aie-file-format"></span>
				</div>
				<button type="button" class="button button-link-delete aie-remove-file">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>
		</div>

		<div class="aie-format-options" style="display:none;">
			<h3><?php esc_html_e( 'Format Options', 'advanced-import-export' ); ?></h3>
			
			<div class="aie-csv-options" style="display:none;">
				<label>
					<?php esc_html_e( 'Delimiter', 'advanced-import-export' ); ?>
					<select name="csv_delimiter" id="csv_delimiter" class="regular-text">
						<option value=",">,<?php esc_html_e( ' (Comma)', 'advanced-import-export' ); ?></option>
						<option value=";">; <?php esc_html_e( ' (Semicolon)', 'advanced-import-export' ); ?></option>
						<option value="tab">	<?php esc_html_e( ' (Tab)', 'advanced-import-export' ); ?></option>
						<option value="|">| <?php esc_html_e( ' (Pipe)', 'advanced-import-export' ); ?></option>
						<option value="custom"><?php esc_html_e( 'Custom', 'advanced-import-export' ); ?></option>
					</select>
				</label>

				<label class="aie-custom-delimiter-wrapper" style="display:none;">
					<?php esc_html_e( 'Custom Delimiter', 'advanced-import-export' ); ?>
						<input type="text" name="csv_custom_delimiter" id="csv_custom_delimiter" class="regular-text" placeholder="<?php esc_attr_e( 'Enter custom delimiter (any string)', 'advanced-import-export' ); ?>">
				<label>
					<input type="checkbox" name="csv_has_header" checked>
					<?php esc_html_e( 'First row contains column names', 'advanced-import-export' ); ?>
				</label>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-next-step" disabled>
				<?php esc_html_e( 'Next Step', 'advanced-import-export' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
