# Export UI System - Спецификация

## 📋 Обзор

Система экспорта с пошаговым визардом, расширенными фильтрами и поддержкой всех типов контента WordPress.

**Версия**: 1.0  
**Дата**: 2024  
**Статус**: Specification

---

## 🎯 Основные возможности

### Поддержка типов контента
- **WordPress Core**: Posts, Pages, Custom Post Types, Users, Comments, Taxonomies (Categories, Tags, Custom), Menus, Nav Menu Items
- **WooCommerce**: Products, Product Variations, Orders, Coupons, Attributes
- **ACF Pro**: Все типы ACF полей (включая Repeater, Flexible Content, Group)
- **Yoast SEO**: SEO meta данные (title, description, keywords, canonical, robots)

### Форматы экспорта
- **CSV** - совместимый с Excel, UTF-8 BOM
- **JSON** - структурированный, с вложенными объектами
- **XLS** - старый формат Excel (BIFF)
- **XLSX** - современный формат Excel (Office 2007+)

### Продвинутые фильтры
- **Posts/Pages**: Status, Date Range, Author, Category, Tag, Custom Taxonomy
- **Users**: Role, Registration Date, Meta Fields
- **Products**: Stock Status, Price Range, Category, Tag, Attribute
- **Comments**: Status, Date Range, Post ID, Author
- **Taxonomies**: Taxonomy Type, Parent Term

### Трансформация данных
- **Search & Replace** - неограниченное количество правил
- **Custom Functions** - применение функций из библиотеки
- **Field Settings** - настройки для каждого поля отдельно

### Background Processing
- Фоновая обработка больших экспортов
- Сохранение файла в `wp-content/uploads/aie/exports/`
- Email уведомление по завершении
- Возможность скачать файл из истории экспортов

---

## 🔧 Архитектура Export Wizard

### 5-Step Export Wizard

```
Step 1: Content Type Selection
   ↓
Step 2: Filters & Query Builder
   ↓
Step 3: Field Selection & Mapping
   ↓
Step 4: Export Options & Format
   ↓
Step 5: Progress & Download
```

---

## 📐 Wireframes & UI Components

### Step 1: Content Type Selection

```
┌─────────────────────────────────────────────────────────────┐
│ Export Wizard - Step 1 of 5: Select Content Type            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Select what you want to export:                            │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  [ WordPress Content ]                               │  │
│  │                                                       │  │
│  │  (•) Posts            ( ) Pages                      │  │
│  │  ( ) Users            ( ) Comments                   │  │
│  │  ( ) Categories       ( ) Tags                       │  │
│  │  ( ) Custom Taxonomy  ( ) Menus                      │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  [ Custom Post Types ]                               │  │
│  │                                                       │  │
│  │  ( ) Portfolio        ( ) Testimonials               │  │
│  │  ( ) Events           ( ) Products (CPT)             │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  [ WooCommerce ] (if installed)                      │  │
│  │                                                       │  │
│  │  ( ) Products         ( ) Product Variations         │  │
│  │  ( ) Orders           ( ) Coupons                    │  │
│  │  ( ) Attributes                                      │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ℹ️ Estimated items: 1,234 posts                            │
│                                                              │
│                                 [Cancel]  [Next: Filters →] │
└─────────────────────────────────────────────────────────────┘
```

**Функциональность**:
- Радио-кнопки для выбора одного типа контента
- Группировка по категориям (WP Core, CPT, WooCommerce)
- Динамическое отображение счетчика элементов
- Скрытие недоступных опций (например, WooCommerce если не установлен)

---

### Step 2: Filters & Query Builder

#### Для Posts/Pages:

