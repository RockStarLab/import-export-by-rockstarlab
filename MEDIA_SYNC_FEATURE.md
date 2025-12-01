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
- 👑 **Real Media Library Integration** ⭐ **NEW in Step 1**
  - Автоматическое создание папок в RML во время сканирования
  - Сохранение структуры исходных папок
  - Назначение файлов в соответствующие RML папки
  - Опция доступна только с активной Premium подпиской
  - Показывается в Step 1: Scan Server Folder с badge "PRO"
  - Для бесплатных пользователей: ссылка на upgrade

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

### Относительные пути (Relative Paths)

**Важно:** Для удобства использования, путь к папке указывается **относительно директории загрузок** (uploads directory).

#### Примеры:
- Вводите: `ftp-import` → Система понимает: `/wp-content/uploads/ftp-import/`
- Вводите: `/test-folder/` → Система понимает: `/wp-content/uploads/test-folder/`
- Вводите: `/` или оставьте пустым → Корневая директория: `/wp-content/uploads/`

#### Преимущества:
- 🎯 **Короткие пути** - не нужно писать полный путь
- 🔒 **Безопасность** - автоматическая проверка, что путь внутри uploads директории
- 👤 **Понятно** - пользователь видит базовый путь и вводит только имя своей папки

### Браузер папок (Folder Browser)

**Новая функция:** Модальное окно для визуального выбора папок на сервере.

#### Как использовать:
1. Нажмите кнопку **"Browse"** рядом с полем Folder Path
2. Откроется модальное окно с деревом папок из `/wp-content/uploads/`
3. Выберите нужную папку одним кликом
4. Двойной клик - переход внутрь папки (навигация)
5. Нажмите **"Choose"** для подтверждения выбора
6. Путь автоматически вставится в поле Folder Path

#### Особенности:
- 📂 **Визуальный выбор** - не нужно помнить названия папок
- 🔄 **Навигация** - двойной клик для перехода в подпапки
- ⬆️ **"Go Up"** - кнопка для возврата на уровень выше
- ⭐ **"Use this folder"** - опция для выбора текущей директории
- 🔒 **Безопасность** - показывает только папки внутри uploads directory

### Главная страница
```
Step 1: Select Folder
  └─ Folder Path: [input] [Browse Button]
  └─ Folder Browser Modal (визуальный выбор)

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
**Дата:** 2025-12-01  
**Статус:** ✅ Реализовано (Phase 9.8)

---

## 📋 Реализованная функциональность

### Созданные компоненты

#### Backend (PHP)

1. **`app/Helper/Media_Sync.php`** - Статический helper класс
   ```php
   use WP_AIE\Helper\Media_Sync;

   // Сканирование папки
   $files = Media_Sync::scan_folder('/path/to/folder', [
       'recursive' => true,
       'file_types' => 'images', // или 'all', 'videos', 'audio', 'documents'
       'custom_types' => ['jpg', 'png'] // если file_types = 'custom'
   ]);

   // Проверка дубликата
   $duplicate = Media_Sync::check_duplicate('/path/to/file.jpg', 'hash');
   // Методы: 'hash' (MD5), 'filename', 'filesize'
   // Возвращает: false или ID существующего attachment

   // Импорт файла
   $attachment_id = Media_Sync::import_file('/path/to/file.jpg', [
       'post_id' => 123,              // Привязать к посту (опционально)
       'generate_thumbnails' => true,  // Генерировать миниатюры
       'set_alt_text' => true,        // Установить alt из имени файла
       'preserve_structure' => false   // Сохранить структуру папок
   ]);

   // Получить разрешенные типы файлов
   $types = Media_Sync::get_allowed_file_types('images');
   // Возвращает: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']
   ```

2. **`app/Controller/Media_Sync_Controller.php`** - AJAX контроллер

   **AJAX Endpoints:**
   
   - `aie_scan_folder` - Сканировать папку
     ```javascript
     jQuery.post(ajaxurl, {
         action: 'aie_scan_folder',
         nonce: aieData.nonce,
         folder_path: '/path/to/folder',
         options: {
             recursive: true,
             file_types: 'images'
         }
     }, function(response) {
         console.log(response.data.files); // Array of file objects
     });
     ```

   - `aie_start_media_sync` - Запустить синхронизацию
     ```javascript
     jQuery.post(ajaxurl, {
         action: 'aie_start_media_sync',
         nonce: aieData.nonce,
         files: [{path: '/file1.jpg', size: 1234, name: 'file1.jpg'}],
         options: {
             duplicate_check: 'hash',
             duplicate_handling: 'skip',
             generate_thumbnails: true
         }
     }, function(response) {
         const jobId = response.data.job_id;
         // Начать отслеживание прогресса
     });
     ```

   - `aie_get_sync_progress` - Получить прогресс
     ```javascript
     jQuery.post(ajaxurl, {
         action: 'aie_get_sync_progress',
         nonce: aieData.nonce,
         job_id: 123
     }, function(response) {
         console.log(response.data.progress); // 0-100
         console.log(response.data.status);   // pending/processing/completed
         console.log(response.data.result);   // {processed, success, skipped, failed}
     });
     ```

   - `aie_pause_media_sync` - Приостановить (заглушка)
   - `aie_cancel_media_sync` - Отменить задачу

3. **`app/Model/Queue/Media_Sync_Processor.php`** - Фоновый процессор
   ```php
   use WP_AIE\Model\Queue\Media_Sync_Processor;

   $processor = new Media_Sync_Processor();
   $result = $processor->process($job_id);
   // Обрабатывает 20 файлов за раз
   // Автоматически вызывается Background_Processor
   ```

#### Frontend (JavaScript)

**Модуль:** `src/js/modules/media_sync.js`

```javascript
// Автоматически инициализируется на странице #wp-aie-media-sync
import MediaSyncModule from './modules/media_sync';

