# Media Folder Sync - Architecture Flow

## 📊 System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        MEDIA FOLDER SYNC SYSTEM                      │
└─────────────────────────────────────────────────────────────────────┘

┌───────────────┐      ┌───────────────┐      ┌───────────────┐
│   WordPress   │      │     Admin     │      │   Database    │
│   Cron Job    │◄────►│      UI       │◄────►│   (5 Tables)  │
└───────────────┘      └───────────────┘      └───────────────┘
        │                      │                      │
        │                      ▼                      │
        │          ┌───────────────────────┐          │
        └─────────►│  Media_Folder_Sync    │◄─────────┘
                   │       (Main Class)     │
                   └───────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│  File Scanner │    │   Duplicate   │    │   File        │
│               │    │   Checker     │    │   Importer    │
└───────────────┘    └───────────────┘    └───────────────┘
        │                     │                     │
        │                     ▼                     │
        │            ┌─────────────────┐            │
        │            │ 3 Check Methods │            │
        │            │  • Hash (MD5)   │            │
        │            │  • Filename     │            │
        │            │  • Filesize     │            │
        │            └─────────────────┘            │
        │                                           │
        └──────────────────┬────────────────────────┘
                           ▼
                  ┌─────────────────┐
                  │ WordPress Media │
                  │    Library      │
                  └─────────────────┘
                           │
                           │ (Premium Only)
                           ▼
                  ┌─────────────────┐
                  │ Real Media      │
                  │ Library (RML)   │
                  └─────────────────┘
```

## 🔄 Import Process Flow

```
START: User clicks [Start Sync]
│
├─► Step 1: Create Job Record
│   └─► Insert into aie_jobs table
│       • type = 'media_sync'
│       • status = 'pending'
│       • total_items = file_count
│
├─► Step 2: Initialize Queue
│   └─► Add files to processing queue
│       • Batch size: 50 files
│       • Background processing via WP Cron
│
├─► Step 3: Process Each File
│   │
│   ├─► 3.1: Validate File
│   │   ├─ Check MIME type ──► Not allowed? ──► Skip (log error)
│   │   ├─ Check file size ──► Too large? ───► Skip (log error)
│   │   └─ File readable? ────► No? ─────────► Skip (log error)
│   │
│   ├─► 3.2: Check Duplicate (if enabled)
│   │   │
│   │   ├─ Method: HASH
│   │   │  ├─► Calculate MD5 + filesize
│   │   │  ├─► Search in postmeta
│   │   │  └─► Found? ──► Skip (log duplicate)
│   │   │
│   │   ├─ Method: FILENAME
│   │   │  ├─► Get filename only
│   │   │  ├─► Search in wp_posts
│   │   │  └─► Found? ──► Skip (log duplicate)
│   │   │
│   │   └─ Method: FILESIZE
│   │      ├─► Get size + filename
│   │      ├─► Search combination
│   │      └─► Found? ──► Skip (log duplicate)
│   │
│   ├─► 3.3: Copy to Uploads Directory
│   │   ├─ Preserve structure? ──► Yes ──► Recreate path
│   │   │                       └─► No ───► Flat structure
│   │   └─► wp_upload_bits()
│   │
│   ├─► 3.4: Create Attachment
│   │   ├─► wp_insert_attachment()
│   │   ├─► Set author (current user)
│   │   ├─► Set title (from filename)
│   │   └─► post_status = 'inherit'
│   │
│   ├─► 3.5: Generate Metadata
│   │   ├─ Is image? ──► Yes ──► wp_generate_attachment_metadata()
│   │   │              └─► No ───► Basic metadata only
│   │   └─► update_post_meta()
│   │
│   ├─► 3.6: Set Alt Text (if enabled)
│   │   ├─► Clean filename
│   │   ├─► Remove extension
│   │   ├─► Replace - and _ with spaces
│   │   └─► update_post_meta('_wp_attachment_image_alt')
│   │
│   ├─► 3.7: Premium - RML Integration
│   │   ├─ Is Premium? ──────────► No ──► Skip
│   │   │              └────────► Yes ──┐
│   │   │                               │
│   │   ├─ RML installed? ──────────────┤
│   │   │                               │
│   │   └─► Parse folder path           │
│   │       ├─► Create RML folders      │
│   │       │   (recursive if needed)   │
│   │       └─► wp_rml_move()           │
│   │           (assign to folder)      │
│   │
│   └─► 3.8: Record Result
│       ├─► Insert into aie_media_sync
│       │   • file_path
│       │   • attachment_id
│       │   • status (synced/skipped/failed)
│       │   • file_hash, file_size
│       │
│       └─► Update aie_jobs
│           • processed_items++
│           • success_items++ OR failed_items++
│
└─► Step 4: Complete Job
    ├─► Update aie_jobs
    │   • status = 'completed'
    │   • completed_at = NOW()
    │
    └─► Notify User
        └─► Show final statistics
            • Total: X
            • Success: Y
            • Skipped: Z
            • Failed: W

