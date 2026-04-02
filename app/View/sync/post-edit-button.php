<?php
/**
 * Sync Button for Post Edit Screen (Classic Editor)
 *
 * @var WP_Post $post Current post object
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="misc-pub-section aie-sync-section">
	<strong><?php esc_html_e( 'Content Sync', 'amplified-import-export' ); ?></strong>
	<div>
		<button type="button" id="aie-sync-content-btn" class="button button-secondary" style="width: 100%;">
			<?php esc_html_e( 'Sync This Post', 'amplified-import-export' ); ?>
		</button>
	</div>
</div>
