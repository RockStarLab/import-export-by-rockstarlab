# 🚀 Quick Start Commands

Быстрые команды для начала разработки новых фич.

---

## 📋 Prerequisites

Перед началом убедитесь, что:
- [ ] Node.js и npm установлены
- [ ] WordPress 5.8+ запущен
- [ ] PHP 7.4+ доступен
- [ ] MySQL 5.6+ настроен

---

## 🛠️ Initial Setup

```bash
# 1. Install dependencies
npm install

# 2. Start watching files (SCSS + JS)
npm run watch

# Or for one-time build
npm run build
```

---

## 🗄️ Database Setup (Phase 0)

### Option 1: AI Command (Recommended)
```
Начни Phase 0: создай все 8 таблиц базы данных
```

### Option 2: Manual
Выполните SQL из `DEVELOPMENT_PLAN.md` Phase 0 или создайте миграции.

### Tables to Create:
1. `aie_jobs` - Background processing jobs
2. `aie_import_logs` - Import operation logs
3. `aie_export_logs` - Export operation logs
4. `aie_custom_functions` - Custom PHP functions
5. `aie_media_sync` - Media sync operations
6. `aie_site_connections` - Site-to-site connections
7. `aie_content_sync` - Content sync history
8. `aie_api_keys` - API key management

---

## 📁 Phase 9.8: Media Folder Sync

### Start Development
```
Начни Phase 9.8: реализуй Media Folder Sync
```

### Manual Steps

#### 1. Backend (PHP)
```bash
# Create Media_Folder_Sync class
touch app/sync/media_folder_sync.php

# Implement methods:
# - scan_folder()
# - check_duplicate()
# - import_file()
# - sync_files()
```

#### 2. Database
```sql
-- Table already created in Phase 0
-- Verify: aie_media_sync
SELECT * FROM aie_media_sync LIMIT 1;
```

#### 3. REST API
```bash
# Create REST API endpoints in app/sync/
# Endpoints:
# - POST /media-sync/scan
# - POST /media-sync/start
# - GET /media-sync/progress/{job_id}
# - GET /media-sync/check-duplicate
```

#### 4. Admin UI
```bash
# Create admin page
touch app/view/settings/media_sync_page.php

# Implement 4-step wizard:
# Step 1: Select folder
# Step 2: Configure options
# Step 3: Review files
# Step 4: Import progress
```

#### 5. JavaScript
```bash
# Create JS module
touch src/js/modules/media_sync.js

# Implement MediaSync class with methods:
# - scanFolder()
# - startImport()
# - updateProgress()
```

#### 6. Styles (SCSS)
```bash
# Create SCSS file
touch src/scss/media_sync.scss

# Import in main app.scss:
echo "@import 'media_sync';" >> src/scss/app.scss

# Compile
npm run build
```

#### 7. Testing
```bash
# Test with small folder (10 files)
# Test with large folder (100+ files)
# Test duplicate detection
# Test background processing
```

### Check Implementation
Use checklist: `.github/PHASE_9.8_CHECKLIST.md`

**Estimated Time**: 18 hours

---

## 🔄 Phase 9.9: Site-to-Site Content Sync

### Start Development
```
Начни Phase 9.9: реализуй Site-to-Site Content Sync
```

### Manual Steps

#### 1. Backend Classes (PHP)

```bash
# Create 3 main classes
touch app/sync/site_connection_manager.php
touch app/sync/content_sync_manager.php
touch app/sync/site_sync_api.php
```

**Site_Connection_Manager:**
- `create_connection()`
- `test_connection()`
- `verify_remote_site()`
- `generate_api_key()`

**Content_Sync_Manager:**
- `pull_content()`
- `push_content()`
- `sync_posts()`
- `sync_users()`
- `sync_media()`
- `download_remote_media()`

**Site_Sync_API:**
- `register_routes()`
- `check_api_key()`
- `verify_connection()`
- `export_content()`
- `import_content()`

#### 2. Database
```sql
-- Tables already created in Phase 0
-- Verify 3 tables:
SELECT * FROM aie_site_connections LIMIT 1;
SELECT * FROM aie_content_sync LIMIT 1;
SELECT * FROM aie_api_keys LIMIT 1;
```

#### 3. REST API
```bash
# Implement 4 endpoints in Site_Sync_API:
# - POST /site-sync/verify
# - GET /site-sync/export
# - POST /site-sync/import
# - GET /site-sync/list
```

#### 4. Admin UI
```bash
# Create admin page
touch app/view/settings/content_sync_page.php

# Components:
# - Connections list table
# - New Connection modal
# - Pull Content modal
# - Push Content modal
# - Sync History table
```

#### 5. JavaScript
```bash
# Create JS module
touch src/js/modules/content_sync.js

# Implement ContentSync class:
# - createConnection()
# - testConnection()
# - pullContent()
# - pushContent()
# - updateProgress()
```

#### 6. API Keys Management (Optional)
```bash
# Create API keys page
touch app/view/settings/api_keys_page.php

# Features:
# - List all API keys
# - Generate new key
# - Revoke key
# - View usage stats
```

#### 7. Security Implementation
```php
// In Site_Sync_API::check_api_key()

// 1. Validate API key format (64 chars)
// 2. Check key exists in database
// 3. Check expiration date
// 4. Verify IP address (if whitelisted)
// 5. Apply rate limiting (60 req/min)
// 6. Log request
```

