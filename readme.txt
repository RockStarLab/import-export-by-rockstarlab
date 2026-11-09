=== Import Export by RockStarLab ===
Contributors: RockstarLab
Tags: ai, export, import, abilities, mcp
Requires at least: 6.2
Tested up to: 7.1.3
Stable tag: 2.1.34
Requires PHP: 7.4
License: GPL v2 or later

Built for humans and AI agents. Import, export, and sync WordPress data from WP Admin or your connected AI assistant.

== Description ==

**Built for humans and AI agents.**

Import Export by RockStarLab helps you import, export, and sync WordPress data. Work visually in WP Admin, or connect an AI agent such as Claude Code, Cursor, or Codex to run supported workflows from your assistant's interface.

https://www.youtube.com/watch?v=3flWPACMyuU

**New in 2.0: AI agent access.** Ask your connected agent to prepare an import, export selected content, sync pages between connected sites, or check a job's progress. The plugin exposes these operations through the WordPress Abilities API and the official WordPress MCP Adapter.

Read [what’s new in Import Export by RockStarLab 2.0.0](https://wpimportexport.com/whats-new-in-import-export-by-rockstarlab-2-0-0/) for the full release overview and [how to use the plugin with AI Agents like Claude, CodexGPT, Cursor and others](https://wpimportexport.com/how-to-use-the-wordpress-abilities-api-with-ai-agents-exports-imports-content-sync-and-backups/).

Prefer working in WordPress? Use the familiar admin screens for file uploads, field mapping, previews, imports, exports, content sync, and job history. AI is optional: everyday import, export, and sync workflows need no AI account or API key.

The free plugin includes import/export for posts, pages, comments, and media; All Site URLs export; Media Folder Sync; site-to-site sync for supported core content; and background jobs. Import and export CSV, XML, JSON, XLSX, and ODS files, or use the AI URL Importer to turn public web pages into WordPress content with a configured AI provider.

The optional PRO addon adds custom post types, menus, users, taxonomy terms, WooCommerce data, database table workflows, Content Updater, and field transformations. PRO extends both the admin tools and the operations available to connected agents.

Use it for content migrations between local, staging, and production; recurring data transfers; SEO URL audits; and workflows involving custom fields, SEO metadata, Elementor layouts, and media relationships.

🌐 Website: [wpimportexport.com](https://wpimportexport.com/)
📖 Video Tutorials: [wpimportexport.com/import-export-video-tutorials-for-wordpress](https://wpimportexport.com/import-export-video-tutorials-for-wordpress/)
📰 Blog: [wpimportexport.com/blog](https://wpimportexport.com/blog/)
🚀 PRO version: New users can try PRO free for 1 month, no credit card required.
⏳ **LIMITED OFFERS:** [Lifetime license for the first users](https://checkout.freemius.com/plugin/28907/plan/47555/licenses/4/) | [Get PRO For Free](https://wpimportexport.com/get-pro-for-free/)

Want to try it before installing? Open the Live Plugin Preview on WordPress.org to explore the admin screens in a temporary WordPress Playground site with sample content.

== Work with AI Agents ==

Describe the task in your connected AI assistant and let it use the plugin's available actions. For example:

* "Export my published posts to CSV, including titles, URLs, and SEO metadata."
* "Preview this CSV and suggest a field mapping before importing it as draft pages."
* "Sync these selected pages from staging to my connected production site."
* "Show the progress and errors for my latest import job."

Send requests like these from your connected agent's interface. Available actions depend on your plugin edition, installed integrations, and WordPress permissions.

= Connect your agent =

1. Open **Plugin Options → AI Agent Connection** in WP Admin and turn on **Enable Abilities API**.
2. Follow the setup wizard to check your site and enable the official WordPress MCP Adapter.
3. Create a WordPress Application Password for an account with administrator permissions.
4. Add the displayed MCP endpoint and credentials to your compatible agent or MCP bridge, then ask it to list the plugin's available actions.

For step-by-step instructions and workflow examples, read the [WordPress Abilities API guide for AI agents: exports, imports, content sync, and backups](https://wpimportexport.com/how-to-use-the-wordpress-abilities-api-with-ai-agents-exports-imports-content-sync-and-backups/).

Agent access requires the WordPress Abilities API and a client that can reach your site and authenticate with its MCP endpoint. The connection screen checks the site's requirements. Client setup varies; some assistants need an MCP bridge for the supported authentication method. Developers can also use the Abilities REST API directly.

**You choose when to enable agent access.** It is off by default and can be turned off independently of the admin tools. Agent operations check WordPress permissions, and premium operations require an active PRO license.

**AI agent access and AI URL Importer are separate features.** Connecting an agent does not require an OpenAI API key in this plugin. The AI URL Importer uses a configured AI provider to extract content from public URLs; your external AI assistant uses its own account and service settings.

== Common Use Cases ==

* 🚚 Move posts and pages between local, staging, and production
* 📄 Import and export CSV, XML, JSON, XLSX, and ODS files with field mapping
* 💬 Run supported import, export, and sync tasks through a connected AI agent
* 🔄 Sync selected content from one WordPress site to another
* 🔎 Build a full public URL inventory for SEO audits
* 🖼️ Add FTP/SFTP uploaded files into the Media Library
* 🧾 Re-run imports, exports, and sync jobs from history
* 🤖 Convert public URLs into draft WordPress content with AI
* 🚀 Use PRO for custom post types, users, menus, taxonomy terms, WooCommerce data, transformations, and bulk content updates

== Features ==

Choose the WordPress admin interface or a connected AI agent for your data workflows:

* 💬 **AI Agent Access (New in 2.0)**: Connect compatible agents through MCP to discover and run supported plugin operations.
* 🖱️ **Visual Admin Tools**: Upload files, map fields, preview data, and manage jobs directly in WordPress.
* 📥 **CSV, XML, JSON, XLSX, and ODS Import/Export**: Move posts, pages, comments, and media with flexible field mapping, previews, validation, and background processing.
* 🌍 **All Site URLs Export**: Export a clean CSV or JSON list of public frontend URLs from posts, pages, custom post types, taxonomies, archives, feeds, and REST API endpoints.
* 🔗 **Site-to-Site Content Sync**: Connect WordPress sites with API keys, then push or pull selected content between environments.
* 🖼️ **Media Folder Sync**: Scan server folders and register uploaded files in the WordPress Media Library with duplicate detection.
* 🤖 **AI URL Importer**: Extract structured content from public URLs with a configured AI provider, including OpenAI.
* 🧾 **Jobs Log**: Re-run previous imports, exports, and sync jobs without rebuilding the whole setup.
* 🧪 **Live Plugin Preview**: Try the plugin in a temporary WordPress Playground demo powered by the included blueprint.
* 🧩 **Metadata-Aware Workflows**: Designed for custom fields, SEO metadata, media references, and builder-heavy sites.

== PRO Addon ==

Start free with posts, pages, comments, media, URLs, and core content sync workflows, using WP Admin or a connected AI agent. Choose PRO when you need custom content types, WooCommerce data, bulk updates, or transformations. AI agent access is included in free; PRO expands what your agent can work with.

New users can try the PRO version free for 1 month, no credit card required. Want PRO for lifetime? See how to get it for free here: [wpimportexport.com/get-pro-for-free](https://wpimportexport.com/get-pro-for-free/)

PRO adds:

* 🧱 Import/export for Custom Post Types, Menus, Taxonomy Terms, Users, Database Tables, and WooCommerce Products, Orders, Coupons, Refunds, Customers, Reviews, and Attributes
* ✏️ Content Updater for changing existing content in bulk without doing a full import cycle
* 🪄 Transformation functions for cleaning, combining, changing, or normalizing values during workflows
* 🧬 Advanced custom-field and metadata workflows for ACF, SEO plugins, builders, and WooCommerce stores
* 🔄 More site-to-site sync coverage for custom content models
* 💬 Premium agent operations for supported PRO import, export, sync, Content Updater, and transformation workflows
* 🏢 Workflow tools built for agencies, WooCommerce teams, content teams, and developers managing staging-to-production releases

== Affiliate Program ==

Love Import Export by RockStarLab? Join our affiliate program and earn commissions by recommending the plugin to WordPress agencies, WooCommerce stores, content teams, and site owners who need reliable import, export, migration, and sync workflows.

Affiliate details are available on our website: [wpimportexport.com](https://wpimportexport.com/)

== Why Choose Our Import / Export Plugin? ==

Manage content, metadata, media, sync, jobs, and URL audits in one plugin. Start with visual admin tools, connect an AI agent when it suits your workflow, and add PRO when you need more data types or bulk editing tools.

* **Built for Real WordPress Sites**: Works with posts, pages, media, metadata, ACF, Elementor, Yoast SEO, Rank Math SEO, and WooCommerce workflows.
* **Background Processing**: Large jobs run in batches, with progress tracking and logs.
* **Repeatable Operations**: Save time by re-running jobs from history.
* **Human-Friendly Mapping**: Preview source data and map it to WordPress fields before the job runs.
* **Built for Humans and AI Agents**: Work in WP Admin or use a connected assistant to run supported data operations.
* **Optional AI Content Extraction**: Turn public URLs into WordPress content with the separate AI URL Importer.
* **Free to Start, PRO When You Grow**: Start with core migration and sync tools, then upgrade when you need custom content types, transformations, WooCommerce data, or Content Updater.

= Core Features =

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

= Media Folder Sync =

Easily synchronize files from server folders (uploaded via FTP/SFTP) to your WordPress Media Library:

* **Scan Server Folders**: Browse and select folders with recursive scanning
* **File Type Filters**: Choose specific file types or all WordPress-allowed types
* **Duplicate Detection**: Three methods (Hash, Filename, Filesize) to skip duplicates
* **Preserve Structure**: Maintain original folder hierarchy
* **Batch Processing**: Handle large numbers of files efficiently
* **Auto Alt Text**: Generate alt text from filenames
* **Real Media Library**: Automatically create folder structure in Real Media Library plugin (the plugin is not included)

= PRO Content Types and Tools =

The PRO addon extends Import and Export with additional content types, adds the Content Updater, and enables field transformation integrations.

= Perfect For =

* 🚚 **Site Migration**: Moving content between WordPress sites
* 🌐 **Multi-Site Management**: Sync content across multiple WordPress installations
* ⚡ **Bulk Operations**: Updating hundreds or thousands of posts at once
* 🔌 **Data Integration**: Importing data from external systems
* 📝 **Content Management**: Exporting for backup or analysis
* 🖼️ **Media Organization**: Organizing FTP-uploaded files into Media Library
* 🏢 **Agency Workflows**: Moving content from staging to production without manual rebuilding
* 🔎 **SEO Workflows**: Exporting URLs and preserving SEO metadata during migrations
* 🛒 **WooCommerce**: Product catalog management (via the optional PRO addon)

== Works With ==

* 🧱 WordPress
* 🧩 ACF (Advanced Custom Fields) and ACF Pro (Flexible content, repeaters)
* 🛒 WooCommerce (PRO addon)
* 🎨 Elementor
* 🔎 Yoast SEO
* 📈 RankMath SEO
* 🗂️ Real Media Library
* 🤖 OpenAI (AI URL Importer)
* 💬 Compatible MCP clients, including Claude Code, Cursor, and Codex (connection setup required)

== External services ==

This plugin connects to the following external services:

= OpenAI API =

Used for AI-powered features:

* AI URL Importer (extracts clean content from a URL)

What data is sent and when:

* When you use the AI URL Importer, the plugin fetches the target URL content and sends the cleaned page content and the URL to OpenAI for extraction.

Service provider:

* OpenAI ([terms](https://openai.com/policies/terms-of-use), [privacy](https://openai.com/policies/privacy-policy))

== Tutorials ==

Learn how to use Import Export by RockStarLab in real WordPress workflows:

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

== Frequently Asked Questions ==

= Do I need an AI agent to use this plugin? =

No. You can import, export, map fields, sync content, and manage jobs in WP Admin as before. Agent access is optional and disabled by default.

= Can I use Claude, Cursor, or Codex to manage my WordPress data? =

Yes, with a compatible MCP connection. Open Plugin Options → AI Agent Connection and follow the setup wizard. Your client must support the site's MCP transport and authentication, directly or through a bridge. The agent can discover and run the operations available to your account and plugin edition.

= Is AI agent access included in the free version? =

Yes. The free plugin exposes supported free workflows to connected agents. An active licensed PRO addon adds premium content types and tools, including Content Updater and transformations.

= Do I need an OpenAI API key? =

Not for ordinary imports, exports, sync, or connecting an external agent. Your agent uses its own service setup. The separate AI URL Importer requires a configured AI provider; using OpenAI for extraction requires OpenAI credentials.

= Can I disable AI agent access and keep using WP Admin? =

Yes. Turn off Enable Abilities API on the AI Agent Connection screen. The plugin's agent operations become unavailable, while the admin tools remain usable. Turning it off does not cancel background jobs that have already started.

= Can I import large files without timeout issues? =

The plugin processes large jobs in background batches to reduce timeout and memory-limit problems. Practical limits depend on your hosting resources, file contents, and server configuration.

= Can I transform data during import? =

Yes. The optional PRO addon includes field transformations for import, export, and Content Updater workflows.

= How do I avoid importing duplicate media files? =

Use the Media Folder Sync feature with duplicate detection. Choose from three methods: Hash (most accurate), Filename (fastest), or Filesize (balanced).

= Can I sync content between two WordPress sites? =

Yes! Use the Site-to-Site Content Sync feature. Connect two sites with API keys and sync supported core content in free, with expanded custom content workflows available through the optional PRO addon. Choose between Pull (import from remote) or Push (send to remote) operations.

== Changelog ==

= 2.1.34 =
* Fixed: importing a damaged ZIP file no longer writes a "Zip stream error: CRC error" PHP warning to the site's error log; the import reports the file as damaged.
* Improved: the Taxonomy Filter of the export screen accepts term names, slugs or IDs, and taxonomy names such as "Categories" or "Tags". Before, only slugs worked, so a category whose slug differs from its name (for example "edge-case-2") was not found.
* New: "Category Names" and "Tag Names" export columns: plain comma-separated names that are easy to read and filter in a spreadsheet. The existing category and tag columns keep the full term data for imports.
* Fixed: an export or preview requested with the field list "*" (as AI agents often do) produced a single empty "*" column. It now exports the default columns.
* New: media export columns "Original File Size" and "Size on Disk, All Sizes". WordPress keeps the original of a large upload next to a scaled-down copy and creates several smaller sizes; these columns show how much space each image really takes.
* Fixed: a Media import set to update existing files stopped with "File source is required" when the file had no file URL column. You can now update alt text, titles, captions and descriptions of existing Media Library items by ID (or another unique field) without the files.

= 2.1.33 =
* New: "Parent Title" export column for posts and pages: the title of the parent page next to its ID, empty for top-level pages.
* Improved: Auto-match by Title no longer stops when one title exists on several posts of the remote site. The other posts are matched, and only those rows are left for you to choose, with a message naming the title. AI agents get the same result: the post appears in "ambiguous" with the IDs of the remote posts to choose from.
* Improved: the remote post list in Map Posts for Sync shows the status and date of each post, so posts with the same title can be told apart.
* Fixed: searching the remote post list right after opening it could show "The results could not be loaded" while the first search was still running. The search now waits and retries.
* Fixed: remote post titles in the Map Posts for Sync list are always shown as plain text, and titles with & or other special characters are shown correctly.
* Fixed: an AI agent that renamed a post and then ran Auto-match in the same session could get a match for the old title. Auto-match and the selected-posts list now read the current titles.
* Fixed: Content Sync now sends files that a post links to, such as a PDF in a link, a File block, or a video or audio file, together with the post. Before, the link was changed to the target site but the file was not copied there.
* Fixed: "Last Sync" on the Content Sync screen now shows the time of the last completed push or pull. Before, it changed only when the connection was tested.
* Fixed: CSV, XLSX and ODS exports started by AI agents or other API calls without the header option had no header row. The header row is now included unless it is turned off, as in the export screen.

= 2.1.32 =
* Fixed: pulling a post back to the site it was first pushed from could add a second copy of its comments. Comments that already exist on the post (same author email, date and text) are now matched instead of added again.

= 2.1.31 =
* Improved: XLSX and ODS exports write plain numbers (such as 120, 0.5 or -12) as number cells you can sort and sum, and keep codes with leading zeros (00123), numbers longer than 11 digits (barcodes, tracking numbers) and values like 49.90 as text, so spreadsheet apps don't change them.
* Improved: AI agents can use the same CSV separators as the export screen in rsl-ie/export-start, including multi-character separators of up to 10 characters such as ;; or |~|.

= 2.1.30 =
* New: "Changes only" for scheduled exports of posts and pages. The first run exports everything the filters select; each later run exports only what was modified since the previous completed run started. Also available to AI agents as changed_only in the schedule abilities.
* Improved: a Date or Modified Date filter with a time (for example 2026-11-09 08:30) now compares to the minute instead of the whole day.
* Improved: background exports (scheduled runs and exports started by AI agents) process several batches per cron event. A scheduled export of 74 posts in batches of 3 needed about 25 cron events before; now it finishes in one.
* Improved: exports with filters are much faster on large sites. The posts that match are found once per export instead of once per batch, and Status and Date filters are applied by the database: on a site with 100,000 posts the item counter with a Status filter takes 0.07 s instead of 5.5 s, and each batch after the first 0.1 s instead of 5 s.
* Improved: the default export batch size is 50 instead of 3, and the field accepts up to 500 (the largest value the exporter uses). A 100,000-post export in the browser took about 70 minutes with batches of 3 and about 3 minutes with 500.
* Fixed: a CSV export of a very large site (100,000 posts with content) stopped at the end with a PHP memory error, because the whole file was built in memory. CSV files are now written row by row.
* Fixed: a Content Sync push started from a long-running process (an AI agent through the stdio MCP server, or WP-CLI) could send an older copy of a post that had been edited in the meantime. The selected posts are now read fresh before every push.

Older versions: see changelog.txt in the plugin folder.

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
14. Jobs log
15. Plugin options page
