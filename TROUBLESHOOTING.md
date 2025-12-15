# Troubleshooting Guide

## Plugin Activation Errors

If you encounter a "Plugin could not be activated because it triggered a fatal error" message, follow these steps:

### Step 1: Check WordPress and PHP Requirements

- **Minimum WordPress Version:** 5.8
- **Minimum PHP Version:** 7.4
- **Recommended PHP Version:** 8.0+

### Step 2: Enable Debug Mode

Add the following to your `wp-config.php` file:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Step 3: Check Error Logs

After enabling debug mode, try activating the plugin again. Then check:

1. WordPress debug log: `wp-content/debug.log`
2. PHP error log: Check your server's PHP error log location
3. Server error log: Check Apache/Nginx error logs

### Step 4: Common Issues and Solutions

#### Issue: Class not found error
**Solution:** Ensure your server supports PHP namespaces and the autoloader is working correctly.

#### Issue: Database error
**Solution:** Check that your WordPress database user has permissions to CREATE and ALTER tables.

#### Issue: Memory limit error
**Solution:** Increase PHP memory limit in `wp-config.php`:
```php
define('WP_MEMORY_LIMIT', '256M');
```

#### Issue: Conflict with another plugin
**Solution:** 
1. Deactivate all other plugins
2. Try activating WP Advanced Import Export
3. If successful, reactivate other plugins one by one to find the conflict

### Step 5: Manual Database Check

If the plugin still fails to activate, check if the database tables were created:

```sql
SHOW TABLES LIKE 'wp_aie_%';
```

You should see tables like:
- `wp_aie_jobs`
- `wp_aie_custom_functions`
- `wp_aie_site_connections`
- `wp_aie_media_hashes`

### Step 6: Get Support

If none of the above solutions work, please provide:

1. WordPress version
2. PHP version
3. Complete error message from debug.log
4. List of active plugins
5. Server environment (Apache/Nginx, etc.)

## Content Sync Connection Errors

### "Cannot connect to remote site"

**Possible causes:**
- Remote site URL is incorrect or not accessible
- Remote site is behind a firewall
- SSL certificate issues

**Solutions:**
- Verify the URL is correct and accessible in a browser
- Check firewall settings allow outbound connections
- If using HTTPS, ensure SSL certificate is valid

### "Invalid API key"

**Possible causes:**
- API key was copied incorrectly
- API key was regenerated on remote site
- Extra spaces in the API key field

**Solutions:**
- Go to the remote site's Content Sync page
- Click "Show Details" to reveal the API key
- Copy the entire key (no spaces before/after)
- Paste it carefully in the connection form

### "Plugin is not installed or activated on the remote site"

**Solution:**
- Install and activate WP Advanced Import Export on the remote site
- Verify the plugin is active in wp-admin > Plugins

### REST API Issues

If you're having connection problems, verify REST API is working:

1. Visit: `https://yoursite.com/wp-json/`
2. You should see JSON output
3. If you see an error, check:
   - Permalink settings (resave if needed)
   - .htaccess file permissions
   - Server mod_rewrite enabled

## Debug Mode for Content Sync

To get detailed information about connection attempts, you can use browser Developer Tools:

1. Open browser DevTools (F12)
2. Go to Network tab
3. Try to add a site connection
4. Look for the AJAX request to `admin-ajax.php`
5. Check the Response tab for detailed error messages

## Still Having Issues?

Create a support ticket with:
- Screenshots of any error messages
- Browser console errors (if any)
- WordPress and PHP versions
- Steps to reproduce the issue
