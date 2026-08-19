# Export System Architecture Update Summary

**Date**: 2024-01-15
**Updated Files**: `ARCHITECTURE.md`, `DEVELOPMENT_PLAN.md`

---

## What Was Added

### 1. ARCHITECTURE.md - Section 11: Export System

Added comprehensive architecture documentation (~800 lines) covering:

#### Components Created

**Controllers**:
- `Export_Wizard_Controller` - 5-step wizard flow management
  - `process_step_1()` - Content Type Selection
  - `process_step_2()` - Filters & Query Builder
  - `process_step_3()` - Field Selection & Mapping
  - `process_step_4()` - Export Options & Format
  - `start_export()` - Export initiation

**Exporters** (6 classes):
- `Base_Exporter` - Abstract base with shared functionality
- `Post_Exporter` - Posts/Pages/CPT export with advanced filtering
- `User_Exporter` - User export with role filtering
- `Product_Exporter` - WooCommerce products with variations
- `Comment_Exporter` - Comment export
- `Taxonomy_Exporter` - Categories/Tags/Custom taxonomies
- `Menu_Exporter` - Navigation menus

**Format Writers** (3 classes):
- `CSV_Writer` - UTF-8 BOM support, streaming write
- `JSON_Writer` - Pretty print, metadata wrapper
- `Excel_Writer` - XLS/XLSX via PhpSpreadsheet

**Progress Tracking**:
- `Export_Progress_Tracker` - Real-time progress updates
  - `update()` - Update progress
  - `complete()` - Mark as completed
  - `fail()` - Handle errors
  - `get_progress()` - Get current status

#### Key Features Documented

**Advanced Filtering**:
- Post Status (multiple)
- Date Range (from/to)
- Author (multiple)
- Categories/Tags/Custom Taxonomies
- Meta Queries (AND/OR, all operators)
- WooCommerce specific:
  - Product Type
  - Stock Status
  - Price Range
  - Product Categories/Tags

**Field Support**:
- **WordPress Core**: All standard fields
- **ACF Pro**: All field types (simple, repeater, flexible, gallery, etc.)
- **Yoast SEO**: 6 meta fields
- **WooCommerce**: Products (30+ fields), Orders, Coupons

**Field Transformation**:
- **Search & Replace**: 
  - Plain text (case-sensitive/insensitive)
  - Regex with validation
  - Unlimited rules per field
- **Custom Functions**: Integration with Custom_Function_Manager
- **Default Values**: For empty fields
- **Live Preview**: First 3 transformed values

**Formats**:
- **CSV**: UTF-8 BOM for Excel, custom delimiter/enclosure
- **JSON**: Pretty print, optional metadata wrapper
- **XLS**: Legacy format (Office 97-2003)
- **XLSX**: Modern format (Office 2007+)

**Background Processing**:
- Queue Manager integration
- Batch size: 50 items (configurable)
- Progress tracking in real-time
- Pause/Cancel support
- Error handling and retry logic

**Templates & History**:
- Save/Load export configurations
- Export History with download/preview/rerun
- Auto-cleanup: Delete files >7 days old

#### UI Views (8 files)

- `export_wizard.php` - Main wizard container
- `step_1_content_type.php` - Content type selection
- `step_2_filters.php` - Filtering UI with query builder
- `step_3_fields.php` - Field selection with drag & drop
- `step_4_options.php` - Format and processing options
- `step_5_progress.php` - Real-time progress tracking
- `export_history.php` - List all exports
- `export_templates.php` - Manage templates

#### JavaScript Modules (4 files)

- `export_wizard.js` - Wizard flow management
- `field_selector.js` - Drag & Drop field selection (Sortable.js)
- `export_progress.js` - Real-time progress updates (polling/SSE)
- `field_settings_modal.js` - Field transformation settings

#### REST API Endpoints (12+)

```
POST   /aie/v1/export/estimate        - Estimate items count
POST   /aie/v1/export/start           - Start export
GET    /aie/v1/export/progress/{id}   - Get progress
GET    /aie/v1/export/download/{id}   - Download file
GET    /aie/v1/export/templates       - List templates
POST   /aie/v1/export/template/save   - Save template
GET    /aie/v1/export/template/{id}   - Load template
PUT    /aie/v1/export/template/{id}   - Update template
DELETE /aie/v1/export/template/{id}   - Delete template
GET    /aie/v1/export/history         - Export history
POST   /aie/v1/export/rerun/{id}      - Rerun export
DELETE /aie/v1/export/delete/{id}     - Delete export
```

