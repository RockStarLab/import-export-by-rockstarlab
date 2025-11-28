# 📚 WP Advanced Import Export - Documentation Index

Полная документация плагина для импорта и экспорта данных WordPress.

---

## 🎯 Начало работы

### Для пользователей
- **[readme.txt](../readme.txt)** - Описание плагина для Wo**Implementation Estimate:** ~80 hours (5-6 weeks)

---

### 📤 Export System

Расширенная система экспорта с 5-шаговым визардом и advanced filtering

**Main Documentation:**
- **[EXPORT_UI_SPECIFICATION.md](../EXPORT_UI_SPECIFICATION.md)** - Полная спецификация UI (~1200 строк)

**Quick Reference:**
- **[EXPORT_UI_SUMMARY.md](./EXPORT_UI_SUMMARY.md)** - Обзор и приоритеты реализации
- **[EXPORT_ARCHITECTURE_UPDATE_SUMMARY.md](./EXPORT_ARCHITECTURE_UPDATE_SUMMARY.md)** - Обновление архитектуры

**Implementation:**
- **Architecture:** `ARCHITECTURE.md` Section 11 ← NEW
- **Development Plan:** `DEVELOPMENT_PLAN.md` Phase 5 ← NEW

**Key Features:**
- **5-Step Wizard:**
  1. Content Type - Posts/Users/Products/Comments/Taxonomies/Menus/Media
  2. Filters - Advanced query builder с meta queries и tax queries
  3. Field Selection - Drag & Drop field selection с реорганизацией
  4. Export Options - Format (CSV/JSON/XLS/XLSX), field settings, transformations
  5. Progress - Real-time tracking с pause/cancel

- **Content Types Supported:**
  - WordPress: Posts, Pages, CPT, Users, Comments
  - Taxonomies: Categories, Tags, Custom Taxonomies
  - Navigation: Menus (с hierarchy)
  - Media: Attachments (с filters)
  - WooCommerce: Products (с variations), Orders, Coupons, Attributes

- **Advanced Filtering:**
  - **Posts:** Status, Date Range, Author, Categories, Tags, Meta Queries
  - **Products:** Type, Stock Status, Price Range, Categories, Attributes, SKU
  - **Users:** Roles, Registration Date, Meta Queries
  - **Comments:** Status, Post ID, Date Range, User ID
  - **Meta Query Builder:** AND/OR, All operators (=, !=, >, <, LIKE, BETWEEN, EXISTS), Type casting

- **Field Support:**
  - **WordPress Core:** All standard fields
  - **ACF Pro:** All field types (Repeater, Flexible, Gallery, Relationship)
  - **Yoast SEO:** 6 meta fields (Title, Description, Focus Keyword, Canonical, Noindex, Nofollow)
  - **WooCommerce:** 30+ product fields, Order fields, Coupon fields

- **Field Transformation:**
  - **Search & Replace:** Plain text (case-sensitive/insensitive) + Regex, unlimited rules
  - **Custom Functions:** Integration с Custom_Function_Manager
  - **Default Values:** For empty fields
  - **Live Preview:** First 3 transformed values
  - **Drag & Drop:** Reorder fields (Sortable.js)

- **Export Formats:**
  - **CSV:** UTF-8 BOM for Excel, custom delimiter/enclosure
  - **JSON:** Pretty print, optional metadata wrapper
  - **XLS:** Legacy format (Office 97-2003)
  - **XLSX:** Modern format (Office 2007+), auto-width, freeze headers

- **Background Processing:**
  - Queue Manager integration
  - 50 items per batch (configurable)
  - Real-time progress tracking
  - Pause/Cancel support
  - Error handling and retry logic

- **Templates & History:**
  - Save/Load export configurations
  - Export History: Download, Preview, Rerun, Delete
  - View Logs for errors
  - Auto-cleanup: Delete files >7 days old

**Technical Details:**
- **Backend:** `app/controller/export_wizard_controller.php`
- **Exporters:** 8 classes (Post, User, Product, Order, Coupon, Comment, Taxonomy, Menu, Media)
- **Format Writers:** 3 classes (CSV_Writer, JSON_Writer, Excel_Writer)
- **Progress:** `app/export/export_progress_tracker.php`
- **Frontend:** `src/js/modules/export_wizard.js`, `field_selector.js`, `export_progress.js`, `field_settings_modal.js`
- **Views:** `app/view/export/*.php` (8 files: wizard + 5 steps + history + templates)
- **REST API:** `/wp-json/aie/v1/export/*` (12+ endpoints)
- **Database:** Uses `aie_jobs` + new table `aie_export_templates`

