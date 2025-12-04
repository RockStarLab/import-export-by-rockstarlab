# Custom Filters Documentation

## Overview
Added support for custom field (meta) and taxonomy filters across all exporters.

## Supported Content Types

### Custom Field Filters
Available for:
- Posts (`post`)
- Pages (`page`)
- Media (`media`)
- Users (`user`)
- Comments (`comment`)
- Taxonomy (`taxonomy`)
- Custom Post Types (`custom_post_types`)
- WooCommerce Products (`woo_product`)
- WooCommerce Orders (`woo_order`)
- WooCommerce Coupons (`woo_coupon`)

### Taxonomy Filters
Available for:
- Posts (`post`)
- Pages (`page`)
- Custom Post Types (`custom_post_types`)
- WooCommerce Products (`woo_product`)

## Usage

### Frontend (JavaScript)

When adding filters in the export wizard (Step 2), users can now select:

**🔧 Custom Field (Meta)** - Filter by custom field/meta values
- Field Name: Enter the meta key name
- Condition: Select comparison operator
- Value: Enter the value to compare

**🏷️ Taxonomy Filter** - Filter by taxonomy terms
- Taxonomy Name: Enter taxonomy slug (e.g., category, post_tag, product_cat)
- Condition: IN, NOT IN, or AND
- Terms: Comma-separated list of term slugs

### Backend (PHP)

#### Custom Field Filters

**Format:**
```php
$options = [
    'custom_fields' => [
        [
            'name' => 'price',
            'value' => '100',
            'condition' => 'greater'
        ],
        [
            'name' => 'featured',
            'value' => '1',
            'condition' => 'equals'
        ]
    ]
];
```

**Supported Conditions:**
- `equals` - Exact match
- `not_equals` - Not equal
- `contains` - LIKE search
- `not_contains` - NOT LIKE search
- `greater` - Greater than (>)
- `less` - Less than (<)
- `equals_or_greater` - Greater or equal (>=)
- `equals_or_less` - Less or equal (<=)
- `in` - Value in array (comma-separated)
- `not_in` - Value not in array
- `is_empty` - Field doesn't exist
- `is_not_empty` - Field exists

#### Taxonomy Filters

**Format:**
```php
$options = [
    'taxonomy' => [
        [
            'taxonomy' => 'category',
            'terms' => ['news', 'updates'],
            'condition' => 'in'
        ],
        [
            'taxonomy' => 'product_cat',
            'terms' => 'electronics,gadgets',
            'condition' => 'and'
        ]
    ]
];
```

**Supported Conditions:**
- `in` (or `IN`) - Has any of the terms
- `not_in` (or `NOT IN`) - Doesn't have any of the terms
- `and` (or `AND`) - Has all of the terms

**Terms Format:**
- Array: `['term1', 'term2']`
- String: `'term1,term2'`

### API Examples

#### Export posts with custom field filter
```php
use WP_AIE\Model\Export\Exporter_Factory;

$options = [
    'post_type' => 'post',
    'custom_fields' => [
        [
            'name' => '_price',
            'value' => '50',
            'condition' => 'greater'
        ]
    ]
];

$exporter = Exporter_Factory::get_exporter('post');
$data = $exporter->export($options);
```

#### Export products with taxonomy filter
```php
$options = [
    'post_type' => 'product',
    'taxonomy' => [
        [
            'taxonomy' => 'product_cat',
            'terms' => ['electronics', 'computers'],
            'condition' => 'in'
        ]
    ],
    'custom_fields' => [
        [
            'name' => '_stock',
            'value' => '0',
            'condition' => 'greater'
        ]
    ]
];

$exporter = Exporter_Factory::get_exporter('woo_product');
$data = $exporter->export($options);
```

#### Export users with custom field filter
```php
$options = [
    'custom_fields' => [
        [
            'name' => 'company',
            'value' => 'Acme',
            'condition' => 'equals'
        ]
    ]
];

$exporter = Exporter_Factory::get_exporter('user');
$data = $exporter->export($options);
```

## Implementation Details

### Backend Changes

1. **Post_Exporter.php**
   - Added `apply_custom_field_filters()` method
   - Added `apply_taxonomy_filters()` method
   - Updated `get_supported_filters()` to document new filters

2. **Media_Exporter.php**
   - Added `apply_custom_field_filters()` method
   - Updated `get_supported_filters()`

3. **User_Exporter.php**
   - Added `apply_custom_field_filters()` method
   - Updated `get_supported_filters()`

4. **Comment_Exporter.php**
   - Added `apply_custom_field_filters()` method
   - Added `convert_condition_to_meta_compare()` method
   - Updated `get_supported_filters()`

5. **Taxonomy_Exporter.php**
   - Added `apply_custom_field_filters()` method
   - Added `convert_condition_to_meta_compare()` method
   - Updated `get_supported_filters()`

### Frontend Changes

1. **export.js**
   - Added custom_field and taxonomy_filter types to `getFieldsByContentType()`
   - Updated `onFilterFieldChange()` to handle new filter types
   - Updated `getDynamicFilters()` to collect and structure filter data
   - Updated `refreshCount()` and `startExport()` to pass new filters to backend

2. **app.scss**
   - Added styles for `.aie-custom-field-inputs`
   - Added styles for `.aie-taxonomy-filter-inputs`
   - Improved alignment and spacing

## Testing

Test the filters with various content types:

1. **Posts with taxonomy filter:**
   - Select "Post" content type
   - Add "Taxonomy Filter"
   - Enter taxonomy: "category", terms: "news,updates", condition: "IN"

2. **Products with custom field:**
   - Select "WooCommerce Product"
   - Add "Custom Field (Meta)"
   - Enter name: "_price", value: "100", condition: "greater"

3. **Users with meta filter:**
   - Select "User"
   - Add "Custom Field (Meta)"
   - Enter name: "last_login", condition: "is_not_empty"

## Notes

- All filters use WordPress's native query capabilities (WP_Query, WP_User_Query, etc.)
- Filters are combined with AND logic
- Meta queries support all standard WordPress meta compare operators
- Taxonomy queries use term slugs for matching
- Custom field filters work with both post meta, user meta, comment meta, and term meta depending on content type