#### Hooks & Filters (15+)

**Actions**:
- `aie_before_export` - Before export starts
- `aie_before_export_item` - Before exporting each item
- `aie_after_export_item` - After exporting each item
- `aie_after_export` - After export completes
- `aie_export_complete` - On successful completion
- `aie_export_failed` - On failure

**Filters**:
- `aie_export_query_args` - Modify WP_Query args
- `aie_export_item_data` - Modify item data
- `aie_export_field_value` - Modify field value
- `aie_export_file_path` - Modify file save path
- `aie_export_batch_size` - Modify batch size
- `aie_export_formats` - Add custom formats
- `aie_export_available_fields` - Add custom fields
- `aie_export_content_types` - Add custom content types

---

## 2. DEVELOPMENT_PLAN.md - Phase 5: Export System

Added detailed implementation plan (~500 lines) with 9 sub-phases:

### Phase Breakdown

| Sub-Phase | Description | Hours |
|-----------|-------------|-------|
| 5.1 | Core Export Functionality | 20h |
| 5.2 | Format Writers (CSV/JSON/Excel) | 10h |
| 5.3 | Advanced Filtering | 12h |
| 5.4 | Field Transformation | 8h |
| 5.5 | Product Export (WooCommerce) | 8h |
| 5.6 | Background Processing | 6h |
| 5.7 | Templates & History | 6h |
| 5.8 | Advanced Exporters (Taxonomy/Menu/Media) | 6h |
| 5.9 | ACF & Yoast Integration | 4h |
| **Total** | | **65-70h** |

### 5.1 Core Export Functionality (20h)

**5.1.1 Export_Wizard_Controller** (8h):
- 5-step wizard implementation
- Session management
- Validation for each step
- Preview functions
- Job creation

**5.1.2 Base_Exporter Class** (4h):
- Abstract class with shared methods
- Search & Replace engine
- Custom Functions integration
- Meta query validation

**5.1.3 Post_Exporter** (4h):
- WP_Query builder
- All WordPress fields
- ACF fields integration
- Yoast SEO fields
- Batch processing

**5.1.4 User_Exporter** (2h):
- WP_User_Query builder
- User roles filtering
- User meta export

**5.1.5 Comment_Exporter** (2h):
- Comment query builder
- Comment status filtering
- Comment meta export

### 5.2 Format Writers (10h)

**5.2.1 CSV_Writer** (4h):
- UTF-8 BOM for Excel compatibility
- Custom delimiter/enclosure
- Streaming write (fputcsv)
- Large file support (>100MB)

**5.2.2 JSON_Writer** (3h):
- Pretty print (JSON_PRETTY_PRINT)
- Metadata wrapper
- Streaming write
- Unicode support (JSON_UNESCAPED_UNICODE)

**5.2.3 Excel_Writer** (3h):
- PhpSpreadsheet integration
- XLSX (Office 2007+) support
- XLS (Office 97-2003) support
- Auto-width, freeze headers, auto-filter

### 5.3 Advanced Filtering (12h)

**5.3.1 Query Builder for Posts** (5h):
- Post status (multiple)
- Date range
- Author/Category/Tag filtering
- Meta queries (AND/OR, all operators, nested)
- Custom taxonomy support
- UI for meta query builder

**5.3.2 Query Builder for Products** (4h):
- Product type filtering
- Stock status
- Price range
- Product categories/tags
- Attributes filtering
- SKU search

**5.3.3 Query Builder for Users** (2h):
- User roles (multiple)
- Registration date range
- Meta queries

**5.3.4 Query Builder for Comments** (1h):
- Comment status
- Post/User filtering
- Date range

### 5.4 Field Transformation (8h)

**5.4.1 Field Settings Modal** (4h):
- UI component with drag & drop
- Column name customization
- Default value setting
- Search & Replace rules
- Custom Function selector
- Live Preview (first 3 values)

**5.4.2 Search & Replace Engine** (2h):
- Plain text: str_replace/str_ireplace
- Regex: preg_replace with validation
- Multiple rules execution
- Error handling

**5.4.3 Custom Functions Integration** (2h):
- Dropdown selector
- Execute functions on field values
- Error handling
- Preview results

### 5.5 Product Export (8h)

**5.5.1 Product_Exporter** (5h):
- 30+ WooCommerce fields
- Product variations
- Gallery images
- Product attributes
- Advanced filtering (from 5.3.2)

**5.5.2 Order_Exporter** (2h):
- Order details
- Line items
- Customer info
- Billing/Shipping addresses
- Order status filtering

