<?php
/**
 * Browse Remote Posts Modal Template
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="rsl-ie-browse-modal" class="rsl-ie-modal rsl-ie-browse-library-modal" style="display: none;">
	<div class="rsl-ie-modal-backdrop"></div>
	<div class="rsl-ie-modal-content rsl-ie-browse-library-content">
		<div class="rsl-ie-modal-header">
			<h2 class="rsl-ie-modal-title">
				<span class="dashicons dashicons-admin-post"></span>
				<?php esc_html_e( 'Browse Remote Posts', 'import-export-by-rockstarlab' ); ?>
			</h2>
			<button type="button" class="rsl-ie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="rsl-ie-browse-search-bar">
			<input type="text" id="rsl-ie-browse-search" class="widefat" placeholder="<?php esc_attr_e( 'Search posts...', 'import-export-by-rockstarlab' ); ?>">
		</div>

		<div class="rsl-ie-modal-body rsl-ie-browse-body">
			<!-- Sidebar with filters -->
			<div class="rsl-ie-browse-sidebar">
				<h3><?php esc_html_e( 'Filters', 'import-export-by-rockstarlab' ); ?></h3>
				
				<div class="rsl-ie-browse-filter-group">
					<h4><?php esc_html_e( 'Status', 'import-export-by-rockstarlab' ); ?></h4>
					<ul class="rsl-ie-filter-list" id="rsl-ie-browse-status-filter">
						<li class="rsl-ie-filter-item active" data-status="">
							<span class="dashicons dashicons-category"></span>
							<?php esc_html_e( 'All', 'import-export-by-rockstarlab' ); ?>
							<span class="rsl-ie-filter-count">0</span>
						</li>
						<li class="rsl-ie-filter-item" data-status="publish">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Published', 'import-export-by-rockstarlab' ); ?>
							<span class="rsl-ie-filter-count">0</span>
						</li>
						<li class="rsl-ie-filter-item" data-status="draft">
							<span class="dashicons dashicons-edit"></span>
							<?php esc_html_e( 'Draft', 'import-export-by-rockstarlab' ); ?>
							<span class="rsl-ie-filter-count">0</span>
						</li>
						<li class="rsl-ie-filter-item" data-status="pending">
							<span class="dashicons dashicons-clock"></span>
							<?php esc_html_e( 'Pending', 'import-export-by-rockstarlab' ); ?>
							<span class="rsl-ie-filter-count">0</span>
						</li>
					</ul>
				</div>

				<div class="rsl-ie-browse-selection-info">
					<strong><?php esc_html_e( 'Selected:', 'import-export-by-rockstarlab' ); ?></strong>
					<span id="rsl-ie-browse-selected-count">0</span>
				</div>
			</div>

			<!-- Main content area with posts tree -->
			<div class="rsl-ie-browse-main">
				<div id="rsl-ie-browse-loading" class="rsl-ie-loading-posts">
					<span class="spinner is-active"></span>
					<p><?php esc_html_e( 'Loading posts from remote site...', 'import-export-by-rockstarlab' ); ?></p>
				</div>

				<div id="rsl-ie-browse-posts-tree" class="rsl-ie-posts-tree" style="display: none;">
					<!-- Tree will be populated dynamically -->
				</div>

				<div id="rsl-ie-browse-pagination" class="rsl-ie-browse-pagination" style="display: none;">
					<button type="button" id="rsl-ie-browse-prev-page" class="button" disabled>
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
					</button>
					<span class="rsl-ie-pagination-info">
						<span id="rsl-ie-browse-current-page">1</span> / <span id="rsl-ie-browse-total-pages">1</span>
					</span>
					<button type="button" id="rsl-ie-browse-next-page" class="button">
						<?php esc_html_e( 'Next', 'import-export-by-rockstarlab' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				</div>
			</div>
		</div>

		<div class="rsl-ie-modal-footer rsl-ie-browse-footer">
			<button type="button" id="rsl-ie-browse-cancel-btn" class="button">
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" id="rsl-ie-browse-pull-btn" class="button button-primary" disabled>
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Pull Selected Posts', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
