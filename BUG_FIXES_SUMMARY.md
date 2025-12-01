# Bug Fixes Summary - Media Sync

## Issue 1: Database Column Errors

### Error
```
Unknown column 'parameters' in 'field list'
Uncaught TypeError: count(): Argument #1 ($value) must be of type Countable|array, null given
```

## Issue 2: Undefined Method Error

### Error
```
PHP Fatal error: Uncaught Error: Call to undefined method WP_AIE\Model\Job::read()
```

## Issue 3: Database Column Errors (Part 2)

### Error
```
WordPress database error Unknown column 'started_at' in 'field list'
PHP Fatal error: file_exists(): Argument #1 ($filename) must be of type string, array given
```

### Root Causes (All Issues)
1. Code tried to use non-existent `parameters` column
2. Code tried to use non-existent `progress` column  
3. Code referenced undefined variable `$file_paths`
4. Code referenced undefined variable `$relative_path`
5. **Code called non-existent method `read()` instead of `find()`**
6. **Code tried to update non-existent column `started_at`**
7. **Code passed array to `file_exists()` instead of string**
8. **Code called `update_progress()` with wrong number of arguments**

### Fixes Applied

#### 1. Use Existing `settings` Column
**Changed:** `app/Controller/Media_Sync_Controller.php` line 114
```php
// BEFORE
'parameters' => wp_json_encode([...])

// AFTER  
'settings' => wp_json_encode([...])
```

**Changed:** `app/Model/Queue/Media_Sync_Processor.php` (multiple lines)
```php
// BEFORE
$parameters = json_decode( $job->parameters, true );
$parameters['offset'] = $new_offset;
'parameters' => wp_json_encode( $parameters )

// AFTER
$settings = json_decode( $job->settings, true );
$settings['offset'] = $new_offset;
'settings' => wp_json_encode( $settings )
```

#### 2. Add Missing `progress` Column
**Added:** `app/Helper/Database_Migration.php`
```php
private static function maybe_add_progress_column() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aie_jobs';
    
    // Check if column exists
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'progress'",
            DB_NAME,
            $table_name
        )
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

**Modified:** `create_tables()` method to call migration
```php
public static function create_tables() {
    // ... existing code ...
    
    do_action( 'aie_tables_created' );
    
    // Run migrations for existing tables
    self::maybe_add_progress_column(); // <- NEW
}
```

#### 3. Fix Undefined Variable `$file_paths`
**Changed:** `app/Controller/Media_Sync_Controller.php` line 137
```php
// BEFORE
$this->send_success([
    'job_id'      => $job_id,
    'total_files' => count( $file_paths ), // <- $file_paths undefined!
]);

// AFTER
$this->send_success([
    'job_id'      => $job_id,
    'folder_path' => $folder_path,
]);
```

#### 4. Fix Undefined Variable `$relative_path`
**Changed:** `app/Controller/Media_Sync_Controller.php` line 137 (same location)
```php
// First iteration had this bug:
'folder_path' => $relative_path, // <- $relative_path undefined!

// Final fix:
'folder_path' => $folder_path, // <- Use existing variable
```

#### 5. Fix Undefined Method `read()`
The model uses `find()` method, not `read()`. Fixed in multiple files:

**Changed:** `app/Controller/Media_Sync_Controller.php` line 153
```php
// BEFORE
$data = $job->read( $job_id );

// AFTER
$data = $job->find( $job_id );
```

**Changed:** `app/Model/Queue/Media_Sync_Processor.php` lines 73, 347
```php
// BEFORE
$job = $this->job_model->read( $job_id );

// AFTER
$job = $this->job_model->find( $job_id );
```

**Also fixed in:**
- `app/Controller/Export_Controller.php` (3 occurrences: lines 191, 224, 296)
- `app/Controller/Import_Controller.php` (2 occurrences: lines 258, 308)
- `app/Controller/Job_Controller.php` (1 occurrence: line 103)

#### 6. Fix Non-Existent Column `started_at`
Table `wp_aie_jobs` doesn't have `started_at` column. Changed to use existing `updated_at`.

**Changed:** `app/Model/Queue/Media_Sync_Processor.php` line 84
```php
// BEFORE
$this->job_model->update( $job_id, [
    'status'     => 'processing',
    'started_at' => current_time( 'mysql' ),  // ❌ Column doesn't exist
]);

// AFTER
$this->job_model->update( $job_id, [
    'status'     => 'processing',
    'updated_at' => current_time( 'mysql' ),  // ✅ Use existing column
]);
```

#### 7. Fix Type Error with file_exists()
`scan_folder()` returns array of file info, not just paths. Need to extract `path` key.

**Changed:** `app/Model/Queue/Media_Sync_Processor.php` lines 260+
```php
// BEFORE
foreach ( $files as $file ) {
    if ( ! file_exists( $file ) ) {  // ❌ $file is array!
        // ...
    }
    Media_Sync::check_duplicate( $file, ... );  // ❌ Wrong!
    Media_Sync::import_file( $file, ... );      // ❌ Wrong!
}

