# Media Sync Processing Flow

## Synchronous vs Asynchronous Processing

### ❌ OLD (Blocking) Flow:
```
User clicks "Start Sync"
    ↓
Frontend AJAX → start_media_sync
    ↓
Backend: Create job
    ↓
Backend: Process first batch (20 files) ⏱️ BLOCKS HERE!
    ↓ (wait 5-30 seconds...)
Backend: Return response
    ↓
Frontend: Show progress UI
    ↓
Start polling progress
```
**Problem:** User sees loading button for 5-30 seconds with no feedback

---

### ✅ NEW (Non-blocking) Flow:
```
User clicks "Start Sync"
    ↓
Frontend AJAX → start_media_sync
    ↓
Backend: Create job
    ↓
Backend: Trigger async processing (non-blocking wp_remote_post)
    ↓
Backend: Return response IMMEDIATELY ⚡
    ↓
Frontend: Show progress UI (0%, "Initializing...")
    ↓
Wait 500ms
    ↓
Start polling progress every 2 seconds
    ↓
    ├─→ Shows real-time stats as batches complete
    └─→ Updates every 2 seconds until done
```
**Benefit:** User sees progress UI instantly, gets feedback within 1 second

---

## Technical Implementation

### Backend Changes

**File:** `app/Controller/Media_Sync_Controller.php`

**Old Code (Blocking):**
```php
// Create job
$job_id = $job->create( $job_data );

// Process first batch synchronously (BLOCKS!)
$processor = new Media_Sync_Processor();
$result = $processor->process( $job_id );

// Return response after processing
$this->send_success([ 'job_id' => $job_id ]);
```

**New Code (Non-blocking):**
```php
// Create job
$job_id = $job->create( $job_data );

// Trigger async processing (non-blocking)
$this->trigger_async_processing( $job_id );

// Return response IMMEDIATELY
$this->send_success([ 'job_id' => $job_id ]);
```

**New Method:**
```php
protected function trigger_async_processing( $job_id ) {
    wp_remote_post(
        admin_url( 'admin-ajax.php' ),
        array(
            'timeout'   => 0.01,        // Very short timeout
            'blocking'  => false,        // Don't wait for response
            'sslverify' => false,
            'body'      => array(
                'action' => 'aie_process_media_sync_batch',
                'nonce'  => wp_create_nonce( 'aie_nonce' ),
                'job_id' => $job_id,
            ),
            'cookies'   => $_COOKIE,
        )
    );
}
```

### Frontend Changes

**File:** `src/js/modules/media_sync.js`

**Old Code:**
```javascript
.done( ( response ) => {
    this.jobId = response.data.job_id;
    jQuery( '#aie-sync-progress-section' ).slideDown();
    this.startProgressTracking(); // Immediate polling
} )
```

**New Code:**
```javascript
.done( ( response ) => {
    this.jobId = response.data.job_id;
    
    // Show progress UI immediately
    jQuery( '#aie-sync-progress-section' ).slideDown();
    jQuery( '#aie-progress-percentage' ).text( '0%' );
    jQuery( '#aie-sync-status' ).text( 'Initializing...' );
    
    // Wait 500ms before polling to let async processing start
    setTimeout( () => {
        this.startProgressTracking();
    }, 500 );
} )
```

---

## Processing Timeline

```
Time | Backend                          | Frontend
-----|----------------------------------|------------------
0ms  | Create job                       | AJAX sent
50ms | Trigger async wp_remote_post     |
100ms| Return response                  | Response received
150ms|                                  | Show progress UI
200ms| ← Async request arrives          | "Initializing..."
250ms| Start processing batch 1         |
650ms|                                  | First poll (0%)
1s   | Batch 1 processing files...      |
2.7s |                                  | Second poll (10%)
3s   | Batch 1 complete (20 files)      |
3.1s | Trigger batch 2                  |
4.7s |                                  | Third poll (25%)
5s   | Batch 2 processing...            |
...  | Continue until all files done    | Poll every 2s
```

---

## Benefits

1. **Instant UI Feedback:** Progress window appears in ~100ms instead of 5-30 seconds
2. **Better UX:** User sees "Initializing..." message immediately
3. **No Blocking:** Server doesn't hold connection during processing
4. **Reliable Progress:** Polling starts after async processing begins
5. **Scalable:** Works the same for 10 files or 10,000 files

---

## Potential Issues & Solutions

### Issue: Async request might fail silently
**Solution:** Backup WP Cron also scheduled:
```php
if ( ! wp_next_scheduled( 'aie_process_queue' ) ) {
    wp_schedule_single_event( time(), 'aie_process_queue' );
}
```

### Issue: Progress polling starts before processing begins
**Solution:** 500ms delay before first poll:
```javascript
setTimeout( () => {
    this.startProgressTracking();
}, 500 );
```

### Issue: User might close browser before completion
**Solution:** All processing happens server-side via async requests. Browser can be closed safely.

---

## Testing

Test the timing:
1. Open browser DevTools → Network tab
2. Click "Start Sync"
3. Measure time from click to progress UI appearing
4. Expected: < 500ms
5. Old behavior: 5-30 seconds

Console logs to verify:
```javascript
console.log( 'Progress response:', response );
console.log( 'Result object:', data.result );
```
