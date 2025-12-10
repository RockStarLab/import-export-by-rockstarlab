# Content Updater Feature

## Overview

Content Updater is a powerful feature that allows you to bulk update content by applying custom functions to selected fields. This feature processes updates in batches, ensuring efficient and safe modifications to your WordPress content.

## Architecture

### Components

1. **Content_Updater_Controller** (`app/Controller/Content_Updater_Controller.php`)
   - Handles AJAX requests
   - Methods:
     - `get_count()` - Get count of items to update
     - `get_preview()` - Preview items before updating
     - `start_update()` - Initialize update job
     - `process_batch()` - Process a batch of items
     - `get_progress()` - Get update progress
     - `cancel_update()` - Cancel running update

2. **Update_Processor** (`app/Model/Queue/Update_Processor.php`)
   - Processes content updates in batches
   - Applies custom functions to field values
   - Supports multiple content types:
     - Posts, Pages, Custom Post Types
     - Media
     - Users
     - Comments
     - Taxonomy Terms
     - WooCommerce Products, Orders, Coupons
   - Tracks statistics (updated, skipped, errors)

3. **User Interface** (`app/View/settings/content_updater.php`)
   - 4-step wizard interface:
     1. Select Content Type
     2. Select Fields
     3. Assign Functions
     4. Start Update & View Progress

4. **JavaScript Module** (`src/js/modules/content-updater.js`)
   - Handles UI interactions
   - Manages AJAX requests
   - Real-time progress tracking
   - Drag-and-drop field selection

5. **Styles** (`src/scss/admin/_content-updater.scss`)
   - Modern, responsive design
   - Progress indicators
   - Statistics cards
   - Animated transitions

## User Workflow

### Step 1: Select Content Type
User chooses what type of content to update:
- Blog Posts
- Pages
- Custom Post Types (Premium)
- Media
- Menus
- Users
- Comments
- Taxonomy Terms (Premium)
- WooCommerce Products/Orders/Coupons (Premium)
- MySQL Database Tables (Premium)

### Step 2: Select Fields
- Browse available fields for the selected content type
- Drag-and-drop fields to select them
- Search/filter fields
- View field categories (Basic, Meta, Taxonomies, ACF, etc.)

### Step 3: Assign Functions
- Assign custom functions to each selected field
- Preview function output with test values
- Apply same function to all fields
- View assignment statistics

### Step 4: Start Update
- Review update summary
- Configure settings:
  - Items per iteration (batch size)
  - Dry run mode (test without saving)
- Monitor real-time progress:
  - Progress bar with percentage
  - Processed/Updated/Skipped/Error counts
  - Current status messages
- View final results

## Technical Details

### Update Process Flow

1. **Job Creation**
   ```
   User → start_update() → Create Job → Return job_id
   ```

2. **Batch Processing**
   ```
   process_batch() → 
     Fetch items (via Exporter_Factory) →
     Apply functions to fields →
     Save updated items →
     Update progress →
     Repeat until complete
   ```

3. **Function Application**
   ```
   For each item:
     For each field:
       Get current value
       Execute assigned function
       If value changed: mark for update
     Save item if any changes
   ```

### Data Flow

```
Content Updater Controller
    ↓
Update Processor
    ↓
Exporter Factory (get items)
    ↓
Function Executor (apply functions)
    ↓
Save Methods (update_post, update_user, etc.)
```

### Job Parameters Structure

```json
{
  "content_type": "post",
  "fields": ["post_title", "post_excerpt", "custom_field"],
  "field_functions": [5, "none", 12],
  "options": {
    "items_per_iteration": 10,
    "dry_run": false
  }
}
```

## Features

### Batch Processing
- Configurable batch size (1-100 items)
- Prevents timeouts and memory issues
- Automatic progress tracking

### Function Support
- Uses existing Function_Executor system
- Supports all custom functions
- Real-time function testing
- Error handling per field

### Content Type Support
- Reuses Exporter_Factory for data fetching
- Consistent field structure across content types
- Automatic field detection

### Progress Tracking
- Real-time updates every 2 seconds
- Detailed statistics:
  - Total items
  - Processed items
  - Updated items (changes applied)
  - Skipped items (no changes)
  - Error items (failed to update)
- Percentage completion

### Safety Features
- Dry run mode for testing
- Warning before starting
- Cancel at any time
- Detailed error logging
- Per-item error handling (continues on error)

## API Endpoints

### `aie_updater_get_count`
Get count of items to update.

**Parameters:**
- `content_type` - Type of content
- `options` - Filter options (optional)

**Response:**
```json
{
  "success": true,
  "data": {
    "count": 150
  }
}
```

### `aie_updater_get_preview`
Get preview of items.

**Parameters:**
- `content_type` - Type of content
- `fields` - Array of field names
- `options` - Filter options

**Response:**
```json
{
  "success": true,
  "data": {
    "preview": [...],
    "count": 5
  }
}
```

### `aie_updater_start`
Start update process.

**Parameters:**
- `content_type` - Type of content
- `fields` - Array of field names
- `field_functions` - Array of function IDs (indexed by field)
- `options` - Update options

**Response:**
```json
{
  "success": true,
  "data": {
    "job_id": 123,
    "total_count": 150
  }
}
```

### `aie_updater_process_batch`
Process next batch.

**Parameters:**
- `job_id` - Job ID

