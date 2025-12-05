<?php
/**
 * Functions Page Header and Filters
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<h1>
	<?php esc_html_e( 'Custom Functions', 'wp-aie' ); ?>
	<button type="button" class="page-title-action aie-new-function">
		<span class="dashicons dashicons-plus-alt"></span>
		<?php esc_html_e( 'New Function', 'wp-aie' ); ?>
	</button>
	<button type="button" class="page-title-action aie-browse-library">
		<span class="dashicons dashicons-book"></span>
		<?php esc_html_e( 'Browse Library', 'wp-aie' ); ?>
	</button>
</h1>

<p class="description">
	<?php esc_html_e( 'Create custom PHP functions to transform data during import and export. Browse the library for ready-to-use examples.', 'wp-aie' ); ?>
</p>

<!-- Filters -->
<div class="aie-functions-filters">
	<div class="aie-filter-group">
		<label for="aie-filter-status"><?php esc_html_e( 'Status:', 'wp-aie' ); ?></label>
		<select id="aie-filter-status">
			<option value=""><?php esc_html_e( 'All Statuses', 'wp-aie' ); ?></option>
			<option value="active"><?php esc_html_e( 'Active', 'wp-aie' ); ?></option>
			<option value="inactive"><?php esc_html_e( 'Inactive', 'wp-aie' ); ?></option>
		</select>
	</div>

	<div class="aie-filter-group">
		<label for="aie-filter-category"><?php esc_html_e( 'Category:', 'wp-aie' ); ?></label>
		<select id="aie-filter-category">
			<option value=""><?php esc_html_e( 'All Categories', 'wp-aie' ); ?></option>
			<option value="library"><?php esc_html_e( 'Library', 'wp-aie' ); ?></option>
			<option value="custom"><?php esc_html_e( 'Custom', 'wp-aie' ); ?></option>
		</select>
	</div>

	<div class="aie-filter-group">
		<label for="aie-filter-search"><?php esc_html_e( 'Search:', 'wp-aie' ); ?></label>
		<input type="text" id="aie-filter-search" placeholder="<?php esc_attr_e( 'Search functions...', 'wp-aie' ); ?>">
	</div>

	<button type="button" class="button aie-filter-clear">
		<?php esc_html_e( 'Clear Filters', 'wp-aie' ); ?>
	</button>
</div>
