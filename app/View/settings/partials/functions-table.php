<?php
/**
 * Functions Table with Pagination
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Functions Table -->
<div class="aie-functions-table-container">
	<table class="wp-list-table widefat fixed striped aie-functions-table">
		<thead>
			<tr>
				<th class="column-name"><?php esc_html_e( 'Name', 'amplified-import-export' ); ?></th>
				<th class="column-description"><?php esc_html_e( 'Description', 'amplified-import-export' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'amplified-import-export' ); ?></th>
			</tr>
		</thead>
		<tbody id="aie-functions-tbody">
			<tr class="aie-loading-row">
				<td colspan="3" style="text-align:center;">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading functions...', 'amplified-import-export' ); ?>
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
			<?php esc_html_e( 'Previous', 'amplified-import-export' ); ?>
		</button>
		<span class="aie-page-info">
			<?php esc_html_e( 'Page', 'amplified-import-export' ); ?> <span class="aie-current-page">1</span> / <span class="aie-total-pages">1</span>
		</span>
		<button type="button" class="button aie-next-page" disabled>
			<?php esc_html_e( 'Next', 'amplified-import-export' ); ?>
			<span class="dashicons dashicons-arrow-right-alt2"></span>
		</button>
	</div>
</div>