**5.5.3 Coupon_Exporter** (1h):
- Coupon code/description
- Discount settings
- Usage limits
- Product/Category restrictions

### 5.6 Background Processing (6h)

**5.6.1 Export_Progress_Tracker** (3h):
- Update progress in aie_jobs
- Complete/Fail methods
- Get progress API
- Action hooks
- Error logging

**5.6.2 Queue Integration** (2h):
- Queue_Manager integration
- Background task processing
- Error handling and retry
- Pause/Cancel support

**5.6.3 Direct Export** (1h):
- Synchronous export for <1000 items
- Immediate download
- Time limit check

### 5.7 Templates & History (6h)

**5.7.1 Export Templates** (3h):
- CRUD operations
- aie_export_templates table
- Save/Load configurations
- UI page

**5.7.2 Export History** (3h):
- List all exports (aie_jobs)
- Download (secure file serving)
- Preview (first 10 rows)
- Rerun (duplicate export)
- Delete (file + record)
- View logs
- Auto-cleanup (cron job)

### 5.8 Advanced Exporters (6h)

**5.8.1 Taxonomy_Exporter** (2h):
- Terms export
- Hierarchical structure
- Term meta

**5.8.2 Menu_Exporter** (2h):
- Menu items
- Menu hierarchy
- Menu item meta

**5.8.3 Media_Exporter** (2h):
- Attachments export
- Media meta
- File URLs

### 5.9 ACF & Yoast Integration (4h)

**5.9.1 ACF Fields Export** (2h):
- Detect ACF plugin
- Get field groups
- Export simple fields
- Export complex fields (Repeater, Flexible, Gallery, Relationship)
- Format options

**5.9.2 Yoast SEO Fields Export** (2h):
- Detect Yoast plugin
- 6 meta fields export
- Add to available_fields

---

## Completion Criteria

### Functionality
- ✅ 5-step wizard fully functional
- ✅ Export 10+ content types
- ✅ 4 formats (CSV, JSON, XLS, XLSX)
- ✅ Advanced filtering with meta queries
- ✅ Field transformation (Search/Replace + Functions)
- ✅ Background processing
- ✅ Templates system
- ✅ Export History
- ✅ ACF & Yoast integration

### Performance
- ✅ Export 10,000 posts in <2 minutes
- ✅ CSV files >100MB without memory overflow
- ✅ Streaming write for all formats

### UI/UX
- ✅ Intuitive wizard flow
- ✅ Real-time progress tracking
- ✅ Preview at each step
- ✅ Field Settings Modal with live preview
- ✅ Drag & Drop field reorder

---

## Database Schema

No new tables required. Uses existing tables:

**aie_jobs** - New columns for export:
- `type` (import/export)
- `data_type` (post, user, product, etc.)
- `file_format` (csv, json, xls, xlsx)
- `file_size` (bytes)

**aie_export_templates** (NEW table):
```sql
CREATE TABLE {prefix}_aie_export_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    content_type VARCHAR(50) NOT NULL,
    filters TEXT,
    selected_fields TEXT,
    field_settings TEXT,
    export_options TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    INDEX (user_id),
    INDEX (content_type)
);
```

---

## Testing Plan

### Unit Tests
```php
// Test Export_Wizard_Controller
test_process_step_1_validates_content_type();
test_process_step_2_applies_filters();
test_process_step_3_validates_fields();
test_start_export_creates_job();

// Test Exporters
test_post_exporter_query_builder();
test_post_exporter_field_mapper();
test_user_exporter_role_filter();
test_product_exporter_price_filter();

// Test Format Writers
test_csv_writer_with_bom();
test_json_writer_pretty_print();
test_excel_writer_xlsx();

// Test Progress Tracker
test_update_progress();
test_complete_export();
test_fail_export();
```

### Integration Tests
```php
// Full export flow
test_full_post_export_csv();
test_full_product_export_xlsx();
test_full_user_export_json();

// Background processing
test_background_export_queue();
test_export_progress_tracking();
test_export_pause_cancel();

// Templates
test_save_load_template();
test_template_prepopulates_wizard();

// History
test_export_history_list();
test_download_export_file();
test_rerun_export();
```

### Performance Tests
```php
test_export_10000_posts_under_2_minutes();
test_csv_100mb_no_memory_overflow();
test_streaming_write_large_file();
```

---

## Next Steps

