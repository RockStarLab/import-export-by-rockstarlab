<?php
/**
 * Database Migration Helper
 * 
 * Handles creation and management of custom database tables
 * 
 * @package WP_AIE
 * @subpackage Helper
 */

namespace WP_AIE\helper;

class database_migration {
    
    /**
     * Database version
     * Update this when schema changes
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Database version option name
     */
    const DB_VERSION_OPTION = 'aie_db_version';
    
    /**
     * Create all custom tables
     * Called on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        
        // Get current DB version
        $current_version = get_option( self::DB_VERSION_OPTION );
        
        // Only create/update tables if version changed
        if ( $current_version === self::DB_VERSION ) {
            return;
        }
        
        // 1. Jobs table - история импорта/экспорта
        $sql_jobs = "CREATE TABLE {$prefix}aie_jobs (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            type ENUM('import', 'export') NOT NULL,
            data_type VARCHAR(50) NOT NULL,
            file_format VARCHAR(10) NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed', 'paused', 'cancelled') DEFAULT 'pending',
            total_items INT DEFAULT 0,
            processed_items INT DEFAULT 0,
            success_items INT DEFAULT 0,
            failed_items INT DEFAULT 0,
            file_path VARCHAR(255),
            file_size BIGINT(20),
            settings TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME,
            INDEX user_id_idx (user_id),
            INDEX status_idx (status),
            INDEX type_idx (type),
            INDEX created_at_idx (created_at)
        ) ENGINE=InnoDB $charset_collate;";
        
        // 2. Logs table - логи выполнения
        $sql_logs = "CREATE TABLE {$prefix}aie_logs (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id BIGINT(20) UNSIGNED NOT NULL,
            level ENUM('info', 'warning', 'error') DEFAULT 'info',
            message TEXT NOT NULL,
            data LONGTEXT,
            created_at DATETIME NOT NULL,
            INDEX job_id_idx (job_id),
            INDEX level_idx (level),
            INDEX created_at_idx (created_at)
        ) ENGINE=InnoDB $charset_collate;";
        
        // 3. Field Maps table - сохраненные пресеты маппинга
        $sql_field_maps = "CREATE TABLE {$prefix}aie_field_maps (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            data_type VARCHAR(50) NOT NULL,
            mapping TEXT NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX user_id_idx (user_id),
            INDEX data_type_idx (data_type)
        ) ENGINE=InnoDB $charset_collate;";
        
        // 4. Custom Functions table - пользовательские функции
        $sql_custom_functions = "CREATE TABLE {$prefix}aie_custom_functions (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            function_code LONGTEXT NOT NULL,
            source VARCHAR(100) DEFAULT 'custom',
            input_type VARCHAR(50) DEFAULT 'string',
            output_type VARCHAR(50) DEFAULT 'string',
            is_active TINYINT(1) DEFAULT 1,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_used_at DATETIME,
            usage_count INT DEFAULT 0,
            INDEX name_idx (name),
            INDEX user_id_idx (user_id),
            INDEX is_active_idx (is_active),
            INDEX source_idx (source)
        ) ENGINE=InnoDB $charset_collate;";
        
        // 5. Media Sync table - синхронизация медиа папок
        $sql_media_sync = "CREATE TABLE {$prefix}aie_media_sync (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id BIGINT(20) UNSIGNED NOT NULL,
            folder_path VARCHAR(500) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            attachment_id BIGINT(20) UNSIGNED,
            status ENUM('pending', 'synced', 'skipped', 'failed') DEFAULT 'pending',
            skip_reason VARCHAR(100),
            file_hash VARCHAR(32),
            file_size BIGINT(20),
            error_message TEXT,
            created_at DATETIME NOT NULL,
            INDEX job_id_idx (job_id),
            INDEX folder_path_idx (folder_path(255)),
            INDEX file_hash_idx (file_hash),
            INDEX attachment_id_idx (attachment_id),
            INDEX status_idx (status)
        ) ENGINE=InnoDB $charset_collate;";
        
        // 6. Site Connections table - подключения между сайтами
        $sql_site_connections = "CREATE TABLE {$prefix}aie_site_connections (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            remote_url VARCHAR(500) NOT NULL,
            api_key VARCHAR(100) NOT NULL UNIQUE,
            direction ENUM('pull', 'push', 'bidirectional') DEFAULT 'bidirectional',
            status ENUM('active', 'inactive', 'error') DEFAULT 'active',
            last_sync_at DATETIME,
            last_error TEXT,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX remote_url_idx (remote_url(255)),
            INDEX status_idx (status),
            INDEX created_by_idx (created_by)
        ) ENGINE=InnoDB $charset_collate;";
        
        // 7. Content Sync table - история синхронизации контента
        $sql_content_sync = "CREATE TABLE {$prefix}aie_content_sync (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id BIGINT(20) UNSIGNED NOT NULL,
            connection_id BIGINT(20) UNSIGNED NOT NULL,
            direction ENUM('pull', 'push') NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            local_id BIGINT(20),
            remote_id BIGINT(20),
            action ENUM('created', 'updated', 'skipped', 'failed') NOT NULL,
            error_message TEXT,
            created_at DATETIME NOT NULL,
            INDEX job_id_idx (job_id),
            INDEX connection_id_idx (connection_id),
            INDEX content_type_idx (content_type),
            INDEX local_id_idx (local_id),
            INDEX remote_id_idx (remote_id)
        ) ENGINE=InnoDB $charset_collate;";
        
        // 8. API Keys table - API ключи для входящих подключений
        $sql_api_keys = "CREATE TABLE {$prefix}aie_api_keys (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            api_key VARCHAR(100) NOT NULL UNIQUE,
            permissions TEXT,
            allowed_ips TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            last_used_at DATETIME,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX status_idx (status),
            INDEX api_key_idx (api_key)
        ) ENGINE=InnoDB $charset_collate;";
        
        // Execute table creation
        dbDelta( $sql_jobs );
        dbDelta( $sql_logs );
        dbDelta( $sql_field_maps );
        dbDelta( $sql_custom_functions );
        dbDelta( $sql_media_sync );
        dbDelta( $sql_site_connections );
        dbDelta( $sql_content_sync );
        dbDelta( $sql_api_keys );
        
        // Update DB version
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        
        do_action( 'aie_tables_created' );
    }
    
    /**
     * Drop all custom tables
     * Called on plugin uninstall (not deactivation)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        
        // Drop tables in reverse order (respect foreign keys)
        $tables = [
            "{$prefix}aie_content_sync",
            "{$prefix}aie_site_connections",
            "{$prefix}aie_media_sync",
            "{$prefix}aie_logs",
            "{$prefix}aie_api_keys",
            "{$prefix}aie_custom_functions",
            "{$prefix}aie_field_maps",
            "{$prefix}aie_jobs",
        ];
        
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
        }
        
        // Delete DB version option
        delete_option( self::DB_VERSION_OPTION );
        
        do_action( 'aie_tables_dropped' );
    }
    
    /**
     * Check if tables exist
     * 
     * @return bool
     */
    public static function tables_exist() {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        $table = "{$prefix}aie_jobs";
        
        $result = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
        
        return $result === $table;
    }
    
