# Media Folder Sync - Синхронизация папок с медиа библиотекой

## Описание функции

Позволяет пользователям синхронизировать файлы из папок на сервере (загруженных через FTP) с медиа библиотекой WordPress.

## Основные возможности

### 1. Сканирование папок
- ✅ Выбор папки на сервере
- ✅ Рекурсивное сканирование (включая вложенные папки)
- ✅ Фильтрация по типам файлов
- ✅ Предпросмотр списка файлов перед импортом

### 2. Типы файлов
- **Все типы** - все разрешенные WordPress MIME типы
- **Только изображения** - jpg, png, gif, webp, svg
- **Пользовательский выбор** - ручной выбор расширений

### 3. Проверка дубликатов
Три метода проверки существующих файлов:

#### Hash (Самый точный)
- MD5 hash файла + размер
- Самый надежный метод
- Медленнее остальных

#### Filename (Самый быстрый)
- Проверка только по имени файла
- Быстрый, но может давать false positives
- Рекомендуется когда имена уникальны

#### Filesize (Баланс)
- Размер + имя файла
- Хороший баланс скорости и точности
- Рекомендуется для больших объемов

### 4. Опции импорта

#### Базовые опции:
- ✅ **Set alt text** - автоматическая установка alt из имени файла
- ✅ **Generate thumbnails** - создание миниатюр для изображений
- ✅ **Preserve folder structure** - сохранение структуры папок

#### Premium опции (через Freemius):
- 👑 **Real Media Library Integration**
  - Автоматическое создание папок в RML
  - Сохранение структуры исходных папок
  - Назначение файлов в соответствующие RML папки

### 5. Пакетная обработка
- Обработка больших объемов файлов
- Background processing через WordPress Cron
- Progress tracking в реальном времени
- Пауза и отмена процесса

### 6. Статистика
- ✓ **Success** - успешно импортированные файлы
- ⊘ **Skipped** - пропущенные файлы (дубликаты)
- ✗ **Failed** - файлы с ошибками
- Размер обработанных файлов
- Время выполнения

## UI/UX

### Главная страница
```
Step 1: Select Folder
  └─ Browse server + Recent folders dropdown

Step 2: File Options  
  └─ All / Images / Custom types

Step 3: Duplicate Handling
  └─ Hash / Filename / Filesize methods

Step 4: Import Options
  └─ Alt text, Thumbnails, Folder structure
  └─ Premium: Real Media Library

Actions:
  [Scan Folder] → Preview
  [Start Sync] → Begin import
```

### Progress Modal
```
┌─────────────────────────────────┐
│ Syncing Files...           [✕] │
├─────────────────────────────────┤
│ Progress: 47 / 247 (19%)        │
│ ████████░░░░░░░░░░░░░░░         │
│                                 │
│ Current: image-047.jpg          │
│                                 │
│ ✓ Success: 45                   │
│ ⊘ Skipped: 2                    │
│ ✗ Failed: 0                     │
│                                 │
│ Time: 00:01:23 / 00:03:45       │
│                                 │
│ [Pause] [Cancel]                │
└─────────────────────────────────┘
```

### Recent Syncs Table
| Date       | Folder          | Files | Status      |
|------------|-----------------|-------|-------------|
| 2025-11-27 | /ftp-import/    | 247   | ✓ Completed |
| 2025-11-25 | /old-media/     | 89    | ✓ Completed |
| 2025-11-20 | /products/      | 456   | ⚠ 3 failed  |

## Технические детали

### Архитектура
**Класс:** `Media_Folder_Sync` в `app/sync/media_folder_sync.php`

**Основные методы:**
```php
scan_folder($path, $options)      // Сканировать папку
sync_files($files, $options)      // Синхронизировать файлы
check_duplicate($file, $method)   // Проверка дубликатов
import_file($file, $options)      // Импорт одного файла
get_sync_stats($job_id)           // Получить статистику
```

### База данных
**Таблица:** `{prefix}_aie_media_sync`

**Поля:**
- `job_id` - связь с заданием
- `folder_path` - путь к папке
- `file_path` - путь к файлу
- `attachment_id` - ID созданного вложения
- `status` - pending, synced, skipped, failed
- `skip_reason` - причина пропуска
- `file_hash` - MD5 hash для проверки дубликатов
- `file_size` - размер файла
- `error_message` - сообщение об ошибке

### JavaScript API
**Модуль:** `src/js/modules/media_sync.js`

```javascript
class MediaFolderSync {
    scanFolder(path, options)      // Сканировать
    startSync(files, options)      // Начать
    updateProgress()               // Обновить прогресс
    pauseSync()                    // Пауза
    cancelSync()                   // Отмена
}
```

### AJAX Endpoints
```php
aie_scan_folder           // Сканировать папку
aie_start_media_sync      // Начать синхронизацию
aie_get_sync_progress     // Получить прогресс
aie_pause_media_sync      // Пауза
aie_cancel_media_sync     // Отмена
```

### REST API
```
POST /wp-json/aie/v1/media-sync/scan
POST /wp-json/aie/v1/media-sync/start
GET  /wp-json/aie/v1/media-sync/progress/{job_id}
GET  /wp-json/aie/v1/media-sync/check-duplicate
```

