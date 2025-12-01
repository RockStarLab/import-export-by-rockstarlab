# Database Migration Fix

## Problem
The `wp_aie_jobs` table was missing two columns:
- `parameters` (was being used in code but never created)
- `progress` (used everywhere but not in schema)

## Solution

### 1. Use Existing `settings` Column
Changed code to use existing `settings` TEXT column instead of non-existent `parameters`:

**Files Updated:**
- `app/Controller/Media_Sync_Controller.php` - Line 114: Changed `'parameters'` to `'settings'`
- `app/Model/Queue/Media_Sync_Processor.php` - Multiple lines: Changed all `$parameters` to `$settings`

### 2. Add `progress` Column Migration
Added automatic migration in `Database_Migration.php`:

```php
private static function maybe_add_progress_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aie_jobs';
    
    // Check if column exists
    $column_exists = $wpdb->get_results(
        "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '{$table_name}' 
        AND COLUMN_NAME = 'progress'"
    );
    
    // Add if missing
    if ( empty( $column_exists ) ) {
        $wpdb->query(
            "ALTER TABLE {$table_name} 
            ADD COLUMN progress DECIMAL(5,2) DEFAULT 0 
            COMMENT 'Progress percentage (0-100)' 
            AFTER failed_items"
        );
    }
}
```

This migration runs automatically on plugin activation.

## Manual SQL Fix (If Needed)

If the migration doesn't run automatically, execute this SQL manually:

```sql
ALTER TABLE wp_aie_jobs 
ADD COLUMN progress DECIMAL(5,2) DEFAULT 0 
COMMENT 'Progress percentage (0-100)' 
AFTER failed_items;
```

Replace `wp_` with your actual WordPress table prefix.

## Verification

Check if column exists:
```sql
DESCRIBE wp_aie_jobs;
```

Should show:
```
+------------------+---------------+------+-----+---------+----------------+
| Field            | Type          | Null | Key | Default | Extra          |
+------------------+---------------+------+-----+---------+----------------+
| id               | bigint(20)    | NO   | PRI | NULL    | auto_increment |
| user_id          | bigint(20)    | NO   | MUL | NULL    |                |
| type             | enum(...)     | NO   | MUL | NULL    |                |
| ...              | ...           | ...  | ... | ...     | ...            |
| failed_items     | int(11)       | YES  |     | 0       |                |
| progress         | decimal(5,2)  | YES  |     | 0.00    |                | <- NEW
| file_path        | varchar(255)  | YES  |     | NULL    |                |
| ...              | ...           | ...  | ... | ...     | ...            |
+------------------+---------------+------+-----+---------+----------------+
```

## To Apply Migration

### Option 1: Deactivate and Reactivate Plugin
1. Go to WordPress Admin → Plugins
2. Deactivate "WP Advanced Import Export"
3. Activate it again
4. Migration will run automatically

### Option 2: Visit Plugin Page
Just load the admin page - migration runs on `admin_init` hook via `App.php`:
```php
if ( ! Database_Migration::tables_exist() || Database_Migration::get_version() !== Database_Migration::DB_VERSION ) {
    Database_Migration::create_tables();
}
```

### Option 3: Manual SQL
Execute the ALTER TABLE query above directly in phpMyAdmin or MySQL CLI.

## Testing

After migration, test Media Sync:
1. Go to Media Sync page
2. Select folder
3. Click "Scan Folder"
4. Click "Start Sync"
5. Should NOT get "Unknown column" error anymore
6. Progress should track correctly (0.00 → 100.00)
