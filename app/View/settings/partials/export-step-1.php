<?php
/**
 * Export Step 1: Select Content Type
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 1: Select Content Type -->
<div class="aie-step aie-step-1 active" data-step="1">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 1: Select Content Type', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose what type of data you want to export', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="aie-step-content">
		<?php
		// Check if premium is active
		$is_premium = function_exists( 'rsl_ie_fs' ) && rsl_ie_fs()->can_use_premium_code();
		?>
		
		<!-- Search/Filter Field -->
		<div class="aie-content-type-filter">
			<input 
				type="text" 
				id="aie-content-type-search" 
				class="regular-text" 
				placeholder="<?php esc_attr_e( 'Search content types...', 'import-export-by-rockstarlab' ); ?>"
				autocomplete="off"
			>
			<span class="dashicons dashicons-search"></span>
			<span class="aie-filter-count" style="display:none;">
				<span class="aie-filter-count-value">0</span> <?php esc_html_e( 'found', 'import-export-by-rockstarlab' ); ?>
			</span>
		</div>

		<!-- No Results Message -->
		<div class="aie-no-results" style="display:none;">
			<span class="dashicons dashicons-search"></span>
			<h3><?php esc_html_e( 'No content types found', 'import-export-by-rockstarlab' ); ?></h3>
			<p><?php esc_html_e( 'Try adjusting your search terms', 'import-export-by-rockstarlab' ); ?></p>
		</div>
		
		<div class="aie-content-types">
			<!-- Free Features: Blog Posts & Pages -->
			<label class="aie-content-type">
				<input type="radio" name="content_type" value="post" checked>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-post"></span>
					<h3><?php esc_html_e( 'Blog Posts', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export blog posts', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="page">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-page"></span>
					<h3><?php esc_html_e( 'Pages', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export pages', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<!-- Premium Features -->
			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="custom_post_types"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-generic"></span>
					<h3><?php esc_html_e( 'Custom Post Types', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export custom post types', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="media"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-media"></span>
					<h3><?php esc_html_e( 'Media', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export media files data', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="menu"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-menu"></span>
					<h3><?php esc_html_e( 'Menus', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export navigation menus', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="user"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-users"></span>
					<h3><?php esc_html_e( 'Users', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export user accounts', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="comment"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-comments"></span>
					<h3><?php esc_html_e( 'Comments', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export comments and reviews', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="taxonomy"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-category"></span>
					<h3><?php esc_html_e( 'Taxonomy Terms', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export categories, tags, and custom taxonomies', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="woo_product"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-products"></span>
					<h3><?php esc_html_e( 'WooCommerce Products', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export WooCommerce products', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="woo_order"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-cart"></span>
					<h3><?php esc_html_e( 'WooCommerce Orders (8.0+)', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export WooCommerce orders', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="woo_coupon"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-tickets-alt"></span>
					<h3><?php esc_html_e( 'WooCommerce Coupons', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export WooCommerce coupons', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="woo_attribute"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-tag"></span>
					<h3><?php esc_html_e( 'WooCommerce Attributes', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export WooCommerce attributes', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="content_type" value="database_table"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-database-view"></span>
					<h3><?php esc_html_e( 'MySQL Database Table', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Export any MySQL table fields', 'import-export-by-rockstarlab' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'import-export-by-rockstarlab' ); ?></span>
					<?php endif; ?>
				</div>
			</label>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-primary button-large aie-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
