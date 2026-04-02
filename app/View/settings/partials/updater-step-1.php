<?php
/**
 * Content Updater Step 1: Select Content Type
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 1: Select Content Type -->
<div class="aie-step aie-updater-step-1 active" data-step="1">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 1: Select Content Type', 'advanced-import-export' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose what type of content you want to update', 'advanced-import-export' ); ?></p>
	</div>

	<div class="aie-step-content">
		<?php
		// Check if premium is active
		$is_premium = function_exists( 'aie_fs' ) && aie_fs()->can_use_premium_code();
		?>
		
		<!-- Search/Filter Field -->
		<div class="aie-content-type-filter">
			<input 
				type="text" 
				id="aie-updater-content-type-search" 
				class="regular-text" 
				placeholder="<?php esc_attr_e( 'Search content types...', 'advanced-import-export' ); ?>"
				autocomplete="off"
			>
			<span class="dashicons dashicons-search"></span>
		</div>

		<!-- No Results Message -->
		<div class="aie-no-results" style="display:none;">
			<span class="dashicons dashicons-search"></span>
			<h3><?php esc_html_e( 'No content types found', 'advanced-import-export' ); ?></h3>
			<p><?php esc_html_e( 'Try adjusting your search terms', 'advanced-import-export' ); ?></p>
		</div>
		
		<div class="aie-content-types">
			<!-- Core Content Types -->
			<label class="aie-content-type">
				<input type="radio" name="updater_content_type" value="post" checked>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-post"></span>
					<h3><?php esc_html_e( 'Blog Posts', 'advanced-import-export' ); ?></h3>
					<p><?php esc_html_e( 'Update blog posts', 'advanced-import-export' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="updater_content_type" value="page"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-page"></span>
					<h3><?php esc_html_e( 'Pages', 'advanced-import-export' ); ?></h3>
					<p><?php esc_html_e( 'Update pages', 'advanced-import-export' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'advanced-import-export' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
			<input type="radio" name="updater_content_type" value="custom_post_types"<?php echo $is_premium ? '' : ' disabled'; ?>>
			<div class="aie-content-type-card">
				<span class="dashicons dashicons-admin-generic"></span>
				<h3><?php esc_html_e( 'Custom Post Types', 'advanced-import-export' ); ?></h3>
				<p><?php esc_html_e( 'Update custom post types', 'advanced-import-export' ); ?></p>
				<?php if ( ! $is_premium ) : ?>
					<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'advanced-import-export' ); ?></span>
				<?php endif; ?>
			</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="updater_content_type" value="user"<?php echo $is_premium ? '' : ' disabled'; ?>>
			<div class="aie-content-type-card">
				<span class="dashicons dashicons-admin-users"></span>
				<h3><?php esc_html_e( 'Users', 'advanced-import-export' ); ?></h3>
				<p><?php esc_html_e( 'Update user accounts', 'advanced-import-export' ); ?></p>
				<?php if ( ! $is_premium ) : ?>
					<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'advanced-import-export' ); ?></span>
				<?php endif; ?>
			</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="updater_content_type" value="comment">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-comments"></span>
					<h3><?php esc_html_e( 'Comments', 'advanced-import-export' ); ?></h3>
					<p><?php esc_html_e( 'Update comments', 'advanced-import-export' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="updater_content_type" value="taxonomy"<?php echo $is_premium ? '' : ' disabled'; ?>>
			<div class="aie-content-type-card">
				<span class="dashicons dashicons-tag"></span>
				<h3><?php esc_html_e( 'Taxonomy Terms', 'advanced-import-export' ); ?></h3>
				<p><?php esc_html_e( 'Update taxonomy terms', 'advanced-import-export' ); ?></p>
				<?php if ( ! $is_premium ) : ?>
					<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'advanced-import-export' ); ?></span>
				<?php endif; ?>
			</div>
			</label>

			<!-- WooCommerce Types -->
			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="updater_content_type" value="woo_product"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-products"></span>
					<h3><?php esc_html_e( 'WooCommerce Products', 'advanced-import-export' ); ?></h3>
					<p><?php esc_html_e( 'Update WooCommerce products', 'advanced-import-export' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'advanced-import-export' ); ?></span>
					<?php endif; ?>
				</div>
			</label>

			<?php endif; ?>

			<!-- Database Table -->
			<label class="aie-content-type<?php echo $is_premium ? '' : ' aie-premium-locked'; ?>">
				<input type="radio" name="updater_content_type" value="database_table"<?php echo $is_premium ? '' : ' disabled'; ?>>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-database-view"></span>
					<h3><?php esc_html_e( 'MySQL Database Table', 'advanced-import-export' ); ?></h3>
					<p><?php esc_html_e( 'Update database table records', 'advanced-import-export' ); ?></p>
					<?php if ( ! $is_premium ) : ?>
						<span class="aie-premium-badge"><?php esc_html_e( 'Premium', 'advanced-import-export' ); ?></span>
					<?php endif; ?>
				</div>
			</label>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-primary button-large aie-updater-next-step">
				<?php esc_html_e( 'Next Step', 'advanced-import-export' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
