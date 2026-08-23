<?php
/**
 * Sync Content Button for Post List Screen
 *
 * This button is rendered on the post list page (edit.php)
 */

defined( 'ABSPATH' ) || exit;

global $typenow;

$rsl_ie_post_type   = ! empty( $typenow ) ? sanitize_key( $typenow ) : 'post';
$rsl_ie_location_id = 'post_type:' . $rsl_ie_post_type;
$rsl_ie_show_export = \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_export_enabled( $rsl_ie_location_id );
$rsl_ie_show_sync   = \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_sync_enabled( $rsl_ie_location_id );
?>

<?php if ( $rsl_ie_show_export ) : ?>
	<button type="button" id="rsl-ie-export-selected-btn" class="button action" style="margin-left: 5px;" disabled aria-disabled="true" data-post-type="<?php echo esc_attr( $rsl_ie_post_type ); ?>">
		<?php esc_html_e( 'Export', 'import-export-by-rockstarlab' ); ?>
	</button>
<?php endif; ?>

<?php if ( $rsl_ie_show_sync ) : ?>
	<button type="button" id="rsl-ie-sync-content-btn" class="button action" style="margin-left: 5px;">
		<?php esc_html_e( 'Sync Content', 'import-export-by-rockstarlab' ); ?>
	</button>
<?php endif; ?>
