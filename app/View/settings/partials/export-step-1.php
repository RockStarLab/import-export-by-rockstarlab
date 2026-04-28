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
		$rsl_ie_pro_active      = \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active();
		$rsl_ie_promo_cta       = \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_cta();
		$rsl_ie_promo_dismissed = (bool) get_user_meta( get_current_user_id(), 'rsl_ie_dismiss_pro_promo_export', true );

		$rsl_ie_promo_title = __( 'Need more features? Buy PRO addon', 'import-export-by-rockstarlab' );
		$rsl_ie_promo_desc  = __( 'Get additional export content types by installing the PRO addon.', 'import-export-by-rockstarlab' );

		$rsl_ie_promo_features = \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_features( 'export' );
		$rsl_ie_trial_note     = __( 'Free 30-day PRO Addon trial is available for all new users.', 'import-export-by-rockstarlab' );
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

			<?php if ( $rsl_ie_pro_active ) : ?>
				<?php
				$rsl_ie_pro_types  = \RockStarLab\ImportExport\Helper\Pro_Addon::get_pro_content_type_cards( 'export' );
				$rsl_ie_pro_types  = apply_filters( 'rsl_ie_pro_export_content_types', $rsl_ie_pro_types );
				$rsl_ie_pro_locked = false;
				foreach ( $rsl_ie_pro_types as $rsl_ie_type ) :
					?>
					<label class="aie-content-type <?php echo $rsl_ie_pro_locked ? 'aie-premium-locked' : ''; ?>">
						<input type="radio" name="content_type" value="<?php echo esc_attr( $rsl_ie_type['value'] ); ?>" <?php echo $rsl_ie_pro_locked ? 'disabled' : ''; ?>>
						<div class="aie-content-type-card">
							<span class="dashicons <?php echo esc_attr( $rsl_ie_type['icon'] ?? 'dashicons-star-filled' ); ?>"></span>
							<h3><?php echo esc_html( $rsl_ie_type['title'] ?? '' ); ?></h3>
							<p><?php echo esc_html( $rsl_ie_type['description'] ?? '' ); ?></p>
						</div>
					</label>
				<?php endforeach; ?>
			<?php elseif ( ! $rsl_ie_promo_dismissed ) : ?>
				<div class="aie-content-type aie-pro-addon-card">
					<div class="aie-content-type-card">
						<div class="aie-pro-addon-header">
							<div class="aie-pro-addon-icon">
								<span class="dashicons dashicons-star-filled"></span>
							</div>
							<div class="aie-pro-addon-copy">
								<h3><?php echo esc_html( $rsl_ie_promo_title ); ?></h3>
								<p><?php echo esc_html( $rsl_ie_promo_desc ); ?></p>
							</div>
						</div>

						<?php if ( ! $rsl_ie_pro_active ) : ?>
							<div class="aie-pro-addon-trial">
								<span class="dashicons dashicons-calendar-alt"></span>
								<span class="aie-pro-addon-trial-text"><?php echo esc_html( $rsl_ie_trial_note ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( ! $rsl_ie_pro_active && ! empty( $rsl_ie_promo_features ) ) : ?>
							<ul class="aie-pro-addon-features">
								<?php foreach ( $rsl_ie_promo_features as $rsl_ie_feature ) : ?>
									<li>
										<span class="dashicons dashicons-yes-alt"></span>
										<div class="aie-pro-addon-feature-text">
											<strong><?php echo esc_html( $rsl_ie_feature['title'] ?? '' ); ?></strong>
											<span><?php echo esc_html( $rsl_ie_feature['description'] ?? '' ); ?></span>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<a
							href="<?php echo esc_url( $rsl_ie_promo_cta['url'] ); ?>"
							class="button button-primary aie-pro-addon-cta"
							target="_blank" rel="noopener noreferrer"
						>
							<?php echo esc_html( $rsl_ie_promo_cta['label'] ); ?>
						</a>

						<?php if ( ! $rsl_ie_pro_active ) : ?>
							<div class="aie-pro-addon-dismiss">
								<button type="button" class="button-link aie-pro-addon-hide" data-context="export">
									<?php esc_html_e( 'Hide', 'import-export-by-rockstarlab' ); ?>
								</button>
								<span class="aie-pro-addon-dismiss-sep">·</span>
								<button type="button" class="button-link aie-pro-addon-dismiss-forever" data-context="export">
									<?php esc_html_e( "Don't show again", 'import-export-by-rockstarlab' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="aie-step-actions">
			<button type="button" class="button button-primary button-large aie-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
