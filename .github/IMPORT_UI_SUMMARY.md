# 📥 Import UI Implementation Summary

## ✅ Что создано

### Новый документ: IMPORT_UI_SPECIFICATION.md (~850 строк)

Полная спецификация пользовательского интерфейса импорта с визуальными wireframes.

---

## 🎨 UI/UX Компоненты

### 7-Шаговый Import Wizard

1. **Step 1: File Upload & Format Detection**
   - Drag & Drop upload
   - Форматы: CSV, JSON, XLS, XLSX
   - Import from URL
   - Recent imports (reuse settings)

2. **Step 2: Content Type Selection**
   - WordPress Core: Posts, Pages, Users, Comments, Taxonomies, Menus
   - Custom Post Types: Любые CPT
   - WooCommerce: Products, Orders, Coupons, Attributes
   - **Custom MySQL Table** (прямой импорт в БД)

3. **Step 3: Column Selection (Drag & Drop)**
   - Visual drag & drop interface
   - Available columns ← → Columns to import
   - Preview first 3 rows
   - Select All / Deselect All

4. **Step 4: Field Mapping & Advanced Settings**
   - Map columns to WordPress fields
   - Поддержка ВСЕХ WordPress полей:
     - Core: Title, Content, Date, Author, Status, etc.
     - Taxonomies: Categories, Tags, Custom Taxonomies
     - Custom Fields (Meta)
     - **ACF Fields** (включая Repeater!)
     - WooCommerce fields
   
   **Per-field Settings Modal** (⚙️ кнопка для каждого поля):
   - Basic mapping
   - Default value (if empty)
   - **Search & Replace** (multiple rules)
   - **Apply Custom Function** (from Function Library)
   - Data transformation (strip HTML, decode entities, etc.)
   - Live preview transformation

