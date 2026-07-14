<?php
/**
 * Welcome Page Template — WordPress Dashboard style
 *
 * @package RockStarLab\ImportExport
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_plugin_version = defined( 'RSL_IE_VERSION' ) ? RSL_IE_VERSION : '1.0';
$rsl_ie_pro_active     = \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active();
?>

<div class="wrap rsl-ie-welcome-page">

	<h1 class="rsl-ie-welcome-title">
		<span class="dashicons dashicons-update-alt"></span>
		<?php esc_html_e( 'Import Export by RockStarLab', 'import-export-by-rockstarlab' ); ?>
		<span class="rsl-ie-version-badge"><?php echo esc_html( $rsl_ie_plugin_version ); ?></span>
	</h1>

	<!-- Welcome Panel -->
	<div class="rsl-ie-welcome-panel welcome-panel">
		<div class="rsl-ie-welcome-panel-content">
			<div class="rsl-ie-welcome-panel-header">
				<h2><?php esc_html_e( 'Welcome! Let\'s get started.', 'import-export-by-rockstarlab' ); ?></h2>
				<p class="about-description">
					<?php esc_html_e( 'Use the links below to quickly access the main features of Import Export by RockStarLab plugin.', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>
			<div class="rsl-ie-welcome-panel-column-container">
				<div class="rsl-ie-welcome-panel-column">
					<h3><?php esc_html_e( 'Get Started', 'import-export-by-rockstarlab' ); ?></h3>
					<a class="button button-hero button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-import' ) ); ?>">
						<?php esc_html_e( 'Import Content', 'import-export-by-rockstarlab' ); ?>
					</a>
					<a class="button button-hero button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-export' ) ); ?>">
						<?php esc_html_e( 'Export Content', 'import-export-by-rockstarlab' ); ?>
					</a>
				</div>
				<div class="rsl-ie-welcome-panel-column">
					<h3><?php esc_html_e( 'Next Steps', 'import-export-by-rockstarlab' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-networking"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-content-sync' ) ); ?>">
								<?php esc_html_e( 'Content Sync — sync between sites', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-format-image"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-media-sync' ) ); ?>">
								<?php esc_html_e( 'Media Sync — import media files', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-admin-links"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-ai-url-importer' ) ); ?>">
								<?php esc_html_e( 'AI URL Importer — import from URL', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
					</ul>
				</div>
				<div class="rsl-ie-welcome-panel-column rsl-ie-welcome-panel-last">
					<h3><?php esc_html_e( 'More', 'import-export-by-rockstarlab' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-list-view"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-jobs-log' ) ); ?>">
								<?php esc_html_e( 'Jobs Log', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-admin-settings"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-plugin-options' ) ); ?>">
								<?php esc_html_e( 'Plugin Options', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-book-alt"></span>
							<a href="https://wpimportexport.com/docs/" target="_blank">
								<?php esc_html_e( 'Documentation', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div><!-- .rsl-ie-welcome-panel -->

	<!-- Dashboard-style widget grid -->
	<div class="rsl-ie-dashboard-widgets-wrap">
		<div class="rsl-ie-dashboard-widgets-holder">

			<!-- Left column: Special Offer -->
			<div class="rsl-ie-postbox-container rsl-ie-postbox-container-left">

				<div class="rsl-ie-card <?php echo $rsl_ie_pro_active ? 'rsl-ie-card--pro-active' : 'rsl-ie-card--promo'; ?>">
					<div class="rsl-ie-card__header">
						<h2 class="rsl-ie-card__title">
							<?php if ( $rsl_ie_pro_active ) : ?>
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'You are using PRO Addon', 'import-export-by-rockstarlab' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-awards"></span>
								<?php esc_html_e( 'Import Export PRO Addon', 'import-export-by-rockstarlab' ); ?>
							<?php endif; ?>
						</h2>
					</div>
					<div class="rsl-ie-card__body">
							<?php if ( $rsl_ie_pro_active ) : ?>
								<div class="rsl-ie-pro-active-badge">
									<span class="dashicons dashicons-awards"></span>
									<?php esc_html_e( 'PRO active', 'import-export-by-rockstarlab' ); ?>
								</div>
								<p class="rsl-ie-pro-active-message">
									<?php esc_html_e( 'Thank you so much for your trust and support.', 'import-export-by-rockstarlab' ); ?>
								</p>
								<p class="rsl-ie-pro-active-copy">
									<?php esc_html_e( 'Advanced content types, transformations, and PRO workflows are ready whenever you need them.', 'import-export-by-rockstarlab' ); ?>
								</p>
									<div class="rsl-ie-pro-active-links" aria-label="<?php esc_attr_e( 'PRO tools', 'import-export-by-rockstarlab' ); ?>">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-content-updater' ) ); ?>" class="rsl-ie-pro-active-link">
											<span class="dashicons dashicons-update"></span>
											<span><?php esc_html_e( 'Content updater', 'import-export-by-rockstarlab' ); ?></span>
										</a>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-functions' ) ); ?>" class="rsl-ie-pro-active-link">
											<span class="dashicons dashicons-admin-tools"></span>
											<span><?php esc_html_e( 'Functions', 'import-export-by-rockstarlab' ); ?></span>
										</a>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-export' ) ); ?>" class="rsl-ie-pro-active-link">
											<span class="dashicons dashicons-upload"></span>
											<span><?php esc_html_e( 'Export', 'import-export-by-rockstarlab' ); ?></span>
										</a>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-import' ) ); ?>" class="rsl-ie-pro-active-link">
											<span class="dashicons dashicons-download"></span>
											<span><?php esc_html_e( 'Import', 'import-export-by-rockstarlab' ); ?></span>
										</a>
									</div>
							<?php else : ?>
							<p class="rsl-ie-promo-intro">
								<?php esc_html_e( 'Extend the plugin with additional workflows and integrations from the separate PRO addon.', 'import-export-by-rockstarlab' ); ?>
							</p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=import-export-by-rockstarlab-addons' ) ); ?>" class="button button-primary rsl-ie-promo-cta">
								<?php esc_html_e( 'Get PRO Addon →', 'import-export-by-rockstarlab' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

			</div><!-- .rsl-ie-postbox-container-left -->

			<!-- Middle column: Active Development Notice -->
			<div class="rsl-ie-postbox-container rsl-ie-postbox-container-middle">

				<div class="rsl-ie-card rsl-ie-card--notice">
					<div class="rsl-ie-card__header">
						<h2 class="rsl-ie-card__title">
							<span class="dashicons dashicons-megaphone"></span>
							<?php esc_html_e( 'Active Development', 'import-export-by-rockstarlab' ); ?>
						</h2>
					</div>
					<div class="rsl-ie-card__body">
						<p class="rsl-ie-notice-badge">
							<span class="dashicons dashicons-hammer"></span>
							<?php echo wp_kses_post( __( 'New &amp; Growing', 'import-export-by-rockstarlab' ) ); ?>
						</p>
						<p class="rsl-ie-notice-text">
							<?php echo wp_kses_post( __( 'This plugin is brand new and actively developed&nbsp;&mdash; new features and improvements are released regularly.', 'import-export-by-rockstarlab' ) ); ?>
						</p>
						<p class="rsl-ie-notice-text">
							<?php echo wp_kses_post( __( 'If you run into any issues, please reach out via the Support Forum or Email Support&nbsp;&mdash; we&rsquo;ll do our best to help you as quickly as possible.', 'import-export-by-rockstarlab' ) ); ?>
						</p>
						<div class="rsl-ie-notice-links">
							<a href="https://wordpress.org/support/plugin/import-export-by-rockstarlab/" target="_blank" class="rsl-ie-notice-link">
								<span class="dashicons dashicons-wordpress"></span>
								<?php esc_html_e( 'Support Forum', 'import-export-by-rockstarlab' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=import-export-by-rockstarlab-contact' ) ); ?>" class="rsl-ie-notice-link">
								<span class="dashicons dashicons-email-alt"></span>
								<?php esc_html_e( 'Email Support', 'import-export-by-rockstarlab' ); ?>
							</a>
						</div>
					</div>
				</div>

			</div><!-- .rsl-ie-postbox-container-middle -->

			<!-- Right column: Help & Support -->
			<div class="rsl-ie-postbox-container rsl-ie-postbox-container-right">

				<div class="rsl-ie-card">
					<div class="rsl-ie-card__header">
						<h2 class="rsl-ie-card__title">
							<span class="dashicons dashicons-editor-help"></span>
							<?php echo wp_kses_post( __( 'Help &amp; Support', 'import-export-by-rockstarlab' ) ); ?>
						</h2>
					</div>
					<div class="rsl-ie-card__body">
						<ul class="rsl-ie-support-list">
							<li>
								<span class="rsl-ie-support-icon"><span class="dashicons dashicons-book-alt"></span></span>
								<span class="rsl-ie-support-text">
									<a href="https://wpimportexport.com/docs/" target="_blank"><?php esc_html_e( 'Documentation', 'import-export-by-rockstarlab' ); ?></a>
									<span class="rsl-ie-support-desc"><?php esc_html_e( 'Guides and tutorials', 'import-export-by-rockstarlab' ); ?></span>
								</span>
							</li>
							<li>
								<span class="rsl-ie-support-icon"><span class="dashicons dashicons-wordpress"></span></span>
								<span class="rsl-ie-support-text">
									<a href="https://wordpress.org/support/plugin/import-export-by-rockstarlab/" target="_blank"><?php esc_html_e( 'Support Forum', 'import-export-by-rockstarlab' ); ?></a>
									<span class="rsl-ie-support-desc"><?php esc_html_e( 'Community help on WP.org', 'import-export-by-rockstarlab' ); ?></span>
								</span>
							</li>
							<li>
								<span class="rsl-ie-support-icon"><span class="dashicons dashicons-email-alt"></span></span>
								<span class="rsl-ie-support-text">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=import-export-by-rockstarlab-contact' ) ); ?>"><?php esc_html_e( 'Email Support', 'import-export-by-rockstarlab' ); ?></a>
									<span class="rsl-ie-support-desc"><?php esc_html_e( 'Direct help from the team', 'import-export-by-rockstarlab' ); ?></span>
								</span>
							</li>
							<li>
								<span class="rsl-ie-support-icon"><span class="dashicons dashicons-star-half"></span></span>
								<span class="rsl-ie-support-text">
									<a href="https://wordpress.org/support/plugin/import-export-by-rockstarlab/reviews/#new-post" target="_blank"><?php esc_html_e( 'Leave a Review', 'import-export-by-rockstarlab' ); ?></a>
									<span class="rsl-ie-support-desc"><?php esc_html_e( 'Rate us on WordPress.org', 'import-export-by-rockstarlab' ); ?></span>
								</span>
							</li>
						</ul>
					</div>
				</div>

			</div><!-- .rsl-ie-postbox-container-right -->

		</div><!-- .rsl-ie-dashboard-widgets-holder -->
	</div><!-- .rsl-ie-dashboard-widgets-wrap -->

</div><!-- .wrap -->
