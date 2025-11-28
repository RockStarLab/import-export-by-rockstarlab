# Site-to-Site Content Sync - Синхронизация контента между сайтами

## Описание функции

Двусторонняя синхронизация контента между WordPress сайтами с использованием безопасного API ключа. Позволяет легко переносить посты, страницы, пользователей, медиа файлы и таксономии между production, staging и development окружениями.

## Основные возможности

### 1. Управление подключениями
- ✅ Создание подключений к удаленным сайтам
- ✅ Безопасные API ключи (64 символа)
- ✅ Верификация подключений
- ✅ Тестирование соединения
- ✅ Статус мониторинг (Active/Inactive/Error)

### 2. Направления синхронизации
```
Pull (↓)  - Импорт с удаленного сайта
Push (↑)  - Экспорт на удаленный сайт
Both (↔)  - Двусторонняя синхронизация
```

### 3. Типы контента

#### Posts & Pages
- Title, Content, Excerpt
- Status (publish, draft, etc.)
- Meta fields
- Categories & Tags
- Featured images
- Media в контенте
- Authors (опционально)
- Comments (опционально)

#### Users
- Username, Email, Display name
- Roles & Capabilities
- User meta
- Avatar

#### Media Files
- Автоматическое скачивание
- Metadata (alt, title, caption)
- Thumbnails regeneration
- Hash-based дуп

ликатов

#### Taxonomies
- Categories, Tags, Custom taxonomies
- Term meta
- Hierarchy (parent/child)

### 4. Фильтры и выборка

#### Selection modes:
- **All** - весь контент выбранного типа
- **Filtered** - по категориям, датам, авторам
- **Selected** - конкретные ID элементов

#### Фильтры:
- По категориям
- По диапазону дат
- По авторам
- По статусам

### 5. Conflict Resolution

**Три стратегии при конфликтах:**

#### Skip (Пропустить)
- Не импортировать если существует
- Быстро, безопасно
- Рекомендуется для начальной синхронизации

#### Update (Обновить)
- Перезаписать существующий контент
- Используется для updates
- Сохраняет ID

#### Duplicate (Дублировать)
- Создать копию
- Новый ID
- Полезно для копирования структуры

### 6. Опции синхронизации
- ☑ Sync meta fields
- ☑ Sync taxonomies
- ☑ Sync featured images
- ☑ Sync media in content
- ☐ Sync authors (create if not exists)
- ☐ Sync comments

## UI/UX

### Connections List

```
┌──────────────────────────────────────────────────────┐
│ 📡 Production Site                      [Active]     │
│ https://example.com                                  │
│ Direction: ↔ Push & Pull                             │
│ Last sync: 2 hours ago                               │
│ [Pull Content] [Push Content] [Edit] [Delete]       │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│ 📡 Staging Site                         [Active]     │
│ https://staging.example.com                          │
│ Direction: ↓ Pull only                               │
│ Last sync: Never                                     │
│ [Pull Content] [Edit] [Delete]                       │
└──────────────────────────────────────────────────────┘
```

### New Connection Modal

```
┌─────────────────────────────────────┐
│ Create New Connection          [✕] │
├─────────────────────────────────────┤
│ Connection Name:                    │
│ [Production Site______________]     │
│                                     │
│ Remote Site URL:                    │
│ [https://example.com__________]     │
│                                     │
│ API Key (from remote site):         │
│ [••••••••••••••••••••••••]          │
│                                     │
│ Direction:                          │
│ ○ Pull only (← Import)              │
│ ○ Push only (→ Export)              │
│ ● Bidirectional (↔ Both)            │
│                                     │
│ [Test Connection]                   │
│ ✓ Verified successfully             │
│                                     │
│ [Cancel] [Save Connection]          │
└─────────────────────────────────────┘
```

### Pull/Push Content Modal

