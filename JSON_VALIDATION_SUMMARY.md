# JSON Structure Validation - Implementation Summary

## Changes Made

### 1. Updated Validation Rules in `Chunk_Upload.php`

**Previous**: Max depth 3 levels  
**Current**: Max depth 2 levels

#### Validation Logic:
```
Level 0: Root array []
Level 1: Object fields {"id": 1, "title": "..."}  
Level 2: Nested values {"meta": {"key": "value"}} ✅ ALLOWED
Level 3: Too deep {"data": {"meta": {"key": "value"}}} ❌ REJECTED
```

#### Why This Structure?

WordPress data often needs nested values for meta fields:
- **Flat values**: `"title": "My Post"` → Direct field mapping
- **Nested objects**: `"meta": {"views": 100}` → Serialized and stored as meta
- **Arrays**: `"tags": ["tag1", "tag2"]` → Serialized or converted to taxonomy terms

### 2. Validation Method: `validate_json_structure()`

Checks:
- ✅ JSON is an array (not single object)
- ✅ Array is not empty
- ✅ Items are associative arrays (objects with named keys)
- ✅ Keys are strings (not numeric indices)
- ✅ Maximum depth ≤ 2 levels
- ✅ Consistent structure (warning if fields differ)

### 3. Error Messages

User-friendly error messages for each validation failure:

| Error | Message |
|-------|---------|
| Not array | "JSON must be an array of objects. Example: [{"field1": "value1"}]" |
| Empty | "JSON file is empty" |
| Not objects | "JSON must contain an array of objects (associative arrays)" |
| Numeric keys | "JSON objects must have named fields (keys)" |
| Too deep | "JSON structure is too deeply nested (depth: X). Maximum allowed: 2" |
| Inconsistent | "Some objects have different fields" (warning, not blocking) |

### 4. Helper Method: `get_array_depth()`

Recursively calculates maximum nesting depth:
```php
get_array_depth(["id" => 1, "title" => "test"]) // = 1
get_array_depth(["meta" => ["views" => 100]]) // = 2
get_array_depth(["data" => ["meta" => ["key" => "val"]]]) // = 3 ❌
```

### 5. Frontend Integration

JavaScript handles validation results:
- **Error**: Shows error notice, removes file, prevents upload
- **Warning**: Shows warning notice, allows upload to continue
- **Success**: Shows success notice, enables next step

### 6. UI Help Section

Added expandable help in Step 2 with:
- Valid JSON example
- Structure requirements
- Depth explanation
- Visual code example

---

## Test Files Created

### ✅ Valid Files

1. **test-json-flat.json** - Simple flat structure
   ```json
   [{"id": 1, "title": "Post", "author": "John"}]
   ```

2. **test-json-valid.json** - With nested objects (depth 2)
   ```json
   [{"id": 1, "meta": {"views": 100}}]
   ```

3. **test-json-with-nested.json** - Multiple nested objects
   ```json
   [{"id": 1, "meta": {...}, "preferences": {...}}]
   ```

4. **test-json-with-arrays.json** - With array values
   ```json
   [{"id": 1, "tags": ["tag1", "tag2"]}]
   ```

### ❌ Invalid Files

1. **test-json-single-object.json** - Single object (not array)
   ```json
   {"id": 1, "title": "Not an array"}
   ```

2. **test-json-too-deep.json** - Depth 3 (too deep)
   ```json
   [{"data": {"meta": {"nested": "value"}}}]
   ```

---

## How Nested Data is Processed

### During Import:

1. **Flat fields** (`"title": "Post"`)
   - Mapped directly to WordPress fields
   - Example: post_title, user_email, etc.

2. **Nested objects** (`"meta": {"views": 100}`)
   - Serialized as PHP array: `a:1:{s:5:"views";i:100;}`
   - Or stored as JSON string: `{"views":100}`
   - Saved to meta tables (postmeta, usermeta, etc.)

3. **Arrays** (`"tags": ["tag1", "tag2"]`)
   - For taxonomy: Each item becomes a term
   - For meta: Serialized array
   - For custom fields: Depends on configuration

### Example Processing:

**Input JSON:**
```json
{
  "id": 1,
  "title": "My Post",
  "content": "Post content",
  "meta": {
    "views": 100,
    "likes": 20
  },
  "categories": ["Tech", "News"]
}
```

**WordPress Storage:**
- `wp_posts.post_title` = "My Post"
- `wp_posts.post_content` = "Post content"
- `wp_postmeta` → key: "meta", value: `a:2:{s:5:"views";i:100;s:5:"likes";i:20;}`
- `wp_term_relationships` → Terms: "Tech", "News"

---

## Benefits

1. **Flexible Structure**: Supports both flat and nested data
2. **WordPress Compatible**: Nested data serializes like WordPress expects
3. **User Friendly**: Clear error messages explain issues
4. **Safe Limits**: Prevents overly complex structures
5. **Meta Fields Support**: Perfect for custom fields, user meta, post meta
6. **Taxonomy Support**: Arrays can become terms/categories
7. **WooCommerce Ready**: Product meta, attributes, etc.

---

## Next Steps

- [ ] Test with real WordPress import scenarios
- [ ] Field mapping implementation for nested data
- [ ] Preview showing nested values correctly
- [ ] Serialization options (PHP vs JSON)
- [ ] Taxonomy term creation from arrays
