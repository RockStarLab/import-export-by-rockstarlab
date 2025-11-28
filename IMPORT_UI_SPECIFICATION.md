# 📥 Import UI/UX Specification

Полная спецификация пользовательского интерфейса для импорта данных в WordPress.

---

## 🎯 Overview

Интерфейс импорта представляет собой **многошаговый визард** с drag-and-drop функциональностью, расширенным маппингом полей и поддержкой всех типов контента WordPress.

---

## 📋 Multi-Step Import Wizard

### Step 1: File Upload & Format Detection

```
┌─────────────────────────────────────────────────────────────────┐
│ 📥 Import Data - Step 1 of 5: Select File                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                                                         │   │
│  │              [📁 Choose File or Drag & Drop]            │   │
│  │                                                         │   │
│  │         Supported formats: CSV, JSON, XLS, XLSX         │   │
│  │                                                         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Or import from URL:                                            │
│  [_________________________________________________]  [Fetch]   │
│                                                                 │
│  Recent imports:                                                │
│  • products_import_2024-11-28.csv (500 rows)   [Reuse Settings]│
│  • users_data.xlsx (1,250 rows)                [Reuse Settings]│
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                    [Cancel]  [Next Step →]      │
└─────────────────────────────────────────────────────────────────┘
```

**Features:**
- ✅ Drag & Drop file upload
- ✅ File format auto-detection (CSV, JSON, XLS, XLSX)
- ✅ Import from URL
- ✅ Recent imports with "Reuse Settings" option
- ✅ File validation and preview

---

### Step 2: Content Type Selection

```
┌─────────────────────────────────────────────────────────────────┐
│ 📥 Import Data - Step 2 of 5: Select Content Type               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  What do you want to import?                                    │
│                                                                 │
│  ┌─── WordPress Core ────────────────────────────────────┐     │
│  │  ○ Posts             ○ Pages           ○ Media        │     │
│  │  ○ Users             ○ Comments        ○ Taxonomies   │     │
│  │  ○ Menus             ○ Nav Menu Items                 │     │
│  └───────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Custom Post Types ─────────────────────────────────┐     │
│  │  ○ Portfolio         ○ Testimonials    ○ Events       │     │
│  │  ○ [Custom CPT...]                                    │     │
│  └───────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── WooCommerce ───────────────────────────────────────┐     │
│  │  ○ Products          ○ Product Variations             │     │
│  │  ○ Orders            ○ Coupons                        │     │
│  │  ○ Product Attributes ○ Product Categories            │     │
│  └───────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Advanced ──────────────────────────────────────────┐     │
│  │  ○ Custom MySQL Table                                 │     │
│  │    Table name: [wp_custom_table_______] [Select ▼]   │     │
│  └───────────────────────────────────────────────────────┘     │
│                                                                 │
│  💡 Tip: Your import file contains 500 rows, 12 columns        │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [← Back]  [Cancel]  [Next Step →]  │
└─────────────────────────────────────────────────────────────────┘
```

**Supported Content Types:**

#### WordPress Core:
- Posts
- Pages
- Media (attachments)
- Users
- Comments
- Taxonomies (Categories, Tags, Custom Taxonomies)
- Menus
- Nav Menu Items

#### Custom Post Types:
- Any registered CPT
- Auto-detected from site

#### WooCommerce:
- Products (Simple, Variable, Grouped, External)
- Product Variations
- Orders
- Coupons
- Product Attributes
- Product Categories
- Product Tags

#### Advanced:
- Custom MySQL Table (direct database import)

---

### Step 3: Column Selection (Drag & Drop)

