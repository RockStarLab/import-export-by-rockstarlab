<?php
/**
 * Plugin maintenance tools.
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;
?>

<div id="rsl-ie-tools" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Tools', 'import-export-by-rockstarlab' ); ?></h1>

	<div id="rsl-ie-media-hash-tool" class="card" style="max-width: 820px;">
		<h2><?php esc_html_e( 'Media Hash Index', 'import-export-by-rockstarlab' ); ?></h2>
		<p>
			<?php esc_html_e( 'Build an MD5 hash index for every file in the WordPress Media Library. Import, Media Sync, and Content Sync use this index to identify identical files even when their names or URLs differ.', 'import-export-by-rockstarlab' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'The scan reads files in small batches and does not modify the files themselves. Existing hashes are refreshed.', 'import-export-by-rockstarlab' ); ?>
		</p>

		<table class="widefat striped" style="margin: 20px 0;">
			<tbody>
				<tr><th><?php esc_html_e( 'Media files', 'import-export-by-rockstarlab' ); ?></th><td id="rsl-ie-hash-total">—</td></tr>
				<tr><th><?php esc_html_e( 'Indexed', 'import-export-by-rockstarlab' ); ?></th><td id="rsl-ie-hash-indexed">—</td></tr>
				<tr><th><?php esc_html_e( 'Without a hash', 'import-export-by-rockstarlab' ); ?></th><td id="rsl-ie-hash-unindexed">—</td></tr>
			</tbody>
		</table>

		<div id="rsl-ie-hash-progress-wrap" hidden>
			<progress id="rsl-ie-hash-progress" max="100" value="0" style="width: 100%;"></progress>
			<p id="rsl-ie-hash-progress-text" aria-live="polite"></p>
		</div>

		<p>
			<button type="button" class="button button-primary" id="rsl-ie-start-hash-index">
				<?php esc_html_e( 'Start scan', 'import-export-by-rockstarlab' ); ?>
			</button>
			<span class="spinner" id="rsl-ie-hash-spinner"></span>
		</p>
		<div id="rsl-ie-hash-result" class="notice inline" hidden><p></p></div>
	</div>
</div>
