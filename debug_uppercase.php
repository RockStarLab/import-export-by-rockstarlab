<?php
/**
 * Debug uppercase function
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

global $wpdb;

// Get uppercase function
$function = $wpdb->get_row(
	"
    SELECT * FROM {$wpdb->prefix}aie_custom_functions 
    WHERE source LIKE '%uppercase%' OR name LIKE '%uppercase%'
    LIMIT 1
"
);

echo "<h2>Uppercase Function</h2>\n";
echo "<pre>\n";
print_r( $function );
echo "</pre>\n";

// Test the function
if ( $function ) {
	echo "<h2>Test Function</h2>\n";
	echo "<pre>\n";

	$executor = new \WP_AIE\Helper\Function_Executor();

	$test_values = [
		'hello world',
		'HELLO WORLD',
		'Hello World',
		'Sample Page',
		'SAMPLE PAGE',
	];

	foreach ( $test_values as $test ) {
		$result = $executor->execute( $function->id, $test, [] );
		echo "Input: '{$test}' => Output: ";
		if ( is_wp_error( $result ) ) {
			echo 'ERROR: ' . $result->get_error_message();
		} else {
			echo "'{$result}'";
		}

		if ( $test === $result ) {
			echo ' [NO CHANGE - WILL BE SKIPPED]';
		}
		echo "\n";
	}
	echo "</pre>\n";
}

// Check current page titles
echo "<h2>Current Page Titles</h2>\n";
echo "<pre>\n";
$pages = $wpdb->get_results(
	"
    SELECT ID, post_title, post_status 
    FROM {$wpdb->posts} 
    WHERE post_type = 'page' 
    ORDER BY post_title
"
);

$already_uppercase = 0;
$need_update       = 0;

foreach ( $pages as $page ) {
	$uppercase_title      = strtoupper( $page->post_title );
	$is_already_uppercase = ( $page->post_title === $uppercase_title );

	if ( $is_already_uppercase ) {
		++$already_uppercase;
		echo '[SKIP] ';
	} else {
		++$need_update;
		echo '[UPDATE] ';
	}

	echo "ID {$page->ID}: '{$page->post_title}'";

	if ( ! $is_already_uppercase ) {
		echo " => '{$uppercase_title}'";
	}

	echo "\n";
}

echo "\n";
echo "Summary:\n";
echo "Already uppercase: {$already_uppercase}\n";
echo "Need update: {$need_update}\n";
echo 'Total: ' . count( $pages ) . "\n";
echo "</pre>\n";
