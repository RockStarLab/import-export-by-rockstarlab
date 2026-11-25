=== Content Sync, Media Sync, Import, Export with AI Agents Support ===
Contributors: RockstarLab
Tags: content-migration, content-sync, ai-agents, abilities, synchronization
Requires at least: 6.2
Tested up to: 7.2
Stable tag: 2.2.59
Requires PHP: 7.4
License: GPL v2 or later

Push and pull posts, pages, media and ACF between WordPress sites. Import and export CSV, XML, JSON, Excel. From WP Admin or your AI agent.

== Description ==

**Sync content and media between WordPress sites: staging, production, client sites. No full migration needed.**

Pick the posts or pages you changed and push them to the live site, or pull them back. Images and featured images, ACF fields (repeaters, groups, flexible content, galleries, clones), Elementor and Gutenberg layouts, synced patterns, categories, authors and comments come along. Links and media addresses are rewritten for the target site, and nothing else on it is touched.

https://www.youtube.com/watch?v=eicm-pxxXMo

Need files instead? Import and export posts, pages, comments and media as CSV, XML (including WordPress export files), JSON, XLSX or ODS, with field mapping, previews and background jobs. Move a whole site's content with Content Migration, or register files uploaded by FTP in the Media Library with Media Folder Sync.

**Works with AI agents.** Connect Claude, Cursor, Codex or another MCP client and ask it to sync pages, prepare an import, export content or check a job. The plugin exposes its operations through the WordPress Abilities API and the official WordPress MCP Adapter. AI is optional: every workflow also works in WP Admin and WP-CLI, without an AI account or API key.