```
┌─────────────────────────────────────────────────────────────┐
│ Export Wizard - Step 2 of 5: Filter Posts                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Apply filters to narrow down your export:                  │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  Post Status:                                        │  │
│  │  ☑ Published  ☑ Draft  ☐ Pending  ☐ Private  ☐ Trash│  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Date Range:                                         │  │
│  │  (•) All dates                                       │  │
│  │  ( ) Custom range:                                   │  │
│  │      From: [2024-01-01] To: [2024-12-31]            │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Author:                                             │  │
│  │  [Select authors...          ▼]                     │  │
│  │  Selected: John Doe, Jane Smith                      │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Categories:                                         │  │
│  │  [Select categories...       ▼]                     │  │
│  │  Selected: News, Blog, Tutorials                     │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Tags:                                               │  │
│  │  [Select tags...             ▼]                     │  │
│  │  Selected: WordPress, PHP, JavaScript                │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Custom Taxonomies:                                  │  │
│  │  [+ Add taxonomy filter]                            │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Custom Fields (Meta):                               │  │
│  │  [+ Add meta query]                                 │  │
│  │                                                       │  │
│  │  Example:                                            │  │
│  │  ┌─────────────────────────────────────────────┐    │  │
│  │  │ Meta Key: [views_count    ▼]               │    │  │
│  │  │ Operator: [Greater than   ▼]               │    │  │
│  │  │ Value:    [1000          ]  [Remove]       │    │  │
│  │  └─────────────────────────────────────────────┘    │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Post ID Range:                                      │  │
│  │  ☐ Specific IDs: [1,5,10,15-20]                     │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ℹ️ Filtered results: 234 of 1,234 posts                   │
│  [Preview filtered posts]                                   │
│                                                              │
│                            [← Back]  [Next: Select Fields →]│
└─────────────────────────────────────────────────────────────┘
```

#### Для WooCommerce Products:

```
┌─────────────────────────────────────────────────────────────┐
│ Export Wizard - Step 2 of 5: Filter Products                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  Product Type:                                       │  │
│  │  ☑ Simple  ☑ Variable  ☑ Grouped  ☐ External        │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Stock Status:                                       │  │
│  │  ☑ In Stock  ☑ Out of Stock  ☐ On Backorder         │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Price Range:                                        │  │
│  │  Min: [$0.00    ] Max: [$1000.00]                   │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Product Categories:                                 │  │
│  │  [Select categories...       ▼]                     │  │
│  │                                                       │  │
│  │  Product Tags:                                       │  │
│  │  [Select tags...             ▼]                     │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Attributes:                                         │  │
│  │  [+ Add attribute filter]                           │  │
│  │                                                       │  │
│  │  SKU:                                                │  │
│  │  ☐ Specific SKUs: [SKU-001, SKU-002]                │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ℹ️ Filtered results: 89 of 456 products                   │
│                                                              │
│                            [← Back]  [Next: Select Fields →]│
└─────────────────────────────────────────────────────────────┘
```

#### Для Users:

```
┌─────────────────────────────────────────────────────────────┐
│ Export Wizard - Step 2 of 5: Filter Users                   │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  User Roles:                                         │  │
│  │  ☑ Administrator  ☑ Editor  ☑ Author  ☐ Contributor │  │
│  │  ☐ Subscriber     ☐ Customer (WooCommerce)          │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Registration Date:                                  │  │
│  │  (•) All dates                                       │  │
│  │  ( ) Custom range:                                   │  │
│  │      From: [2024-01-01] To: [2024-12-31]            │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  User Meta Filters:                                  │  │
│  │  [+ Add meta query]                                 │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ℹ️ Filtered results: 45 of 123 users                      │
│                                                              │
│                            [← Back]  [Next: Select Fields →]│
└─────────────────────────────────────────────────────────────┘
```

**Функциональность**:
- Динамические фильтры в зависимости от типа контента
- Multi-select для статусов, категорий, тегов
- Date picker для диапазонов дат
- Условия для meta queries (=, !=, >, <, LIKE, IN, NOT IN, EXISTS, NOT EXISTS)
- Live preview счетчика отфильтрованных элементов
- Кнопка "Preview filtered posts" открывает модальное окно со списком

---

### Step 3: Field Selection & Mapping

