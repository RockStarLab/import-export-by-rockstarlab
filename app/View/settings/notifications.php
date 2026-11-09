<?php
/**
 * Notifications page (Jobs Log → Notifications).
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_n      = $data['rsl_ie_notifications'];
$rsl_ie_log    = $data['rsl_ie_notify_log'];
$rsl_ie_uid    = get_current_user_id();
$rsl_ie_notice = get_transient( 'rsl_ie_notify_notice_' . $rsl_ie_uid );
$rsl_ie_secret = get_transient( 'rsl_ie_notify_secret_' . $rsl_ie_uid );
delete_transient( 'rsl_ie_notify_notice_' . $rsl_ie_uid );
delete_transient( 'rsl_ie_notify_secret_' . $rsl_ie_uid );
$rsl_ie_types = [
	'import'    => __( 'Imports', 'import-export-by-rockstarlab' ),
	'export'    => __( 'Exports', 'import-export-by-rockstarlab' ),
	'sync'      => __( 'Content Sync', 'import-export-by-rockstarlab' ),
	'migration' => __( 'Content Migration', 'import-export-by-rockstarlab' ),
];
?>
<div id="rsl-ie-notifications" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Jobs Log', 'import-export-by-rockstarlab' ); ?></h1>
	<?php
	$rsl_ie_active_tab = 'notifications';
	require RSL_IE_PATH . 'app/View/settings/partials/jobs-tabs.php';
	?>

	<?php if ( is_array( $rsl_ie_notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $rsl_ie_notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $rsl_ie_notice[1] ); ?></p></div>
	<?php endif; ?>
	<?php if ( is_string( $rsl_ie_secret ) && '' !== $rsl_ie_secret ) : ?>
		<div class="notice notice-info rsl-ie-webhook-secret">
			<p><strong><?php esc_html_e( 'Webhook signing secret', 'import-export-by-rockstarlab' ); ?></strong> — <?php esc_html_e( 'copy it now; it is not shown again. Your endpoint checks the X-RSL-Signature header with it.', 'import-export-by-rockstarlab' ); ?></p>
			<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $rsl_ie_secret ); ?>" onfocus="this.select()"></p>
		</div>
	<?php endif; ?>

	<p class="description rsl-ie-notifications-intro"><?php esc_html_e( 'Get a message when an import or export finishes, with the number of rows done and failed and the first errors. Works for imports you start yourself, scheduled runs and jobs started by AI agents.', 'import-export-by-rockstarlab' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rsl-ie-notifications-form">
		<input type="hidden" name="action" value="rsl_ie_save_notifications">
		<?php wp_nonce_field( 'rsl_ie_save_notifications' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Notifications', 'import-export-by-rockstarlab' ); ?></th>
				<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $rsl_ie_n['enabled'] ); ?>> <?php esc_html_e( 'Send a notification when a job finishes', 'import-export-by-rockstarlab' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'When', 'import-export-by-rockstarlab' ); ?></th>
				<td>
					<fieldset>
						<label><input type="radio" name="when" value="errors" <?php checked( 'errors', $rsl_ie_n['when'] ); ?>> <?php esc_html_e( 'Only when a job failed or some rows failed', 'import-export-by-rockstarlab' ); ?></label><br>
						<label><input type="radio" name="when" value="always" <?php checked( 'always', $rsl_ie_n['when'] ); ?>> <?php esc_html_e( 'After every job', 'import-export-by-rockstarlab' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Jobs', 'import-export-by-rockstarlab' ); ?></th>
				<td>
					<fieldset class="rsl-ie-check-row">
						<?php foreach ( $rsl_ie_types as $rsl_ie_key => $rsl_ie_label ) : ?>
							<label class="rsl-ie-inline-check"><input type="checkbox" name="job_types[]" value="<?php echo esc_attr( $rsl_ie_key ); ?>" <?php checked( in_array( $rsl_ie_key, $rsl_ie_n['job_types'], true ) ); ?>> <?php echo esc_html( $rsl_ie_label ); ?></label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Email', 'import-export-by-rockstarlab' ); ?></th>
				<td>
					<div class="rsl-ie-inline-field">
						<label><input type="checkbox" name="email_enabled" value="1" <?php checked( $rsl_ie_n['email']['enabled'] ); ?>> <?php esc_html_e( 'Send an email to', 'import-export-by-rockstarlab' ); ?></label>
						<input type="text" name="email_recipients" class="regular-text" value="<?php echo esc_attr( implode( ', ', $rsl_ie_n['email']['recipients'] ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" aria-label="<?php esc_attr_e( 'Email addresses', 'import-export-by-rockstarlab' ); ?>">
					</div>
					<p class="description"><?php esc_html_e( 'Up to 10 addresses, separated by commas. Sent with the site mailer (wp_mail).', 'import-export-by-rockstarlab' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Slack', 'import-export-by-rockstarlab' ); ?></th>
				<td>
					<label><input type="checkbox" name="slack_enabled" value="1" <?php checked( $rsl_ie_n['slack']['enabled'] ); ?>> <?php esc_html_e( 'Post to a Slack channel', 'import-export-by-rockstarlab' ); ?></label>
					<input type="url" name="slack_url" class="large-text code" value="" autocomplete="off" placeholder="<?php echo esc_attr( $rsl_ie_n['slack']['url_set'] ? sprintf( /* translators: %s: masked URL. */ __( 'Saved: %s — paste a new URL to replace it', 'import-export-by-rockstarlab' ), $rsl_ie_n['slack']['url'] ) : 'https://hooks.slack.com/services/…' ); ?>">
					<?php if ( $rsl_ie_n['slack']['url_set'] ) : ?>
						<label class="rsl-ie-inline-check"><input type="checkbox" name="slack_clear" value="1"> <?php esc_html_e( 'Remove the saved URL', 'import-export-by-rockstarlab' ); ?></label>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'An incoming webhook URL from your Slack app (Slack → Apps → Incoming Webhooks). It is stored on this site only and never shown again in full.', 'import-export-by-rockstarlab' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Webhook', 'import-export-by-rockstarlab' ); ?></th>
				<td>
					<label><input type="checkbox" name="webhook_enabled" value="1" <?php checked( $rsl_ie_n['webhook']['enabled'] ); ?>> <?php esc_html_e( 'Send a signed JSON POST to', 'import-export-by-rockstarlab' ); ?></label>
					<input type="url" name="webhook_url" class="large-text code" value="" autocomplete="off" placeholder="<?php echo esc_attr( $rsl_ie_n['webhook']['url_set'] ? sprintf( /* translators: %s: masked URL. */ __( 'Saved: %s — enter a new URL to replace it', 'import-export-by-rockstarlab' ), $rsl_ie_n['webhook']['url'] ) : 'https://example.com/hooks/imports' ); ?>">
					<?php if ( $rsl_ie_n['webhook']['url_set'] ) : ?>
						<label class="rsl-ie-inline-check"><input type="checkbox" name="webhook_clear" value="1"> <?php esc_html_e( 'Remove the saved URL', 'import-export-by-rockstarlab' ); ?></label>
					<?php endif; ?>
					<?php if ( $rsl_ie_n['webhook']['secret_set'] ) : ?>
						<label class="rsl-ie-inline-check"><input type="checkbox" name="regenerate_secret" value="1"> <?php esc_html_e( 'Create a new signing secret', 'import-export-by-rockstarlab' ); ?></label>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'For Zapier, Make, n8n or your own system. The request carries the job, its counts and the first 20 row errors, signed with HMAC-SHA256 in the X-RSL-Signature header. Only public https addresses are allowed.', 'import-export-by-rockstarlab' ); ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rsl_ie_notifications_n8n' ), 'rsl_ie_notifications_n8n' ) ); ?>"><?php esc_html_e( 'Download an n8n workflow that receives it and checks the signature', 'import-export-by-rockstarlab' ); ?></a></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rsl-ie-notify-subject"><?php esc_html_e( 'Subject', 'import-export-by-rockstarlab' ); ?></label></th>
				<td><input type="text" id="rsl-ie-notify-subject" name="subject" class="large-text" value="<?php echo esc_attr( $rsl_ie_n['subject'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="rsl-ie-notify-message"><?php esc_html_e( 'Message', 'import-export-by-rockstarlab' ); ?></label></th>
				<td>
					<textarea id="rsl-ie-notify-message" name="message" class="large-text code" rows="7"><?php echo esc_textarea( $rsl_ie_n['message'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Placeholders:', 'import-export-by-rockstarlab' ); ?> <code><?php echo esc_html( implode( ' ', $rsl_ie_n['placeholders'] ) ); ?></code></p>
				</td>
			</tr>
		</table>
		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'import-export-by-rockstarlab' ); ?></button></p>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rsl-ie-notifications-test">
		<input type="hidden" name="action" value="rsl_ie_test_notifications">
		<?php wp_nonce_field( 'rsl_ie_test_notifications' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'Send Test Notification', 'import-export-by-rockstarlab' ); ?></button>
		<span class="description"><?php esc_html_e( 'Sends a sample message to every channel that is turned on and saved.', 'import-export-by-rockstarlab' ); ?></span>
	</form>

	<h2><?php esc_html_e( 'Recent notifications', 'import-export-by-rockstarlab' ); ?></h2>
	<?php if ( ! $rsl_ie_log ) : ?>
		<p class="description"><?php esc_html_e( 'Nothing sent yet.', 'import-export-by-rockstarlab' ); ?></p>
	<?php else : ?>
		<table class="widefat striped rsl-ie-notify-log">
			<thead><tr>
				<th><?php esc_html_e( 'Time', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Job', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Channel', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Result', 'import-export-by-rockstarlab' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rsl_ie_log as $rsl_ie_entry ) : ?>
				<tr>
					<td><?php echo esc_html( get_date_from_gmt( $rsl_ie_entry['time'], 'Y-m-d H:i' ) ); ?></td>
					<td><?php echo $rsl_ie_entry['job_id'] ? esc_html( '#' . $rsl_ie_entry['job_id'] ) : esc_html__( 'Test', 'import-export-by-rockstarlab' ); ?></td>
					<td><?php echo esc_html( ucfirst( $rsl_ie_entry['channel'] ) ); ?></td>
					<td><?php echo esc_html( ( $rsl_ie_entry['ok'] ? '✓ ' : '✗ ' ) . $rsl_ie_entry['detail'] . ( $rsl_ie_entry['attempt'] > 1 ? ' (' . sprintf( /* translators: %d: attempt number. */ __( 'attempt %d', 'import-export-by-rockstarlab' ), $rsl_ie_entry['attempt'] ) . ')' : '' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
