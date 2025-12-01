# Quick Test Guide

## Check if Processing Started

### 1. Check Browser Console
Open browser DevTools (F12) → Console tab

Look for:
```
Progress response: {success: true, data: {status: "processing", progress: 20, ...}}
```

### 2. Check PHP Error Log
```bash
tail -f /home/brovatar/Local\ Sites/wp-advanced-import-export/app/public/wp-content/debug.log
```

Should see:
```
[info] Started processing media sync job #123
[info] Scanning folder: /path/to/folder
[info] Found X files in folder
[info] Processing batch: files 1-20 of X
```

### 3. Check Database
```sql
-- Check job status
SELECT id, status, progress, settings 
FROM wp_aie_jobs 
WHERE type = 'media_sync' 
ORDER BY id DESC 
LIMIT 1;

-- Check logs
SELECT * FROM wp_aie_logs 
WHERE job_id = (SELECT MAX(id) FROM wp_aie_jobs WHERE type = 'media_sync')
ORDER BY created_at DESC;
```

### 4. Manual Test
```bash
# SSH into Local site
cd /home/brovatar/Local\ Sites/wp-advanced-import-export/app/public

# Trigger WP Cron manually
php -q wp-cron.php
```

## Common Issues

### Issue: Status stays "pending"
**Solution:** First batch didn't start

Check:
1. Is folder path correct?
2. Are there files in the folder?
3. Check PHP error log for exceptions

### Issue: Progress updates but stays at same percentage
**Solution:** Batches not chaining

Check:
1. `wp_remote_post` might be blocked
2. Try manual cron trigger
3. Check `trigger_next_batch()` is called

### Issue: "Job not found" error
**Solution:** Job creation failed

Check:
1. Database connection
2. `wp_aie_jobs` table exists
3. `progress` column exists

## Enable Debug Mode

Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
```

## Test with Single File

1. Create test folder: `wp-content/uploads/test-media-sync/`
2. Add 1 image file
3. Run scan
4. Run sync
5. Should complete in <5 seconds

## Force Synchronous Processing

Temporary workaround - disable async:

In `Media_Sync_Controller::start_media_sync()`:
```php
// Comment out trigger_next_batch()
// $this->trigger_next_batch( $job_id );

// Instead, process all batches synchronously
while ( ! $result['completed'] ) {
    $result = $processor->process( $job_id );
}
```

**Warning:** This will block the request until completion. Only for debugging!
