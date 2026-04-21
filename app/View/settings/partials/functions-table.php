<?php
/**
 * Functions Table with Pagination
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Functions Table -->
<div class="aie-functions-table-container">
	<table class="wp-list-table widefat fixed striped aie-functions-table">
		<thead>
			<tr>
				<th class="column-name"><?php esc_html_e( 'Name', 'import-export-by-rockstarlab' ); ?></th>
				<th class="column-description"><?php esc_html_e( 'Description', 'import-export-by-rockstarlab' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
			</tr>
		</thead>
		<tbody id="aie-functions-tbody">
			<tr class="aie-loading-row">
				<td colspan="3" style="text-align:center;">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading functions...', 'import-export-by-rockstarlab' ); ?>
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
			<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
		</button>
		<span class="aie-page-info">
			<?php esc_html_e( 'Page', 'import-export-by-rockstarlab' ); ?> <span class="aie-current-page">1</span> / <span class="aie-total-pages">1</span>
		</span>
		<button type="button" class="button aie-next-page" disabled>
			<?php esc_html_e( 'Next', 'import-export-by-rockstarlab' ); ?>
			<span class="dashicons dashicons-arrow-right-alt2"></span>
		</button>
	</div>
</div>
