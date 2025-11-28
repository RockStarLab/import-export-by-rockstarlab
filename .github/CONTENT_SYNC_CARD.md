# 🔄 Site-to-Site Content Sync - Quick Reference

## 📌 Feature Overview

**Site-to-Site Content Sync** позволяет синхронизировать контент между двумя WordPress сайтами через REST API с безопасной аутентификацией по API ключу.

---

## 🎯 Key Capabilities

| Capability | Description | Status |
|------------|-------------|--------|
| **Connection Management** | Подключение двух сайтов по API ключу | ✅ Core |
| **Bidirectional Sync** | Pull (импорт) или Push (экспорт) | ✅ Core |
| **All Content Types** | Posts, Pages, CPT, Users, Media, Terms | ✅ Core |
| **Selective Sync** | Фильтрация по ID, дате, автору, статусу | ✅ Core |
| **Media Sync** | Автоматическая загрузка медиа файлов | ✅ Core |
| **Conflict Resolution** | Skip, Update, Duplicate | ✅ Core |
| **Background Processing** | Синхронизация без таймаутов | ✅ Core |
| **Security** | API keys, rate limiting, IP whitelist | ✅ Core |
| **Scheduled Sync** | Автоматическая периодическая синхронизация | 💎 Premium |

---

## 🔑 API Key Authentication

```
┌─────────────┐                          ┌─────────────┐
│   Site A    │                          │   Site B    │
│   (Local)   │                          │  (Remote)   │
└──────┬──────┘                          └──────┬──────┘
       │                                        │
       │  1. Generate API Key                   │
       │ ───────────────────────────────────────▶
       │                                        │
       │  2. Return 64-char Key                 │
       │ ◀───────────────────────────────────────
       │                                        │
       │  3. Create Connection (save key)       │
       │                                        │
       │  4. Verify Connection                  │
       │ ───────────────────────────────────────▶
       │                                        │
       │  5. Connection OK (site info)          │
       │ ◀───────────────────────────────────────
       │                                        │
```

---

## 🔄 Pull Workflow

```
Local Site                                Remote Site
┌──────────────────────────────────────┐ ┌──────────────────────────────────────┐
│                                      │ │                                      │
│  1. User clicks "Pull from Remote"   │ │                                      │
│                                      │ │                                      │
│  2. Select Content Type & Filters    │ │                                      │
│                                      │ │                                      │
│  3. Send Request with API Key        │ │                                      │
│     GET /wp-json/aie/v1/site-sync    │ │                                      │
│     /export?type=posts&...           │ │                                      │
│  ─────────────────────────────────────────────────────────▶                  │
│                                      │ │                                      │
│                                      │ │  4. Validate API Key                │
│                                      │ │                                      │
│                                      │ │  5. Query Posts (filtered)           │
│                                      │ │                                      │
│                                      │ │  6. Prepare Export Data              │
│                                      │ │     - Serialize posts                │
│                                      │ │     - Collect media URLs             │
│                                      │ │     - Collect taxonomy terms         │
│                                      │ │                                      │
│  7. Receive Export Data              │ │                                      │
│  ◀─────────────────────────────────────────────────────────                  │
│                                      │ │                                      │
│  8. Import Posts Locally             │ │                                      │
│     - Check duplicates               │ │                                      │
│     - Create/Update posts            │ │                                      │
│                                      │ │                                      │
│  9. Download Media Files             │ │                                      │
│     GET media URLs from remote       │ │                                      │
│  ─────────────────────────────────────────────────────────▶                  │
│  ◀─────────────────────────────────────────────────────────                  │
│     - Save to wp_posts               │ │                                      │
│                                      │ │                                      │
│  10. Log Sync Operation              │ │                                      │
│                                      │ │                                      │
│  11. Show Success Message            │ │                                      │
│                                      │ │                                      │
└──────────────────────────────────────┘ └──────────────────────────────────────┘
```

---

## 📤 Push Workflow

