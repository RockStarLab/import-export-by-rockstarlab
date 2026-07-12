<?php
/**
 * Job Schedules Page
 *
 * @package RockStarLab\ImportExport\View
 */

defined( 'ABSPATH' ) || exit;

$rsl_ie_schedule_model = rsl_ie()->Model->job_schedule;
$rsl_ie_schedules      = $rsl_ie_schedule_model->get_with_jobs();
$rsl_ie_source_jobs    = rsl_ie()->Model->job->all(
	[
		'limit'    => 1,
		'order_by' => 'id',
		'order'    => 'DESC',
	]
);

$rsl_ie_schedule_screen_nonce = wp_create_nonce( 'rsl_ie_schedules_screen' );
$rsl_ie_query_nonce           = filter_has_var( INPUT_GET, '_wpnonce' ) ? sanitize_text_field( (string) filter_input( INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW ) ) : '';
$rsl_ie_query_is_verified     = wp_verify_nonce( $rsl_ie_query_nonce, 'rsl_ie_schedules_screen' );
$rsl_ie_schedule_url          = static function ( $args = [] ) use ( $rsl_ie_schedule_screen_nonce ) {
	$args = array_merge(
		[
			'page'     => 'rsl-ie-schedules',
			'_wpnonce' => $rsl_ie_schedule_screen_nonce,
		],
		$args
	);

	return add_query_arg( $args, admin_url( 'admin.php' ) );
};

$rsl_ie_schedule_id_raw    = $rsl_ie_query_is_verified ? filter_input( INPUT_GET, 'schedule_id', FILTER_UNSAFE_RAW ) : null;
$rsl_ie_mode_raw           = $rsl_ie_query_is_verified ? filter_input( INPUT_GET, 'mode', FILTER_UNSAFE_RAW ) : null;
$rsl_ie_schedule_error_raw = $rsl_ie_query_is_verified ? filter_input( INPUT_GET, 'schedule_error', FILTER_UNSAFE_RAW ) : null;
$rsl_ie_schedule_saved     = $rsl_ie_query_is_verified && filter_has_var( INPUT_GET, 'schedule_saved' );
$rsl_ie_schedule_deleted   = $rsl_ie_query_is_verified && filter_has_var( INPUT_GET, 'schedule_deleted' );
$rsl_ie_schedule_id        = null !== $rsl_ie_schedule_id_raw && false !== $rsl_ie_schedule_id_raw ? absint( $rsl_ie_schedule_id_raw ) : 0;
$rsl_ie_mode               = null !== $rsl_ie_mode_raw && false !== $rsl_ie_mode_raw ? sanitize_key( $rsl_ie_mode_raw ) : '';
$rsl_ie_schedule_error     = null !== $rsl_ie_schedule_error_raw && false !== $rsl_ie_schedule_error_raw ? sanitize_text_field( $rsl_ie_schedule_error_raw ) : '';
$rsl_ie_selected           = $rsl_ie_schedule_id ? $rsl_ie_schedule_model->find( $rsl_ie_schedule_id ) : null;
$rsl_ie_readonly           = $rsl_ie_selected && 'view' === $rsl_ie_mode;
$rsl_ie_selected_job       = $rsl_ie_selected ? rsl_ie()->Model->job->find( (int) $rsl_ie_selected->source_job_id ) : null;

$rsl_ie_default_timestamp = time() + HOUR_IN_SECONDS;
$rsl_ie_start_timestamp   = $rsl_ie_selected ? strtotime( $rsl_ie_selected->start_at_gmt . ' UTC' ) : $rsl_ie_default_timestamp;
$rsl_ie_start_value       = wp_date( 'Y-m-d\TH:i', $rsl_ie_start_timestamp, wp_timezone() );
$rsl_ie_form_title        = $rsl_ie_readonly
	? __( 'Schedule details', 'import-export-by-rockstarlab' )
	: ( $rsl_ie_selected ? __( 'Edit schedule', 'import-export-by-rockstarlab' ) : __( 'Add schedule', 'import-export-by-rockstarlab' ) );
?>

