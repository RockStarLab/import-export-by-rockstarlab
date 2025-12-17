<?php
/**
 * Post Mapping Modal Template
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="aie-mapping-modal" class="aie-modal aie-mapping-modal" style="display: none;">
	<div class="aie-modal-content aie-modal-large">
		<div class="aie-modal-header">
			<h2><?php esc_html_e( 'Map Posts for Sync', 'wp-advanced-import-export' ); ?></h2>
			<button type="button" class="aie-modal-close">&times;</button>
		</div>
		<div class="aie-modal-body">
			<div class="aie-mapping-info">
				<p><?php esc_html_e( 'Select which posts to update on the remote site, or create new ones:', 'wp-advanced-import-export' ); ?></p>
				<div class="aie-mapping-actions">
					<button type="button" id="aie-auto-match-btn" class="button">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'Auto-match by Title', 'wp-advanced-import-export' ); ?>
					</button>
				</div>
			</div>

			<div id="aie-mapping-loading" class="aie-loading-state" style="display: none;">
				<div class="aie-spinner"></div>
				<p><?php esc_html_e( 'Loading posts from remote site...', 'wp-advanced-import-export' ); ?></p>
			</div>

			<div id="aie-mapping-table-container" style="display: none;">
				<table class="aie-mapping-table wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="aie-local-post"><?php esc_html_e( 'Local Post', 'wp-advanced-import-export' ); ?></th>
							<th class="aie-sync-arrow"></th>
							<th class="aie-remote-post"><?php esc_html_e( 'Remote Site Action', 'wp-advanced-import-export' ); ?></th>
						</tr>
					</thead>
					<tbody id="aie-mapping-tbody">
						<!-- Populated dynamically -->
					</tbody>
				</table>
			</div>

			<div class="aie-mapping-footer">
				<button type="button" id="aie-mapping-cancel-btn" class="button">
					<?php esc_html_e( 'Cancel', 'wp-advanced-import-export' ); ?>
				</button>
				<button type="button" id="aie-mapping-confirm-btn" class="button button-primary" disabled>
					<span id="aie-mapping-btn-text"><?php esc_html_e( 'Confirm & Sync', 'wp-advanced-import-export' ); ?></span>
				</button>
			</div>
		</div>
	</div>
</div>