```
┌─────────────────────────────────────────────────────────────┐
│ Export Wizard - Step 3 of 5: Select Fields                  │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Select which fields to export:                             │
│                                                              │
│  ┌───────────────────────────┬──────────────────────────┐  │
│  │ Available Fields          │ Selected Fields (5)      │  │
│  ├───────────────────────────┼──────────────────────────┤  │
│  │                           │                          │  │
│  │ 🔍 Search fields...       │  [Clear all] [Select all]│  │
│  │                           │                          │  │
│  │ ▼ WordPress Core          │  ┌────────────────────┐ │  │
│  │   ☐ ID                    │  │ 1. Post Title      │ │  │
│  │   ☑ Post Title      [+]   │  │    [⚙️] [↑] [↓] [×]│ │  │
│  │   ☑ Post Content    [+]   │  └────────────────────┘ │  │
│  │   ☐ Post Excerpt          │                          │  │
│  │   ☑ Post Date       [+]   │  ┌────────────────────┐ │  │
│  │   ☐ Post Modified         │  │ 2. Post Content    │ │  │
│  │   ☑ Post Status     [+]   │  │    [⚙️] [↑] [↓] [×]│ │  │
│  │   ☐ Post Author           │  └────────────────────┘ │  │
│  │   ☐ Post Parent           │                          │  │
│  │   ☐ Post Slug             │  ┌────────────────────┐ │  │
│  │   ☐ Menu Order            │  │ 3. Post Date       │ │  │
│  │   ☐ Comment Status        │  │    [⚙️] [↑] [↓] [×]│ │  │
│  │                           │  └────────────────────┘ │  │
│  │ ▼ Taxonomies              │                          │  │
│  │   ☐ Categories            │  ┌────────────────────┐ │  │
│  │   ☐ Tags                  │  │ 4. Post Status     │ │  │
│  │   ☐ Product Categories    │  │    [⚙️] [↑] [↓] [×]│ │  │
│  │                           │  └────────────────────┘ │  │
│  │ ▼ ACF Fields              │                          │  │
│  │   ☐ Custom Field 1        │  ┌────────────────────┐ │  │
│  │   ☐ Custom Field 2        │  │ 5. Featured Image  │ │  │
│  │   ☑ Featured Image   [+]  │  │    [⚙️] [↑] [↓] [×]│ │  │
│  │   ☐ Gallery (Repeater)    │  └────────────────────┘ │  │
│  │                           │                          │  │
│  │ ▼ Yoast SEO               │   Drag to reorder       │  │
│  │   ☐ SEO Title             │                          │  │
│  │   ☐ Meta Description      │                          │  │
│  │   ☐ Focus Keyword         │                          │  │
│  │                           │                          │  │
│  │ ▼ WooCommerce             │                          │  │
│  │   ☐ SKU                   │                          │  │
│  │   ☐ Regular Price         │                          │  │
│  │   ☐ Sale Price            │                          │  │
│  │   ☐ Stock Quantity        │                          │  │
│  │                           │                          │  │
│  └───────────────────────────┴──────────────────────────┘  │
│                                                              │
│  💡 Tip: Click [⚙️] to configure field settings             │
│  (search/replace, custom functions, formatting)             │
│                                                              │
│                      [← Back]  [Next: Export Options →]     │
└─────────────────────────────────────────────────────────────┘
```

**Функциональность**:
- **Drag & Drop** перемещение полей между списками
- **Сортировка** выбранных полей (↑ ↓)
- **Группировка** полей по источникам (WP Core, ACF, Yoast, WC)
- **Поиск** по имени поля
- **Bulk actions**: Select all / Clear all
- **Field Settings** - клик на [⚙️] открывает модалку настроек

---

### Field Settings Modal

```
┌─────────────────────────────────────────────────────────────┐
│ Field Settings: Post Title                           [×]    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  [ Basic Settings ]  [ Advanced ]                           │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  Column Name in Export:                              │  │
│  │  [Post Title                    ]                    │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Default Value (if empty):                           │  │
│  │  [(No title)                    ]                    │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Search & Replace:                                   │  │
│  │  ☑ Enable search & replace                          │  │
│  │                                                       │  │
│  │  ┌─────────────────────────────────────────────┐    │  │
│  │  │ Rule 1:                                     │    │  │
│  │  │ Search:  [http://oldsite.com]              │    │  │
│  │  │ Replace: [https://newsite.com]             │    │  │
│  │  │ ☑ Case sensitive  ☐ Use regex              │    │  │
│  │  │                              [Remove Rule] │    │  │
│  │  └─────────────────────────────────────────────┘    │  │
│  │                                                       │  │
│  │  [+ Add another rule]                                │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Apply Custom Function:                              │  │
│  │  ☑ Enable function transformation                   │  │
│  │                                                       │  │
│  │  Select function:                                    │  │
│  │  [Uppercase              ▼]                         │  │
│  │                                                       │  │
│  │  Available functions:                                │  │
│  │  • Uppercase                                         │  │
│  │  • Lowercase                                         │  │
│  │  • Trim whitespace                                   │  │
│  │  • Strip HTML tags                                   │  │
│  │  • Custom function...                                │  │
│  │                                                       │  │
│  │  [Browse all functions →]                           │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Preview Transformation:                             │  │
│  │  ┌─────────────────────────────────────────────┐    │  │
│  │  │ Original:                                   │    │  │
│  │  │ "Hello World from http://oldsite.com"       │    │  │
│  │  │                                             │    │  │
│  │  │ After transformation:                       │    │  │
│  │  │ "HELLO WORLD FROM HTTPS://NEWSITE.COM"      │    │  │
│  │  └─────────────────────────────────────────────┘    │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│                                       [Cancel]  [Save Settings]│
└─────────────────────────────────────────────────────────────┘
```

