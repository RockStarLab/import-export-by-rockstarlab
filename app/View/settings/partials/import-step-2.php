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
		<h2><?php esc_html_e( 'Step 2: Upload File', 'wp-aie' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Upload your data file (CSV or JSON)', 'wp-aie' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-upload-area" id="aie-upload-area">
			<div class="aie-upload-placeholder">
				<span class="dashicons dashicons-upload"></span>
				<h3><?php esc_html_e( 'Drag & Drop your file here', 'wp-aie' ); ?></h3>
				<p><?php esc_html_e( 'or', 'wp-aie' ); ?></p>
				<button type="button" class="button button-secondary" id="aie-select-file">
					<?php esc_html_e( 'Select File', 'wp-aie' ); ?>
				</button>
				<input type="file" id="aie-file-input" accept=".csv,.json" style="display:none;">
				<p class="description">
					<?php esc_html_e( 'Supported formats: CSV, JSON', 'wp-aie' ); ?><br>
					<?php esc_html_e( 'No file size limit - large files supported via chunked upload', 'wp-aie' ); ?>
				</p>
			</div>

			<!-- Upload Progress Bar -->
			<div class="aie-upload-progress" style="display:none;">
				<div class="aie-upload-status">
					<span class="dashicons dashicons-upload"></span>
					<span class="aie-upload-status-text"><?php esc_html_e( 'Uploading...', 'wp-aie' ); ?></span>
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
			<h3><?php esc_html_e( 'Format Options', 'wp-aie' ); ?></h3>
			
			<div class="aie-csv-options" style="display:none;">
				<label>
					<?php esc_html_e( 'Delimiter', 'wp-aie' ); ?>
					<select name="csv_delimiter" class="regular-text">
						<option value=",">,<?php esc_html_e( ' (Comma)', 'wp-aie' ); ?></option>
						<option value=";">; <?php esc_html_e( ' (Semicolon)', 'wp-aie' ); ?></option>
						<option value="\t">\t <?php esc_html_e( ' (Tab)', 'wp-aie' ); ?></option>
						<option value="|">| <?php esc_html_e( ' (Pipe)', 'wp-aie' ); ?></option>
					</select>
				</label>

				<label>
					<?php esc_html_e( 'Encoding', 'wp-aie' ); ?>
					<select name="csv_encoding" class="regular-text">
						<option value="UTF-8">UTF-8</option>
						<option value="ISO-8859-1">ISO-8859-1</option>
						<option value="Windows-1252">Windows-1252</option>
					</select>
				</label>

				<label>
					<input type="checkbox" name="csv_has_header" checked>
					<?php esc_html_e( 'First row contains column names', 'wp-aie' ); ?>
				</label>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-next-step" disabled>
				<?php esc_html_e( 'Next Step', 'wp-aie' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
