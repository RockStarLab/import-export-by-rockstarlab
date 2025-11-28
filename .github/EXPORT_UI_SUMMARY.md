# Export UI System - Quick Summary

## 📊 Overview

5-шаговый визард для экспорта контента WordPress с расширенными фильтрами и трансформацией данных.

**Full Specification**: [EXPORT_UI_SPECIFICATION.md](../EXPORT_UI_SPECIFICATION.md)

---

## 🎯 Key Features

### 5-Step Export Wizard
1. **Content Type Selection** - Выбор типа контента для экспорта
2. **Filters & Query Builder** - Расширенные фильтры для сужения выборки
3. **Field Selection & Mapping** - Drag & Drop выбор полей с настройками
4. **Export Options & Format** - Формат, имя файла, background processing
5. **Progress & Download** - Real-time прогресс и скачивание

### Supported Content Types
- ✅ **WordPress Core**: Posts, Pages, CPT, Users, Comments, Taxonomies, Menus
- ✅ **WooCommerce**: Products, Variations, Orders, Coupons, Attributes
- ✅ **ACF Pro**: All field types (включая Repeater, Flexible Content)
- ✅ **Yoast SEO**: SEO meta data

### Export Formats
- ✅ **CSV** - UTF-8 with BOM, configurable delimiter/enclosure
- ✅ **JSON** - Pretty print, nested objects, metadata
- ✅ **XLS** - Legacy Excel format (BIFF)
- ✅ **XLSX** - Modern Excel format (Office 2007+)

### Advanced Filtering

**Posts/Pages**:
- Status (Published, Draft, Pending, Private, Trash)
- Date Range (custom from/to)
- Author (multi-select)
- Categories, Tags (multi-select)
- Custom Taxonomies
- Meta Queries (key, operator, value)
- Post ID range

**WooCommerce Products**:
- Product Type (Simple, Variable, Grouped, External)
- Stock Status (In Stock, Out of Stock, On Backorder)
- Price Range (min/max)
- Product Categories, Tags
- Attributes
- SKU filter

**Users**:
- User Roles (multi-select)
- Registration Date Range
- User Meta Queries

### Field Transformation
- **Search & Replace** - unlimited rules per field
- **Custom Functions** - apply functions from library
- **Regex Support** - advanced pattern matching
- **Live Preview** - see transformation before export

### Background Processing
- ✅ Batch processing (50 items/batch)
- ✅ File saved to `wp-content/uploads/aie/exports/`
- ✅ Email notification on completion
- ✅ Real-time progress tracking
- ✅ Pause/Resume/Cancel

### Template System
- ✅ Save export configurations
- ✅ Reuse templates for recurring exports
- ✅ Edit/Duplicate/Delete templates
- ✅ Template library page

### Export History
- ✅ List all exports with filters
- ✅ Download exported files
- ✅ Preview file content
- ✅ View detailed logs
- ✅ Rerun exports
- ✅ Auto-cleanup (7 days default)

---

## 🏗️ Architecture

### Backend Components

```
app/
├── controller/
│   └── export_wizard_controller.php    # Main wizard controller
├── exporter/
│   ├── base_exporter.php               # Abstract base class
│   ├── post_exporter.php               # Posts/Pages exporter
│   ├── user_exporter.php               # Users exporter
│   ├── product_exporter.php            # WooCommerce products
│   ├── comment_exporter.php            # Comments exporter
│   ├── taxonomy_exporter.php           # Taxonomies exporter
│   └── menu_exporter.php               # Menus exporter
├── format/
│   ├── csv_writer.php                  # CSV format writer
│   ├── json_writer.php                 # JSON format writer
│   └── excel_writer.php                # XLS/XLSX writer
└── export/
    └── export_progress_tracker.php     # Progress tracking
```

### Frontend Components

```
src/js/modules/
├── export_wizard.js          # Main wizard logic
├── field_selector.js         # Field selection with drag & drop
├── export_progress.js        # Real-time progress tracking
└── field_settings_modal.js   # Field transformation settings
```

### Views

```
app/view/export/
├── export_wizard.php         # Main wizard page
├── step_1_content_type.php   # Content type selection
├── step_2_filters.php        # Filters & query builder
├── step_3_fields.php         # Field selection
├── step_4_options.php        # Export options
├── step_5_progress.php       # Progress & download
├── export_history.php        # History page
└── export_templates.php      # Templates page
```

---

## 📋 Implementation Priority

### Priority 1: Core Export Functionality (20 hours)
- [ ] Export_Wizard_Controller (5 steps processing)
- [ ] Post_Exporter + User_Exporter
- [ ] CSV_Writer + JSON_Writer
- [ ] Basic UI (5 wizard steps)

