<?php
/**
 * Check job statistics
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

global $wpdb;

// Get latest update jobs
$jobs = $wpdb->get_results(
	"
    SELECT 
        id, 
        type, 
        status, 
        total_items, 
        processed_items, 
        imported_items, 
        skipped_items, 
        error_items,
        parameters,
        created_at 
    FROM {$wpdb->prefix}aie_jobs 
    WHERE type = 'update' 
    ORDER BY id DESC 
    LIMIT 5
"
);

echo "<h2>Latest Update Jobs</h2>\n";
echo "<pre>\n";
foreach ( $jobs as $job ) {
	echo "Job ID: {$job->id}\n";
	echo "Status: {$job->status}\n";
	echo "Total Items: {$job->total_items}\n";
	echo "Processed: {$job->processed_items}\n";
	echo "Imported (Updated): {$job->imported_items}\n";
	echo "Skipped: {$job->skipped_items}\n";
	echo "Errors: {$job->error_items}\n";
	echo "Created: {$job->created_at}\n";
	echo "\nParameters:\n";
	$params = json_decode( $job->parameters, true );
	print_r( $params );
	echo "\n" . str_repeat( '-', 80 ) . "\n\n";
}
echo "</pre>\n";

// Check logs for the latest job
if ( ! empty( $jobs ) ) {
	$latest_job_id = $jobs[0]->id;

	echo "<h2>Logs for Job #{$latest_job_id}</h2>\n";
	echo "<pre>\n";

	$logs = $wpdb->get_results(
		$wpdb->prepare(
			"
        SELECT level, message, created_at 
        FROM {$wpdb->prefix}aie_logs 
        WHERE job_id = %d 
        ORDER BY id DESC 
        LIMIT 100
    ",
			$latest_job_id
		)
	);

	foreach ( $logs as $log ) {
		echo "[{$log->created_at}] [{$log->level}] {$log->message}\n";
	}
	echo "</pre>\n";
}

// Count pages
$pages_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status IN ('publish', 'draft', 'private', 'pending')" );
echo "<h2>Total Pages in Database</h2>\n";
echo "<p>Total: {$pages_count} pages</p>\n";

// Get sample pages with their current titles
$pages = $wpdb->get_results(
	"
    SELECT ID, post_title, post_status 
    FROM {$wpdb->posts} 
    WHERE post_type = 'page' 
    ORDER BY ID 
    LIMIT 20
"
);

echo "<h2>Sample Pages (first 20)</h2>\n";
echo "<table border='1' cellpadding='5'>\n";
echo "<tr><th>ID</th><th>Title</th><th>Status</th></tr>\n";
foreach ( $pages as $page ) {
	echo "<tr><td>{$page->ID}</td><td>{$page->post_title}</td><td>{$page->post_status}</td></tr>\n";
}
echo "</table>\n";
