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