**Implementation Estimate:** ~65-70 hours (4-5 weeks)

**Comparison с Import:**
| Feature | Import | Export |
|---------|--------|--------|
| Steps | 7 | 5 |
| Hours | 80h | 65-70h |
| Content Types | 8+ | 10+ |
| Filtering | Basic | Advanced (Meta Queries) |
| Duplicate Handling | Yes | No (N/A) |
| Image Download | Yes | No (URLs) |
| Field Transformation | Yes | Yes |
| Templates | Yes | Yes |

---

## 📑 Quick Reference Cards

### Import UI System
- **[IMPORT_UI_SUMMARY.md](./IMPORT_UI_SUMMARY.md)** - Обзор и 7 приоритетов реализации
- **[ARCHITECTURE_UPDATE_SUMMARY.md](./ARCHITECTURE_UPDATE_SUMMARY.md)** - Детали обновления архитектуры

### Export System
- **[EXPORT_UI_SUMMARY.md](./EXPORT_UI_SUMMARY.md)** - Обзор и 9 приоритетов реализации ← NEW
- **[EXPORT_ARCHITECTURE_UPDATE_SUMMARY.md](./EXPORT_ARCHITECTURE_UPDATE_SUMMARY.md)** - Детали обновления архитектуры ← NEW
g
- **[NEW_FEATURES_SUMMARY.md](./NEW_FEATURES_SUMMARY.md)** - Обзор новых фич

### Для разработчиков
- **[QUICK_START.md](./QUICK_START.md)** - 🚀 Быстрый старт с командами
- **[copilot-instructions.md](../copilot-instructions.md)** - Правила кодирования для AI
- **[ARCHITECTURE.md](../ARCHITECTURE.md)** - Полная архитектура проекта (включает Section 10: Import System)
- **[DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)** - Поэтапный план разработки (включает Phase 4: Import UI)
- **[IMPORT_UI_SPECIFICATION.md](../IMPORT_UI_SPECIFICATION.md)** - 📥 Спецификация UI импорта (~850 строк)
- **[ARCHITECTURE_UPDATE_SUMMARY.md](./ARCHITECTURE_UPDATE_SUMMARY.md)** - 🆕 Обновление архитектуры (Section 10 + Phase 4)

---

## 📖 Core Documentation

### 🏗️ Архитектура
**[ARCHITECTURE.md](../ARCHITECTURE.md)** - Полная техническая спецификация
```
• Паттерны проектирования (Strategy, Factory, Observer, Chain of Responsibility)
• Структура компонентов и директорий
• Технический стек (PHP 7.4+, WordPress 5.8+, MySQL 5.6+)
• Database Schema (8 custom tables) ← UPDATED
• API и хуки (REST API + Action/Filter hooks)
• Система безопасности
• Custom Functions System (Section 6)
• Function Snippets Library (Section 7)
• Media Folder Sync (Section 8)
• Site-to-Site Content Sync (Section 9)
• Import System (Section 10) ← NEW
```

### 📋 План разработки
**[DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)** - 14+ фаз разработки
```
Phase 0:  Database и структура (8 tables) ← UPDATED
Phase 1:  Helper классы
Phase 2:  Format handlers (CSV, JSON, XML, XLS, XLSX)
Phase 3:  Validation system
Phase 4:  Import UI System (7-step wizard) ← NEW
Phase 5:  Export system
Phase 6:  Background processing
Phase 7:  Admin UI
Phase 8:  AJAX & REST API
Phase 9:  Advanced features
  • 9.7: Custom Functions System + Snippets Library
  • 9.8: Media Folder Sync
  • 9.9: Site-to-Site Content Sync
Phase 10: WooCommerce Integration
Phase 11: ACF Integration
Phase 12: WP-CLI Commands
Phase 13: Testing
Phase 14: Release preparation
```

