<?php
/**
 * Test Database Migration
 * 
 * Run this file to test if database tables are created correctly
 * Usage: php test_db_migration.php
 */

// Load WordPress  
require_once '/home/brovatar/Local Sites/wp-advanced-import-export/app/public/wp-load.php';

// Check if we're in CLI mode
if (php_sapi_name() !== 'cli') {
	die('This script must be run from command line');
}

echo "Testing WP Advanced Import Export Database Migration\n";
echo str_repeat('=', 60) . "\n\n";

// Enable debug mode temporarily
$old_debug = defined('WP_DEBUG') ? WP_DEBUG : false;
if (!defined('WP_DEBUG')) {
	define('WP_DEBUG', true);
}

echo "1. Checking if tables exist...\n";
$tables_exist = \WP_AIE\Helper\Database_Migration::tables_exist();
echo "   Result: " . ($tables_exist ? "YES" : "NO") . "\n\n";

echo "2. Attempting to create tables...\n";
try {
	\WP_AIE\Helper\Database_Migration::create_tables();
	echo "   Success: Tables created/updated\n\n";
} catch (\Exception $e) {
	echo "   ERROR: " . $e->getMessage() . "\n";
	echo "   Stack trace:\n";
	echo $e->getTraceAsString() . "\n\n";
} catch (\Error $e) {
	echo "   FATAL ERROR: " . $e->getMessage() . "\n";
	echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
	echo "   Stack trace:\n";
	echo $e->getTraceAsString() . "\n\n";
}

echo "3. Checking database version...\n";
$version = \WP_AIE\Helper\Database_Migration::get_version();
echo "   Current version: " . $version . "\n";
echo "   Expected version: " . \WP_AIE\Helper\Database_Migration::DB_VERSION . "\n\n";

echo "4. Verifying each table...\n";
global $wpdb;
$prefix = $wpdb->prefix;

$tables = [
	'aie_jobs',
	'aie_logs',
	'aie_field_maps',
	'aie_custom_functions',
	'aie_media_hashes',
	'aie_site_connections',
	'aie_content_sync',
	'aie_api_keys',
];

foreach ($tables as $table) {
	$full_table = $prefix . $table;
	$exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
	echo "   {$full_table}: " . ($exists ? "EXISTS" : "MISSING") . "\n";
	
	if ($exists) {
		$count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
		echo "      Rows: {$count}\n";
	}
}

echo "\n5. Checking for database errors...\n";
if ($wpdb->last_error) {
	echo "   ERROR: " . $wpdb->last_error . "\n";
} else {
	echo "   No errors\n";
}

echo "\n6. Checking table structure for aie_site_connections...\n";
$table_name = $prefix . 'aie_site_connections';
$columns = $wpdb->get_results("DESCRIBE $table_name");
if ($columns) {
	foreach ($columns as $column) {
		echo "   - {$column->Field} ({$column->Type}) {$column->Key}\n";
	}
} else {
	echo "   ERROR: Could not retrieve table structure\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Test completed!\n";
