# Media Folder Sync - Quick Reference

## 🎯 Что это?

Синхронизация файлов из папок на сервере (FTP) → WordPress Media Library

## ✨ Основные фичи

```
📁 Scan Folders       → Выбор папки + рекурсивное сканирование
🔍 File Filters       → Все типы / Только изображения / Custom
🚫 Duplicate Check    → Hash / Filename / Filesize (3 метода)
📦 Batch Processing   → Пакетная обработка больших объемов
📊 Real-time Progress → Progress bar + статистика
👑 Premium: RML       → Автосоздание папок в Real Media Library
```

## 🚀 Quick Start

1. **Upload files via FTP** → `/wp-content/uploads/my-folder/`
2. **Open Media Sync page** → Dashboard → Media Sync
3. **Select folder** → Browse server or type path
4. **Configure options** → File types, duplicates, structure
5. **Start Sync** → Track progress in real-time

## 📋 Options

| Option | Description | Values |
|--------|-------------|--------|
| Include subfolders | Recursive scan | ☑ / ☐ |
| File types | Filter files | All / Images / Custom |
| Skip duplicates | Avoid re-import | ☑ / ☐ |
| Check method | How to detect | Hash / Filename / Filesize |
| Alt text | From filename | ☑ / ☐ |
| Thumbnails | Generate | ☑ / ☐ |
| Preserve structure | Keep folders | ☑ / ☐ |
| RML folders (Premium) | Auto-create | ☑ / ☐ |

## 🎨 UI Flow

```
┌─────────────────────────────────────────┐
│ Step 1: Select Folder                   │
│ → Browse server or enter path           │
├─────────────────────────────────────────┤
│ Step 2: File Options                    │
│ → Choose file types                     │
├─────────────────────────────────────────┤
│ Step 3: Duplicate Handling              │
│ → Select check method                   │
├─────────────────────────────────────────┤
│ Step 4: Import Options                  │
│ → Alt text, thumbnails, structure       │
│ → 👑 Premium: RML integration           │
├─────────────────────────────────────────┤
│ [Scan Folder]  [Start Sync]             │
└─────────────────────────────────────────┘

         ↓ Start Sync ↓

┌─────────────────────────────────────────┐
│ Syncing...                         [✕]  │
├─────────────────────────────────────────┤
│ Progress: 47 / 247 (19%)                │
│ ████████░░░░░░░░░░░░░░░               │
│                                         │
│ ✓ Success: 45                           │
│ ⊘ Skipped: 2 (duplicates)               │
│ ✗ Failed: 0                             │
│                                         │
│ [Pause] [Cancel]                        │
└─────────────────────────────────────────┘
```

## 🔧 Technical Stack

```
Backend:  app/sync/media_folder_sync.php
Frontend: src/js/modules/media_sync.js
Styles:   src/scss/admin/media_sync.scss
View:     app/view/admin/media_sync_page.php
Database: {prefix}_aie_media_sync table
```

## 🎯 Use Cases

### Case 1: Bulk Upload via FTP
- Upload 1000 photos via FTP
- Use Media Sync to import all
- Result: All in Media Library with thumbnails

### Case 2: Site Migration
- Copy media from old site
- Use Hash method to skip duplicates
- Preserve folder structure
- Result: Clean migration without duplicates

### Case 3: WooCommerce Products (Premium)
```
/products/
  ├── t-shirts/      → RML: T-Shirts
  ├── jeans/         → RML: Jeans
  └── accessories/   → RML: Accessories
```

## 🔒 Security

- ✅ WordPress allowed MIME types only
- ✅ File size validation
- ✅ Read permission check
- ✅ Nonce verification
- ✅ Capability check (manage_options)

## 📊 Stats & Logging

```php
[
    'total' => 247,
    'processed' => 247,
    'success' => 245,      // ✓ Imported
    'skipped' => 2,        // ⊘ Duplicates
    'failed' => 0,         // ✗ Errors
    'size_total' => 23500000,  // Bytes
    'duration' => 125      // Seconds
]
```

## 🔌 API

### REST API
```
POST /wp-json/aie/v1/media-sync/scan
POST /wp-json/aie/v1/media-sync/start
GET  /wp-json/aie/v1/media-sync/progress/{job_id}
```

### AJAX
```javascript
aie_scan_folder        // Scan folder
aie_start_media_sync   // Start sync
aie_get_sync_progress  // Get progress
aie_pause_media_sync   // Pause
aie_cancel_media_sync  // Cancel
```

### Hooks
```php
// Actions
do_action('aie_before_sync_file', $file, $options);
do_action('aie_after_sync_file', $id, $file, $options);
do_action('aie_sync_file_skipped', $file, $reason, $existing_id);
do_action('aie_sync_file_error', $file, $error);

// Filters
apply_filters('aie_media_sync_files', $files, $path, $options);
apply_filters('aie_media_sync_allowed_types', $mime_types);
apply_filters('aie_media_sync_alt_text', $alt, $file);
apply_filters('aie_media_sync_title', $title, $file);
```

## 👑 Premium Features

```php
if (aie_fs()->is_premium() && function_exists('wp_rml_create')) {
    // Create RML folder structure
    // Assign files to RML folders
}
```

**Benefits:**
- Auto-create RML folders matching source structure
- Organize media automatically
- Save hours of manual organization

## 📚 Documentation

- [ARCHITECTURE.md](../ARCHITECTURE.md) - Section 8
- [DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md) - Phase 9.8
- [MEDIA_SYNC_FEATURE.md](../MEDIA_SYNC_FEATURE.md) - Full docs

## 📝 Implementation Status

- [x] Architecture design
- [x] Development plan (Phase 9.8)
- [x] Database schema
- [x] UI/UX design
- [ ] Code implementation
- [ ] Testing
- [ ] Documentation
- [ ] Release

---

**Version:** 1.0.0  
**Phase:** 9.8  
**Status:** 📝 Planning Complete → 🚧 Ready for Development
