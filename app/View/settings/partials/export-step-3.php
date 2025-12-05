<?php
/**
 * Export Step 3: Select Fields
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

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