### Priority 2: Format Support (10 hours)
- [ ] Excel_Writer (XLS/XLSX) with PhpSpreadsheet
- [ ] Format-specific options (delimiter, pretty print)
- [ ] File validation and error handling

### Priority 3: Advanced Filtering (12 hours)
- [ ] Query builder for all content types
- [ ] Meta queries support
- [ ] Date range filters
- [ ] Live preview of filtered items count

### Priority 4: Field Transformation (8 hours)
- [ ] Search & Replace engine
- [ ] Custom Functions integration
- [ ] Field settings modal
- [ ] Live preview of transformations

### Priority 5: Product Export (8 hours)
- [ ] Product_Exporter for WooCommerce
- [ ] Product-specific filters (stock, price)
- [ ] Attributes and Variations export
- [ ] Product meta fields

### Priority 6: Background Processing (6 hours)
- [ ] Export_Progress_Tracker
- [ ] Queue Manager integration
- [ ] Batch processing (50 items)
- [ ] Email notifications

### Priority 7: Templates & History (6 hours)
- [ ] Template save/load system
- [ ] Templates management page
- [ ] Export history page
- [ ] File cleanup cron job

### Priority 8: Advanced Exporters (6 hours)
- [ ] Comment_Exporter
- [ ] Taxonomy_Exporter
- [ ] Menu_Exporter
- [ ] Custom content type support

### Priority 9: ACF & Yoast Integration (4 hours)
- [ ] ACF field detection and export
- [ ] ACF Repeater/Flexible Content handling
- [ ] Yoast SEO meta export
- [ ] Custom field mapping

---

## 🔧 Technical Stack

### Backend
- **PHP**: 7.4+
- **WordPress**: 5.8+
- **Libraries**:
  - PhpSpreadsheet (XLS/XLSX)
  - League/CSV (advanced CSV)

### Frontend
- **JavaScript**: ES6+
- **Libraries**:
  - Sortable.js (drag & drop)
  - Select2 (multi-select)
  - Flatpickr (date picker)

### Database
- Uses existing tables: `aie_jobs`, `aie_logs`, `aie_field_maps`
- New columns in `aie_jobs`:
  - `export_format` (CSV/JSON/XLS/XLSX)
  - `file_path` (path to exported file)
  - `file_size` (file size in bytes)

---

## 📊 REST API Endpoints

```
POST   /aie/v1/export/estimate         - Estimate export size
POST   /aie/v1/export/start            - Start export
GET    /aie/v1/export/progress/{id}    - Get progress
POST   /aie/v1/export/pause/{id}       - Pause export
POST   /aie/v1/export/resume/{id}      - Resume export
POST   /aie/v1/export/cancel/{id}      - Cancel export
GET    /aie/v1/export/download/{id}    - Download file

GET    /aie/v1/export/templates        - List templates
POST   /aie/v1/export/template/save    - Save template
PUT    /aie/v1/export/template/{id}    - Update template
DELETE /aie/v1/export/template/{id}    - Delete template

GET    /aie/v1/export/history          - Export history
GET    /aie/v1/export/logs/{id}        - Get export logs
DELETE /aie/v1/export/{id}             - Delete export
```

---

## 🧪 Testing Checklist

### Basic Export
- [ ] Export posts to CSV
- [ ] Export users to JSON
- [ ] Export products to XLSX
- [ ] Verify file encoding (UTF-8)
- [ ] Verify column headers

### Filters
- [ ] Filter posts by status
- [ ] Filter by date range
- [ ] Filter by categories/tags
- [ ] Filter by author
- [ ] Filter by meta query

### Field Selection
- [ ] Select specific fields only
- [ ] Reorder fields (drag & drop)
- [ ] Search fields
- [ ] Select all / Clear all

### Field Transformation
- [ ] Search & Replace (single rule)
- [ ] Search & Replace (multiple rules)
- [ ] Apply custom function
- [ ] Combined transformation
- [ ] Live preview works

### Background Processing
- [ ] Large export (1000+ items)
- [ ] Progress updates correctly
- [ ] Pause/Resume works
- [ ] Cancel works
- [ ] Email notification sent

### Templates
- [ ] Save template
- [ ] Load template
- [ ] Edit template
- [ ] Delete template
- [ ] Use template for export

### History
- [ ] View all exports
- [ ] Filter by status/type/date
- [ ] Download file
- [ ] Rerun export
- [ ] View logs
- [ ] Delete export

### WooCommerce
- [ ] Export simple products
- [ ] Export variable products
- [ ] Export with variations
- [ ] Filter by stock status
- [ ] Filter by price range