### ⚙️ Правила кодирования
**[copilot-instructions.md](../copilot-instructions.md)** - Инструкции для AI
```
• Naming Conventions (WordPress snake_case)
• PHP Guidelines (PSR-4, SOLID principles)
• SCSS Guidelines (desktop-first, BEM-like)
• JavaScript Guidelines (ES6+, modules)
• Project Structure
• Important Documentation Links
```

---

## 🔧 Feature Documentation

### 🔐 Custom Functions System
**Location:** `ARCHITECTURE.md` Section 6

Система пользовательских PHP функций для трансформации данных.

**Related files:**
- **[CUSTOM_FUNCTIONS_EXAMPLES.md](../CUSTOM_FUNCTIONS_EXAMPLES.md)** - 50+ примеров функций
  ```
  • String Operations (10 примеров)
  • Date & Time Operations (5 примеров)
  • Numeric Operations (7 примеров)
  • HTML Operations (5 примеров)
  • Email & URL Operations (5 примеров)
  • WordPress-specific (7 примеров)
  • Advanced Transformations (11 примеров)
  ```

**Key Components:**
- `Function_Executor` - Безопасное выполнение функций
- `Custom_Functions_Manager` - CRUD операции
- `Function_Snippets` - Библиотека готовых примеров
- Database: `aie_custom_functions` table

---

### 📁 Media Folder Sync

Синхронизация папок с сервера (FTP uploads) → WordPress Media Library

**Main Documentation:**
- **[MEDIA_SYNC_FEATURE.md](../MEDIA_SYNC_FEATURE.md)** - Полная документация функции

**Quick Reference:**
- **[MEDIA_SYNC_CARD.md](./MEDIA_SYNC_CARD.md)** - Быстрый обзор
- **[MEDIA_SYNC_SUMMARY.md](./MEDIA_SYNC_SUMMARY.md)** - Техническое резюме
- **[MEDIA_SYNC_FLOW.md](./MEDIA_SYNC_FLOW.md)** - Диаграммы процессов

**Implementation:**
- **[PHASE_9.8_CHECKLIST.md](./PHASE_9.8_CHECKLIST.md)** - Чеклист разработки
- **Architecture:** `ARCHITECTURE.md` Section 8
- **Development Plan:** `DEVELOPMENT_PLAN.md` Phase 9.8

**Key Features:**
- Сканирование серверных папок (FTP uploads)
- 3 метода определения дубликатов (Hash, Filename, Filesize)
- Batch processing (50 файлов/пакет)
- Premium: Real Media Library integration
- REST API: `/wp-json/aie/v1/media-sync/*`
- Database: `aie_media_sync` table

---

### 🔄 Site-to-Site Content Sync

Синхронизация контента между двумя WordPress сайтами по API

**Main Documentation:**
- **[CONTENT_SYNC_FEATURE.md](../CONTENT_SYNC_FEATURE.md)** - Полная документация функции

**Quick Reference:**
- **[CONTENT_SYNC_CARD.md](./CONTENT_SYNC_CARD.md)** - Быстрый обзор с диаграммами

**Implementation:**
- **[PHASE_9.9_CHECKLIST.md](./PHASE_9.9_CHECKLIST.md)** - Чеклист разработки
- **Architecture:** `ARCHITECTURE.md` Section 9
- **Development Plan:** `DEVELOPMENT_PLAN.md` Phase 9.9

**Key Features:**
- API key authentication (64-character secure keys)
- Bidirectional sync (Pull/Push)
- All content types: Posts, Pages, Users, Media, Terms, Comments
- Selective sync with filters (ID, date, author, status)
- Conflict resolution (Skip, Update, Duplicate)
- Media file synchronization
- Background processing for large operations
- Security: Rate limiting, IP whitelisting
- REST API: `/wp-json/aie/v1/site-sync/*`
- Database: 3 tables (`aie_site_connections`, `aie_content_sync`, `aie_api_keys`)

**Usage Scenarios:**
1. Development → Production (Push)
2. Production → Staging (Pull)
3. Bidirectional sync between active sites
4. Multi-language sites sync

---

### � Import UI System

Расширенная система импорта с 7-шаговым визардом и drag & drop интерфейсом

**Main Documentation:**
- **[IMPORT_UI_SPECIFICATION.md](../IMPORT_UI_SPECIFICATION.md)** - Полная спецификация UI (~850 строк)

