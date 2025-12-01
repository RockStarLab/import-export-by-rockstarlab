# Pause/Resume Feature - Media Sync

## Overview

Реализована функциональность паузы и возобновления синхронизации медиа-файлов.

## User Experience

### При нажатии на "Pause":
1. ✅ Заголовок меняется: **"Synchronization in Progress"** → **"Synchronization Paused"**
2. ✅ Иконка меняется: `dashicons-update` (крутящийся) → `dashicons-controls-pause`
3. ✅ Кнопка меняется: **"Pause"** → **"Resume"**
4. ✅ Иконка кнопки меняется: `dashicons-controls-pause` → `dashicons-controls-play`
5. ✅ Остановка мониторинга прогресса (clearInterval)
6. ✅ Статус джоба в БД меняется на `paused`

### При нажатии на "Resume":
1. ✅ Заголовок восстанавливается: **"Synchronization Paused"** → **"Synchronization in Progress"**
2. ✅ Иконка восстанавливается: `dashicons-controls-pause` → `dashicons-update` (крутящийся)
3. ✅ Кнопка восстанавливается: **"Resume"** → **"Pause"**
4. ✅ Иконка кнопки восстанавливается: `dashicons-controls-play` → `dashicons-controls-pause`
5. ✅ Возобновление мониторинга прогресса (startProgressMonitoring)
6. ✅ Статус джоба в БД меняется на `processing`
7. ✅ Задание снова добавляется в очередь для обработки

## Technical Implementation

### Frontend (media_sync.js)

#### New State Variable
```javascript
isPaused: false
```

#### Modified Event Handler
```javascript
$page.on('click', '#aie-pause-sync-btn', (e) => {
    e.preventDefault();
    if (this.isPaused) {
        this.resumeSync();
    } else {
        this.pauseSync();
    }
});
```

#### pauseSync() Method
```javascript
pauseSync() {
    jQuery.ajax({
        url: window.aieData?.ajaxUrl || window.ajaxurl,
        method: 'POST',
        data: {
            action: 'aie_pause_media_sync',
            nonce: window.aieData?.nonce || '',
            job_id: this.jobId,
        },
    }).done((response) => {
        if (response.success) {
            this.isPaused = true;
            clearInterval(this.progressInterval);
            
            // Update UI
            const $header = jQuery('#aie-sync-progress-section .aie-card-header h2');
            $header.html('<span class="dashicons dashicons-controls-pause"></span> Synchronization Paused');
            
            const $pauseBtn = jQuery('#aie-pause-sync-btn');
            $pauseBtn.html('<span class="dashicons dashicons-controls-play"></span> Resume');
            
            Utils.showNotice('Sync paused', 'info');
        }
    });
}
```

#### resumeSync() Method
```javascript
resumeSync() {
    jQuery.ajax({
        url: window.aieData?.ajaxUrl || window.ajaxurl,
        method: 'POST',
        data: {
            action: 'aie_resume_media_sync',
            nonce: window.aieData?.nonce || '',
            job_id: this.jobId,
        },
    }).done((response) => {
        if (response.success) {
            this.isPaused = false;
            
            // Update UI
            const $header = jQuery('#aie-sync-progress-section .aie-card-header h2');
            $header.html('<span class="dashicons dashicons-update aie-spin"></span> Synchronization in Progress');
            
            const $pauseBtn = jQuery('#aie-pause-sync-btn');
            $pauseBtn.html('<span class="dashicons dashicons-controls-pause"></span> Pause');
            
            // Restart progress monitoring
            this.startProgressMonitoring();
            
            Utils.showNotice('Sync resumed', 'success');
        }
    });
}
```

#### resetPage() Updates
```javascript
resetPage() {
    // ... existing code ...
    
    this.isPaused = false;
    
    // Reset pause button to default state
    const $pauseBtn = jQuery('#aie-pause-sync-btn');
    $pauseBtn.html('<span class="dashicons dashicons-controls-pause"></span> Pause');
    
    // Reset header to default state
    const $header = jQuery('#aie-sync-progress-section .aie-card-header h2');
    $header.html('<span class="dashicons dashicons-update aie-spin"></span> Synchronization in Progress');
}
```

### Backend (Media_Sync_Controller.php)

