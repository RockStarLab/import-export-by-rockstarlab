# Import Export by RockStarLab

[![WordPress tested](https://img.shields.io/badge/WordPress-tested%20up%20to%207.0-3858e9.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-1.1.0-informational.svg)](readme.txt)

Import, export, sync, and update WordPress content with CSV, XML, XLSX, ODS, JSON, media sync, AI URL import, and PRO migration workflows.

[Website](https://wpimportexport.com/) · [Documentation](https://wpimportexport.com/docs/) · [Blog & Tutorials](https://wpimportexport.com/blog/)

## Overview

Import Export by RockStarLab helps you move real WordPress content without fragile copy-paste work. Export clean files, import structured content with field mapping, sync selected content between sites, scan server folders into the Media Library, and keep a Jobs Log so repeat work can be re-run instead of rebuilt.

It is built for everyday migrations between local, staging, and production sites; bulk content operations; SEO URL audits; media cleanup; and content workflows where posts, pages, custom fields, SEO data, Elementor layouts, and media relationships need to stay intact.

## Common Use Cases

- Move posts and pages between local, staging, and production.
- Import and export CSV, XML, XLSX, and ODS files with field mapping.
- Export public WordPress URLs to CSV or JSON for SEO audits.
- Sync selected content from one WordPress site to another.
- Register FTP/SFTP-uploaded files in the WordPress Media Library.
- Re-run imports, exports, and sync jobs from history.
- Convert public URLs into draft WordPress content with AI.
- Use the optional PRO addon for custom content types, WooCommerce data, transformations, and bulk content updates.

## Core Features

### Import and Export

- Import CSV, XML, XLSX, and ODS files.
- Export CSV, XML, JSON, XLSX, and ODS files.
- Map source fields to WordPress fields with previews and validation.
- Process large datasets in background batches to reduce timeout and memory-limit problems.
- Upload ZIP archives when they contain exactly one supported import file.
- Download completed exports as ZIP files when ZIP support is available on the server.

### All Site URLs Export

Create a clean inventory of public frontend URLs from your WordPress site:

- Posts, pages, media, and public custom post types.
- Categories, tags, WooCommerce taxonomies, and other public term archives.
- Post type archives, author archives, date archives, homepage/front page, and search result URL.
- Optional RSS, Atom, comments feeds, REST API root, and REST post type endpoints.
- CSV or JSON output with a simple URL column.
- Jobs Log support for re-running URL exports and downloading completed files.

### Site-to-Site Content Sync

- Connect sites with secure 64-character API keys.
- Pull from a remote site or push to it.
- Sync posts and pages in the free plugin.
- Filter by ID, date, author, status, and taxonomy.
- Choose skip, update, or duplicate conflict strategies.
- Download and sync media files automatically.

### Media Folder Sync

- Browse server folders and scan recursively.
- Register FTP/SFTP-uploaded files in the WordPress Media Library.
- Filter by file type or use WordPress-allowed types.
- Detect duplicates by hash, filename, or filesize.
- Preserve folder structure where supported.
- Generate alt text from filenames.
- Integrate with Real Media Library when available.

### AI URL Importer

Use your own OpenAI API key to extract structured content from public URLs and create draft WordPress content.

## PRO Addon

The optional PRO addon expands the plugin for heavier migration and content operations:

- Custom Post Types, Taxonomy Terms, Users, Comments, Media, WooCommerce Products, Orders, Coupons, and Attributes.
- Content Updater for changing existing content in bulk.
- Transformation functions for cleaning, combining, changing, or normalizing values.
- Advanced custom-field and metadata workflows for ACF, SEO plugins, builders, and WooCommerce stores.
- Expanded site-to-site sync coverage for custom content models.
- Agency, WooCommerce, and content-team workflows for staging-to-production releases.

## Works With

- WordPress
- ACF and ACF Pro
- WooCommerce through the optional PRO addon
- Elementor
- Yoast SEO
- Rank Math SEO
- Real Media Library
- OpenAI

## External Services

### OpenAI API

The AI URL Importer can send cleaned page content and the target URL to OpenAI for extraction when you explicitly use that feature.

- Terms: https://openai.com/policies/terms-of-use
- Privacy: https://openai.com/policies/privacy-policy

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later

## Installation

1. Upload the plugin folder to `/wp-content/plugins/import-export-by-rockstarlab`.
2. Activate **Import Export by RockStarLab** from **Plugins** in WordPress.
3. Open the plugin screen in the WordPress admin menu and choose an import, export, sync, media, or jobs workflow.

## Tutorials

- [How to export all WordPress URLs to CSV or JSON](https://wpimportexport.com/how-to-export-all-wordpress-urls-to-csv-or-json/)
- [How to use Jobs Log and schedule WP import and export jobs](https://wpimportexport.com/how-to-use-jobs-log-and-schedule-wp-import-and-export-jobs/)
- [How to export and import WordPress media files without duplicates](https://wpimportexport.com/how-to-export-and-import-wordpress-media-files-without-duplicates/)
- [How to import posts, pages, and custom post types with ACF Pro fields](https://wpimportexport.com/how-to-import-posts-pages-custom-post-types-with-acf-pro-fields/)
- [How to export WordPress posts, pages, and custom post types with ACF Pro fields](https://wpimportexport.com/how-to-export-wordpress-posts-pages-custom-post-types-with-acf-pro-fields/)
- [How to sync content between two WordPress websites without export files](https://wpimportexport.com/how-to-sync-content-between-two-wordpress-websites-without-export-files/)
- [How to import content from a URL into WordPress with AI](https://wpimportexport.com/how-to-import-content-from-a-url-into-wordpress-with-ai/)
- [What are transformation functions and how to use them during import or export](https://wpimportexport.com/what-are-transformation-functions-and-how-to-use-them-during-import-or-export/)
- [How to register FTP/SFTP uploaded files in the WordPress Media Library](https://wpimportexport.com/how-to-register-ftp-sftp-uploaded-files-in-the-wordpress-media-library/)

## FAQ

### Can I import large files without timeout issues?

Yes. The plugin uses background processing to handle large files without memory or timeout issues.

### Can I transform data during import?

Advanced field transformation workflows are available through optional addon integrations.

### How do I avoid importing duplicate media files?

Use Media Folder Sync with duplicate detection. You can choose hash, filename, or filesize matching.

### Can I sync content between two WordPress sites?

Yes. Use Site-to-Site Content Sync to connect two sites with API keys and sync posts and pages in the free plugin. Expanded custom content workflows are available through the optional PRO addon.

## Changelog

### 1.1.0

- Added XLSX and ODS support for import workflows.
- Added XLSX and ODS support for export workflows.
- Added XML support for import and export workflows.
- Added ZIP upload support for imports when the archive contains exactly one supported CSV, XML, XLSX, or ODS file.
- Added optional ZIP download for completed export files when ZIP support is available on the server.
- Improved format-selection cards so available formats display with consistent card heights.

## License

Import Export by RockStarLab is licensed under the GPL v2 or later.
