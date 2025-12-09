<?php
/**
 * Test Job Creation
 *
 * Add this to functions.php temporarily to test
 */

add_action(
	'init',
	function () {
		if ( isset( $_GET['test_job'] ) && current_user_can( 'manage_options' ) ) {
			$job_model = WP_AIE()->Model->job;

			$test_job = [
				'type'            => 'export',
				'status'          => 'completed',
				'user_id'         => get_current_user_id(),
				'data_type'       => 'post',
				'file_format'     => 'csv',
				'total_items'     => 10,
				'processed_items' => 10,
				'success_items'   => 10,
				'progress'        => 100,
				'parameters'      => wp_json_encode( [ 'test' => true ] ),
			];

			$job_id = $job_model->create( $test_job );

			echo '<pre>';
			echo 'Test Job Created: ';
			var_dump( $job_id );
			echo "\n\nJob Model: ";
			var_dump( $job_model );
			echo "\n\nAll Jobs: ";
			var_dump( $job_model->get_all( [], 5, 0 ) );
			echo '</pre>';
			exit;
		}
	}
);