**Функциональность**:
- Переименование колонки в экспорте
- Default value если поле пустое
- Search & Replace (неограниченное количество правил)
- Regex поддержка
- Применение Custom Functions из библиотеки
- Live preview трансформации
- Tabs для Basic/Advanced настроек

---

### Step 4: Export Options & Format

```
┌─────────────────────────────────────────────────────────────┐
│ Export Wizard - Step 4 of 5: Export Options                 │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Configure export settings:                                 │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  Export Format:                                      │  │
│  │  (•) CSV   ( ) JSON   ( ) XLS   ( ) XLSX            │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  CSV Options: (visible only for CSV)                 │  │
│  │  Delimiter:     [,  ▼] (comma, semicolon, tab)      │  │
│  │  Enclosure:     ["  ▼] (double quote, single quote) │  │
│  │  ☑ Include BOM (UTF-8 signature for Excel)          │  │
│  │  ☑ Include column headers                           │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  JSON Options: (visible only for JSON)               │  │
│  │  ☑ Pretty print (formatted with indentation)        │  │
│  │  ☑ Include metadata (count, timestamp, filters)     │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  File Name:                                          │  │
│  │  [posts-export-2024-11-28.csv              ]        │  │
│  │  Auto-generated based on content type and date       │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Processing:                                         │  │
│  │  (•) Background processing (recommended)            │  │
│  │      Process in batches, file saved to uploads/     │  │
│  │                                                       │  │
│  │  ( ) Direct download                                │  │
│  │      Immediate download (max 1000 items)            │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Batch Size: [50  ▼] items per batch                │  │
│  │  (Lower = slower but safer for large exports)       │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Notifications:                                      │  │
│  │  ☑ Send email when export completes                 │  │
│  │  Email: [admin@example.com                  ]       │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Save as Template:                                   │  │
│  │  ☑ Save this export configuration                   │  │
│  │  Template name: [Posts Export 2024]                 │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  Export Summary:                                            │
│  • Content type: Posts                                      │
│  • Filters: Published, Date: 2024-01-01 to 2024-12-31      │
│  • Fields: 5 selected                                       │
│  • Format: CSV (UTF-8 with BOM)                             │
│  • Estimated items: 234 posts                               │
│  • Estimated file size: ~2.5 MB                             │
│  • Estimated time: ~30 seconds                              │
│                                                              │
│                        [← Back]  [Start Export →]           │
└─────────────────────────────────────────────────────────────┘
```

**Функциональность**:
- Выбор формата экспорта (CSV/JSON/XLS/XLSX)
- Настройки для каждого формата
- Настройка имени файла
- Background vs Direct download
- Batch size настройка
- Email уведомления
- Сохранение как шаблон
- Summary с оценками

---

### Step 5: Export Progress & Download