END
```

## 🗄️ Database Schema Flow

```
┌──────────────────────────────────────────────────────────────────┐
│                      DATABASE RELATIONSHIPS                       │
└──────────────────────────────────────────────────────────────────┘

┌────────────────────┐
│   aie_jobs         │ ← Main job tracking
├────────────────────┤
│ id (PK)            │
│ user_id            │
│ type = 'media_sync'│
│ status             │
│ total_items        │
│ processed_items    │
│ success_items      │
│ failed_items       │
│ created_at         │
│ completed_at       │
└────────────────────┘
         │
         │ 1:N
         ▼
┌────────────────────┐
│ aie_media_sync     │ ← Individual file tracking
├────────────────────┤
│ id (PK)            │
│ job_id (FK) ───────┼──► Points to aie_jobs.id
│ folder_path        │
│ file_path          │
│ attachment_id ─────┼──► Points to wp_posts.ID
│ status             │     (post_type = 'attachment')
│ skip_reason        │
│ file_hash (MD5)    │
│ file_size          │
│ error_message      │
│ created_at         │
└────────────────────┘
         │
         │ 1:N
         ▼
┌────────────────────┐
│   aie_logs         │ ← Detailed logging
├────────────────────┤
│ id (PK)            │
│ job_id (FK) ───────┼──► Points to aie_jobs.id
│ level              │
│ message            │
│ data (JSON)        │
│ created_at         │
└────────────────────┘

┌────────────────────┐
│   wp_posts         │ ← WordPress core
├────────────────────┤
│ ID (PK)            │
│ post_type =        │
│  'attachment'      │
│ post_title         │
│ guid (file URL)    │
└────────────────────┘
         │
         │ 1:N
         ▼
┌────────────────────┐
│   wp_postmeta      │ ← Attachment metadata
├────────────────────┤
│ post_id (FK)       │
│ meta_key           │
│ • _wp_attached_file│
│ • _wp_attachment_  │
│   metadata         │
│ • _wp_attachment_  │
│   image_alt        │
│ meta_value         │
└────────────────────┘

