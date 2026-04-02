<?php
/**
 * Functions Page Header and Filters
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<h1>
	<?php esc_html_e( 'Custom Functions', 'advanced-import-export' ); ?>
	<button type="button" class="page-title-action aie-new-function">
		<span class="dashicons dashicons-plus-alt"></span>
		<?php esc_html_e( 'New Function', 'advanced-import-export' ); ?>
	</button>
	<button type="button" class="page-title-action aie-browse-library">
		<span class="dashicons dashicons-book"></span>
		<?php esc_html_e( 'Browse Library', 'advanced-import-export' ); ?>
	</button>
</h1>

<p class="description">
	<?php esc_html_e( 'Create custom PHP functions to transform data during import and export. Browse the library for ready-to-use examples.', 'advanced-import-export' ); ?>
</p>

<!-- Filters -->
<div class="aie-functions-filters">
	<div class="aie-filter-group">
		<label for="aie-filter-search"><?php esc_html_e( 'Search:', 'advanced-import-export' ); ?></label>
		<input type="text" id="aie-filter-search" placeholder="<?php esc_attr_e( 'Search functions...', 'advanced-import-export' ); ?>">
	</div>

	<button type="button" class="button aie-filter-clear">
		<?php esc_html_e( 'Clear Filters', 'advanced-import-export' ); ?>
	</button>
</div>