```
┌─────────────────────────────────────────────────────────────┐
│ Export Wizard - Step 5 of 5: Export in Progress             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Exporting your data...                                     │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  Progress:                                           │  │
│  │  ████████████████████░░░░░░░░░  65%                 │  │
│  │                                                       │  │
│  │  Status: Processing batch 7 of 10                    │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Details:                                            │  │
│  │  • Total items:      234                             │  │
│  │  • Processed:        152                             │  │
│  │  • Remaining:        82                              │  │
│  │  • Success:          150                             │  │
│  │  • Errors:           2                               │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Time:                                               │  │
│  │  • Elapsed:          20 seconds                      │  │
│  │  • Estimated remaining: 10 seconds                   │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Recent items:                                       │  │
│  │  ✓ Post #123: "Sample Post Title"                   │  │
│  │  ✓ Post #124: "Another Post"                        │  │
│  │  ✗ Post #125: "Failed Post" (Error: Missing field)  │  │
│  │  ✓ Post #126: "Success Post"                        │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  [View detailed log]                     [Pause]  [Cancel]  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

#### После завершения:

```
┌─────────────────────────────────────────────────────────────┐
│ Export Wizard - Step 5 of 5: Export Complete! ✓             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  🎉 Export completed successfully!                          │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  Summary:                                            │  │
│  │  • Total items exported:  234                        │  │
│  │  • Successful:            232                        │  │
│  │  • Errors:                2                          │  │
│  │  • File size:             2.3 MB                     │  │
│  │  • Duration:              28 seconds                 │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Export File:                                        │  │
│  │  📄 posts-export-2024-11-28.csv                      │  │
│  │                                                       │  │
│  │  File location:                                      │  │
│  │  wp-content/uploads/aie/exports/posts-export-202... │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Actions:                                            │  │
│  │  [📥 Download File]  [👁️ Preview]  [📋 View Log]     │  │
│  │                                                       │  │
│  │  ─────────────────────────────────────────────────   │  │
│  │                                                       │  │
│  │  Errors (2):                                         │  │
│  │  ⚠️ Post #125: Missing required field 'post_title'  │  │
│  │  ⚠️ Post #189: ACF field not found                  │  │
│  │                                                       │  │
│  │  [Download error log]                                │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  Template saved as: "Posts Export 2024"                     │
│  You can reuse this configuration from Export History.      │
│                                                              │
│  ✉️ Email notification sent to admin@example.com           │
│                                                              │
│                [View Export History]  [New Export]          │
└─────────────────────────────────────────────────────────────┘
```

**Функциональность**:
- Real-time progress bar
- Счетчики: Total/Processed/Success/Errors
- Estimated time remaining
- Recent items log (последние 4-5)
- Pause/Cancel возможность
- После завершения:
  - Summary с статистикой
  - Download button
  - Preview file (первые 100 строк)
  - View full log
  - Error details (если есть)
  - Template confirmation

---

## 📊 Export History Page

```
┌─────────────────────────────────────────────────────────────┐
│ Export History                                               │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  [New Export]  [Export Templates ▼]                         │
│                                                              │
│  Filters: [All Statuses ▼] [All Types ▼] [Last 30 days ▼]  │
│  Search: [Search exports...                    ] [🔍]       │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ Date & Time      │ Type    │ Format│ Items│ Status   │  │
│  ├──────────────────┼─────────┼───────┼──────┼──────────┤  │
│  │ 2024-11-28 14:30│ Posts   │ CSV   │ 234  │ ✓ Complete│  │
│  │                 │         │       │      │ [Download]│  │
│  │                 │         │       │      │ [Preview] │  │
│  │                 │         │       │      │ [Rerun]   │  │
│  │                 │         │       │      │ [Delete]  │  │
│  ├──────────────────┼─────────┼───────┼──────┼──────────┤  │
│  │ 2024-11-27 10:15│ Products│ XLSX  │ 89   │ ✓ Complete│  │
│  │                 │         │       │      │ [Download]│  │
│  ├──────────────────┼─────────┼───────┼──────┼──────────┤  │
│  │ 2024-11-26 16:45│ Users   │ JSON  │ 45   │ ✓ Complete│  │
│  │                 │         │       │      │ [Download]│  │
│  ├──────────────────┼─────────┼───────┼──────┼──────────┤  │
│  │ 2024-11-25 09:20│ Posts   │ CSV   │ 1234 │ 🔄 Processing│
│  │                 │         │       │      │ 65% (800) │  │
│  │                 │         │       │      │ [View]    │  │
│  ├──────────────────┼─────────┼───────┼──────┼──────────┤  │
│  │ 2024-11-24 13:00│ Comments│ CSV   │ 567  │ ✗ Failed  │  │
│  │                 │         │       │      │ [View Log]│  │
│  │                 │         │       │      │ [Retry]   │  │
│  └──────────────────┴─────────┴───────┴──────┴──────────┘  │
│                                                              │
│  Showing 1-5 of 23 exports         [< Previous] [Next >]   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

**Функциональность**:
- Список всех экспортов с фильтрами
- Статусы: Complete, Processing, Failed, Cancelled
- Actions для каждого экспорта:
  - Download - скачать файл
  - Preview - просмотр первых 100 строк
  - Rerun - повторить экспорт с теми же настройками
  - Delete - удалить экспорт
  - View Log - просмотр детального лога
  - Retry - повторить (для Failed)
- Pagination
- Bulk actions

---

## 📋 Export Templates Page