Premium Only:
┌────────────────────┐
│ RML Tables         │ ← Real Media Library
├────────────────────┤
│ realmedialibrary   │ ← Folders
│ realmedialibrary_  │ ← Relations
│   posts            │
└────────────────────┘
```

## 🎨 UI Component Tree

```
┌─────────────────────────────────────────────────────────────┐
│              media_sync_page.php (Main View)                 │
└─────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│ Step 1: Folder│    │ Step 2: Files │    │ Step 3: Dupes │
│               │    │               │    │               │
│ • Path input  │    │ • All types   │    │ • Hash        │
│ • Browse btn  │    │ • Images only │    │ • Filename    │
│ • Recent ▼    │    │ • Custom []   │    │ • Filesize    │
│ • Recursive ☑ │    │               │    │ • Skip ☑      │
└───────────────┘    └───────────────┘    └───────────────┘
        │                     │                     │
        └─────────────────────┼─────────────────────┘
                              ▼
                    ┌───────────────┐
                    │ Step 4: Import│
                    │               │
                    │ • Alt text ☑  │
                    │ • Thumbnails ☑│
                    │ • Structure ☑ │
                    │ ┌───────────┐ │
                    │ │👑 Premium │ │
                    │ │ RML ☑     │ │
                    │ └───────────┘ │
                    └───────────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ▼                   ▼
            [Scan Folder]      [Start Sync]
                    │                   │
                    │                   └──► Opens Progress Modal
                    │                              │
                    └────► Shows Preview           ▼
                            │              ┌───────────────┐
                            │              │ Progress Modal│
                            │              │               │
                            │              │ • Progress %  │
                            │              │ • Stats       │
                            │              │ • Time        │
                            │              │ [Pause] [✕]  │
                            │              └───────────────┘
                            │
                            ▼
                    ┌───────────────┐
                    │ Preview Table │
                    │               │
                    │ File | Size   │
                    │ Status | Path │
                    └───────────────┘
```

## 📡 AJAX/API Communication Flow

```
┌─────────────┐                              ┌──────────────┐
│   Browser   │                              │   Server     │
│ (JavaScript)│                              │    (PHP)     │
└─────────────┘                              └──────────────┘
       │                                              │
       │  1. Scan Folder Request                     │
       ├────────────────────────────────────────────►│
       │  POST aie_scan_folder                       │
       │  {                                          │
       │    folder_path: "/uploads/test",            │
       │    recursive: true,                         │
       │    file_types: ["jpg","png"]                │
       │  }                                          │
       │                                             │
       │              ┌─────────────────────┐        │
       │              │ Media_Folder_Sync   │        │
       │              │ ::scan_folder()     │        │
       │              └─────────────────────┘        │
       │                                             │
       │  2. Scan Results                            │
       │◄────────────────────────────────────────────┤
       │  {                                          │
       │    success: true,                           │
       │    files: [...],                            │
       │    total: 247,                              │
       │    size: 23500000                           │
       │  }                                          │
       │                                             │
       │  3. Start Sync Request                      │
       ├────────────────────────────────────────────►│
       │  POST aie_start_media_sync                  │
       │  {                                          │
       │    files: [...],                            │
       │    options: {...}                           │
       │  }                                          │
       │                                             │
       │              ┌─────────────────────┐        │
       │              │ Create job record   │        │
       │              │ Initialize queue    │        │
       │              │ Start background    │        │
       │              └─────────────────────┘        │
       │                                             │
       │  4. Job Created Response                    │
       │◄────────────────────────────────────────────┤
       │  {                                          │
       │    success: true,                           │
       │    job_id: 123                              │
       │  }                                          │
       │                                             │
       │  5. Progress Polling (every 1s)             │
       ├────────────────────────────────────────────►│
       │  GET aie_get_sync_progress                  │
       │  { job_id: 123 }                            │
       │                                             │
       │              ┌─────────────────────┐        │
       │              │ Query aie_jobs      │        │
       │              │ Get current status  │        │
       │              └─────────────────────┘        │
       │                                             │
       │  6. Progress Update                         │
       │◄────────────────────────────────────────────┤
       │  {                                          │
       │    success: true,                           │
       │    progress: {                              │
       │      total: 247,                            │
       │      processed: 47,                         │
       │      success: 45,                           │
       │      skipped: 2,                            │
       │      failed: 0,                             │
       │      status: "processing"                   │
       │    }                                        │
       │  }                                          │
       │                                             │
       │  ... Repeat polling until complete ...      │
       │                                             │
       │  7. Final Status                            │
       │◄────────────────────────────────────────────┤
       │  {                                          │
       │    progress: {                              │
       │      status: "completed",                   │
       │      total: 247,                            │
       │      processed: 247,                        │
       │      success: 245,                          │
       │      skipped: 2,                            │
       │      failed: 0                              │
       │    }                                        │
       │  }                                          │
       │                                             │
       └─────────────────────────────────────────────┘

