<?php
/**
 * Browse Remote Posts Modal Template
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="aie-browse-modal" class="aie-modal aie-browse-library-modal" style="display: none;">
	<div class="aie-modal-backdrop"></div>
	<div class="aie-modal-content aie-browse-library-content">
		<div class="aie-modal-header">
			<h2 class="aie-modal-title">
				<span class="dashicons dashicons-admin-post"></span>
				<?php esc_html_e( 'Browse Remote Posts', 'import-export-by-rockstarlab' ); ?>
			</h2>
			<button type="button" class="aie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="aie-browse-search-bar">
			<input type="text" id="aie-browse-search" class="widefat" placeholder="<?php esc_attr_e( 'Search posts...', 'import-export-by-rockstarlab' ); ?>">
		</div>

		<div class="aie-modal-body aie-browse-body">
			<!-- Sidebar with filters -->
			<div class="aie-browse-sidebar">
				<h3><?php esc_html_e( 'Filters', 'import-export-by-rockstarlab' ); ?></h3>
				
				<div class="aie-browse-filter-group">
					<h4><?php esc_html_e( 'Status', 'import-export-by-rockstarlab' ); ?></h4>
					<ul class="aie-filter-list" id="aie-browse-status-filter">
						<li class="aie-filter-item active" data-status="">
							<span class="dashicons dashicons-category"></span>
							<?php esc_html_e( 'All', 'import-export-by-rockstarlab' ); ?>
							<span class="aie-filter-count">0</span>
						</li>
						<li class="aie-filter-item" data-status="publish">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Published', 'import-export-by-rockstarlab' ); ?>
							<span class="aie-filter-count">0</span>
						</li>
						<li class="aie-filter-item" data-status="draft">
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e( 'Draft', 'import-export-by-rockstarlab' ); ?>
							<span class="aie-filter-count">0</span>
						</li>
						<li class="aie-filter-item" data-status="pending">
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Pending', 'import-export-by-rockstarlab' ); ?>
							<span class="aie-filter-count">0</span>
						</li>
					</ul>
				</div>

				<div class="aie-browse-selection-info">
					<strong><?php esc_html_e( 'Selected:', 'import-export-by-rockstarlab' ); ?></strong>
					<span id="aie-browse-selected-count">0</span>
				</div>
			</div>

			<!-- Main content area with posts tree -->
			<div class="aie-browse-main">
				<div id="aie-browse-loading" class="aie-loading-posts">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading posts from remote site...', 'import-export-by-rockstarlab' ); ?></p>
				</div>

				<div id="aie-browse-posts-tree" class="aie-posts-tree" style="display: none;">
					<!-- Tree will be populated dynamically -->
				</div>

				<div id="aie-browse-pagination" class="aie-browse-pagination" style="display: none;">
					<button type="button" id="aie-browse-prev-page" class="button" disabled>
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
					</button>
					<span class="aie-pagination-info">
						<span id="aie-browse-current-page">1</span> / <span id="aie-browse-total-pages">1</span>
					</span>
					<button type="button" id="aie-browse-next-page" class="button">
						<?php esc_html_e( 'Next', 'import-export-by-rockstarlab' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				</div>
			</div>
		</div>

		<div class="aie-modal-footer aie-browse-footer">
			<button type="button" id="aie-browse-cancel-btn" class="button">
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" id="aie-browse-pull-btn" class="button button-primary" disabled>
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Pull Selected Posts', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