<div id="rsl-ie-schedules" class="import-export-by-rockstarlab wrap">
	<h1><?php esc_html_e( 'Schedules', 'import-export-by-rockstarlab' ); ?></h1>
	<?php
	$rsl_ie_active_tab = 'schedules';
	require RSL_IE_PATH . 'app/View/settings/partials/jobs-tabs.php';
	?>

	<?php if ( $rsl_ie_schedule_saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Schedule saved.', 'import-export-by-rockstarlab' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $rsl_ie_schedule_deleted ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Schedule deleted.', 'import-export-by-rockstarlab' ); ?></p></div>
	<?php endif; ?>
	<?php if ( '' !== $rsl_ie_schedule_error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $rsl_ie_schedule_error ); ?></p></div>
	<?php endif; ?>

	<div class="card" style="max-width: 900px; margin-top: 20px;">
		<h2><?php echo esc_html( $rsl_ie_form_title ); ?></h2>
		<?php if ( empty( $rsl_ie_source_jobs ) && ! $rsl_ie_selected ) : ?>
			<p><?php esc_html_e( 'Complete at least one Job before creating a schedule.', 'import-export-by-rockstarlab' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rsl_ie_save_schedule">
				<input type="hidden" name="schedule_id" value="<?php echo esc_attr( $rsl_ie_selected ? $rsl_ie_selected->id : 0 ); ?>">
				<?php wp_nonce_field( 'rsl_ie_save_schedule' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="schedule-name"><?php esc_html_e( 'Name', 'import-export-by-rockstarlab' ); ?></label></th>
						<td><input id="schedule-name" name="schedule_name" type="text" class="regular-text" value="<?php echo esc_attr( $rsl_ie_selected ? $rsl_ie_selected->name : '' ); ?>" <?php disabled( $rsl_ie_readonly ); ?>></td>
					</tr>
					<tr>
						<th scope="row"><label for="source-job-id"><?php esc_html_e( 'Source Job', 'import-export-by-rockstarlab' ); ?></label></th>
						<td>
							<select id="source-job-id" class="rsl-ie-source-job-select" name="source_job_id" required <?php disabled( $rsl_ie_readonly ); ?> data-placeholder="<?php esc_attr_e( 'Search Jobs...', 'import-export-by-rockstarlab' ); ?>">
								<option value=""><?php esc_html_e( 'Select a Job', 'import-export-by-rockstarlab' ); ?></option>
								<?php if ( $rsl_ie_selected_job ) : ?>
									<option value="<?php echo esc_attr( $rsl_ie_selected_job->id ); ?>" selected>
										<?php echo esc_html( sprintf( '#%1$d — %2$s / %3$s (%4$s)', $rsl_ie_selected_job->id, $rsl_ie_selected_job->type, $rsl_ie_selected_job->data_type, $rsl_ie_selected_job->status ) ); ?>
									</option>
								<?php endif; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Each occurrence creates a new Job with the same configuration; the source log entry is never modified.', 'import-export-by-rockstarlab' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="schedule-type"><?php esc_html_e( 'Run type', 'import-export-by-rockstarlab' ); ?></label></th>
						<td>
							<select id="schedule-type" name="schedule_type" <?php disabled( $rsl_ie_readonly ); ?>>
								<option value="once" <?php selected( $rsl_ie_selected ? $rsl_ie_selected->schedule_type : 'once', 'once' ); ?>><?php esc_html_e( 'One time', 'import-export-by-rockstarlab' ); ?></option>
								<option value="recurring" <?php selected( $rsl_ie_selected ? $rsl_ie_selected->schedule_type : 'once', 'recurring' ); ?>><?php esc_html_e( 'Recurring', 'import-export-by-rockstarlab' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="schedule-recurrence"><?php esc_html_e( 'Repeat', 'import-export-by-rockstarlab' ); ?></label></th>
						<td>
							<select id="schedule-recurrence" name="recurrence" <?php disabled( $rsl_ie_readonly ); ?>>
								<option value="hourly" <?php selected( $rsl_ie_selected ? $rsl_ie_selected->recurrence : '', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'import-export-by-rockstarlab' ); ?></option>
								<option value="twicedaily" <?php selected( $rsl_ie_selected ? $rsl_ie_selected->recurrence : '', 'twicedaily' ); ?>><?php esc_html_e( 'Twice daily', 'import-export-by-rockstarlab' ); ?></option>
								<option value="daily" <?php selected( $rsl_ie_selected ? $rsl_ie_selected->recurrence : 'daily', 'daily' ); ?>><?php esc_html_e( 'Daily', 'import-export-by-rockstarlab' ); ?></option>
								<option value="weekly" <?php selected( $rsl_ie_selected ? $rsl_ie_selected->recurrence : '', 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'import-export-by-rockstarlab' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Ignored for a one-time schedule.', 'import-export-by-rockstarlab' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="schedule-start-at"><?php esc_html_e( 'First execution', 'import-export-by-rockstarlab' ); ?></label></th>
						<td>
							<input id="schedule-start-at" name="start_at" type="datetime-local" value="<?php echo esc_attr( $rsl_ie_start_value ); ?>" required <?php disabled( $rsl_ie_readonly ); ?>>
							<p class="description"><?php echo esc_html( sprintf( /* translators: %s: WordPress timezone name. */ __( 'Timezone: %s', 'import-export-by-rockstarlab' ), wp_timezone_string() ) ); ?></p>
						</td>
					</tr>
				</table>
				<?php if ( $rsl_ie_readonly ) : ?>
					<a class="button button-primary" href="
					<?php
					echo esc_url(
						$rsl_ie_schedule_url(
							[
								'schedule_id' => $rsl_ie_selected->id,
								'mode'        => 'edit',
							]
						)
					);
					?>
															"><?php esc_html_e( 'Edit', 'import-export-by-rockstarlab' ); ?></a>
				<?php else : ?>
					<?php submit_button( $rsl_ie_selected ? __( 'Update schedule', 'import-export-by-rockstarlab' ) : __( 'Add schedule', 'import-export-by-rockstarlab' ), 'primary', 'submit', false ); ?>
				<?php endif; ?>
				<?php if ( $rsl_ie_selected ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-schedules' ) ); ?>"><?php esc_html_e( 'Cancel', 'import-export-by-rockstarlab' ); ?></a>
				<?php endif; ?>
			</form>
		<?php endif; ?>
	</div>

	<h2 style="margin-top: 30px;"><?php esc_html_e( 'Existing schedules', 'import-export-by-rockstarlab' ); ?></h2>
	<table class="widefat striped">
		<thead><tr>
			<th><?php esc_html_e( 'Name', 'import-export-by-rockstarlab' ); ?></th>
			<th><?php esc_html_e( 'Source Job', 'import-export-by-rockstarlab' ); ?></th>
			<th><?php esc_html_e( 'Schedule', 'import-export-by-rockstarlab' ); ?></th>
			<th><?php esc_html_e( 'Next run', 'import-export-by-rockstarlab' ); ?></th>
			<th><?php esc_html_e( 'Last run', 'import-export-by-rockstarlab' ); ?></th>
			<th><?php esc_html_e( 'Status', 'import-export-by-rockstarlab' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'import-export-by-rockstarlab' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( empty( $rsl_ie_schedules ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No schedules found.', 'import-export-by-rockstarlab' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $rsl_ie_schedules as $rsl_ie_schedule ) : ?>
				<?php
				$rsl_ie_next = $rsl_ie_schedule->next_run_gmt ? strtotime( $rsl_ie_schedule->next_run_gmt . ' UTC' ) : 0;
				$rsl_ie_last = $rsl_ie_schedule->last_run_gmt ? strtotime( $rsl_ie_schedule->last_run_gmt . ' UTC' ) : 0;
				?>
				<tr>
					<td><strong><?php echo esc_html( $rsl_ie_schedule->name ); ?></strong></td>
					<td><?php echo esc_html( sprintf( '#%1$d — %2$s / %3$s', $rsl_ie_schedule->source_job_id, $rsl_ie_schedule->source_type ? $rsl_ie_schedule->source_type : '—', $rsl_ie_schedule->source_data_type ? $rsl_ie_schedule->source_data_type : '—' ) ); ?></td>
					<td><?php echo esc_html( 'recurring' === $rsl_ie_schedule->schedule_type ? ucfirst( $rsl_ie_schedule->recurrence ) : __( 'One time', 'import-export-by-rockstarlab' ) ); ?></td>
					<td><?php echo $rsl_ie_next ? esc_html( wp_date( 'Y-m-d H:i', $rsl_ie_next, wp_timezone() ) ) : '—'; ?></td>
					<td>
						<?php echo $rsl_ie_last ? esc_html( wp_date( 'Y-m-d H:i', $rsl_ie_last, wp_timezone() ) ) : '—'; ?>
						<?php if ( $rsl_ie_schedule->last_job_id ) : ?>
							<br><a href="<?php echo esc_url( admin_url( 'admin.php?page=rsl-ie-jobs-log' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: last Job ID. */ __( 'Job #%d', 'import-export-by-rockstarlab' ), $rsl_ie_schedule->last_job_id ) ); ?></a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( ucfirst( $rsl_ie_schedule->status ) ); ?></td>
					<td>
						<a href="
						<?php
						echo esc_url(
							$rsl_ie_schedule_url(
								[
									'schedule_id' => $rsl_ie_schedule->id,
									'mode'        => 'view',
								]
							)
						);
						?>
									"><?php esc_html_e( 'View', 'import-export-by-rockstarlab' ); ?></a> |
						<a href="
						<?php
						echo esc_url(
							$rsl_ie_schedule_url(
								[
									'schedule_id' => $rsl_ie_schedule->id,
									'mode'        => 'edit',
								]
							)
						);
						?>
									"><?php esc_html_e( 'Edit', 'import-export-by-rockstarlab' ); ?></a> |
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<input type="hidden" name="action" value="rsl_ie_delete_schedule">
							<input type="hidden" name="schedule_id" value="<?php echo esc_attr( $rsl_ie_schedule->id ); ?>">
							<?php wp_nonce_field( 'rsl_ie_delete_schedule' ); ?>
							<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'import-export-by-rockstarlab' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
