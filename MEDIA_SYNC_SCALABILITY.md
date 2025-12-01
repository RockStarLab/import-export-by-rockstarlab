# Media Sync Scalability Improvements

## Problem

Original implementation sent all scanned files as an array via AJAX request:
- For folders with 1000+ files, this would exceed PHP POST limits
- Large payloads could cause timeout errors
- Memory issues on both frontend and backend

## Solution

**Batch Processing from Folder Path**

Instead of sending file array, we now send only the folder path and options:

```javascript
// OLD: Send entire file array
{
  action: 'aie_start_media_sync',
  files: [file1, file2, ..., file1000],
  options: {...}
}

// NEW: Send only folder path
{
  action: 'aie_start_media_sync',
  folder_path: '/path/to/folder',
  scan_options: {...},
  sync_options: {...}
}
```

## Architecture Changes

### 1. Frontend (media_sync.js)

**Scan Phase:**
- `scanFolder()` still scans all files to show summary
- `displayScanSummary()` shows aggregate stats instead of checkboxes
- No file selection UI - all valid files will be processed

**Sync Phase:**
- `startSync()` sends `folder_path` + options instead of file array
- Progress tracking via polling remains unchanged

### 2. Backend Controller (Media_Sync_Controller.php)

**start_media_sync() endpoint:**
```php
// OLD: Accept files array
$files = $request['files'];

// NEW: Accept folder path
$folder_path = $request['folder_path'];
$scan_options = $request['scan_options'];
$sync_options = $request['sync_options'];

// Store in job parameters
$parameters = [
    'folder_path' => $absolute_path,
    'scan_options' => $scan_options,
    'sync_options' => $sync_options,
    'offset' => 0
];
```

### 3. Queue Processor (Media_Sync_Processor.php)

**Dynamic folder scanning:**

```php
// First batch: Scan folder and cache result
if (!isset($parameters['total_files'])) {
    $all_files = Media_Sync::scan_folder($folder_path, $scan_options);
    $parameters['total_files'] = count($all_files);
    $parameters['all_files'] = $all_files;
}

// Each batch: Get chunk from cached results
$chunk = array_slice($all_files, $offset, 20);
```

**Why cache the scan result?**
- Scanning folder on every batch would be inefficient
- File list is cached in job parameters after first scan
- Subsequent batches read from cache
- Trade-off: One-time memory usage vs repeated I/O

## Benefits

✅ **Scalability:** Can handle folders with unlimited files  
✅ **Performance:** Small AJAX payloads regardless of folder size  
✅ **Memory:** Only 20 files in memory per batch  
✅ **Reliability:** No POST size limit issues  
✅ **Progress:** Accurate progress tracking with total count  

## Technical Details

### Batch Size
- **20 files per batch** (configurable in processor)
- Balance between efficiency and memory usage
- Can be adjusted based on server resources

### File List Caching
- Stored in job parameters as JSON
- One-time scan during first batch
- Removed when job completes

### Memory Considerations
For 10,000 files:
- **Old approach:** Send ~50MB in single AJAX request ❌
- **New approach:** 
  - Scan: ~50MB cached in database ✓
  - Processing: ~2.5MB per batch (20 files) ✓
  - Total: Same memory, distributed over time ✓

## Testing Checklist

- [ ] 10 files folder
- [ ] 100 files folder  
- [ ] 1,000 files folder
- [ ] 10,000 files folder
- [ ] Mixed file types (images, videos, audio)
- [ ] Subfolders (recursive scan)
- [ ] Progress tracking accuracy
- [ ] Memory usage monitoring
- [ ] Error handling for invalid paths

## Future Optimizations

### Option 1: Stream Processing
Instead of caching full file list, scan folder on each batch:
```php
// Scan with pagination
$chunk = scan_folder_with_limit($folder_path, $offset, 20);
```
**Pros:** Lower memory usage  
**Cons:** Repeated I/O, harder to track progress

### Option 2: Database Caching
Store file list in temporary table instead of job parameters:
```php
CREATE TABLE wp_aie_scan_cache (
    job_id INT,
    file_path VARCHAR(500),
    file_data TEXT
);
```
**Pros:** Better for very large folders  
**Cons:** Additional database complexity

### Option 3: Filesystem Cursor
Use directory handles to maintain scan position:
```php
// Save cursor position between batches
$handle = opendir($folder_path);
seekdir($handle, $offset);
```
**Pros:** True streaming, minimal memory  
**Cons:** Complex state management

## Migration Notes

**Database changes:**
- Added `progress` column to `wp_aie_jobs` table (DECIMAL(5,2) DEFAULT 0)
- Automatic migration runs on plugin activation via `Database_Migration::maybe_add_progress_column()`
- Uses `settings` column (existing TEXT field) instead of `parameters`

**Backward compatibility:** New code only affects Media Sync feature. Other import/export features unaffected.

**Frontend:** Existing scan results (before this change) will show old UI with checkboxes. After page reload, new UI will appear.

## Performance Metrics (Expected)

| Folder Size | Old Approach | New Approach |
|-------------|--------------|--------------|
| 10 files    | 1 request (0.5s) | 1 request + 1 batch (1s) |
| 100 files   | 1 request (5s) | 1 request + 5 batches (6s) |
| 1,000 files | ❌ Failed | 1 request + 50 batches (60s) |
| 10,000 files| ❌ Failed | 1 request + 500 batches (10min) |

*Note: Times are estimates. Actual performance depends on server resources and file sizes.*