**Quick Reference:**
- **[IMPORT_UI_SUMMARY.md](./IMPORT_UI_SUMMARY.md)** - Обзор и приоритеты реализации
- **[ARCHITECTURE_UPDATE_SUMMARY.md](./ARCHITECTURE_UPDATE_SUMMARY.md)** - Обновление архитектуры

**Implementation:**
- **Architecture:** `ARCHITECTURE.md` Section 10
- **Development Plan:** `DEVELOPMENT_PLAN.md` Phase 4

**Key Features:**
- **7-Step Wizard:**
  1. Upload - File upload (CSV/JSON/XLS/XLSX) с drag-drop
  2. Content Type - Posts/Pages/Users/WooCommerce/Custom Tables
  3. Column Selection - Drag & Drop interface с live preview
  4. Field Mapping - Маппинг на WordPress/ACF/WooCommerce поля
  5. Import Options - Duplicate handling, update strategies
  6. Progress - Real-time tracking с pause/resume/cancel
  7. Complete - Результаты, save template, email notification

- **Advanced Field Mapping:**
  - ALL WordPress Core fields
  - ALL ACF fields (включая Repeater - 3 метода импорта)
  - ALL WooCommerce fields
  - Custom MySQL table columns

- **Per-Field Settings Modal:**
  - Default value
  - Search/Replace rules (unlimited)
  - Custom function execution
  - Data transformations
  - Live preview

- **Duplicate Handling:**
  - Detection: By Title, ID, Custom Field (SKU, external_id)
  - Actions: Skip / Update / Delete+Recreate / Create Duplicate
  - Update Strategies: Replace all / Update mapped / Don't update if value exists

- **Image Auto-Download:**
  - URL → Media Library
  - Duplicate detection by hash
  - Alt text assignment

- **Custom MySQL Table Import:**
  - Direct INSERT/UPDATE
  - Table column mapping
  - Duplicate detection

- **Background Processing:**
  - 50 items per batch
  - Queue Manager integration
  - Real-time progress (AJAX)

**Technical Details:**
- **Backend:** `app/controller/import_wizard_controller.php`
- **Importers:** `app/importer/post_importer.php` (+ User, Product, Custom_Table, etc.)
- **Frontend:** `src/js/modules/import_wizard.js`
- **Views:** `app/view/import/step_*.php` (7 steps)
- **REST API:** `/wp-json/aie/v1/import/*` (10+ endpoints)
- **Database:** Uses existing tables (`aie_jobs`, `aie_logs`, `aie_field_maps`)

**Implementation Estimate:** ~80 hours (5-6 weeks)

---

## �📑 Quick Reference Cards

### Import UI System
- **[IMPORT_UI_SUMMARY.md](./IMPORT_UI_SUMMARY.md)** - Обзор и 7 приоритетов реализации
- **[ARCHITECTURE_UPDATE_SUMMARY.md](./ARCHITECTURE_UPDATE_SUMMARY.md)** - Детали обновления архитектуры

### Media Folder Sync
- **[MEDIA_SYNC_CARD.md](./MEDIA_SYNC_CARD.md)** - Краткая карточка функции
- **[MEDIA_SYNC_SUMMARY.md](./MEDIA_SYNC_SUMMARY.md)** - Быстрый справочник
- **[MEDIA_SYNC_FLOW.md](./MEDIA_SYNC_FLOW.md)** - Архитектура и диаграммы

### Site-to-Site Content Sync
- **[CONTENT_SYNC_CARD.md](./CONTENT_SYNC_CARD.md)** - Краткая карточка с диаграммами Pull/Push

---

## 🛠️ Implementation Checklists

### Phase 9.8: Media Folder Sync
**[PHASE_9.8_CHECKLIST.md](./PHASE_9.8_CHECKLIST.md)** - Чеклист реализации
```
✅ Media_Folder_Sync class
✅ 3 duplicate detection methods
✅ Admin UI (4-step wizard)
✅ JavaScript module (media_sync.js)
✅ SCSS styling
✅ REST API endpoints (4 endpoints)
✅ Database table (aie_media_sync)
✅ Premium: RML integration
✅ Testing & Documentation

Estimated Time: 18 hours
Status: ⏳ Pending
```