### Хуки и фильтры

#### Actions:
```php
do_action('aie_before_sync_file', $file_path, $options);
do_action('aie_after_sync_file', $attachment_id, $file_path, $options);
do_action('aie_sync_file_skipped', $file_path, $reason, $existing_id);
do_action('aie_sync_file_error', $file_path, $error);
```

#### Filters:
```php
apply_filters('aie_media_sync_files', $files, $folder_path, $options);
apply_filters('aie_media_sync_allowed_types', $mime_types);
apply_filters('aie_media_sync_alt_text', $alt_text, $file_path);
apply_filters('aie_media_sync_title', $title, $file_path);
```

## Premium интеграция (Freemius)

### Real Media Library
Требования:
- Активная Premium версия плагина
- Установлен плагин Real Media Library

### Функциональность:
```php
if (aie_fs()->is_premium() && function_exists('wp_rml_create')) {
    // Создание структуры папок в RML
    // Автоматическое назначение файлов
}
```

**Пример:**
```
Исходная структура:
/wp-content/uploads/ftp-import/
  ├── products/
  │   ├── electronics/
  │   └── clothing/
  └── banners/

Результат в RML:
RML Root
  └── FTP Import/
      ├── Products/
      │   ├── Electronics/
      │   └── Clothing/
      └── Banners/
```

### Upgrade промо
Для Free версии показывается:
```
┌─────────────────────────────────────────┐
│ 👑 Premium Feature                      │
│                                         │
│ Automatically create folder structure   │
│ in Real Media Library                   │
│                                         │
│ [Upgrade to Premium]                    │
└─────────────────────────────────────────┘
```

## Сценарии использования

### 1. Массовая загрузка через FTP
**Проблема:** Пользователь загрузил 1000 фото через FTP и нужно добавить их в медиа библиотеку

**Решение:**
1. Загрузить файлы в `/wp-content/uploads/bulk-import/`
2. Открыть Media Folder Sync
3. Выбрать папку
4. Включить "Skip duplicates" (hash method)
5. Нажать [Start Sync]
6. Результат: все файлы в медиа библиотеке с thumbnails

### 2. Миграция со старого сайта
**Проблема:** Нужно перенести медиа с другого сайта, но избежать дубликатов

**Решение:**
1. Скопировать папку со старого сайта через FTP
2. Использовать "Hash" метод проверки дубликатов
3. Включить "Preserve folder structure"
4. Premium: включить Real Media Library
5. Результат: структура папок сохранена, дубликаты пропущены

### 3. Организация продуктов WooCommerce
**Проблема:** 500 фото продуктов в папках по категориям

**Решение:**
```
/products/
  ├── t-shirts/      → RML folder "T-Shirts"
  ├── jeans/         → RML folder "Jeans"
  └── accessories/   → RML folder "Accessories"
```

Premium: автоматически создаются папки в RML с той же структурой

## Безопасность

### Валидация
- ✅ Проверка MIME типов WordPress
- ✅ Проверка размера файла
- ✅ Проверка прав доступа к файлам
- ✅ Nonce verification для AJAX
- ✅ Capability check (manage_options)

### Ограничения
- Только разрешенные WordPress типы файлов
- Проверка доступности папки для чтения
- Timeout для обработки (prevent infinite loops)
- Memory limit awareness

## Тестирование

### Unit тесты
```php
// Сканирование папки
$files = $sync->scan_folder('/test-folder/', [
    'recursive' => true,
    'file_types' => ['jpg', 'png']
]);
assertEquals(10, count($files));

// Проверка дубликата
$exists = $sync->check_duplicate('/file.jpg', 'hash');
assertFalse($exists); // Первый раз

// Импорт файла
$id = $sync->import_file('/file.jpg', ['set_alt_text' => true]);
assertTrue($id > 0);

// Проверка дубликата снова
$exists = $sync->check_duplicate('/file.jpg', 'hash');
assertEquals($id, $exists); // Теперь найден
```

### Integration тесты
- Интеграция с Queue_Manager
- Background processing
- RML folder creation (Premium)
- Progress tracking
- Error handling

## Roadmap

### Phase 9.8 (Current)
- ✅ Базовая функциональность
- ✅ UI/UX
- ✅ Premium: RML integration

### Будущие улучшения
- Автоматическая синхронизация по расписанию
- FTP connector для прямого доступа
- Image optimization при импорте
- Metadata extraction (EXIF, IPTC)
- Bulk operations на imported files
- CSV import для metadata assignment

## Документация

- **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Section 8: Media Folder Sync
- **[DEVELOPMENT_PLAN.md](./DEVELOPMENT_PLAN.md)** - Phase 9.8
- **[copilot-instructions.md](./copilot-instructions.md)** - AI coding guidelines

## Лицензия

Эта функция является частью WP Advanced Import Export плагина.

Free версия: Базовая функциональность  
Premium версия: Real Media Library integration

---

**Версия:** 1.0.0  
**Дата:** 2025-11-27  
**Статус:** В разработке (Phase 9.8)
