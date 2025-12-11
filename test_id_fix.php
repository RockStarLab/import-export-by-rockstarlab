<?php
/**
 * Test ID field inclusion
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

echo "<h2>Testing Content Updater ID Field Fix</h2>\n";
echo "<pre>\n";

// Test that exporter actually returns ID
echo "Testing Post Exporter with 'post_title' field only:\n";

$exporter = \WP_AIE\Model\Export\Exporter_Factory::get_exporter( 'page' );
$data     = $exporter->get_data(
	[
		'post_type'   => 'page',
		'post_status' => 'any',
		'limit'       => 3,
		'fields'      => [ 'post_title' ],  // Only requesting post_title
	]
);

if ( ! empty( $data ) ) {
	foreach ( $data as $index => $item ) {
		echo "\nItem #{$index}:\n";
		echo '  Keys: ' . implode( ', ', array_keys( $item ) ) . "\n";
		if ( isset( $item['ID'] ) ) {
			echo "  ✓ ID field present: {$item['ID']}\n";
		} else {
			echo "  ✗ ID field MISSING!\n";
		}
		if ( isset( $item['post_title'] ) ) {
			echo "  post_title: {$item['post_title']}\n";
		}
	}
} else {
	echo "No data returned!\n";
}

echo "\n";

// Now test with ID explicitly included
echo "Testing Post Exporter with 'ID' and 'post_title' fields:\n";

$data2 = $exporter->get_data(
	[
		'post_type'   => 'page',
		'post_status' => 'any',
		'limit'       => 3,
		'fields'      => [ 'ID', 'post_title' ],
	]
);

if ( ! empty( $data2 ) ) {
	foreach ( $data2 as $index => $item ) {
		echo "\nItem #{$index}:\n";
		echo '  Keys: ' . implode( ', ', array_keys( $item ) ) . "\n";
		if ( isset( $item['ID'] ) ) {
			echo "  ✓ ID field present: {$item['ID']}\n";
		} else {
			echo "  ✗ ID field MISSING!\n";
		}
		if ( isset( $item['post_title'] ) ) {
			echo "  post_title: {$item['post_title']}\n";
		}
	}
} else {
	echo "No data returned!\n";
}

echo "</pre>\n";
