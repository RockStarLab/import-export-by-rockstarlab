=== Import Export by RockStarLab ===
Contributors: RockstarLab
Tags: ai, export, import, abilities, mcp
Requires at least: 6.2
Tested up to: 7.1.3
Stable tag: 2.1.42
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
* ⌨️ **WP-CLI Commands**: Run imports, exports, schedules and triggers from the command line or CI with `wp rsl-ie import` and `wp rsl-ie export`, without waiting for WP-Cron.
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

= Does it work on WordPress Multisite? =

Yes. Activate it for the whole network or for single sites. Each site keeps its own jobs, schedules, settings and files, and Content Sync can copy posts and pages between sites of the network. Site administrators can use it on their own site.

= Can I run imports and exports with WP-CLI? =

Yes. `wp rsl-ie import posts.csv --type=post --user=admin` imports a file and `wp rsl-ie export --type=post --format=xlsx --output=posts.xlsx --user=admin` exports one. The job runs in the same process until it finishes, so WP-Cron is not needed. Run `wp help rsl-ie` for all commands, including jobs, schedules, triggers and calling any ability. Exit codes: 0 done, 1 error, 2 finished with failed rows.

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

= 2.1.42 =
* Improved: the Jobs log has a Continue button for an import or export that is still running. If a job stopped moving because WP-Cron doesn't run (WP-Cron turned off, or no visitors after you closed the tab), Continue opens its progress page and finishes it from your browser. A continued progress page shows elapsed time, speed and time remaining.
* Improved: when an upload is refused, the import wizard says why, for example "The file is empty.", that a spreadsheet could not be read, or which file types can be imported, instead of "HTTP error! status: 400". WP-CLI and AI agents get the same messages.
* Improved: Auto Map matches the category_names and tag_names columns of a post export to Categories and Tags.
* Improved: when If Match Found is set to Skip, the job log names each skipped row and the existing post it matched.
* Fixed: WP-CLI imports and AI agents map the categories, tags, taxonomy_* and meta_* columns of a post file, so posts imported back keep their categories and tags instead of landing in Uncategorized.
* Fixed: the item count on the export filter step could show the number for earlier filters when several filters were changed quickly.

= 2.1.41 =
* New: JSON imports with nested objects. Values inside an object get their own columns named by their path, such as author.name, author.email and image.url, so they can be mapped like any other column. The original columns stay.
* Improved: Auto Map for JSON columns: author.name, author.email, author.login, image.url and image.alt go to the author and featured image fields; a column that holds a whole object is not mapped to a single-value field; a "published" column with a date goes to Date.

= 2.1.40 =
* Improved: when every row is imported but some rows have notes, for example an author that wasn't found, the import result says "Import Completed with Warnings" and lists them. Job Details in Jobs Log has a Warnings section.
* Improved: Auto Map for comments understands common headers such as Comment ID, Parent ID, Post Slug, Author, Email, Date, Comment, Approved and Website.
* Improved: when a comment's post isn't found and a draft post is created for it, the import result and the job log say so.
* Improved: the job log says "Imported media" for featured images and images in content.
* Fixed: replies imported from a spreadsheet with Source Comment ID and Source Parent Comment ID are threaded under their parent comments, also when the parent came in an earlier import.
* Fixed: a media import from a URL that opens a web page now says "The URL returned a web page, not a file." with a matching hint. Files WordPress refuses by type get their own hint.

= 2.1.39 =
* New: WP-CLI commands. `wp rsl-ie import <file>` and `wp rsl-ie export` run a job in the same process until it finishes, without WP-Cron (`--no-wait` leaves it to WP-Cron). Columns are matched by name, or use `--template` or `--mapping`; `--dry-run` checks a file without importing; exports are copied to `--output`. Also `wp rsl-ie jobs`, `job get|wait|errors|rerun|cancel`, `schedule list|run`, `trigger list|run|log` and `wp rsl-ie ability <name> '<json>'`. Exit codes for scripts and CI: 0 done, 1 error, 2 finished with failed rows, 3 still running after `--timeout`. The commands work even when AI agent access is off.
* New: export form entries. Choose Form Entries in the export wizard, then a Gravity Forms form or a Contact Form 7 form saved by Flamingo. Each field becomes a column under its label; filter by status, date, entry ID or any field. In CSV files, values starting with =, +, - or @ get an apostrophe so spreadsheets don't run them as formulas.
* New: a webhook trigger of an import can accept a file URL in the request and import that file once; columns are matched by name. A status URL returns the counts of a job the trigger started.
* New: ready n8n workflows: one starts a trigger and checks the job until it has finished, one receives the job-finished notification and checks its signature.
* New: WordPress Multisite is tested and supported: network or per-site activation, separate jobs, settings and files for each site, and Content Sync between sites of one network. The Multisite warning is removed.
* New: AI agents can import a file from a URL (upload-from-url), export form entries, and get the n8n workflows as an exact file (workflow_json).
* Improved: post authors in imports. New Author Login field; Author Name also finds a user by display name; existing users are no longer renamed; a row whose author isn't found is noted in the job log.
* Improved: the status "scheduled" in a file is read as a scheduled post.
* Fixed: a recurring import from a URL no longer stops for good when the file can't be downloaded at one run. It tries again at the next run, and Schedules shows why the run didn't start.
* Fixed: after network activation, sites other than the main one showed the plugin menu without its pages.
* Fixed: uninstalling the plugin in a network now cleans every site, and leftover options and scheduled events are removed.
* Fixed: job notifications no longer show a double space when the job has no name.
* Security: when the person running an import can't post unfiltered HTML (site administrators in a multisite network, or DISALLOW_UNFILTERED_HTML), imported post content, excerpts and comments are filtered like in the editor.

