<?php
/**
 * Snippet Preview Modal
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Snippet Preview Modal -->
<div id="rsl-ie-snippet-preview-modal" class="rsl-ie-modal" style="display:none;">
	<div class="rsl-ie-modal-backdrop"></div>
	<div class="rsl-ie-modal-content">
		<div class="rsl-ie-modal-header">
			<h2 class="rsl-ie-modal-title rsl-ie-snippet-title"></h2>
			<button type="button" class="rsl-ie-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>

		<div class="rsl-ie-modal-body">
			<p class="rsl-ie-snippet-description"></p>

			<div class="rsl-ie-snippet-details">
				<div class="rsl-ie-snippet-detail">
					<strong><?php esc_html_e( 'Category:', 'import-export-by-rockstarlab' ); ?></strong>
					<span class="rsl-ie-snippet-category"></span>
				</div>
				<div class="rsl-ie-snippet-detail">
					<strong><?php esc_html_e( 'Tags:', 'import-export-by-rockstarlab' ); ?></strong>
					<span class="rsl-ie-snippet-tags"></span>
				</div>
			</div>

			<h3><?php esc_html_e( 'Code:', 'import-export-by-rockstarlab' ); ?></h3>
			<pre><code class="rsl-ie-snippet-code"></code></pre>

			<div class="rsl-ie-snippet-example">
				<h3><?php esc_html_e( 'Example:', 'import-export-by-rockstarlab' ); ?></h3>
				<div class="rsl-ie-example-io">
					<div class="rsl-ie-example-input">
						<strong><?php esc_html_e( 'Input:', 'import-export-by-rockstarlab' ); ?></strong>
						<code class="rsl-ie-example-input-value"></code>
					</div>
					<span class="dashicons dashicons-arrow-right-alt"></span>
					<div class="rsl-ie-example-output">
						<strong><?php esc_html_e( 'Output:', 'import-export-by-rockstarlab' ); ?></strong>
						<code class="rsl-ie-example-output-value"></code>
					</div>
				</div>
			</div>
		</div>

		<div class="rsl-ie-modal-footer">
			<button type="button" class="button button-secondary rsl-ie-modal-cancel">
				<?php esc_html_e( 'Close', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary rsl-ie-customize-snippet">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Customize', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
