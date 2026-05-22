<?php
/**
 * Post Mapping Modal Template
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="rsl-ie-mapping-modal" class="rsl-ie-modal rsl-ie-mapping-modal" style="display: none;">
	<div class="rsl-ie-modal-content rsl-ie-modal-large">
		<div class="rsl-ie-modal-header">
			<h2><?php esc_html_e( 'Map Posts for Sync', 'import-export-by-rockstarlab' ); ?></h2>
			<button type="button" class="rsl-ie-modal-close">&times;</button>
		</div>
		<div class="rsl-ie-modal-body">
			<div class="rsl-ie-mapping-info">
				<p><?php esc_html_e( 'Select which posts to update on the remote site, or create new ones:', 'import-export-by-rockstarlab' ); ?></p>
				<div class="rsl-ie-mapping-actions">
					<button type="button" id="rsl-ie-auto-match-btn" class="button">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'Auto-match by Title', 'import-export-by-rockstarlab' ); ?>
					</button>
				</div>
			</div>

			<div id="rsl-ie-mapping-loading" class="rsl-ie-loading-state" style="display: none;">
				<div class="rsl-ie-spinner"></div>
				<p><?php esc_html_e( 'Loading posts from remote site...', 'import-export-by-rockstarlab' ); ?></p>
			</div>

			<div id="rsl-ie-mapping-table-container" style="display: none;">
				<table class="rsl-ie-mapping-table wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="rsl-ie-local-post"><?php esc_html_e( 'Local Post', 'import-export-by-rockstarlab' ); ?></th>
							<th class="rsl-ie-sync-arrow"></th>
							<th class="rsl-ie-remote-post"><?php esc_html_e( 'Remote Site Action', 'import-export-by-rockstarlab' ); ?></th>
						</tr>
					</thead>
					<tbody id="rsl-ie-mapping-tbody">
						<!-- Populated dynamically -->
					</tbody>
				</table>
			</div>

			<div class="rsl-ie-mapping-footer">
				<button type="button" id="rsl-ie-mapping-cancel-btn" class="button">
					<?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?>
				</button>
				<button type="button" id="rsl-ie-mapping-confirm-btn" class="button button-primary" disabled>
					<span id="rsl-ie-mapping-btn-text"><?php esc_html_e( 'Confirm & Sync', 'import-export-by-rockstarlab' ); ?></span>
				</button>
			</div>
		</div>
	</div>
</div>
