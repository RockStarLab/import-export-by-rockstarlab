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
								<option value="custom"><?php esc_html_e( 'Custom', 'wp-aie' ); ?></option>
							</select>
						</td>
					</tr>
					<tr class="aie-custom-delimiter-row" style="display:none;">
						<th><?php esc_html_e( 'Custom Delimiter', 'wp-aie' ); ?></th>
						<td>
							<input type="text" name="csv_custom_delimiter" maxlength="1" class="regular-text" placeholder="<?php esc_attr_e( 'Enter a single character', 'wp-aie' ); ?>">
							<p class="description"><?php esc_html_e( 'Enter a single character to use as delimiter', 'wp-aie' ); ?></p>
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

			<div class="aie-common-options">
				<h3><?php esc_html_e( 'Processing Options', 'wp-aie' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Items per iteration', 'wp-aie' ); ?></th>
						<td>
							<input type="number" name="items_per_iteration" value="3" min="1" max="100" class="small-text">
							<p class="description"><?php esc_html_e( 'Number of items to process in each batch. Lower values are safer for large exports.', 'wp-aie' ); ?></p>
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