```
┌─────────────────────────────────────────────┐
│ Pull Content from Production Site      [✕] │
├─────────────────────────────────────────────┤
│                                             │
│ Content Types:                              │
│ ☑ Posts    ☑ Pages     ☐ Users             │
│ ☑ Media    ☑ Taxonomies ☐ Comments         │
│                                             │
│ ═══ Posts Options ═══                       │
│ Post Types:                                 │
│ ☑ Posts  ☐ Products  ☐ Projects            │
│                                             │
│ Selection:                                  │
│ ● All posts                                 │
│ ○ Filtered (by category/date/author)       │
│ ○ Selected (choose specific)               │
│                                             │
│ ═══ Sync Options ═══                        │
│ ☑ Meta fields                               │
│ ☑ Taxonomies                                │
│ ☑ Featured images                           │
│ ☑ Media in content                          │
│ ☐ Authors                                   │
│                                             │
│ Existing Content:                           │
│ ○ Skip if exists                            │
│ ● Update if exists                          │
│ ○ Create duplicate                          │
│                                             │
│ [Preview Selection] [Start Sync]           │
└─────────────────────────────────────────────┘
```

### Progress Modal

```
┌─────────────────────────────────┐
│ Syncing Content...         [✕] │
├─────────────────────────────────┤
│                                 │
│ Connection: Production Site     │
│ Direction: Pull ↓               │
│                                 │
│ Progress: 18 / 25 (72%)         │
│ ████████████████░░░░░           │
│                                 │
│ Current: "WordPress Tips"       │
│                                 │
│ ✓ Created: 12                   │
│ ↻ Updated: 6                    │
│ ⊘ Skipped: 0                    │
│ ✗ Failed: 0                     │
│                                 │
│ Media: 5 / 8 files              │
│                                 │
│ Time: 00:01:15 / ~00:01:45      │
│                                 │
│ [Pause] [Cancel]                │
└─────────────────────────────────┘
```

### Recent Syncs Table

| Date    | Site       | Direction | Type  | Status   |
|---------|------------|-----------|-------|----------|
| 2h ago  | Production | Pull ↓    | Posts | ✓ 25/25  |
| 1d ago  | Production | Push ↑    | Media | ✓ 12/12  |
| 3d ago  | Staging    | Pull ↓    | Posts | ⚠ 18/20  |

## Сценарии использования

### 1. Production → Staging (Pull)

**Задача:** Синхронизировать production контент на staging для тестирования

**Решение:**
1. Создать подключение к production сайту
2. Pull: Посты + Медиа за последние 30 дней
3. Strategy: Update if exists
4. Результат: Staging обновлен свежим контентом

```
Production (https://example.com)
      ↓ Pull
Staging (https://staging.example.com)
```

### 2. Development → Production (Push)

**Задача:** Опубликовать новые посты с development

**Решение:**
1. Создать подключение к production
2. Push: Выбранные посты (Selected IDs)
3. Strategy: Skip if exists (безопасно)
4. Результат: Только новые посты на production

```
Development (http://local.test)
      ↑ Push
Production (https://example.com)
```

### 3. Multi-site синхронизация

**Задача:** Синхронизировать контент между двумя production сайтами

**Решение:**
1. Site A ↔ Site B (Bidirectional)
2. Автоматическая синхронизация новых постов
3. Strategy: Update if exists
4. Sync authors для сохранения авторства

```
Site A (https://site-a.com)
      ↔ Bidirectional
Site B (https://site-b.com)
```

### 4. Backup через Pull

**Задача:** Периодический backup контента

**Решение:**
1. Pull все типы контента
2. Schedule: ежедневно
3. Strategy: Update (обновлять изменения)
4. Результат: Актуальная копия на backup сайте

```
Production
      ↓ Scheduled Pull
Backup Server
```

## Технические детали

### Архитектура

```
Site A (Local)                    Site B (Remote)
     │                                 │
     ├─► Site_Connection_Manager      │
     │   • create_connection()         │
     │   • test_connection()           │
     │                                 │
     ├─► Content_Sync_Manager         │
     │   • pull_content() ────────────┼─► Site_Sync_API
     │   • push_content() ────────────┼─► • verify_connection()
     │   • import_posts()              │   • export_content()
     │   • download_media()            │   • import_content()
     │                                 │   • check_api_key()
     └─► Progress Tracking            │
         • Real-time updates          │
```

### Backend Classes

#### Site_Connection_Manager
```php
- create_connection($data)
- get_connections($filters)
- test_connection($connection_id)
- verify_remote_site($url, $key)
- generate_api_key()
- update/delete_connection()
```