### ACF
- [ ] Export ACF text fields
- [ ] Export ACF image fields
- [ ] Export ACF Repeater (JSON)
- [ ] Export ACF Flexible Content

### Yoast SEO
- [ ] Export SEO title
- [ ] Export meta description
- [ ] Export focus keyword
- [ ] Export canonical URL

---

## 🎨 UI Components

### Wizard Steps
- Step indicators (1/5, 2/5, etc.)
- Progress bar
- Next/Back buttons
- Step validation

### Filters Panel
- Checkboxes (multi-select)
- Date picker (from/to)
- Select2 dropdowns
- Meta query builder
- Live count preview

### Field Selector
- Two-panel layout (Available | Selected)
- Drag & Drop between panels
- Reorder in Selected panel
- Search box
- Settings icon (⚙️) per field

### Field Settings Modal
- Tabs (Basic | Advanced)
- Column name input
- Default value input
- Search/Replace rules (dynamic list)
- Function dropdown
- Live preview panel

### Progress Page
- Progress bar (0-100%)
- Status message
- Item counters (Total/Processed/Success/Errors)
- Time estimates
- Recent items log
- Pause/Cancel buttons

### History Table
- Sortable columns
- Status badges
- Action buttons per row
- Filters and search
- Pagination

---

## 📝 Key Differences from Import

| Feature | Import | Export |
|---------|--------|--------|
| **Steps** | 7 steps | 5 steps |
| **Focus** | Field mapping | Field selection + filters |
| **Formats** | 4 formats | 4 formats |
| **Direction** | File → WordPress | WordPress → File |
| **Duplicate Handling** | Yes (4 actions) | No (N/A) |
| **Image Handling** | Auto-download | Export URLs or Base64 |
| **Custom Tables** | Yes (direct import) | No (only WP content) |
| **ACF Repeater** | 3 import methods | Export as JSON array |
| **Background** | 50 items/batch | 50 items/batch |

---

## 💡 Future Enhancements

### Phase 1 (Core) ✅
- Basic export functionality
- CSV/JSON support
- Post/User exporters

### Phase 2 (Advanced)
- XLS/XLSX support
- WooCommerce exporter
- Background processing

### Phase 3 (Premium)
- Scheduled exports (cron)
- FTP/SFTP upload
- Google Sheets integration
- Dropbox/OneDrive sync
- Email export results
- Export presets library

### Phase 4 (Enterprise)
- REST API for external apps
- Webhook notifications
- Export analytics
- Multi-site support
- Advanced caching

---

## 🔗 Related Documentation

- **[EXPORT_UI_SPECIFICATION.md](../EXPORT_UI_SPECIFICATION.md)** - Full UI/UX specification with wireframes
- **[ARCHITECTURE.md](../ARCHITECTURE.md)** - Will add Section 11: Export System
- **[DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)** - Will add Phase 5: Export System
- **[IMPORT_UI_SPECIFICATION.md](../IMPORT_UI_SPECIFICATION.md)** - Import counterpart
- **[CUSTOM_FUNCTIONS_EXAMPLES.md](../CUSTOM_FUNCTIONS_EXAMPLES.md)** - Functions for field transformation

---

## 📈 Estimated Effort

| Component | Hours |
|-----------|-------|
| Backend Controllers | 10 |
| Content Type Exporters | 15 |
| Format Writers | 8 |
| Frontend Wizard | 10 |
| Field Selector UI | 5 |
| Progress Tracking | 4 |
| Templates & History | 6 |
| Testing & Polish | 7 |
| **Total** | **65-70 hours** |

---

## ✅ Success Criteria

- [ ] All 5 wizard steps work end-to-end
- [ ] Export to CSV/JSON/XLS/XLSX works
- [ ] Filters narrow down results correctly
- [ ] Field selection with drag & drop works
- [ ] Field transformation (search/replace + functions) works
- [ ] Background processing for large exports
- [ ] Real-time progress tracking
- [ ] Templates save/load correctly
- [ ] History page shows all exports
- [ ] Download exported files works
- [ ] WooCommerce products export with all fields
- [ ] ACF fields export correctly (including Repeater)
- [ ] Yoast SEO meta exports
- [ ] Email notifications sent
- [ ] File cleanup cron job works
- [ ] All REST API endpoints functional
- [ ] Security: nonce validation, capabilities, file protection
- [ ] Performance: handles 10,000+ items smoothly

---

**Next Steps**: Add to ARCHITECTURE.md (Section 11) and DEVELOPMENT_PLAN.md (Phase 5)

**Status**: ✅ Specification Complete  
**Version**: 1.0  
**Date**: 2024
