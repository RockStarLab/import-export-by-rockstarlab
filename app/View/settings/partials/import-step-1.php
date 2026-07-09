<?php
/**
 * Import Step 1: Select Content Type
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Step 1: Select Content Type -->
<div class="rsl-ie-step rsl-ie-step-1 active" data-step="1">
	<div class="rsl-ie-step-header">
		<h2><?php esc_html_e( 'Step 1: Select Content Type', 'import-export-by-rockstarlab' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Choose what type of data you want to import', 'import-export-by-rockstarlab' ); ?></p>
	</div>

	<div class="rsl-ie-step-content">
		<?php
		$rsl_ie_pro_active      = \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active();
		$rsl_ie_promo_cta       = \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_cta();
		$rsl_ie_promo_dismissed = (bool) get_user_meta( get_current_user_id(), 'rsl_ie_dismiss_pro_promo_import', true );

		$rsl_ie_promo_title = __( 'Need more features? Buy PRO addon', 'import-export-by-rockstarlab' );
		$rsl_ie_promo_desc  = __( 'Get additional import content types by installing the PRO addon.', 'import-export-by-rockstarlab' );

			$rsl_ie_promo_features = \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_features( 'import' );
		?>

			<?php if ( $rsl_ie_pro_active ) : ?>
				<?php do_action( 'rsl_ie_pro_license_notice', 'import' ); ?>
			<?php endif; ?>
			
			<!-- Search/Filter Field -->
			<div class="rsl-ie-content-type-filter">
				<input 
				type="text" 
				id="rsl-ie-content-type-search" 
				class="regular-text" 
				placeholder="<?php esc_attr_e( 'Search content types...', 'import-export-by-rockstarlab' ); ?>"
				autocomplete="off"
			>
			<span class="dashicons dashicons-search"></span>
			<span class="rsl-ie-filter-count" style="display:none;">
				<span class="rsl-ie-filter-count-value">0</span> <?php esc_html_e( 'found', 'import-export-by-rockstarlab' ); ?>
			</span>
		</div>

		<!-- No Results Message -->
		<div class="rsl-ie-no-results" style="display:none;">
			<span class="dashicons dashicons-search"></span>
			<h3><?php esc_html_e( 'No content types found', 'import-export-by-rockstarlab' ); ?></h3>
			<p><?php esc_html_e( 'Try adjusting your search terms', 'import-export-by-rockstarlab' ); ?></p>
		</div>
		
		<div class="rsl-ie-content-types">
			<!-- Free Features: Blog Posts & Pages -->
			<label class="rsl-ie-content-type">
				<input type="radio" name="content_type" value="post" checked>
				<div class="rsl-ie-content-type-card">
					<span class="dashicons dashicons-admin-post"></span>
					<h3><?php esc_html_e( 'Blog Posts', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Import blog posts', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<label class="rsl-ie-content-type">
				<input type="radio" name="content_type" value="page">
				<div class="rsl-ie-content-type-card">
					<span class="dashicons dashicons-admin-page"></span>
					<h3><?php esc_html_e( 'Pages', 'import-export-by-rockstarlab' ); ?></h3>
					<p><?php esc_html_e( 'Import pages', 'import-export-by-rockstarlab' ); ?></p>
				</div>
			</label>

			<?php if ( $rsl_ie_pro_active ) : ?>
				<?php
				$rsl_ie_pro_types = \RockStarLab\ImportExport\Helper\Pro_Addon::get_pro_content_type_cards( 'import' );
				$rsl_ie_pro_types = apply_filters( 'rsl_ie_pro_import_content_types', $rsl_ie_pro_types );
				foreach ( $rsl_ie_pro_types as $rsl_ie_type ) :
					?>
					<label class="rsl-ie-content-type">
						<input type="radio" name="content_type" value="<?php echo esc_attr( $rsl_ie_type['value'] ); ?>">
						<div class="rsl-ie-content-type-card">
							<span class="dashicons <?php echo esc_attr( $rsl_ie_type['icon'] ?? 'dashicons-star-filled' ); ?>"></span>
							<h3><?php echo esc_html( $rsl_ie_type['title'] ?? '' ); ?></h3>
							<p><?php echo esc_html( $rsl_ie_type['description'] ?? '' ); ?></p>
						</div>
					</label>
				<?php endforeach; ?>
			<?php elseif ( ! $rsl_ie_promo_dismissed ) : ?>
				<div class="rsl-ie-content-type rsl-ie-pro-addon-card">
					<div class="rsl-ie-content-type-card">
						<div class="rsl-ie-pro-addon-header">
							<div class="rsl-ie-pro-addon-icon">
								<span class="dashicons dashicons-star-filled"></span>
							</div>
							<div class="rsl-ie-pro-addon-copy">
								<h3><?php echo esc_html( $rsl_ie_promo_title ); ?></h3>
								<p><?php echo esc_html( $rsl_ie_promo_desc ); ?></p>
							</div>
						</div>

						<?php if ( ! $rsl_ie_pro_active && ! empty( $rsl_ie_promo_features ) ) : ?>
							<ul class="rsl-ie-pro-addon-features">
								<?php foreach ( $rsl_ie_promo_features as $rsl_ie_feature ) : ?>
									<li>
										<span class="dashicons dashicons-yes-alt"></span>
										<div class="rsl-ie-pro-addon-feature-text">
											<strong><?php echo esc_html( $rsl_ie_feature['title'] ?? '' ); ?></strong>
											<span><?php echo esc_html( $rsl_ie_feature['description'] ?? '' ); ?></span>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

							<a
								href="<?php echo esc_url( $rsl_ie_promo_cta['url'] ); ?>"
								class="button button-primary rsl-ie-pro-addon-cta"
							>
								<?php echo esc_html( $rsl_ie_promo_cta['label'] ); ?>
							</a>

						<?php if ( ! $rsl_ie_pro_active ) : ?>
							<div class="rsl-ie-pro-addon-dismiss">
								<button type="button" class="button-link rsl-ie-pro-addon-hide" data-context="import">
									<?php esc_html_e( 'Hide', 'import-export-by-rockstarlab' ); ?>
								</button>
								<span class="rsl-ie-pro-addon-dismiss-sep">·</span>
								<button type="button" class="button-link rsl-ie-pro-addon-dismiss-forever" data-context="import">
									<?php esc_html_e( "Don't show again", 'import-export-by-rockstarlab' ); ?>
								</button>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="rsl-ie-step-actions">
			<button type="button" class="button button-primary button-large rsl-ie-next-step">
				<?php esc_html_e( 'Next Step', 'import-export-by-rockstarlab' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>
