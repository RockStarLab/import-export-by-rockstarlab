<?php
/**
 * Remove duplicate pages
 *
 * This script will find and remove duplicate pages, keeping only the oldest version
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

global $wpdb;

echo "<h2>Finding Duplicate Pages</h2>\n";
echo "<pre>\n";

// Find pages with duplicate titles
$duplicates = $wpdb->get_results(
	"
    SELECT post_title, COUNT(*) as count, GROUP_CONCAT(ID ORDER BY ID) as ids
    FROM {$wpdb->posts}
    WHERE post_type = 'page'
    AND post_status IN ('publish', 'draft', 'private', 'pending')
    GROUP BY post_title
    HAVING count > 1
    ORDER BY count DESC
"
);

if ( empty( $duplicates ) ) {
	echo "No duplicate pages found!\n";
	echo "</pre>\n";
	exit;
}

echo 'Found ' . count( $duplicates ) . " titles with duplicates:\n\n";

$total_to_delete = 0;
$deleted         = 0;

foreach ( $duplicates as $dup ) {
	$ids        = explode( ',', $dup->ids );
	$keep_id    = array_shift( $ids ); // Keep first (oldest) ID
	$delete_ids = $ids;

	echo "Title: '{$dup->post_title}' - {$dup->count} copies\n";
	echo "  Keep ID: {$keep_id}\n";
	echo '  Delete IDs: ' . implode( ', ', $delete_ids ) . "\n";

	$total_to_delete += count( $delete_ids );

	// Delete duplicates
	foreach ( $delete_ids as $delete_id ) {
		$result = wp_delete_post( $delete_id, true ); // Force delete (bypass trash)
		if ( $result ) {
			++$deleted;
			echo "    ✓ Deleted page ID {$delete_id}\n";
		} else {
			echo "    ✗ Failed to delete page ID {$delete_id}\n";
		}
	}

	echo "\n";
}

echo "\n";
echo "Summary:\n";
echo "Total duplicates to delete: {$total_to_delete}\n";
echo "Successfully deleted: {$deleted}\n";
echo 'Failed: ' . ( $total_to_delete - $deleted ) . "\n";

// Show remaining pages
$remaining = $wpdb->get_var(
	"
    SELECT COUNT(*) 
    FROM {$wpdb->posts} 
    WHERE post_type = 'page' 
    AND post_status IN ('publish', 'draft', 'private', 'pending')
"
);

echo "\nRemaining pages: {$remaining}\n";

echo "</pre>\n";