### Phase 9.9: Site-to-Site Content Sync
**[PHASE_9.9_CHECKLIST.md](./PHASE_9.9_CHECKLIST.md)** - Чеклист реализации
```
✅ Site_Connection_Manager class
✅ Content_Sync_Manager class
✅ Site_Sync_API class
✅ Admin UI (connections + modals)
✅ JavaScript module (content_sync.js)
✅ SCSS styling
✅ REST API endpoints (4 endpoints)
✅ Database tables (3 tables)
✅ API Keys management
✅ Security (rate limiting, IP whitelist)
✅ Background processing
✅ Testing & Documentation

Estimated Time: 33 hours
Status: ⏳ Pending
```

---

#### Quick Access
- **[MEDIA_SYNC_CARD.md](./MEDIA_SYNC_CARD.md)** - Краткая карточка функции
- **[MEDIA_SYNC_FEATURE.md](../MEDIA_SYNC_FEATURE.md)** - Полная документация
- **[MEDIA_SYNC_SUMMARY.md](./MEDIA_SYNC_SUMMARY.md)** - Быстрый справочник
- **[MEDIA_SYNC_FLOW.md](./MEDIA_SYNC_FLOW.md)** - Архитектура и диаграммы
- **[PHASE_9.8_CHECKLIST.md](./PHASE_9.8_CHECKLIST.md)** - Чек-лист реализации

#### Основные фичи
```
✅ Сканирование папок (рекурсивное)
✅ Фильтры файлов (все / изображения / custom)
✅ Проверка дубликатов (3 метода: Hash, Filename, Filesize)
✅ Пакетная обработка (Background processing)
✅ Progress tracking (Real-time)
✅ Alt text generation
✅ Thumbnail generation
✅ Preserve folder structure
👑 Premium: Real Media Library integration
```

#### Technical Details
- **Backend:** `app/sync/media_folder_sync.php`
- **Frontend:** `src/js/modules/media_sync.js`
- **View:** `app/view/admin/media_sync_page.php`
- **Styles:** `src/scss/admin/media_sync.scss`
- **Database:** `aie_media_sync` table

#### API Endpoints
```
POST /wp-json/aie/v1/media-sync/scan
POST /wp-json/aie/v1/media-sync/start
GET  /wp-json/aie/v1/media-sync/progress/{job_id}
GET  /wp-json/aie/v1/media-sync/check-duplicate
```

#### Use Cases
1. **Массовая загрузка** - 1000+ фото через FTP → один клик импорт
2. **Миграция сайта** - Перенос медиа без дубликатов
3. **WooCommerce** - Организация product images по категориям (Premium + RML)

---

## 🗂️ Database Schema

### Tables (5 total)

1. **`aie_jobs`** - История импорта/экспорта
   ```sql
   • id, user_id, type, data_type, file_format
   • status, total_items, processed_items
   • success_items, failed_items
   • file_path, settings, timestamps
   ```

2. **`aie_logs`** - Логи выполнения
   ```sql
   • id, job_id, level, message, data
   • created_at
   ```

3. **`aie_field_maps`** - Сохраненные маппинги
   ```sql
   • id, name, data_type, mapping_data
   • user_id, timestamps
   ```

4. **`aie_custom_functions`** - Пользовательские функции
   ```sql
   • id, name, description, function_code
   • source (custom/library/imported)
   • input_type, output_type, is_active
   • user_id, usage_count, timestamps
   ```

5. **`aie_media_sync`** - Синхронизация медиа
   ```sql
   • id, job_id, folder_path, file_path
   • attachment_id, status, skip_reason
   • file_hash, file_size, error_message
   • created_at
   ```

Full SQL schemas: **[ARCHITECTURE.md](../ARCHITECTURE.md)** (Database section)

---

## 🎨 UI/UX Documentation

### Admin Pages

1. **Import Page** - `app/view/admin/import_page.php`
   - File upload
   - Data type selection
   - Field mapping interface
   - Preview and validation

2. **Export Page** - `app/view/admin/export_page.php`
   - Data type selection
   - Filters configuration
   - Format selection (CSV/JSON/XML)
   - Download

3. **Functions Page** - `app/view/admin/functions_page.php`
   - Custom functions table
   - Function editor modal
   - Snippets library browser
   - Test functionality