1. **Review Documentation**:
   - Read `EXPORT_UI_SPECIFICATION.md` for detailed UI wireframes
   - Review `ARCHITECTURE.md` Section 11 for technical details
   - Check `DEVELOPMENT_PLAN.md` Phase 5 for implementation order

2. **Begin Implementation**:
   - Start with Phase 5.1: Core Export Functionality
   - Implement Export_Wizard_Controller first
   - Then Base_Exporter and Post_Exporter
   - Test each component before moving to next

3. **Iterative Development**:
   - Complete one sub-phase at a time
   - Write tests for each component
   - Test with real data (posts, users, products)
   - Verify performance benchmarks

4. **Dependencies**:
   - Install PhpSpreadsheet: `composer require phpoffice/phpspreadsheet`
   - Install Sortable.js: Already in assets
   - Install Select2: Already in assets

5. **After Phase 5**:
   - Move to Phase 6: Background Processing (already partially implemented)
   - Then Phase 7: Admin UI improvements
   - Phase 8: REST API enhancements

---

## Documentation Reference

| Document | Lines | Purpose |
|----------|-------|---------|
| `EXPORT_UI_SPECIFICATION.md` | 1200 | Complete UI/UX specification with wireframes |
| `EXPORT_UI_SUMMARY.md` | 350 | Quick reference and priorities |
| `ARCHITECTURE.md` Section 11 | 800 | Technical architecture |
| `DEVELOPMENT_PLAN.md` Phase 5 | 500 | Implementation roadmap |
| This file | - | Update summary |

**Total Documentation**: ~2850 lines for Export System

---

## Comparison: Import vs Export

| Feature | Import System | Export System |
|---------|---------------|---------------|
| **Wizard Steps** | 7 steps | 5 steps |
| **Estimated Hours** | ~80 hours | ~65-70 hours |
| **Content Types** | 8+ types | 10+ types |
| **Formats** | CSV, JSON, XLS, XLSX | CSV, JSON, XLS, XLSX |
| **Filtering** | Basic filters | Advanced (Meta Queries) |
| **Field Mapping** | Yes (Required) | Yes (Optional) |
| **Field Transformation** | Yes | Yes (Search/Replace + Functions) |
| **Duplicate Handling** | Yes (4 methods) | No (Not applicable) |
| **Image Download** | Yes (3 sources) | No (Export URLs) |
| **Background Processing** | Yes (50 items/batch) | Yes (50 items/batch) |
| **Templates** | Yes (Save/Load) | Yes (Save/Load) |
| **History** | Yes | Yes (with Rerun) |
| **ACF Support** | Yes | Yes |
| **WooCommerce** | Products only | Products + Orders + Coupons |

**Key Differences**:
- Export is simpler (no duplicate handling, no image download)
- Export has MORE filtering (meta queries, complex filters)
- Export has MORE content types (Taxonomies, Menus, Media)
- Export uses streaming write (better performance for large exports)
- Import has MORE validation (7 validators vs minimal in export)

---

## Questions & Answers

**Q: Why only 5 steps vs Import's 7 steps?**
A: Export is simpler - no file upload, no field mapping required (just selection), no duplicate handling. Steps: Content Type → Filters → Fields → Options → Progress.

**Q: Why is Export faster (65h vs Import's 80h)?**
A: No image download logic, no duplicate detection, no extensive validation, simpler field mapping.

**Q: Do we need a new database table for Export?**
A: Yes, `aie_export_templates` for saving export configurations. Jobs use existing `aie_jobs` table with `type='export'`.

**Q: What's the batch size for export?**
A: 50 items per batch (same as import). Configurable via filter `aie_export_batch_size`.

**Q: Can users export variations?**
A: Yes, Product_Exporter supports variations as separate rows linked to parent product.

**Q: How are complex ACF fields exported?**
A: Repeater/Flexible: JSON array. Gallery: comma-separated URLs. Relationship: post IDs or titles (configurable).

**Q: What's the file size limit for exports?**
A: No hard limit. Streaming write supports files >100MB. Server limits (max_execution_time, memory_limit) may apply - use background processing for large exports.

---

## Changelog

**v1.0 - 2024-01-15**:
- ✅ Added Section 11 to ARCHITECTURE.md (800 lines)
- ✅ Added Phase 5 to DEVELOPMENT_PLAN.md (500 lines)
- ✅ Created EXPORT_ARCHITECTURE_UPDATE_SUMMARY.md (this file)
- ✅ Total Export System documentation: ~2850 lines

---

**Ready for Implementation** ✅

All documentation complete. Begin with Phase 5.1 (Core Export Functionality).
