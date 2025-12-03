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
				<?php
				// Check if premium is active
				$is_premium = function_exists( 'waie_fs' ) && waie_fs()->is_premium();
				?>
				
				<!-- Search/Filter Field -->
				<div class="aie-content-type-filter">
					<input 
						type="text" 
						id="aie-content-type-search" 
						class="regular-text" 
						placeholder="<?php esc_attr_e( 'Search content types...', 'wp-aie' ); ?>"
						autocomplete="off"
					>
					<span class="dashicons dashicons-search"></span>
					<span class="aie-filter-count" style="display:none;">
						<span class="aie-filter-count-value">0</span> <?php esc_html_e( 'found', 'wp-aie' ); ?>
					</span>
				</div>

				<!-- No Results Message -->
				<div class="aie-no-results" style="display:none;">
					<span class="dashicons dashicons-search"></span>
					<h3><?php esc_html_e( 'No content types found', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Try adjusting your search terms', 'wp-aie' ); ?></p>
				</div>
				
				<div class="aie-content-types">
					<!-- Free Features -->
					<label class="aie-content-type">
						<input type="radio" name="content_type" value="post" checked>
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-post"></span>
							<h3><?php esc_html_e( 'Blog Posts', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export blog posts', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="page">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-page"></span>
							<h3><?php esc_html_e( 'Pages', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export pages', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="media">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-media"></span>
							<h3><?php esc_html_e( 'Media', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export media files data', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="menu">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-menu"></span>
							<h3><?php esc_html_e( 'Menus', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export navigation menus', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="user">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-users"></span>
							<h3><?php esc_html_e( 'Users', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export user accounts', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="comment">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-comments"></span>
							<h3><?php esc_html_e( 'Comments', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export comments and reviews', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="block_theme_settings">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-admin-customizer"></span>
							<h3><?php esc_html_e( 'Block Theme Settings', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export block theme customizations', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="taxonomy">
						<div class="aie-content-type-card">
							<span class="dashicons dashicons-category"></span>
							<h3><?php esc_html_e( 'Taxonomy Terms', 'wp-aie' ); ?></h3>
							<p><?php esc_html_e( 'Export categories, tags, and custom taxonomies', 'wp-aie' ); ?></p>
						</div>
					</label>

					<!-- Premium Features -->
					<label class="aie-content-type">
						<input type="radio" name="content_type" value="custom_post_types" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
						<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
							<span class="dashicons dashicons-admin-post"></span>
							<h3><?php esc_html_e( 'Custom Post Types', 'wp-aie' ); ?></h3>
							<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Export custom post types', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="woo_product" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
						<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
							<span class="dashicons dashicons-products"></span>
							<h3><?php esc_html_e( 'WooCommerce Products', 'wp-aie' ); ?></h3>
							<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Export WooCommerce products', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="woo_order" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
						<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
							<span class="dashicons dashicons-cart"></span>
							<h3><?php esc_html_e( 'WooCommerce Orders', 'wp-aie' ); ?></h3>
							<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Export WooCommerce orders', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="woo_coupon" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
						<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
							<span class="dashicons dashicons-tickets-alt"></span>
							<h3><?php esc_html_e( 'WooCommerce Coupons', 'wp-aie' ); ?></h3>
							<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Export WooCommerce coupons', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="woo_attribute" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
						<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
							<span class="dashicons dashicons-tag"></span>
							<h3><?php esc_html_e( 'WooCommerce Attributes', 'wp-aie' ); ?></h3>
							<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Export WooCommerce attributes', 'wp-aie' ); ?></p>
						</div>
					</label>

					<label class="aie-content-type">
						<input type="radio" name="content_type" value="custom_table" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
						<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
							<span class="dashicons dashicons-database"></span>
							<h3><?php esc_html_e( 'MySQL Table', 'wp-aie' ); ?></h3>
							<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Export any database table data', 'wp-aie' ); ?></p>
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
				
				<!-- Item Count Summary (Top) -->
				<div class="aie-filter-summary-top">
					<div class="aie-summary-card">
						<div class="aie-summary-icon">
							<span class="dashicons dashicons-database"></span>
						</div>
						<div class="aie-summary-content">
							<div class="aie-summary-label"><?php esc_html_e( 'Total Items Available', 'wp-aie' ); ?></div>
							<div class="aie-item-count">
								<span class="aie-count-value">-</span>
								<div class="spinner"></div>
							</div>
						</div>
						<button type="button" class="button aie-refresh-count">
							<span class="dashicons dashicons-update"></span>
						</button>
					</div>
				</div>

				<!-- Custom Filters Section -->
				<div class="aie-custom-filters-section">
					<div class="aie-section-header">
						<h3>
							<span class="dashicons dashicons-filter"></span>
							<?php esc_html_e( 'Customize Filters', 'wp-aie' ); ?>
						</h3>
						<p class="description"><?php esc_html_e( 'Add custom filters to narrow down your export', 'wp-aie' ); ?></p>
					</div>

					<!-- Filters Container -->
					<div class="aie-filters-list" id="aie-filters-list">
						<!-- Filters will be added here dynamically -->
					</div>

					<!-- Add Filter Button -->
					<div class="aie-add-filter-wrap">
						<button type="button" class="button button-secondary aie-add-filter">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add Filter', 'wp-aie' ); ?>
						</button>
					</div>
				</div>

				<!-- Hidden Template for Filter Row -->
				<template id="aie-filter-row-template">
					<div class="aie-filter-row">
						<div class="aie-filter-row-inner">
							<!-- Field Selection -->
							<div class="aie-filter-field-wrap">
								<label><?php esc_html_e( 'Field', 'wp-aie' ); ?></label>
								<select class="aie-filter-field" name="filter_field[]">
									<option value=""><?php esc_html_e( 'Select Field...', 'wp-aie' ); ?></option>
								</select>
							</div>

							<!-- Condition Selection -->
							<div class="aie-filter-condition-wrap">
								<label><?php esc_html_e( 'Condition', 'wp-aie' ); ?></label>
								<select class="aie-filter-condition" name="filter_condition[]">
									<option value=""><?php esc_html_e( 'Select...', 'wp-aie' ); ?></option>
								</select>
							</div>

							<!-- Value Input -->
							<div class="aie-filter-value-wrap">
								<label><?php esc_html_e( 'Value', 'wp-aie' ); ?></label>
								<input type="text" class="aie-filter-value" name="filter_value[]" placeholder="<?php esc_attr_e( 'Enter value...', 'wp-aie' ); ?>">
							</div>

							<!-- Remove Button -->
							<div class="aie-filter-actions">
								<button type="button" class="button button-link-delete aie-remove-filter" title="<?php esc_attr_e( 'Remove filter', 'wp-aie' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</div>
					</div>
				</template>

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
					<div class="notice notice-success" style="display:none;">
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
						<p>
							<button type="button" class="button button-primary button-large aie-download-file">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Download Export File', 'wp-aie' ); ?>
							</button>
						</p>
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