```
┌─────────────────────────────────────────────────────────────┐
│ Export Templates                                             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Saved export configurations for quick reuse.               │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  📋 Posts Export 2024                                │  │
│  │  ────────────────────────────────────────────────    │  │
│  │  Content: Posts (Published)                          │  │
│  │  Fields: 5 selected                                  │  │
│  │  Filters: Date range, Categories                     │  │
│  │  Format: CSV (UTF-8 with BOM)                        │  │
│  │  Last used: 2024-11-28                               │  │
│  │                                                       │  │
│  │  [Use Template] [Edit] [Duplicate] [Delete]         │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  📋 WooCommerce Products Weekly                      │  │
│  │  ────────────────────────────────────────────────    │  │
│  │  Content: Products (All types)                       │  │
│  │  Fields: 12 selected (SKU, Prices, Stock)           │  │
│  │  Filters: In stock, Price > $0                      │  │
│  │  Format: XLSX                                        │  │
│  │  Last used: 2024-11-27                               │  │
│  │                                                       │  │
│  │  [Use Template] [Edit] [Duplicate] [Delete]         │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │                                                       │  │
│  │  📋 User List with Meta                              │  │
│  │  ────────────────────────────────────────────────    │  │
│  │  Content: Users (All roles)                          │  │
│  │  Fields: 8 selected                                  │  │
│  │  Filters: None                                       │  │
│  │  Format: JSON                                        │  │
│  │  Last used: 2024-11-26                               │  │
│  │                                                       │  │
│  │  [Use Template] [Edit] [Duplicate] [Delete]         │  │
│  │                                                       │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│                                        [+ Create New Template]│
└─────────────────────────────────────────────────────────────┘
```

**Функциональность**:
- Список сохраненных шаблонов
- Карточки с описанием конфигурации
- Actions:
  - Use Template - запустить экспорт с этой конфигурацией
  - Edit - изменить шаблон
  - Duplicate - создать копию
  - Delete - удалить шаблон
- Create New Template - создать шаблон вручную

---

## 🔧 Technical Architecture

### Backend Components

#### Export_Wizard_Controller
**File**: `app/controller/export_wizard_controller.php`

```php
class Export_Wizard_Controller {
    
    // Step 1: Content Type Selection
    public function process_step_1($content_type, $sub_type = null)
    
    // Step 2: Filters
    public function process_step_2($filters)
    
    // Step 3: Field Selection
    public function process_step_3($selected_fields, $field_settings = [])
    
    // Step 4: Export Options
    public function process_step_4($options)
    
    // Step 5: Start Export
    public function start_export()
    
    // Helpers
    protected function get_available_fields($content_type, $sub_type)
    protected function estimate_items_count($content_type, $filters)
    protected function create_export_job($data)
    protected function schedule_background_export($job_id)
}
```

#### Content Type Exporters

**Post_Exporter** (`app/exporter/post_exporter.php`):
```php
class Post_Exporter extends Base_Exporter {
    
    public function export($filters, $fields, $field_settings, $format)
    
    protected function build_query($filters)
    protected function get_posts_batch($offset, $limit)
    protected function map_post_data($post, $fields)
    protected function apply_field_settings($value, $settings)
    protected function export_acf_fields($post_id, $fields)
    protected function export_yoast_seo($post_id, $fields)
    protected function export_taxonomies($post_id, $fields)
}
```

**User_Exporter** (`app/exporter/user_exporter.php`):
```php
class User_Exporter extends Base_Exporter {
    
    public function export($filters, $fields, $field_settings, $format)
    
    protected function build_query($filters)
    protected function get_users_batch($offset, $limit)
    protected function map_user_data($user, $fields)
    protected function export_user_meta($user_id, $fields)
}
```

**Product_Exporter** (`app/exporter/product_exporter.php`):
```php
class Product_Exporter extends Base_Exporter {
    
    public function export($filters, $fields, $field_settings, $format)
    
    protected function build_query($filters)
    protected function get_products_batch($offset, $limit)
    protected function map_product_data($product, $fields)
    protected function export_product_meta($product_id, $fields)
    protected function export_product_attributes($product_id, $fields)
    protected function export_product_variations($product_id, $fields)
}
```

#### Format Handlers

**CSV_Writer** (`app/format/csv_writer.php`):
```php
class CSV_Writer {
    
    public function __construct($options = [])
    public function write_header($fields)
    public function write_row($data)
    public function finalize()
    
    protected $delimiter = ',';
    protected $enclosure = '"';
    protected $include_bom = true;
}
```

