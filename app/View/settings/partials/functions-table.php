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
