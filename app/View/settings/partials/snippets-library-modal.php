<?php
/**
 * Snippets Library Modal
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Snippets Library Modal -->
<div id="aie-snippets-library-modal" class="aie-modal aie-library-modal" style="display:none;">
	<div class="aie-modal-backdrop"></div>
	<div class="aie-modal-content aie-library-content">
		<div class="aie-modal-header">
			<h2 class="aie-modal-title">
				<span class="dashicons dashicons-book"></span>
				<?php esc_html_e( 'Function Library', 'wp-aie' ); ?>
			</h2>
			<button type="button" class="aie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="aie-library-search">
			<input type="text" id="aie-snippet-search" class="widefat" placeholder="<?php esc_attr_e( 'Search snippets...', 'wp-aie' ); ?>">
		</div>

		<div class="aie-modal-body aie-library-body">
			<!-- Sidebar with categories -->
			<div class="aie-library-sidebar">
				<h3><?php esc_html_e( 'Categories', 'wp-aie' ); ?></h3>
				<ul class="aie-categories-list" id="aie-categories-list">
					<li class="aie-category-item active" data-category="">
						<span class="dashicons dashicons-category"></span>
						<?php esc_html_e( 'All Snippets', 'wp-aie' ); ?>
						<span class="aie-category-count">0</span>
					</li>
				</ul>
			</div>

			<!-- Main content area with snippet cards -->
			<div class="aie-library-main">
				<div class="aie-snippets-grid" id="aie-snippets-grid">
					<div class="aie-loading-snippets">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Loading snippets...', 'wp-aie' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
