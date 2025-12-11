# Content Updater - Functions Not Found Fix

## Problem

When using Content Updater with built-in functions (like "Uppercase"), you get errors:
```
[WP_AIE] [ERROR] Custom function not found: ID 1
```

And not all items are updated.

## Root Cause

1. **Built-in functions are NOT loaded into database** - They exist only as code snippets in `Function_Snippets.php`
2. **Function_Executor expects database IDs** - It queries `wp_aie_custom_functions` table by ID
3. **Content Updater sends function IDs from UI** - But these IDs don't exist in the database

## Flow:

1. User selects "Uppercase" in UI → Frontend assigns an ID (e.g., 1)
2. Content Updater sends `field_functions: [[1]]` to backend
3. Update_Processor tries to execute function ID 1
4. Function_Executor queries database for ID 1 → NOT FOUND
5. Returns original value unchanged (skips update)

## Solution Options

### Option 1: Load Built-in Functions to Database (Recommended)

Create a method to seed built-in functions from `Function_Snippets` into the database:

```php
// In Custom_Function model or Database_Migration
public static function seed_builtin_functions() {
    $snippets = Function_Snippets::get_all_functions();
    $custom_function = WP_AIE()->Model->custom_function;
    
    foreach ($snippets as $key => $snippet) {
        // Check if already exists
        $existing = $custom_function->find_by_name($snippet['name']);
        if (!$existing) {
            $custom_function->create([
                'name' => $snippet['name'],
                'description' => $snippet['description'],
                'function_code' => $snippet['code'],
                'source' => 'builtin',
                'input_type' => $snippet['input_type'] ?? 'string',
                'output_type' => $snippet['output_type'] ?? 'string',
                'is_active' => 1,
                'user_id' => 0, // System user
            ]);
        }
    }
}
```

Call this during:
- Plugin activation
- First time accessing Functions page
- Before Content Updater starts

### Option 2: Modify Function_Executor to Support Snippets

Add fallback logic to `get_function()`:

```php
private function get_builtin_function($function_id) {
    $snippets = Function_Snippets::get_all_functions();
    $all_functions = array_values($snippets);
    
    // Try to match by array index (ID - 1)
    if (isset($all_functions[$function_id - 1])) {
        return $all_functions[$function_id - 1];
    }
    
    return null;
}
```

### Option 3: UI Should Send Function Keys Instead of IDs

Modify Content Updater to send function names/keys instead of database IDs:
- Send: `field_functions: [['uppercase']]`
- Backend resolves keys to actual functions

## Recommended Fix

**Implement Option 1** - Load built-in functions into database:

1. Add `seed_builtin_functions()` method to `Custom_Function` model
2. Call it in `Database_Migration::create_tables()` after creating tables
3. Add UI button in Functions page to "Reload Built-in Functions"
4. Update DB version to trigger migration

## Temporary Workaround

Until fixed, users must:
1. Go to Functions page
2. Manually create each function they want to use
3. Copy code from built-in snippets
4. Then use in Content Updater

This is not user-friendly and defeats the purpose of built-in functions!
