<?php
/**
 * Function Editor Modal
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Function Editor Modal -->
<div id="rsl-ie-function-editor-modal" class="rsl-ie-modal" style="display:none;">
	<div class="rsl-ie-modal-backdrop"></div>
	<div class="rsl-ie-modal-content">
		<div class="rsl-ie-modal-header">
			<h2 class="rsl-ie-modal-title"><?php esc_html_e( 'Edit Function', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="rsl-ie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="rsl-ie-modal-body">
			<form id="rsl-ie-function-form">
				<input type="hidden" id="rsl-ie-function-id" value="">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="rsl-ie-function-name">
								<?php esc_html_e( 'Function Name', 'import-export-by-rockstarlab' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<input type="text" id="rsl-ie-function-name" class="regular-text">
							<p class="description"><?php esc_html_e( 'A descriptive name for this function', 'import-export-by-rockstarlab' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="rsl-ie-function-description"><?php esc_html_e( 'Description', 'import-export-by-rockstarlab' ); ?></label>
						</th>
						<td>
							<textarea id="rsl-ie-function-description" class="large-text" rows="3"></textarea>
							<p class="description"><?php esc_html_e( 'Optional description of what this function does', 'import-export-by-rockstarlab' ); ?></p>
						</td>
					</tr>

					<?php
					$rsl_ie_has_api_key   = \RockStarLab\ImportExport\Helper\AI_Function_Generator::has_api_key();
					$rsl_ie_feature_ready = $rsl_ie_has_api_key;
					$rsl_ie_is_wp7_plus   = \RockStarLab\ImportExport\Helper\OpenAI_API_Key::is_wp7_plus();
					?>
					<tr class="rsl-ie-ai-generate-section">
						<th scope="row">
							<label><?php esc_html_e( 'AI Generator', 'import-export-by-rockstarlab' ); ?></label>
						</th>
						<td>
								<button type="button" class="button rsl-ie-generate-with-ai" <?php echo ! $rsl_ie_feature_ready ? 'disabled' : ''; ?>>
									<span class="dashicons dashicons-admin-generic"></span>
									<?php esc_html_e( 'Generate function with AI', 'import-export-by-rockstarlab' ); ?>
								</button>
								<p class="description">
									<?php esc_html_e( 'Describe what you want the function to do, and AI will generate the code for you.', 'import-export-by-rockstarlab' ); ?>
								</p>
							<?php if ( ! $rsl_ie_has_api_key ) : ?>
								<div class="notice notice-warning inline">
									<p>
										<?php
										if ( $rsl_ie_is_wp7_plus ) {
											echo wp_kses_post(
												sprintf(
													/* translators: 1: connectors URL, 2: plugin options URL */
													__( '<strong>API Key Required:</strong> OpenAI API key is not configured. Please add it in <a href="%1$s">Settings → Connectors</a> or in <a href="%2$s">Plugin Options</a> to use AI generation.', 'import-export-by-rockstarlab' ),
													esc_url( admin_url( 'options-connectors.php' ) ),
													esc_url( admin_url( 'admin.php?page=rsl-ie-plugin-options' ) )
												)
											);
										} else {
											echo wp_kses_post(
												sprintf(
													/* translators: %s: plugin options URL */
													__( '<strong>API Key Required:</strong> OpenAI API key is not configured. Please add it in <a href="%s">Plugin Options</a> to use AI generation.', 'import-export-by-rockstarlab' ),
													esc_url( admin_url( 'admin.php?page=rsl-ie-plugin-options' ) )
												)
											);
										}
										?>
									</p>
								</div>
							<?php endif; ?>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="rsl-ie-function-code">
								<?php esc_html_e( 'PHP Code', 'import-export-by-rockstarlab' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<textarea id="rsl-ie-function-code" class="large-text code" rows="10" spellcheck="false"></textarea>
							<p class="description">
								<?php esc_html_e( 'Enter PHP code without opening/closing tags. Use $value for input. Example:', 'import-export-by-rockstarlab' ); ?>
								<code>return strtoupper($value);</code>
							</p>
							<p class="description">
								<strong><?php esc_html_e( 'Security:', 'import-export-by-rockstarlab' ); ?></strong>
								<?php esc_html_e( 'Dangerous functions (eval, exec, file operations) are blocked.', 'import-export-by-rockstarlab' ); ?>
							</p>
						</td>
					</tr>

					<tr style="display:none;">
						<th scope="row">
							<label for="rsl-ie-function-status"><?php esc_html_e( 'Status', 'import-export-by-rockstarlab' ); ?></label>
						</th>
						<td>
							<select id="rsl-ie-function-status">
								<option value="active"><?php esc_html_e( 'Active', 'import-export-by-rockstarlab' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'import-export-by-rockstarlab' ); ?></option>
							</select>
						</td>
					</tr>

					<tr class="rsl-ie-test-section">
						<th scope="row">
							<label for="rsl-ie-test-value"><?php esc_html_e( 'Test Function', 'import-export-by-rockstarlab' ); ?></label>
						</th>
						<td>
							<div class="rsl-ie-test-controls">
								<input type="text" id="rsl-ie-test-value" class="regular-text" placeholder="<?php esc_attr_e( 'Enter test value...', 'import-export-by-rockstarlab' ); ?>">
								<button type="button" class="button rsl-ie-test-function">
									<span class="dashicons dashicons-media-code"></span>
									<?php esc_html_e( 'Test', 'import-export-by-rockstarlab' ); ?>
								</button>
							</div>
							<div class="rsl-ie-test-results" style="display:none;">
								<div class="rsl-ie-test-result">
									<strong><?php esc_html_e( 'Input:', 'import-export-by-rockstarlab' ); ?></strong>
									<code class="rsl-ie-test-input"></code>
								</div>
								<div class="rsl-ie-test-result">
									<strong><?php esc_html_e( 'Output:', 'import-export-by-rockstarlab' ); ?></strong>
									<code class="rsl-ie-test-output"></code>
								</div>
							</div>
						</td>
					</tr>
				</table>
				
				<!-- Hidden fields for category and status -->
				<input type="hidden" id="rsl-ie-function-category" value="custom">
			</form>
		</div>

		<div class="rsl-ie-modal-footer">
			<button type="button" class="button button-secondary rsl-ie-modal-cancel">
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary rsl-ie-save-function">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Save Function', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- AI Function Generator Modal -->
<div id="rsl-ie-ai-prompt-modal" class="rsl-ie-modal" style="display:none;">
	<div class="rsl-ie-modal-backdrop"></div>
	<div class="rsl-ie-modal-content rsl-ie-ai-prompt-modal-content">
		<div class="rsl-ie-modal-header">
			<h2 class="rsl-ie-modal-title">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Generate Function with AI', 'import-export-by-rockstarlab' ); ?>
			</h2>
			<button type="button" class="rsl-ie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="rsl-ie-modal-body">
			<div class="rsl-ie-ai-prompt-container">
				<label for="rsl-ie-ai-prompt">
					<?php esc_html_e( 'Describe what you want the function to do:', 'import-export-by-rockstarlab' ); ?>
					<span class="required">*</span>
				</label>
				<textarea 
					id="rsl-ie-ai-prompt" 
					class="large-text" 
					rows="8" 
					placeholder="<?php esc_attr_e( 'Example: Convert text to uppercase and remove all spaces', 'import-export-by-rockstarlab' ); ?>"
				></textarea>
				<p class="description">
					<?php esc_html_e( 'Be as specific as possible. The AI will generate a PHP function based on your description.', 'import-export-by-rockstarlab' ); ?>
				</p>

				<div class="rsl-ie-ai-examples">
					<strong><?php esc_html_e( 'Example prompts:', 'import-export-by-rockstarlab' ); ?></strong>
					<ul>
						<li>
							<a href="#" class="rsl-ie-use-example" data-prompt="<?php esc_attr_e( 'Convert text to uppercase', 'import-export-by-rockstarlab' ); ?>">
								<?php esc_html_e( 'Convert text to uppercase', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<a href="#" class="rsl-ie-use-example" data-prompt="<?php esc_attr_e( 'Remove all HTML tags and trim whitespace', 'import-export-by-rockstarlab' ); ?>">
								<?php esc_html_e( 'Remove all HTML tags and trim whitespace', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<a href="#" class="rsl-ie-use-example" data-prompt="<?php esc_attr_e( 'Format phone number to (XXX) XXX-XXXX format', 'import-export-by-rockstarlab' ); ?>">
								<?php esc_html_e( 'Format phone number to (XXX) XXX-XXXX format', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
						<li>
							<a href="#" class="rsl-ie-use-example" data-prompt="<?php esc_attr_e( 'Extract domain from email address', 'import-export-by-rockstarlab' ); ?>">
								<?php esc_html_e( 'Extract domain from email address', 'import-export-by-rockstarlab' ); ?>
							</a>
						</li>
					</ul>
				</div>

				<div class="rsl-ie-ai-generating" style="display:none;">
					<div class="rsl-ie-ai-loader">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'AI is generating your function...', 'import-export-by-rockstarlab' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<div class="rsl-ie-modal-footer">
			<button type="button" class="button button-secondary rsl-ie-modal-cancel">
				<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary rsl-ie-generate-code">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'Generate Code', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