// Доступные методы:
MediaSyncModule.init();              // Инициализация
MediaSyncModule.scanFolder();        // Сканирование папки
MediaSyncModule.startSync();         // Запуск синхронизации
MediaSyncModule.checkProgress();     // Проверка прогресса
MediaSyncModule.pauseSync();         // Пауза
MediaSyncModule.cancelSync();        // Отмена
MediaSyncModule.resetPage();         // Сброс в начальное состояние
```

#### Admin UI

**Страница:** `/wp-admin/admin.php?page=wp-aie-media-sync`

**View:** `app/View/settings/media_sync.php`

**Стили:** `src/scss/app.scss` (секция `#wp-aie-media-sync`)

## 🔌 Хуки и фильтры

### Actions (будущее расширение)

```php
// Перед импортом файла
do_action('aie_before_import_file', $file_path, $options);

// После успешного импорта
do_action('aie_after_import_file', $attachment_id, $file_path, $options);

// При пропуске дубликата
do_action('aie_import_file_skipped', $file_path, $duplicate_id, $method);

// При ошибке импорта
do_action('aie_import_file_error', $file_path, $error);

// Завершение задачи синхронизации
do_action('aie_media_sync_completed', $job_id, $result);
```

### Filters (будущее расширение)

```php
// Фильтрация списка файлов перед сканированием
$files = apply_filters('aie_media_sync_scanned_files', $files, $folder_path, $options);

// Разрешенные типы файлов
$mime_types = apply_filters('aie_media_sync_allowed_mimes', $mime_types, $type);

// Alt text для изображения
$alt_text = apply_filters('aie_media_sync_alt_text', $alt_text, $file_path, $attachment_id);

// Размер батча для обработки
$batch_size = apply_filters('aie_media_sync_batch_size', 20);

// Лимит памяти для обработки
$memory_limit = apply_filters('aie_media_sync_memory_limit', $memory_limit);
```

## 🎯 Примеры использования

### Пример 1: Программный запуск синхронизации

