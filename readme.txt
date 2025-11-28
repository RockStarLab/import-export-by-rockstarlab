=== WP Advanced Import Export ===
Contributors: RockstarLab
Tags: import, export, csv, xml, json, media sync, site sync, bulk import, data migration
Requires at least: 5.8
Tested up to: 6.5.2
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL v2 or later

Advanced import and export solution for WordPress with custom functions, media folder sync, site-to-site content sync, and premium Real Media Library integration.

== Description ==

**WP Advanced Import Export** is a powerful and flexible plugin for importing and exporting WordPress data with support for various content types and file formats.

= Core Features =

* **Multiple Data Types**: Posts, Pages, Users, Comments, Media, Custom Post Types, Taxonomies
* **File Formats**: CSV, JSON, XML with streaming support for large files
* **Background Processing**: Handle large datasets without memory limits
* **Field Mapping**: Intuitive field mapping interface with preview
* **Validation System**: Comprehensive data validation before import
* **Custom Functions**: Write PHP functions to transform data during import/export
* **Snippets Library**: 50+ ready-to-use transformation functions
* **Media Folder Sync**: Synchronize FTP-uploaded files with WordPress Media Library
* **Site-to-Site Sync**: Connect and sync content between two WordPress sites
* **Progress Tracking**: Real-time progress bars and detailed logs
* **History & Logs**: Complete history of all import/export operations

= Media Folder Sync =

Easily synchronize files from server folders (uploaded via FTP) to your WordPress Media Library:

* **Scan Server Folders**: Browse and select folders with recursive scanning
* **File Type Filters**: Choose specific file types or all WordPress-allowed types
* **Duplicate Detection**: Three methods (Hash, Filename, Filesize) to skip duplicates
* **Preserve Structure**: Maintain original folder hierarchy
* **Batch Processing**: Handle large numbers of files efficiently
* **Auto Alt Text**: Generate alt text from filenames
* **Premium: Real Media Library**: Automatically create folder structure in Real Media Library

= Site-to-Site Content Sync =

Synchronize content between two WordPress sites with secure API-based connection:

* **API Key Connection**: Connect sites with secure 64-character API keys
* **Bidirectional Sync**: Pull from remote site or push to it
* **All Content Types**: Posts, Pages, CPT, Users, Media, Terms, Comments
* **Selective Sync**: Filter by ID, date, author, status, taxonomy
* **Conflict Resolution**: Skip, Update, or Duplicate strategies
* **Media Sync**: Automatically download and sync media files
* **Background Processing**: Large operations run without timeout
* **Security**: Rate limiting, IP whitelisting, API key validation
* **Sync History**: Track all operations with detailed logs
* **Premium: Scheduled Sync**: Set up automatic recurring sync

= Custom Functions System =

Transform your data during import/export with custom PHP functions:

* **Code Editor**: Built-in syntax highlighting
* **Test Before Use**: Test functions with sample data
* **Library of Examples**: 50+ pre-built functions for common transformations
* **Categories**: String, Date, Number, HTML, WordPress, Validation operations
* **Safe Execution**: Sandboxed execution with whitelist/blacklist
* **Reusable**: Save and reuse functions across different imports

= Premium Features =

* **Real Media Library Integration**: Automatic folder creation and organization
* **Scheduled Sync**: Set up automatic recurring content synchronization
* **Priority Support**: Get help when you need it
* **Advanced Scheduler**: Set up automatic recurring imports/exports
* **WooCommerce Support**: Import/export products, orders, and variations
* **ACF Integration**: Support for Advanced Custom Fields

= Perfect For =

* **Site Migration**: Moving content between WordPress sites
* **Multi-Site Management**: Sync content across multiple WordPress installations
* **Bulk Operations**: Updating hundreds or thousands of posts at once
* **Data Integration**: Importing data from external systems
* **Content Management**: Exporting for backup or analysis
* **Media Organization**: Organizing FTP-uploaded files into Media Library
* **WooCommerce**: Product catalog management

= Developer Friendly =

* **REST API**: Full REST API for programmatic access
* **WP-CLI Commands**: Command-line interface for imports/exports
* **Action & Filter Hooks**: Extensive hooks for customization
* **PSR-4 Autoloading**: Clean, modern codebase
* **Extensible Architecture**: Easy to add new importers/exporters

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-advanced-import-export` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to **Dashboard → Import/Export** to start using the plugin
4. For Media Sync, go to **Dashboard → Media Sync**
5. For Site-to-Site Sync, go to **Dashboard → Content Sync**

= Minimum Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* MySQL 5.6 or MariaDB 10.1 or higher

== Frequently Asked Questions ==

= Can I import large files without timeout issues? =

Yes! The plugin uses background processing to handle large files without memory or timeout issues.

= How do I sync files uploaded via FTP? =

1. Upload your files to any folder on the server
2. Go to Media Sync page
3. Select the folder and configure options
4. Click "Start Sync"
5. Files will be imported to Media Library

= What file formats are supported? =

CSV, JSON, and XML formats are fully supported with streaming capabilities for large files.

= Can I transform data during import? =

Yes! Use the Custom Functions feature to write PHP code that transforms your data. We also provide 50+ ready-to-use examples.

= How do I avoid importing duplicate media files? =

Use the Media Folder Sync feature with duplicate detection. Choose from three methods: Hash (most accurate), Filename (fastest), or Filesize (balanced).

= Is Real Media Library integration included? =

Real Media Library integration is a Premium feature. Upgrade to Premium to automatically create folder structures in RML.

= Can I schedule automatic imports? =

Yes, the Premium version includes an advanced scheduler for recurring imports and exports.

= Is there an API for developers? =

Yes! The plugin includes a full REST API and WP-CLI commands for programmatic access.

= Can I sync content between two WordPress sites? =

Yes! Use the Site-to-Site Content Sync feature. Connect two sites with API keys and sync posts, media, users, and more. Choose between Pull (import from remote) or Push (send to remote) operations.

= How secure is Site-to-Site sync? =

Very secure! We use 64-character API keys, rate limiting, IP whitelisting, and WordPress nonce verification. All requests are authenticated and logged.

= Can I schedule automatic syncs between sites? =

Yes, scheduled synchronization is available in the Premium version.

== Screenshots ==

1. Import page with field mapping
2. Export configuration
3. Custom Functions editor with snippets library
4. Media Folder Sync interface
5. Site-to-Site Content Sync with connection management
6. Progress tracking with real-time updates
7. Import/Export history with logs
8. Settings page

== Changelog ==

= 1.0.0 =
* Initial release
* Core import/export functionality
* CSV, JSON, XML support
* Background processing
* Field mapping interface
* Custom Functions System
* Function Snippets Library (50+ examples)
* Media Folder Sync
* Site-to-Site Content Sync
* Real Media Library integration (Premium)
* REST API endpoints
* Progress tracking and logging

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Advanced Import Export with advanced features for data management, media synchronization, and site-to-site content sync.

== Privacy Policy ==

WP Advanced Import Export does not collect or store any user data outside of your WordPress installation. All import/export operations are performed locally on your server.

== Support ==

* Free Support: WordPress.org support forum
* Premium Support: Available for Premium license holders
* Documentation: Full documentation available in plugin folder
* GitHub: Report issues and contribute

== Credits ==

* Built with modern WordPress standards
* Uses Freemius SDK for licensing
* Compatible with Real Media Library (Premium feature)