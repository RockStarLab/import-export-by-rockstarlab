<?php
/**
 * Content Updater Step 1: Select Content Type
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 1: Select Content Type -->
<div class="aie-step aie-updater-step-1 active" data-step="1">
	<div class="aie-step-header">
		<h2><?php esc_html_e( 'Step 1: Select Content Type', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose what type of content you want to update', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="aie-step-content">
		<?php
		$pro_active      = \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active();
		$promo_cta       = \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_cta();
		$promo_dismissed = (bool) get_user_meta( get_current_user_id(), 'rsl_ie_dismiss_pro_promo_updater', true );

		$promo_title = __( 'Need more features? Buy PRO addon', 'import-export-by-rockstarlab' );
		$promo_desc  = __( 'Get additional updater content types by installing the PRO addon.', 'import-export-by-rockstarlab' );

		$promo_features = \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_features( 'updater' );
		$trial_note     = __( 'Free 30-day PRO Addon trial is available for all new users.', 'import-export-by-rockstarlab' );
		?>
		
		<!-- Search/Filter Field -->
		<div class="aie-content-type-filter">
			<input 
				type="text" 
				id="aie-updater-content-type-search" 
				class="regular-text" 
				placeholder="<?php esc_attr_e( 'Search content types...', 'import-export-by-rockstarlab' ); ?>"
				autocomplete="off"
			>
			<span class="dashicons dashicons-search"></span>
		</div>

		<!-- No Results Message -->
		<div class="aie-no-results" style="display:none;">
			<span class="dashicons dashicons-search"></span>
			<h3><?php esc_html_e( 'No content types found', 'import-export-by-rockstarlab' ); ?></h3>
			<p><?php esc_html_e( 'Try adjusting your search terms', 'import-export-by-rockstarlab' ); ?></p>
		</div>
		
		<div class="aie-content-types">
			<!-- Core Content Types -->
			<label class="aie-content-type">
				<input type="radio" name="updater_content_type" value="post" checked>
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-post"></span>
					<h3><?php esc_html_e( 'Blog Posts', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Update blog posts', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<label class="aie-content-type">
				<input type="radio" name="updater_content_type" value="comment">
				<div class="aie-content-type-card">
					<span class="dashicons dashicons-admin-comments"></span>
					<h3><?php esc_html_e( 'Comments', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Update comments', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<?php if ( $pro_active ) : ?>
				<?php
				$pro_types  = \RockStarLab\ImportExport\Helper\Pro_Addon::get_pro_content_type_cards( 'updater' );
				$pro_types  = apply_filters( 'rsl_ie_pro_updater_content_types', $pro_types );
				$pro_locked = false;
				foreach ( $pro_types as $type ) :
					?>
					<label class="aie-content-type <?php echo $pro_locked ? 'aie-premium-locked' : ''; ?>">
						<input type="radio" name="updater_content_type" value="<?php echo esc_attr( $type['value'] ); ?>" <?php echo $pro_locked ? 'disabled' : ''; ?>>
						<div class="aie-content-type-card">
							<span class="dashicons <?php echo esc_attr( $type['icon'] ?? 'dashicons-star-filled' ); ?>"></span>
							<h3><?php echo esc_html( $type['title'] ?? '' ); ?></h3>
							<p><?php echo esc_html( $type['description'] ?? '' ); ?></p>
						</div>
					</label>
				<?php endforeach; ?>
			<?php elseif ( ! $promo_dismissed ) : ?>
				<div class="aie-content-type aie-pro-addon-card">
					<div class="aie-content-type-card">
						<div class="aie-pro-addon-header">
							<div class="aie-pro-addon-icon">
								<span class="dashicons dashicons-star-filled"></span>
							</div>
							<div class="aie-pro-addon-copy">
								<h3><?php echo esc_html( $promo_title ); ?></h3>
								<p><?php echo esc_html( $promo_desc ); ?></p>
							</div>
						</div>

						<?php if ( ! $pro_active ) : ?>
							<div class="aie-pro-addon-trial">
								<span class="dashicons dashicons-calendar-alt"></span>
								<span class="aie-pro-addon-trial-text"><?php echo esc_html( $trial_note ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( ! $pro_active && ! empty( $promo_features ) ) : ?>
							<ul class="aie-pro-addon-features">
								<?php foreach ( $promo_features as $feature ) : ?>
									<li>
										<span class="dashicons dashicons-yes-alt"></span>
										<div class="aie-pro-addon-feature-text">
											<strong><?php echo esc_html( $feature['title'] ?? '' ); ?></strong>
											<span><?php echo esc_html( $feature['description'] ?? '' ); ?></span>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<a
							href="<?php echo esc_url( $promo_cta['url'] ); ?>"
							class="button button-primary aie-pro-addon-cta"
							target="_blank" rel="noopener noreferrer"
						>
							<?php echo esc_html( $promo_cta['label'] ); ?>
						</a>

						<?php if ( ! $pro_active ) : ?>
							<div class="aie-pro-addon-dismiss">
								<button type="button" class="button-link aie-pro-addon-hide" data-context="updater">
									<?php esc_html_e( 'Hide', 'import-export-by-rockstarlab' ); ?>
								</button>
								<span class="aie-pro-addon-dismiss-sep">·</span>
								<button type="button" class="button-link aie-pro-addon-dismiss-forever" data-context="updater">
									<?php esc_html_e( "Don't show again", 'import-export-by-rockstarlab' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-primary button-large aie-updater-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