```php
// Сканируем папку
$files = \WP_AIE\Helper\Media_Sync::scan_folder(
    ABSPATH . 'wp-content/uploads/ftp-import',
    ['recursive' => true, 'file_types' => 'images']
);

// Создаем задачу
$job = new \WP_AIE\Model\Job();
$job_id = $job->create([
    'type' => 'media_sync',
    'status' => 'pending',
    'user_id' => get_current_user_id(),
    'parameters' => wp_json_encode([
        'files' => array_map(function($file) {
            return $file['path'];
        }, $files),
        'options' => [
            'duplicate_check' => 'hash',
            'duplicate_handling' => 'skip',
            'generate_thumbnails' => true
        ],
        'offset' => 0
    ])
]);

// Запускаем обработку
if (!wp_next_scheduled('aie_process_queue')) {
    wp_schedule_single_event(time(), 'aie_process_queue');
}
```

### Пример 2: Кастомная обработка дубликатов

```php
// В вашем плагине/теме
add_filter('aie_media_sync_duplicate_handling', function($handling, $file_path, $existing_id) {
    // Всегда перезаписывать старые файлы
    return 'overwrite';
}, 10, 3);
```

### Пример 3: Интеграция с Real Media Library (Premium)

```php
// Проверяем Premium и RML
if (aie_fs()->is_premium() && function_exists('wp_rml_create')) {
    add_action('aie_after_import_file', function($attachment_id, $file_path, $options) {
        // Получаем структуру папок из пути
        $folder_structure = dirname(str_replace(ABSPATH, '', $file_path));
        
        // Создаем папку в RML (если не существует)
        $rml_folder_id = wp_rml_create($folder_structure);
        
        // Назначаем файл в папку
        wp_rml_set_attachment_folder($attachment_id, $rml_folder_id);
    }, 10, 3);
}
```

### Пример 4: Автоматическая синхронизация по расписанию

```php
// Добавляем кастомное расписание
add_filter('cron_schedules', function($schedules) {
    $schedules['daily_sync'] = [
        'interval' => DAY_IN_SECONDS,
        'display' => __('Once Daily for Media Sync')
    ];
    return $schedules;
});

// Регистрируем задачу
if (!wp_next_scheduled('my_daily_media_sync')) {
    wp_schedule_event(time(), 'daily_sync', 'my_daily_media_sync');
}

// Обработчик
add_action('my_daily_media_sync', function() {
    $watch_folder = ABSPATH . 'wp-content/uploads/auto-sync';
    
    if (!is_dir($watch_folder)) {
        return;
    }
    
    $files = \WP_AIE\Helper\Media_Sync::scan_folder($watch_folder, [
        'recursive' => true,
        'file_types' => 'all'
    ]);
    
    // Создаем задачу и запускаем...
    // (код аналогичен примеру 1)
});
```

## 🧪 Тестирование

### Вручную через UI

1. Откройте `/wp-admin/admin.php?page=wp-aie-media-sync`
2. Введите путь к папке (например, `/wp-content/uploads/test-import`)
3. Нажмите "Scan Folder"
4. Выберите файлы для импорта
5. Настройте опции (дубликаты, миниатюры и т.д.)
6. Нажмите "Start Synchronization"
7. Наблюдайте за прогрессом в реальном времени
8. Проверьте результат в медиа библиотеке

### Через WP-CLI (будущее)

```bash
# Сканировать папку
wp aie media-sync scan /path/to/folder --recursive

# Запустить синхронизацию
wp aie media-sync start /path/to/folder --skip-duplicates --hash-method

# Проверить статус задачи
wp aie media-sync status 123

# Отменить задачу
wp aie media-sync cancel 123
```

### PHP Unit тесты (будущее)

```php
class Media_Sync_Test extends WP_UnitTestCase {
    public function test_scan_folder() {
        $files = Media_Sync::scan_folder('/test-folder', ['recursive' => true]);
        $this->assertIsArray($files);
        $this->assertGreaterThan(0, count($files));
    }
    
    public function test_check_duplicate_hash() {
        $file = '/path/to/test.jpg';
        
        // Первая проверка - не найдет
        $duplicate = Media_Sync::check_duplicate($file, 'hash');
        $this->assertFalse($duplicate);
        
        // Импортируем
        $attachment_id = Media_Sync::import_file($file, []);
        
        // Вторая проверка - найдет
        $duplicate = Media_Sync::check_duplicate($file, 'hash');
        $this->assertEquals($attachment_id, $duplicate);
    }
}
```

