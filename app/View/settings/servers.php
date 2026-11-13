<?php
/**
 * Servers page (Jobs Log → Servers): FTP, FTPS and SFTP servers for imports and exports.
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_servers   = $data['rsl_ie_servers'];
$rsl_ie_available = $data['rsl_ie_available'];
$rsl_ie_uid       = get_current_user_id();
$rsl_ie_notice    = get_transient( 'rsl_ie_servers_notice_' . $rsl_ie_uid );
delete_transient( 'rsl_ie_servers_notice_' . $rsl_ie_uid );
$rsl_ie_editing = null;
foreach ( $rsl_ie_servers as $rsl_ie_server ) {
	if ( $rsl_ie_server['id'] === $data['rsl_ie_edit'] ) {
		$rsl_ie_editing = $rsl_ie_server;
	}
}
$rsl_ie_show_form = $rsl_ie_editing || ! $rsl_ie_servers || isset( $_GET['add'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
$rsl_ie_f         = $rsl_ie_editing ? $rsl_ie_editing : [
	'id'       => '',
	'label'    => '',
	'protocol' => 'sftp',
	'host'     => '',
	'port'     => 22,
	'username' => '',
	'passive'  => true,
];
$rsl_ie_names = [
	'ftp'  => 'FTP',
	'ftps' => __( 'FTPS (FTP over TLS)', 'import-export-by-rockstarlab' ),
	'sftp' => __( 'SFTP (over SSH)', 'import-export-by-rockstarlab' ),
];
?>
<div id="rsl-ie-servers" class="wrap">
	<h1>
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Plugin Options', 'import-export-by-rockstarlab' ); ?>
	</h1>
	<?php
	$rsl_ie_options_active_tab = 'servers';
	require RSL_IE_PATH . 'app/View/settings/partials/plugin-options-tabs.php';
	?>

	<?php if ( is_array( $rsl_ie_notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $rsl_ie_notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $rsl_ie_notice[1] ); ?></p></div>
	<?php endif; ?>

	<div class="rsl-ie-settings-container">
	<div class="rsl-ie-settings-section">
		<div class="rsl-ie-settings-section-header">
			<h2><?php esc_html_e( 'FTP / SFTP Servers', 'import-export-by-rockstarlab' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Save an FTP, FTPS or SFTP server once, then import files from it and upload finished exports to it. Scheduled imports download the current file from the server on every run; scheduled exports upload a new file every time. Passwords are stored encrypted and are never shown again.', 'import-export-by-rockstarlab' ); ?></p>
		</div>
		<div class="rsl-ie-settings-section-body">

	<?php if ( ! $rsl_ie_servers ) : ?>
		<p class="rsl-ie-servers-empty"><?php esc_html_e( 'No servers saved yet. Add one below.', 'import-export-by-rockstarlab' ); ?></p>
	<?php endif; ?>
	<?php if ( $rsl_ie_servers ) : ?>
		<table class="widefat striped rsl-ie-servers-table">
			<thead><tr>
				<th><?php esc_html_e( 'Name', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Server', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'User', 'import-export-by-rockstarlab' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rsl_ie_servers as $rsl_ie_server ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $rsl_ie_server['label'] ); ?></strong></td>
					<td>
						<code><?php echo esc_html( $rsl_ie_server['protocol'] . '://' . $rsl_ie_server['host'] . ':' . $rsl_ie_server['port'] ); ?></code>
						<?php if ( '' !== $rsl_ie_server['host_key'] ) : ?>
							<br><span class="description"><?php /* translators: %s: SSH host key fingerprint. */ echo esc_html( sprintf( __( 'Host key: %s', 'import-export-by-rockstarlab' ), $rsl_ie_server['host_key'] ) ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $rsl_ie_server['username'] ); ?></td>
					<td class="rsl-ie-server-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="rsl_ie_test_remote_server">
							<input type="hidden" name="id" value="<?php echo esc_attr( $rsl_ie_server['id'] ); ?>">
							<?php wp_nonce_field( 'rsl_ie_test_remote_server' ); ?>
							<button type="submit" class="button button-small"><?php esc_html_e( 'Test Connection', 'import-export-by-rockstarlab' ); ?></button>
						</form>
						<a class="button button-small" href="<?php echo esc_url( add_query_arg( [ 'page' => 'rsl-ie-servers', 'edit' => $rsl_ie_server['id'] ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'import-export-by-rockstarlab' ); ?></a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Remove this server? Scheduled imports and exports that use it will stop working until you choose another server.', 'import-export-by-rockstarlab' ) ); ?>');">
							<input type="hidden" name="action" value="rsl_ie_delete_remote_server">
							<input type="hidden" name="id" value="<?php echo esc_attr( $rsl_ie_server['id'] ); ?>">
							<?php wp_nonce_field( 'rsl_ie_delete_remote_server' ); ?>
							<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Remove', 'import-export-by-rockstarlab' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( ! $rsl_ie_show_form ) : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( add_query_arg( [ 'page' => 'rsl-ie-servers', 'add' => 1 ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Add Server', 'import-export-by-rockstarlab' ); ?></a></p>
		<?php endif; ?>
	<?php endif; ?>
		</div>
	</div>

	<?php if ( $rsl_ie_show_form ) : ?>
	<div class="rsl-ie-settings-section">
		<div class="rsl-ie-settings-section-header">
			<h2><?php echo $rsl_ie_editing ? esc_html__( 'Edit server', 'import-export-by-rockstarlab' ) : esc_html__( 'Add a server', 'import-export-by-rockstarlab' ); ?></h2>
		</div>
		<div class="rsl-ie-settings-section-body">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rsl-ie-server-form" autocomplete="off">
			<input type="hidden" name="action" value="rsl_ie_save_remote_server">
			<input type="hidden" name="id" value="<?php echo esc_attr( $rsl_ie_f['id'] ); ?>">
			<?php wp_nonce_field( 'rsl_ie_save_remote_server' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rsl-ie-server-label"><?php esc_html_e( 'Name', 'import-export-by-rockstarlab' ); ?></label></th>
					<td><input type="text" id="rsl-ie-server-label" name="label" class="regular-text" value="<?php echo esc_attr( $rsl_ie_f['label'] ); ?>" placeholder="<?php esc_attr_e( 'Supplier FTP', 'import-export-by-rockstarlab' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Protocol', 'import-export-by-rockstarlab' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $rsl_ie_names as $rsl_ie_proto => $rsl_ie_name ) : ?>
								<label class="rsl-ie-inline-check"><input type="radio" name="protocol" value="<?php echo esc_attr( $rsl_ie_proto ); ?>" <?php checked( $rsl_ie_proto, $rsl_ie_f['protocol'] ); ?> <?php disabled( empty( $rsl_ie_available[ $rsl_ie_proto ] ) ); ?> onchange="var p=document.getElementById('rsl-ie-server-port');if(p&&(p.value===''||p.value==='21'||p.value==='22')){p.value=this.value==='sftp'?'22':'21';}"> <?php echo esc_html( $rsl_ie_name ); ?></label>
							<?php endforeach; ?>
						</fieldset>
						<?php if ( empty( $rsl_ie_available['ftp'] ) ) : ?>
							<p class="description"><?php esc_html_e( 'FTP and FTPS need the PHP FTP extension, which this server does not have. SFTP works without it.', 'import-export-by-rockstarlab' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rsl-ie-server-host"><?php esc_html_e( 'Host', 'import-export-by-rockstarlab' ); ?></label></th>
					<td>
						<input type="text" id="rsl-ie-server-host" name="host" class="regular-text code" value="<?php echo esc_attr( $rsl_ie_f['host'] ); ?>" placeholder="ftp.example.com" required>
						<label for="rsl-ie-server-port"><?php esc_html_e( 'Port', 'import-export-by-rockstarlab' ); ?></label>
						<input type="number" id="rsl-ie-server-port" name="port" class="small-text" min="1" max="65535" value="<?php echo esc_attr( (string) $rsl_ie_f['port'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rsl-ie-server-user"><?php esc_html_e( 'User name', 'import-export-by-rockstarlab' ); ?></label></th>
					<td><input type="text" id="rsl-ie-server-user" name="username" class="regular-text" value="<?php echo esc_attr( $rsl_ie_f['username'] ); ?>" autocomplete="off" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="rsl-ie-server-pass"><?php esc_html_e( 'Password', 'import-export-by-rockstarlab' ); ?></label></th>
					<td>
						<input type="password" id="rsl-ie-server-pass" name="password" class="regular-text" value="" autocomplete="new-password" <?php echo $rsl_ie_editing ? '' : 'required'; ?> placeholder="<?php echo $rsl_ie_editing ? esc_attr__( 'Saved — enter a new password to change it', 'import-export-by-rockstarlab' ) : ''; ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Passive mode', 'import-export-by-rockstarlab' ); ?></th>
					<td>
						<label><input type="checkbox" name="passive" value="1" <?php checked( ! empty( $rsl_ie_f['passive'] ) ); ?>> <?php esc_html_e( 'Use passive mode (FTP and FTPS). Leave it on unless your server needs active mode.', 'import-export-by-rockstarlab' ); ?></label>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save and Test Connection', 'import-export-by-rockstarlab' ); ?></button>
				<?php if ( $rsl_ie_servers ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( [ 'page' => 'rsl-ie-servers' ], admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
		</div>
	</div>
	<?php endif; ?>
	</div>
</div>