// AFTER
foreach ( $files as $file ) {
    // Extract path from array
    $file_path = is_array( $file ) ? $file['path'] : $file;
    
    if ( ! file_exists( $file_path ) ) {  // ✅ String path
        // ...
    }
    Media_Sync::check_duplicate( $file_path, ... );  // ✅ Correct
    Media_Sync::import_file( $file_path, ... );      // ✅ Correct
}
```

**Why this happened:**
`Media_Sync::scan_folder()` returns:
```php
[
    ['path' => '/full/path/file.jpg', 'name' => 'file.jpg', 'size' => 12345],
    ['path' => '/full/path/file2.jpg', 'name' => 'file2.jpg', 'size' => 67890],
]
```

But processor code expected simple string paths.

#### 8. Fix ArgumentCountError with update_progress()
`Progress_Tracker::update_progress()` expects 5 parameters, but was called with only 2.

**Changed:** `app/Model/Queue/Media_Sync_Processor.php` line 172
```php
// BEFORE
$this->progress_tracker->update_progress( $job_id, $progress );  
// ❌ Only 2 params, needs 5: ($job_id, $total, $processed, $success, $failed)

// AFTER
$this->progress_tracker->update_percentage( $job_id, $new_offset, $total_files );  
// ✅ Correct method with 3 params
```

**Also removed duplicate call:** Line 401
```php
// REMOVED (redundant - progress already set to 100 in job update)
$this->progress_tracker->update_progress( $job_id, 100 );
```

**Available Progress_Tracker methods:**
- `update_progress($job_id, $total, $processed, $success, $failed)` - Full update with all stats
- `update_percentage($job_id, $processed, $total)` - Just update percentage


## Files Modified

1. ✅ `app/Controller/Media_Sync_Controller.php`
   - Line 114: Changed `parameters` to `settings`
   - Line 137: Fixed response data (removed `$file_paths`, fixed `$folder_path`)
   - Line 153: Changed `read()` to `find()`

2. ✅ `app/Model/Queue/Media_Sync_Processor.php`
   - Lines 73, 347: Changed `read()` to `find()`
   - Line 84: **Changed `started_at` to `updated_at`**
   - Lines 95-100: Changed `$parameters` to `$settings`
   - Lines 113-114: Changed array keys to use `settings`
   - Line 119: Changed column name to `settings`
   - Lines 123, 127: Changed variable references
   - Lines 169-170, 175: Changed settings updates
   - **Line 260: Added `$file_path` extraction from file array**
   - **Lines 261-332: Changed all `$file` to `$file_path` (14 occurrences)**
   - **Line 172: Changed `update_progress()` to `update_percentage()` with correct params**
   - **Line 401: Removed redundant `update_progress()` call**

3. ✅ `app/Helper/Database_Migration.php`
   - Added `maybe_add_progress_column()` method
   - Modified `create_tables()` to call migration

4. ✅ `app/Controller/Export_Controller.php`
   - Lines 191, 224, 296: Changed `read()` to `find()`

5. ✅ `app/Controller/Import_Controller.php`
   - Lines 258, 308: Changed `read()` to `find()`

6. ✅ `app/Controller/Job_Controller.php`
   - Line 103: Changed `read()` to `find()`

## Testing Checklist

After applying fixes:

- [x] ✅ No PHP errors in error log
- [x] ✅ Code passes static analysis (no undefined variables)
- [ ] 🔲 Test scan folder functionality
- [ ] 🔲 Test start sync functionality
- [ ] 🔲 Verify progress tracking works
- [ ] 🔲 Check database has `progress` column
- [ ] 🔲 Verify job data stored in `settings` column

## Manual Verification Steps

### 1. Check Database Migration
```sql
-- Check if progress column exists
DESCRIBE wp_aie_jobs;

-- Should show:
-- progress | decimal(5,2) | YES | | 0.00 |
```

### 2. Apply Migration
Option A: Deactivate/Reactivate plugin in WordPress
Option B: Just reload admin page (runs on `admin_init`)
Option C: Manual SQL if needed:
```sql
ALTER TABLE wp_aie_jobs 
ADD COLUMN progress DECIMAL(5,2) DEFAULT 0 
AFTER failed_items;
```

### 3. Test Media Sync
1. Go to Media Sync page
2. Select folder
3. Click "Scan Folder" → Should scan successfully
4. Click "Start Sync" → Should start without errors
5. Watch progress → Should increment from 0 to 100

### 4. Verify Database
```sql
-- Check job record
SELECT id, type, status, settings, progress 
FROM wp_aie_jobs 
WHERE type = 'media_sync' 
ORDER BY id DESC 
LIMIT 1;

-- Settings should contain:
-- {"folder_path":"/path","scan_options":{...},"sync_options":{...},"offset":0}
```

## Related Documentation
- `DATABASE_MIGRATION_FIX.md` - Detailed migration guide
- `MEDIA_SYNC_SCALABILITY.md` - Architecture changes
