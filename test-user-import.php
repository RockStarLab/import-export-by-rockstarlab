<?php
/**
 * Test User Import Script
 *
 * Simple script to test user import functionality
 * Usage: wp eval-file test-user-import.php
 */

// Load WordPress
if ( ! defined( 'ABSPATH' ) ) {
	require_once __DIR__ . '/../../../wp-load.php';
}

use WP_AIE\Model\Import\Importer_Factory;
use WP_AIE\Model\Format\Format_Factory;

echo "=== User Import Test ===\n\n";

// CSV file path
$csv_file = __DIR__ . '/temp/export-2026-01-05-211153.csv';

if ( ! file_exists( $csv_file ) ) {
	echo "Error: CSV file not found at: $csv_file\n";
	exit( 1 );
}

echo "CSV file found: $csv_file\n\n";

// Parse CSV file
echo "Parsing CSV file...\n";
$parser = Format_Factory::create( 'csv' );
if ( is_wp_error( $parser ) ) {
	echo "Error creating parser: " . $parser->get_error_message() . "\n";
	exit( 1 );
}

$data = $parser->parse( $csv_file );
if ( is_wp_error( $data ) ) {
	echo "Error parsing CSV: " . $data->get_error_message() . "\n";
	exit( 1 );
}

echo "Parsed " . count( $data ) . " users from CSV\n\n";

// Show sample data
echo "Sample data (first user):\n";
if ( ! empty( $data[0] ) ) {
	print_r( array_slice( $data[0], 0, 10, true ) );
}
echo "\n";

// Get importer
echo "Creating user importer...\n";
$importer = Importer_Factory::get_importer( 'users' );
if ( is_wp_error( $importer ) ) {
	echo "Error creating importer: " . $importer->get_error_message() . "\n";
	exit( 1 );
}

echo "User importer created successfully\n\n";

// Set import options
$options = [
	'duplicate_mode'     => 'skip', // Skip existing users
	'duplicate_check'    => 'user_login', // Check by login
	'default_role'       => 'subscriber',
	'send_notification'  => false, // Don't send emails
	'update_password'    => false,
	'generate_password'  => true,
	'import_acf'         => true,
	'import_social'      => true,
];

echo "Import options:\n";
print_r( $options );
echo "\n";

// Validate data
echo "Validating data...\n";
$validation = $importer->validate( $data );
if ( is_wp_error( $validation ) ) {
	echo "Validation error: " . $validation->get_error_message() . "\n";
	exit( 1 );
}

echo "Data validation passed\n\n";

// Confirm before import
echo "Ready to import " . count( $data ) . " users.\n";
echo "Type 'yes' to continue or anything else to cancel: ";

$handle = fopen( "php://stdin", "r" );
$line   = fgets( $handle );
fclose( $handle );

if ( trim( $line ) !== 'yes' ) {
	echo "Import cancelled\n";
	exit( 0 );
}

echo "\nStarting import...\n\n";

// Run import
$start_time = microtime( true );
$result     = $importer->import( $data, $options );
$end_time   = microtime( true );

if ( is_wp_error( $result ) ) {
	echo "Import error: " . $result->get_error_message() . "\n";
	exit( 1 );
}

// Show results
echo "\n=== Import Results ===\n";
echo "Total time: " . round( $end_time - $start_time, 2 ) . " seconds\n";
echo "Total users: " . $result['total'] . "\n";
echo "Created: " . $result['created'] . "\n";
echo "Updated: " . $result['updated'] . "\n";
echo "Skipped: " . $result['skipped'] . "\n";
echo "Failed: " . $result['failed'] . "\n";

if ( ! empty( $result['errors'] ) ) {
	echo "\nErrors:\n";
	foreach ( $result['errors'] as $error ) {
		echo "  - Row {$error['row']}: {$error['message']}\n";
	}
}

echo "\n=== Import Complete ===\n";
