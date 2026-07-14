<?php
/**
 * Sync Content Button for Post List Screen
 *
 * This button is rendered on the post list page (edit.php)
 */

defined( 'ABSPATH' ) || exit;
?>

<button type="button" id="rsl-ie-sync-content-btn" class="button action" style="margin-left: 5px;">
	<?php esc_html_e( 'Sync Content', 'import-export-by-rockstarlab' ); ?>
</button>
