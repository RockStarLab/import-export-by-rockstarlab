<?php
/**
 * Functions Page Header and Filters
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<h1>
	<?php esc_html_e( 'Custom Functions', 'import-export-by-rockstarlab' ); ?>
	<button type="button" class="page-title-action aie-new-function">
		<span class="dashicons dashicons-plus-alt"></span>
		<?php esc_html_e( 'New Function', 'import-export-by-rockstarlab' ); ?>
	</button>
	<button type="button" class="page-title-action aie-browse-library">
		<span class="dashicons dashicons-book"></span>
		<?php esc_html_e( 'Browse Library', 'import-export-by-rockstarlab' ); ?>
	</button>
</h1>

<p class="description">
	<?php esc_html_e( 'Create custom PHP functions to transform data during import and export. Browse the library for ready-to-use examples.', 'import-export-by-rockstarlab' ); ?>
</p>

<!-- Filters -->
<div class="aie-functions-filters">
	<div class="aie-filter-group">
		<label for="aie-filter-search"><?php esc_html_e( 'Search:', 'import-export-by-rockstarlab' ); ?></label>
		<input type="text" id="aie-filter-search" placeholder="<?php esc_attr_e( 'Search functions...', 'import-export-by-rockstarlab' ); ?>">
	</div>

	<button type="button" class="button aie-filter-clear">
		<?php esc_html_e( 'Clear Filters', 'import-export-by-rockstarlab' ); ?>
	</button>
</div>