= 2.1.38 =
* Fixed: when the file of a "file changes" trigger changed again while the trigger's previous job was still running, the change could be skipped. The next hourly check now starts the import.
* Improved: the Triggers list shows post type names, the wait time in minutes, and the number of runs in correct English.

= 2.1.37 =
* New: event triggers (Jobs Log → Triggers). Run a finished import or export again when another system calls a secure webhook URL (Zapier, Make, n8n, a CRM or a script), when a post of chosen types is published, when a WooCommerce order is created, or when the file an import downloads from a URL changes (checked hourly). Each run is a new job with the same file source, mapping and options, started as the administrator who created the trigger.
* New: webhook triggers use a secret token sent in the X-RSL-Token header or as a Bearer token, never in the URL. The token is shown once, stored only as a hash and can be replaced at any time. Repeated wrong tokens are blocked for an hour.
* New: triggers never start a second job while the previous one is still running, webhook runs are limited to one a minute, and post and order events within the chosen wait time start a single run. Posts created by the plugin's own imports, syncs and migrations do not start triggers.
* New: AI agents can list, create, pause, run and delete triggers, replace a webhook token and read the trigger log (rsl-ie/trigger-sources, trigger-list, trigger-get, trigger-create, trigger-update, trigger-run, trigger-rotate-token, trigger-delete, trigger-log).
* Improved: uninstalling the plugin also removes mapping templates, notification settings and triggers.

= 2.1.36 =
* Fixed: a custom field added in the Field Mapping step with a plain key such as "event_id" was not saved by the import. Keys without a meta_ or _ prefix now get meta_ automatically, and custom fields stored under an underscore key (for example _event_code) can be used to find existing posts.
* Improved: the "Or import a file from a URL" box is hidden while a file is uploading and after it is uploaded, and comes back when you remove the file.
* Improved: the mapping template list in step 4 shows "Loading templates…" until the saved templates are loaded, and Load Template and Save as Template show that they are working.

= 2.1.35 =
* New: field mapping templates. Save the column mapping and import settings of an import under a name ("Save as Template" in steps 4 and 5) and load it for the next file in step 4. Columns are matched by name, so their order may change; columns missing from the new file are listed and their fields highlighted. Templates can be renamed and deleted, and any earlier import can be saved as a template from the Jobs Log.
* New: AI agents can list, read, save, delete and apply mapping templates (rsl-ie/mapping-template-list, -get, -save, -delete, -from-job, -apply). mapping-template-apply returns a mapping and options that import-start accepts unchanged.
* Fixed: an import of posts, pages or custom post types with a custom field as "Check for Existing Items by Field" (for example a supplier code) created a new post every time instead of updating the matching one. Custom fields (meta_ and acf_ columns) now work as the match key, and the list offers only fields an existing post can be found by.
* New: notifications when a job finishes (Jobs Log → Notifications): email, a Slack channel, or a signed webhook (JSON with HMAC-SHA256 signature) for Zapier, Make, n8n or your own system. Choose after every job or only when something failed, and which jobs (imports, exports, Content Sync, Content Migration); edit the subject and message with placeholders. Failed webhook and Slack deliveries are retried, at most 30 notifications are sent per hour, and the screen lists recent deliveries.
* New: readable import errors. Each failed row shows the row number in the file, the field, the error and how to fix it, on the import result screen and in the Jobs Log details. "Download rows with errors (CSV)" saves the failed rows with their values, ready to correct and import again.
* New: AI agents can read and change notification settings, send a test and read the delivery log (rsl-ie/notification-settings-get, -update, notification-test, notification-log), and read the failed rows of an import with fixes and the error CSV (rsl-ie/job-errors).

= 2.1.34 =
* Fixed: importing a damaged ZIP file no longer writes a "Zip stream error: CRC error" PHP warning to the site's error log; the import reports the file as damaged.
* Improved: the Taxonomy Filter of the export screen accepts term names, slugs or IDs, and taxonomy names such as "Categories" or "Tags". Before, only slugs worked, so a category whose slug differs from its name (for example "edge-case-2") was not found.
* New: "Category Names" and "Tag Names" export columns: plain comma-separated names that are easy to read and filter in a spreadsheet. The existing category and tag columns keep the full term data for imports.
* Fixed: an export or preview requested with the field list "*" (as AI agents often do) produced a single empty "*" column. It now exports the default columns.
* New: media export columns "Original File Size" and "Size on Disk, All Sizes". WordPress keeps the original of a large upload next to a scaled-down copy and creates several smaller sizes; these columns show how much space each image really takes.
* Fixed: a Media import set to update existing files stopped with "File source is required" when the file had no file URL column. You can now update alt text, titles, captions and descriptions of existing Media Library items by ID (or another unique field) without the files.

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
