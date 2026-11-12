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
		<?php esc_html_e( 'Content & Media Sync, Import, Export with AI Agents Support', 'import-export-by-rockstarlab' ); ?>
		<span class="rsl-ie-version-badge"><?php echo esc_html( $rsl_ie_plugin_version ); ?></span>
	</h1>

	<!-- Welcome Panel -->
	<div class="rsl-ie-welcome-panel">
		<div class="rsl-ie-welcome-panel-content">
			<section class="rsl-ie-agent-hero" aria-labelledby="rsl-ie-agent-hero-title">
				<svg class="rsl-ie-agent-orbits" viewBox="0 0 1200 460" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
					<defs><linearGradient id="rsl-ie-orbit-gradient"><stop stop-color="#9656f3"/><stop offset="1" stop-color="#68d6e5"/></linearGradient><pattern id="rsl-ie-hero-grid" width="36" height="36" patternUnits="userSpaceOnUse"><circle cx="1" cy="1" r="1" fill="#a7a1c5" opacity=".22"/></pattern></defs>
					<rect width="1200" height="460" fill="url(#rsl-ie-hero-grid)"/>
					<g fill="none" stroke="url(#rsl-ie-orbit-gradient)" stroke-width="1"><ellipse cx="930" cy="240" rx="340" ry="190" opacity=".18"/><ellipse cx="930" cy="240" rx="410" ry="260" opacity=".12"/><path class="rsl-ie-agent-signal" d="M480 390C660 390 580 70 860 70S1030 390 1200 200" stroke-dasharray="4 12" opacity=".35"/></g>
				</svg>
				<div class="rsl-ie-agent-hero-copy">
					<span class="rsl-ie-agent-hero-eyebrow"><?php esc_html_e( 'YOUR WORDPRESS. YOUT AI AGENT.', 'import-export-by-rockstarlab' ); ?></span>
					<h2 id="rsl-ie-agent-hero-title"><?php esc_html_e( 'Powerful data workflows.', 'import-export-by-rockstarlab' ); ?><br><span><?php esc_html_e( 'Just ask.', 'import-export-by-rockstarlab' ); ?></span></h2>
					<p><?php esc_html_e( 'Bring your AI agent to WordPress. Connect CodexGPT, Claude, Cursor, GitHub Copilot or Gemini CLI to import, export and sync content just by asking.', 'import-export-by-rockstarlab' ); ?></p>
					<a class="rsl-ie-agent-hero-cta" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-agent-connection' ) ); ?>"><?php esc_html_e( 'Connect your AI agent', 'import-export-by-rockstarlab' ); ?><span aria-hidden="true">↗</span></a>
					<span class="rsl-ie-agent-hero-note"><?php esc_html_e( 'Your tools. A whole new way to use them.', 'import-export-by-rockstarlab' ); ?></span>
				</div>
				<div class="rsl-ie-agent-demo">
					<div class="rsl-ie-agent-demo-bar"><span><span class="rsl-ie-agent-spark" aria-hidden="true">✳</span> <?php esc_html_e( 'AI workspace', 'import-export-by-rockstarlab' ); ?></span><span class="rsl-ie-agent-demo-label"><?php esc_html_e( 'Demo', 'import-export-by-rockstarlab' ); ?></span></div>
					<div class="rsl-ie-agent-demo-body" aria-hidden="true">
						<div class="rsl-ie-agent-demo-context"><span class="dashicons dashicons-wordpress-alt"></span> WordPress <span>↔</span> Import Export</div>
						<div class="rsl-ie-agent-prompt"><span class="rsl-ie-agent-prompt-text"><?php esc_html_e( 'Make export of WooCommerce orders for last 24 hours into Excel.', 'import-export-by-rockstarlab' ); ?></span><span class="rsl-ie-agent-cursor">▍</span></div>
						<div class="rsl-ie-agent-demo-reply"><span class="rsl-ie-agent-spark">✳</span><div><strong><?php esc_html_e( 'Let’s make it happen.', 'import-export-by-rockstarlab' ); ?></strong><span><?php esc_html_e( 'Understand the request. Prepare the workflow.', 'import-export-by-rockstarlab' ); ?></span><div class="rsl-ie-agent-demo-steps"><span>✓ <?php esc_html_e( 'Select data', 'import-export-by-rockstarlab' ); ?></span><span>✓ <?php esc_html_e( 'Set up job', 'import-export-by-rockstarlab' ); ?></span></div></div></div>
						<div class="rsl-ie-agent-demo-input"><span><?php esc_html_e( 'Describe your next workflow…', 'import-export-by-rockstarlab' ); ?></span><span>↑</span></div>
					</div>
					<div class="rsl-ie-agent-demo-footer"><span><?php esc_html_e( 'A little conversation. A lot less busywork.', 'import-export-by-rockstarlab' ); ?></span><button type="button" class="rsl-ie-agent-pause" aria-pressed="false"><?php esc_html_e( 'Pause animation', 'import-export-by-rockstarlab' ); ?></button></div>
					<ul hidden class="rsl-ie-agent-prompts">
						<li><?php esc_html_e( 'Make export of WooCommerce orders for last 24 hours into Excel.', 'import-export-by-rockstarlab' ); ?></li>
						<li><?php esc_html_e( 'Import products from a CSV file and map their prices and stock.', 'import-export-by-rockstarlab' ); ?></li>
						<li><?php esc_html_e( 'Sync my latest blog posts from staging to the live site.', 'import-export-by-rockstarlab' ); ?></li>
						<li><?php esc_html_e( 'Export all published posts with their custom fields into JSON.', 'import-export-by-rockstarlab' ); ?></li>
					</ul>
				</div>
			</section>
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
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-import&import_mode=media-sync' ) ); ?>">
								<?php esc_html_e( 'Media Sync — import media files', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-admin-links"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-import&import_mode=ai-url' ) ); ?>">
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
							<div class="rsl-ie-card__header rsl-ie-promo-offer-header">
								<h2 class="rsl-ie-card__title">
									<span class="dashicons dashicons-clock" aria-hidden="true"></span>
									<?php esc_html_e( 'Limited Time Offer', 'import-export-by-rockstarlab' ); ?>
								</h2>
							</div>
							<p class="rsl-ie-promo-intro rsl-ie-promo-launch-copy">
								<?php esc_html_e( 'A special launch offer for the first users of the plugin — available for a limited time only.', 'import-export-by-rockstarlab' ); ?>
							</p>
							<a href="https://checkout.freemius.com/plugin/28907/plan/47555/licenses/4/" target="_blank" rel="noopener noreferrer" class="button button-primary rsl-ie-promo-cta rsl-ie-promo-lifetime-cta">
								<?php esc_html_e( 'Get Lifetime License →', 'import-export-by-rockstarlab' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

			</div><!-- .rsl-ie-postbox-container-left -->

<?php
/*
Temporarily hidden: Active Development card.
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

*/
?>

			<!-- Middle column: What's new -->
			<div class="rsl-ie-postbox-container rsl-ie-postbox-container-middle">
				<div class="rsl-ie-card rsl-ie-card--notice rsl-ie-card--whats-new">
					<div class="rsl-ie-card__header">
						<h2 class="rsl-ie-card__title">
							<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
							<?php esc_html_e( "What's new", 'import-export-by-rockstarlab' ); ?>
						</h2>
					</div>
					<div class="rsl-ie-card__body">
						<span class="rsl-ie-notice-badge"><?php esc_html_e( 'Version 2.0.0', 'import-export-by-rockstarlab' ); ?></span>
						<ul class="rsl-ie-release-notes">
							<li><strong><?php esc_html_e( 'Connect your AI agents', 'import-export-by-rockstarlab' ); ?></strong><span><?php esc_html_e( 'Connect CodexGPT, Claude, Cursor, GitHub Copilot or Gemini CLI to work with your WordPress data.', 'import-export-by-rockstarlab' ); ?></span></li>
							<li><strong><?php esc_html_e( 'AI URL Importer & Media Sync', 'import-export-by-rockstarlab' ); ?></strong><span><?php esc_html_e( 'Now available on the Import screen.', 'import-export-by-rockstarlab' ); ?></span></li>
							<li><strong><?php esc_html_e( 'Content Migration Tool Improvements', 'import-export-by-rockstarlab' ); ?></strong><span><?php esc_html_e( 'Improved content migration functionality.', 'import-export-by-rockstarlab' ); ?></span></li>
						</ul>
						<div class="rsl-ie-notice-links">
							<a href="https://wpimportexport.com/import-export-video-tutorials-for-wordpress/ai-agents/" target="_blank" rel="noopener noreferrer" class="rsl-ie-notice-link">
								<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
								<?php esc_html_e( 'Abilities API & AI agents guide', 'import-export-by-rockstarlab' ); ?>
							</a>
							<a href="https://wpimportexport.com/whats-new-in-import-export-by-rockstarlab-2-0-0/" target="_blank" rel="noopener noreferrer" class="rsl-ie-notice-link">
								<span class="dashicons dashicons-megaphone" aria-hidden="true"></span>
								<?php esc_html_e( 'Read what’s new in 2.0.0', 'import-export-by-rockstarlab' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>

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
										<a href="https://wpimportexport.com/import-export-video-tutorials-for-wordpress/#written-guides" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Documentation', 'import-export-by-rockstarlab' ); ?></a>
										<span class="rsl-ie-support-desc"><?php esc_html_e( 'Step-by-step written guides', 'import-export-by-rockstarlab' ); ?></span>
									</span>
								</li>
								<li>
									<span class="rsl-ie-support-icon"><span class="dashicons dashicons-video-alt3"></span></span>
									<span class="rsl-ie-support-text">
										<a href="https://wpimportexport.com/import-export-video-tutorials-for-wordpress/" target="_blank"><?php esc_html_e( 'Video Tutorials', 'import-export-by-rockstarlab' ); ?></a>
										<span class="rsl-ie-support-desc"><?php esc_html_e( 'Step-by-step video guides', 'import-export-by-rockstarlab' ); ?></span>
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
								<li>
									<span class="rsl-ie-support-icon"><span class="dashicons dashicons-lightbulb"></span></span>
									<span class="rsl-ie-support-text">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=import-export-by-rockstarlab-contact' ) ); ?>"><?php esc_html_e( 'Propose New Feature', 'import-export-by-rockstarlab' ); ?></a>
										<span class="rsl-ie-support-desc"><?php esc_html_e( 'Tell us what to build next', 'import-export-by-rockstarlab' ); ?></span>
									</span>
								</li>
							</ul>
						</div>
					</div>

			</div><!-- .rsl-ie-postbox-container-right -->

		</div><!-- .rsl-ie-dashboard-widgets-holder -->
	</div><!-- .rsl-ie-dashboard-widgets-wrap -->

</div><!-- .wrap -->
