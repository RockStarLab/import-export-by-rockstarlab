<?php
/**
 * Recommendation to index an existing media library.
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_notice_action_url = admin_url( 'admin-post.php?action=rsl_ie_media_hash_notice' );
$rsl_ie_snooze_url        = wp_nonce_url( add_query_arg( 'mode', 'snooze', $rsl_ie_notice_action_url ), 'rsl_ie_media_hash_notice' );
$rsl_ie_forever_url       = wp_nonce_url( add_query_arg( 'mode', 'forever', $rsl_ie_notice_action_url ), 'rsl_ie_media_hash_notice' );
?>
<div class="notice notice-info rsl-ie-media-hash-notice">
	<p>
		<strong><?php esc_html_e( 'Your Media Library hash index is incomplete.', 'import-export-by-rockstarlab' ); ?></strong>
		<?php esc_html_e( 'Create hashes for existing media files so Import, Media Sync, and Content Sync can detect identical files reliably.', 'import-export-by-rockstarlab' ); ?>
	</p>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-tools#rsl-ie-media-hash-tool' ) ); ?>"><?php esc_html_e( 'Build Media Hash Index', 'import-export-by-rockstarlab' ); ?></a>
		<a class="button button-secondary" href="<?php echo esc_url( $rsl_ie_snooze_url ); ?>"><?php esc_html_e( 'Remind me in 7 days', 'import-export-by-rockstarlab' ); ?></a>
		<a class="button-link" style="margin-left: 8px;" href="<?php echo esc_url( $rsl_ie_forever_url ); ?>"><?php esc_html_e( 'Do not show again', 'import-export-by-rockstarlab' ); ?></a>
	</p>
</div>