4. **Media Sync Page** - `app/view/admin/media_sync_page.php`
   - Folder selection
   - File options
   - Duplicate handling
   - Import options
   - Progress modal

5. **History Page** - `app/view/admin/history_page.php`
   - Jobs list
   - Logs viewer
   - Statistics

6. **Settings Page** - `app/view/admin/settings_page.php`
   - General settings
   - Default options
   - Advanced configuration

---

## 🔌 API Reference

### REST API

**Base URL:** `/wp-json/aie/v1/`

#### Import Endpoints
```
POST   /import/upload         - Upload file
POST   /import/validate       - Validate data
POST   /import/start          - Start import
GET    /import/progress/{id}  - Get progress
GET    /import/logs/{id}      - Get logs
```

#### Export Endpoints
```
POST   /export/start          - Start export
GET    /export/progress/{id}  - Get progress
GET    /export/download/{id}  - Download file
```

#### Media Sync Endpoints
```
POST   /media-sync/scan              - Scan folder
POST   /media-sync/start             - Start sync
GET    /media-sync/progress/{id}     - Get progress
GET    /media-sync/check-duplicate   - Check if file exists
```

#### Jobs Endpoints
```
GET    /jobs                  - List all jobs
GET    /jobs/{id}             - Get single job
DELETE /jobs/{id}             - Delete job
```

Full API documentation: **[ARCHITECTURE.md](../ARCHITECTURE.md)** (API section)

---

## 🪝 Hooks Reference

### Action Hooks

#### Import/Export
```php
do_action('aie_before_import', $jobId, $dataType);
do_action('aie_import_row', $rowData, $rowIndex);
do_action('aie_after_import_row', $itemId, $rowData);
do_action('aie_import_complete', $jobId, $stats);

do_action('aie_before_export', $jobId, $dataType);
do_action('aie_export_row', $itemId, $rowData);
do_action('aie_export_complete', $jobId, $filePath);
```

#### Media Sync
```php
do_action('aie_before_sync_file', $file_path, $options);
do_action('aie_after_sync_file', $attachment_id, $file_path, $options);
do_action('aie_sync_file_skipped', $file_path, $reason, $existing_id);
do_action('aie_sync_file_error', $file_path, $error);
```

#### Custom Functions
```php
do_action('aie_before_function_execute', $function_id, $value);
do_action('aie_after_function_execute', $function_id, $result);
do_action('aie_function_error', $function_id, $error);
```

### Filter Hooks

#### Import/Export
```php
apply_filters('aie_import_data', $data, $dataType);
apply_filters('aie_field_mapping', $mapping, $dataType);
apply_filters('aie_validate_row', $isValid, $rowData);
apply_filters('aie_export_data', $data, $dataType);
```

#### Media Sync
```php
apply_filters('aie_media_sync_files', $files, $folder_path, $options);
apply_filters('aie_media_sync_allowed_types', $mime_types);
apply_filters('aie_media_sync_alt_text', $alt_text, $file_path);
apply_filters('aie_media_sync_title', $title, $file_path);
```

#### Custom Functions
```php
apply_filters('aie_function_whitelist', $allowed_functions);
apply_filters('aie_function_blacklist', $blocked_functions);
apply_filters('aie_snippet_categories', $categories);
apply_filters('aie_function_snippets', $snippets);
```

Full hooks documentation: **[ARCHITECTURE.md](../ARCHITECTURE.md)** (Hooks section)

---

## 🧪 Testing

### Unit Tests
- Location: `tests/unit/`
- Framework: PHPUnit
- Coverage: Helper classes, Models, Format handlers

### Integration Tests
- Location: `tests/integration/`
- Coverage: Complete workflows, Background processing

### Manual Testing
- Checklists in each Phase
- Test data in `tests/fixtures/`

Testing guides: **[DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)** (Phase 13)

---

## 🚀 Development Workflow

### 1. Setup
```bash
npm install
composer install (if needed)
```

### 2. Development
```bash
npm run watch    # Watch and compile assets
npm run dev      # Development build
```

### 3. Build
```bash
npm run build    # Production build
```

### 4. Phase Implementation
Follow **[DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)** phase by phase:
- Complete tasks in order
- Test after each task
- Update checklist
- Move to next phase

---

## 👑 Premium Features

