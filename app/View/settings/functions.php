<?php
/**
 * Functions Settings Page
 *
 * @package WP_AIE\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="wp-aie-functions" class="wrap">
	<?php
		require __DIR__ . '/partials/functions-header.php';
		require __DIR__ . '/partials/functions-table.php';
	?>
</div>

<?php
	require __DIR__ . '/partials/function-editor-modal.php';
	require __DIR__ . '/partials/snippets-library-modal.php';
	require __DIR__ . '/partials/snippet-preview-modal.php';
?>