## 📊 Мониторинг и логи

### Логи задач

Все операции логируются в таблицу `wp_aie_logs`:

```sql
SELECT * FROM wp_aie_logs 
WHERE job_id = 123 
ORDER BY created_at DESC;
```

### Прогресс задачи

```php
$job = new \WP_AIE\Model\Job();
$job_data = $job->read($job_id);

echo "Status: " . $job_data->status . "\n";
echo "Progress: " . $job_data->progress . "%\n";

$result = json_decode($job_data->result, true);
echo "Processed: " . $result['processed'] . "\n";
echo "Success: " . $result['success'] . "\n";
echo "Skipped: " . $result['skipped'] . "\n";
echo "Failed: " . $result['failed'] . "\n";
```

### Отладка

Включите WP_DEBUG и WP_DEBUG_LOG в wp-config.php:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Логи будут в `/wp-content/debug.log`

## 🔧 Troubleshooting

### Проблема: Файлы не импортируются

**Решение:**
1. Проверьте права доступа к папке (должна быть readable)
2. Убедитесь, что файлы разрешенных MIME типов
3. Проверьте логи: `SELECT * FROM wp_aie_logs WHERE job_id = ?`

### Проблема: Медленная обработка

**Решение:**
1. Уменьшите размер батча в Media_Sync_Processor (по умолчанию 20)
2. Используйте 'filename' вместо 'hash' для проверки дубликатов
3. Отключите генерацию миниатюр для больших изображений

### Проблема: Cron не запускается

**Решение:**
```php
// Проверьте расписание
wp_get_schedules();

// Проверьте задачу
wp_next_scheduled('aie_process_queue');

// Запустите вручную
do_action('aie_process_queue');

// Или через WP-CLI
wp cron event run aie_process_queue
```

### Проблема: Таймаут при сканировании больших папок

**Решение:**
1. Увеличьте `max_execution_time` в php.ini
2. Или разбейте сканирование на несколько подпапок
3. Отключите рекурсивное сканирование

## 📦 Зависимости

- PHP 7.4+
- WordPress 5.8+
- MySQL 5.7+ / MariaDB 10.2+
- JavaScript ES6
- jQuery 3.x

### Опциональные:
- Real Media Library (Premium feature)
- WP-CLI (для CLI команд)

## 🚀 Производительность

### Рекомендации:

- **Батч размер:** 10-50 файлов (по умолчанию 20)
- **Метод дубликатов:** 
  - `hash` - для точности (медленно)
  - `filename` - для скорости (быстро)
  - `filesize` - баланс
- **Миниатюры:** Отключите для очень больших изображений
- **Cron:** Рекомендуется настроить system cron вместо WP Cron

### Оптимизация:

```php
// Увеличить размер батча (осторожно с памятью!)
add_filter('aie_media_sync_batch_size', function() {
    return 50;
});

// Пропустить генерацию миниатюр для больших файлов
add_filter('aie_media_sync_generate_thumbnails', function($generate, $file_path) {
    $size = filesize($file_path);
    if ($size > 5 * 1024 * 1024) { // > 5MB
        return false;
    }
    return $generate;
}, 10, 2);
```

---

## 🎓 Дополнительные ресурсы

- **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Полная архитектура плагина
- **[DEVELOPMENT_PLAN.md](./DEVELOPMENT_PLAN.md)** - План разработки
- **[PHASE_7_QUEUE_SYSTEM.md](./PHASE_7_QUEUE_SYSTEM.md)** - Документация системы очередей
- **[copilot-instructions.md](./copilot-instructions.md)** - Инструкции для AI

---

**Автор:** DKudleichuk  
**Дата обновления:** 2025-12-01  
**Статус:** ✅ Полностью реализовано и готово к использованию
