=== Amplified Import Export ===
Contributors: RockstarLab
Tags: import, export, csv, site sync, data migration
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL v2 or later

Advanced import/export for WordPress: custom functions, media sync, site-to-site content sync, and Real Media Library integration.

== Description ==

🚀 Amplified Import Export is the ultimate solution for managing your WordPress data with surgical precision. Whether you are migrating a website, syncing content between staging and production, or performing bulk data updates, this plugin provides a robust and flexible framework to handle even the most complex datasets.

Tired of restrictive tools that only handle posts? Our WordPress Import Export Plugin is built for developers and store owners who need total control. From standard WordPress elements to raw MySQL tables and WooCommerce entities, move your data without the headache of manual SQL queries or broken serialized data. 📂

Using advanced parsing logic and an AI-driven interface, Amplified Import Export ensures that your data arrives exactly where it needs to be, formatted exactly how you want it.

== Features ==

Our plugin is packed with tools to make data management seamless:

Comprehensive Data Support: Import and Export Posts, Pages, Custom Post Types (CPT), Taxonomies, Media Files, Navigation Menus, Users, and Comments. 📝

WooCommerce Mastery: Full support for WooCommerce Products (including variations), Attributes, Orders, and Coupons. Perfect for store migrations! 🛒

Raw MySQL Power: Go beyond WordPress standards. Import and export data from any MySQL table within your database. 🗄️

Intelligent Sync: Easily sync selected content between sites. Keep your Production and Staging environments in perfect harmony. 🔄

Bulk Content Updater: Update data based on specific filters. Need to replace a specific link in all posts published last week? Done in seconds. ⚡

SFTP Media Sync: Automatically detect and add media files uploaded via SFTP directly into the WordPress Media Library. 🖼️

AI-Powered URL Importer: Give the plugin a URL, and our AI will parse the remote website to import content directly as any Post Type. 🤖

Smart Jobs Log: Every action is saved. Re-run any import or export job with a single click without re-configuring parameters. 📜

== Special Offer ==

All new users get 4 weeks of all premium features! 🔥

== Why Choose Our Import / Export Plugin? ==

When searching for a reliable WordPress Import Export Addon, reliability and flexibility are key. Here is why RockstarLab built this to be the best in class:

Developer Friendly: Use our Functions Library to apply custom PHP transformations to any field during the process.

AI Integration: Don't know how to code a specific data transformation? Simply describe what you need (e.g., "Convert all titles to Uppercase"), and our AI assistant will write the function for you. 🧠

Speed & Performance: Optimized to handle large XML, CSV, and JSON files without crashing your server or hitting timeout limits.

No Data Left Behind: We handle complex metadata and hidden custom fields that other plugins often skip.

= Core Features =

* **Multiple Data Types**: Posts, Pages, Users, Comments, Media, Custom Post Types, Taxonomies
* **File Formats**: CSV with streaming support for large files
* **Background Processing**: Handle large datasets without memory limits
* **Field Mapping**: Intuitive field mapping interface with preview
* **Validation System**: Comprehensive data validation before import
* **Custom Functions**: Write PHP functions to transform data during import/export
* **Snippets Library**: 50+ ready-to-use transformation functions
* **Media Folder Sync**: Synchronize FTP-uploaded files with WordPress Media Library
* **Site-to-Site Sync**: Connect and sync content between two WordPress sites
* **Progress Tracking**: Real-time progress bars and detailed logs
* **History & Logs**: Complete history of all import/export operations

= Site-to-Site Content Sync =

Synchronize content between two WordPress sites with secure API-based connection:

* **API Key Connection**: Connect sites with secure 64-character API keys
* **Bidirectional Sync**: Pull from remote site or push to it
* **All Content Types**: Posts, Pages, CPT, Users, Media, Terms, Comments
* **Selective Sync**: Filter by ID, date, author, status, taxonomy
* **Conflict Resolution**: Skip, Update, or Duplicate strategies
* **Media Sync**: Automatically download and sync media files
* **Security**: Rate limiting, IP whitelisting, API key validation

= Media Folder Sync =

Easily synchronize files from server folders (uploaded via FTP/SFTP) to your WordPress Media Library:

* **Scan Server Folders**: Browse and select folders with recursive scanning
* **File Type Filters**: Choose specific file types or all WordPress-allowed types
* **Duplicate Detection**: Three methods (Hash, Filename, Filesize) to skip duplicates
* **Preserve Structure**: Maintain original folder hierarchy
* **Batch Processing**: Handle large numbers of files efficiently
* **Auto Alt Text**: Generate alt text from filenames
* **Premium: Real Media Library**: Automatically create folder structure in Real Media Library

= Custom Functions System =

Transform your data during import/export with custom PHP functions:

* **Code Editor**: Built-in syntax highlighting
* **Test Before Use**: Test functions with sample data
* **Library of Examples**: 50+ pre-built functions for common transformations
* **Categories**: String, Date, Number, HTML, WordPress, Validation operations
* **Safe Execution**: Sandboxed execution with whitelist/blacklist
* **Reusable**: Save and reuse functions across different imports

= Premium Features =

* **ACF Integration**: Support for Advanced Custom Fields
* **Real Media Library Integration**: Automatic folder creation and organization
* **WooCommerce Support**: Import/export products, orders, and variations
* **YOAST SEO Integration**: Support for YOAST SEO WordPress Plugin
* **Priority Support**: Get help when you need it

= Perfect For =

* **Site Migration**: Moving content between WordPress sites
* **Multi-Site Management**: Sync content across multiple WordPress installations
* **Bulk Operations**: Updating hundreds or thousands of posts at once
* **Data Integration**: Importing data from external systems
* **Content Management**: Exporting for backup or analysis
* **Media Organization**: Organizing FTP-uploaded files into Media Library
* **WooCommerce**: Product catalog management

== Frequently Asked Questions ==

= Where is plugin documentation? =

Fresh and actual documentation located here: https://wpimportexport.com/docs/

= Can I import large files without timeout issues? =

Yes! The plugin uses background processing to handle large files without memory or timeout issues.

= Can I transform data during import? =

Yes! Use the Custom Functions feature to write PHP code that transforms your data. We also provide 50+ ready-to-use examples.

= How do I avoid importing duplicate media files? =

Use the Media Folder Sync feature with duplicate detection. Choose from three methods: Hash (most accurate), Filename (fastest), or Filesize (balanced).

= Is Real Media Library integration included? =

Real Media Library integration is a Premium feature. Upgrade to Premium to automatically create folder structures in RML.

= Can I sync content between two WordPress sites? =

Yes! Use the Site-to-Site Content Sync feature. Connect two sites with API keys and sync posts or custom post types. Choose between Pull (import from remote) or Push (send to remote) operations.


== Screenshots ==

1. Import page 
2. File upload
3. Preview data
4. Fields mapping
5. Data transformation functions
6. Additional import options
7. Export page
8. Filter posts for export
9. Select fields to export
10. Additional export options
11. Content sync options page
12. Page content sync with remote website
13. Bulk content updater page
14. Sync files from any folder with Media Library
15. AI URL Importer
16. AI URL Importer Options
17. Functions library
18. AI Functions Generator
19. Jobs log
20. Plugin options page


`<?php code(); // goes in backticks ?>`