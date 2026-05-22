<?php
/**
 * Media Sync Completion Section
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Completion Message -->
<div class="rsl-ie-card rsl-ie-completion-section" id="rsl-ie-sync-completion" style="display: none;">
	<div class="rsl-ie-card-header">
		<h2>
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Synchronization Complete', 'import-export-by-rockstarlab' ); ?>
		</h2>
	</div>

	<div class="rsl-ie-card-body">
		<div class="notice notice-success inline">
			<p id="rsl-ie-completion-message">
				<?php esc_html_e( 'Media synchronization has been completed successfully!', 'import-export-by-rockstarlab' ); ?>
			</p>
		</div>

		<div class="rsl-ie-completion-stats">
			<!-- Stats will be populated by JS -->
		</div>

		<div class="rsl-ie-actions">
			<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button button-primary">
				<span class="dashicons dashicons-admin-media"></span>
				<?php esc_html_e( 'View Media Library', 'import-export-by-rockstarlab' ); ?>
			</a>
			<button type="button" id="rsl-ie-sync-another-btn" class="button">
				<span class="dashicons dashicons-image-rotate"></span>
				<?php esc_html_e( 'Sync Another Folder', 'import-export-by-rockstarlab' ); ?>
			</button>
		</div>
	</div>
</div>
