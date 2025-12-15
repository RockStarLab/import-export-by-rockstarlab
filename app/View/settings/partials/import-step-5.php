<?php
/**
 * Import Step 5: Import Options
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 5: Import Options -->
<div class="aie-step aie-step-5" data-step="5">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 5: Import Options', 'wp-advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Configure how your data should be imported', 'wp-advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		<table class="form-table">
			<!-- Unique Field Selector -->
			<tr>
				<th scope="row">
					<label for="aie-unique-field"><?php esc_html_e( 'Check for Existing Items by Field', 'wp-advanced-import-export' ); ?></label>
				</th>
				<td>
					<select id="aie-unique-field" name="unique_field" class="regular-text">
						<option value=""><?php esc_html_e( '-- Select Field --', 'wp-advanced-import-export' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select which field to use for checking if an item already exists (e.g., post_title, sku, user_email)', 'wp-advanced-import-export' ); ?>
					</p>
				</td>
			</tr>

			<!-- Action if Match Found -->
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'If Match Found', 'wp-advanced-import-export' ); ?></label>
				</th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="if_exists" value="update" checked>
							<strong><?php esc_html_e( 'Update', 'wp-advanced-import-export' ); ?></strong> - 
							<?php esc_html_e( 'Update existing item with new data', 'wp-advanced-import-export' ); ?>
						</label><br>
						<label>
							<input type="radio" name="if_exists" value="skip">
							<strong><?php esc_html_e( 'Skip', 'wp-advanced-import-export' ); ?></strong> - 
							<?php esc_html_e( 'Skip import for this item', 'wp-advanced-import-export' ); ?>
						</label><br>
						<label>
							<input type="radio" name="if_exists" value="ignore">
							<strong><?php esc_html_e( 'Ignore', 'wp-advanced-import-export' ); ?></strong> - 
							<?php esc_html_e( 'Create new item anyway (ignore existing)', 'wp-advanced-import-export' ); ?>
						</label><br>
						<label>
							<input type="radio" name="if_exists" value="create">
							<strong><?php esc_html_e( 'Create', 'wp-advanced-import-export' ); ?></strong> - 
							<?php esc_html_e( 'Always create new item', 'wp-advanced-import-export' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'What to do when an item with matching field value is found', 'wp-advanced-import-export' ); ?>
					</p>
				</td>
			</tr>

			<!-- Action if No Match Found -->
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'If No Match Found', 'wp-advanced-import-export' ); ?></label>
				</th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="if_not_exists" value="create" checked>
							<strong><?php esc_html_e( 'Create', 'wp-advanced-import-export' ); ?></strong> - 
							<?php esc_html_e( 'Create new item', 'wp-advanced-import-export' ); ?>
						</label><br>
						<label>
							<input type="radio" name="if_not_exists" value="skip">
							<strong><?php esc_html_e( 'Skip', 'wp-advanced-import-export' ); ?></strong> - 
							<?php esc_html_e( 'Skip import for this item', 'wp-advanced-import-export' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'What to do when no matching item is found', 'wp-advanced-import-export' ); ?>
					</p>
				</td>
			</tr>

			<!-- Batch Size -->
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'Batch Size', 'wp-advanced-import-export' ); ?></label>
				</th>
				<td>
					<input type="number" name="batch_size" value="50" min="10" max="500" class="small-text">
					<p class="description"><?php esc_html_e( 'Number of items to process per batch', 'wp-advanced-import-export' ); ?></p>
				</td>
			</tr>
		</table>

		<div class="aie-step-actions">
			<button type="button" class="button button-secondary aie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'wp-advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary button-large aie-start-import">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Start Import', 'wp-advanced-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
