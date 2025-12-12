# JSON Import Format Guide

## Valid JSON Structure

Your JSON file must be an **array of objects** where each object represents one record to import.

### ✅ Flat Structure (Recommended)
```json
[
  {
    "id": 1,
    "title": "My Post",
    "content": "Post content here",
    "author": "John Doe",
    "status": "publish"
  },
  {
    "id": 2,
    "title": "Another Post",
    "content": "More content",
    "author": "Jane Smith",
    "status": "draft"
  }
]
```

### ✅ With Nested Objects (Meta Fields)
Nested objects and arrays will be **serialized** and stored as a single value.

```json
[
  {
    "id": 1,
    "email": "john@example.com",
    "meta": {
      "first_name": "John",
      "last_name": "Doe",
      "phone": "+1234567890"
    },
    "preferences": {
      "newsletter": true,
      "notifications": false
    }
  }
]
```

In this example:
- `email` → stored as string
- `meta` → stored as serialized array or JSON string
- `preferences` → stored as serialized array or JSON string

### ✅ With Arrays
```json
[
  {
    "id": 1,
    "product": "Widget",
    "tags": ["electronics", "gadgets", "new"],
    "categories": ["tech", "accessories"]
  }
]
```

Arrays will be:
- Serialized as PHP array for meta fields
- Or converted to comma-separated values for taxonomy terms

---

## ❌ Invalid Structures

### Too Deeply Nested
```json
[
  {
    "id": 1,
    "data": {
      "meta": {
        "nested": {
          "tooDeep": "value"  // ❌ 3+ levels deep
        }
      }
    }
  }
]
```

**Error**: Maximum depth is 2 levels (field → nested value)

### Single Object
```json
{
  "id": 1,
  "title": "Single object"  // ❌ Not an array
}
```

**Error**: Must be an array of objects: `[{...}]`

### Array of Primitives
```json
[
  "value1",  // ❌ Not objects
  "value2",
  "value3"
]
```

**Error**: Must contain objects with key-value pairs

### Array of Arrays
```json
[
  [1, "value1"],  // ❌ Numeric arrays
  [2, "value2"]
]
```

**Error**: Objects must have named fields (keys)

---

## Depth Calculation

- **Level 0**: Root array `[]`
- **Level 1**: Object fields `{"id": 1, "title": "..."}`
- **Level 2**: Nested values `{"meta": {"key": "value"}}` ✅
- **Level 3**: Too deep `{"meta": {"nested": {"key": "value"}}}` ❌

---

## What Happens to Nested Data?

During import, nested objects and arrays are handled based on the target field type:

### For WordPress Meta Fields
```json
{"meta": {"views": 100, "likes": 20}}
```
- Stored as serialized array in `wp_postmeta`
- Can be retrieved with `get_post_meta()`

### For Taxonomy Terms
```json
{"categories": ["Tech", "News"]}
```
- Each item becomes a term
- Assigned to the post/product

### For Custom Fields
```json
{"custom_data": {"setting1": true, "setting2": "value"}}
```
- Stored as JSON string or serialized array
- Depends on field configuration

---

## Tips

1. **Keep it flat**: Use flat structure when possible for better performance
2. **Consistent structure**: All objects should have the same fields
3. **Use arrays for lists**: Categories, tags, multiple values
4. **Use objects for complex data**: Meta information, settings, preferences
5. **Test with small file first**: Upload 2-3 records to verify structure

---

## Need Help?

If your JSON is rejected:
1. Check the error message for specific issue
2. Verify structure matches examples above
3. Use JSON validator: https://jsonlint.com
4. Flatten deeply nested structures
5. Convert single object to array: `{...}` → `[{...}]`
