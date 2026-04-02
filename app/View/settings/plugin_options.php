<?php
/**
 * Plugin Options Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;

// Get current settings
$openai_api_key = get_option( 'wp_aie_openai_api_key', '' );
$has_constant_key = defined( 'WP_AIE_OPENAI_API_KEY' ) && ! empty( WP_AIE_OPENAI_API_KEY );

// Mask the API key for display
$masked_api_key = '';
if ( ! empty( $openai_api_key ) ) {
	$masked_api_key = substr( $openai_api_key, 0, 7 ) . '...' . substr( $openai_api_key, -4 );
}
?>

<div id="wp-aie-plugin-options" class="wrap">
	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Plugin Options', 'amplified-import-export' ); ?>
	</h1>

	<p class="description">
		<?php esc_html_e( 'Configure plugin-wide settings and integrations.', 'amplified-import-export' ); ?>
	</p>

	<div class="aie-settings-container">
		<!-- AI Integration Section -->
		<div class="aie-settings-section">
			<div class="aie-settings-section-header">
				<h2>
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'AI Integration', 'amplified-import-export' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Configure AI features powered by OpenAI.', 'amplified-import-export' ); ?>
				</p>
			</div>

			<div class="aie-settings-section-body">
				<form id="aie-settings-form" class="aie-settings-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="aie-openai-api-key">
									<?php esc_html_e( 'OpenAI API Key', 'amplified-import-export' ); ?>
								</label>
							</th>
							<td>
								<?php if ( $has_constant_key ) : ?>
									<div class="aie-info-box aie-warning">
										<span class="dashicons dashicons-info"></span>
										<div>
											<strong><?php esc_html_e( 'API Key is defined in wp-config.php', 'amplified-import-export' ); ?></strong>
											<p><?php esc_html_e( 'The API key is set via WP_AIE_OPENAI_API_KEY constant and cannot be changed here. Remove the constant from wp-config.php to manage it through this interface.', 'amplified-import-export' ); ?></p>
										</div>
									</div>
									<input 
										type="text" 
										id="aie-openai-api-key" 
										name="openai_api_key"
										class="regular-text" 
										value="<?php echo esc_attr( substr( WP_AIE_OPENAI_API_KEY, 0, 7 ) . '...' . substr( WP_AIE_OPENAI_API_KEY, -4 ) ); ?>"
										disabled
										readonly
									>
								<?php else : ?>
									<input 
										type="password" 
										id="aie-openai-api-key" 
										name="openai_api_key"
										class="regular-text" 
										value="<?php echo esc_attr( $openai_api_key ); ?>"
										placeholder="sk-proj-..."
									>
									<button type="button" class="button aie-toggle-password" data-target="aie-openai-api-key">
										<span class="dashicons dashicons-visibility"></span>
									</button>
									<?php if ( ! empty( $openai_api_key ) ) : ?>
										<p class="description">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: masked API key */
													__( 'Current key: %s', 'amplified-import-export' ),
													$masked_api_key
												)
											);
											?>
										</p>
									<?php endif; ?>
									<p class="description">
										<?php
										echo wp_kses_post(
											sprintf(
												/* translators: %s: OpenAI platform URL */
												__( 'Get your API key from <a href="%s" target="_blank">OpenAI Platform</a>. Required for AI-powered function generation.', 'amplified-import-export' ),
												'https://platform.openai.com/api-keys'
											)
										);
										?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'API Status', 'amplified-import-export' ); ?>
							</th>
							<td>
								<div id="aie-api-status" class="aie-api-status">
									<?php if ( ! empty( $openai_api_key ) || $has_constant_key ) : ?>
										<span class="aie-status-badge aie-status-configured">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Configured', 'amplified-import-export' ); ?>
										</span>
										<button type="button" class="button button-secondary aie-test-api-key">
											<span class="dashicons dashicons-update"></span>
											<?php esc_html_e( 'Test Connection', 'amplified-import-export' ); ?>
										</button>
									<?php else : ?>
										<span class="aie-status-badge aie-status-not-configured">
											<span class="dashicons dashicons-warning"></span>
											<?php esc_html_e( 'Not Configured', 'amplified-import-export' ); ?>
										</span>
									<?php endif; ?>
								</div>
								<div id="aie-api-test-result" style="display:none; margin-top:10px;"></div>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Model', 'amplified-import-export' ); ?>
							</th>
							<td>
								<span class="aie-info-badge">GPT-4o-mini</span>
								<!-- <p class="description">
									<?php esc_html_e( 'Fast and cost-effective model for function generation. ~$0.0001-0.0003 per function.', 'amplified-import-export' ); ?>
								</p> -->
							</td>
						</tr>
					</table>

					<?php if ( ! $has_constant_key ) : ?>
						<div class="aie-settings-footer">
							<button type="submit" class="button button-primary aie-save-settings">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Save Settings', 'amplified-import-export' ); ?>
							</button>
							<span class="aie-settings-status"></span>
						</div>
					<?php endif; ?>
				</form>
			</div>
		</div>

		<!-- Information Section -->
		<div class="aie-settings-section">
			<div class="aie-settings-section-header">
				<h2>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'About AI Features', 'amplified-import-export' ); ?>
				</h2>
			</div>

			<div class="aie-settings-section-body">
				<div class="aie-info-cards">

					<div class="aie-info-card">
						<span class="dashicons dashicons-admin-links"></span>
						<h3><?php esc_html_e( 'AI URL Importer', 'amplified-import-export' ); ?></h3>
						<p><?php esc_html_e( 'Import content directly from URLs using AI-powered extraction. Automatically extracts and structures content from web pages into WordPress posts.', 'amplified-import-export' ); ?></p>
					</div>

					<div class="aie-info-card">
						<span class="dashicons dashicons-admin-generic"></span>
						<h3><?php esc_html_e( 'AI Function Generator', 'amplified-import-export' ); ?></h3>
						<p><?php esc_html_e( 'Generate PHP transformation functions using natural language descriptions. Perfect for data transformation during import/export.', 'amplified-import-export' ); ?></p>
					</div>

					<div class="aie-info-card">
						<span class="dashicons dashicons-chart-area"></span>
						<h3><?php esc_html_e( 'Pricing & Tokens', 'amplified-import-export' ); ?></h3>
						<p>
							<?php esc_html_e( 'AI features use OpenAI GPT-4o-mini model. Typical costs:', 'amplified-import-export' ); ?>
						</p>
						<ul style="list-style: disc; margin: 10px 0; padding-left: 20px; font-size: 0.95em;">
							<li><?php esc_html_e( 'Function generation: ~$0.0001-0.0003 per function', 'amplified-import-export' ); ?></li>
							<li><?php esc_html_e( 'URL import: ~$0.001-0.005 per page', 'amplified-import-export' ); ?></li>
						</ul>
						<p style="font-size: 0.9em; color: #666;">
							<?php esc_html_e( 'You pay OpenAI directly based on usage. Monitor costs in your OpenAI dashboard.', 'amplified-import-export' ); ?>
						</p>
					</div>
				</div>

				<div class="aie-help-section">
					<h3>
						<span class="dashicons dashicons-editor-help"></span>
						<?php esc_html_e( 'Need Help?', 'amplified-import-export' ); ?>
					</h3>
					<ul>
						<li>
							<a href="https://platform.openai.com/account/api-keys" target="_blank">
								<?php esc_html_e( 'Manage API Keys', 'amplified-import-export' ); ?>
							</a>
						</li>
						<li>
							<a href="https://platform.openai.com/account/billing" target="_blank">
								<?php esc_html_e( 'View Usage & Billing', 'amplified-import-export' ); ?>
							</a>
						</li>
						<li>
							<a href="https://platform.openai.com/docs/api-reference" target="_blank">
								<?php esc_html_e( 'OpenAI API Documentation', 'amplified-import-export' ); ?>
							</a>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Toggle password visibility
	$('.aie-toggle-password').on('click', function() {
		const targetId = $(this).data('target');
		const $input = $('#' + targetId);
		const $icon = $(this).find('.dashicons');
		
		if ($input.attr('type') === 'password') {
			$input.attr('type', 'text');
			$icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
		} else {
			$input.attr('type', 'password');
			$icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
		}
	});

	// Save settings
	$('#aie-settings-form').on('submit', function(e) {
		e.preventDefault();
		
		const $form = $(this);
		const $submitBtn = $form.find('.aie-save-settings');
		const $status = $form.find('.aie-settings-status');
		
		$submitBtn.prop('disabled', true);
		$status.html('<span class="spinner is-active"></span>');
		
		$.ajax({
			url: window.aieData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aie_settings_save',
				nonce: window.aieData.nonce,
				openai_api_key: $('#aie-openai-api-key').val()
			},
			success: function(response) {
				if (response.success) {
					$status.html('<span class="aie-success-message"><span class="dashicons dashicons-yes-alt"></span> ' + response.data.message + '</span>');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					$status.html('<span class="aie-error-message"><span class="dashicons dashicons-warning"></span> ' + response.data + '</span>');
					$submitBtn.prop('disabled', false);
				}
			},
			error: function() {
				$status.html('<span class="aie-error-message"><span class="dashicons dashicons-warning"></span> An error occurred</span>');
				$submitBtn.prop('disabled', false);
			}
		});
	});

	// Test API connection
	$('.aie-test-api-key').on('click', function() {
		const $btn = $(this);
		const $result = $('#aie-api-test-result');
		const apiKey = $('#aie-openai-api-key').val() || '<?php echo esc_js( $has_constant_key ? 'CONSTANT_DEFINED' : '' ); ?>';
		
		$btn.prop('disabled', true);
		$result.html('<div class="aie-info-box"><span class="spinner is-active"></span> Testing connection...</div>').show();
		
		// Simple test - we'll just check if key is configured
		setTimeout(function() {
			if (apiKey && apiKey !== '') {
				$result.html('<div class="aie-info-box aie-success"><span class="dashicons dashicons-yes-alt"></span> <strong>API key is configured.</strong><br>Try generating a function to test the actual connection.</div>');
			} else {
				$result.html('<div class="aie-info-box aie-error"><span class="dashicons dashicons-warning"></span> <strong>No API key configured.</strong><br>Please enter your OpenAI API key and save settings.</div>');
			}
			$btn.prop('disabled', false);
		}, 1000);
	});
});
</script>
