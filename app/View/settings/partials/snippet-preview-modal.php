<?php
/**
 * Snippet Preview Modal
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

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
					<strong><?php esc_html_e( 'Category:', 'import-export-by-rockstarlab' ); ?></strong>
					<span class="aie-snippet-category"></span>
				</div>
				<div class="aie-snippet-detail">
					<strong><?php esc_html_e( 'Tags:', 'import-export-by-rockstarlab' ); ?></strong>
					<span class="aie-snippet-tags"></span>
				</div>
			</div>

			<h3><?php esc_html_e( 'Code:', 'import-export-by-rockstarlab' ); ?></h3>
			<pre><code class="aie-snippet-code"></code></pre>

			<div class="aie-snippet-example">
				<h3><?php esc_html_e( 'Example:', 'import-export-by-rockstarlab' ); ?></h3>
				<div class="aie-example-io">
					<div class="aie-example-input">
						<strong><?php esc_html_e( 'Input:', 'import-export-by-rockstarlab' ); ?></strong>
						<code class="aie-example-input-value"></code>
					</div>
					<span class="dashicons dashicons-arrow-right-alt"></span>
					<div class="aie-example-output">
						<strong><?php esc_html_e( 'Output:', 'import-export-by-rockstarlab' ); ?></strong>
						<code class="aie-example-output-value"></code>
					</div>
				</div>
			</div>
		</div>

		<div class="aie-modal-footer">
			<button type="button" class="button button-secondary aie-modal-cancel">
				<?php esc_html_e( 'Close', 'import-export-by-rockstarlab' ); ?>
			</button>
			<button type="button" class="button button-primary aie-customize-snippet">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Customize', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
