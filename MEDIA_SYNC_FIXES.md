# Media Sync Critical Fixes - December 2025

## Issues Fixed

### 1. ✅ Start Sync Button Delay Feedback
**Problem:** Button appeared unresponsive during AJAX request  
**Solution:** Added loading indicator with spinner and "Starting..." text  
**Files:**
- `src/js/modules/media_sync.js` (lines 482-488)

### 1.1. ✅ Progress Window Delay
**Problem:** Progress window didn't appear until first batch completed (blocking UI)  
**Solution:** Changed processing to async - return response immediately, start processing in background  
**Implementation:**
- Backend: Removed synchronous first batch processing from `start_media_sync()`
- Backend: Added `trigger_async_processing()` method using non-blocking `wp_remote_post()`
- Frontend: Show progress UI immediately with "Initializing..." status
- Frontend: Start progress polling after 500ms delay to allow async processing to begin
**Files:**
- `app/Controller/Media_Sync_Controller.php` - `start_media_sync()` method
- `src/js/modules/media_sync.js` - `startSync()` method

### 2. ✅ Wrong Upload Directory
**Problem:** Files uploaded to custom `/aie-media-sync/` folder instead of WordPress standard year/month structure  
**Solution:** Use `wp_upload_dir()['path']` for standard WordPress uploads (e.g., `/uploads/2025/12/`)  
**Files:**
- `app/Helper/Media_Sync.php` - `import_file()` method (lines 174-240)

### 3. ✅ Preserve Folder Structure Not Working
**Problem:** Folder structure option ignored, all files imported flat  
**Solution:** Calculate relative path from base folder and recreate in uploads  
**Implementation:**
```php
if ( ! empty( $options['preserve_structure'] ) && ! empty( $options['base_folder'] ) ) {
    $relative_path = str_replace( trailingslashit( $options['base_folder'] ), '', dirname( $file_path ) );
    $dest_dir = $uploads['path'] . '/' . $relative_path;
    wp_mkdir_p( $dest_dir );
}
```
**Files:**
- `app/Helper/Media_Sync.php` - `import_file()` method
- `app/Model/Queue/Media_Sync_Processor.php` - Added `base_folder` to sync options

### 4. ✅ Real Media Library (RML) Integration
**Problem:** Virtual folders not created in RML  
**Solution:** Added RML integration with folder hierarchy creation  
**Implementation:**
- New method: `assign_to_rml_folder()` - Creates virtual folder hierarchy
- New method: `find_rml_folder_by_name()` - Finds existing RML folders
- Uses RML API: `wp_rml_create()` and `wp_rml_move()`
- Only activates when both `rml_integration` and `preserve_structure` are enabled

**Files:**
- `app/Helper/Media_Sync.php` - New private methods (lines 241-290)

### 5. ✅ Copy vs Move Files Option
**Problem:** Always copied files, ignored move option  
**Solution:** Check `move_files` option and use `rename()` instead of `copy()`  
**Files:**
- `app/Helper/Media_Sync.php` - `import_file()` method

### 6. ✅ Thumbnail Generation Option
**Problem:** Always generated thumbnails, ignored option  
**Solution:** Check `skip_thumbnails` option before calling `wp_generate_attachment_metadata()`  
**Note:** UI sends `generate_thumbnails`, processor converts to `skip_thumbnails`  
**Files:**
- `app/Helper/Media_Sync.php` - `import_file()` method
- `app/Model/Queue/Media_Sync_Processor.php` - Option mapping

## Technical Details

### Option Mapping in Processor
UI option names converted to helper option names:
```php
$import_options = $options;
$import_options['skip_thumbnails'] = ! $options['generate_thumbnails'];
$import_options['move_files'] = ! $options['copy_files'];
$import_options['rml_folder_structure'] = ! empty( $options['rml_integration'] ) && ! empty( $options['preserve_structure'] );
```

### Upload Directory Logic
```php
// Standard WordPress structure (2025/12/)
$uploads = wp_upload_dir();
$dest_dir = $uploads['path'];

// With preserve_structure
$relative_path = str_replace( trailingslashit( $base_folder ), '', dirname( $file_path ) );
$dest_dir = $uploads['path'] . '/' . $relative_path;
```

### RML Virtual Folder Hierarchy
```
Source: /external/photos/2024/vacation/beach/image.jpg
Base folder: /external/photos/

RML hierarchy created:
- 2024/
  - vacation/
    - beach/
      - image.jpg (attachment)
```

## Testing Checklist

- [x] Start Sync shows loading indicator
- [ ] Files imported to `/uploads/YYYY/MM/` not `/aie-media-sync/`
- [ ] Preserve structure creates subdirectories
- [ ] RML virtual folders created when enabled
- [ ] Copy/Move option respected
- [ ] Thumbnail generation option respected
- [ ] Stats display during sync
- [ ] All options work together

## Database Schema

No schema changes required. Uses existing columns:
- `progress` - DECIMAL(5,2) for percentage
- `result` - TEXT for JSON stats (processed, success, skipped, failed)
- `settings` - TEXT for job options

## Dependencies

### Required
- WordPress 5.0+
- PHP 7.4+

### Optional
- Real Media Library plugin (for RML integration)

## Notes

- RML integration only works if RML plugin is active
- File operations (copy/move) depend on filesystem permissions
- Thumbnail generation only applies to image files
- Progress updates every 20 files (batch size)