**JSON_Writer** (`app/format/json_writer.php`):
```php
class JSON_Writer {
    
    public function __construct($options = [])
    public function start()
    public function write_item($data)
    public function finalize()
    
    protected $pretty_print = true;
    protected $include_metadata = true;
}
```

**Excel_Writer** (использует PhpSpreadsheet):
```php
class Excel_Writer {
    
    public function __construct($format = 'xlsx') // xlsx or xls
    public function write_header($fields)
    public function write_row($data, $row_number)
    public function save($filepath)
    
    protected $spreadsheet;
    protected $active_sheet;
}
```

#### Export Progress Tracker
**File**: `app/export/export_progress_tracker.php`

```php
class Export_Progress_Tracker {
    
    protected $job_id;
    
    public function __construct($job_id)
    public function update($processed, $success, $failed, $status)
    public function get_progress()
    public function complete($file_path)
    public function fail($error_message)
    public function pause()
    public function resume()
    public function cancel()
}
```

### Frontend Components

#### JavaScript Modules

**export_wizard.js** - Главный визард:
```javascript
class ExportWizard {
    constructor()
    init()
    
    // Navigation
    nextStep()
    prevStep()
    goToStep(step)
    
    // Step processing
    processStep1(contentType)
    processStep2(filters)
    processStep3(fields, settings)
    processStep4(options)
    startExport()
    
    // Helpers
    saveStepData(step, data)
    getStepData(step)
    validateStep(step)
    updateProgressBar()
}
```

**field_selector.js** - Выбор полей:
```javascript
class FieldSelector {
    constructor(containerId)
    init()
    
    renderAvailableFields(fields)
    renderSelectedFields()
    addField(field)
    removeField(field)
    reorderFields(oldIndex, newIndex)
    searchFields(query)
    
    onSelectionChange(callback)
}
```

**export_progress.js** - Прогресс экспорта:
```javascript
class ExportProgress {
    constructor(jobId)
    start()
    
    poll() // Every 2 seconds
    updateUI(progress)
    handleComplete(result)
    handleError(error)
    
    pause()
    resume()
    cancel()
}
```

### REST API Endpoints

```php
// Export wizard
POST   /wp-json/aie/v1/export/estimate
POST   /wp-json/aie/v1/export/start
GET    /wp-json/aie/v1/export/progress/{job_id}
POST   /wp-json/aie/v1/export/pause/{job_id}
POST   /wp-json/aie/v1/export/resume/{job_id}
POST   /wp-json/aie/v1/export/cancel/{job_id}
GET    /wp-json/aie/v1/export/download/{job_id}

// Templates
GET    /wp-json/aie/v1/export/templates
POST   /wp-json/aie/v1/export/template/save
PUT    /wp-json/aie/v1/export/template/{id}
DELETE /wp-json/aie/v1/export/template/{id}

// History
GET    /wp-json/aie/v1/export/history
GET    /wp-json/aie/v1/export/logs/{job_id}
DELETE /wp-json/aie/v1/export/{job_id}
```

### Database

Использует существующие таблицы:
- `aie_jobs` - экспорт jobs
- `aie_logs` - логи экспорта
- `aie_field_maps` - сохраненные шаблоны (templates)

Новые колонки для `aie_jobs`:
- `export_format` - CSV/JSON/XLS/XLSX
- `file_path` - путь к сохраненному файлу
- `file_size` - размер файла в байтах

---

## 🔒 Security

### Permissions
- Export capability: `manage_options` или `aie_export_data`
- File access: Только через nonce-protected endpoints
- Download: Временные signed URLs (expire через 1 час)

### File Storage
- Путь: `wp-content/uploads/aie/exports/`
- Защита: `.htaccess` для предотвращения прямого доступа
- Cleanup: Автоудаление файлов старше 7 дней (configurable)

### Rate Limiting
- Max 5 concurrent exports per user
- Max export size: 100,000 items (configurable)

---

## 🎨 Styling

### CSS Classes
```scss
// Export wizard
.aie-export-wizard
.aie-export-step
.aie-export-progress

// Field selector
.aie-field-selector
.aie-available-fields
.aie-selected-fields
.aie-field-item

// Progress
.aie-export-progress-bar
.aie-export-status
.aie-export-summary

// History
.aie-export-history-table
.aie-export-actions
```

---

## 🧪 Testing Scenarios

