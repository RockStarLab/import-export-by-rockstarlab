<p align="center"><img src="https://ps.w.org/import-export-by-rockstarlab/assets/banner-1544x500.png" alt="Content Sync, Media Sync, Import, Export with AI Agents Support" width="100%"></p>

<h1 align="center">Content Sync, Media Sync, Import, Export with AI Agents Support</h1>

<p align="center"><b>Push and pull posts, pages, media and ACF between WordPress sites. Import and export CSV, XML, JSON, Excel. From WP Admin or your AI agent.</b></p>

<p align="center">

[![Version](https://img.shields.io/wordpress/plugin/v/import-export-by-rockstarlab?label=version&color=4C39C4)](https://wordpress.org/plugins/import-export-by-rockstarlab/) [![Rating](https://img.shields.io/wordpress/plugin/stars/import-export-by-rockstarlab?color=4C39C4)](https://wordpress.org/plugins/import-export-by-rockstarlab/#reviews) [![WordPress tested](https://img.shields.io/wordpress/plugin/tested/import-export-by-rockstarlab?color=4C39C4)](https://wordpress.org/plugins/import-export-by-rockstarlab/) [![PHP](https://img.shields.io/badge/PHP-7.4%2B-4C39C4)](https://wordpress.org/plugins/import-export-by-rockstarlab/) [![License](https://img.shields.io/badge/license-GPLv2%2B-4C39C4)](license.txt)

</p>

<p align="center"><a href="https://wordpress.org/plugins/import-export-by-rockstarlab/"><b>Download on WordPress.org</b></a> · <a href="https://wpimportexport.com/">Website</a> · <a href="https://wpimportexport.com/import-export-video-tutorials-for-wordpress/">Docs &amp; video tutorials</a> · <a href="https://wpimportexport.com/blog/">Blog</a></p>

## Contents

- [Work with AI Agents](#work-with-ai-agents)
- [Common Use Cases](#common-use-cases)
- [Features](#features)
- [PRO Addon](#pro-addon)
- [Why Choose This Plugin?](#why-choose-this-plugin)
- [Works With](#works-with)
- [External services](#external-services)
- [Tutorials](#tutorials)
- [Frequently Asked Questions](#frequently-asked-questions)
- [Changelog](#changelog)

## About

**Sync content and media between WordPress sites: staging, production, client sites. No full migration needed.**

Pick the posts or pages you changed and push them to the live site, or pull them back. Images and featured images, ACF fields (repeaters, groups, flexible content, galleries, clones), Elementor and Gutenberg layouts, synced patterns, categories, authors and comments come along. Links and media addresses are rewritten for the target site, and nothing else on it is touched.

<p align="center"><a href="https://www.youtube.com/watch?v=eicm-pxxXMo"><img src="https://img.youtube.com/vi/eicm-pxxXMo/maxresdefault.jpg" alt="Watch the video" width="720"></a></p>

Need files instead? Import and export posts, pages, comments and media as CSV, XML (including WordPress export files), JSON, XLSX or ODS, with field mapping, previews and background jobs. Move a whole site's content with Content Migration, or register files uploaded by FTP in the Media Library with Media Folder Sync.

**Works with AI agents.** Connect Claude, Cursor, Codex or another MCP client and ask it to sync pages, prepare an import, export content or check a job. The plugin exposes its operations through the WordPress Abilities API and the official WordPress MCP Adapter. AI is optional: every workflow also works in WP Admin and WP-CLI, without an AI account or API key.

Read [how to sync content between two WordPress websites without export files](https://wpimportexport.com/how-to-sync-content-between-two-wordpress-websites-without-export-files/) and [how to use the plugin with AI agents like Claude, Codex and Cursor](https://wpimportexport.com/how-to-use-the-wordpress-abilities-api-with-ai-agents-exports-imports-content-sync-and-backups/).

**Test: AI browser agent vs Content Sync.** We had an AI agent copy a WordPress page between two sites by clicking through wp-admin, then did the same with Content Sync. [See the time, the steps and what went missing](https://wpimportexport.com/ai-browser-agent-vs-content-sync-move-wordpress-page/).

**Agent Skills.** Four ready-made [Agent Skills](https://github.com/RockStarLab/wp-import-export-content-sync-skills) teach Claude Code, Codex and Cursor to sync content, import, export and migrate sites with this plugin's abilities. [How to install and use them](https://wpimportexport.com/wordpress-agent-skills-content-sync-import-export/).

The free plugin includes Content Sync for posts, pages and media; import/export for posts, pages, comments and media; Content Migration; All Site URLs export; Media Folder Sync; WP-CLI and background jobs. The optional PRO addon adds custom post types, menus, users, taxonomy terms, WooCommerce data, database tables, Content Updater and field transformations, for both the admin tools and connected agents.

Formerly named "Import Export by RockStarLab": same plugin, same settings, same updates.

🌐 Website: [wpimportexport.com](https://wpimportexport.com/)  
📖 Video Tutorials: [wpimportexport.com/import-export-video-tutorials-for-wordpress](https://wpimportexport.com/import-export-video-tutorials-for-wordpress/)  
📰 Blog: [wpimportexport.com/blog](https://wpimportexport.com/blog/)  
🚀 PRO version: New users can try PRO free for 1 month, no credit card required.  
⏳ **LIMITED OFFERS:** [Lifetime license for the first users](https://checkout.freemius.com/plugin/28907/plan/47555/licenses/4/) | [Get PRO For Free](https://wpimportexport.com/get-pro-for-free/)  

Want to try it before installing? Open the Live Plugin Preview on WordPress.org to explore the admin screens in a temporary WordPress Playground site with sample content.

## Work with AI Agents

Describe the task in your connected AI assistant and let it use the plugin's available actions. For example:

* "Export my published posts to CSV, including titles, URLs, and SEO metadata."
* "Preview this CSV and suggest a field mapping before importing it as draft pages."
* "Sync these selected pages from staging to my connected production site."
* "Show the progress and errors for my latest import job."

Send requests like these from your connected agent's interface. Available actions depend on your plugin edition, installed integrations, and WordPress permissions.

### Connect your agent

1. Open **Plugin Options → AI Agent Connection** in WP Admin and turn on **Enable Abilities API**.
2. Follow the setup wizard to check your site and enable the official WordPress MCP Adapter.
3. Create a WordPress Application Password for an account with administrator permissions.
4. Add the displayed MCP endpoint and credentials to your compatible agent or MCP bridge, then ask it to list the plugin's available actions.

For step-by-step instructions and workflow examples, read the [WordPress Abilities API guide for AI agents: exports, imports, content sync, and backups](https://wpimportexport.com/how-to-use-the-wordpress-abilities-api-with-ai-agents-exports-imports-content-sync-and-backups/).

Agent access requires the WordPress Abilities API and a client that can reach your site and authenticate with its MCP endpoint. The connection screen checks the site's requirements. Client setup varies; some assistants need an MCP bridge for the supported authentication method. Developers can also use the Abilities REST API directly.

**You choose when to enable agent access.** It is off by default and can be turned off independently of the admin tools. Agent operations check WordPress permissions, and premium operations require an active PRO license.

**AI agent access and AI URL Importer are separate features.** Connecting an agent does not require an OpenAI API key in this plugin. The AI URL Importer uses a configured AI provider to extract content from public URLs; your external AI assistant uses its own account and service settings.

## Common Use Cases

* 🔄 Push edited pages and posts from staging to production, and pull them back
* 🌐 Keep several sites in step: copy posts, pages and media between them
* 🖼️ Sync media with its titles, alt texts and ACF fields, without duplicates
* 🚚 Move content to a new site with media, ACF, Elementor and SEO data
* 📄 Import and export CSV, XML, JSON, XLSX and ODS files with field mapping
* 💬 Run sync, import and export tasks from a connected AI agent
* 🔎 Build a full public URL inventory for SEO audits
* 🧾 Re-run imports, exports and sync jobs from history
* 🤖 Convert public URLs into draft WordPress content with AI
* 🚀 Use PRO for custom post types, users, menus, taxonomy terms, WooCommerce data, transformations and bulk content updates

## Features

Choose the WordPress admin interface or a connected AI agent for your data workflows:

* 🔗 **Site-to-Site Content Sync**: Connect WordPress sites with API keys, then push or pull selected posts, pages and media with their images, ACF fields, Elementor/Gutenberg layouts and synced patterns.
* 💬 **AI Agent Access**: Connect compatible agents through MCP to discover and run supported plugin operations.
* 🖱️ **Visual Admin Tools**: Upload files, map fields, preview data, and manage jobs directly in WordPress.
* ⌨️ **WP-CLI Commands**: Run imports, exports, schedules and triggers from the command line or CI with `wp rsl-ie import` and `wp rsl-ie export`, without waiting for WP-Cron.
* 📥 **CSV, XML, JSON, XLSX, and ODS Import/Export**: Move posts, pages, comments, and media with flexible field mapping, previews, validation, and background processing.
* 🌍 **All Site URLs Export**: Export a clean CSV or JSON list of public frontend URLs from posts, pages, custom post types, taxonomies, archives, feeds, and REST API endpoints.
* 🖼️ **Media Folder Sync**: Scan server folders and register uploaded files in the WordPress Media Library with duplicate detection.
* 🤖 **AI URL Importer**: Extract structured content from public URLs with a configured AI provider, including OpenAI.
* 🧾 **Jobs Log**: Re-run previous imports, exports, and sync jobs without rebuilding the whole setup.
* 🧪 **Live Plugin Preview**: Try the plugin in a temporary WordPress Playground demo powered by the included blueprint.
* 🧩 **Metadata-Aware Workflows**: Designed for custom fields, SEO metadata, media references, and builder-heavy sites.

## PRO Addon

Start free with posts, pages, comments, media, URLs, and core content sync workflows, using WP Admin or a connected AI agent. Choose PRO when you need custom content types, WooCommerce data, bulk updates, or transformations. AI agent access is included in free; PRO expands what your agent can work with.

New users can try the PRO version free for 1 month, no credit card required. Want PRO for lifetime? See how to get it for free here: [wpimportexport.com/get-pro-for-free](https://wpimportexport.com/get-pro-for-free/)

PRO adds:

* 🧱 Import/export for Custom Post Types, Menus, Taxonomy Terms, Users, Database Tables, and WooCommerce Products, Orders, Coupons, Refunds, Customers, Reviews, and Attributes
* ✏️ Content Updater for changing existing content in bulk without doing a full import cycle
* 🪄 Transformation functions for cleaning, combining, changing, or normalizing values during workflows
* 🧬 Advanced custom-field and metadata workflows for ACF, SEO plugins, builders, and WooCommerce stores
* 🔄 More site-to-site sync coverage for custom content models
* 🌍 Polylang support: Content Sync, import and export keep the language of every post, page, product, term, menu and media file and link it to its translations
* 💬 Premium agent operations for supported PRO import, export, sync, Content Updater, and transformation workflows
* 🏢 Workflow tools built for agencies, WooCommerce teams, content teams, and developers managing staging-to-production releases

## Why Choose This Plugin?

Manage content, metadata, media, sync, jobs, and URL audits in one plugin. Start with visual admin tools, connect an AI agent when it suits your workflow, and add PRO when you need more data types or bulk editing tools.

* **Built for Real WordPress Sites**: Works with posts, pages, media, metadata, ACF, Elementor, Yoast SEO, Rank Math SEO, and WooCommerce workflows.
* **Background Processing**: Large jobs run in batches, with progress tracking and logs.
* **Repeatable Operations**: Save time by re-running jobs from history.
* **Human-Friendly Mapping**: Preview source data and map it to WordPress fields before the job runs.
* **Built for Humans and AI Agents**: Work in WP Admin or use a connected assistant to run supported data operations.
* **Optional AI Content Extraction**: Turn public URLs into WordPress content with the separate AI URL Importer.
* **Free to Start, PRO When You Grow**: Start with core migration and sync tools, then upgrade when you need custom content types, transformations, WooCommerce data, or Content Updater.

### Core Features

* **Multiple Data Types**: Posts, Pages, Comments, and Media in free; additional content types via the optional PRO addon
* **All Site URLs**: Export public site URLs from posts, pages, custom post types, taxonomies, post type archives, author/date/search archives, RSS/Atom/comments feeds, and REST API endpoints
* **File Formats**: Import and export CSV, XML, JSON, XLSX, and ODS files
* **Background Processing**: Handle large datasets in batches to reduce timeout and memory-limit problems
* **Field Mapping**: Intuitive field mapping interface with preview
* **Validation System**: Comprehensive data validation before import
* **Media Folder Sync**: Synchronize FTP-uploaded files with WordPress Media Library
* **Site-to-Site Sync**: Connect and sync content between two WordPress sites
* **Progress Tracking**: Real-time progress bars and detailed logs
* **History & Logs**: Complete history of all import/export operations
* **Live Preview Blueprint**: WordPress Playground blueprint support for the Live Plugin Preview on WordPress.org

### All Site URLs

Create a clean inventory of public frontend URLs from your WordPress site:

* **Content URLs**: Posts, Pages, Media, and public Custom Post Types
* **Taxonomy URLs**: Categories, Tags, WooCommerce taxonomies, and other public term archives
* **Archive URLs**: Post type archives, author archives, date archives, homepage/front page, and search results URL
* **Technical URLs**: Optional RSS feeds, Atom feeds, comments feed, post type feeds, REST API root, and REST post type endpoints
* **Simple Output**: CSV or JSON file with a single URL column
* **Bulk Selection**: Quickly select or deselect Posts, Taxonomies, REST endpoints, RSS feeds, Atom feeds, and Comments feeds
* **Jobs Log Support**: Re-run URL export jobs or download completed files from the Jobs Log

### Site-to-Site Content Sync

Synchronize content between two WordPress sites with secure API-based connection:

* **API Key Connection**: Connect sites with secure 64-character API keys
* **Bidirectional Sync**: Pull from remote site or push to it
* **Content Types**: Posts and Pages (additional content types via the optional PRO addon)
* **Selective Sync**: Filter by ID, date, author, status, taxonomy
* **Conflict Resolution**: Skip, Update, or Duplicate strategies
* **Media Sync**: Automatically download and sync media files

### Media Folder Sync

Easily synchronize files from server folders (uploaded via FTP/SFTP) to your WordPress Media Library:

* **Scan Server Folders**: Browse and select folders with recursive scanning
* **File Type Filters**: Choose specific file types or all WordPress-allowed types
* **Duplicate Detection**: Three methods (Hash, Filename, Filesize) to skip duplicates
* **Preserve Structure**: Maintain original folder hierarchy
* **Batch Processing**: Handle large numbers of files efficiently
* **Auto Alt Text**: Generate alt text from filenames
* **Real Media Library**: Automatically create folder structure in Real Media Library plugin (the plugin is not included)

### PRO Content Types and Tools

The PRO addon extends Import and Export with additional content types, adds the Content Updater, and enables field transformation integrations.

### Perfect For

* 🚚 **Site Migration**: Moving content between WordPress sites
* 🌐 **Multi-Site Management**: Sync content across multiple WordPress installations
* ⚡ **Bulk Operations**: Updating hundreds or thousands of posts at once
* 🔌 **Data Integration**: Importing data from external systems
* 📝 **Content Management**: Exporting for backup or analysis
* 🖼️ **Media Organization**: Organizing FTP-uploaded files into Media Library
* 🏢 **Agency Workflows**: Moving content from staging to production without manual rebuilding
* 🔎 **SEO Workflows**: Exporting URLs and preserving SEO metadata during migrations
* 🛒 **WooCommerce**: Product catalog management (via the optional PRO addon)

## Works With

* 🧱 WordPress
* 🧩 ACF (Advanced Custom Fields) and ACF Pro (Flexible content, repeaters)
* 🌍 Polylang and Polylang for WooCommerce (languages and translation links with the PRO addon)
* 🛒 WooCommerce (PRO addon)
* 🎨 Elementor
* 🔎 Yoast SEO
* 📈 RankMath SEO
* 🗂️ Real Media Library
* 🤖 OpenAI (AI URL Importer)
* 💬 Compatible MCP clients, including Claude Code, Cursor, and Codex (connection setup required)

## External services

This plugin connects to the following external services:

### OpenAI API

Used for AI-powered features:

* AI URL Importer (extracts clean content from a URL)

What data is sent and when:

* When you use the AI URL Importer, the plugin fetches the target URL content and sends the cleaned page content and the URL to OpenAI for extraction.

Service provider:

* OpenAI ([terms](https://openai.com/policies/terms-of-use), [privacy](https://openai.com/policies/privacy-policy))

## Tutorials

Learn how to use the plugin in real WordPress workflows:

* [How to use the WordPress Abilities API with AI agents: exports, imports, content sync, and backups](https://wpimportexport.com/how-to-use-the-wordpress-abilities-api-with-ai-agents-exports-imports-content-sync-and-backups/)
* [How to export all WordPress URLs to CSV or JSON](https://wpimportexport.com/how-to-export-all-wordpress-urls-to-csv-or-json/)
* [How to use Jobs Log and schedule WP import and export jobs](https://wpimportexport.com/how-to-use-jobs-log-and-schedule-wp-import-and-export-jobs/)
* [How to export and import WordPress media files without duplicates](https://wpimportexport.com/how-to-export-and-import-wordpress-media-files-without-duplicates/)
* [How to import posts, pages, and custom post types with ACF Pro fields](https://wpimportexport.com/how-to-import-posts-pages-custom-post-types-with-acf-pro-fields/)
* [How to export WordPress posts, pages, and custom post types with ACF Pro fields](https://wpimportexport.com/how-to-export-wordpress-posts-pages-custom-post-types-with-acf-pro-fields/)
* [How to sync content between two WordPress websites without export files](https://wpimportexport.com/how-to-sync-content-between-two-wordpress-websites-without-export-files/)
* [How to import content from a URL into WordPress with AI](https://wpimportexport.com/how-to-import-content-from-a-url-into-wordpress-with-ai/)
* [What are transformation functions and how to use them during import or export](https://wpimportexport.com/what-are-transformation-functions-and-how-to-use-them-during-import-or-export/)
* [How to register FTP/SFTP uploaded files in the WordPress Media Library](https://wpimportexport.com/how-to-register-ftp-sftp-uploaded-files-in-the-wordpress-media-library/)

## Frequently Asked Questions

<details>
<summary><b>Can I sync just one page from staging to the live site?</b></summary>

Yes. Connect the two sites once with an API key, then use Sync on the page (in the editor or in the Pages list) and choose Push. Only that page, its images and the fields it uses are sent; the rest of the live site is not touched. Pull works the other way.

</details>

<details>
<summary><b>Can I import a WordPress export (WXR) file from Tools → Export?</b></summary>

Yes. Upload the .xml file in a Posts or Pages import. Posts and pages are imported separately, with their featured images, categories, tags, custom fields, Elementor designs, page hierarchy and comments. A Media import of the same file adds its images to the Media Library and attaches them to the imported posts.

</details>

<details>
<summary><b>Is this the plugin formerly called "Import Export by RockStarLab"?</b></summary>

Yes. Only the name changed. The plugin, its settings, jobs, connections and updates stay the same.

</details>

<details>
<summary><b>Do I need an AI agent to use this plugin?</b></summary>

No. You can import, export, map fields, sync content, and manage jobs in WP Admin as before. Agent access is optional and disabled by default.

</details>

<details>
<summary><b>Can I use Claude, Cursor, or Codex to manage my WordPress data?</b></summary>

Yes, with a compatible MCP connection. Open Plugin Options → AI Agent Connection and follow the setup wizard. Your client must support the site's MCP transport and authentication, directly or through a bridge. The agent can discover and run the operations available to your account and plugin edition.

</details>

<details>
<summary><b>Does it work on WordPress Multisite?</b></summary>

Yes. Activate it for the whole network or for single sites. Each site keeps its own jobs, schedules, settings and files, and Content Sync can copy posts and pages between sites of the network. Site administrators can use it on their own site.

</details>

<details>
<summary><b>Can I run imports and exports with WP-CLI?</b></summary>

Yes. `wp rsl-ie import posts.csv --type=post --user=admin` imports a file and `wp rsl-ie export --type=post --format=xlsx --output=posts.xlsx --user=admin` exports one. The job runs in the same process until it finishes, so WP-Cron is not needed. Run `wp help rsl-ie` for all commands, including jobs, schedules, triggers and calling any ability. Exit codes: 0 done, 1 error, 2 finished with failed rows.

</details>

<details>
<summary><b>Is AI agent access included in the free version?</b></summary>

Yes. The free plugin exposes supported free workflows to connected agents. An active licensed PRO addon adds premium content types and tools, including Content Updater and transformations.

</details>

<details>
<summary><b>Do I need an OpenAI API key?</b></summary>

Not for ordinary imports, exports, sync, or connecting an external agent. Your agent uses its own service setup. The separate AI URL Importer requires a configured AI provider; using OpenAI for extraction requires OpenAI credentials.

</details>

<details>
<summary><b>Can I disable AI agent access and keep using WP Admin?</b></summary>

Yes. Turn off Enable Abilities API on the AI Agent Connection screen. The plugin's agent operations become unavailable, while the admin tools remain usable. Turning it off does not cancel background jobs that have already started.

</details>

<details>
<summary><b>Can I import large files without timeout issues?</b></summary>

The plugin processes large jobs in background batches to reduce timeout and memory-limit problems. Practical limits depend on your hosting resources, file contents, and server configuration.

</details>

<details>
<summary><b>Can I transform data during import?</b></summary>

Yes. The optional PRO addon includes field transformations for import, export, and Content Updater workflows.

</details>

<details>
<summary><b>How do I avoid importing duplicate media files?</b></summary>

Use the Media Folder Sync feature with duplicate detection. Choose from three methods: Hash (most accurate), Filename (fastest), or Filesize (balanced).

</details>

<details>
<summary><b>Can I sync content between two WordPress sites?</b></summary>

Yes! Use the Site-to-Site Content Sync feature. Connect two sites with API keys and sync supported core content in free, with expanded custom content workflows available through the optional PRO addon. Choose between Pull (import from remote) or Push (send to remote) operations.

</details>

<details>
<summary><b>Does it work with Polylang?</b></summary>

Yes. The free plugin exports and imports Polylang sites and can filter an export by language. With the PRO addon, Content Sync sends the selected posts, pages or products together with their translations, each item gets its language on the receiving site, and the translation links are kept. Export files get Language and Translation group columns, so a translator can work in a spreadsheet and the import links the new rows to their translations. Categories, tags, menus, media files, ACF fields and WooCommerce variations go to the version of their own language.

</details>

## Changelog

### 2.2.49

* Fix (Polylang): Content Sync to a site without Polylang no longer lets one translation replace another that shares its slug; each arrives as its own post.
* Fix: the WooCommerce order export and import offer the field "Language (Polylang)" when Polylang for WooCommerce is active (export, filter and import mapping).
* Fix (Polylang): menu exports keep the options of the language switcher item.

### 2.2.48

* New: export filter "Language (Polylang)" for posts, pages, custom post types and products: pick the languages from a list instead of typing the language taxonomy.
* Fix: Content Sync sets ACF User fields to the matching user of the receiving site (a synced user, or the same login or email) instead of keeping the user ID of the other site. Works for push and pull.
* Fix: Content Sync no longer renames a category that has the same slug as a category used in an ACF Taxonomy field, for example an English and a German category that both use the slug "coffee".
* Fix: comments imported from a file keep the link to their ACF fields, so ACF shows the values.
* Fix: confirming a sync while the list of remote posts was still loading could fail with "This connection is already in use".
* Tweak: the message for a connection that works in one direction only says how to make it work in both.

### 2.2.47

* New: Polylang support (with PRO). Export files get Language and Translation group columns, and exports list every language even when the admin bar filters content by one language or an AI agent exports through the REST API.
* New: Content Sync abilities sync-push and sync-pull accept with_translations: with Polylang and PRO, the translations of the selected posts travel with them (on by default).
* Tweak: the multilingual warning no longer appears for Polylang when PRO is active. Without PRO it says that Polylang is supported by the PRO version.
* New: Media Library sync and Content Migration carry the Polylang language of files, menus and content when PRO is active; a receiving site that does not know file languages still accepts the files.
* Dev: hooks for integrations: rsl_ie_import_item, rsl_ie_import_existing_post, rsl_ie_import_item_saved, rsl_ie_export_terms_value, rsl_ie_export_default_fields, rsl_ie_export_taxonomies, rsl_ie_export_menu_locations, rsl_ie_migration_step_fields, rsl_ie_media_sync_item, rsl_ie_media_sync_received.

### 2.2.46

* Fix: an order import with Product Stock keeps the stock right when the same order is imported again with Update. A cancelled or refunded order puts its stock back, a changed quantity is corrected, and stock is never taken twice. The option is now called "Reduce the stock of ordered products".
* New: "Changes only" schedules work for exports of WooCommerce orders, so an order that was cancelled or refunded since the previous run is exported again.
* New: Content Sync lets the PRO plugin add posts that belong to the selection to a push or pull (the product of a paid course) and bring related content after a pull.

### 2.2.45

* New: Product Stock option for WooCommerce order imports (PRO). "Reduce the stock of ordered products for new orders" lowers the stock of the products in orders that the import creates, so a second store that sells the same stock can send its orders to the main store. Orders that already exist change nothing.
* New: Content Sync lets the PRO plugin send what belongs to a pushed post, such as the topics, sections, lessons and quizzes of a Tutor LMS or LifterLMS course. The push result names what came along.
* New: WooCommerce Tax Rates in the import and export type lists, Jobs Log and WP-CLI (--type=tax-rate) when PRO is active.

Older versions: see changelog.txt in the plugin folder.

Older versions: see [changelog.txt](changelog.txt).

## Requirements

| WordPress | Tested up to | PHP | Stable version |
|---|---|---|---|
| 6.2+ | 7.2 | 7.4+ | 2.2.49 |

---

<sub>This repository mirrors the plugin released on <a href="https://wordpress.org/plugins/import-export-by-rockstarlab/">WordPress.org</a>. README.md is generated from <code>readme.txt</code> on every release. Install the plugin from WordPress.org to get automatic updates. License: GPL v2 or later.</sub>
