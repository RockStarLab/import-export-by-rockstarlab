<?php
/**
 * Welcome Page Template
 */
defined( 'ABSPATH' ) or exit;
?>

<div class="wrap aie-welcome-page">
	<div class="aie-welcome-header">
		<div class="aie-welcome-header-icon">
			<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
				<polyline points="23 4 23 10 17 10"></polyline>
				<polyline points="1 20 1 14 7 14"></polyline>
				<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
			</svg>
		</div>
		<h1><?php _e( 'Welcome to Advanced Import Export!', 'wp-advanced-import-export' ); ?></h1>
		<p class="aie-welcome-subtitle"><?php _e( 'Thank you for installing our plugin.', 'wp-advanced-import-export' ); ?><br><?php _e( 'We\'re here to help you manage your WordPress content efficiently.', 'wp-advanced-import-export' ); ?></p>
	</div>

	<div class="aie-welcome-content">
		<div class="aie-welcome-cards">
			<!-- Documentation Card -->
			<div class="aie-welcome-card">
				<div class="aie-welcome-card-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
						<path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
					</svg>
				</div>
				<h2><?php _e( 'Documentation', 'wp-advanced-import-export' ); ?></h2>
				<p><?php _e( 'Learn how to use all features of the plugin with our comprehensive documentation.', 'wp-advanced-import-export' ); ?></p>
				<a href="https://docs.example.com" target="_blank" class="aie-welcome-button aie-welcome-button-primary">
					<?php _e( 'Read Documentation', 'wp-advanced-import-export' ); ?>
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="5" y1="12" x2="19" y2="12"></line>
						<polyline points="12 5 19 12 12 19"></polyline>
					</svg>
				</a>
			</div>

			<!-- Active Development Card -->
			<div class="aie-welcome-card">
				<div class="aie-welcome-card-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="16 18 22 12 16 6"></polyline>
						<polyline points="8 6 2 12 8 18"></polyline>
					</svg>
				</div>
				<h2><?php _e( 'Active Development', 'wp-advanced-import-export' ); ?></h2>
				<p><?php _e( 'We\'re constantly improving the plugin with new features and updates based on your feedback.', 'wp-advanced-import-export' ); ?></p>
				<div class="aie-welcome-badges">
					<span class="aie-badge aie-badge-success"><?php _e( 'Regular Updates', 'wp-advanced-import-export' ); ?></span>
					<span class="aie-badge aie-badge-info"><?php _e( 'New Features', 'wp-advanced-import-export' ); ?></span>
				</div>
			</div>

			<!-- Support Card -->
			<div class="aie-welcome-card">
				<div class="aie-welcome-card-icon">
					<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
					</svg>
				</div>
				<h2><?php _e( 'Need Help?', 'wp-advanced-import-export' ); ?></h2>
				<p><?php _e( 'Our support team is ready to help you with any questions or issues.', 'wp-advanced-import-export' ); ?></p>
				<div class="aie-welcome-support-links">
					<a href="<?php esc_attr_e( admin_url( 'admin.php?page=wp-advanced-import-export-contact') ); ?>" class="aie-welcome-link">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
							<polyline points="22,6 12,13 2,6"></polyline>
						</svg>
						<?php _e( 'Email Support', 'wp-advanced-import-export' ); ?>
					</a>
					<a href="https://wordpress.org/support/plugin/wp-advanced-import-export/" target="_blank" class="aie-welcome-link">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="12" cy="12" r="10"></circle>
							<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
							<line x1="12" y1="17" x2="12.01" y2="17"></line>
						</svg>
						<?php _e( 'Support Forum', 'wp-advanced-import-export' ); ?>
					</a>
				</div>
			</div>

			<!-- Premium Offer Card -->
			<div class="aie-welcome-card aie-welcome-card-premium aie-welcome-card-wide">
				<div class="aie-welcome-card-badge">
					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="12" cy="8" r="7"></circle>
						<polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
					</svg>
					<span><?php _e( 'Special Offer', 'wp-advanced-import-export' ); ?></span>
				</div>
				<div class="aie-premium-content">
					<div class="aie-premium-icon">
						<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M12 2L2 7l10 5 10-5-10-5z"></path>
							<path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
						</svg>
					</div>
					<div class="aie-premium-text">
						<h2><?php _e( 'For all new users: get 4 Weeks Premium for FREE!', 'wp-advanced-import-export' ); ?></h2>
						<p><?php _e( 'As a welcome gift, enjoy 1 month of all premium features absolutely free. Use the promo code below:', 'wp-advanced-import-export' ); ?></p>
						<div class="aie-promo-code-container">
							<div class="aie-promo-code" id="aie-promo-code">NEW2026</div>
							<button class="aie-copy-button" onclick="aieWelcome.copyPromoCode()">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
									<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
								</svg>
								<?php _e( 'Copy Code', 'wp-advanced-import-export' ); ?>
							</button>
						</div>
						<a href="<?php echo admin_url( 'admin.php?page=wp-advanced-import-export-pricing&checkout=true&plan_id=36762&plan_name=unlimited&billing_cycle=monthly&pricing_id=48039&currency=usd' ); ?>" class="aie-welcome-button aie-welcome-button-premium">
							<?php _e( 'Activate Premium', 'wp-advanced-import-export' ); ?>
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="5" y1="12" x2="19" y2="12"></line>
								<polyline points="12 5 19 12 12 19"></polyline>
							</svg>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
var aieWelcome = {
	copyPromoCode: function() {
		var promoCode = document.getElementById('aie-promo-code').textContent;
		navigator.clipboard.writeText(promoCode).then(function() {
			var btn = document.querySelector('.aie-copy-button');
			var originalText = btn.innerHTML;
			btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> <?php _e( 'Copied!', 'wp-advanced-import-export' ); ?>';
			btn.classList.add('copied');
			setTimeout(function() {
				btn.innerHTML = originalText;
				btn.classList.remove('copied');
			}, 2000);
		});
	}
};
</script>