Background Process (WordPress Cron):
       ┌──────────────────────────────────┐
       │  WP Cron Job                     │
       │  (runs every minute)             │
       │                                  │
       │  ┌────────────────────────────┐  │
       │  │ Get pending jobs           │  │
       │  │ Process batch (50 files)   │  │
       │  │ Update progress            │  │
       │  │ Repeat until complete      │  │
       │  └────────────────────────────┘  │
       └──────────────────────────────────┘
```

## 🔐 Security Layer

```
┌───────────────────────────────────────────────────────────┐
│                    SECURITY CHECKPOINTS                    │
└───────────────────────────────────────────────────────────┘

User Request
     │
     ├─► 1. Nonce Verification
     │   └─► check_ajax_referer('aie_nonce')
     │
     ├─► 2. Capability Check
     │   └─► current_user_can('manage_options')
     │
     ├─► 3. File Path Validation
     │   ├─► Path exists?
     │   ├─► Within allowed directory?
     │   └─► No directory traversal (..)?
     │
     ├─► 4. MIME Type Check
     │   ├─► Get file MIME type
     │   ├─► Check against allowed list
     │   └─► get_allowed_mime_types()
     │
     ├─► 5. File Size Check
     │   ├─► Check filesize()
     │   ├─► Compare to upload_max_filesize
     │   └─► Compare to post_max_size
     │
     ├─► 6. Read Permission Check
     │   └─► is_readable($file_path)
     │
     ├─► 7. Sanitization
     │   ├─► sanitize_text_field()
     │   ├─► wp_normalize_path()
     │   └─► wp_check_filetype()
     │
     └─► 8. Rate Limiting
         ├─► Check concurrent jobs
         └─► Limit: 1 active sync per user

✅ All Checks Passed → Proceed with Import
❌ Any Check Failed → Return WP_Error
```

## 📊 Performance Optimization

```
┌───────────────────────────────────────────────────────────┐
│                  PERFORMANCE STRATEGIES                    │
└───────────────────────────────────────────────────────────┘

1. BATCH PROCESSING
   ┌────────────────┐
   │ 247 files      │
   └────────────────┘
          │
          ├─► Batch 1 (50 files) ─────► Process
          ├─► Batch 2 (50 files) ─────► Process
          ├─► Batch 3 (50 files) ─────► Process
          ├─► Batch 4 (50 files) ─────► Process
          └─► Batch 5 (47 files) ─────► Process

2. CACHING
   ┌────────────────────────────────┐
   │ Duplicate Check Cache          │
   │ • Store MD5 hashes in memory   │
   │ • Avoid repeated DB queries    │
   │ • Clear after batch            │
   └────────────────────────────────┘

3. MEMORY MANAGEMENT
   ┌────────────────────────────────┐
   │ • Process one file at a time   │
   │ • Unset variables after use    │
   │ • wp_suspend_cache_addition()  │
   │ • Stop WordPress autoload      │
   └────────────────────────────────┘

4. DATABASE OPTIMIZATION
   ┌────────────────────────────────┐
   │ • Bulk inserts where possible  │
   │ • Indexed columns for searches │
   │ • Prepared statements          │
   │ • Transaction for batch        │
   └────────────────────────────────┘

5. BACKGROUND PROCESSING
   ┌────────────────────────────────┐
   │ WordPress Cron                 │
   │ • Non-blocking execution       │
   │ • Resume after server restart  │
   │ • Timeout protection           │
   └────────────────────────────────┘
```

---

**Last Updated:** 2025-11-27  
**Version:** 1.0.0  
**Phase:** 9.8 - Planning Complete
