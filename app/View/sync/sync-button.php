<?php
/**
 * Sync Content Button for Post List Screen
 *
 * This button is rendered on the post list page (edit.php)
 */

defined( 'ABSPATH' ) || exit;
?>

<button type="button" id="aie-sync-content-btn" class="button action" style="margin-left: 5px;">
	<?php esc_html_e( 'Sync Content', 'amplified-import-export' ); ?>
</button>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Move sync button after the Filter button
	var $syncBtn = $('#aie-sync-content-btn');
	var $filterBtn = $('#post-query-submit');
	
	if ($syncBtn.length && $filterBtn.length) {
		$syncBtn.insertAfter($filterBtn);
	}
});
</script>