5. **Step 5: Import Options & Duplicate Handling**
   
   **Duplicate Detection:**
   - By Post Title
   - By Post ID
   - By Custom Field (e.g., SKU)
   - Multiple fields combination
   
   **If Duplicate Found:**
   - Skip (don't import)
   - Update existing
   - Delete and recreate
   - Create duplicate anyway
   
   **Update Strategy:**
   - Replace all fields
   - Update only mapped fields
   - Don't update if field has value
   
   **Other Options:**
   - Post Status, Author, Date
   - Comment/Ping Status
   - Background processing
   - Email notification

6. **Step 6: Import Progress**
   - Real-time progress bar
   - Live statistics (created, updated, skipped, failed)
   - Image download progress
   - Time estimates
   - Recent activity log
   - Pause/Cancel

7. **Step 7: Import Complete**
   - Final statistics
   - Actions: View items, Download log, Import more
   - Save import template (reuse settings)
   - Email notification

---

## 🖼️ Image Import Features

**Auto-download images from URLs:**
- ✅ Download to Media Library
- ✅ Skip duplicates (by URL hash)
- ✅ Set as Featured Image
- ✅ Alt text & title mapping
- ✅ Download timeout setting
- 👑 Premium: Resize, Compress, WebP conversion

**Example:**
```
Column: product_image_url
Value: https://example.com/images/product.jpg
→ Downloads and creates attachment in Media Library
→ Sets as Featured Image
```

---

## 🔄 ACF Repeater Field Support

**3 Import Methods:**

1. **Multiple Columns:**
   ```
   feature_1_name | feature_1_value | feature_2_name | feature_2_value
   Color          | Red             | Size           | Large
   ```

2. **Single Column with Delimiter:**
   ```
   features
   Name:Color|Value:Red|Name:Size|Value:Large
   ```

3. **JSON Format:**
   ```
   features
   [{"name":"Color","value":"Red"},{"name":"Size","value":"Large"}]
   ```

**UI Features:**
- Auto-detect repeater rows
- Visual row mapping
- Preview before import

---

## 🗄️ Custom MySQL Table Import

**Direct database import:**
```
SELECT table → Map columns → Import directly to custom table
```

**Use Cases:**
- Legacy data migration
- Third-party plugin data
- Custom application tables
- Analytics data

---

## 📋 Duplicate Handling Examples

### Example 1: Skip if exists (by Title)
```
Import: "Blue Jeans" (already exists)
Action: Skip
Result: Existing post unchanged
```

### Example 2: Update existing (by SKU)
```
Import: SKU "PROD-123" (exists with old price)
Action: Update
Result: Price updated, other fields preserved
```

### Example 3: Update only mapped fields
```
Import: Map only "price" field
Existing post: title, content, images
Action: Update
Result: Only price updated, rest untouched
```

---

## 🎛️ Admin Menu Structure

```
📊 Import/Export (Top-level menu)
├─ 📥 Import           ← New import wizard
├─ 📤 Export
├─ 📜 History          ← Import/export logs
├─ 🔧 Custom Functions
├─ 📚 Function Library
├─ 📁 Media Sync
├─ 🔄 Content Sync
└─ ⚙️  Settings
```

**Каждое submenu - отдельная страница!**

---

## 🔌 Technical Stack

### Backend (PHP):
```php
app/controller/
├─ import_controller.php      // Main wizard controller
├─ import_step_controller.php // Step-by-step logic
└─ importers/
   ├─ post_importer.php        // Posts/Pages/CPT
   ├─ user_importer.php        // Users
   ├─ product_importer.php     // WooCommerce
   ├─ taxonomy_importer.php    // Terms
   ├─ comment_importer.php     // Comments
   ├─ menu_importer.php        // Menus
   └─ custom_table_importer.php // Custom MySQL
```

### Frontend (JavaScript):
```javascript
src/js/modules/
├─ import_wizard.js           // Main wizard logic
├─ column_selector.js         // Drag & Drop columns
├─ field_mapper.js            // Field mapping + settings
├─ duplicate_handler.js       // Duplicate detection
├─ image_downloader.js        // Auto-download images
└─ progress_tracker.js        // Real-time progress
```

### UI Views:
```php
app/view/import/
├─ import_wizard.php          // Main wizard page
├─ step_1_upload.php          // File upload
├─ step_2_content_type.php    // Content type
├─ step_3_columns.php         // Column selection
├─ step_4_mapping.php         // Field mapping
├─ step_5_options.php         // Import options
├─ step_6_progress.php        // Progress
└─ step_7_complete.php        // Complete
```

---

## 📊 Supported Content Types

| Content Type | Import | Field Mapping | ACF Support | WC Support | Custom Tables |
|--------------|--------|---------------|-------------|------------|---------------|
| **Posts** | ✅ | ✅ All fields | ✅ Full | - | - |
| **Pages** | ✅ | ✅ All fields | ✅ Full | - | - |
| **Users** | ✅ | ✅ All fields | ✅ Full | - | - |
| **Comments** | ✅ | ✅ All fields | - | - | - |
| **Taxonomies** | ✅ | ✅ All fields | ✅ Full | - | - |
| **Menus** | ✅ | ✅ All fields | - | - | - |
| **Custom Post Types** | ✅ | ✅ All fields | ✅ Full | - | - |
| **WC Products** | ✅ | ✅ All fields | ✅ Full | ✅ Full | - |
| **WC Orders** | ✅ | ✅ All fields | - | ✅ Full | - |
| **WC Coupons** | ✅ | ✅ All fields | - | ✅ Full | - |
| **WC Attributes** | ✅ | ✅ All fields | - | ✅ Full | - |
| **Media** | ✅ | ✅ All fields | ✅ Full | - | - |
| **Custom Tables** | ✅ | ✅ Direct SQL | - | - | ✅ Direct |

---

## 🎯 Key Features Implemented

### ✅ Drag & Drop
- Column selection with visual feedback
- Reorder columns
- Multi-select support

### ✅ Field Mapping
- **All WordPress Fields:**
  - Post Title, Content, Excerpt
  - Post Date, Modified, Status
  - Author, Parent, Order
  - Featured Image, Gallery
  - Comment/Ping Status
  - Slug, Password, Format

- **All Taxonomies:**
  - Categories (by name or ID)
  - Tags (by name or ID)
  - Custom Taxonomies

- **Custom Fields (Meta):**
  - Any post meta
  - WooCommerce fields (_sku, _price, _stock, etc.)

- **ACF Fields (All Types!):**
  - Text, Textarea, Number, Email, URL
  - Wysiwyg, Image, File, Gallery
  - Select, Checkbox, Radio, True/False
  - Date Picker, Color Picker, Google Map
  - Post Object, Relationship, User
  - **Repeater** (3 import methods)
  - Flexible Content, Group, Clone

### ✅ Per-Field Settings
Each field has advanced settings:
- Default value (if empty)
- Search & Replace (multiple rules)
- Apply Custom Function
- Data transformation (strip HTML, decode entities)
- Live preview

### ✅ Image Auto-Download
- Download from URL
- Save to Media Library
- Set as Featured Image
- Skip duplicates
- Alt text & title mapping
- 👑 Premium: Optimize, resize, compress, WebP

### ✅ Duplicate Detection
- By Post Title (exact or fuzzy)
- By Post ID
- By Custom Field (SKU, external_id, etc.)
- Multiple fields combination

### ✅ Duplicate Actions
- Skip (don't import)
- Update existing (merge strategies)
- Delete and recreate
- Create duplicate anyway

### ✅ Background Processing
- Queue Manager integration
- Batch size: 50 items
- Real-time progress
- Email notification
- Pause/Cancel support

---

## 📈 Performance

### Target Metrics:
- **Small imports** (< 100 items): Instant processing
- **Medium imports** (100-1000 items): Background processing
- **Large imports** (1000-10000 items): Batch processing with progress
- **Huge imports** (10000+ items): WP-CLI recommended

### Optimization:
- ✅ Streaming file reading (low memory)
- ✅ Batch processing (50 items/batch)
- ✅ Image download queue
- ✅ Database optimization (prepared statements)
- ✅ Caching (field mapping, functions)

---

## 🎨 UI/UX Principles

1. **Progressive Disclosure** - Show advanced options when needed
2. **Visual Feedback** - Progress indicators, status messages
3. **Error Prevention** - Validation before import
4. **Undo Support** - Revert imports (Premium)
5. **Accessibility** - WCAG 2.1 AA compliant
6. **Responsive** - Works on all devices
7. **Performance** - Handles 10,000+ rows

---

## 📚 Documentation Structure

```
IMPORT_UI_SPECIFICATION.md
├─ Overview
├─ Multi-Step Wizard (7 steps with wireframes)
│  ├─ Step 1: File Upload
│  ├─ Step 2: Content Type
│  ├─ Step 3: Column Selection (Drag & Drop)
│  ├─ Step 4: Field Mapping
│  │  ├─ Field Settings Modal
│  │  ├─ Image Import Settings
│  │  └─ ACF Repeater Mapping
│  ├─ Step 5: Import Options & Duplicates
│  ├─ Step 6: Import Progress
│  └─ Step 7: Complete
├─ Admin Menu Structure
├─ Import History Page
├─ UI/UX Principles
├─ Technical Implementation
└─ Summary
```

**Total Lines**: ~850 строк с ASCII wireframes!

---

## 🔗 Integration Points

### With Custom Functions:
- Select function from library (50+ examples)
- Apply to any field during import
- Live preview transformation

### With Media Sync:
- Import images via URL
- Auto-download to Media Library
- Duplicate detection by hash

### With Site-to-Site Sync:
- Import data from remote WordPress site
- Use same field mapping interface
- Background processing

### With Queue Manager:
- Background job creation
- Progress tracking
- Batch processing

---

## 🚀 Implementation Priority

### Phase 1: Core Import (High Priority)
- [ ] File upload & format detection
- [ ] Basic field mapping (Posts)
- [ ] Simple duplicate detection (by Title)
- [ ] Background processing
- [ ] Progress tracking

### Phase 2: Advanced Mapping (High Priority)
- [ ] Drag & Drop column selection
- [ ] All WordPress fields support
- [ ] Custom fields (meta)
- [ ] Per-field settings modal
- [ ] Search & Replace

### Phase 3: Content Types (Medium Priority)
- [ ] Users import
- [ ] Taxonomies import
- [ ] Comments import
- [ ] Menus import
- [ ] Custom Post Types

### Phase 4: WooCommerce (Medium Priority)
- [ ] Products import
- [ ] Product variations
- [ ] Orders import
- [ ] Coupons import
- [ ] Attributes import

### Phase 5: Advanced Features (Medium Priority)
- [ ] ACF fields support (all types)
- [ ] ACF Repeater (3 methods)
- [ ] Image auto-download
- [ ] Advanced duplicate detection

### Phase 6: Custom Tables (Low Priority)
- [ ] MySQL table selection
- [ ] Direct database import
- [ ] Custom column mapping

### Phase 7: Premium Features (Low Priority)
- [ ] Image optimization
- [ ] Scheduled imports
- [ ] Import templates
- [ ] Undo/Revert

---

## ✅ Next Steps

1. **Review Specification** - Прочитать IMPORT_UI_SPECIFICATION.md
2. **Update DEVELOPMENT_PLAN.md** - Добавить Phase 4 (Import System)
3. **Update ARCHITECTURE.md** - Добавить Section 3 (Import System)
4. **Start Implementation** - Начать с Phase 4.1 (File Upload)

---

## 📝 Files Updated

### Created:
- ✅ **IMPORT_UI_SPECIFICATION.md** (~850 lines)

### Updated:
- ✅ **copilot-instructions.md** - Added link to IMPORT_UI_SPECIFICATION.md
- ✅ **.github/DOCUMENTATION_INDEX.md** - Added import spec to docs

### To Update:
- ⏳ **ARCHITECTURE.md** - Add Section 3: Import System
- ⏳ **DEVELOPMENT_PLAN.md** - Add Phase 4: Import System Implementation
- ⏳ **.github/README.md** - Mention import UI spec

---

**Status**: 📋 Specification Complete  
**Ready for**: Architecture Design → Development Plan → Implementation  
**Estimated Implementation**: ~80 hours (full import system)

**Let me know if you want to:**
1. Update ARCHITECTURE.md with Import System section
2. Update DEVELOPMENT_PLAN.md with Phase 4 tasks
3. Start implementing Step 1 (File Upload)
4. Create UI mockups/designs

🚀 Готов продолжать!
