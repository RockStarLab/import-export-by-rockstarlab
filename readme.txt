=== Import Export by RockStarLab ===
Contributors: RockstarLab
Tags: import, export, csv, site sync, data migration
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL v2 or later

Advanced import/export for WordPress: CSV workflows, media sync, and site-to-site content sync.

== Description ==

Import Export by RockStarLab helps you move WordPress data between sites and environments. It supports CSV-based import/export workflows, frontend URLs export, custom field mapping, and background jobs for large datasets.

Import and export WordPress content with a flexible workflow that scales to large datasets. Use mapping and background jobs to move data reliably. 📂

It also includes an optional AI URL Importer (when configured) to help extract content from URLs.

== Features ==

Our plugin is packed with tools to make data management seamless:

Core Data Support: Import and Export Posts and Pages with field mapping and background processing. 📝

All Site URLs: Export a clean CSV or JSON list of public frontend URLs from posts, pages, custom post types, taxonomies, archives, feeds, and REST API endpoints. 🔗

Intelligent Sync: Easily sync selected content between sites. Keep your Production and Staging environments in perfect harmony. 🔄

SFTP Media Sync: Automatically detect and add media files uploaded via SFTP directly into the WordPress Media Library. 🖼️

AI-Powered URL Importer: Give the plugin a URL, and our AI will parse the remote website to import content directly as any Post Type. 🤖

Smart Jobs Log: Every action is saved. Re-run any import or export job with a single click without re-configuring parameters. 📜

== PRO Addon ==

This plugin has an optional PRO addon plugin that adds additional Import/Export content types, the Content Updater, and data transformation tools.

== Why Choose Our Import / Export Plugin? ==

When searching for a reliable WordPress import/export plugin, reliability and flexibility are key. Here is why RockstarLab built this:

Developer Friendly: Built-in hooks and filters let developers integrate the free plugin with separate addons and site-specific workflows.

AI Integration: Import content from URLs using your own OpenAI API key. 🧠

Speed & Performance: Optimized to handle large XML, CSV, and JSON files without crashing your server or hitting timeout limits.

Data Integrity: Handles metadata and custom fields during import/export workflows.

= Core Features =

* **Multiple Data Types**: Posts and Pages (additional content types via the optional PRO addon)
* **All Site URLs**: Export public site URLs from posts, pages, custom post types, taxonomies, post type archives, author/date/search archives, RSS/Atom/comments feeds, and REST API endpoints
* **File Formats**: CSV with streaming support for large files and JSON for developer workflows
* **Background Processing**: Handle large datasets without memory limits
* **Field Mapping**: Intuitive field mapping interface with preview
* **Validation System**: Comprehensive data validation before import
* **Media Folder Sync**: Synchronize FTP-uploaded files with WordPress Media Library
* **Site-to-Site Sync**: Connect and sync content between two WordPress sites
* **Progress Tracking**: Real-time progress bars and detailed logs
* **History & Logs**: Complete history of all import/export operations

= All Site URLs =

Create a clean inventory of public frontend URLs from your WordPress site:

* **Content URLs**: Posts, Pages, Media, and public Custom Post Types
* **Taxonomy URLs**: Categories, Tags, WooCommerce taxonomies, and other public term archives
* **Archive URLs**: Post type archives, author archives, date archives, homepage/front page, and search results URL
* **Technical URLs**: Optional RSS feeds, Atom feeds, comments feed, post type feeds, REST API root, and REST post type endpoints
* **Simple Output**: CSV or JSON file with a single URL column
* **Bulk Selection**: Quickly select or deselect Posts, Taxonomies, REST endpoints, RSS feeds, Atom feeds, and Comments feeds
* **Jobs Log Support**: Re-run URL export jobs or download completed files from the Jobs Log

= Site-to-Site Content Sync =

Synchronize content between two WordPress sites with secure API-based connection:

* **API Key Connection**: Connect sites with secure 64-character API keys
* **Bidirectional Sync**: Pull from remote site or push to it
* **Content Types**: Posts and Pages (additional content types via the optional PRO addon)
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
* **PRO Addon: Real Media Library**: Automatically create folder structure in Real Media Library

= What the PRO Addon adds =

The PRO addon extends Import and Export with additional content types (for example: Custom Post Types, Taxonomy Terms, Users, Comments, Media, and more), adds the Content Updater, and enables field transformation integrations.

= Perfect For =

* **Site Migration**: Moving content between WordPress sites
* **Multi-Site Management**: Sync content across multiple WordPress installations
* **Bulk Operations**: Updating hundreds or thousands of posts at once
* **Data Integration**: Importing data from external systems
* **Content Management**: Exporting for backup or analysis
* **Media Organization**: Organizing FTP-uploaded files into Media Library
* **WooCommerce**: Product catalog management (via the optional PRO addon)

== External services ==

This plugin connects to the following external services:

= OpenAI API =

Used for AI-powered features:

* AI URL Importer (extracts clean content from a URL)

What data is sent and when:

* When you use the AI URL Importer, the plugin fetches the target URL content and sends the cleaned page content and the URL to OpenAI for extraction.

Service provider:

* OpenAI (terms: https://openai.com/policies/terms-of-use, privacy: https://openai.com/policies/privacy-policy)

== Frequently Asked Questions ==

= Where is plugin documentation? =

Fresh and actual documentation located here: https://wpimportexport.com/docs/

= Can I import large files without timeout issues? =

Yes! The plugin uses background processing to handle large files without memory or timeout issues.

= Can I transform data during import? =

Advanced field transformation workflows are available through optional addon integrations.

= How do I avoid importing duplicate media files? =

Use the Media Folder Sync feature with duplicate detection. Choose from three methods: Hash (most accurate), Filename (fastest), or Filesize (balanced).

= Is Real Media Library integration included? =

Real Media Library integration is available via the optional PRO addon.

= Can I sync content between two WordPress sites? =

Yes! Use the Site-to-Site Content Sync feature. Connect two sites with API keys and sync posts or custom post types. Choose between Pull (import from remote) or Push (send to remote) operations.


== Screenshots ==

1. Import page 
2. File upload
3. Preview data
4. Fields mapping
5. Additional import options
6. Export page
7. Filter posts for export
8. Select fields to export
9. Additional export options
10. Content sync options page
11. Page content sync with remote website
12. Sync files from any folder with Media Library
13. AI URL Importer
14. AI URL Importer Options
15. Jobs log
16. Plugin options page