```
┌─────────────────────────────────────────────────────────────────┐
│ 📥 Import Data - Step 3 of 5: Select Columns                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Drag columns from LEFT to RIGHT to import them                 │
│                                                                 │
│  ┌─── Available Columns (12) ──┐   ┌─── Columns to Import ───┐ │
│  │                              │   │                          │ │
│  │  [≡] product_id              │   │  [≡] product_name        │ │
│  │  [≡] product_price           │   │  [≡] description         │ │
│  │  [≡] product_image_url       │   │  [≡] price               │ │
│  │  [≡] category                │   │  [≡] stock_quantity      │ │
│  │  [≡] tags                    │   │  [≡] categories          │ │
│  │  [≡] custom_field_1          │   │  [≡] featured_image      │ │
│  │  [≡] internal_notes          │   │                          │ │
│  │  [≡] legacy_id               │   │     👆 Drag here        │ │
│  │                              │   │                          │ │
│  │  [Select All] [Deselect All] │   │  Selected: 6 columns     │ │
│  └──────────────────────────────┘   └──────────────────────────┘ │
│                                                                 │
│  Preview (first 3 rows):                                        │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ product_name    │ price  │ stock_quantity │ categories  │   │
│  ├─────────────────┼────────┼────────────────┼─────────────┤   │
│  │ Red T-Shirt     │ 29.99  │ 150            │ Clothing    │   │
│  │ Blue Jeans      │ 59.99  │ 80             │ Clothing    │   │
│  │ Leather Wallet  │ 39.99  │ 200            │ Accessories │   │
│  └─────────────────┴────────┴────────────────┴─────────────┘   │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [← Back]  [Cancel]  [Next Step →]  │
└─────────────────────────────────────────────────────────────────┘
```

**Features:**
- ✅ Drag & Drop column selection
- ✅ Visual preview of selected columns
- ✅ Data preview (first 3 rows)
- ✅ Select All / Deselect All
- ✅ Column count indicator

---

