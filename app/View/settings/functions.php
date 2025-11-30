<?php
/**
 * Functions Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-functions" class="wrap">
	<h1>
		<?php esc_html_e( 'Custom Functions', 'wp-aie' ); ?>
		<button type="button" class="page-title-action aie-new-function">
			<span class="dashicons dashicons-plus-alt"></span>
			<?php esc_html_e( 'New Function', 'wp-aie' ); ?>
		</button>
		<button type="button" class="page-title-action aie-browse-library">
			<span class="dashicons dashicons-book"></span>
			<?php esc_html_e( 'Browse Library', 'wp-aie' ); ?>
		</button>
	</h1>

	<p class="description">
		<?php esc_html_e( 'Create custom PHP functions to transform data during import and export. Browse the library for ready-to-use examples.', 'wp-aie' ); ?>
	</p>

	<!-- Filters -->
	<div class="aie-functions-filters">
		<div class="aie-filter-group">
			<label for="aie-filter-status"><?php esc_html_e( 'Status:', 'wp-aie' ); ?></label>
			<select id="aie-filter-status">
				<option value=""><?php esc_html_e( 'All Statuses', 'wp-aie' ); ?></option>
				<option value="active"><?php esc_html_e( 'Active', 'wp-aie' ); ?></option>
				<option value="inactive"><?php esc_html_e( 'Inactive', 'wp-aie' ); ?></option>
			</select>
		</div>

		<div class="aie-filter-group">
			<label for="aie-filter-category"><?php esc_html_e( 'Category:', 'wp-aie' ); ?></label>
			<select id="aie-filter-category">
				<option value=""><?php esc_html_e( 'All Categories', 'wp-aie' ); ?></option>
				<option value="library"><?php esc_html_e( 'Library', 'wp-aie' ); ?></option>
				<option value="custom"><?php esc_html_e( 'Custom', 'wp-aie' ); ?></option>
			</select>
		</div>

		<div class="aie-filter-group">
			<label for="aie-filter-search"><?php esc_html_e( 'Search:', 'wp-aie' ); ?></label>
			<input type="text" id="aie-filter-search" placeholder="<?php esc_attr_e( 'Search functions...', 'wp-aie' ); ?>">
		</div>

		<button type="button" class="button aie-filter-clear">
			<?php esc_html_e( 'Clear Filters', 'wp-aie' ); ?>
		</button>
	</div>

	<!-- Functions Table -->
	<div class="aie-functions-table-container">
		<table class="wp-list-table widefat fixed striped aie-functions-table">
			<thead>
				<tr>
					<th class="column-name"><?php esc_html_e( 'Name', 'wp-aie' ); ?></th>
					<th class="column-description"><?php esc_html_e( 'Description', 'wp-aie' ); ?></th>
					<th class="column-category"><?php esc_html_e( 'Category', 'wp-aie' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'wp-aie' ); ?></th>
					<th class="column-usage"><?php esc_html_e( 'Usage', 'wp-aie' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'wp-aie' ); ?></th>
				</tr>
			</thead>
			<tbody id="aie-functions-tbody">
				<tr class="aie-loading-row">
					<td colspan="6" style="text-align:center;">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Loading functions...', 'wp-aie' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<div class="aie-functions-pagination">
		<div class="aie-pagination-info"></div>
		<div class="aie-pagination-controls">
			<button type="button" class="button aie-prev-page" disabled>
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Previous', 'wp-aie' ); ?>
			</button>
			<span class="aie-page-info">
				<?php esc_html_e( 'Page', 'wp-aie' ); ?> <span class="aie-current-page">1</span> / <span class="aie-total-pages">1</span>
			</span>
			<button type="button" class="button aie-next-page" disabled>
				<?php esc_html_e( 'Next', 'wp-aie' ); ?>
				<span class="dashicons dashicons-arrow-right-alt2"></span>
			</button>
		</div>
	</div>
</div>

<!-- Function Editor Modal -->
<div id="aie-function-editor-modal" class="aie-modal" style="display:none;">
	<div class="aie-modal-backdrop"></div>
	<div class="aie-modal-content">
		<div class="aie-modal-header">
			<h2 class="aie-modal-title"><?php esc_html_e( 'Edit Function', 'wp-aie' ); ?></h2>
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
								<?php esc_html_e( 'Function Name', 'wp-aie' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<input type="text" id="aie-function-name" class="regular-text">
							<p class="description"><?php esc_html_e( 'A descriptive name for this function', 'wp-aie' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-function-description"><?php esc_html_e( 'Description', 'wp-aie' ); ?></label>
						</th>
						<td>
							<textarea id="aie-function-description" class="large-text" rows="3"></textarea>
							<p class="description"><?php esc_html_e( 'Optional description of what this function does', 'wp-aie' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="aie-function-code">
								<?php esc_html_e( 'PHP Code', 'wp-aie' ); ?>
								<span class="required">*</span>
							</label>
						</th>
						<td>
							<textarea id="aie-function-code" class="large-text code" rows="10" spellcheck="false"></textarea>
							<p class="description">
								<?php esc_html_e( 'Enter PHP code without opening/closing tags. Use $value for input. Example:', 'wp-aie' ); ?>
								<code>return strtoupper($value);</code>
							</p>
							<p class="description">
								<strong><?php esc_html_e( 'Security:', 'wp-aie' ); ?></strong>
								<?php esc_html_e( 'Dangerous functions (eval, exec, file operations) are blocked.', 'wp-aie' ); ?>
							</p>
						</td>
					</tr>

					<tr style="display:none;">
						<th scope="row">
							<label for="aie-function-status"><?php esc_html_e( 'Status', 'wp-aie' ); ?></label>
						</th>
						<td>
							<select id="aie-function-status">
								<option value="active"><?php esc_html_e( 'Active', 'wp-aie' ); ?></option>
								<option value="inactive"><?php esc_html_e( 'Inactive', 'wp-aie' ); ?></option>
							</select>
						</td>
					</tr>

					<tr class="aie-test-section">
						<th scope="row">
							<label for="aie-test-value"><?php esc_html_e( 'Test Function', 'wp-aie' ); ?></label>
						</th>
						<td>
							<div class="aie-test-controls">
								<input type="text" id="aie-test-value" class="regular-text" placeholder="<?php esc_attr_e( 'Enter test value...', 'wp-aie' ); ?>">
								<button type="button" class="button aie-test-function">
									<span class="dashicons dashicons-media-code"></span>
									<?php esc_html_e( 'Test', 'wp-aie' ); ?>
								</button>
							</div>
							<div class="aie-test-results" style="display:none;">
								<div class="aie-test-result">
									<strong><?php esc_html_e( 'Input:', 'wp-aie' ); ?></strong>
									<code class="aie-test-input"></code>
								</div>
								<div class="aie-test-result">
									<strong><?php esc_html_e( 'Output:', 'wp-aie' ); ?></strong>
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
				<?php esc_html_e( 'Cancel', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button button-primary aie-save-function">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Save Function', 'wp-aie' ); ?>
			</button>
		</div>
	</div>
</div>

<!-- Snippets Library Modal -->
<div id="aie-snippets-library-modal" class="aie-modal aie-library-modal" style="display:none;">
	<div class="aie-modal-backdrop"></div>
	<div class="aie-modal-content aie-library-content">
		<div class="aie-modal-header">
			<h2 class="aie-modal-title">
				<span class="dashicons dashicons-book"></span>
				<?php esc_html_e( 'Function Library', 'wp-aie' ); ?>
			</h2>
			<button type="button" class="aie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="aie-library-search">
			<input type="text" id="aie-snippet-search" class="widefat" placeholder="<?php esc_attr_e( 'Search snippets...', 'wp-aie' ); ?>">
		</div>

		<div class="aie-modal-body aie-library-body">
			<!-- Sidebar with categories -->
			<div class="aie-library-sidebar">
				<h3><?php esc_html_e( 'Categories', 'wp-aie' ); ?></h3>
				<ul class="aie-categories-list" id="aie-categories-list">
					<li class="aie-category-item active" data-category="">
						<span class="dashicons dashicons-category"></span>
						<?php esc_html_e( 'All Snippets', 'wp-aie' ); ?>
						<span class="aie-category-count">0</span>
					</li>
				</ul>
			</div>

			<!-- Main content area with snippet cards -->
			<div class="aie-library-main">
				<div class="aie-snippets-grid" id="aie-snippets-grid">
					<div class="aie-loading-snippets">
						<span class="spinner is-active"></span>
						<p><?php esc_html_e( 'Loading snippets...', 'wp-aie' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Snippet Preview Modal -->
<div id="aie-snippet-preview-modal" class="aie-modal" style="display:none;">
	<div class="aie-modal-backdrop"></div>
	<div class="aie-modal-content">
		<div class="aie-modal-header">
			<h2 class="aie-modal-title aie-snippet-title"></h2>
			<button type="button" class="aie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="aie-modal-body">
			<p class="aie-snippet-description"></p>

			<div class="aie-snippet-details">
				<div class="aie-snippet-detail">
					<strong><?php esc_html_e( 'Category:', 'wp-aie' ); ?></strong>
					<span class="aie-snippet-category"></span>
				</div>
				<div class="aie-snippet-detail">
					<strong><?php esc_html_e( 'Tags:', 'wp-aie' ); ?></strong>
					<span class="aie-snippet-tags"></span>
				</div>
			</div>

			<h3><?php esc_html_e( 'Code:', 'wp-aie' ); ?></h3>
			<pre><code class="aie-snippet-code"></code></pre>

			<div class="aie-snippet-example">
				<h3><?php esc_html_e( 'Example:', 'wp-aie' ); ?></h3>
				<div class="aie-example-io">
					<div class="aie-example-input">
						<strong><?php esc_html_e( 'Input:', 'wp-aie' ); ?></strong>
						<code class="aie-example-input-value"></code>
					</div>
					<span class="dashicons dashicons-arrow-right-alt"></span>
					<div class="aie-example-output">
						<strong><?php esc_html_e( 'Output:', 'wp-aie' ); ?></strong>
						<code class="aie-example-output-value"></code>
					</div>
				</div>
			</div>
		</div>

		<div class="aie-modal-footer">
			<button type="button" class="button button-secondary aie-modal-cancel">
				<?php esc_html_e( 'Close', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button aie-customize-snippet">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Customize', 'wp-aie' ); ?>
			</button>
			<button type="button" class="button button-primary aie-use-snippet">
				<span class="dashicons dashicons-yes"></span>
				<?php esc_html_e( 'Use As Is', 'wp-aie' ); ?>
			</button>
		</div>
	</div>
</div>