### Test 1: Basic Post Export
```php
$filters = ['post_status' => ['publish']];
$fields = ['post_title', 'post_content', 'post_date'];
$options = ['format' => 'csv'];

$exporter = new Post_Exporter();
$result = $exporter->export($filters, $fields, [], $options);
// Expected: CSV file with 3 columns
```

### Test 2: WooCommerce Products with Filters
```php
$filters = [
    'stock_status' => ['instock'],
    'price_range' => ['min' => 10, 'max' => 100],
    'category' => ['electronics']
];
$fields = ['post_title', '_sku', '_regular_price', '_stock'];
$options = ['format' => 'xlsx'];

$exporter = new Product_Exporter();
$result = $exporter->export($filters, $fields, [], $options);
// Expected: XLSX file with filtered products
```

### Test 3: Field Transformation
```php
$fields = ['post_title'];
$field_settings = [
    'post_title' => [
        'search_replace' => [
            ['search' => 'old', 'replace' => 'new']
        ],
        'function_id' => 1 // uppercase
    ]
];

$exporter = new Post_Exporter();
$result = $exporter->export([], $fields, $field_settings, ['format' => 'csv']);
// Expected: Transformed post_title values
```

### Test 4: ACF Fields Export
```php
$fields = ['post_title', 'custom_field_1', 'gallery_repeater'];
$options = ['format' => 'json'];

$exporter = new Post_Exporter();
$result = $exporter->export([], $fields, [], $options);
// Expected: JSON with nested ACF data
```

### Test 5: Large Export (Background Processing)
```php
$filters = []; // All posts
$fields = ['post_title', 'post_content'];
$options = [
    'format' => 'csv',
    'background' => true,
    'batch_size' => 50,
    'email_notification' => true
];

$controller = new Export_Wizard_Controller();
$job = $controller->start_export();
// Expected: Job created, background processing started
```

---

## 📈 Performance Optimization

### Batch Processing
- Default: 50 items per batch
- Memory efficient streaming for CSV/JSON
- Excel: Buffer rows in chunks of 100

### Database Queries
- Use `WP_Query` with pagination
- Select only required fields
- Use `no_found_rows` when count not needed
- Index on filtered columns

### File Writing
- Stream write for CSV/JSON (не держать весь файл в памяти)
- For Excel: Use `\PhpOffice\PhpSpreadsheet\Writer\Xlsx` with cell caching

### Caching
- Cache available fields per content type (transient, 1 hour)
- Cache taxonomy terms (transient, 1 hour)

---

## 🌐 Internationalization

All strings wrapped in translation functions:
```php
__('Export Wizard', 'wp-advanced-import-export')
_e('Select Content Type', 'wp-advanced-import-export')
_n('%s item', '%s items', $count, 'wp-advanced-import-export')
```

---

## 📚 Hooks & Filters

### Actions
```php
do_action('aie_before_export', $job_id, $settings);
do_action('aie_before_export_item', $item_data, $content_type);
do_action('aie_after_export_item', $exported_data, $item_data);
do_action('aie_after_export', $job_id, $file_path, $stats);
do_action('aie_export_complete', $job_id, $file_path);
do_action('aie_export_failed', $job_id, $error_message);
```

### Filters
```php
apply_filters('aie_export_query_args', $args, $content_type, $filters);
apply_filters('aie_export_item_data', $data, $item, $fields);
apply_filters('aie_export_field_value', $value, $field, $item);
apply_filters('aie_export_file_path', $file_path, $job_id);
apply_filters('aie_export_batch_size', $batch_size, $content_type);
apply_filters('aie_export_formats', $formats);
apply_filters('aie_export_available_fields', $fields, $content_type);
```

---

## 📝 Summary

### Total Components
- **5 wizard steps** - полный контроль над экспортом
- **4 export formats** - CSV, JSON, XLS, XLSX
- **10+ content types** - Posts, Users, Products, etc.
- **100+ field types** - WordPress + ACF + Yoast + WooCommerce
- **Background processing** - для больших экспортов
- **Template system** - сохранение и переиспользование конфигураций
- **History & logs** - полная аудит история

### Implementation Priority
1. Backend: Export_Wizard_Controller + Content Type Exporters
2. Backend: Format Writers (CSV/JSON/Excel)
3. Frontend: Export Wizard UI (5 steps)
4. Frontend: Field Selector with Drag & Drop
5. Backend: Background Processing Integration
6. Frontend: Progress Tracking
7. Templates & History pages
8. Testing & Polish

**Estimated Time**: ~60-70 hours

---

**End of Specification**
