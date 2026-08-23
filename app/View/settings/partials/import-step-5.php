<?php
/**
 * Import Step 5: Import Options
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_current_site_url = home_url();
?>

<!-- Step 5: Import Options -->
<div class="rsl-ie-step rsl-ie-step-5" data-step="5">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 5: Import Options', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Configure how your data should be imported', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<table class="form-table">
			<!-- Unique Field Selector -->
			<tr>
				<th scope="row">
					<label for="rsl-ie-unique-field"><?php esc_html_e( 'Check for Existing Items by Field', 'import-export-by-rockstarlab' ); ?></label>
				</th>
				<td>
					<select id="rsl-ie-unique-field" name="unique_field" class="regular-text">
						<option value=""><?php esc_html_e( '-- Select Field --', 'import-export-by-rockstarlab' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select which field to use for checking if an item already exists (e.g., post_title, sku, user_email)', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<!-- Action if Match Found -->
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'If Match Found', 'import-export-by-rockstarlab' ); ?></label>
				</th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="if_exists" value="update" checked>
							<strong><?php esc_html_e( 'Update', 'import-export-by-rockstarlab' ); ?></strong> - 
							<?php esc_html_e( 'Update existing item with new data', 'import-export-by-rockstarlab' ); ?>
						</label><br>
						<label>
							<input type="radio" name="if_exists" value="skip">
							<strong><?php esc_html_e( 'Skip', 'import-export-by-rockstarlab' ); ?></strong> - 
							<?php esc_html_e( 'Skip import for this item', 'import-export-by-rockstarlab' ); ?>
						</label><br>
						<label>
							<input type="radio" name="if_exists" value="create">
							<strong><?php esc_html_e( 'Create', 'import-export-by-rockstarlab' ); ?></strong> - 
							<?php esc_html_e( 'Always create new item', 'import-export-by-rockstarlab' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'What to do when an item with matching field value is found', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<!-- Action if No Match Found -->
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'If No Match Found', 'import-export-by-rockstarlab' ); ?></label>
				</th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="if_not_exists" value="create" checked>
							<strong><?php esc_html_e( 'Create', 'import-export-by-rockstarlab' ); ?></strong> - 
							<?php esc_html_e( 'Create new item', 'import-export-by-rockstarlab' ); ?>
						</label><br>
						<label>
							<input type="radio" name="if_not_exists" value="skip">
							<strong><?php esc_html_e( 'Skip', 'import-export-by-rockstarlab' ); ?></strong> - 
							<?php esc_html_e( 'Skip import for this item', 'import-export-by-rockstarlab' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'What to do when no matching item is found', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<!-- Batch Size -->
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'Batch Size', 'import-export-by-rockstarlab' ); ?></label>
				</th>
				<td>
					<input type="number" name="batch_size" value="1" min="1" max="500" class="small-text" data-default-value="1" data-media-value="1">
					<p class="description"><?php esc_html_e( 'Number of items to process per batch', 'import-export-by-rockstarlab' ); ?></p>
				</td>
			</tr>

			<!-- Auto Import Media (for posts, pages, products) -->
			<tr class="rsl-ie-media-import-option" style="display: none;">
				<th scope="row">
					<label for="rsl-ie-auto-import-media"><?php esc_html_e( 'Automatically Import Media Files', 'import-export-by-rockstarlab' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="rsl-ie-auto-import-media" name="auto_import_media" value="1" checked>
						<?php esc_html_e( 'Automatically download and import all media files from content to the media library', 'import-export-by-rockstarlab' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, all images and media files found in content will be downloaded to your media library', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<!-- Media Duplicate Handling -->
			<tr class="rsl-ie-media-duplicate-option" style="display: none;">
				<th scope="row">
					<label><?php esc_html_e( 'If Media File Already Exists', 'import-export-by-rockstarlab' ); ?></label>
				</th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="media_duplicate_mode" value="skip" checked>
							<strong><?php esc_html_e( 'Skip', 'import-export-by-rockstarlab' ); ?></strong> - 
							<?php esc_html_e( 'Use existing media file (checked by filename, size, and hash)', 'import-export-by-rockstarlab' ); ?>
						</label><br>
						<label>
							<input type="radio" name="media_duplicate_mode" value="create">
							<strong><?php esc_html_e( 'Create New', 'import-export-by-rockstarlab' ); ?></strong> - 
							<?php esc_html_e( 'Always import as new media file', 'import-export-by-rockstarlab' ); ?>
						</label><br>
						<label>
							<input type="radio" name="media_duplicate_mode" value="replace">
							<strong><?php esc_html_e( 'Replace', 'import-export-by-rockstarlab' ); ?></strong> - 
							<?php esc_html_e( 'Replace existing media file with new one', 'import-export-by-rockstarlab' ); ?>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'Duplicates are detected by comparing filename, file size, and MD5 hash for maximum accuracy', 'import-export-by-rockstarlab' ); ?>
					</p>
				</td>
			</tr>

			<!-- Replace Links / Strings -->
			<tr>
				<th scope="row">
					<label for="rsl-ie-enable-replace-links"><?php esc_html_e( 'Replace links', 'import-export-by-rockstarlab' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="rsl-ie-enable-replace-links" name="replace_links" value="1">
						<?php esc_html_e( 'Find and replace links or other text values during import', 'import-export-by-rockstarlab' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Use this when importing content from another domain or when values need to be rewritten before they are saved.', 'import-export-by-rockstarlab' ); ?>
					</p>

					<div id="rsl-ie-replace-links-repeater" class="rsl-ie-replace-links-repeater" style="display: none;" data-current-site-url="<?php echo esc_attr( $rsl_ie_current_site_url ); ?>">
						<div class="rsl-ie-replace-links-header">
							<span><?php esc_html_e( 'Replace what', 'import-export-by-rockstarlab' ); ?></span>
							<span><?php esc_html_e( 'Replace to', 'import-export-by-rockstarlab' ); ?></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></span>
						</div>
						<div class="rsl-ie-replace-links-rows">
							<div class="rsl-ie-replace-links-row">
								<input type="text" class="regular-text rsl-ie-replace-what" name="replace_links_from[]" placeholder="<?php esc_attr_e( 'https://old-site.com', 'import-export-by-rockstarlab' ); ?>">
								<input type="text" class="regular-text rsl-ie-replace-to" name="replace_links_to[]" value="<?php echo esc_attr( $rsl_ie_current_site_url ); ?>">
								<button type="button" class="button button-secondary rsl-ie-remove-replace-link" aria-label="<?php esc_attr_e( 'Remove replacement row', 'import-export-by-rockstarlab' ); ?>">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
						</div>
						<button type="button" class="button button-secondary rsl-ie-add-replace-link">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add More', 'import-export-by-rockstarlab' ); ?>
						</button>
					</div>
				</td>
			</tr>
		</table>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-secondary rsl-ie-prev-step">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary button-large rsl-ie-start-import">
				<span class="dashicons dashicons-download"></span>
				<span class="rsl-ie-start-import-text"><?php esc_html_e( 'Start Import', 'import-export-by-rockstarlab' ); ?></span>
			</button>
		</div>
	</div>
</div>
