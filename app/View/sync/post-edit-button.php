<?php
/**
 * Sync Button for Post Edit Screen (Classic Editor)
 *
 * @var WP_Post $post Current post object
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="misc-pub-section rsl-ie-sync-section">
	<strong><?php esc_html_e( 'Content Sync', 'import-export-by-rockstarlab' ); ?></strong>
	<div>
		<button type="button" id="rsl-ie-sync-content-btn" class="button button-secondary" style="width: 100%;">
			<?php esc_html_e( 'Sync This Post', 'import-export-by-rockstarlab' ); ?>
		</button>
	</div>
</div>
