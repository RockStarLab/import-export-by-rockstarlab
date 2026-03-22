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
		<?php _e( 'Advanced Import Export', 'wp-advanced-import-export' ); ?>
		<span class="aie-version-badge"><?php echo esc_html( $plugin_version ); ?></span>
	</h1>

	<!-- Welcome Panel -->
	<div class="aie-welcome-panel welcome-panel">
		<div class="aie-welcome-panel-content">
			<div class="aie-welcome-panel-header">
				<h2><?php _e( 'Welcome! Let\'s get started.', 'wp-advanced-import-export' ); ?></h2>
				<p class="about-description">
					<?php _e( 'Use the links below to quickly access the main features of Advanced Import Export plugin.', 'wp-advanced-import-export' ); ?>
				</p>
			</div>
			<div class="aie-welcome-panel-column-container">
				<div class="aie-welcome-panel-column">
					<h3><?php _e( 'Get Started', 'wp-advanced-import-export' ); ?></h3>
					<a class="button button-hero button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-import' ) ); ?>">
						<?php _e( 'Import Content', 'wp-advanced-import-export' ); ?>
					</a>
					<a class="button button-hero button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-export' ) ); ?>">
						<?php _e( 'Export Content', 'wp-advanced-import-export' ); ?>
					</a>
				</div>
				<div class="aie-welcome-panel-column">
					<h3><?php _e( 'Next Steps', 'wp-advanced-import-export' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-update"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-content-updater' ) ); ?>">
								<?php _e( 'Content Updater — bulk update fields', 'wp-advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-networking"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-content-sync' ) ); ?>">
								<?php _e( 'Content Sync — sync between sites', 'wp-advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-format-image"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-media-sync' ) ); ?>">
								<?php _e( 'Media Sync — import media files', 'wp-advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-admin-links"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-ai-url-importer' ) ); ?>">
								<?php _e( 'AI URL Importer — import from URL', 'wp-advanced-import-export' ); ?>
							</a>
						</li>
					</ul>
				</div>
				<div class="aie-welcome-panel-column aie-welcome-panel-last">
					<h3><?php _e( 'More', 'wp-advanced-import-export' ); ?></h3>
					<ul>
						<li>
							<span class="dashicons dashicons-admin-tools"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-functions' ) ); ?>">
								<?php _e( 'Custom Functions', 'wp-advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-list-view"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-jobs-log' ) ); ?>">
								<?php _e( 'Jobs Log', 'wp-advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-admin-settings"></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-aie-plugin-options' ) ); ?>">
								<?php _e( 'Plugin Options', 'wp-advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<span class="dashicons dashicons-book-alt"></span>
							<a href="https://docs.example.com" target="_blank">
								<?php _e( 'Documentation', 'wp-advanced-import-export' ); ?>
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
							<?php _e( 'Special Offer', 'wp-advanced-import-export' ); ?>
						</h2>
					</div>
					<div class="aie-card__body">
						<p class="aie-promo-intro">
							<?php _e( 'New users get <strong>4 weeks Premium for free</strong>. Use the code below at checkout:', 'wp-advanced-import-export' ); ?>
						</p>
						<div class="aie-promo-code-row">
							<code class="aie-promo-code" id="aie-promo-code">NEW2026</code>
							<button type="button" class="button" onclick="aieWelcome.copyPromoCode()">
								<span class="dashicons dashicons-clipboard"></span>
								<?php _e( 'Copy', 'wp-advanced-import-export' ); ?>
							</button>
						</div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-advanced-import-export-pricing&checkout=true&plan_id=36762&plan_name=unlimited&billing_cycle=monthly&pricing_id=48039&currency=usd' ) ); ?>" class="button button-primary aie-promo-cta">
							<?php _e( 'Activate Premium →', 'wp-advanced-import-export' ); ?>
						</a>
					</div>
				</div>

			</div><!-- .aie-postbox-container-left -->

			<!-- Right column: Help & Support -->
			<div class="aie-postbox-container aie-postbox-container-right">

				<div class="aie-card">
					<div class="aie-card__header">
						<h2 class="aie-card__title">
							<span class="dashicons dashicons-editor-help"></span>
							<?php _e( 'Help &amp; Support', 'wp-advanced-import-export' ); ?>
						</h2>
					</div>
					<div class="aie-card__body">
						<ul class="aie-support-list">
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-book-alt"></span></span>
								<span class="aie-support-text">
									<a href="https://docs.example.com" target="_blank"><?php _e( 'Documentation', 'wp-advanced-import-export' ); ?></a>
									<span class="aie-support-desc"><?php _e( 'Guides and tutorials', 'wp-advanced-import-export' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-wordpress"></span></span>
								<span class="aie-support-text">
									<a href="https://wordpress.org/support/plugin/wp-advanced-import-export/" target="_blank"><?php _e( 'Support Forum', 'wp-advanced-import-export' ); ?></a>
									<span class="aie-support-desc"><?php _e( 'Community help on WP.org', 'wp-advanced-import-export' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-email-alt"></span></span>
								<span class="aie-support-text">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-advanced-import-export-contact' ) ); ?>"><?php _e( 'Email Support', 'wp-advanced-import-export' ); ?></a>
									<span class="aie-support-desc"><?php _e( 'Direct help from the team', 'wp-advanced-import-export' ); ?></span>
								</span>
							</li>
							<li>
								<span class="aie-support-icon"><span class="dashicons dashicons-star-half"></span></span>
								<span class="aie-support-text">
									<a href="https://wordpress.org/support/plugin/wp-advanced-import-export/reviews/#new-post" target="_blank"><?php _e( 'Leave a Review', 'wp-advanced-import-export' ); ?></a>
									<span class="aie-support-desc"><?php _e( 'Rate us on WordPress.org', 'wp-advanced-import-export' ); ?></span>
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
		var btn = document.querySelector( '.aie-promo-code-row .button' );
		var copiedLabel = '<span class="dashicons dashicons-yes"></span> <?php echo esc_js( __( 'Copied!', 'wp-advanced-import-export' ) ); ?>';

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
