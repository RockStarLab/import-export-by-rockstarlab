<?php
/**
 * Export Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-export" class="wp-advanced-import-export wrap">
	<h1><?php esc_html_e( 'Export Data', 'wp-aie' ); ?></h1>

	<div class="aie-export-wizard">
		
		<!-- Step 1: Select Content Type -->
		<div class="aie-step aie-step-1 active" data-step="1">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 1: Select Content Type', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose what type of data you want to export', 'wp-aie' ); ?></p>
			</div>

			<div class="aie-step-content">
				<div class="aie-content-types">
					<label class="aie-content-type">
						<input type="radio" name="content_type" value="post" checked>
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-post"></span>
							<h3><?php esc_html_e( 'Posts', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export blog posts, pages, or custom post types', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="media">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-media"></span>
							<h3><?php esc_html_e( 'Media', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export media files metadata', 'wp-aie' ); ?></p>
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

		<!-- Step 2: Filters -->
		<div class="aie-step aie-step-2" data-step="2">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 2: Filter Data', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Apply filters to select specific data to export', 'wp-aie' ); ?></p>
			</div>

			<div class="aie-step-content">
				<table class="form-table aie-export-filters">
					<!-- Post Filters -->
					<tbody class="aie-post-filters">
						<tr>
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

						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Post Status', 'wp-aie' ); ?></label>
							</th>
							<td>
								<select name="post_status[]" class="regular-text" multiple size="4">
									<option value="publish" selected><?php esc_html_e( 'Published', 'wp-aie' ); ?></option>
									<option value="draft"><?php esc_html_e( 'Draft', 'wp-aie' ); ?></option>
									<option value="pending"><?php esc_html_e( 'Pending', 'wp-aie' ); ?></option>
									<option value="private"><?php esc_html_e( 'Private', 'wp-aie' ); ?></option>
									<option value="trash"><?php esc_html_e( 'Trash', 'wp-aie' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple', 'wp-aie' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Date Range', 'wp-aie' ); ?></label>
							</th>
							<td>
								<input type="date" name="date_from" class="regular-text" placeholder="<?php esc_attr_e( 'From', 'wp-aie' ); ?>">
								<span> – </span>
								<input type="date" name="date_to" class="regular-text" placeholder="<?php esc_attr_e( 'To', 'wp-aie' ); ?>">
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Author', 'wp-aie' ); ?></label>
							</th>
							<td>
								<select name="author" class="regular-text">
									<option value=""><?php esc_html_e( 'All Authors', 'wp-aie' ); ?></option>
									<?php
									$authors = get_users( [ 'who' => 'authors' ] );
									foreach ( $authors as $author ) {
										printf(
											'<option value="%d">%s</option>',
											$author->ID,
											esc_html( $author->display_name )
										);
									}
									?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Category', 'wp-aie' ); ?></label>
							</th>
							<td>
								<select name="category" class="regular-text">
									<option value=""><?php esc_html_e( 'All Categories', 'wp-aie' ); ?></option>
									<?php
									$categories = get_categories( [ 'hide_empty' => false ] );
									foreach ( $categories as $category ) {
										printf(
											'<option value="%d">%s (%d)</option>',
											$category->term_id,
											esc_html( $category->name ),
											$category->count
										);
									}
									?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Tag', 'wp-aie' ); ?></label>
							</th>
							<td>
								<input type="text" name="tag" class="regular-text" placeholder="<?php esc_attr_e( 'Tag name or slug', 'wp-aie' ); ?>">
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Search', 'wp-aie' ); ?></label>
							</th>
							<td>
								<input type="text" name="search" class="regular-text" placeholder="<?php esc_attr_e( 'Search in title and content', 'wp-aie' ); ?>">
							</td>
						</tr>
					</tbody>

					<!-- Media Filters -->
					<tbody class="aie-media-filters" style="display:none;">
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Media Type', 'wp-aie' ); ?></label>
							</th>
							<td>
								<select name="mime_type" class="regular-text">
									<option value=""><?php esc_html_e( 'All Types', 'wp-aie' ); ?></option>
									<option value="image"><?php esc_html_e( 'Images', 'wp-aie' ); ?></option>
									<option value="video"><?php esc_html_e( 'Videos', 'wp-aie' ); ?></option>
									<option value="audio"><?php esc_html_e( 'Audio', 'wp-aie' ); ?></option>
									<option value="application"><?php esc_html_e( 'Documents', 'wp-aie' ); ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Upload Date', 'wp-aie' ); ?></label>
							</th>
							<td>
								<input type="date" name="media_date_from" class="regular-text">
								<span> – </span>
								<input type="date" name="media_date_to" class="regular-text">
							</td>
						</tr>
					</tbody>
				</table>

				<div class="aie-filter-summary">
					<h3><?php esc_html_e( 'Items to Export', 'wp-aie' ); ?></h3>
					<div class="aie-item-count">
						<div class="spinner is-active"></div>
						<span class="aie-count-value">-</span>
					</div>
					<button type="button" class="button aie-refresh-count">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh Count', 'wp-aie' ); ?>
					</button>
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

		<!-- Step 3: Select Fields -->
		<div class="aie-step aie-step-3" data-step="3">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Step 3: Select Fields', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose which fields to include in the export', 'wp-aie' ); ?></p>
			</div>

			<div class="aie-step-content">
				<div class="aie-field-selection-controls">
					<button type="button" class="button aie-select-all-fields">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Select All', 'wp-aie' ); ?>
					</button>
					<button type="button" class="button aie-deselect-all-fields">
						<span class="dashicons dashicons-no-alt"></span>
						<?php esc_html_e( 'Deselect All', 'wp-aie' ); ?>
					</button>
					<button type="button" class="button aie-select-common-fields">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e( 'Common Fields', 'wp-aie' ); ?>
					</button>
				</div>

				<div class="aie-field-groups">
					<!-- Post Fields -->
					<div class="aie-field-group aie-post-field-group">
						<h3><?php esc_html_e( 'Basic Fields', 'wp-aie' ); ?></h3>
						<div class="aie-fields-grid">
							<label><input type="checkbox" name="fields[]" value="ID" checked> ID</label>
							<label><input type="checkbox" name="fields[]" value="post_title" checked> <?php esc_html_e( 'Title', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="post_content" checked> <?php esc_html_e( 'Content', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="post_excerpt"> <?php esc_html_e( 'Excerpt', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="post_status" checked> <?php esc_html_e( 'Status', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="post_author"> <?php esc_html_e( 'Author', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="post_date"> <?php esc_html_e( 'Date', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="post_name"> <?php esc_html_e( 'Slug', 'wp-aie' ); ?></label>
						</div>
					</div>

					<div class="aie-field-group aie-post-field-group">
						<h3><?php esc_html_e( 'Additional Fields', 'wp-aie' ); ?></h3>
						<div class="aie-fields-grid">
							<label><input type="checkbox" name="fields[]" value="post_parent"> <?php esc_html_e( 'Parent ID', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="menu_order"> <?php esc_html_e( 'Menu Order', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="comment_status"> <?php esc_html_e( 'Comment Status', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="ping_status"> <?php esc_html_e( 'Ping Status', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="guid"> GUID</label>
							<label><input type="checkbox" name="fields[]" value="post_modified"> <?php esc_html_e( 'Modified Date', 'wp-aie' ); ?></label>
						</div>
					</div>

					<div class="aie-field-group aie-post-field-group">
						<h3><?php esc_html_e( 'Taxonomies & Meta', 'wp-aie' ); ?></h3>
						<div class="aie-fields-grid">
							<label><input type="checkbox" name="fields[]" value="categories"> <?php esc_html_e( 'Categories', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="tags"> <?php esc_html_e( 'Tags', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="featured_image"> <?php esc_html_e( 'Featured Image', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="post_meta"> <?php esc_html_e( 'All Post Meta', 'wp-aie' ); ?></label>
						</div>
					</div>

					<!-- Media Fields -->
					<div class="aie-field-group aie-media-field-group" style="display:none;">
						<h3><?php esc_html_e( 'Media Fields', 'wp-aie' ); ?></h3>
						<div class="aie-fields-grid">
							<label><input type="checkbox" name="fields[]" value="ID" checked> ID</label>
							<label><input type="checkbox" name="fields[]" value="post_title" checked> <?php esc_html_e( 'Title', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="post_mime_type" checked> <?php esc_html_e( 'MIME Type', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="guid" checked> <?php esc_html_e( 'URL', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="file_path"> <?php esc_html_e( 'File Path', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="file_size"> <?php esc_html_e( 'File Size', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="dimensions"> <?php esc_html_e( 'Dimensions', 'wp-aie' ); ?></label>
							<label><input type="checkbox" name="fields[]" value="alt_text"> <?php esc_html_e( 'Alt Text', 'wp-aie' ); ?></label>
						</div>
					</div>
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

		<!-- Step 5: Export Progress -->
		<div class="aie-step aie-step-5" data-step="5">
			<div class="aie-step-header">
				<h2><?php esc_html_e( 'Export in Progress', 'wp-aie' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Please wait while your data is being exported', 'wp-aie' ); ?></p>
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

				<div class="aie-export-results" style="display:none;">
					<div class="notice notice-success">
						<h3><?php esc_html_e( 'Export Completed!', 'wp-aie' ); ?></h3>
						<ul class="aie-results-list">
							<li>
								<?php esc_html_e( 'Total Exported:', 'wp-aie' ); ?>
								<strong class="aie-result-processed">0</strong>
							</li>
							<li>
								<?php esc_html_e( 'File Size:', 'wp-aie' ); ?>
								<strong class="aie-result-filesize">0 KB</strong>
							</li>
							<li>
								<?php esc_html_e( 'Duration:', 'wp-aie' ); ?>
								<strong class="aie-result-duration">0s</strong>
							</li>
						</ul>
						<button type="button" class="button button-primary button-large aie-download-file">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Download Export File', 'wp-aie' ); ?>
						</button>
					</div>
				</div>

				<div class="aie-step-actions">
					<button type="button" class="button button-secondary aie-cancel-export">
						<span class="dashicons dashicons-no"></span>
						<?php esc_html_e( 'Cancel Export', 'wp-aie' ); ?>
					</button>
					<button type="button" class="button button-primary aie-new-export" style="display:none;">
						<span class="dashicons dashicons-plus"></span>
						<?php esc_html_e( 'New Export', 'wp-aie' ); ?>
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
				<div class="aie-step-label"><?php esc_html_e( 'Filters', 'wp-aie' ); ?></div>
			</div>
			<div class="aie-step-indicator" data-step="3">
				<div class="aie-step-number">3</div>
				<div class="aie-step-label"><?php esc_html_e( 'Fields', 'wp-aie' ); ?></div>
			</div>
			<div class="aie-step-indicator" data-step="4">
				<div class="aie-step-number">4</div>
				<div class="aie-step-label"><?php esc_html_e( 'Format', 'wp-aie' ); ?></div>
			</div>
			<div class="aie-step-indicator" data-step="5">
				<div class="aie-step-number">5</div>
				<div class="aie-step-label"><?php esc_html_e( 'Export', 'wp-aie' ); ?></div>
			</div>
		</div>

	</div>
</div>
