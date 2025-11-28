<?php
/**
 * Import Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-import" class="wp-advanced-import-export wrap">
	<h1><?php esc_html_e( 'Import Data', 'wp-aie' ); ?></h1>

	<div class="aie-import-wizard">
		
		<!-- Step 1: Select Content Type -->
		<div class="aie-step aie-step-1 active" data-step="1">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 1: Select Content Type', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose what type of data you want to import', 'wp-aie' ); ?></p>
			</div>

			<div class="aie-step-content">
				<div class="aie-content-types">
					<label class="aie-content-type">
						<input type="radio" name="content_type" value="post" checked>
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-post"></span>
							<h3><?php esc_html_e( 'Posts', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Import blog posts, pages, or custom post types', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="media">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-media"></span>
							<h3><?php esc_html_e( 'Media', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Import images, videos, and documents', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="user" disabled>
						<div class="aie-content-type-card aie-disabled">
							<span class="dashicons dashicons-admin-users"></span>
							<h3><?php esc_html_e( 'Users', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Coming soon...', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="comment" disabled>
						<div class="aie-content-type-card aie-disabled">
							<span class="dashicons dashicons-admin-comments"></span>
							<h3><?php esc_html_e( 'Comments', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Coming soon...', 'wp-aie' ); ?></p>
						</div>
					</label>
				</div>

				<div class="aie-step-actions">
					<button type="button" class="button button-primary button-large aie-next-step">
						<?php esc_html_e( 'Next Step', 'wp-aie' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 2: Upload File -->
		<div class="aie-step aie-step-2" data-step="2">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 2: Upload File', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Upload your data file (CSV, JSON, or XML)', 'wp-aie' ); ?></p>
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
						<input type="file" id="aie-file-input" accept=".csv,.json,.xml" style="display:none;">
						<p class="description">
							<?php esc_html_e( 'Supported formats: CSV, JSON, XML', 'wp-aie' ); ?><br>
							<?php esc_html_e( 'Max file size: 50MB', 'wp-aie' ); ?>
						</p>
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

		<!-- Step 3: Preview Data -->
		<div class="aie-step aie-step-3" data-step="3">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 3: Preview Data', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Review the first few rows from your file', 'wp-aie' ); ?></p>
			</div>

			<div class="aie-step-content">
				<div class="aie-preview-container">
					<div class="aie-preview-stats">
						<div class="aie-stat">
							<span class="aie-stat-label"><?php esc_html_e( 'Total Rows:', 'wp-aie' ); ?></span>
							<span class="aie-stat-value aie-total-rows">-</span>
						</div>
						<div class="aie-stat">
							<span class="aie-stat-label"><?php esc_html_e( 'Columns:', 'wp-aie' ); ?></span>
							<span class="aie-stat-value aie-total-columns">-</span>
						</div>
					</div>

					<div class="aie-preview-table-container">
						<table class="wp-list-table widefat fixed striped aie-preview-table">
							<thead></thead>
							<tbody></tbody>
						</table>
					</div>

					<p class="description">
						<?php esc_html_e( 'Showing first 5 rows. All data will be imported.', 'wp-aie' ); ?>
					</p>
				</div>

				<div class="aie-step-actions">
					<button type="button" class="button button-secondary aie-prev-step">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Previous', 'wp-aie' ); ?>
					</button>
					<button type="button" class="button button-primary button-large aie-next-step">
						<?php esc_html_e( 'Next Step', 'wp-aie' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 4: Field Mapping -->
		<div class="aie-step aie-step-4" data-step="4">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 4: Field Mapping', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Map your file columns to WordPress fields', 'wp-aie' ); ?></p>
			</div>

			<div class="aie-step-content">
				<div class="aie-mapping-controls">
					<button type="button" class="button aie-auto-map">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e( 'Auto Map', 'wp-aie' ); ?>
					</button>
					<button type="button" class="button aie-clear-map">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Clear All', 'wp-aie' ); ?>
					</button>
				</div>

				<div class="aie-field-mapping">
					<table class="wp-list-table widefat fixed aie-mapping-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Source Column', 'wp-aie' ); ?></th>
								<th><?php esc_html_e( 'Target Field', 'wp-aie' ); ?></th>
								<th><?php esc_html_e( 'Sample Data', 'wp-aie' ); ?></th>
							</tr>
						</thead>
						<tbody class="aie-mapping-body">
							<!-- Populated by JavaScript -->
						</tbody>
					</table>
				</div>

				<div class="aie-step-actions">
					<button type="button" class="button button-secondary aie-prev-step">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Previous', 'wp-aie' ); ?>
					</button>
					<button type="button" class="button button-primary button-large aie-next-step">
						<?php esc_html_e( 'Next Step', 'wp-aie' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 5: Import Options -->
		<div class="aie-step aie-step-5" data-step="5">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 5: Import Options', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Configure how your data should be imported', 'wp-aie' ); ?></p>
			</div>

			<div class="aie-step-content">
				<table class="form-table">
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Duplicate Handling', 'wp-aie' ); ?></label>
						</th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="duplicate_handling" value="skip" checked>
									<?php esc_html_e( 'Skip - Don\'t import duplicates', 'wp-aie' ); ?>
								</label><br>
								<label>
									<input type="radio" name="duplicate_handling" value="update">
									<?php esc_html_e( 'Update - Overwrite existing items', 'wp-aie' ); ?>
								</label><br>
								<label>
									<input type="radio" name="duplicate_handling" value="create">
									<?php esc_html_e( 'Create - Always create new items', 'wp-aie' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>

					<tr class="aie-post-options">
						<th scope="row">
							<label><?php esc_html_e( 'Post Status', 'wp-aie' ); ?></label>
						</th>
						<td>
							<select name="post_status" class="regular-text">
								<option value="publish"><?php esc_html_e( 'Published', 'wp-aie' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Draft', 'wp-aie' ); ?></option>
								<option value="pending"><?php esc_html_e( 'Pending Review', 'wp-aie' ); ?></option>
								<option value="private"><?php esc_html_e( 'Private', 'wp-aie' ); ?></option>
							</select>
						</td>
					</tr>

					<tr class="aie-post-options">
						<th scope="row">
							<label><?php esc_html_e( 'Post Type', 'wp-aie' ); ?></label>
						</th>
						<td>
							<select name="post_type" class="regular-text">
								<option value="post"><?php esc_html_e( 'Post', 'wp-aie' ); ?></option>
								<option value="page"><?php esc_html_e( 'Page', 'wp-aie' ); ?></option>
								<?php
								$post_types = get_post_types(
									[
										'public'   => true,
										'_builtin' => false,
									],
									'objects'
								);
								foreach ( $post_types as $post_type ) {
									printf(
										'<option value="%s">%s</option>',
										esc_attr( $post_type->name ),
										esc_html( $post_type->label )
									);
								}
								?>
							</select>
						</td>
					</tr>

					<tr class="aie-media-options" style="display:none;">
						<th scope="row">
							<label><?php esc_html_e( 'Download Remote Images', 'wp-aie' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="download_images" checked>
								<?php esc_html_e( 'Download images from URLs to media library', 'wp-aie' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Batch Size', 'wp-aie' ); ?></label>
						</th>
						<td>
							<input type="number" name="batch_size" value="50" min="10" max="500" class="small-text">
							<p class="description"><?php esc_html_e( 'Number of items to process per batch', 'wp-aie' ); ?></p>
						</td>
					</tr>
				</table>

				<div class="aie-step-actions">
					<button type="button" class="button button-secondary aie-prev-step">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Previous', 'wp-aie' ); ?>
					</button>
					<button type="button" class="button button-primary button-large aie-start-import">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Start Import', 'wp-aie' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Step 6: Import Progress -->
		<div class="aie-step aie-step-6" data-step="6">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Import in Progress', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Please wait while your data is being imported', 'wp-aie' ); ?></p>
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
							<?php esc_html_e( 'items', 'wp-aie' ); ?>
						</div>
					</div>
					
					<div class="aie-progress-estimates">
						<div class="aie-estimate">
							<span class="label"><?php esc_html_e( 'Elapsed:', 'wp-aie' ); ?></span>
							<span class="value aie-elapsed-time">0s</span>
						</div>
						<div class="aie-estimate">
							<span class="label"><?php esc_html_e( 'Remaining:', 'wp-aie' ); ?></span>
							<span class="value aie-remaining-time">-</span>
						</div>
						<div class="aie-estimate">
							<span class="label"><?php esc_html_e( 'Speed:', 'wp-aie' ); ?></span>
							<span class="value aie-items-per-second">-</span>
						</div>
					</div>
				</div>

				<div class="aie-import-results" style="display:none;">
					<div class="notice notice-success">
						<h3><?php esc_html_e( 'Import Completed!', 'wp-aie' ); ?></h3>
						<ul class="aie-results-list">
							<li>
								<?php esc_html_e( 'Total Processed:', 'wp-aie' ); ?>
								<strong class="aie-result-processed">0</strong>
							</li>
							<li>
								<?php esc_html_e( 'Successful:', 'wp-aie' ); ?>
								<strong class="aie-result-success">0</strong>
							</li>
							<li>
								<?php esc_html_e( 'Failed:', 'wp-aie' ); ?>
								<strong class="aie-result-failed">0</strong>
							</li>
							<li>
								<?php esc_html_e( 'Duration:', 'wp-aie' ); ?>
								<strong class="aie-result-duration">0s</strong>
							</li>
						</ul>
					</div>
				</div>

				<div class="aie-import-logs">
					<h3>
						<?php esc_html_e( 'Recent Logs', 'wp-aie' ); ?>
						<button type="button" class="button button-small aie-toggle-logs">
							<?php esc_html_e( 'Show/Hide', 'wp-aie' ); ?>
						</button>
					</h3>
					<div class="aie-logs-container" style="display:none;">
						<ul class="aie-logs-list"></ul>
					</div>
				</div>

				<div class="aie-step-actions">
					<button type="button" class="button button-secondary aie-cancel-import">
						<span class="dashicons dashicons-no"></span>
						<?php esc_html_e( 'Cancel Import', 'wp-aie' ); ?>
					</button>
					<button type="button" class="button button-primary aie-new-import" style="display:none;">
						<span class="dashicons dashicons-plus"></span>
						<?php esc_html_e( 'New Import', 'wp-aie' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Progress Steps Indicator -->
		<div class="aie-steps-indicator">
			<div class="aie-step-indicator active" data-step="1">
				<div class="aie-step-number">1</div>
				<div class="aie-step-label"><?php esc_html_e( 'Content Type', 'wp-aie' ); ?></div>
			</div>
			<div class="aie-step-indicator" data-step="2">
				<div class="aie-step-number">2</div>
				<div class="aie-step-label"><?php esc_html_e( 'Upload', 'wp-aie' ); ?></div>
			</div>
			<div class="aie-step-indicator" data-step="3">
				<div class="aie-step-number">3</div>
				<div class="aie-step-label"><?php esc_html_e( 'Preview', 'wp-aie' ); ?></div>
			</div>
			<div class="aie-step-indicator" data-step="4">
				<div class="aie-step-number">4</div>
				<div class="aie-step-label"><?php esc_html_e( 'Mapping', 'wp-aie' ); ?></div>
			</div>
			<div class="aie-step-indicator" data-step="5">
				<div class="aie-step-number">5</div>
				<div class="aie-step-label"><?php esc_html_e( 'Options', 'wp-aie' ); ?></div>
			</div>
			<div class="aie-step-indicator" data-step="6">
				<div class="aie-step-number">6</div>
				<div class="aie-step-label"><?php esc_html_e( 'Import', 'wp-aie' ); ?></div>
			</div>
		</div>

	</div>
</div>