### Step 4: Field Mapping & Advanced Settings

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 📥 Import Data - Step 4 of 5: Map Fields                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Map your columns to WordPress fields:                                      │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ Import Column          →  WordPress Field              Actions       │   │
│  ├─────────────────────────────────────────────────────────────────────┤   │
│  │ product_name           →  [Post Title ▼]               [⚙️ Settings] │   │
│  │ description            →  [Post Content ▼]             [⚙️ Settings] │   │
│  │ price                  →  [_regular_price (meta) ▼]    [⚙️ Settings] │   │
│  │ stock_quantity         →  [_stock (meta) ▼]            [⚙️ Settings] │   │
│  │ categories             →  [Product Category ▼]         [⚙️ Settings] │   │
│  │ featured_image         →  [Featured Image (URL) ▼]     [⚙️ Settings] │   │
│  ├─────────────────────────────────────────────────────────────────────┤   │
│  │                                           [+ Add Custom Field Mapping] │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  Available WordPress Fields (for Posts):                                    │
│  ┌──────────────────────────────────────────────────────────┐              │
│  │ Standard Fields:                                         │              │
│  │  • Post Title           • Post Content      • Post Excerpt             │
│  │  • Post Date            • Post Modified     • Post Status              │
│  │  • Post Author          • Post Parent       • Menu Order               │
│  │  • Comment Status       • Ping Status       • Post Password            │
│  │  • Post Slug            • Featured Image    • Post Format              │
│  │                                                          │              │
│  │ Taxonomies:                                              │              │
│  │  • Categories           • Tags              • Custom Taxonomies        │
│  │                                                          │              │
│  │ Custom Fields (Meta):                                    │              │
│  │  • Any custom field     • WooCommerce fields • ACF fields              │
│  │                                                          │              │
│  │ ACF Fields (if installed):                               │              │
│  │  • Text                 • Textarea          • Number                   │
│  │  • Email                • URL               • Password                 │
│  │  • Image                • File              • Wysiwyg                  │
│  │  • Gallery              • Select            • Checkbox                 │
│  │  • Radio                • True/False        • Date Picker              │
│  │  • Color Picker         • Google Map        • Relationship             │
│  │  • Repeater 🔄         • Flexible Content   • Clone                    │
│  │  • Group                • Tab               • Message                  │
│  └──────────────────────────────────────────────────────────┘              │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                    [← Back]  [Cancel]  [Next Step →]        │
└─────────────────────────────────────────────────────────────────────────────┘
```

**WordPress Field Types Support:**

#### Core Fields:
- Post Title, Content, Excerpt
- Post Date, Modified Date
- Post Status (publish, draft, pending, private)
- Post Author
- Comment Status, Ping Status
- Post Slug, Post Password
- Menu Order, Post Parent
- Post Format

#### Media Fields:
- Featured Image (URL or ID)
- Gallery Images (URLs or IDs)
- Attachment metadata

#### Taxonomy Fields:
- Categories (by name or ID)
- Tags (by name or ID)
- Custom Taxonomies

#### Custom Fields (Meta):
- Any post meta field
- WooCommerce product fields (_regular_price, _sale_price, _sku, etc.)

#### ACF Pro Fields:
- **Text Fields**: Text, Textarea, Number, Email, URL, Password
- **Content Fields**: Wysiwyg Editor, oEmbed, Image, File, Gallery
- **Choice Fields**: Select, Checkbox, Radio Button, Button Group, True/False
- **Relational Fields**: Link, Post Object, Page Link, Relationship, Taxonomy, User
- **jQuery Fields**: Google Map, Date Picker, Date Time Picker, Time Picker, Color Picker
- **Layout Fields**: Message, Accordion, Tab, Group, **Repeater**, Flexible Content, Clone

---

### Step 4.1: Field Settings Modal (Popup)

Clicking [⚙️ Settings] opens advanced settings for each field:

```
┌─────────────────────────────────────────────────────────────────┐
│ ⚙️  Field Settings: product_name → Post Title                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─── Basic Mapping ─────────────────────────────────────┐     │
│  │  Import Column:  product_name                         │     │
│  │  Map to Field:   [Post Title ▼]                       │     │
│  │                                                        │     │
│  │  Default Value (if empty):                            │     │
│  │  [Untitled Product_____________________________]      │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Search & Replace ──────────────────────────────────┐     │
│  │  ☑ Enable Search & Replace                            │     │
│  │                                                        │     │
│  │  Replace: [old-domain.com___________]                 │     │
│  │  With:    [new-domain.com___________]                 │     │
│  │                                                        │     │
│  │  [+ Add Another Rule]                                 │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Apply Custom Function ─────────────────────────────┐     │
│  │  ☑ Apply Function                                      │     │
│  │                                                        │     │
│  │  Select Function: [Uppercase Text ▼]                  │     │
│  │                                                        │     │
│  │  Available functions:                                 │     │
│  │  • Uppercase Text        • Lowercase Text             │     │
│  │  • Capitalize Words      • Remove HTML Tags           │     │
│  │  • Trim Whitespace       • Custom Function (50+)      │     │
│  │                                                        │     │
│  │  [Create New Function]   [View All Functions]         │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Data Transformation ───────────────────────────────┐     │
│  │  ☐ Strip HTML tags                                    │     │
│  │  ☐ Decode HTML entities                               │     │
│  │  ☐ Convert encoding (from: UTF-8 to: UTF-8)          │     │
│  │  ☐ Sanitize filename (for slugs)                     │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  Preview transformation:                                        │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ Before: Red T-Shirt                                    │    │
│  │ After:  RED T-SHIRT                                    │    │
│  └────────────────────────────────────────────────────────┘    │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                       [Cancel]  [Save Settings]  │
└─────────────────────────────────────────────────────────────────┘
```

**Field Settings Include:**
- ✅ Field mapping selection
- ✅ Default value (if column is empty)
- ✅ Search & Replace (multiple rules)
- ✅ Custom Functions (from Function Snippets Library)
- ✅ Data transformation options
- ✅ Live preview of transformation

---

### Step 4.2: Image Import Settings

Special settings for image/media fields:

```
┌─────────────────────────────────────────────────────────────────┐
│ ⚙️  Field Settings: featured_image → Featured Image              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─── Image Import Options ──────────────────────────────┐     │
│  │  Import from:  ● URL  ○ Attachment ID  ○ File Path    │     │
│  │                                                        │     │
│  │  ☑ Auto-download images to Media Library              │     │
│  │                                                        │     │
│  │  If image already exists:                             │     │
│  │  ● Skip (use existing)                                │     │
│  │  ○ Download anyway (create duplicate)                 │     │
│  │  ○ Update existing attachment                         │     │
│  │                                                        │     │
│  │  Download timeout: [30_] seconds                      │     │
│  │                                                        │     │
│  │  Set as Featured Image: ☑ Yes                         │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Image Optimization (Premium) ──────────────────────┐     │
│  │  ☐ Resize images (max width: [1920] px)               │     │
│  │  ☐ Compress images (quality: [85__]%)                 │     │
│  │  ☐ Convert to WebP format                             │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Alternative Text & Title ──────────────────────────┐     │
│  │  Alt Text from: [product_name ▼]                      │     │
│  │  Image Title from: [Auto-generate from filename ▼]    │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                       [Cancel]  [Save Settings]  │
└─────────────────────────────────────────────────────────────────┘
```

**Image Import Features:**
- ✅ Auto-download from URL
- ✅ Skip duplicates (check by URL hash)
- ✅ Set as Featured Image
- ✅ Alt text & title mapping
- 👑 Premium: Image optimization, resize, compress, WebP

---

### Step 4.3: ACF Repeater Field Mapping

Special interface for ACF Repeater fields:

```
┌─────────────────────────────────────────────────────────────────┐
│ ⚙️  ACF Repeater Mapping: product_features (Repeater)            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Your import data structure:                                    │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ feature_1_name | feature_1_value | feature_2_name | ... │    │
│  │ Color          | Red              | Size           | ... │    │
│  └────────────────────────────────────────────────────────┘    │
│                                                                 │
│  Import method:                                                 │
│  ● Multiple columns (feature_1_name, feature_2_name, ...)      │
│  ○ Single column with delimiter (e.g., "Name:Value|Name:Value")│
│  ○ JSON format in single column                                │
│                                                                 │
│  ┌─── Repeater Rows ─────────────────────────────────────┐     │
│  │                                                        │     │
│  │  Row 1:                                                │     │
│  │    feature_name:  [feature_1_name ▼]                  │     │
│  │    feature_value: [feature_1_value ▼]                 │     │
│  │                                                        │     │
│  │  Row 2:                                                │     │
│  │    feature_name:  [feature_2_name ▼]                  │     │
│  │    feature_value: [feature_2_value ▼]                 │     │
│  │                                                        │     │
│  │  [+ Add Row]  [Auto-detect Rows]                      │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  Preview:                                                       │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ Feature Name  │ Feature Value                          │    │
│  ├───────────────┼────────────────────────────────────────┤    │
│  │ Color         │ Red                                    │    │
│  │ Size          │ Large                                  │    │
│  │ Material      │ Cotton                                 │    │
│  └────────────────┴────────────────────────────────────────┘    │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                       [Cancel]  [Save Settings]  │
└─────────────────────────────────────────────────────────────────┘
```

**ACF Repeater Support:**
- ✅ Multiple columns mapping (feature_1_name, feature_2_name, ...)
- ✅ Single column with delimiter
- ✅ JSON format support
- ✅ Auto-detect repeater rows
- ✅ Preview before import

---

### Step 5: Import Options & Duplicate Handling

```
┌─────────────────────────────────────────────────────────────────┐
│ 📥 Import Data - Step 5 of 5: Import Options                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─── Duplicate Detection ───────────────────────────────┐     │
│  │  Check for existing items by:                         │     │
│  │  ☑ Post Title                                         │     │
│  │  ☐ Post ID                                            │     │
│  │  ☐ Custom Field: [_sku__________] [+ Add Field]      │     │
│  │                                                        │     │
│  │  If duplicate found:                                  │     │
│  │  ● Skip (don't import)                                │     │
│  │  ○ Update existing item                               │     │
│  │  ○ Delete and recreate                                │     │
│  │  ○ Create duplicate anyway                            │     │
│  │                                                        │     │
│  │  When updating, merge strategy:                       │     │
│  │  ○ Replace all fields                                 │     │
│  │  ● Update only mapped fields                          │     │
│  │  ○ Don't update if field has value                    │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Import Settings ───────────────────────────────────┐     │
│  │  Post Status: [Publish ▼]                             │     │
│  │               (Draft, Pending Review, Private)        │     │
│  │                                                        │     │
│  │  Post Author: [Current User (admin) ▼]                │     │
│  │                                                        │     │
│  │  Post Date:   ● Use import date                       │     │
│  │               ○ Use current date                      │     │
│  │               ○ From column: [date_column ▼]          │     │
│  │                                                        │     │
│  │  Comment Status:  [Open ▼]                            │     │
│  │  Ping Status:     [Open ▼]                            │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Processing Options ────────────────────────────────┐     │
│  │  ☑ Process in background (recommended for 100+ items) │     │
│  │                                                        │     │
│  │  Batch size: [50_] items per batch                    │     │
│  │                                                        │     │
│  │  ☑ Send email notification when complete              │     │
│  │    Email: [admin@example.com__________________]       │     │
│  │                                                        │     │
│  │  ☐ Create import log file                            │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ┌─── Summary ───────────────────────────────────────────┐     │
│  │  Import file:     products_import.csv                 │     │
│  │  Content type:    WooCommerce Products                │     │
│  │  Total rows:      500 items                           │     │
│  │  Columns mapped:  6 fields                            │     │
│  │  Estimated time:  ~2-3 minutes                        │     │
│  └────────────────────────────────────────────────────────┘     │
│                                                                 │
│  ☑ I understand this will create/update 500 items              │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                              [← Back]  [Cancel]  [Start Import]  │
└─────────────────────────────────────────────────────────────────┘
```

**Duplicate Detection Methods:**
- ✅ By Post Title (exact match or fuzzy)
- ✅ By Post ID
- ✅ By Custom Field (e.g., SKU, external ID)
- ✅ Multiple fields combination

**Duplicate Actions:**
- Skip (don't import)
- Update existing (merge)
- Delete and recreate
- Create duplicate anyway

**Update Strategies:**
- Replace all fields
- Update only mapped fields
- Don't update if field has value (preserve existing)

---

### Step 6: Import Progress

```
┌─────────────────────────────────────────────────────────────────┐
│ 📥 Importing Products...                                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Overall Progress:                                              │
│  ████████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 45% (225/500)│
│                                                                 │
│  Current Operation: Importing "Blue Jeans"...                   │
│                                                                 │
│  Statistics:                                                    │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  ✅ Created:     200 items                              │   │
│  │  🔄 Updated:      25 items                              │   │
│  │  ⏭️  Skipped:      0 items (duplicates)                 │   │
│  │  ❌ Failed:       0 items                               │   │
│  │                                                         │   │
│  │  📥 Images downloaded: 180 / 225                        │   │
│  │  ⏱️  Elapsed time: 1 min 23 sec                         │   │
│  │  ⏱️  Estimated remaining: 1 min 45 sec                  │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Recent activity:                                               │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  ✅ Created "Red T-Shirt" (ID: 1234)                    │   │
│  │  ✅ Created "Blue Jeans" (ID: 1235)                     │   │
│  │  🔄 Updated "Leather Wallet" (ID: 987)                  │   │
│  │  📥 Downloaded image: red-tshirt.jpg                    │   │
│  │  ✅ Created "Summer Dress" (ID: 1236)                   │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  [Pause Import]  [Cancel Import]                                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Progress Features:**
- ✅ Real-time progress bar
- ✅ Live statistics (created, updated, skipped, failed)
- ✅ Image download progress
- ✅ Time estimates (elapsed, remaining)
- ✅ Recent activity log
- ✅ Pause/Cancel options
- ✅ Background processing support

---

### Step 7: Import Complete

```
┌─────────────────────────────────────────────────────────────────┐
│ ✅ Import Complete!                                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  🎉 Your import has finished successfully!                      │
│                                                                 │
│  Final Statistics:                                              │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  ✅ Created:     475 items                              │   │
│  │  🔄 Updated:      25 items                              │   │
│  │  ⏭️  Skipped:      0 items                              │   │
│  │  ❌ Failed:       0 items                               │   │
│  │                                                         │   │
│  │  📥 Images downloaded: 500 images                       │   │
│  │  ⏱️  Total time: 3 min 18 sec                           │   │
│  │  📊 Average speed: 151 items/minute                     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Actions:                                                       │
│  [View Imported Items]  [Download Log File]  [Import More]      │
│                                                                 │
│  ☑ Save these import settings for future use                   │
│    Template name: [Products Import Template___________] [Save] │
│                                                                 │
│  📧 Email notification sent to admin@example.com                │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                    [Done]        │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎛️ Admin Menu Structure

```
WordPress Admin Menu:
├─ Dashboard
├─ Posts
├─ Media
├─ Pages
├─ ...
└─ 📊 Import/Export ← NEW TOP-LEVEL MENU
   ├─ 📥 Import           (Main import wizard)
   ├─ 📤 Export           (Export data)
   ├─ 📜 History           (Import/Export logs)
   ├─ 🔧 Custom Functions  (Function editor)
   ├─ 📚 Function Library  (50+ snippets)
   ├─ 📁 Media Sync        (FTP → Media Library)
   ├─ 🔄 Content Sync      (Site-to-Site sync)
   └─ ⚙️  Settings         (Plugin settings)
```

**Each submenu has its own dedicated page.**

---

## 📊 Import History Page

```
┌─────────────────────────────────────────────────────────────────┐
│ 📜 Import History                                   [Clear All]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Filters: [All Types ▼] [All Statuses ▼] [Last 30 days ▼]      │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ Date       │ Type     │ File    │ Items │ Status │ ⚡  │   │
│  ├────────────┼──────────┼─────────┼───────┼────────┼─────┤   │
│  │ 2024-11-28 │ Products │ prod... │ 500   │ ✅ Done │ [↻] │   │
│  │ 10:30 AM   │          │         │       │        │ [👁] │   │
│  │            │          │         │       │        │ [📄] │   │
│  ├────────────┼──────────┼─────────┼───────┼────────┼─────┤   │
│  │ 2024-11-27 │ Users    │ user... │ 1250  │ ✅ Done │ [↻] │   │
│  │ 03:15 PM   │          │         │       │        │ [👁] │   │
│  │            │          │         │       │        │ [📄] │   │
│  ├────────────┼──────────┼─────────┼───────┼────────┼─────┤   │
│  │ 2024-11-26 │ Posts    │ post... │ 350   │ ❌ Fail │ [↻] │   │
│  │ 09:00 AM   │          │         │       │        │ [👁] │   │
│  │            │          │         │       │        │ [📄] │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Actions:                                                       │
│  [↻] Rerun import with same settings                           │
│  [👁] View details                                              │
│  [📄] Download log file                                         │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎨 UI/UX Principles

### Design Guidelines:
1. **Progressive Disclosure** - Show advanced options only when needed
2. **Visual Feedback** - Clear progress indicators and status messages
3. **Error Prevention** - Validation before import starts
4. **Undo Support** - Ability to revert imports (Premium)
5. **Accessibility** - WCAG 2.1 AA compliant
6. **Responsive** - Works on all screen sizes
7. **Performance** - Handles 10,000+ rows efficiently

### Color Coding:
- 🟢 Green: Success, created items
- 🔵 Blue: Updated items
- 🟡 Yellow: Skipped items
- 🔴 Red: Failed items, errors
- ⚪ Gray: Pending, in progress

---

## 🔌 Technical Implementation

### Backend (PHP):
```php
// Main import controller
class Import_Controller {
    public function process_step_1() {} // File upload
    public function process_step_2() {} // Content type selection
    public function process_step_3() {} // Column selection
    public function process_step_4() {} // Field mapping
    public function process_step_5() {} // Import options
    public function start_import() {}   // Start background job
}

// Import processors for each content type
class Post_Importer {}
class User_Importer {}
class Product_Importer {}
class Custom_Table_Importer {}
// ... etc
```

### Frontend (JavaScript):
```javascript
// Main import wizard
class ImportWizard {
    constructor() {
        this.currentStep = 1;
        this.importData = {};
    }
    
    nextStep() {}
    prevStep() {}
    uploadFile() {}
    mapFields() {}
    startImport() {}
    updateProgress() {}
}

// Drag & Drop column selector
class ColumnSelector {
    initDragDrop() {}
    onColumnDrop() {}
}

// Field mapping component
class FieldMapper {
    openSettingsModal() {}
    applyFunction() {}
    previewTransform() {}
}
```

---

## 📋 Summary

### Wizard Steps:
1. ✅ File Upload & Format Detection
2. ✅ Content Type Selection (Posts, Products, Users, Custom Tables, etc.)
3. ✅ Column Selection (Drag & Drop)
4. ✅ Field Mapping + Advanced Settings (per-field)
5. ✅ Import Options + Duplicate Handling
6. ✅ Import Progress (Real-time)
7. ✅ Import Complete + Save Template

### Key Features:
- ✅ Drag & Drop column selection
- ✅ Advanced field mapping (all WordPress + ACF fields)
- ✅ Per-field settings popup (Search/Replace, Functions, Transformations)
- ✅ Auto-download images from URLs
- ✅ Skip duplicates (by Title, ID, Custom Field)
- ✅ Duplicate handling (Skip, Update, Delete, Create)
- ✅ ACF Repeater field support
- ✅ Custom MySQL table import
- ✅ Background processing
- ✅ Real-time progress tracking
- ✅ Import history & logs
- ✅ Save/reuse import templates

### Supported Content Types:
- WordPress: Posts, Pages, Users, Comments, Taxonomies, Menus
- Custom Post Types: Any registered CPT
- WooCommerce: Products, Variations, Orders, Coupons, Attributes
- Custom MySQL Tables: Direct database import
- Media: Images with auto-download

### Supported File Formats:
- CSV (with any delimiter)
- JSON
- XLS (Excel 97-2003)
- XLSX (Excel 2007+)

---

**Status**: 📋 Specification Complete  
**Ready for**: UI/UX Design → Development → Testing  
**Estimated Implementation Time**: ~80 hours