#### Content_Sync_Manager
```php
- pull_content($connection_id, $options)
- push_content($connection_id, $options)
- sync_posts/users/media/terms()
- import_posts/users/media()
- find_existing_content()
- download_remote_media()
```

#### Site_Sync_API
```php
- register_routes()
- check_api_key()
- verify_connection()
- export_content() // для Pull
- import_content() // для Push
- list_content()
```

### Database Schema

#### aie_site_connections
```sql
- id, name, remote_url
- api_key (64 chars, unique)
- direction (pull/push/bidirectional)
- status (active/inactive/error)
- last_sync_at, last_error
- created_by, timestamps
```

#### aie_content_sync
```sql
- id, job_id, connection_id
- direction (pull/push)
- content_type (posts/users/media/terms)
- local_id, remote_id
- action (created/updated/skipped/failed)
- error_message, created_at
```

#### aie_api_keys
```sql
- id, name, api_key
- permissions (JSON)
- allowed_ips (JSON)
- status (active/inactive)
- last_used_at, created_by
```

### REST API Endpoints

```
POST /wp-json/aie/v1/site-sync/verify
  → Верификация подключения
  ← Site info + capabilities

POST /wp-json/aie/v1/site-sync/export
  → content_type, filters, options
  ← Array of data

POST /wp-json/aie/v1/site-sync/import
  → content_type, data, options
  ← Import statistics

POST /wp-json/aie/v1/site-sync/list
  → content_type, filters
  ← List of available items
```

### JavaScript API

```javascript
class ContentSync {
    createConnection(data)
    testConnection(url, key)
    startPull(connection_id, options)
    startPush(connection_id, options)
    previewContent(connection_id, options)
    updateProgress()
}
```

### AJAX Handlers

```php
aie_create_site_connection
aie_test_site_connection
aie_get_site_connections
aie_delete_site_connection
aie_preview_sync_content
aie_start_content_pull
aie_start_content_push
aie_get_sync_progress
aie_pause_content_sync
aie_cancel_content_sync
```

## Security

### API Key Authentication
```php
Header: X-AIE-API-Key: {64-char-key}

// Проверка
$key = $request->get_header('X-AIE-API-Key');
$valid = check_api_key($key);

// Permissions
$permissions = get_api_key_permissions($key);
if (!in_array('posts', $permissions)) {
    return WP_Error('forbidden');
}
```

### Rate Limiting
```
Max 60 requests per minute per API key
429 Too Many Requests при превышении
Transient-based tracking
```

### IP Whitelisting (опционально)
```php
$allowed_ips = get_api_key_allowed_ips($key);
$client_ip = $_SERVER['REMOTE_ADDR'];

if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips)) {
    return WP_Error('ip_blocked');
}
```

### Data Validation
- Sanitize все входящие данные
- Validate content structure
- Check user permissions (manage_options)
- Nonce для AJAX запросов

## Hooks & Filters

### Action Hooks
```php
do_action('aie_before_content_sync', $job_id, $direction, $connection_id);
do_action('aie_before_sync_item', $item_data, $content_type, $direction);
do_action('aie_after_sync_item', $local_id, $remote_id, $content_type, $action);
do_action('aie_sync_item_skipped', $item_data, $reason);
do_action('aie_sync_item_error', $item_data, $error);
do_action('aie_after_content_sync', $job_id, $stats);
```

### Filter Hooks
```php
apply_filters('aie_sync_post_data', $post_data, $direction);
apply_filters('aie_sync_user_data', $user_data, $direction);
apply_filters('aie_sync_term_data', $term_data, $direction);
apply_filters('aie_sync_media_data', $media_data, $direction);
apply_filters('aie_find_existing_content', $existing_id, $item_data, $content_type);
apply_filters('aie_api_key_permissions', $permissions, $api_key);
```

## Performance

### Batch Processing
- 50 items per batch
- Background processing via WP Cron
- Resume after errors
- Progress tracking in real-time

### Media Optimization
- Download только необходимые файлы
- Hash-based duplicate detection
- Parallel downloads (future)
- Thumbnail regeneration async

