<?php
/**
 * Triggers page (Jobs Log → Triggers).
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_list   = $data['rsl_ie_triggers'];
$rsl_ie_srcs   = $data['rsl_ie_sources'];
$rsl_ie_tlog   = $data['rsl_ie_trigger_log'];
$rsl_ie_ptypes = $data['rsl_ie_post_types'];
$rsl_ie_uid    = get_current_user_id();
$rsl_ie_notice = get_transient( 'rsl_ie_trigger_notice_' . $rsl_ie_uid );
$rsl_ie_token  = get_transient( 'rsl_ie_trigger_token_' . $rsl_ie_uid );
delete_transient( 'rsl_ie_trigger_notice_' . $rsl_ie_uid );
delete_transient( 'rsl_ie_trigger_token_' . $rsl_ie_uid );
$rsl_ie_events = [
	'webhook'           => __( 'Webhook URL is called', 'import-export-by-rockstarlab' ),
	'post_published'    => __( 'A post is published', 'import-export-by-rockstarlab' ),
	'woo_order_created' => __( 'A WooCommerce order is created', 'import-export-by-rockstarlab' ),
	'file_changed'      => __( 'The import file at its URL changes', 'import-export-by-rockstarlab' ),
];
$rsl_ie_reasons = [
	'webhook' => __( 'Webhook', 'import-export-by-rockstarlab' ),
	'event'   => __( 'Event', 'import-export-by-rockstarlab' ),
	'file'    => __( 'File changed', 'import-export-by-rockstarlab' ),
	'manual'  => __( 'Run now', 'import-export-by-rockstarlab' ),
];
$rsl_ie_has_url = (bool) array_filter( $rsl_ie_srcs, static fn( $s ) => $s['from_url'] );
?>
<div id="rsl-ie-triggers" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Jobs Log', 'import-export-by-rockstarlab' ); ?></h1>
	<?php
	$rsl_ie_active_tab = 'triggers';
	require RSL_IE_PATH . 'app/View/settings/partials/jobs-tabs.php';
	?>

	<?php if ( is_array( $rsl_ie_notice ) && '' !== $rsl_ie_notice[1] ) : ?>
		<div class="notice notice-<?php echo esc_attr( $rsl_ie_notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $rsl_ie_notice[1] ); ?></p></div>
	<?php endif; ?>
	<?php if ( is_array( $rsl_ie_token ) && ! empty( $rsl_ie_token['token'] ) ) : ?>
		<div class="notice notice-info rsl-ie-trigger-token">
			<p><strong><?php esc_html_e( 'Webhook token', 'import-export-by-rockstarlab' ); ?></strong> — <?php esc_html_e( 'copy it now; it is not shown again. Send it in the X-RSL-Token header, never in the URL.', 'import-export-by-rockstarlab' ); ?></p>
			<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $rsl_ie_token['token'] ); ?>" onfocus="this.select()" aria-label="<?php esc_attr_e( 'Webhook token', 'import-export-by-rockstarlab' ); ?>"></p>
			<p><?php esc_html_e( 'Example:', 'import-export-by-rockstarlab' ); ?></p>
			<pre class="rsl-ie-trigger-curl"><code><?php echo esc_html( "curl -X POST \\\n  -H \"X-RSL-Token: " . $rsl_ie_token['token'] . "\" \\\n  " . $rsl_ie_token['url'] ); ?></code></pre>
		</div>
	<?php endif; ?>

	<p class="description rsl-ie-notifications-intro"><?php esc_html_e( 'Run a saved import or export again when something happens: another system calls a URL, a post is published, an order comes in, or the file the import downloads has changed. The job runs exactly like the original, with the same file source, mapping and options.', 'import-export-by-rockstarlab' ); ?></p>

	<h2><?php esc_html_e( 'Triggers', 'import-export-by-rockstarlab' ); ?></h2>
	<?php if ( ! $rsl_ie_list ) : ?>
		<p class="description"><?php esc_html_e( 'No triggers yet. Create one below.', 'import-export-by-rockstarlab' ); ?></p>
	<?php else : ?>
		<table class="widefat striped rsl-ie-trigger-table">
			<thead><tr>
				<th><?php esc_html_e( 'Name', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Starts when', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Runs job', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Status', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Last run', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rsl_ie_list as $rsl_ie_t ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $rsl_ie_t['name'] ); ?></strong></td>
					<td>
						<?php echo esc_html( $rsl_ie_events[ $rsl_ie_t['event'] ] ?? $rsl_ie_t['event'] ); ?>
						<?php if ( 'post_published' === $rsl_ie_t['event'] ) : ?>
							<br><span class="description"><?php echo esc_html( implode( ', ', $rsl_ie_t['post_types'] ) ); ?></span>
						<?php endif; ?>
						<?php if ( in_array( $rsl_ie_t['event'], [ 'post_published', 'woo_order_created' ], true ) && $rsl_ie_t['delay'] ) : ?>
							<br><span class="description">
							<?php
							/* translators: %d: seconds. */
							echo esc_html( sprintf( __( 'waits %d s to group events', 'import-export-by-rockstarlab' ), $rsl_ie_t['delay'] ) );
							?>
							</span>
						<?php endif; ?>
						<?php if ( 'webhook' === $rsl_ie_t['event'] ) : ?>
							<br><code class="rsl-ie-trigger-url"><?php echo esc_html( $rsl_ie_t['webhook_url'] ); ?></code>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $rsl_ie_t['source'] ); ?></td>
					<td>
						<span class="rsl-ie-trigger-status rsl-ie-trigger-status--<?php echo esc_attr( $rsl_ie_t['status'] ); ?>"><?php echo esc_html( 'active' === $rsl_ie_t['status'] ? __( 'Active', 'import-export-by-rockstarlab' ) : __( 'Paused', 'import-export-by-rockstarlab' ) ); ?></span>
						<?php if ( $rsl_ie_t['pending'] ) : ?>
							<br><span class="description"><?php esc_html_e( 'Run queued', 'import-export-by-rockstarlab' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $rsl_ie_t['last_run_at'] ) : ?>
							<?php echo esc_html( get_date_from_gmt( $rsl_ie_t['last_run_at'], 'Y-m-d H:i' ) ); ?>
							<?php if ( $rsl_ie_t['last_job_id'] ) : ?>
								<br><span class="description">
								<?php
								/* translators: 1: job ID, 2: number of runs. */
								echo esc_html( sprintf( __( 'job #%1$d · %2$d runs', 'import-export-by-rockstarlab' ), $rsl_ie_t['last_job_id'], $rsl_ie_t['runs'] ) );
								?>
								</span>
							<?php endif; ?>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'Never', 'import-export-by-rockstarlab' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="rsl-ie-trigger-actions">
						<?php
						$rsl_ie_buttons = [
							'run'                                              => __( 'Run Now', 'import-export-by-rockstarlab' ),
							( 'active' === $rsl_ie_t['status'] ? 'pause' : 'resume' ) => 'active' === $rsl_ie_t['status'] ? __( 'Pause', 'import-export-by-rockstarlab' ) : __( 'Resume', 'import-export-by-rockstarlab' ),
						];
						if ( 'webhook' === $rsl_ie_t['event'] ) {
							$rsl_ie_buttons['rotate'] = __( 'New Token', 'import-export-by-rockstarlab' );
						}
						$rsl_ie_buttons['delete'] = __( 'Delete', 'import-export-by-rockstarlab' );
						foreach ( $rsl_ie_buttons as $rsl_ie_do => $rsl_ie_label ) :
							?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="rsl_ie_trigger_action">
								<input type="hidden" name="trigger_id" value="<?php echo esc_attr( $rsl_ie_t['trigger_id'] ); ?>">
								<input type="hidden" name="do" value="<?php echo esc_attr( $rsl_ie_do ); ?>">
								<?php wp_nonce_field( 'rsl_ie_trigger_action' ); ?>
								<button type="submit" class="button button-small<?php echo 'delete' === $rsl_ie_do ? ' button-link-delete' : ''; ?>"><?php echo esc_html( $rsl_ie_label ); ?></button>
							</form>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<h2><?php esc_html_e( 'New trigger', 'import-export-by-rockstarlab' ); ?></h2>
	<?php if ( ! $rsl_ie_srcs ) : ?>
		<p class="description"><?php esc_html_e( 'Run an import or export once first. A trigger repeats a finished job.', 'import-export-by-rockstarlab' ); ?></p>
	<?php else : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rsl-ie-trigger-form">
		<input type="hidden" name="action" value="rsl_ie_create_trigger">
		<?php wp_nonce_field( 'rsl_ie_create_trigger' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rsl-ie-trigger-source"><?php esc_html_e( 'Job to run', 'import-export-by-rockstarlab' ); ?></label></th>
				<td>
					<select id="rsl-ie-trigger-source" name="source_job_id">
						<?php foreach ( $rsl_ie_srcs as $rsl_ie_s ) : ?>
							<option value="<?php echo esc_attr( $rsl_ie_s['job_id'] ); ?>" data-from-url="<?php echo $rsl_ie_s['from_url'] ? '1' : '0'; ?>"><?php echo esc_html( $rsl_ie_s['label'] . ( $rsl_ie_s['from_url'] ? ' (URL)' : '' ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'A finished import or export. Each run is a new job with the same settings.', 'import-export-by-rockstarlab' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Starts when', 'import-export-by-rockstarlab' ); ?></th>
				<td>
					<fieldset class="rsl-ie-trigger-events">
						<?php foreach ( $rsl_ie_events as $rsl_ie_key => $rsl_ie_label ) : ?>
							<?php
							$rsl_ie_off = ( 'woo_order_created' === $rsl_ie_key && ! $data['rsl_ie_woo'] ) || ( 'file_changed' === $rsl_ie_key && ! $rsl_ie_has_url );
							?>
							<label><input type="radio" name="event" value="<?php echo esc_attr( $rsl_ie_key ); ?>" <?php checked( 'webhook', $rsl_ie_key ); ?> <?php disabled( $rsl_ie_off ); ?>> <?php echo esc_html( $rsl_ie_label ); ?>
							<?php if ( 'woo_order_created' === $rsl_ie_key && ! $data['rsl_ie_woo'] ) : ?>
								<span class="description"><?php esc_html_e( '(WooCommerce is not active)', 'import-export-by-rockstarlab' ); ?></span>
							<?php elseif ( 'file_changed' === $rsl_ie_key && ! $rsl_ie_has_url ) : ?>
								<span class="description"><?php esc_html_e( '(needs an import from a URL)', 'import-export-by-rockstarlab' ); ?></span>
							<?php endif; ?>
							</label><br>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Webhook: for Zapier, Make, n8n, a CRM or a script. The token is shown once after you create the trigger. File changes are checked every hour.', 'import-export-by-rockstarlab' ); ?></p>
				</td>
			</tr>
			<tr class="rsl-ie-trigger-post-types">
				<th scope="row"><?php esc_html_e( 'Post types', 'import-export-by-rockstarlab' ); ?></th>
				<td>
					<fieldset class="rsl-ie-check-row">
						<?php foreach ( $rsl_ie_ptypes as $rsl_ie_pt ) : ?>
							<label class="rsl-ie-inline-check"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $rsl_ie_pt->name ); ?>" <?php checked( 'post', $rsl_ie_pt->name ); ?>> <?php echo esc_html( $rsl_ie_pt->labels->singular_name ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Only for "A post is published". Posts created by the plugin\'s own imports do not start a trigger.', 'import-export-by-rockstarlab' ); ?></p>
				</td>
			</tr>
			<tr class="rsl-ie-trigger-delay">
				<th scope="row"><label for="rsl-ie-trigger-delay"><?php esc_html_e( 'Wait', 'import-export-by-rockstarlab' ); ?></label></th>
				<td>
					<input type="number" id="rsl-ie-trigger-delay" name="delay" min="0" max="3600" value="60" class="small-text"> <?php esc_html_e( 'seconds', 'import-export-by-rockstarlab' ); ?>
					<p class="description"><?php esc_html_e( 'For posts and orders: events within this time start a single run.', 'import-export-by-rockstarlab' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rsl-ie-trigger-name"><?php esc_html_e( 'Name', 'import-export-by-rockstarlab' ); ?></label></th>
				<td><input type="text" id="rsl-ie-trigger-name" name="name" class="regular-text" maxlength="100" placeholder="<?php esc_attr_e( 'Optional — defaults to the job name', 'import-export-by-rockstarlab' ); ?>"></td>
			</tr>
		</table>
		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Create Trigger', 'import-export-by-rockstarlab' ); ?></button></p>
	</form>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Recent trigger runs', 'import-export-by-rockstarlab' ); ?></h2>
	<?php if ( ! $rsl_ie_tlog ) : ?>
		<p class="description"><?php esc_html_e( 'No runs yet.', 'import-export-by-rockstarlab' ); ?></p>
	<?php else : ?>
		<table class="widefat striped rsl-ie-notify-log">
			<thead><tr>
				<th><?php esc_html_e( 'Time', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Trigger', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Started by', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Result', 'import-export-by-rockstarlab' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rsl_ie_tlog as $rsl_ie_e ) : ?>
				<tr>
					<td><?php echo esc_html( get_date_from_gmt( $rsl_ie_e['time'], 'Y-m-d H:i:s' ) ); ?></td>
					<td><?php echo esc_html( $rsl_ie_e['name'] ); ?></td>
					<td><?php echo esc_html( $rsl_ie_reasons[ $rsl_ie_e['reason'] ] ?? $rsl_ie_e['reason'] ); ?></td>
					<td>
						<?php if ( $rsl_ie_e['ok'] ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-jobs-log' ) ); ?>">
							<?php
							/* translators: %d: job ID. */
							echo esc_html( sprintf( __( 'Started job #%d', 'import-export-by-rockstarlab' ), $rsl_ie_e['job_id'] ) );
							?>
							</a>
						<?php else : ?>
							<span class="rsl-ie-trigger-skip"><?php echo esc_html( str_replace( [ 'rsl_ie_trigger_', 'rsl_ie_', '_' ], [ '', '', ' ' ], $rsl_ie_e['detail'] ) ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
<?php
wp_print_inline_script_tag(
	<<<'RSLJS'
( function () {
	var form = document.querySelector( '.rsl-ie-trigger-form' );
	if ( ! form ) { return; }
	function sync() {
		var ev = ( form.querySelector( 'input[name="event"]:checked' ) || {} ).value;
		form.querySelector( '.rsl-ie-trigger-post-types' ).style.display = 'post_published' === ev ? '' : 'none';
		form.querySelector( '.rsl-ie-trigger-delay' ).style.display = ( 'post_published' === ev || 'woo_order_created' === ev ) ? '' : 'none';
		Array.prototype.forEach.call( form.querySelectorAll( '#rsl-ie-trigger-source option' ), function ( o ) {
			o.disabled = 'file_changed' === ev && '1' !== o.getAttribute( 'data-from-url' );
		} );
		var sel = form.querySelector( '#rsl-ie-trigger-source' );
		if ( sel.options[ sel.selectedIndex ] && sel.options[ sel.selectedIndex ].disabled ) {
			var first = form.querySelector( '#rsl-ie-trigger-source option:not([disabled])' );
			if ( first ) { sel.value = first.value; }
		}
	}
	form.addEventListener( 'change', sync );
	sync();
}() );
RSLJS
);

