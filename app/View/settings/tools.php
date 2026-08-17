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

	<h2 class="nav-tab-wrapper">
		<a href="#rsl-ie-media-hash-panel" class="nav-tab nav-tab-active" data-rsl-ie-tools-tab="media-hash"><?php esc_html_e( 'Media Hash Index', 'import-export-by-rockstarlab' ); ?></a>
		<a href="#rsl-ie-debug-panel" class="nav-tab" data-rsl-ie-tools-tab="debug"><?php esc_html_e( 'Debug', 'import-export-by-rockstarlab' ); ?></a>
	</h2>

	<div id="rsl-ie-media-hash-panel" class="rsl-ie-tools-panel" data-rsl-ie-tools-panel="media-hash">
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

	<div id="rsl-ie-debug-panel" class="rsl-ie-tools-panel" data-rsl-ie-tools-panel="debug" hidden>
		<div id="rsl-ie-debug-tool" class="card" style="max-width: 980px;">
			<h2><?php esc_html_e( 'Debug Site Info', 'import-export-by-rockstarlab' ); ?></h2>
			<p>
				<?php esc_html_e( 'Generate a safe support snapshot with WordPress, plugin, theme, server, database, upload, cron, and filesystem details. Sensitive values such as API keys and passwords are not included.', 'import-export-by-rockstarlab' ); ?>
			</p>

			<p>
				<button type="button" class="button button-primary" id="rsl-ie-load-debug-info">
					<?php esc_html_e( 'Load site info', 'import-export-by-rockstarlab' ); ?>
				</button>
				<span class="spinner" id="rsl-ie-debug-spinner"></span>
				<button type="button" class="button button-secondary" id="rsl-ie-copy-debug-info" style="display:none;">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copy site info', 'import-export-by-rockstarlab' ); ?>
				</button>
			</p>

			<textarea id="rsl-ie-debug-site-info" class="large-text code" rows="24" readonly hidden></textarea>
			<div id="rsl-ie-debug-result" class="notice inline" hidden><p></p></div>
		</div>
	</div>
</div>
