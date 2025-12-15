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
			<h2 class="aie-modal-title"><?php esc_html_e( 'Edit Function', 'wp-advanced-import-export' ); ?></h2>
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
								<?php esc_html_e( 'Function Name', 'wp-advanced-import-export' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<input type="text" id="aie-function-name" class="regular-text">
							<p class="description"><?php esc_html_e( 'A descriptive name for this function', 'wp-advanced-import-export' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-function-description"><?php esc_html_e( 'Description', 'wp-advanced-import-export' ); ?></label>
						</th>
						<td>
							<textarea id="aie-function-description" class="large-text" rows="3"></textarea>
							<p class="description"><?php esc_html_e( 'Optional description of what this function does', 'wp-advanced-import-export' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-function-code">
								<?php esc_html_e( 'PHP Code', 'wp-advanced-import-export' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<textarea id="aie-function-code" class="large-text code" rows="10" spellcheck="false"></textarea>
							<p class="description">
								<?php esc_html_e( 'Enter PHP code without opening/closing tags. Use $value for input. Example:', 'wp-advanced-import-export' ); ?>
								<code>return strtoupper($value);</code>
							</p>
							<p class="description">
								<strong><?php esc_html_e( 'Security:', 'wp-advanced-import-export' ); ?></strong>
								<?php esc_html_e( 'Dangerous functions (eval, exec, file operations) are blocked.', 'wp-advanced-import-export' ); ?>
							</p>
						</td>
					</tr>

					<tr style="display:none;">
						<th scope="row">
							<label for="aie-function-status"><?php esc_html_e( 'Status', 'wp-advanced-import-export' ); ?></label>
						</th>
						<td>
							<select id="aie-function-status">
								<option value="active"><?php esc_html_e( 'Active', 'wp-advanced-import-export' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'wp-advanced-import-export' ); ?></option>
							</select>
						</td>
					</tr>

					<tr class="aie-test-section">
						<th scope="row">
							<label for="aie-test-value"><?php esc_html_e( 'Test Function', 'wp-advanced-import-export' ); ?></label>
						</th>
						<td>
							<div class="aie-test-controls">
								<input type="text" id="aie-test-value" class="regular-text" placeholder="<?php esc_attr_e( 'Enter test value...', 'wp-advanced-import-export' ); ?>">
								<button type="button" class="button aie-test-function">
									<span class="dashicons dashicons-media-code"></span>
									<?php esc_html_e( 'Test', 'wp-advanced-import-export' ); ?>
								</button>
							</div>
							<div class="aie-test-results" style="display:none;">
								<div class="aie-test-result">
									<strong><?php esc_html_e( 'Input:', 'wp-advanced-import-export' ); ?></strong>
									<code class="aie-test-input"></code>
								</div>
								<div class="aie-test-result">
									<strong><?php esc_html_e( 'Output:', 'wp-advanced-import-export' ); ?></strong>
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
				<?php esc_html_e( 'Cancel', 'wp-advanced-import-export' ); ?>
			</button>
			<button type="button" class="button button-primary aie-save-function">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Save Function', 'wp-advanced-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
