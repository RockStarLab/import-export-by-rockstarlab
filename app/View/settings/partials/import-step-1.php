<?php
/**
 * Import Step 1: Select Content Type
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 1: Select Content Type -->
<div class="aie-step aie-step-1 active" data-step="1">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 1: Select Content Type', 'wp-aie' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose what type of data you want to import', 'wp-aie' ); ?></p>
	</div>

	<div class="aie-step-content">
		<?php
		// Check if premium is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->is_premium();
		?>
		
		<!-- Search/Filter Field -->
		<div class="aie-content-type-filter">
			<input 
				type="text" 
				id="aie-content-type-search" 
				class="regular-text" 
				placeholder="<?php esc_attr_e( 'Search content types...', 'wp-aie' ); ?>"
				autocomplete="off"
			>
			<span class="dashicons dashicons-search"></span>
			<span class="aie-filter-count" style="display:none;">
				<span class="aie-filter-count-value">0</span> <?php esc_html_e( 'found', 'wp-aie' ); ?>
			</span>
		</div>

		<!-- No Results Message -->
		<div class="aie-no-results" style="display:none;">
			<span class="dashicons dashicons-search"></span>
			<h3><?php esc_html_e( 'No content types found', 'wp-aie' ); ?></h3>
			<p><?php esc_html_e( 'Try adjusting your search terms', 'wp-aie' ); ?></p>
		</div>
		
		<div class="aie-content-types">
			<!-- Free Features -->
			<label class="aie-content-type">
				<input type="radio" name="content_type" value="post" checked>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-post"></span>
					<h3><?php esc_html_e( 'Blog Posts', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Import blog posts', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="page">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-page"></span>
					<h3><?php esc_html_e( 'Pages', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Import pages', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="media">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-media"></span>
					<h3><?php esc_html_e( 'Media', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Import media files data', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="menu">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-menu"></span>
					<h3><?php esc_html_e( 'Menus', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Import navigation menus', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="user">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-users"></span>
					<h3><?php esc_html_e( 'Users', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Import user accounts', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="comment">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-comments"></span>
					<h3><?php esc_html_e( 'Comments', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Import comments and reviews', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="block_theme_settings">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-customizer"></span>
					<h3><?php esc_html_e( 'Block Theme Settings', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Import block theme customizations', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="taxonomy">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-category"></span>
					<h3><?php esc_html_e( 'Taxonomy Terms', 'wp-aie' ); ?></h3>
					<p><?php esc_html_e( 'Import categories, tags, and custom taxonomies', 'wp-aie' ); ?></p>
				</div>
			</label>

			<!-- Premium Features -->
			<label class="aie-content-type">
				<input type="radio" name="content_type" value="custom_post_types" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
				<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
					<span class="dashicons dashicons-admin-post"></span>
					<h3><?php esc_html_e( 'Custom Post Types', 'wp-aie' ); ?></h3>
					<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Import custom post types', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="woo_product" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
				<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
					<span class="dashicons dashicons-products"></span>
					<h3><?php esc_html_e( 'WooCommerce Products', 'wp-aie' ); ?></h3>
					<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Import WooCommerce products', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="woo_order" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
				<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
					<span class="dashicons dashicons-cart"></span>
					<h3><?php esc_html_e( 'WooCommerce Orders (8.0+)', 'wp-aie' ); ?></h3>
					<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Import WooCommerce orders', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="woo_coupon" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
				<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
					<span class="dashicons dashicons-tickets-alt"></span>
					<h3><?php esc_html_e( 'WooCommerce Coupons', 'wp-aie' ); ?></h3>
					<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Import WooCommerce coupons', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="woo_attribute" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
				<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
					<span class="dashicons dashicons-tag"></span>
					<h3><?php esc_html_e( 'WooCommerce Attributes', 'wp-aie' ); ?></h3>
					<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Import WooCommerce attributes', 'wp-aie' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="content_type" value="database_table" <?php echo ! $is_premium ? 'disabled' : ''; ?>>
				<div class="aie-content-type-card <?php echo ! $is_premium ? 'aie-disabled' : ''; ?>">
					<span class="dashicons dashicons-database-view"></span>
					<h3><?php esc_html_e( 'MySQL Database Table', 'wp-aie' ); ?></h3>
					<p><?php echo ! $is_premium ? esc_html__( 'Premium feature', 'wp-aie' ) : esc_html__( 'Import to any MySQL table', 'wp-aie' ); ?></p>
				</div>
			</label>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-primary button-large aie-next-step">
				<?php esc_html_e( 'Next Step', 'wp-aie' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