```
Local Site                                Remote Site
┌──────────────────────────────────────┐ ┌──────────────────────────────────────┐
│                                      │ │                                      │
│  1. User clicks "Push to Remote"     │ │                                      │
│                                      │ │                                      │
│  2. Select Content Type & Items      │ │                                      │
│                                      │ │                                      │
│  3. Query Local Posts                │ │                                      │
│                                      │ │                                      │
│  4. Prepare Export Data              │ │                                      │
│     - Serialize posts                │ │                                      │
│     - Collect media files            │ │                                      │
│                                      │ │                                      │
│  5. Send to Remote with API Key      │ │                                      │
│     POST /wp-json/aie/v1/site-sync   │ │                                      │
│     /import                          │ │                                      │
│  ─────────────────────────────────────────────────────────▶                  │
│                                      │ │                                      │
│                                      │ │  6. Validate API Key                │
│                                      │ │                                      │
│                                      │ │  7. Import Posts                    │
│                                      │ │     - Check duplicates               │
│                                      │ │     - Create/Update posts            │
│                                      │ │                                      │
│                                      │ │  8. Request Media Downloads          │
│                                      │ │     Send media URLs back             │
│                                      │ │                                      │
│  9. Receive Media Requests           │ │                                      │
│  ◀─────────────────────────────────────────────────────────                  │
│                                      │ │                                      │
│  10. Send Media Files                │ │                                      │
│      GET /wp-content/uploads/...     │ │                                      │
│  ─────────────────────────────────────────────────────────▶                  │
│                                      │ │                                      │
│                                      │ │  11. Save Media Files               │
│                                      │ │                                      │
│                                      │ │  12. Return Import Results          │
│                                      │ │                                      │
│  13. Receive Results                 │ │                                      │
│  ◀─────────────────────────────────────────────────────────                  │
│                                      │ │                                      │
│  14. Log Sync Operation              │ │                                      │
│                                      │ │                                      │
│  15. Show Success Message            │ │                                      │
│                                      │ │                                      │
└──────────────────────────────────────┘ └──────────────────────────────────────┘
```

---

## 🗄️ Database Tables

### `aie_site_connections`
Хранит информацию о подключенных сайтах.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `name` | VARCHAR(255) | Название соединения |
| `site_url` | VARCHAR(255) | URL удаленного сайта |
| `api_key` | VARCHAR(64) | API ключ для аутентификации |
| `direction` | ENUM | `pull`, `push`, `bidirectional` |
| `status` | ENUM | `active`, `inactive`, `error` |
| `last_sync_at` | DATETIME | Последняя синхронизация |
| `created_at` | DATETIME | Дата создания |

### `aie_content_sync`
Логи всех операций синхронизации.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `connection_id` | BIGINT | FK → aie_site_connections |
| `operation` | ENUM | `pull`, `push` |
| `content_type` | VARCHAR(50) | `post`, `user`, `media`, `term` |
| `items_total` | INT | Всего элементов |
| `items_synced` | INT | Успешно синхронизировано |
| `items_failed` | INT | Ошибок |
| `error_log` | TEXT | JSON с ошибками |
| `started_at` | DATETIME | Начало операции |
| `completed_at` | DATETIME | Завершение операции |

### `aie_api_keys`
Управление API ключами для входящих запросов.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `api_key` | VARCHAR(64) | Уникальный API ключ |
| `name` | VARCHAR(255) | Название ключа |
| `allowed_ips` | TEXT | JSON с разрешенными IP |
| `rate_limit` | INT | Лимит запросов в минуту |
| `last_used_at` | DATETIME | Последнее использование |
| `expires_at` | DATETIME | Дата истечения |
| `created_at` | DATETIME | Дата создания |
| `is_active` | TINYINT | Активен ли ключ |

---

## 🔒 Security Features

| Feature | Description |
|---------|-------------|
| **API Key Auth** | 64-символьные случайные ключи (cryptographically secure) |
| **Rate Limiting** | Ограничение запросов (по умолчанию 60/минуту) |
| **IP Whitelisting** | Разрешить запросы только с определенных IP |
| **Request Logging** | Логирование всех API запросов |
| **Nonce Verification** | WordPress nonce для защиты от CSRF |
| **Permission Checks** | Проверка `manage_options` capability |
| **SSL Required** | Рекомендуется HTTPS для безопасной передачи |

---

## 🎨 UI Components