**Response:**
```json
{
  "success": true,
  "data": {
    "completed": false,
    "processed": 10,
    "total": 150,
    "updated_items": 8,
    "skipped_items": 2,
    "error_items": 0,
    "progress": 6.67
  }
}
```

### `aie_updater_get_progress`
Get current progress.

**Parameters:**
- `job_id` - Job ID

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "processing",
    "total_items": 150,
    "processed_items": 45,
    "updated_items": 40,
    "skipped_items": 5,
    "error_items": 0,
    "percentage": 30.0
  }
}
```

### `aie_updater_cancel`
Cancel update process.

**Parameters:**
- `job_id` - Job ID

**Response:**
```json
{
  "success": true,
  "data": {
    "cancelled": true
  }
}
```

## Database Schema

Updates use the existing `wp_aie_jobs` table with type = 'update'.

**Fields used:**
- `type` = 'update'
- `status` - 'pending', 'processing', 'completed', 'failed', 'cancelled'
- `total_items` - Total items to process
- `processed_items` - Items processed so far
- `imported_items` - Reused for updated_items count
- `skipped_items` - Items skipped (no changes)
- `error_items` - Items with errors
- `parameters` - JSON with update configuration

## Future Enhancements

1. **Conditional Updates**
   - Add filters/conditions to update only specific items
   - Support for complex queries

2. **Bulk Operations**
   - Set same value for all selected items
   - Find and replace across fields

3. **Scheduling**
   - Schedule updates to run at specific times
   - Recurring update jobs

4. **Rollback**
   - Save original values before update
   - Ability to rollback changes

5. **Preview Mode**
   - Show before/after preview for each item
   - Confirm changes before applying

6. **Database Table Support**
   - Full implementation for custom database tables
   - Support for complex table structures

## Usage Examples

### Example 1: Update Post Titles
1. Select "Blog Posts"
2. Select field "post_title"
3. Assign function "Convert to Uppercase"
4. Start update with 20 items per iteration

### Example 2: Sanitize Custom Fields
1. Select "Blog Posts"
2. Select custom fields: "custom_field_1", "custom_field_2"
3. Assign function "Strip HTML Tags" to both
4. Test in dry run mode first
5. Run actual update

### Example 3: Update User Metadata
1. Select "Users"
2. Select fields: "first_name", "last_name", "description"
3. Assign different functions to each field
4. Process 10 users per iteration

## Best Practices

1. **Always Test First**
   - Use dry run mode to test functions
   - Start with small batch sizes
   - Preview results before full update

2. **Backup Database**
   - Create database backup before bulk updates
   - Especially important for production sites

3. **Batch Size**
   - Use smaller batches (10-20) for complex functions
   - Use larger batches (50-100) for simple transformations
   - Monitor server resources during update

4. **Function Testing**
   - Test functions thoroughly before applying to all items
   - Handle edge cases in function code
   - Validate output formats

5. **Monitor Progress**
   - Watch for high error rates
   - Cancel if unexpected behavior occurs
   - Check logs for detailed error messages

## Troubleshooting

### High Error Rate
- Check function code for errors
- Verify field names are correct
- Check server error logs

### Slow Processing
- Reduce batch size
- Optimize function code
- Check server resources

### Timeout Issues
- Decrease items per iteration
- Increase PHP max_execution_time
- Use background processing (cron)

## Support Matrix

| Content Type | Fields Support | Meta Support | Save Support |
|-------------|---------------|--------------|--------------|
| Posts/Pages | ✅ Full | ✅ Full | ✅ Full |
| Custom Post Types | ✅ Full | ✅ Full | ✅ Full |
| Media | ✅ Full | ✅ Full | ✅ Full |
| Users | ✅ Full | ✅ Full | ✅ Full |
| Comments | ✅ Full | ✅ Full | ✅ Full |
| Taxonomy Terms | ✅ Full | ✅ Full | ✅ Full |
| WooCommerce | ✅ Full | ✅ Full | ✅ Full |
| Database Tables | ⚠️ Partial | ❌ N/A | ❌ Not Implemented |

## Files Created

### PHP
- `app/Controller/Content_Updater_Controller.php` - Main controller
- `app/Model/Queue/Update_Processor.php` - Batch processor
- `app/View/settings/content_updater.php` - Main view
- `app/View/settings/partials/updater-step-1.php` - Step 1 view
- `app/View/settings/partials/updater-step-2.php` - Step 2 view
- `app/View/settings/partials/updater-step-3.php` - Step 3 view
- `app/View/settings/partials/updater-step-4.php` - Step 4 view
- `app/View/settings/partials/updater-steps-indicator.php` - Steps indicator

### JavaScript
- `src/js/modules/content-updater.js` - Main JS module

### CSS
- `src/scss/admin/_content-updater.scss` - Styles

### Modified Files
- `app/Controller/Init.php` - Added controller and menu
- `src/js/app.js` - Added module import
- `src/scss/app.scss` - Added styles import

## Testing Checklist

- [ ] Select each content type
- [ ] Drag and drop fields
- [ ] Search/filter fields
- [ ] Assign functions to fields
- [ ] Test function preview
- [ ] Start update with dry run
- [ ] Monitor progress
- [ ] Cancel update mid-process
- [ ] Complete full update
- [ ] Verify results in database
- [ ] Check error handling
- [ ] Test with large datasets
- [ ] Verify batch processing
- [ ] Check mobile responsiveness