Read [how to sync content between two WordPress websites without export files](https://wpimportexport.com/how-to-sync-content-between-two-wordpress-websites-without-export-files/) and [how to use the plugin with AI agents like Claude, Codex and Cursor](https://wpimportexport.com/how-to-use-the-wordpress-abilities-api-with-ai-agents-exports-imports-content-sync-and-backups/).

**Multilingual sites with Polylang or WPML.** Content Sync, import and export keep the language of every post, page, product, term, menu and media file and its links to the translations, so a German page updates the German page on the other site. The free plugin filters exports by language; languages and translation links travel with the PRO addon.

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

* 🔄 Push edited pages and posts from staging to production, and pull them back
* 🌐 Keep several sites in step: copy posts, pages and media between them
* 🖼️ Sync media with its titles, alt texts and ACF fields, without duplicates
* 🚚 Move content to a new site with media, ACF, Elementor and SEO data
* 📄 Import and export CSV, XML, JSON, XLSX and ODS files with field mapping
* 💬 Run sync, import and export tasks from a connected AI agent
* 🌍 Move multilingual Polylang or WPML content between sites with its languages and translations (PRO addon)
* 🔎 Build a full public URL inventory for SEO audits
* 🧾 Re-run imports, exports and sync jobs from history
* 🤖 Convert public URLs into draft WordPress content with AI
* 🚀 Use PRO for custom post types, users, menus, taxonomy terms, WooCommerce data, transformations and bulk content updates

== Features ==

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
* 🌍 **Polylang and WPML Support**: Filter Polylang exports by language; with PRO (also for WPML), sync, import and export keep languages and translation links (Polylang, Polylang for WooCommerce, WPML and WooCommerce Multilingual).

== PRO Addon ==

Start free with posts, pages, comments, media, URLs, and core content sync workflows, using WP Admin or a connected AI agent. Choose PRO when you need custom content types, WooCommerce data, bulk updates, or transformations. AI agent access is included in free; PRO expands what your agent can work with.

New users can try the PRO version free for 1 month, no credit card required. Want PRO for lifetime? See how to get it for free here: [wpimportexport.com/get-pro-for-free](https://wpimportexport.com/get-pro-for-free/)

PRO adds:

* 🧱 Import/export for Custom Post Types, Menus, Taxonomy Terms, Users, Database Tables, and WooCommerce Products, Orders, Coupons, Refunds, Customers, Reviews, and Attributes
* ✏️ Content Updater for changing existing content in bulk without doing a full import cycle
* 🪄 Transformation functions for cleaning, combining, changing, or normalizing values during workflows
* 🧬 Advanced custom-field and metadata workflows for ACF, SEO plugins, builders, and WooCommerce stores
* 🔄 More site-to-site sync coverage for custom content models
* 🌍 Polylang and WPML support: Content Sync, import and export keep the language of every post, page, product, term, menu and media file and link it to its translations
* 💬 Premium agent operations for supported PRO import, export, sync, Content Updater, and transformation workflows
* 🏢 Workflow tools built for agencies, WooCommerce teams, content teams, and developers managing staging-to-production releases

== Affiliate Program ==

Love Content Sync, Media Sync, Import, Export? Join our affiliate program and earn commissions by recommending the plugin to WordPress agencies, WooCommerce stores, content teams, and site owners who need reliable import, export, migration, and sync workflows.

Affiliate details are available on our website: [wpimportexport.com](https://wpimportexport.com/)

== Why Choose This Plugin? ==

Manage content, metadata, media, sync, jobs, and URL audits in one plugin. Start with visual admin tools, connect an AI agent when it suits your workflow, and add PRO when you need more data types or bulk editing tools.

* **Built for Real WordPress Sites**: Works with posts, pages, media, metadata, ACF, Elementor, Yoast SEO, Rank Math SEO, and WooCommerce workflows.
* **Background Processing**: Large jobs run in batches, with progress tracking and logs.
* **Repeatable Operations**: Save time by re-running jobs from history.
* **Human-Friendly Mapping**: Preview source data and map it to WordPress fields before the job runs.
* **Built for Humans and AI Agents**: Work in WP Admin or use a connected assistant to run supported data operations.
* **Optional AI Content Extraction**: Turn public URLs into WordPress content with the separate AI URL Importer.
* **Free to Start, PRO When You Grow**: Start with core migration and sync tools, then upgrade when you need custom content types, transformations, WooCommerce data, or Content Updater.

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

== Works With ==

* 🧱 WordPress
* 🧩 ACF (Advanced Custom Fields) and ACF Pro (Flexible content, repeaters)
* 🌍 Polylang and Polylang for WooCommerce (languages and translation links with the PRO addon)
* 🌍 WPML and WooCommerce Multilingual (languages and translation links with the PRO addon)
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

== Frequently Asked Questions ==

= Can I sync just one page from staging to the live site? =

Yes. Connect the two sites once with an API key, then use Sync on the page (in the editor or in the Pages list) and choose Push. Only that page, its images and the fields it uses are sent; the rest of the live site is not touched. Pull works the other way.

= Can I import a WordPress export (WXR) file from Tools → Export? =

Yes. Upload the .xml file in a Posts or Pages import. Posts and pages are imported separately, with their featured images, categories, tags, custom fields, Elementor designs, page hierarchy and comments. A Media import of the same file adds its images to the Media Library and attaches them to the imported posts.

= Is this the plugin formerly called "Import Export by RockStarLab"? =

Yes. Only the name changed. The plugin, its settings, jobs, connections and updates stay the same.

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

= Does it work with Polylang? =

Yes. The free plugin exports and imports Polylang sites and can filter an export by language. With the PRO addon, Content Sync sends the selected posts, pages or products together with their translations, each item gets its language on the receiving site, and the translation links are kept. Export files get Language and Translation group columns, so a translator can work in a spreadsheet and the import links the new rows to their translations. Categories, tags, menus, media files, ACF fields and WooCommerce variations go to the version of their own language.

= Does it work with WPML? =

Yes, with the PRO addon. Content Sync sends the selected posts, pages or products together with their WPML translations, each item gets its language on the receiving site, and the translations stay linked. Pages and terms that share a slug across languages keep it, menus arrive as translations of each other, and media files get their translated copies. Export files get Language and Translation group columns, and the export can be filtered by language. Without PRO, the plugin exports and imports a WPML site but does not keep languages and translation links.

== Changelog ==

= 2.2.59 =
* New: Content Sync brings the settings of a category, tag or product category with the post when the other site has to create that term (its term meta, including the WooCommerce category image, display type and order). Terms the other site already has are not changed. Developers can choose the keys with the `rsl_ie_sync_post_term_meta` filter.

= 2.2.58 =
* New: Jobs Log favorites. Click the star in front of a job to add it to your favorites and tick "Favorites only" to see just those. Favorites are personal for each user. AI agents and WP-CLI can use them too (ability job-favorite, job-list favorites_only, `wp rsl-ie job favorite 42`, `wp rsl-ie jobs --favorites`).
* Fix: Content Sync to a site with Polylang could leave the post without its categories and tags (Polylang hid terms of another language from WordPress); they are now assigned.
* Tweak: the Jobs Log shows job names in whole words; the date column may take two lines instead.

= 2.2.57 =
* Fix: media import skipped an image when an earlier import from another site had used the same source ID; an image now counts as already imported only when the file name matches too.
* Fix: Content Sync created a parent category or term that the post itself does not have with a name made from its slug ("Qa" for non-Latin names); parents now get their real names, and a term created by sync keeps its description.
* Fix: Elementor Pro Theme Builder templates (header, footer, single, archive) kept the other site's post, category and author IDs in their display conditions after sync or import; the conditions are now remapped and Elementor's conditions cache is rebuilt.
* Fix: a form entries export that matched no entries wrote the header "*" instead of the form's columns.
* Fix: read-only AI agent abilities whose parameters are all optional (connection, job and schedule lists, UpdraftPlus status and backups) refused a call without input; they now run with defaults.
* Tweak: UpdraftPlus backup and restore errors say what went wrong and what to do (wrong or expired confirmation token, another backup running, not enough disk space and more), translated into Russian and Ukrainian.
* Tweak: WP-CLI accepts a post type name directly, for example `wp rsl-ie export --type=portfolio`.

= 2.2.56 =
* Fix: content migration of WooCommerce products kept the sale dates, purchase note, downloadable files, download limit and download expiry out of the package; they now travel like in a normal product export (PRO).

= 2.2.55 =
* New: hidden custom fields (names starting with "_", such as a plugin's "_price_note") are listed among the export's Custom Fields; add them one by one ("Add all" leaves them out). WordPress, WooCommerce, page builder, SEO and ACF internal keys stay out of the list.
* New: WP-CLI and AI agent imports map the "meta_" custom field columns of this plugin's own export automatically, hidden fields included.
* New: WooCommerce product export has the columns upsell_skus, crosssell_skus and default_attributes (the PRO import restores them).
* Fix: WooCommerce product export stopped with a strpos() error on products whose default attributes were stored next to a custom field of the same name.
* Fix: Content Sync pull kept the other site's product IDs in up-sells and cross-sells; they now point to the local copies (push already did).

Older versions: see changelog.txt in the plugin folder.
