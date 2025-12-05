<?php
/**
 * Export Step 4: Export Format
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 4: Export Format -->
<div class="aie-step aie-step-4" data-step="4">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 4: Export Format', 'wp-aie' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose the format for your export file', 'wp-aie' ); ?></p>
	</div>

	<div class="aie-step-content">
		<div class="aie-format-selection">
			<label class="aie-format-option">
				<input type="radio" name="format" value="csv" checked>
				<div class="aie-format-card">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<h3>CSV</h3>
					<p><?php esc_html_e( 'Comma-separated values, best for spreadsheets', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-format-option">
				<input type="radio" name="format" value="json">
				<div class="aie-format-card">
					<span class="dashicons dashicons-media-code"></span>
					<h3>JSON</h3>
					<p><?php esc_html_e( 'JavaScript Object Notation, best for APIs', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-format-option">
				<input type="radio" name="format" value="xml">
				<div class="aie-format-card">
					<span class="dashicons dashicons-media-document"></span>
					<h3>XML</h3>
					<p><?php esc_html_e( 'Extensible Markup Language', 'wp-aie' ); ?></p>
				</div>
			</label>
		</div>

		<div class="aie-format-options">
			<div class="aie-csv-options">
				<h3><?php esc_html_e( 'CSV Options', 'wp-aie' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Delimiter', 'wp-aie' ); ?></th>
						<td>
							<select name="csv_delimiter" class="regular-text">
								<option value=",">,<?php esc_html_e( ' (Comma)', 'wp-aie' ); ?></option>
								<option value=";">; <?php esc_html_e( ' (Semicolon)', 'wp-aie' ); ?></option>
								<option value="\t">\t <?php esc_html_e( ' (Tab)', 'wp-aie' ); ?></option>
								<option value="|">| <?php esc_html_e( ' (Pipe)', 'wp-aie' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Encoding', 'wp-aie' ); ?></th>
						<td>
							<select name="csv_encoding" class="regular-text">
								<option value="UTF-8">UTF-8</option>
								<option value="ISO-8859-1">ISO-8859-1</option>
								<option value="Windows-1252">Windows-1252</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Include Header Row', 'wp-aie' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="csv_include_header" checked>
								<?php esc_html_e( 'First row contains column names', 'wp-aie' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="aie-json-options" style="display:none;">
				<h3><?php esc_html_e( 'JSON Options', 'wp-aie' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Pretty Print', 'wp-aie' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="json_pretty_print" checked>
								<?php esc_html_e( 'Format with indentation for readability', 'wp-aie' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="aie-xml-options" style="display:none;">
				<h3><?php esc_html_e( 'XML Options', 'wp-aie' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Root Element', 'wp-aie' ); ?></th>
						<td>
							<input type="text" name="xml_root" value="data" class="regular-text">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Item Element', 'wp-aie' ); ?></th>
						<td>
							<input type="text" name="xml_item" value="item" class="regular-text">
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-start-export">
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Start Export', 'wp-aie' ); ?>
			</button>
		</div>
	</div>
</div>