### 1. Connections List Page
```
┌─────────────────────────────────────────────────────────────────┐
│ ⚙️  Content Sync                                    [+ New Connection] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Active Connections                                              │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ 🌐 Production Site                                          │ │
│ │ https://example.com                                         │ │
│ │ Direction: Pull | Last Sync: 2 hours ago | Status: ✅ Active│ │
│ │ [Pull Now] [Push Now] [Test] [Edit] [Delete]               │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ 🌐 Staging Site                                             │ │
│ │ https://staging.example.com                                 │ │
│ │ Direction: Bidirectional | Last Sync: Never | Status: ⚠️ New│ │
│ │ [Pull Now] [Push Now] [Test] [Edit] [Delete]               │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ Sync History                                     [View All Logs] │
│                                                                 │
│ [Table with recent sync operations]                             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 2. New Connection Modal
```
┌──────────────────────────────────────────┐
│ ➕ Create New Connection                  │
├──────────────────────────────────────────┤
│                                          │
│ Connection Name:                         │
│ [_____________________________]          │
│                                          │
│ Remote Site URL:                         │
│ [_____________________________]          │
│                                          │
│ Direction:                               │
│ ○ Pull (Import from remote)              │
│ ○ Push (Export to remote)                │
│ ● Bidirectional (Both)                   │
│                                          │
│ [Generate API Key on Remote Site]        │
│                                          │
│ API Key:                                 │
│ [_____________________________]          │
│                                          │
│ [Test Connection]  [Cancel]  [Save]      │
│                                          │
└──────────────────────────────────────────┘
```

### 3. Pull Content Modal
```
┌──────────────────────────────────────────┐
│ ⬇️  Pull Content from Remote              │
├──────────────────────────────────────────┤
│                                          │
│ Connection: [Production Site ▼]          │
│                                          │
│ Content Type:                            │
│ ☑ Posts  ☑ Pages  □ Users  ☑ Media      │
│                                          │
│ Filters:                                 │
│ IDs: [1,2,3 or leave empty]              │
│ Date From: [__________]                  │
│ Date To:   [__________]                  │
│ Author:    [All authors ▼]               │
│ Status:    [All statuses ▼]              │
│                                          │
│ Conflict Resolution:                     │
│ ● Skip existing                          │
│ ○ Update existing                        │
│ ○ Create duplicates                      │
│                                          │
│ ☑ Include media files                    │
│ ☑ Include taxonomies                     │
│ ☑ Process in background                  │
│                                          │
│ [Cancel]  [Start Pull]                   │
│                                          │
└──────────────────────────────────────────┘
```

---

## 📊 Content Types Support

| Type | Pull | Push | Media | Taxonomies | Meta | Comments |
|------|------|------|-------|------------|------|----------|
| **Posts** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Pages** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **CPT** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Users** | ✅ | ✅ | ✅ | - | ✅ | - |
| **Media** | ✅ | ✅ | ✅ | - | ✅ | - |
| **Terms** | ✅ | ✅ | - | - | ✅ | - |
| **Comments** | ✅ | ✅ | - | - | ✅ | - |

---

## 🚀 Usage Scenarios

### Scenario 1: Development → Production
```bash
# Setup
Local (Dev)  →  Remote (Production)
Direction: Push
```

**Use Case**: Отправить новые посты с локального сайта на production.

### Scenario 2: Production → Staging
```bash
# Setup
Remote (Production)  →  Local (Staging)
Direction: Pull
```

**Use Case**: Скопировать контент с production для тестирования.

### Scenario 3: Bidirectional Sync
```bash
# Setup
Site A  ↔  Site B
Direction: Bidirectional
```

**Use Case**: Синхронизация контента между двумя активными сайтами.

### Scenario 4: Multi-Language Sites
```bash
# Setup
Main Site (EN)  →  Translation Site (FR)
Direction: Pull
```

**Use Case**: Импортировать посты для перевода.

---

## 🔗 REST API Endpoints

### POST `/wp-json/aie/v1/site-sync/verify`
Проверить подключение к удаленному сайту.

**Request:**
```json
{
  "site_url": "https://example.com",
  "api_key": "abc123..."
}
```

**Response:**
```json
{
  "success": true,
  "site_name": "Example Site",
  "site_url": "https://example.com",
  "wordpress_version": "6.4"
}
```

### GET `/wp-json/aie/v1/site-sync/export`
Экспортировать контент для удаленного сайта.

**Parameters:**
- `type` (string): `post`, `page`, `user`, `media`, `term`
- `ids` (array): Список ID элементов
- `date_from` (string): Дата начала (Y-m-d H:i:s)
- `date_to` (string): Дата окончания
- `author` (int): ID автора
- `status` (string): `publish`, `draft`, `pending`

### POST `/wp-json/aie/v1/site-sync/import`
Импортировать контент с удаленного сайта.

**Request:**
```json
{
  "type": "post",
  "items": [...],
  "conflict_resolution": "skip",
  "include_media": true
}
```

---

## ✅ Quick Checklist

- [ ] Create connection with API key
- [ ] Test connection
- [ ] Configure content filters
- [ ] Choose conflict resolution strategy
- [ ] Run first sync operation
- [ ] Verify imported content
- [ ] Check sync logs
- [ ] Set up scheduled sync (Premium)

---

## 📚 Related Documentation

- **[CONTENT_SYNC_FEATURE.md](../CONTENT_SYNC_FEATURE.md)** - Complete feature documentation
- **[ARCHITECTURE.md](../ARCHITECTURE.md)** - Section 9: Site-to-Site Content Sync
- **[DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)** - Phase 9.9: Implementation plan
- **[PHASE_9.9_CHECKLIST.md](./PHASE_9.9_CHECKLIST.md)** - Development checklist

---

**Version**: 1.0.0  
**Status**: 📋 Planning Complete  
**Implementation**: ⏳ Pending (Phase 9.9)