#### 8. Styles (SCSS)
```bash
# Create SCSS file
touch src/scss/content_sync.scss

# Import in main app.scss:
echo "@import 'content_sync';" >> src/scss/app.scss

# Compile
npm run build
```

#### 9. Testing
```bash
# Test connection creation
# Test API key authentication
# Test Pull operation (10 posts)
# Test Push operation (10 posts)
# Test media synchronization
# Test conflict resolution (Skip/Update/Duplicate)
# Test rate limiting
# Test large sync (500+ posts)
```

### Check Implementation
Use checklist: `.github/PHASE_9.9_CHECKLIST.md`

**Estimated Time**: 33 hours

---

## 🧪 Testing Commands

### Unit Tests (When Available)
```bash
# Run PHP unit tests
composer test

# Run specific test
composer test -- --filter=MediaFolderSyncTest
```

### Manual Testing

#### Media Sync
```bash
# 1. Upload files via FTP to /wp-content/uploads/temp/
# 2. Go to Admin → Media Sync
# 3. Select folder: /wp-content/uploads/temp/
# 4. Choose duplicate method: Hash
# 5. Click "Start Sync"
# 6. Verify files imported to Media Library
```

#### Site-to-Site Sync
```bash
# Setup 2 local WordPress sites (Site A + Site B)

# On Site B:
# 1. Go to Admin → Content Sync → API Keys
# 2. Generate new API key
# 3. Copy key

# On Site A:
# 1. Go to Admin → Content Sync
# 2. Click "New Connection"
# 3. Enter Site B URL and API key
# 4. Click "Test Connection" (should show Site B info)
# 5. Click "Save"
# 6. Click "Pull Now"
# 7. Select Posts, configure filters
# 8. Click "Start Pull"
# 9. Verify posts imported from Site B
```

---

## 📊 Development Progress

Track your progress:

```bash
# Check Phase 9.8 status
cat .github/PHASE_9.8_CHECKLIST.md | grep "\[ \]" | wc -l
# Shows remaining tasks

# Check Phase 9.9 status
cat .github/PHASE_9.9_CHECKLIST.md | grep "\[ \]" | wc -l
```

---

## 🔗 Useful Links

### Documentation
- **Architecture**: [ARCHITECTURE.md](../ARCHITECTURE.md)
- **Development Plan**: [DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)
- **Media Sync Docs**: [MEDIA_SYNC_FEATURE.md](../MEDIA_SYNC_FEATURE.md)
- **Content Sync Docs**: [CONTENT_SYNC_FEATURE.md](../CONTENT_SYNC_FEATURE.md)

### Quick Reference
- **Media Sync Card**: [.github/MEDIA_SYNC_CARD.md](./.MEDIA_SYNC_CARD.md)
- **Content Sync Card**: [.github/CONTENT_SYNC_CARD.md](./CONTENT_SYNC_CARD.md)

### Checklists
- **Phase 9.8 Checklist**: [.github/PHASE_9.8_CHECKLIST.md](./PHASE_9.8_CHECKLIST.md)
- **Phase 9.9 Checklist**: [.github/PHASE_9.9_CHECKLIST.md](./PHASE_9.9_CHECKLIST.md)

---

## 🆘 Troubleshooting

### npm run watch not working
```bash
# Check Node version
node --version  # Should be 14+

# Reinstall dependencies
rm -rf node_modules
npm install
```

### Database tables not created
```bash
# Check database connection
wp db check

# Manually create tables (see Phase 0)
wp db query < database-schema.sql
```

### Assets not compiling
```bash
# Check webpack.mix.js syntax
npm run build  # Should show errors if any

# Clear cache
rm -rf node_modules/.cache
npm run build
```

### REST API not working
```bash
# Check permalinks
wp rewrite flush

# Test API endpoint
curl -X GET "http://yoursite.local/wp-json/aie/v1/media-sync/scan"
```

---

## 🎯 Next Steps After Implementation

1. **Testing**
   - Unit tests
   - Integration tests
   - Manual testing with real data

2. **Documentation**
   - Update ARCHITECTURE.md if needed
   - Update DEVELOPMENT_PLAN.md progress
   - Add code examples

3. **Premium Features**
   - Implement Real Media Library integration (Phase 9.8)
   - Implement Scheduled Sync (Phase 9.9)

4. **Release**
   - Version bump
   - Changelog update
   - WordPress.org submission

---

## 📝 AI Commands Reference

### Quick Commands
```
# Start database setup
"Начни Phase 0"

# Start Media Folder Sync
"Начни Phase 9.8"

# Start Site-to-Site Content Sync
"Начни Phase 9.9"

# Test implementation
"Протестируй Media Folder Sync с 10 файлами"

# Fix bugs
"Исправь ошибку в Media_Folder_Sync::scan_folder()"

# Add feature
"Добавь поддержку PDF файлов в Media Sync"

# Update documentation
"Обнови MEDIA_SYNC_FEATURE.md с примерами кода"
```

### Detailed Commands
```
# Complete implementation with tests
"Реализуй полностью Phase 9.8 со всеми тестами и документацией"

# Implement specific component
"Создай Media_Folder_Sync class с методами scan_folder(), check_duplicate(), import_file()"

# Create UI
"Создай admin UI для Media Sync с 4-step wizard"

# Debug issue
"Почему Media Sync не обнаруживает дубликаты при использовании Hash метода?"
```

---

**Version**: 1.0.0  
**Last Updated**: 2024  
**Status**: 📋 Ready for Implementation

**Happy Coding! 🚀**
