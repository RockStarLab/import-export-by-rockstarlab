<?php
/**
 * Media Sync Completion Section
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Completion Message -->
<div class="aie-card aie-completion-section" id="aie-sync-completion" style="display: none;">
	<div class="aie-card-header">
		<h2>
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Synchronization Complete', 'amplified-import-export' ); ?>
		</h2>
	</div>

	<div class="aie-card-body">
		<div class="notice notice-success inline">
			<p id="aie-completion-message">
				<?php esc_html_e( 'Media synchronization has been completed successfully!', 'amplified-import-export' ); ?>
			</p>
		</div>

		<div class="aie-completion-stats">
			<!-- Stats will be populated by JS -->
		</div>

		<div class="aie-actions">
			<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-primary">
				<span class="dashicons dashicons-admin-media"></span>
				<?php esc_html_e( 'View Media Library', 'amplified-import-export' ); ?>
			</a>
			<button type="button" id="aie-sync-another-btn" class="button">
				<span class="dashicons dashicons-image-rotate"></span>
				<?php esc_html_e( 'Sync Another Folder', 'amplified-import-export' ); ?>
			</button>
		</div>
	</div>
</div>