    /**
     * Get database version
     * 
     * @return string|false
     */
    public static function get_version() {
        return get_option( self::DB_VERSION_OPTION, false );
    }
    
    /**
     * Clean up old jobs (>30 days)
     * Can be called via cron
     */
    public static function cleanup_old_jobs() {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        $days = apply_filters( 'aie_cleanup_old_jobs_days', 30 );
        
        // Delete old jobs and related logs (cascade)
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$prefix}aie_jobs 
            WHERE status IN ('completed', 'failed', 'cancelled') 
            AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
        
        // Delete orphaned logs (just in case)
        $wpdb->query(
            "DELETE l FROM {$prefix}aie_logs l
            LEFT JOIN {$prefix}aie_jobs j ON l.job_id = j.id
            WHERE j.id IS NULL"
        );
        
        do_action( 'aie_old_jobs_cleaned' );
    }
    
    /**
     * Clean up exported files older than X days
     * 
     * @param int $days Number of days to keep files
     */
    public static function cleanup_old_files( $days = 7 ) {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        $days = apply_filters( 'aie_cleanup_old_files_days', $days );
        
        // Get old export jobs with file paths
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, file_path FROM {$prefix}aie_jobs 
            WHERE type = 'export' 
            AND status = 'completed' 
            AND file_path IS NOT NULL 
            AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
        
        $deleted_count = 0;
        
        foreach ( $results as $row ) {
            // Delete physical file
            if ( file_exists( $row->file_path ) ) {
                @unlink( $row->file_path );
                $deleted_count++;
            }
            
            // Clear file_path in database
            $wpdb->update(
                "{$prefix}aie_jobs",
                [ 'file_path' => null ],
                [ 'id' => $row->id ],
                [ '%s' ],
                [ '%d' ]
            );
        }
        
        do_action( 'aie_old_files_cleaned', $deleted_count );
        
        return $deleted_count;
    }
}