### Database Optimization
- Indexed columns для searches
- Bulk inserts где возможно
- Prepared statements
- Transaction support

## Monitoring & Logs

### Connection Status
```
Active   - работает нормально
Inactive - отключено пользователем
Error    - последняя синхронизация failed
```

### Sync History
```
Date/Time
Site name
Direction (Pull/Push)
Content types
Items: Total / Success / Failed
Duration
Status
Error messages (if any)
```

### Logging
```php
// Job level
aie_jobs table
- status, progress, timestamps

// Item level
aie_content_sync table
- local_id, remote_id, action, error

// Detailed logs
aie_logs table
- level (info/warning/error)
- message, data (JSON)
```

## Roadmap

### Phase 9.9 (Current)
- ✅ Basic Pull/Push functionality
- ✅ API Key authentication
- ✅ All content types support
- ✅ Conflict resolution
- ✅ UI/UX

### Future Enhancements
- 🔮 Scheduled automatic sync
- 🔮 Webhook triggers
- 🔮 Merge strategy (advanced conflict resolution)
- 🔮 Selective field sync
- 🔮 Bi-directional conflict detection
- 🔮 Visual diff before sync
- 🔮 Rollback functionality
- 🔮 Multi-site hub (1 → many)

## Comparison

### vs Manual Export/Import
| Feature | Manual | Site-to-Site Sync |
|---------|--------|-------------------|
| Speed | Slow (download/upload) | Fast (direct) |
| Automation | Manual process | One click |
| Media | Separate process | Automatic |
| Updates | Full re-import | Selective |
| Scheduling | Not possible | Future |

### vs FTP/Database Sync
| Feature | FTP/DB | Site-to-Site Sync |
|---------|--------|-------------------|
| Safety | Risky (can break) | Safe (WP API) |
| Selective | No | Yes |
| Media | Manual | Automatic |
| Users | Complex | Built-in |
| Validation | None | Full |

## Best Practices

### 1. Test First
Always test connection before bulk sync:
```
[Test Connection] → Verify → Then sync
```

### 2. Start Small
Begin with small batches:
```
Selection: Filtered
Date: Last 7 days
Then expand gradually
```

### 3. Backup Before Push
Push to production? Backup first:
```
Remote site backup → Then Push
```

### 4. Use Skip Strategy First
First sync? Use Skip:
```
Strategy: Skip if exists
Avoids accidental overwrites
```

### 5. Monitor Logs
Check logs after sync:
```
Recent Syncs → View Details
Check for warnings/errors
```

## FAQ

**Q: Можно ли синхронизировать только определенные категории?**  
A: Да, используйте Filtered selection + выберите категории.

**Q: Что если удаленный сайт недоступен?**  
A: Sync failed, сохранится в истории, можно повторить позже.

**Q: Синхронизируются ли custom post types?**  
A: Да, выберите нужные post types в опциях.

**Q: Можно ли синхронизировать WooCommerce продукты?**  
A: Да, после Phase 10 (WooCommerce Integration).

**Q: Безопасна ли передача API ключей?**  
A: Да, используется HTTPS + 64-char keys + permissions.

**Q: Можно ли синхронизировать в обе стороны?**  
A: Да, выберите "Bidirectional" направление.

**Q: Что если на обоих сайтах изменен один пост?**  
A: Conflict resolution: последний wins (update strategy).

**Q: Можно ли исключить определенные поля?**  
A: Future enhancement (Phase 9.9+).

## Documentation

- **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Section 9
- **[DEVELOPMENT_PLAN.md](./DEVELOPMENT_PLAN.md)** - Phase 9.9
- **[copilot-instructions.md](./copilot-instructions.md)** - Guidelines

## Status

```
Planning:    ████████████████████████████ 100%
Development: ░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 0%
Testing:     ░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 0%
Overall:     ███░░░░░░░░░░░░░░░░░░░░░░░░░ 10%
```

**Current Phase:** 9.9  
**Status:** ✅ Planning Complete → 🚧 Ready for Development  
**Next:** "Начни Phase 9.9"

---

**Version:** 1.0.0 | **Date:** 2025-11-27 | **License:** GPL v2+
