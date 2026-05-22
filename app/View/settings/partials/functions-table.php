<?php
/**
 * Functions Table with Pagination
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Functions Table -->
<div class="rsl-ie-functions-table-container">
	<table class="wp-list-table widefat fixed striped rsl-ie-functions-table">
		<thead>
			<tr>
				<th class="column-name"><?php esc_html_e( 'Name', 'import-export-by-rockstarlab' ); ?></th>
				<th class="column-description"><?php esc_html_e( 'Description', 'import-export-by-rockstarlab' ); ?></th>
				<th class="column-actions"><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
			</tr>
		</thead>
		<tbody id="rsl-ie-functions-tbody">
			<tr class="rsl-ie-loading-row">
				<td colspan="3" style="text-align:center;">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading functions...', 'import-export-by-rockstarlab' ); ?>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<!-- Pagination -->
<div class="rsl-ie-functions-pagination">
	<div class="rsl-ie-pagination-info"></div>
	<div class="rsl-ie-pagination-controls">
		<button type="button" class="button rsl-ie-prev-page" disabled>
			<span class="dashicons dashicons-arrow-left-alt2"></span>
			<?php esc_html_e( 'Previous', 'import-export-by-rockstarlab' ); ?>
		</button>
		<span class="rsl-ie-page-info">
			<?php esc_html_e( 'Page', 'import-export-by-rockstarlab' ); ?> <span class="rsl-ie-current-page">1</span> / <span class="rsl-ie-total-pages">1</span>
		</span>
		<button type="button" class="button rsl-ie-next-page" disabled>
			<?php esc_html_e( 'Next', 'import-export-by-rockstarlab' ); ?>
			<span class="dashicons dashicons-arrow-right-alt2"></span>
		</button>
	</div>
</div>