### Freemius Integration
- Free version: Core functionality
- Premium version: Advanced features

### Premium Features List
```
✅ Real Media Library integration
✅ Advanced scheduler (recurring imports)
✅ WooCommerce full support
✅ ACF integration
✅ Priority support
✅ No branding
```

### Checking Premium Status
```php
if (aie_fs()->is_premium()) {
    // Premium features
}
```

---

## 📝 Changelog

### Version 1.0.0 (In Development)
- Initial release
- Core import/export (CSV, JSON, XML)
- Background processing
- Field mapping interface
- Custom Functions System
- Function Snippets Library (50+ examples)
- Media Folder Sync
- Site-to-Site Content Sync
- Premium: Real Media Library integration
- Premium: Scheduled Sync
- REST API
- WP-CLI commands (Phase 12)

---

## 🤝 Contributing

### Code Style
Follow **[copilot-instructions.md](../copilot-instructions.md)**:
- WordPress snake_case naming
- PSR-4 autoloading
- SOLID principles
- Comprehensive comments

### Git Workflow
```
main
  └─ develop
      └─ feature/phase-X
```

### Pull Request Process
1. Follow coding standards
2. Add tests
3. Update documentation
4. Reference issue/phase number

---

## 📞 Support

- **Documentation:** This index + linked files
- **Issues:** GitHub Issues (private repo)
- **Free Support:** WordPress.org forum (after release)
- **Premium Support:** Dedicated support portal

---

## 📄 License

GPL v2 or later - see [license.txt](../license.txt)

---

## 🗺️ File Structure

```
wp-advanced-import-export/
├── .github/
│   ├── DOCUMENTATION_INDEX.md          ← You are here
│   ├── MEDIA_SYNC_CARD.md             ← Quick reference
│   ├── MEDIA_SYNC_SUMMARY.md          ← Summary
│   ├── MEDIA_SYNC_FLOW.md             ← Architecture
│   └── PHASE_9.8_CHECKLIST.md         ← Implementation checklist
│
├── app/
│   ├── controller/                     ← Business logic
│   ├── model/                          ← Data handling
│   ├── helper/                         ← Utilities
│   ├── library/                        ← Snippets library
│   ├── sync/                           ← Media folder sync
│   └── view/                           ← Templates
│
├── assets/                             ← Compiled CSS/JS
├── src/                                ← Source SCSS/JS
├── vendor/                             ← Dependencies (Freemius)
│
├── ARCHITECTURE.md                     ← Full architecture
├── DEVELOPMENT_PLAN.md                 ← 14-phase plan
├── CUSTOM_FUNCTIONS_EXAMPLES.md        ← 50+ examples
├── MEDIA_SYNC_FEATURE.md               ← Media sync docs
├── copilot-instructions.md             ← AI coding rules
├── readme.txt                          ← WordPress.org readme
│
└── wp-advanced-import-export.php       ← Main plugin file
```

---

## 🎯 Quick Navigation

### I want to...

**...understand the architecture**
→ [ARCHITECTURE.md](../ARCHITECTURE.md)

**...follow the development plan**
→ [DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)

**...learn about coding standards**
→ [copilot-instructions.md](../copilot-instructions.md)

**...see custom function examples**
→ [CUSTOM_FUNCTIONS_EXAMPLES.md](../CUSTOM_FUNCTIONS_EXAMPLES.md)

**...understand Media Folder Sync**
→ [MEDIA_SYNC_FEATURE.md](../MEDIA_SYNC_FEATURE.md)  
→ [MEDIA_SYNC_CARD.md](./MEDIA_SYNC_CARD.md)

**...implement Media Sync (Phase 9.8)**
→ [PHASE_9.8_CHECKLIST.md](./PHASE_9.8_CHECKLIST.md)

**...view API documentation**
→ [ARCHITECTURE.md](../ARCHITECTURE.md) (API section)

**...see database schema**
→ [ARCHITECTURE.md](../ARCHITECTURE.md) (Database section)

**...find hooks reference**
→ [ARCHITECTURE.md](../ARCHITECTURE.md) (Hooks section)

---

**Last Updated:** 2025-11-27  
**Version:** 1.0.0  
**Status:** Phase 9.8 planning complete, ready for implementation

---

*Made with ❤️ for WordPress community*
