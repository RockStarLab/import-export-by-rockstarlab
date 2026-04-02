<?php
/**
 * Function Editor Modal
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Function Editor Modal -->
<div id="aie-function-editor-modal" class="aie-modal" style="display:none;">
	<div class="aie-modal-backdrop"></div>
	<div class="aie-modal-content">
		<div class="aie-modal-header">
			<h2 class="aie-modal-title"><?php esc_html_e( 'Edit Function', 'advanced-import-export' ); ?></h2>
			<button type="button" class="aie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="aie-modal-body">
			<form id="aie-function-form">
				<input type="hidden" id="aie-function-id" value="">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="aie-function-name">
								<?php esc_html_e( 'Function Name', 'advanced-import-export' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<input type="text" id="aie-function-name" class="regular-text">
							<p class="description"><?php esc_html_e( 'A descriptive name for this function', 'advanced-import-export' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-function-description"><?php esc_html_e( 'Description', 'advanced-import-export' ); ?></label>
						</th>
						<td>
							<textarea id="aie-function-description" class="large-text" rows="3"></textarea>
							<p class="description"><?php esc_html_e( 'Optional description of what this function does', 'advanced-import-export' ); ?></p>
						</td>
					</tr>

					<?php
					$is_premium = function_exists( 'aie_fs' ) && aie_fs()->can_use_premium_code();
					$has_api_key = \WP_AIE\Helper\AI_Function_Generator::has_api_key();
					?>
					<tr class="aie-ai-generate-section">
						<th scope="row">
							<label><?php esc_html_e( 'AI Generator', 'advanced-import-export' ); ?></label>
						</th>
						<td>
							<?php if ( $is_premium ) : ?>
								<button type="button" class="button aie-generate-with-ai">
									<span class="dashicons dashicons-admin-generic"></span>
									<?php esc_html_e( 'Generate function with AI', 'advanced-import-export' ); ?>
								</button>
								<p class="description">
									<?php esc_html_e( 'Describe what you want the function to do, and AI will generate the code for you.', 'advanced-import-export' ); ?>
								</p>
								<?php if ( ! $has_api_key ) : ?>
									<div class="notice notice-warning inline">
										<p>
											<?php
											echo wp_kses_post(
												sprintf(
													/* translators: %s: plugin options URL */
													__( '<strong>API Key Required:</strong> OpenAI API key is not configured. Please add it in <a href="%s">Plugin Options</a> to use AI generation.', 'advanced-import-export' ),
													esc_url( admin_url( 'admin.php?page=wp-aie-plugin-options' ) )
												)
											);
											?>
										</p>
									</div>
								<?php endif; ?>
							<?php else : ?>
								<button type="button" class="button aie-premium-locked" disabled>
									<span class="dashicons dashicons-lock"></span>
									<?php esc_html_e( 'Generate function with AI', 'advanced-import-export' ); ?>
									<span class="aie-premium-badge">Premium</span>
								</button>
								<p class="description">
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: %s: upgrade URL */
											__( 'AI function generation is a premium feature. <a href="%s">Upgrade to Premium</a> to unlock this feature.', 'advanced-import-export' ),
											esc_url( aie_fs()->get_upgrade_url() )
										)
									);
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-function-code">
								<?php esc_html_e( 'PHP Code', 'advanced-import-export' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<textarea id="aie-function-code" class="large-text code" rows="10" spellcheck="false"></textarea>
							<p class="description">
								<?php esc_html_e( 'Enter PHP code without opening/closing tags. Use $value for input. Example:', 'advanced-import-export' ); ?>
								<code>return strtoupper($value);</code>
							</p>
							<p class="description">
								<strong><?php esc_html_e( 'Security:', 'advanced-import-export' ); ?></strong>
								<?php esc_html_e( 'Dangerous functions (eval, exec, file operations) are blocked.', 'advanced-import-export' ); ?>
							</p>
						</td>
					</tr>

					<tr style="display:none;">
						<th scope="row">
							<label for="aie-function-status"><?php esc_html_e( 'Status', 'advanced-import-export' ); ?></label>
						</th>
						<td>
							<select id="aie-function-status">
								<option value="active"><?php esc_html_e( 'Active', 'advanced-import-export' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'advanced-import-export' ); ?></option>
							</select>
						</td>
					</tr>

					<tr class="aie-test-section">
						<th scope="row">
							<label for="aie-test-value"><?php esc_html_e( 'Test Function', 'advanced-import-export' ); ?></label>
						</th>
						<td>
							<div class="aie-test-controls">
								<input type="text" id="aie-test-value" class="regular-text" placeholder="<?php esc_attr_e( 'Enter test value...', 'advanced-import-export' ); ?>">
								<button type="button" class="button aie-test-function">
									<span class="dashicons dashicons-media-code"></span>
									<?php esc_html_e( 'Test', 'advanced-import-export' ); ?>
								</button>
							</div>
							<div class="aie-test-results" style="display:none;">
								<div class="aie-test-result">
									<strong><?php esc_html_e( 'Input:', 'advanced-import-export' ); ?></strong>
									<code class="aie-test-input"></code>
								</div>
								<div class="aie-test-result">
									<strong><?php esc_html_e( 'Output:', 'advanced-import-export' ); ?></strong>
									<code class="aie-test-output"></code>
								</div>
							</div>
						</td>
					</tr>
				</table>
				
				<!-- Hidden fields for category and status -->
				<input type="hidden" id="aie-function-category" value="custom">
			</form>
		</div>

		<div class="aie-modal-footer">
			<button type="button" class="button button-secondary aie-modal-cancel">
				<?php esc_html_e( 'Cancel', 'advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary aie-save-function">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Save Function', 'advanced-import-export' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- AI Function Generator Modal -->
<div id="aie-ai-prompt-modal" class="aie-modal" style="display:none;">
	<div class="aie-modal-backdrop"></div>
	<div class="aie-modal-content aie-ai-prompt-modal-content">
		<div class="aie-modal-header">
			<h2 class="aie-modal-title">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Generate Function with AI', 'advanced-import-export' ); ?>
			</h2>
			<button type="button" class="aie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="aie-modal-body">
			<div class="aie-ai-prompt-container">
				<label for="aie-ai-prompt">
					<?php esc_html_e( 'Describe what you want the function to do:', 'advanced-import-export' ); ?>
					<span class="required">*</span>
				</label>
				<textarea 
					id="aie-ai-prompt" 
					class="large-text" 
					rows="8" 
					placeholder="<?php esc_attr_e( 'Example: Convert text to uppercase and remove all spaces', 'advanced-import-export' ); ?>"
				></textarea>
				<p class="description">
					<?php esc_html_e( 'Be as specific as possible. The AI will generate a PHP function based on your description.', 'advanced-import-export' ); ?>
				</p>

				<div class="aie-ai-examples">
					<strong><?php esc_html_e( 'Example prompts:', 'advanced-import-export' ); ?></strong>
					<ul>
						<li>
							<a href="#" class="aie-use-example" data-prompt="<?php esc_attr_e( 'Convert text to uppercase', 'advanced-import-export' ); ?>">
								<?php esc_html_e( 'Convert text to uppercase', 'advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<a href="#" class="aie-use-example" data-prompt="<?php esc_attr_e( 'Remove all HTML tags and trim whitespace', 'advanced-import-export' ); ?>">
								<?php esc_html_e( 'Remove all HTML tags and trim whitespace', 'advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<a href="#" class="aie-use-example" data-prompt="<?php esc_attr_e( 'Format phone number to (XXX) XXX-XXXX format', 'advanced-import-export' ); ?>">
								<?php esc_html_e( 'Format phone number to (XXX) XXX-XXXX format', 'advanced-import-export' ); ?>
							</a>
						</li>
						<li>
							<a href="#" class="aie-use-example" data-prompt="<?php esc_attr_e( 'Extract domain from email address', 'advanced-import-export' ); ?>">
								<?php esc_html_e( 'Extract domain from email address', 'advanced-import-export' ); ?>
							</a>
						</li>
					</ul>
				</div>

				<div class="aie-ai-generating" style="display:none;">
					<div class="aie-ai-loader">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'AI is generating your function...', 'advanced-import-export' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<div class="aie-modal-footer">
			<button type="button" class="button button-secondary aie-modal-cancel">
				<?php esc_html_e( 'Cancel', 'advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary aie-generate-code">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Generate Code', 'advanced-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
