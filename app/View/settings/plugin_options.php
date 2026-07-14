<?php
/**
 * Plugin Options Settings Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

use RockStarLab\ImportExport\Helper\OpenAI_API_Key;

// Get current settings
$rsl_ie_openai_api_key = get_option( 'rsl_ie_openai_api_key', '' );

// WP 7+ AI Client: text generation provider configured.
$rsl_ie_is_wp7_plus        = OpenAI_API_Key::is_wp7_plus();
$rsl_ie_has_wp_ai_client   = OpenAI_API_Key::has_wp_ai_client();
$rsl_ie_has_any_openai_key = OpenAI_API_Key::has_api_key();

// Mask the API key for display
$rsl_ie_masked_api_key = '';
if ( ! empty( $rsl_ie_openai_api_key ) ) {
	$rsl_ie_masked_api_key = substr( $rsl_ie_openai_api_key, 0, 7 ) . '...' . substr( $rsl_ie_openai_api_key, -4 );
}
?>

<div id="rsl-ie-plugin-options" class="wrap">
	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Plugin Options', 'import-export-by-rockstarlab' ); ?>
	</h1>
	<?php
	$rsl_ie_options_active_tab = 'ai';
	require RSL_IE_PATH . 'app/View/settings/partials/plugin-options-tabs.php';
	?>

	<div class="rsl-ie-settings-container">
		<!-- AI Integration Section -->
		<div class="rsl-ie-settings-section">
			<div class="rsl-ie-settings-section-header">
				<h2>
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'AI Integration', 'import-export-by-rockstarlab' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Configure AI features powered by OpenAI.', 'import-export-by-rockstarlab' ); ?>
				</p>
			</div>

				<div class="rsl-ie-settings-section-body">
				<?php if ( $rsl_ie_is_wp7_plus ) : ?>
					<?php if ( $rsl_ie_has_wp_ai_client ) : ?>
					<div class="rsl-ie-info-box rsl-ie-success">
						<span class="dashicons dashicons-yes-alt"></span>
						<div>
							<strong><?php esc_html_e( 'This site is already integrated with WordPress AI Client', 'import-export-by-rockstarlab' ); ?></strong>
							<p>
								<?php
								echo wp_kses_post(
									sprintf(
										/* translators: %s: URL to WordPress Connectors screen */
										__( 'AI generation is available through WordPress AI Client. Manage providers in <a href="%s">Settings → Connectors</a>.', 'import-export-by-rockstarlab' ),
										esc_url( admin_url( 'options-connectors.php' ) )
									)
								);
								?>
							</p>
						</div>
					</div>
					<?php else : ?>
					<div class="rsl-ie-info-box rsl-ie-warning">
						<span class="dashicons dashicons-info"></span>
						<div>
							<strong><?php esc_html_e( 'Configure WordPress AI Client', 'import-export-by-rockstarlab' ); ?></strong>
							<p>
								<?php
								echo wp_kses_post(
									sprintf(
										/* translators: %s: URL to WordPress Connectors screen */
										__( 'AI generation on WordPress 7.0+ uses WordPress AI Client. Configure a text-generation provider in <a href="%s">Settings → Connectors</a>.', 'import-export-by-rockstarlab' ),
										esc_url( admin_url( 'options-connectors.php' ) )
									)
								);
								?>
							</p>
							<a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>" class="button button-primary">
								<?php esc_html_e( 'Open Settings → Connectors', 'import-export-by-rockstarlab' ); ?>
							</a>
						</div>
					</div>
					<?php endif; ?>
				<?php else : ?>
				<form id="rsl-ie-settings-form" class="rsl-ie-settings-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="rsl-ie-openai-api-key">
									<?php esc_html_e( 'OpenAI API Key', 'import-export-by-rockstarlab' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="password" 
									id="rsl-ie-openai-api-key" 
									name="openai_api_key"
									class="regular-text" 
									value="<?php echo esc_attr( $rsl_ie_openai_api_key ); ?>"
									placeholder="sk-proj-..."
								>
								<button type="button" class="button rsl-ie-toggle-password" data-target="rsl-ie-openai-api-key">
									<span class="dashicons dashicons-visibility"></span>
								</button>
								<?php if ( ! empty( $rsl_ie_openai_api_key ) ) : ?>
									<p class="description">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: masked API key */
												__( 'Current key: %s', 'import-export-by-rockstarlab' ),
												$rsl_ie_masked_api_key
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
											__( 'Get your API key from <a href="%s" target="_blank">OpenAI Platform</a>. Required for the AI URL Importer on WordPress versions below 7.0.', 'import-export-by-rockstarlab' ),
											'https://platform.openai.com/api-keys'
										)
									);
									?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'API Status', 'import-export-by-rockstarlab' ); ?>
							</th>
							<td>
								<div id="rsl-ie-api-status" class="rsl-ie-api-status">
									<?php if ( $rsl_ie_has_any_openai_key ) : ?>
										<span class="rsl-ie-status-badge rsl-ie-status-configured">
											<span class="dashicons dashicons-yes-alt"></span>
											<?php esc_html_e( 'Configured', 'import-export-by-rockstarlab' ); ?>
										</span>
										<button type="button" class="button button-secondary rsl-ie-test-api-key">
											<span class="dashicons dashicons-update"></span>
											<?php esc_html_e( 'Test Connection', 'import-export-by-rockstarlab' ); ?>
										</button>
									<?php else : ?>
										<span class="rsl-ie-status-badge rsl-ie-status-not-configured">
											<span class="dashicons dashicons-warning"></span>
											<?php esc_html_e( 'Not Configured', 'import-export-by-rockstarlab' ); ?>
										</span>
									<?php endif; ?>
								</div>
								<div id="rsl-ie-api-test-result" style="display:none; margin-top:10px;"></div>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<?php esc_html_e( 'Model', 'import-export-by-rockstarlab' ); ?>
							</th>
							<td>
								<span class="rsl-ie-info-badge">GPT-4o-mini</span>
							</td>
						</tr>
					</table>

					<div class="rsl-ie-settings-footer">
						<button type="submit" class="button button-primary rsl-ie-save-settings">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Save Settings', 'import-export-by-rockstarlab' ); ?>
						</button>
						<span class="rsl-ie-settings-status"></span>
					</div>
				</form>
				<?php endif; ?>
			</div>
		</div>

		<!-- Information Section -->
		<div class="rsl-ie-settings-section">
			<div class="rsl-ie-settings-section-header">
				<h2>
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'About AI Features', 'import-export-by-rockstarlab' ); ?>
				</h2>
			</div>

			<div class="rsl-ie-settings-section-body">
				<div class="rsl-ie-info-cards">

					<div class="rsl-ie-info-card">
						<span class="dashicons dashicons-admin-links"></span>
						<h3><?php esc_html_e( 'AI URL Importer', 'import-export-by-rockstarlab' ); ?></h3>
						<p><?php esc_html_e( 'Import content directly from URLs using AI-powered extraction. Automatically extracts and structures content from web pages into WordPress posts.', 'import-export-by-rockstarlab' ); ?></p>
					</div>

					<div class="rsl-ie-info-card">
						<span class="dashicons dashicons-chart-area"></span>
						<h3><?php esc_html_e( 'Pricing & Tokens', 'import-export-by-rockstarlab' ); ?></h3>
						<p>
							<?php esc_html_e( 'AI features use OpenAI GPT-4o-mini model. Typical costs:', 'import-export-by-rockstarlab' ); ?>
						</p>
						<ul style="list-style: disc; margin: 10px 0; padding-left: 20px; font-size: 0.95em;">
							<li><?php esc_html_e( 'URL import: ~$0.001-0.005 per page', 'import-export-by-rockstarlab' ); ?></li>
						</ul>
						<p style="font-size: 0.9em; color: #666;">
							<?php esc_html_e( 'You pay OpenAI directly based on usage. Monitor costs in your OpenAI dashboard.', 'import-export-by-rockstarlab' ); ?>
						</p>
					</div>
				</div>

				<div class="rsl-ie-help-section">
					<h3>
						<span class="dashicons dashicons-editor-help"></span>
						<?php esc_html_e( 'Need Help?', 'import-export-by-rockstarlab' ); ?>
					</h3>
					<ul>
						<li>
							<a href="https://platform.openai.com/account/api-keys" target="_blank">
								<?php esc_html_e( 'Manage API Keys', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<a href="https://platform.openai.com/account/billing" target="_blank">
								<?php esc_html_e( 'View Usage & Billing', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<a href="https://platform.openai.com/docs/api-reference" target="_blank">
								<?php esc_html_e( 'OpenAI API Documentation', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