#### New AJAX Action Registration
```php
protected function get_ajax_actions() {
    return [
        'scan_folder'              => ['callback' => 'scan_folder'],
        'start_media_sync'         => ['callback' => 'start_media_sync'],
        'get_sync_progress'        => ['callback' => 'get_sync_progress'],
        'pause_media_sync'         => ['callback' => 'pause_media_sync'],
        'resume_media_sync'        => ['callback' => 'resume_media_sync'], // NEW
        'cancel_media_sync'        => ['callback' => 'cancel_media_sync'],
        'browse_folders'           => ['callback' => 'browse_folders'],
        'process_media_sync_batch' => ['callback' => 'process_media_sync_batch'],
    ];
}
```

#### pause_media_sync() Method
```php
public function pause_media_sync() {
    $verification = $this->verify_request('aie_pause_media_sync');
    if (is_wp_error($verification)) {
        $this->send_error($verification);
    }

    $this->validate_required_params(['job_id']);
    $job_id = (int) $this->get_request_param('job_id');

    $job = new Job();
    $job->update($job_id, ['status' => 'paused']);

    $this->send_success();
}
```

#### resume_media_sync() Method (NEW)
```php
public function resume_media_sync() {
    $verification = $this->verify_request('aie_resume_media_sync');
    if (is_wp_error($verification)) {
        $this->send_error($verification);
    }

    $this->validate_required_params(['job_id']);
    $job_id = (int) $this->get_request_param('job_id');

    $job = new Job();
    $job->update($job_id, ['status' => 'processing']);

    // Resume background processing
    $processor = new \WP_AIE\Model\Queue\Media_Sync_Processor();
    $processor->push_to_queue($job_id);
    $processor->save()->dispatch();

    $this->send_success();
}
```

### Queue Processor (Media_Sync_Processor.php)

#### Pause Status Check
```php
public function process($job_id) {
    try {
        // Get job data
        $job = $this->job_model->find($job_id);

        if (!$job) {
            throw new \Exception(sprintf('Job #%d not found', $job_id));
        }

        // Check if job is paused
        if ('paused' === $job->status) {
            $this->logger->log(
                $job_id,
                'info',
                sprintf('Job #%d is paused, skipping processing', $job_id)
            );
            return array(
                'status'   => 'paused',
                'message'  => 'Job is paused',
            );
        }

        // Continue with processing...
    }
}
```

## Flow Diagram

```
User clicks "Pause"
    ↓
Frontend: pauseSync()
    ↓
AJAX: aie_pause_media_sync
    ↓
Backend: pause_media_sync()
    ↓
Database: status → 'paused'
    ↓
Frontend: Update UI (header + button)
    ↓
Stop progress monitoring

─────────────────────────────────

User clicks "Resume"
    ↓
Frontend: resumeSync()
    ↓
AJAX: aie_resume_media_sync
    ↓
Backend: resume_media_sync()
    ↓
Database: status → 'processing'
    ↓
Queue: push_to_queue() + dispatch()
    ↓
Frontend: Update UI (header + button)
    ↓
Restart progress monitoring
```

## Database Status Values

| Status | Description |
|--------|-------------|
| `pending` | Job created, waiting to start |
| `processing` | Currently being processed |
| `paused` | ⭐ User paused the job |
| `completed` | Successfully finished |
| `failed` | Failed with error |
| `cancelled` | User cancelled the job |

## Files Modified

1. ✅ `src/js/modules/media_sync.js` - Frontend logic
2. ✅ `app/Controller/Media_Sync_Controller.php` - Backend AJAX handlers
3. ✅ `app/Model/Queue/Media_Sync_Processor.php` - Queue processor

## Testing Checklist

- [ ] Start sync → Click Pause → Verify UI changes
- [ ] Check header text changes to "Synchronization Paused"
- [ ] Check button changes to "Resume"
- [ ] Verify progress monitoring stops
- [ ] Click Resume → Verify UI restores
- [ ] Check header text changes back to "Synchronization in Progress"
- [ ] Check button changes back to "Pause"
- [ ] Verify progress monitoring resumes
- [ ] Check database status changes: processing → paused → processing
- [ ] Verify processor skips paused jobs
- [ ] Test multiple pause/resume cycles
- [ ] Test cancel during pause state

## Notes

- Progress is preserved when paused (stored in database)
- Can resume from exact point where paused
- Background processor will skip paused jobs automatically
- UI state resets on page reload (check database status)
- Cancel button still works during pause state

---

**Last Updated:** December 1, 2025
**Feature Status:** ✅ Implemented and Ready for Testing
