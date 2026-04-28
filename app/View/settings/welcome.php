<?php
/**
 * Welcome Page Template — WordPress Dashboard style
 */
defined( 'ABSPATH' ) or exit;

$rsl_ie_plugin_version = defined( 'RSL_IE_VERSION' ) ? RSL_IE_VERSION : '1.0';
?>

<div class="wrap aie-welcome-page">

	<h1 class="aie-welcome-title">
		<span class="dashicons dashicons-update-alt"></span>
		<?php esc_html_e( 'Import Export by RockStarLab', 'import-export-by-rockstarlab' ); ?>
		<span class="aie-version-badge"><?php echo esc_html( $rsl_ie_plugin_version ); ?></span>
	</h1>

	<!-- Welcome Panel -->
	<div class="aie-welcome-panel welcome-panel">
		<div class="aie-welcome-panel-content">
			<div class="aie-welcome-panel-header">
				<h2><?php esc_html_e( 'Welcome! Let\'s get started.', 'import-export-by-rockstarlab' ); ?></h2>
				<p class="about-description">
					<?php esc_html_e( 'Use the links below to quickly access the main features of Import Export by RockStarLab plugin.', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>
			<div class="aie-welcome-panel-column-container">
				<div class="aie-welcome-panel-column">
					<h3><?php esc_html_e( 'Get Started', 'import-export-by-rockstarlab' ); ?></h3>
					<a class="button button-hero button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-import' ) ); ?>">
						<?php esc_html_e( 'Import Content', 'import-export-by-rockstarlab' ); ?>
					</a>
					<a class="button button-hero button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-export' ) ); ?>">
						<?php esc_html_e( 'Export Content', 'import-export-by-rockstarlab' ); ?>
					</a>
				</div>
				<div class="aie-welcome-panel-column">
					<h3><?php esc_html_e( 'Next Steps', 'import-export-by-rockstarlab' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-update"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-content-updater' ) ); ?>">
								<?php esc_html_e( 'Content Updater — bulk update fields', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
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
				<div class="aie-welcome-panel-column aie-welcome-panel-last">
					<h3><?php esc_html_e( 'More', 'import-export-by-rockstarlab' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-admin-tools"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-functions' ) ); ?>">
								<?php esc_html_e( 'Custom Functions', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
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
	</div><!-- .aie-welcome-panel -->

	<!-- Dashboard-style widget grid -->
	<div class="aie-dashboard-widgets-wrap">
		<div class="aie-dashboard-widgets-holder">

			<!-- Left column: Special Offer -->
			<div class="aie-postbox-container aie-postbox-container-left">

				<div class="aie-card aie-card--promo">
					<div class="aie-card__header">
						<h2 class="aie-card__title">
							<span class="dashicons dashicons-awards"></span>
							<?php esc_html_e( 'Import Export PRO Addon', 'import-export-by-rockstarlab' ); ?>
						</h2>
					</div>
					<div class="aie-card__body">
						<p class="aie-promo-intro">
							<?php echo wp_kses_post( __( 'All new users get <strong>PRO addon for free for 1 month</strong>. Try the full power of this addon, starting your free 30-days trial.', 'import-export-by-rockstarlab' ) ); ?>
						</p>
						<!-- <div class="aie-promo-code-row">
							<code class="aie-promo-code" id="aie-promo-code">NEW2026</code>
							<button type="button" class="button aie-copy-btn aie-copy-promo-code">
								<span class="dashicons dashicons-clipboard"></span>
								<?php esc_html_e( 'Copy', 'import-export-by-rockstarlab' ); ?>
							</button>
						</div> -->
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=import-export-by-rockstarlab-addons' ) ); ?>" class="button button-primary aie-promo-cta">
							<?php esc_html_e( 'Get PRO Addon →', 'import-export-by-rockstarlab' ); ?>
						</a>
					</div>
				</div>

			</div><!-- .aie-postbox-container-left -->

			<!-- Middle column: Active Development Notice -->
			<div class="aie-postbox-container aie-postbox-container-middle">

				<div class="aie-card aie-card--notice">
					<div class="aie-card__header">
						<h2 class="aie-card__title">
							<span class="dashicons dashicons-megaphone"></span>
							<?php esc_html_e( 'Active Development', 'import-export-by-rockstarlab' ); ?>
						</h2>
					</div>
					<div class="aie-card__body">
						<p class="aie-notice-badge">
							<span class="dashicons dashicons-hammer"></span>
							<?php echo wp_kses_post( __( 'New &amp; Growing', 'import-export-by-rockstarlab' ) ); ?>
						</p>
						<p class="aie-notice-text">
							<?php echo wp_kses_post( __( 'This plugin is brand new and actively developed&nbsp;&mdash; new features and improvements are released regularly.', 'import-export-by-rockstarlab' ) ); ?>
						</p>
						<p class="aie-notice-text">
							<?php echo wp_kses_post( __( 'If you run into any issues, please reach out via the Support Forum or Email Support&nbsp;&mdash; we&rsquo;ll do our best to help you as quickly as possible.', 'import-export-by-rockstarlab' ) ); ?>
						</p>
						<div class="aie-notice-links">
							<a href="https://wordpress.org/support/plugin/import-export-by-rockstarlab/" target="_blank" class="aie-notice-link">
								<span class="dashicons dashicons-wordpress"></span>
								<?php esc_html_e( 'Support Forum', 'import-export-by-rockstarlab' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=import-export-by-rockstarlab-contact' ) ); ?>" class="aie-notice-link">
								<span class="dashicons dashicons-email-alt"></span>
								<?php esc_html_e( 'Email Support', 'import-export-by-rockstarlab' ); ?>
							</a>
						</div>
					</div>
				</div>

			</div><!-- .aie-postbox-container-middle -->

			<!-- Right column: Help & Support -->
			<div class="aie-postbox-container aie-postbox-container-right">

				<div class="aie-card">
					<div class="aie-card__header">
						<h2 class="aie-card__title">
							<span class="dashicons dashicons-editor-help"></span>
							<?php echo wp_kses_post( __( 'Help &amp; Support', 'import-export-by-rockstarlab' ) ); ?>
						</h2>
					</div>
					<div class="aie-card__body">
						<ul class="aie-support-list">
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-book-alt"></span></span>
								<span class="aie-support-text">
									<a href="https://wpimportexport.com/docs/" target="_blank"><?php esc_html_e( 'Documentation', 'import-export-by-rockstarlab' ); ?></a>
									<span class="aie-support-desc"><?php esc_html_e( 'Guides and tutorials', 'import-export-by-rockstarlab' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-wordpress"></span></span>
								<span class="aie-support-text">
									<a href="https://wordpress.org/support/plugin/import-export-by-rockstarlab/" target="_blank"><?php esc_html_e( 'Support Forum', 'import-export-by-rockstarlab' ); ?></a>
									<span class="aie-support-desc"><?php esc_html_e( 'Community help on WP.org', 'import-export-by-rockstarlab' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-email-alt"></span></span>
								<span class="aie-support-text">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=import-export-by-rockstarlab-contact' ) ); ?>"><?php esc_html_e( 'Email Support', 'import-export-by-rockstarlab' ); ?></a>
									<span class="aie-support-desc"><?php esc_html_e( 'Direct help from the team', 'import-export-by-rockstarlab' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-star-half"></span></span>
								<span class="aie-support-text">
									<a href="https://wordpress.org/support/plugin/import-export-by-rockstarlab/reviews/#new-post" target="_blank"><?php esc_html_e( 'Leave a Review', 'import-export-by-rockstarlab' ); ?></a>
									<span class="aie-support-desc"><?php esc_html_e( 'Rate us on WordPress.org', 'import-export-by-rockstarlab' ); ?></span>
								</span>
							</li>
						</ul>
					</div>
				</div>

			</div><!-- .aie-postbox-container-right -->

		</div><!-- .aie-dashboard-widgets-holder -->
	</div><!-- .aie-dashboard-widgets-wrap -->

</div><!-- .wrap -->
