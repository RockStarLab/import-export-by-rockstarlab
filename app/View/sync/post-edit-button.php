<?php
/**
 * Sync Button for Post Edit Screen (Classic Editor)
 *
 * @var WP_Post $post Current post object
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="misc-pub-section aie-sync-section">
	<span class="dashicons dashicons-update" style="color: #2271b1;"></span>
	<strong><?php esc_html_e( 'Content Sync', 'wp-advanced-import-export' ); ?></strong>
	<div style="margin-top: 8px;">
		<button type="button" id="aie-sync-content-btn" class="button button-secondary" style="width: 100%;">
			<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
			<?php esc_html_e( 'Sync This Post', 'wp-advanced-import-export' ); ?>
		</button>
	</div>
</div>
