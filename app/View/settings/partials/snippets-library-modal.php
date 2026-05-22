<?php
/**
 * Snippets Library Modal
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Snippets Library Modal -->
<div id="rsl-ie-snippets-library-modal" class="rsl-ie-modal rsl-ie-library-modal" style="display:none;">
	<div class="rsl-ie-modal-backdrop"></div>
	<div class="rsl-ie-modal-content rsl-ie-library-content">
		<div class="rsl-ie-modal-header">
			<h2 class="rsl-ie-modal-title">
				<span class="dashicons dashicons-book"></span>
				<?php esc_html_e( 'Function Library', 'import-export-by-rockstarlab' ); ?>
			</h2>
			<button type="button" class="rsl-ie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="rsl-ie-library-search">
			<input type="text" id="rsl-ie-snippet-search" class="widefat" placeholder="<?php esc_attr_e( 'Search snippets...', 'import-export-by-rockstarlab' ); ?>">
		</div>

		<div class="rsl-ie-modal-body rsl-ie-library-body">
			<!-- Sidebar with categories -->
			<div class="rsl-ie-library-sidebar">
				<h3><?php esc_html_e( 'Categories', 'import-export-by-rockstarlab' ); ?></h3>
				<ul class="rsl-ie-categories-list" id="rsl-ie-categories-list">
					<li class="rsl-ie-category-item active" data-category="">
						<span class="dashicons dashicons-category"></span>
						<?php esc_html_e( 'All Snippets', 'import-export-by-rockstarlab' ); ?>
						<span class="rsl-ie-category-count">0</span>
					</li>
				</ul>
			</div>

			<!-- Main content area with snippet cards -->
			<div class="rsl-ie-library-main">
				<div class="rsl-ie-snippets-grid" id="rsl-ie-snippets-grid">
					<div class="rsl-ie-loading-snippets">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Loading snippets...', 'import-export-by-rockstarlab' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
