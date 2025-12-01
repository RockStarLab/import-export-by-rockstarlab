<?php
/**
 * Run database migrations
 * Access via: /wp-content/plugins/wp-advanced-import-export/run-migration.php
 */

// Load WordPress
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Access denied. You must be an administrator.' );
}

echo '<h1>Running Database Migrations</h1>';
echo '<pre>';

// Run migrations
\WP_AIE\Helper\Database_Migration::create_tables();

echo "\n✅ Migrations completed!\n";
echo "\nChecking columns...\n";

global $wpdb;
$table   = $wpdb->prefix . 'aie_jobs';
$columns = $wpdb->get_results( "DESCRIBE $table" );

echo "\nColumns in $table:\n";
foreach ( $columns as $column ) {
	echo "  - {$column->Field} ({$column->Type})\n";
}

echo '</pre>';
echo '<p><a href="' . admin_url( 'admin.php?page=aie-media-sync' ) . '">← Back to Media Sync</a></p>';
