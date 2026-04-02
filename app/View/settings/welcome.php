<?php
/**
 * Welcome Page Template — WordPress Dashboard style
 */
defined( 'ABSPATH' ) or exit;

$plugin_version = defined( 'WP_AIE_VERSION' ) ? WP_AIE_VERSION : '1.0';
?>

<div class="wrap aie-welcome-page">

	<h1 class="aie-welcome-title">
		<span class="dashicons dashicons-update-alt"></span>
		<?php esc_html_e( 'Amplified Import Export', 'amplified-import-export' ); ?>
		<span class="aie-version-badge"><?php echo esc_html( $plugin_version ); ?></span>
	</h1>

	<!-- Welcome Panel -->
	<div class="aie-welcome-panel welcome-panel">
		<div class="aie-welcome-panel-content">
			<div class="aie-welcome-panel-header">
				<h2><?php esc_html_e( 'Welcome! Let\'s get started.', 'amplified-import-export' ); ?></h2>
				<p class="about-description">
					<?php esc_html_e( 'Use the links below to quickly access the main features of Amplified Import Export plugin.', 'amplified-import-export' ); ?>
				</p>
			</div>
			<div class="aie-welcome-panel-column-container">
				<div class="aie-welcome-panel-column">
					<h3><?php esc_html_e( 'Get Started', 'amplified-import-export' ); ?></h3>
					<a class="button button-hero button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-import' ) ); ?>">
						<?php esc_html_e( 'Import Content', 'amplified-import-export' ); ?>
					</a>
					<a class="button button-hero button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-export' ) ); ?>">
						<?php esc_html_e( 'Export Content', 'amplified-import-export' ); ?>
					</a>
				</div>
				<div class="aie-welcome-panel-column">
					<h3><?php esc_html_e( 'Next Steps', 'amplified-import-export' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-update"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-content-updater' ) ); ?>">
								<?php esc_html_e( 'Content Updater — bulk update fields', 'amplified-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-networking"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-content-sync' ) ); ?>">
								<?php esc_html_e( 'Content Sync — sync between sites', 'amplified-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-format-image"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-media-sync' ) ); ?>">
								<?php esc_html_e( 'Media Sync — import media files', 'amplified-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-admin-links"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-ai-url-importer' ) ); ?>">
								<?php esc_html_e( 'AI URL Importer — import from URL', 'amplified-import-export' ); ?>
							</a>
						</li>
					</ul>
				</div>
				<div class="aie-welcome-panel-column aie-welcome-panel-last">
					<h3><?php esc_html_e( 'More', 'amplified-import-export' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-admin-tools"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-functions' ) ); ?>">
								<?php esc_html_e( 'Custom Functions', 'amplified-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-list-view"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-jobs-log' ) ); ?>">
								<?php esc_html_e( 'Jobs Log', 'amplified-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-admin-settings"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-plugin-options' ) ); ?>">
								<?php esc_html_e( 'Plugin Options', 'amplified-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-book-alt"></span>
							<a href="https://wpimportexport.com/docs/" target="_blank">
								<?php esc_html_e( 'Documentation', 'amplified-import-export' ); ?>
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
							<?php esc_html_e( 'Special Offer', 'amplified-import-export' ); ?>
						</h2>
					</div>
					<div class="aie-card__body">
						<p class="aie-promo-intro">
							<?php echo wp_kses_post( __( 'All new users get <strong>4 weeks Premium Features for free</strong>. Use the code below at checkout:', 'amplified-import-export' ) ); ?>
						</p>
						<div class="aie-promo-code-row">
							<code class="aie-promo-code" id="aie-promo-code">NEW2026</code>
							<button type="button" class="button aie-copy-btn" onclick="aieWelcome.copyPromoCode()">
								<span class="dashicons dashicons-clipboard"></span>
								<?php esc_html_e( 'Copy', 'amplified-import-export' ); ?>
							</button>
						</div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=amplified-import-export-pricing' ) ); ?>" class="button button-primary aie-promo-cta">
							<?php esc_html_e( 'Activate Premium →', 'amplified-import-export' ); ?>
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
							<?php esc_html_e( 'Active Development', 'amplified-import-export' ); ?>
						</h2>
					</div>
					<div class="aie-card__body">
						<p class="aie-notice-badge">
							<span class="dashicons dashicons-hammer"></span>
							<?php echo wp_kses_post( __( 'New &amp; Growing', 'amplified-import-export' ) ); ?>
						</p>
						<p class="aie-notice-text">
							<?php echo wp_kses_post( __( 'This plugin is brand new and actively developed&nbsp;&mdash; new features and improvements are released regularly.', 'amplified-import-export' ) ); ?>
						</p>
						<p class="aie-notice-text">
							<?php echo wp_kses_post( __( 'If you run into any issues, please reach out via the Support Forum or Email Support&nbsp;&mdash; we&rsquo;ll do our best to help you as quickly as possible.', 'amplified-import-export' ) ); ?>
						</p>
						<div class="aie-notice-links">
							<a href="https://wordpress.org/support/plugin/amplified-import-export/" target="_blank" class="aie-notice-link">
								<span class="dashicons dashicons-wordpress"></span>
								<?php esc_html_e( 'Support Forum', 'amplified-import-export' ); ?>
							</a>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=amplified-import-export-contact' ) ); ?>" class="aie-notice-link">
								<span class="dashicons dashicons-email-alt"></span>
								<?php esc_html_e( 'Email Support', 'amplified-import-export' ); ?>
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
							<?php echo wp_kses_post( __( 'Help &amp; Support', 'amplified-import-export' ) ); ?>
						</h2>
					</div>
					<div class="aie-card__body">
						<ul class="aie-support-list">
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-book-alt"></span></span>
								<span class="aie-support-text">
									<a href="https://wpimportexport.com/docs/" target="_blank"><?php esc_html_e( 'Documentation', 'amplified-import-export' ); ?></a>
									<span class="aie-support-desc"><?php esc_html_e( 'Guides and tutorials', 'amplified-import-export' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-wordpress"></span></span>
								<span class="aie-support-text">
									<a href="https://wordpress.org/support/plugin/amplified-import-export/" target="_blank"><?php esc_html_e( 'Support Forum', 'amplified-import-export' ); ?></a>
									<span class="aie-support-desc"><?php esc_html_e( 'Community help on WP.org', 'amplified-import-export' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-email-alt"></span></span>
								<span class="aie-support-text">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=amplified-import-export-contact' ) ); ?>"><?php esc_html_e( 'Email Support', 'amplified-import-export' ); ?></a>
									<span class="aie-support-desc"><?php esc_html_e( 'Direct help from the team', 'amplified-import-export' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-star-half"></span></span>
								<span class="aie-support-text">
									<a href="https://wordpress.org/support/plugin/amplified-import-export/reviews/#new-post" target="_blank"><?php esc_html_e( 'Leave a Review', 'amplified-import-export' ); ?></a>
									<span class="aie-support-desc"><?php esc_html_e( 'Rate us on WordPress.org', 'amplified-import-export' ); ?></span>
								</span>
							</li>
						</ul>
					</div>
				</div>

			</div><!-- .aie-postbox-container-right -->

		</div><!-- .aie-dashboard-widgets-holder -->
	</div><!-- .aie-dashboard-widgets-wrap -->

</div><!-- .wrap -->

<script>
var aieWelcome = {
	copyPromoCode: function() {
		var codeEl = document.getElementById( 'aie-promo-code' );
		var promoCode = codeEl ? codeEl.textContent.trim() : '';
		var btn = document.querySelector( '.aie-promo-code-row .aie-copy-btn' );
		var copiedLabel = '<span class="dashicons dashicons-yes"></span> <?php echo esc_js( __( 'Copied!', 'amplified-import-export' ) ); ?>';

		function onSuccess() {
			if ( ! btn ) return;
			var original = btn.innerHTML;
			btn.innerHTML = copiedLabel;
			btn.classList.add( 'aie-copied' );
			setTimeout( function() {
				btn.innerHTML = original;
				btn.classList.remove( 'aie-copied' );
			}, 2000 );
		}

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( promoCode ).then( onSuccess ).catch( function() {
				aieWelcome.fallbackCopy( promoCode, onSuccess );
			} );
		} else {
			aieWelcome.fallbackCopy( promoCode, onSuccess );
		}
	},

	fallbackCopy: function( text, callback ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
		document.body.appendChild( ta );
		ta.focus();
		ta.select();
		try {
			document.execCommand( 'copy' );
			if ( callback ) callback();
		} catch ( e ) {}
		document.body.removeChild( ta );
	}
};
</script>
