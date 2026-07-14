<?php
/**
 * Export Step 4: Export Format
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 4: Export Format -->
<div class="rsl-ie-step rsl-ie-step-4" data-step="4">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 4: Export Format', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose the format for your export file', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<div class="rsl-ie-format-selection">
			<label class="rsl-ie-format-option">
				<input type="radio" name="format" value="csv" checked>
				<div class="rsl-ie-format-card">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<h3>CSV</h3>
					<p><?php esc_html_e( 'Comma-separated values, best for spreadsheets', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<label class="rsl-ie-format-option">
				<input type="radio" name="format" value="json">
				<div class="rsl-ie-format-card">
					<span class="dashicons dashicons-media-code"></span>
					<h3>JSON</h3>
					<p><?php esc_html_e( 'JavaScript Object Notation, best for APIs', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<label class="rsl-ie-format-option">
				<input type="radio" name="format" value="xlsx">
				<div class="rsl-ie-format-card">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<h3>XLSX</h3>
					<p><?php esc_html_e( 'Excel workbook format for spreadsheet applications', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<label class="rsl-ie-format-option">
				<input type="radio" name="format" value="ods">
				<div class="rsl-ie-format-card">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<h3>ODS</h3>
					<p><?php esc_html_e( 'OpenDocument spreadsheet format for LibreOffice and OpenOffice', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<label class="rsl-ie-format-option">
				<input type="radio" name="format" value="xml">
				<div class="rsl-ie-format-card">
					<span class="dashicons dashicons-media-code"></span>
					<h3>XML</h3>
					<p><?php esc_html_e( 'Structured XML format for portable data exchange', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>
		</div>

		<div class="rsl-ie-format-options">
			<div class="rsl-ie-csv-options">
				<h3><?php esc_html_e( 'CSV Options', 'import-export-by-rockstarlab' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Delimiter', 'import-export-by-rockstarlab' ); ?></th>
						<td>
							<select name="csv_delimiter" class="regular-text">
								<option value=",">,<?php esc_html_e( ' (Comma)', 'import-export-by-rockstarlab' ); ?></option>
								<option value=";">; <?php esc_html_e( ' (Semicolon)', 'import-export-by-rockstarlab' ); ?></option>
								<option value="tab">\\t <?php esc_html_e( ' (Tab)', 'import-export-by-rockstarlab' ); ?></option>
								<option value="|">| <?php esc_html_e( ' (Pipe)', 'import-export-by-rockstarlab' ); ?></option>
								<option value="custom"><?php esc_html_e( 'Custom', 'import-export-by-rockstarlab' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="rsl-ie-custom-delimiter-row" style="display:none;">
						<th><?php esc_html_e( 'Custom Delimiter', 'import-export-by-rockstarlab' ); ?></th>
						<td>
						<input type="text" name="csv_custom_delimiter" class="regular-text" placeholder="<?php esc_attr_e( 'Enter a delimiter (any string)', 'import-export-by-rockstarlab' ); ?>">
						<p class="description"><?php esc_html_e( 'No length limit; you can use multi-character delimiters like *****', 'import-export-by-rockstarlab' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Include Header Row', 'import-export-by-rockstarlab' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="csv_include_header" checked>
								<?php esc_html_e( 'First row contains column names', 'import-export-by-rockstarlab' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="rsl-ie-json-options" style="display:none;">
				<h3><?php esc_html_e( 'JSON Options', 'import-export-by-rockstarlab' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Pretty Print', 'import-export-by-rockstarlab' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="json_pretty_print" checked>
								<?php esc_html_e( 'Format with indentation for readability', 'import-export-by-rockstarlab' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="rsl-ie-xml-options" style="display:none;">
				<h3><?php esc_html_e( 'XML Options', 'import-export-by-rockstarlab' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Pretty Print', 'import-export-by-rockstarlab' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="xml_pretty_print" checked>
								<?php esc_html_e( 'Format with indentation for readability', 'import-export-by-rockstarlab' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="rsl-ie-xlsx-options rsl-ie-ods-options" style="display:none;">
				<h3><?php esc_html_e( 'Spreadsheet Options', 'import-export-by-rockstarlab' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Include Header Row', 'import-export-by-rockstarlab' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="spreadsheet_include_header" checked>
								<?php esc_html_e( 'First row contains column names', 'import-export-by-rockstarlab' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<div class="rsl-ie-common-options">
				<h3><?php esc_html_e( 'Processing Options', 'import-export-by-rockstarlab' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Batch size', 'import-export-by-rockstarlab' ); ?></th>
						<td>
							<input type="number" name="items_per_iteration" value="3" min="1" max="1000" class="small-text">
							<p class="description"><?php esc_html_e( 'Number of items to process in each batch. Lower values are safer for large exports.', 'import-export-by-rockstarlab' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large rsl-ie-start-export">
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Start Export', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
