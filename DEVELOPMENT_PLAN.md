# План разработки WP Advanced Import Export

## Общая информация

**Цель**: Создать профессиональный плагин для импорта и экспорта данных WordPress с модульной архитектурой и поддержкой различных форматов.

**Методология**: Поэтапная разработка с тестированием после каждого этапа.

**Документация**:
- `ARCHITECTURE.md` - Подробная архитектура проекта
- `copilot-instructions.md` - Правила кодирования для ИИ
- Этот файл - План разработки

---

## 📋 PHASE 0: Подготовка и фундамент

### Цель фазы
Подготовить базовую инфраструктуру проекта и создать основу для дальнейшей разработки.

### Задачи

#### 0.1 Обновление структуры проекта
- [ ] Создать необходимые директории в `app/`
  - `app/model/import/`
  - `app/model/export/`
  - `app/model/format/`
  - `app/model/validator/`
  - `app/model/queue/`
- [ ] Создать интерфейсы для основных компонентов
- [ ] Настроить autoloader для новых namespace

#### 0.2 Database Schema
- [ ] Создать миграции для custom tables:
  - `{prefix}_aie_jobs` - история заданий
  - `{prefix}_aie_logs` - логи выполнения
  - `{prefix}_aie_field_maps` - сохраненные маппинги
  - `{prefix}_aie_custom_functions` - пользовательские функции обработки
  - `{prefix}_aie_media_sync` - синхронизация медиа папок
  - `{prefix}_aie_site_connections` - подключения между сайтами
  - `{prefix}_aie_content_sync` - история синхронизации контента
  - `{prefix}_aie_api_keys` - API ключи для входящих подключений
- [ ] Написать класс `Database_Migration` для управления схемой
- [ ] Добавить методы активации/деактивации плагина

**Критерии завершения**:
- ✅ Все директории созданы
- ✅ Таблицы создаются при активации плагина (8 таблиц)
- ✅ Таблицы удаляются при деинсталляции (опционально)

**Тестирование**:
```php
// Проверить создание таблиц
global $wpdb;
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}aie_%'");
// Должно быть 8 таблиц: jobs, logs, field_maps, custom_functions, 
// media_sync, site_connections, content_sync, api_keys
// Должно быть 4 таблицы
```

---

## 📋 PHASE 1: Базовая структура и Helper классы

### Цель фазы
Создать вспомогательные классы и утилиты, которые будут использоваться во всех компонентах.

### Задачи

#### 1.1 File Helper - Работа с файлами
- [ ] Создать `app/helper/file.php`
- [ ] Реализовать класс `File_Helper`
- [ ] Реализовать методы:
  ```php
  - upload_file($file)              // Загрузка с валидацией
  - validate_file($file, $rules)    // Проверка типа, размера, безопасности
  - get_upload_dir()                // Безопасная директория загрузок
  - delete_file($path)              // Удаление файла
  - get_file_info($path)            // Получение информации о файле
  - stream_read($path, $callback)   // Чтение большого файла по частям
  ```

#### 1.2 Logger - Система логирования
- [ ] Создать `app/helper/logger.php`
- [ ] Реализовать класс `Logger`
- [ ] Реализовать методы:
  ```php
  - log($job_id, $level, $message, $data)  // Запись лога
  - get_job_logs($job_id)                  // Получение логов задания
  - clear_old_logs($days)                  // Очистка старых логов
  - export_logs($job_id, $format)          // Экспорт логов
  ```

#### 1.3 Security Helper - Безопасность
- [ ] Создать `app/helper/security.php`
- [ ] Реализовать класс `Security_Helper`
- [ ] Реализовать методы:
  ```php
  - validate_nonce($nonce, $action)        // Проверка nonce
  - check_capability($capability)          // Проверка прав
  - sanitize_file_name($filename)          // Очистка имени файла
  - validate_mime_type($file, $allowed)    // Проверка MIME
  - scan_file_content($file)               // Сканирование содержимого
  ```

#### 1.4 Data Mapper - Маппинг данных
- [ ] Создать `app/helper/data_mapper.php`
- [ ] Реализовать класс `Data_Mapper`
- [ ] Реализовать методы:
  ```php
  - map($source_data, $mapping)              // Применить маппинг
  - save_mapping($name, $data_type, $map)    // Сохранить пресет
  - load_mapping($id)                        // Загрузить пресет
  - suggest_mapping($source_fields, $target) // Авто-маппинг
  ```

**Критерии завершения**:
- ✅ Все helper классы созданы и имеют PHPDoc
- ✅ Методы возвращают WP_Error при ошибках
- ✅ Логирование работает и записывает в БД

**Тестирование**:
```php
// Тест File_Helper
$result = File_Helper::upload_file($_FILES['import_file']);
if (is_wp_error($result)) {
    echo $result->get_error_message();
}

// Тест Logger
Logger::log(1, 'info', 'Test message', ['test' => true]);
$logs = Logger::get_job_logs(1);
```

---

## 📋 PHASE 2: Format Handlers - Парсеры файлов

### Цель фазы
Создать систему для работы с различными форматами файлов (CSV, JSON, XML).

### Задачи

#### 2.1 Интерфейс File_Format_Interface
- [ ] Создать `app/model/format/file_format_interface.php`
- [ ] Определить контракт:
  ```php
  interface File_Format_Interface {
      public function parse($file_path, $options = []);      // Парсинг файла
      public function parse_chunk($file_path, $offset, $limit); // Чтение части
      public function generate($data, $file_path, $options);    // Генерация файла
      public function validate($file_path);                     // Валидация формата
      public function get_headers($file_path);                  // Получение заголовков
  }
  ```

#### 2.2 Csv_Format - CSV парсер
- [ ] Создать `app/model/format/csv_format.php`
- [ ] Реализовать класс `Csv_Format`
- [ ] Реализовать интерфейс File_Format_Interface
- [ ] Поддержка разных разделителей (comma, semicolon, tab)
- [ ] Поддержка разных кодировок (UTF-8, Windows-1251)
- [ ] Обработка больших файлов (streaming)
- [ ] Автоопределение delimiter и encoding

#### 2.3 Json_Format - JSON парсер
- [ ] Создать `app/model/format/json_format.php`
- [ ] Реализовать класс `Json_Format`
- [ ] Реализовать интерфейс File_Format_Interface
- [ ] Поддержка разных структур JSON:
  - Массив объектов: `[{}, {}, {}]`
  - Объект с массивом: `{"items": [{}, {}]}`
  - Line-delimited JSON: `{}\n{}\n{}`
- [ ] Валидация JSON структуры
- [ ] Streaming для больших файлов

#### 2.4 Xml_Format - XML парсер
- [ ] Создать `app/model/format/xml_format.php`
- [ ] Реализовать класс `Xml_Format`
- [ ] Реализовать интерфейс File_Format_Interface
- [ ] Использовать XMLReader для streaming
- [ ] Поддержка разных XML структур
- [ ] XPath для навигации
- [ ] Валидация против XSD (опционально)

#### 2.5 Format_Factory - Фабрика форматов
- [ ] Создать `app/model/format/format_factory.php`
- [ ] Реализовать класс `Format_Factory`
- [ ] Метод для создания нужного формата:
  ```php
  public static function create($format) {
      // Возвращает Csv_Format | Json_Format | Xml_Format
  }
  ```
- [ ] Автоопределение формата по расширению
- [ ] Регистрация custom форматов через фильтр

**Критерии завершения**:
- ✅ Все три формата реализованы
- ✅ Поддержка streaming для больших файлов
- ✅ Корректная обработка ошибок
- ✅ Factory возвращает нужный класс

**Тестирование**:
```php
// Тест CSV
$csv = Format_Factory::create('csv');
$data = $csv->parse('test.csv');
$headers = $csv->get_headers('test.csv');

// Тест JSON
$json = Format_Factory::create('json');
$data = $json->parse('test.json');

// Тест streaming
$csv->parse_chunk('large.csv', 0, 100); // Первые 100 строк
```

---

## 📋 PHASE 3: Validation System - Система валидации

### Цель фазы
Создать гибкую систему валидации данных перед импортом.

### Задачи

#### 3.1 Validation_Rule - Базовый класс
- [ ] Создать `app/model/validator/validation_rule.php`
- [ ] Реализовать класс `Validation_Rule`
- [ ] Реализовать Chain of Responsibility паттерн:
  ```php
  abstract class Validation_Rule {
      protected $next;
      
      public function set_next(Validation_Rule $rule);
      public function validate($value, $context = []);
      abstract protected function check($value, $context);
  }
  ```

#### 3.2 Базовые правила валидации
- [ ] `required_rule.php` - Класс `Required_Rule` - Обязательное поле
- [ ] `data_type_rule.php` - Класс `Data_Type_Rule` - Проверка типа данных (string, int, email, url)
- [ ] `length_rule.php` - Класс `Length_Rule` - Проверка длины строки
- [ ] `range_rule.php` - Класс `Range_Rule` - Проверка числового диапазона
- [ ] `regex_rule.php` - Класс `Regex_Rule` - Проверка по регулярному выражению
- [ ] `unique_rule.php` - Класс `Unique_Rule` - Проверка уникальности (в БД)
- [ ] `exists_rule.php` - Класс `Exists_Rule` - Проверка существования записи

#### 3.3 Validator - Менеджер валидации
- [ ] Создать `app/model/validator/validator.php`
- [ ] Реализовать класс `Validator`
- [ ] Реализовать методы:
  ```php
  - add_rule($field, $rule)              // Добавить правило
  - validate($data)                      // Валидировать данные
  - get_errors()                         // Получить ошибки
  - validate_batch($rows)                // Валидировать массив
  ```

#### 3.4 Интеграция с системой
- [ ] Добавить фильтры для расширения правил
- [ ] Создать пресеты правил для разных типов данных (posts, users)
- [ ] UI для настройки правил валидации (Phase 5)

**Критерии завершения**:
- ✅ Все базовые правила реализованы
- ✅ Chain of Responsibility работает
- ✅ Детальные сообщения об ошибках
- ✅ Возможность добавления custom правил

**Тестирование**:
```php
// Создание цепочки правил
$validator = new Validator();
$validator->add_rule('email', new Required_Rule());
$validator->add_rule('email', new Data_Type_Rule('email'));
$validator->add_rule('post_title', new Required_Rule());
$validator->add_rule('post_title', new Length_Rule(1, 200));

// Валидация
$result = $validator->validate([
    'email' => 'test@example.com',
    'post_title' => 'Test Post'
]);

if (!$result->is_valid()) {
    print_r($result->get_errors());
}
```

---

## 📋 PHASE 4: Import System - Система импорта

### Цель фазы
Создать ядро системы импорта с поддержкой базовых типов контента WordPress.

### Задачи

#### 4.1 Интерфейсы и абстракции
- [ ] Создать `app/model/import/importer_interface.php`:
  ```php
  interface Importer_Interface {
      public function import($data, $options = []);
      public function validate($data);
      public function prepare($raw_data);
      public function get_required_fields();
      public function get_optional_fields();
  }
  ```
- [ ] Создать `app/model/import/abstract_importer.php` - класс `Abstract_Importer` с общей логикой

#### 4.2 Post_Importer - Импорт постов
- [ ] Создать `app/model/import/post_importer.php`
- [ ] Реализовать класс `Post_Importer`
- [ ] Реализовать методы:
  ```php
  - import($data, $options)
      * Создание/обновление постов
      * Обработка post_meta
      * Связь с taxonomies
      * Обработка featured image
  - handle_duplicates($post, $mode)  // skip, update, create
  - import_meta($post_id, $meta)
  - import_taxonomies($post_id, $terms)
  ```

#### 4.3 User_Importer - Импорт пользователей
- [ ] Создать `app/model/import/user_importer.php`
- [ ] Реализовать класс `User_Importer`
- [ ] Реализовать:
  ```php
  - import($data, $options)
      * Создание пользователей
      * user_meta
      * Роли и capabilities
      * Генерация паролей
  - send_notification($user_id, $password)  // Email уведомление
  ```

#### 4.4 Comment_Importer - Импорт комментариев
- [ ] Создать `app/model/import/comment_importer.php`
- [ ] Реализовать класс `Comment_Importer`
- [ ] Привязка к существующим постам
- [ ] comment_meta
- [ ] Статусы комментариев

#### 4.5 Media_Importer - Импорт медиа
- [ ] Создать `app/model/import/media_importer.php`
- [ ] Реализовать класс `Media_Importer`
- [ ] Загрузка файлов из URL
- [ ] Загрузка из ZIP архива
- [ ] Генерация thumbnails
- [ ] Привязка к постам

#### 4.6 Importer_Factory
- [ ] Создать `app/model/import/importer_factory.php`
- [ ] Реализовать класс `Importer_Factory`
- [ ] Фабрику для создания импортеров:
  ```php
  Importer_Factory::create('posts');  // Post_Importer
  Importer_Factory::create('users');  // User_Importer
  ```

#### 4.7 Import_Controller - Главный контроллер
- [ ] Создать `app/controller/import.php`
- [ ] Реализовать класс `Import_Controller`
- [ ] Реализовать flow:
  ```php
  1. upload_file()       // Загрузка файла
  2. parse_file()        // Парсинг
  3. validate_data()     // Валидация
  4. create_job()        // Создание задания
  5. process_job()       // Обработка
  6. get_progress()      // Прогресс
  ```

**Критерии завершения**:
- ✅ Импорт posts, users, comments работает
- ✅ Обработка дубликатов (skip/update/create)
- ✅ Импорт мета-данных
- ✅ Обработка ошибок с логами

**Тестирование**:
```php
// Подготовка тестовых данных
$test_data = [
    ['post_title' => 'Test 1', 'post_content' => 'Content 1'],
    ['post_title' => 'Test 2', 'post_content' => 'Content 2']
];

// Импорт
$importer = Importer_Factory::create('posts');
$result = $importer->import($test_data, [
    'post_status' => 'draft',
    'duplicate_mode' => 'skip'
]);

// Проверка
$posts = get_posts(['post_type' => 'post', 'posts_per_page' => -1]);
// Должно быть 2 новых поста
```

---

## 📋 PHASE 4: Import UI System - Система импорта через UI

### Цель фазы
Создать многошаговый визард импорта с расширенными возможностями маппинга полей, обработки дубликатов и поддержкой всех типов контента WordPress.

**Приоритет**: Высокий  
**Время**: ~80 часов  
**Зависимости**: Phase 0 (Database), Phase 1 (Helpers), Phase 2 (Format Parsers), Phase 3 (Validation)

### Общая архитектура

7-шаговый визард:
1. **Upload** - Загрузка файла (CSV/JSON/XLS/XLSX)
2. **Content Type** - Выбор типа контента (Posts/Users/WC/Custom Table)
3. **Column Selection** - Drag & Drop выбор колонок
4. **Field Mapping** - Маппинг полей с расширенными настройками
5. **Import Options** - Настройки импорта и обработка дубликатов
6. **Progress** - Реал-тайм прогресс импорта
7. **Complete** - Результаты и сохранение шаблона

### Задачи

#### 4.1 Backend: Import Wizard Controller

**Файлы**:
- `app/controller/import_wizard_controller.php` - Главный контроллер визарда
- `app/controller/import_step_controller.php` - Контроллер для каждого шага

**Задачи**:
- [ ] Создать `Import_Wizard_Controller` с методами:
  ```php
  // Step 1: Upload & Format Detection
  - process_step_1($file)
      * validate_file($file)
      * detect_format($file)  // CSV/JSON/XLS/XLSX
      * save_file($file)
      * get_preview_data($file, 3)  // First 3 rows
      * count_rows($file)
      * save_session_data('step_1', $data)
  
  // Step 2: Content Type Selection
  - process_step_2($content_type, $sub_type = null)
      * get_allowed_content_types()  // Posts, Users, WC, Custom Table
      * validate_content_type($type)
      * get_available_fields($type, $sub_type)
      * save_session_data('step_2', $data)
  
  // Step 3: Column Selection (Drag & Drop)
  - process_step_3($selected_columns)
      * validate_selected_columns($columns)
      * get_columns_preview($columns)
      * save_session_data('step_3', $data)
  
  // Step 4: Field Mapping
  - process_step_4($field_mapping, $field_settings = [])
      * validate_field_mapping($mapping)
      * process_field_settings($settings)
      * preview_transformations($mapping, $settings)
      * save_session_data('step_4', $data)
  
  // Step 5: Import Options
  - process_step_5($options)
      * validate_options($options)
      * estimate_import_time($rows, $batch_size)
      * save_session_data('step_5', $data)
  
  // Step 6: Start Import
  - start_import()
      * create_import_job($all_session_data)
      * schedule_background_import($job_id)
      * clear_session_data()
  ```

- [ ] Session Management:
  ```php
  - save_session_data($step, $data)
  - get_session_data($step)
  - clear_session_data()
  - validate_session($required_steps)
  ```

**Оценка времени**: 12 часов

#### 4.2 Backend: Content Type Importers

**Файлы**:
- `app/importer/post_importer.php` - Импорт постов/страниц
- `app/importer/user_importer.php` - Импорт пользователей
- `app/importer/product_importer.php` - Импорт WooCommerce продуктов
- `app/importer/taxonomy_importer.php` - Импорт таксономий
- `app/importer/comment_importer.php` - Импорт комментариев
- `app/importer/menu_importer.php` - Импорт меню
- `app/importer/custom_table_importer.php` - Импорт в кастомную MySQL таблицу

**Post_Importer**:
- [ ] Реализовать `import_item($data, $mapping, $settings, $options)`:
  ```php
  - map_post_data($data, $mapping)
      * Core fields: post_title, post_content, post_excerpt, post_date, post_status, post_author
      * Media: featured_image, gallery_images
      * Taxonomies: categories, tags, custom taxonomies
      * Custom fields: post meta
  - apply_field_settings($data, $settings)
      * Default values
      * Search/Replace rules
      * Custom functions
      * Data transformations
  - check_duplicate($data, $check_methods)
      * By Title
      * By ID
      * By Custom Field (SKU, external_id)
  - handle_duplicate_action($existing_id, $action)
      * skip - пропустить
      * update - обновить (с выбором стратегии)
      * delete_recreate - удалить и создать заново
      * create_duplicate - создать дубликат
  - import_featured_image($url, $options)
      * Auto-download from URL
      * Duplicate detection by hash
      * Alt text assignment
  - import_acf_fields($post_id, $acf_data)
      * Support ALL ACF field types
      * ACF Repeater support (3 methods):
        1. Multiple columns (field_0, field_1)
        2. Delimiter (value1|value2|value3)
        3. JSON ([{...}, {...}])
  - import_categories($post_id, $categories)
      * Create if not exists
      * Support hierarchy
  - import_tags($post_id, $tags)
  - import_meta($post_id, $meta)
  ```

**User_Importer**:
- [ ] Импорт пользователей с полями:
  ```
  - user_login, user_email, user_pass
  - first_name, last_name, display_name
  - role, user_meta
  ```
- [ ] Проверка дубликатов по email/login
- [ ] Генерация паролей (если не указан)
- [ ] Email уведомления (опционально)

**Product_Importer**:
- [ ] Импорт WooCommerce товаров:
  ```
  - Basic: SKU, title, description, price, sale_price
  - Stock: _stock, _stock_status, _manage_stock
  - Dimensions: _weight, _length, _width, _height
  - Taxonomies: product_cat, product_tag
  - Attributes: product_attributes
  - Images: featured_image, gallery_images
  ```
- [ ] Проверка дубликатов по SKU
- [ ] Support для Simple/Variable products

**Custom_Table_Importer**:
- [ ] Прямой импорт в кастомную MySQL таблицу:
  ```php
  - validate_table_exists($table_name)
  - get_table_columns($table_name)
  - map_to_columns($data, $mapping)
  - insert_row($table, $data)
  - update_row($table, $data, $where)
  ```

**Оценка времени**: 20 часов

#### 4.3 Backend: Field Mapping System

**Файл**: `app/import/field_mapper.php`

**Задачи**:
- [ ] Создать `Field_Mapper` класс:
  ```php
  - get_available_fields($content_type, $sub_type = null)
      * WordPress Core fields
      * ACF fields (if installed)
      * WooCommerce fields (if installed)
      * Custom post type fields
      * Custom table columns
  - get_acf_fields($post_type)
      * Get all ACF field groups for post type
      * Include Repeater sub-fields
      * Include Flexible Content layouts
  - validate_mapping($mapping, $content_type)
  - apply_field_settings($value, $settings)
      * Default value
      * Search/Replace
      * Custom function execution
      * Data transformation
  - preview_transformation($sample_data, $settings)
  ```

**Оценка времени**: 8 часов

#### 4.4 Backend: Duplicate Handler

**Файл**: `app/import/duplicate_handler.php`

**Задачи**:
- [ ] Создать `Duplicate_Handler` класс:
  ```php
  - check_duplicate($data, $check_methods, $content_type)
      * Methods: 'post_title', 'post_id', 'custom_field'
      * find_by_title($title, $post_type)
      * find_by_id($id, $post_type)
      * find_by_meta($meta_key, $meta_value, $post_type)
  - handle_action($existing_id, $new_data, $action, $strategy)
      * skip - return existing ID
      * update - update with strategy:
        - replace_all: обновить все поля
        - update_mapped: обновить только маппированные поля
        - dont_update_if_value: не обновлять если значение есть
      * delete_recreate - wp_delete_post + create new
      * create_duplicate - create new post
  ```

**Оценка времени**: 6 часов

#### 4.5 Backend: Image Downloader

**Файл**: `app/import/image_downloader.php`

**Задачи**:
- [ ] Создать `Image_Downloader` класс:
  ```php
  - download($url, $args = [])
      * validate_url($url)
      * download_url($url, $timeout)
      * create_attachment($file, $post_id)
      * set_alt_text($attachment_id, $alt)
      * set_title($attachment_id, $title)
      * set_description($attachment_id, $description)
  - find_by_url($url)
      * Check if already downloaded
      * Compare by URL or hash
  - find_by_hash($hash)
      * Duplicate detection by image hash
  - download_batch($urls)
      * Batch processing for multiple images
  ```

**Оценка времени**: 6 часов

#### 4.6 Backend: Progress Tracker

**Файл**: `app/import/progress_tracker.php`

**Задачи**:
- [ ] Создать `Progress_Tracker` класс:
  ```php
  - update($job_id, $processed, $success, $failed, $status)
      * Update aie_jobs table
      * Calculate progress percentage
  - get_progress($job_id)
      * Current status
      * Items: total/processed/success/failed
      * Estimated time remaining
  - complete($job_id)
      * Mark as completed
      * Trigger completion hooks
      * Send email notification (if enabled)
  - fail($job_id, $error_message)
      * Mark as failed
      * Log error
      * Trigger failure hooks
  - pause($job_id)
  - resume($job_id)
  - cancel($job_id)
  ```

**Оценка времени**: 4 часа

#### 4.7 Frontend: UI Views

**Файлы**:
- `app/view/import/import_wizard.php` - Главная страница визарда
- `app/view/import/step_1_upload.php` - Шаг 1: Загрузка
- `app/view/import/step_2_content_type.php` - Шаг 2: Тип контента
- `app/view/import/step_3_columns.php` - Шаг 3: Выбор колонок
- `app/view/import/step_4_mapping.php` - Шаг 4: Маппинг полей
- `app/view/import/step_5_options.php` - Шаг 5: Настройки
- `app/view/import/step_6_progress.php` - Шаг 6: Прогресс
- `app/view/import/step_7_complete.php` - Шаг 7: Завершение
- `app/view/import/import_history.php` - История импортов
- `app/view/import/field_settings_modal.php` - Модалка настроек поля

**Задачи**:
- [ ] **Step 1**: File upload с drag-drop зоной
- [ ] **Step 2**: Выбор типа контента (радио кнопки + description)
- [ ] **Step 3**: Drag & Drop column selector с live preview
- [ ] **Step 4**: Field mapping с модалкой настроек для каждого поля
- [ ] **Step 5**: Import options с duplicate handling settings
- [ ] **Step 6**: Real-time progress bar с pause/cancel buttons
- [ ] **Step 7**: Success message + stats + save template button
- [ ] **History**: Таблица всех импортов с фильтрами
- [ ] **Modal**: Настройки поля (default, search/replace, function, transformations)

**Оценка времени**: 12 часов

#### 4.8 Frontend: JavaScript Modules

**Файлы**:
- `src/js/modules/import_wizard.js` - Главный класс визарда
- `src/js/modules/column_selector.js` - Drag & Drop для колонок
- `src/js/modules/field_mapper.js` - Маппинг полей
- `src/js/modules/field_settings_modal.js` - Модалка настроек поля
- `src/js/modules/duplicate_handler.js` - UI для настроек дубликатов
- `src/js/modules/image_downloader.js` - UI для настроек изображений
- `src/js/modules/progress_tracker.js` - Real-time прогресс

**Import_Wizard**:
- [ ] Реализовать класс `ImportWizard`:
  ```javascript
  - constructor() - инициализация
  - init() - настройка событий
  - nextStep() - переход на следующий шаг
  - prevStep() - возврат назад
  - validateStep(step) - валидация текущего шага
  - saveStepData(step) - сохранение данных шага
  - renderStep(step) - отрисовка шага
  - handleFileUpload(file) - загрузка файла (AJAX)
  - startImport() - запуск импорта
  - trackProgress(jobId) - отслеживание прогресса
  ```

**Column_Selector**:
- [ ] Реализовать Drag & Drop:
  ```javascript
  - initDragDrop() - настройка Sortable.js
  - onSelectionChange(callback) - событие изменения
  - getSelectedColumns() - получить выбранные
  - updatePreview() - обновить превью данных
  ```

**Field_Mapper**:
- [ ] Реализовать маппинг:
  ```javascript
  - renderMappingTable() - таблица маппинга
  - addMappingRow(column, field) - добавить строку
  - removeMappingRow(index) - удалить строку
  - openSettingsModal(column) - открыть модалку настроек
  - saveFieldSettings(column, settings) - сохранить настройки
  - getMappingData() - получить все данные маппинга
  ```

**Progress_Tracker**:
- [ ] Реализовать отслеживание:
  ```javascript
  - start(jobId) - начать отслеживание
  - poll() - проверка каждые 2 секунды
  - updateProgress(data) - обновить UI
  - pause() - поставить на паузу
  - resume() - возобновить
  - cancel() - отменить импорт
  ```

**Оценка времени**: 10 часов

#### 4.9 REST API Endpoints

**Файл**: `app/controller/rest_import_controller.php`

**Задачи**:
- [ ] Создать REST API endpoints:
  ```php
  // File operations
  POST   /aie/v1/import/upload           - upload file
  GET    /aie/v1/import/columns          - get columns from file
  POST   /aie/v1/import/preview          - preview data with mapping
  
  // Wizard steps
  POST   /aie/v1/import/step/1           - process step 1
  POST   /aie/v1/import/step/2           - process step 2
  POST   /aie/v1/import/step/3           - process step 3
  POST   /aie/v1/import/step/4           - process step 4
  POST   /aie/v1/import/step/5           - process step 5
  POST   /aie/v1/import/start            - start import
  
  // Progress tracking
  GET    /aie/v1/import/progress/{job_id} - get progress
  POST   /aie/v1/import/pause/{job_id}    - pause import
  POST   /aie/v1/import/resume/{job_id}   - resume import
  POST   /aie/v1/import/cancel/{job_id}   - cancel import
  
  // Templates
  GET    /aie/v1/import/templates         - get saved templates
  POST   /aie/v1/import/template/save     - save template
  DELETE /aie/v1/import/template/{id}     - delete template
  
  // History
  GET    /aie/v1/import/history           - get import history
  GET    /aie/v1/import/logs/{job_id}     - get logs for job
  ```

**Оценка времени**: 6 часов

#### 4.10 Background Processing Integration

**Задачи**:
- [ ] Интегрировать с Queue Manager (из Phase 2):
  ```php
  - schedule_background_import($job_id)
      * Push job to queue
      * Set batch size: 50 items
      * Dispatch queue processing
  - process_import_batch($job_id, $offset, $limit)
      * Get file data
      * Parse batch (50 items)
      * Import each item
      * Update progress
      * Schedule next batch
  - handle_import_failure($job_id, $error)
      * Mark job as failed
      * Log error
      * Send notification
  - handle_import_completion($job_id)
      * Mark as completed
      * Send email (if enabled)
      * Trigger completion hook
  ```

**Оценка времени**: 4 часа

#### 4.11 Admin Menu & Navigation

**Задачи**:
- [ ] Создать структуру меню:
  ```
  WP Advanced Import/Export
  ├─ Dashboard
  ├─ Import
  │  ├─ New Import (визард)
  │  ├─ Import History
  │  └─ Import Templates
  ├─ Export
  ├─ Settings
  └─ Functions
  ```

- [ ] Добавить в `app/controller/admin_menu_controller.php`:
  ```php
  - add_menu_page('AIE Import', 'aie-import')
  - add_submenu_page('New Import', 'aie-import-new')
  - add_submenu_page('History', 'aie-import-history')
  - add_submenu_page('Templates', 'aie-import-templates')
  ```

**Оценка времени**: 2 часа

### Критерии завершения Phase 4

- ✅ **7-шаговый визард** работает от начала до конца
- ✅ **Drag & Drop** выбор колонок функционирует
- ✅ **Field Mapping** для всех типов полей:
  - WordPress Core (post_title, post_content, post_date, etc.)
  - ACF Fields (включая Repeater - 3 метода)
  - WooCommerce Fields (SKU, prices, stock, categories)
  - Custom MySQL Table columns
- ✅ **Per-field settings modal** работает:
  - Default value
  - Search/Replace rules
  - Custom function selection
  - Data transformations
  - Live preview
- ✅ **Duplicate Detection** работает по:
  - Title
  - ID
  - Custom Field (SKU, external_id)
- ✅ **Duplicate Actions** реализованы:
  - Skip
  - Update (3 стратегии)
  - Delete & Recreate
  - Create Duplicate
- ✅ **Image Auto-Download** работает:
  - URL → Media Library
  - Duplicate detection by hash
  - Alt text assignment
- ✅ **Custom MySQL Table Import** работает:
  - Table selection
  - Column mapping
  - Direct INSERT/UPDATE
- ✅ **Background Processing**:
  - 50 items per batch
  - Real-time progress tracking
  - Pause/Resume/Cancel
- ✅ **Progress Page** показывает:
  - Current status
  - Items: Total/Processed/Success/Failed
  - Estimated time
  - Real-time updates (AJAX)
- ✅ **Import History** работает:
  - List all imports
  - Filters by status/date
  - View logs
  - Re-run import
- ✅ **Template System** работает:
  - Save mapping as template
  - Load template
  - Edit/Delete templates

### Тестирование Phase 4

```php
// === Test 1: File Upload & Format Detection ===
$file = [
    'name' => 'test_products.csv',
    'tmp_name' => '/tmp/test.csv',
    'type' => 'text/csv',
    'size' => 1024
];
$result = $wizard->process_step_1($file);
// Expected: success, format=csv, preview=[3 rows], total_rows=100

// === Test 2: Content Type Selection ===
$result = $wizard->process_step_2('product');
// Expected: available_fields includes _sku, _price, _stock, product_cat

// === Test 3: Column Selection ===
$selected = ['Product Name', 'SKU', 'Price', 'Stock'];
$result = $wizard->process_step_3($selected);
// Expected: preview shows only selected columns

// === Test 4: Field Mapping ===
$mapping = [
    'Product Name' => 'post_title',
    'SKU' => '_sku',
    'Price' => '_regular_price',
    'Stock' => '_stock'
];
$settings = [
    '_sku' => [
        'default_value' => 'PRODUCT-XXX',
        'search_replace' => [
            ['search' => 'OLD-', 'replace' => 'NEW-']
        ]
    ]
];
$result = $wizard->process_step_4($mapping, $settings);
// Expected: preview shows transformed data

// === Test 5: Import Options ===
$options = [
    'duplicate_check' => ['_sku'],
    'duplicate_action' => 'update',
    'update_strategy' => 'update_mapped'
];
$result = $wizard->process_step_5($options);
// Expected: summary with estimated time

// === Test 6: Start Import ===
$result = $wizard->start_import();
// Expected: job_id returned, background processing started

// === Test 7: Track Progress ===
$tracker = new Progress_Tracker($result['job_id']);
$progress = $tracker->get_progress();
// Expected: status=processing, processed=25/100, progress_percent=25

// === Test 8: ACF Repeater Import (Multiple Columns) ===
$csv_data = [
    'Title' => 'Test Post',
    'gallery_0' => 'image1.jpg',
    'gallery_1' => 'image2.jpg',
    'gallery_2' => 'image3.jpg'
];
$mapping = [
    'Title' => 'post_title',
    'gallery_0' => 'gallery_0',
    'gallery_1' => 'gallery_1',
    'gallery_2' => 'gallery_2'
];
$importer = new Post_Importer();
$result = $importer->import_item($csv_data, $mapping, [], []);
// Expected: post created with 3 ACF Repeater rows

// === Test 9: ACF Repeater Import (Delimiter) ===
$csv_data = [
    'Title' => 'Test Post',
    'gallery' => 'image1.jpg|image2.jpg|image3.jpg'
];
$mapping = ['Title' => 'post_title', 'gallery' => 'gallery'];
$settings = ['gallery' => ['delimiter' => '|']];
$result = $importer->import_item($csv_data, $mapping, $settings, []);
// Expected: post created with 3 ACF Repeater rows

// === Test 10: ACF Repeater Import (JSON) ===
$csv_data = [
    'Title' => 'Test Post',
    'gallery' => '[{"image":"image1.jpg"},{"image":"image2.jpg"}]'
];
$mapping = ['Title' => 'post_title', 'gallery' => 'gallery'];
$result = $importer->import_item($csv_data, $mapping, [], []);
// Expected: post created with 2 ACF Repeater rows

// === Test 11: Custom MySQL Table Import ===
$csv_data = [
    'id' => '1',
    'name' => 'Test Item',
    'value' => '100',
    'created' => '2024-01-01'
];
$mapping = [
    'id' => 'id',
    'name' => 'item_name',
    'value' => 'item_value',
    'created' => 'created_at'
];
$importer = new Custom_Table_Importer('wp_custom_items');
$result = $importer->import_item($csv_data, $mapping, [], []);
// Expected: row inserted into wp_custom_items table

// === Test 12: Image Auto-Download ===
$downloader = new Image_Downloader();
$attachment_id = $downloader->download('https://example.com/image.jpg', [
    'post_id' => 123,
    'alt_text' => 'Test Image'
]);
// Expected: attachment created, set as featured image

// === Test 13: Duplicate Detection by Title ===
$existing = get_page_by_title('Existing Post', OBJECT, 'post');
$data = ['post_title' => 'Existing Post', 'post_content' => 'New content'];
$handler = new Duplicate_Handler();
$existing_id = $handler->check_duplicate($data, ['post_title'], 'post');
// Expected: existing post ID returned

// === Test 14: Duplicate Action - Skip ===
$options = ['duplicate_action' => 'skip'];
$result = $importer->import_item($data, $mapping, [], $options);
// Expected: status=skipped, existing_id returned

// === Test 15: Duplicate Action - Update (Update Mapped) ===
$options = [
    'duplicate_action' => 'update',
    'update_strategy' => 'update_mapped'
];
$result = $importer->import_item($data, $mapping, [], $options);
// Expected: status=success, action=updated, only mapped fields updated

// === Test 16: Pause/Resume Import ===
$tracker = new Progress_Tracker($job_id);
$tracker->pause();
// Expected: job status=paused

$tracker->resume();
// Expected: job status=processing, continue from last batch

// === Test 17: Template Save/Load ===
$template_data = [
    'name' => 'WooCommerce Product Import',
    'content_type' => 'product',
    'field_mapping' => $mapping,
    'field_settings' => $settings,
    'options' => $options
];
$template_id = save_import_template($template_data);
// Expected: template saved to aie_field_maps

$loaded = load_import_template($template_id);
// Expected: all template data loaded correctly

// === Test 18: Import History ===
$history = get_import_history(['status' => 'completed']);
// Expected: list of completed imports with stats

// === Test 19: Field Settings - Search/Replace ===
$value = 'OLD-SKU-123';
$settings = [
    'search_replace' => [
        ['search' => 'OLD-', 'replace' => 'NEW-']
    ]
];
$mapper = new Field_Mapper();
$result = $mapper->apply_field_settings($value, $settings);
// Expected: 'NEW-SKU-123'

// === Test 20: Field Settings - Custom Function ===
$value = 'test title';
$settings = ['function_id' => 1]; // uppercase function
$result = $mapper->apply_field_settings($value, $settings);
// Expected: 'TEST TITLE'
```

### Документация Phase 4

См. **IMPORT_UI_SPECIFICATION.md** для полной спецификации UI/UX.

---

## 📋 PHASE 5: Export System - 5-шаговый визард экспорта

### Цель фазы
Реализовать полнофункциональную систему экспорта с 5-шаговым визардом, расширенными фильтрами, трансформацией полей и поддержкой всех типов контента WordPress.

**Основано на**: `EXPORT_UI_SPECIFICATION.md` и `Section 11: Export System` в `ARCHITECTURE.md`

**Estimate**: ~65-70 часов

### Приоритеты реализации

#### 5.1 Core Export Functionality (~20 часов)

**5.1.1 Export_Wizard_Controller** (~8 часов)
- [ ] Создать `app/controller/export_wizard_controller.php`
- [ ] Реализовать 5 методов для шагов визарда:
  - `process_step_1()` - Content Type Selection
  - `process_step_2()` - Filters & Query Builder
  - `process_step_3()` - Field Selection & Mapping
  - `process_step_4()` - Export Options & Format
  - `start_export()` - Запуск экспорта
- [ ] Session management для хранения данных между шагами
- [ ] Валидация на каждом шаге
- [ ] Методы оценки:
  - `estimate_items_count()` - подсчет элементов
  - `estimate_export()` - оценка размера файла и времени
- [ ] Preview функции:
  - `get_preview_items()` - первые 5 элементов
  - `preview_field_transformations()` - preview трансформаций
- [ ] Создание job записи в `aie_jobs`
- [ ] Очистка сессии после завершения

**Тестирование**:
```php
// Step 1: Content Type
$result = $controller->process_step_1('post', null);
assert($result['success'] === true);
assert(!empty($result['available_fields']));

// Step 2: Filters
$result = $controller->process_step_2([
    'post_status' => ['publish'],
    'date_from' => '2024-01-01'
]);
assert($result['filtered_count'] > 0);

// Step 5: Start Export
$result = $controller->start_export();
assert(!empty($result['job_id']));
```

**5.1.2 Base_Exporter Class** (~4 часа)
- [ ] Создать `app/exporter/base_exporter.php`
- [ ] Абстрактные методы:
  - `count($filters)` - подсчет элементов
  - `export($filters, $fields, $field_settings, $format_options)` - generator
- [ ] Общие методы:
  - `apply_field_settings()` - трансформация полей
  - `get_acf_fields()` - получение ACF полей
  - `validate_meta_query()` - валидация meta queries
- [ ] Интеграция с Custom_Function_Manager
- [ ] Search & Replace engine:
  - Plain text замена (case-sensitive/insensitive)
  - Regex замена с валидацией
  - Unlimited правил на поле

**5.1.3 Post_Exporter** (~4 часа)
- [ ] Создать `app/exporter/post_exporter.php`
- [ ] Extends Base_Exporter
- [ ] Query builder:
  - Post status filter (publish, draft, pending, private, etc.)
  - Date range (WP_Query date_query)
  - Author filter (author__in)
  - Categories (cat)
  - Tags (tag__in)
  - Custom taxonomies
  - Meta queries (meta_query)
  - Specific post IDs (post__in)
- [ ] Field mapper:
  - Core WP fields (ID, post_title, post_content, post_excerpt, post_date, post_modified, post_status, post_author, post_parent, post_slug, post_password, menu_order, comment_status, ping_status)
  - Featured image (ID и URL)
  - Gallery images
  - Categories (comma-separated names)
  - Tags (comma-separated names)
  - ACF fields (префикс `acf_`)
  - Yoast SEO fields (6 мета полей)
  - Post meta (любые custom fields)
- [ ] Batch processing с generator
- [ ] apply_filters для расширяемости

**5.1.4 User_Exporter** (~2 часа)
- [ ] Создать `app/exporter/user_exporter.php`
- [ ] Query builder:
  - User roles (role__in)
  - Registration date range
  - Meta queries
- [ ] Field mapper:
  - Core fields (ID, user_login, user_email, user_nicename, user_url, user_registered, display_name, first_name, last_name, description)
  - Role/Roles
  - User meta
- [ ] GDPR compliance (опциональное скрытие email)
- [ ] Batch processing

**5.1.5 Comment_Exporter** (~2 часа)
- [ ] Создать `app/exporter/comment_exporter.php`
- [ ] Query builder:
  - Comment status (approve, hold, spam, trash)
  - Post ID filter
  - Date range
  - User ID filter
- [ ] Field mapper:
  - Core fields (comment_ID, comment_post_ID, comment_author, comment_author_email, comment_author_url, comment_author_IP, comment_date, comment_content, comment_approved, comment_parent, user_id)
  - Comment meta
- [ ] Batch processing

**Критерии завершения 5.1**:
- ✅ Export_Wizard_Controller с 5 шагами работает
- ✅ Post/User/Comment exporters реализованы
- ✅ Session management и валидация работают
- ✅ Preview функции возвращают данные
- ✅ Filters применяются корректно
- ✅ Search & Replace engine работает

---

#### 5.2 Format Writers (~10 часов)

**5.2.1 CSV_Writer** (~4 часа)
- [ ] Создать `app/format/csv_writer.php`
- [ ] Опции:
  - `delimiter` (default: `,`)
  - `enclosure` (default: `"`)
  - `include_bom` (default: `true`) - UTF-8 BOM для Excel
  - `include_headers` (default: `true`)
- [ ] Streaming write:
  - `write_row($data)` - запись строки через fputcsv()
  - Auto-flush каждые 100 строк
- [ ] UTF-8 BOM для совместимости с Excel
- [ ] Экранирование специальных символов
- [ ] Большие файлы (>100MB) без memory overflow

**Тестирование**:
```php
$writer = new CSV_Writer('/path/to/file.csv', [
    'delimiter' => ',',
    'include_bom' => true
]);

foreach ($data as $row) {
    $writer->write_row($row);
}

$writer->close();
```

**5.2.2 JSON_Writer** (~3 часа)
- [ ] Создать `app/format/json_writer.php`
- [ ] Опции:
  - `pretty_print` (default: `true`) - JSON_PRETTY_PRINT
  - `include_metadata` (default: `false`) - обертка с metadata
- [ ] Структура с metadata:
  ```json
  {
    "metadata": {
      "exported_at": "2024-01-15 10:30:00",
      "wordpress_version": "6.4",
      "plugin_version": "1.0.0"
    },
    "data": [...],
    "count": 150
  }
  ```
- [ ] Streaming write:
  - `write_item($data)` - запись одного элемента
  - Запятые между элементами
- [ ] JSON_UNESCAPED_UNICODE для не-ASCII символов
- [ ] Большие файлы без json_encode() всего массива

**5.2.3 Excel_Writer (XLS/XLSX)** (~3 часа)
- [ ] Создать `app/format/excel_writer.php`
- [ ] Dependency: PhpSpreadsheet library
- [ ] Форматы:
  - XLSX (Office 2007+) - default
  - XLS (Office 97-2003) - legacy
- [ ] Features:
  - Auto-width columns
  - Header row bold
  - Freeze first row
  - Auto-filter на headers
- [ ] `write_row($data)` для добавления строк
- [ ] `save()` для записи файла
- [ ] Memory efficient (не хранить весь spreadsheet в памяти)

**Критерии завершения 5.2**:
- ✅ CSV с BOM и всеми опциями
- ✅ JSON с metadata и pretty print
- ✅ Excel (XLS и XLSX) с форматированием
- ✅ Streaming write для больших файлов
- ✅ Тесты на файлы >10000 строк

---

#### 5.3 Advanced Filtering (~12 часов)

**5.3.1 Query Builder для Posts** (~5 часов)
- [ ] Реализовать `build_query_args()` в Post_Exporter
- [ ] Фильтры:
  - Post Status (multiple): publish, draft, pending, private, future, trash
  - Date Range: date_from + date_to (date_query)
  - Author (multiple): author__in
  - Categories (multiple): cat (IDs)
  - Tags (multiple): tag__in
  - Custom Taxonomies: tax_query builder
  - Parent Post: post_parent
  - Post IDs: post__in (explicit list)
- [ ] Meta Query Builder:
  - Key, Value, Compare (=, !=, >, <, >=, <=, LIKE, NOT LIKE, IN, NOT IN, BETWEEN, EXISTS, NOT EXISTS)
  - Type (NUMERIC, BINARY, CHAR, DATE, DATETIME, DECIMAL, SIGNED, UNSIGNED, TIME)
  - Relation (AND, OR)
  - Nested queries
- [ ] UI для meta query:
  - Add/Remove rules
  - Grouped rules с AND/OR
  - Type selector
  - Compare operator selector
- [ ] Estimate count после фильтров
- [ ] Preview первых 5 элементов

**Тестирование**:
```php
$filters = [
    'post_status' => ['publish', 'draft'],
    'date_from' => '2024-01-01',
    'category' => [1, 2, 3],
    'meta_query' => [
        'relation' => 'AND',
        [
            'key' => 'price',
            'value' => [100, 500],
            'compare' => 'BETWEEN',
            'type' => 'NUMERIC'
        ],
        [
            'key' => 'featured',
            'value' => '1',
            'compare' => '='
        ]
    ]
];

$count = $post_exporter->count($filters);
assert($count > 0);
```

**5.3.2 Query Builder для Products** (~4 часа)
- [ ] Реализовать в Product_Exporter
- [ ] Фильтры:
  - Product Type: simple, variable, grouped, external
  - Stock Status: instock, outofstock, onbackorder
  - Price Range: price_min + price_max
  - Product Categories (multiple): product_cat tax_query
  - Product Tags (multiple): product_tag tax_query
  - Attributes: pa_* taxonomies
  - Featured: _featured meta
  - SKU search: _sku LIKE
  - Sales: on_sale (compare _sale_price EXISTS)
- [ ] Meta queries для WC meta
- [ ] Tax queries для WC taxonomies
- [ ] Combine meta_query AND tax_query

**5.3.3 Query Builder для Users** (~2 часа)
- [ ] Реализовать в User_Exporter
- [ ] Фильтры:
  - User Roles (multiple): role__in
  - Registration Date Range: date_query
  - User IDs: include (explicit list)
  - Meta queries
- [ ] Exclude administrators опция

**5.3.4 Query Builder для Comments** (~1 час)
- [ ] Реализовать в Comment_Exporter
- [ ] Фильтры:
  - Comment Status: approve, hold, spam, trash
  - Post ID (specific post)
  - Date Range
  - User ID
  - Parent Comment ID

**Критерии завершения 5.3**:
- ✅ Все фильтры работают для каждого типа
- ✅ Meta queries поддерживают все операторы
- ✅ Tax queries для WooCommerce
- ✅ Estimate count точный
- ✅ Preview показывает правильные элементы

---

#### 5.4 Field Transformation (~8 часов)

**5.4.1 Field Settings Modal** (~4 часа)
- [ ] UI Component: Field_Settings_Modal
- [ ] Настройки для каждого поля:
  - **Column Name**: Custom header name
  - **Default Value**: Значение если поле пустое
  - **Search & Replace**: Unlimited правил
    - Search text (plain или regex)
    - Replace text
    - Case sensitive toggle
    - Regex toggle
  - **Custom Function**: Выбор из сохраненных функций
  - **Live Preview**: Показать первые 3 transformed значения
- [ ] Drag & Drop reorder полей (Sortable.js)
- [ ] Save/Load настроек в template
- [ ] Apply to All для bulk операций

**Тестирование UI**:
- Открыть modal для поля `post_title`
- Добавить 3 search/replace правила
- Выбрать функцию "uppercase"
- Проверить live preview
- Сохранить и экспортировать

**5.4.2 Search & Replace Engine** (~2 часа)
- [ ] Реализовать в `apply_field_settings()`
- [ ] Поддержка:
  - Plain text: `str_replace()` или `str_ireplace()`
  - Regex: `preg_replace()` с валидацией паттерна
  - Multiple rules: применять по порядку
- [ ] Валидация regex перед сохранением
- [ ] Error handling для invalid regex

**Тестирование**:
```php
$settings = [
    'search_replace' => [
        [
            'search' => 'http://',
            'replace' => 'https://',
            'case_sensitive' => true
        ],
        [
            'search' => '/\d{4}-\d{2}-\d{2}/',
            'replace' => 'DATE',
            'regex' => true
        ]
    ]
];

$value = 'Visit http://example.com on 2024-01-15';
$result = apply_field_settings($value, $settings);
assert($result === 'Visit https://example.com on DATE');
```

**5.4.3 Custom Functions Integration** (~2 часа)
- [ ] Интеграция с Custom_Function_Manager
- [ ] Dropdown selector в Field Settings Modal
- [ ] Список всех сохраненных функций:
  - User-defined functions
  - Built-in functions (uppercase, lowercase, strip_tags, etc.)
- [ ] Execute function на значении поля
- [ ] Error handling если функция не найдена
- [ ] Preview результата функции

**Критерии завершения 5.4**:
- ✅ Field Settings Modal работает
- ✅ Search & Replace с regex работает
- ✅ Custom Functions применяются
- ✅ Live Preview показывает результат
- ✅ Drag & Drop reorder полей

---

#### 5.5 Product Export (~8 часов)

**5.5.1 Product_Exporter** (~5 часов)
- [ ] Создать `app/exporter/product_exporter.php`
- [ ] WooCommerce fields:
  - **Core**: ID, post_title, post_content, post_excerpt, post_status
  - **Pricing**: _sku, _regular_price, _sale_price, _price
  - **Inventory**: _stock, _stock_status, _manage_stock, _backorders, _sold_individually
  - **Shipping**: _weight, _length, _width, _height
  - **Tax**: _tax_status, _tax_class
  - **Product Data**: _featured, _virtual, _downloadable, product_type
  - **Images**: featured_image_url, gallery_images (comma-separated URLs)
  - **Taxonomies**: product_cat, product_tag (comma-separated names)
  - **Attributes**: product_attributes (serialized или readable format)
- [ ] Query builder (использовать 5.3.2)
- [ ] Variations support:
  - Экспорт variations как отдельные строки
  - Связь с parent product
  - Variation attributes
- [ ] Batch processing

**5.5.2 Order_Exporter** (~2 часа)
- [ ] Создать `app/exporter/order_exporter.php`
- [ ] Order fields:
  - Order ID, Order Number
  - Order Status, Order Date
  - Customer (User ID, Name, Email)
  - Billing Address (все поля)
  - Shipping Address (все поля)
  - Payment Method
  - Order Total, Order Tax, Shipping Total
  - Line Items (products + quantities + prices)
  - Order Notes
- [ ] Фильтры:
  - Order Status (processing, completed, refunded, etc.)
  - Date Range
  - Customer ID
  - Payment Method
  - Price Range

**5.5.3 Coupon_Exporter** (~1 час)
- [ ] Создать `app/exporter/coupon_exporter.php`
- [ ] Coupon fields:
  - Code, Description
  - Discount Type, Amount
  - Usage Limit, Usage Count
  - Expiry Date
  - Product IDs, Category IDs
  - Email Restrictions

**Критерии завершения 5.5**:
- ✅ Product export с всеми полями WC
- ✅ Variations экспортируются корректно
- ✅ Order export с line items
- ✅ Coupon export работает
- ✅ Тесты на реальных WC данных

---

#### 5.6 Background Processing (~6 часов)

**5.6.1 Export_Progress_Tracker** (~3 часа)
- [ ] Создать `app/export/export_progress_tracker.php`
- [ ] Методы:
  - `update($processed, $success, $failed, $status)` - обновить прогресс
  - `complete($file_path)` - завершить успешно
  - `fail($error_message)` - завершить с ошибкой
  - `get_progress()` - получить текущий прогресс
- [ ] Обновление `aie_jobs` таблицы:
  - `processed_items`
  - `success_items`
  - `failed_items`
  - `status` (pending, processing, completed, failed, paused, cancelled)
  - `file_path` (после завершения)
  - `file_size` (после завершения)
  - `updated_at`
- [ ] Action hooks:
  - `do_action('aie_export_complete', $job_id, $file_path)`
  - `do_action('aie_export_failed', $job_id, $error_message)`
- [ ] Log errors в `aie_logs`

**5.6.2 Queue Integration** (~2 часа)
- [ ] `schedule_background_export()` в Export_Wizard_Controller
- [ ] Интеграция с Queue_Manager
- [ ] Task structure:
  ```php
  [
      'type' => 'export',
      'job_id' => 123,
  ]
  ```
- [ ] Process task:
  - Load job settings
  - Create appropriate exporter
  - Create format writer
  - Loop through items (batch_size = 50)
  - Update progress каждую итерацию
  - Save file
  - Complete job
- [ ] Error handling и retry logic
- [ ] Pause/Cancel support

**5.6.3 Direct Export (Small Jobs)** (~1 час)
- [ ] `process_export_direct()` для <1000 элементов
- [ ] Синхронное выполнение
- [ ] Immediate download после завершения
- [ ] No job creation (опционально)
- [ ] Time limit check (max 30 seconds)

**Критерии завершения 5.6**:
- ✅ Background processing через Queue_Manager
- ✅ Progress tracking в реальном времени
- ✅ Direct export для малых объемов
- ✅ Pause/Cancel работают
- ✅ Error handling и logging

---

#### 5.7 Templates & History (~6 часов)

**5.7.1 Export Templates** (~3 часа)
- [ ] Создать `app/model/export_template.php`
- [ ] Таблица `aie_export_templates`:
  ```sql
  - id
  - user_id
  - name
  - content_type
  - filters (JSON)
  - selected_fields (JSON)
  - field_settings (JSON)
  - export_options (JSON)
  - created_at
  - updated_at
  ```
- [ ] CRUD операции:
  - `save_template()` - сохранить конфигурацию
  - `load_template($id)` - загрузить конфигурацию
  - `update_template($id)` - обновить
  - `delete_template($id)` - удалить
  - `list_templates()` - список всех шаблонов пользователя
- [ ] UI: Export Templates страница
  - List всех templates
  - Load template (populate wizard)
  - Edit template
  - Delete template
  - Duplicate template

**Тестирование**:
```php
$template_id = save_template([
    'name' => 'Published Posts Export',
    'content_type' => 'post',
    'filters' => ['post_status' => ['publish']],
    'selected_fields' => ['ID', 'post_title', 'post_date'],
    'field_settings' => [],
    'export_options' => ['format' => 'csv']
]);

$template = load_template($template_id);
assert($template['name'] === 'Published Posts Export');
```

**5.7.2 Export History** (~3 часа)
- [ ] UI: Export History страница
- [ ] Query `aie_jobs` WHERE `type='export'`
- [ ] Display:
  - Export ID
  - Content Type
  - Format
  - Total Items
  - Status (completed, failed, processing)
  - File Size (if completed)
  - Created Date
  - Actions: Download, Preview, Rerun, Delete
- [ ] Download button:
  - Serve file via PHP (не прямой URL)
  - Security check (user owns job)
  - Content-Disposition header
  - Chunked read для больших файлов
- [ ] Preview:
  - First 10 rows
  - Modal или отдельная страница
- [ ] Rerun:
  - Load settings from job
  - Prepopulate wizard
  - Start new export
- [ ] Delete:
  - Delete file from uploads/
  - Delete job record
- [ ] View Logs:
  - Show errors/warnings из `aie_logs`
- [ ] Auto-cleanup:
  - Cron job: delete files >7 days old
  - User setting: retention period

**Критерии завершения 5.7**:
- ✅ Templates save/load работают
- ✅ Export History показывает все exports
- ✅ Download работает безопасно
- ✅ Preview показывает данные
- ✅ Rerun duplicates export
- ✅ Auto-cleanup удаляет старые файлы

---

#### 5.8 Advanced Exporters (~6 часов)

**5.8.1 Taxonomy_Exporter** (~2 часа)
- [ ] Создать `app/exporter/taxonomy_exporter.php`
- [ ] Export terms:
  - term_id, name, slug, description
  - parent, count
  - term_meta
- [ ] Фильтры:
  - Taxonomy (category, post_tag, custom)
  - Parent (only top-level, only children)
  - Hide empty
- [ ] Hierarchical structure в JSON

**5.8.2 Menu_Exporter** (~2 часа)
- [ ] Создать `app/exporter/menu_exporter.php`
- [ ] Export menu items:
  - Menu ID, Menu Name
  - Menu Item ID, Title, URL, Target
  - Parent Item ID (hierarchy)
  - Object (post, page, custom)
  - CSS Classes
- [ ] Preserve menu structure

**5.8.3 Media_Exporter** (~2 часа)
- [ ] Создать `app/exporter/media_exporter.php`
- [ ] Export attachments:
  - Attachment ID, Title, Alt Text
  - File URL, File Name, File Type
  - File Size, Dimensions
  - Upload Date, Author
  - Attached to (post ID)
- [ ] Фильтры:
  - Media Type (image, video, audio, document)
  - Date Range
  - Author
  - Attached/Unattached

**Критерии завершения 5.8**:
- ✅ Taxonomy export с hierarchy
- ✅ Menu export с structure
- ✅ Media export с всеми meta
- ✅ Фильтры работают

---

#### 5.9 ACF & Yoast Integration (~4 часа)

**5.9.1 ACF Fields Export** (~2 часа)
- [ ] Detect ACF plugin
- [ ] `get_acf_fields()` в Base_Exporter
- [ ] Получить все field groups для post type
- [ ] Добавить ACF поля в available_fields
- [ ] Export ACF values:
  - Simple fields (text, textarea, number, etc.)
  - Complex fields:
    - Repeater: JSON array или pipe-separated
    - Flexible Content: JSON
    - Gallery: comma-separated URLs
    - Relationship/Post Object: post IDs или titles
    - Taxonomy: term names или IDs
- [ ] Format options для complex fields

**5.9.2 Yoast SEO Fields Export** (~2 часа)
- [ ] Detect Yoast plugin
- [ ] `get_yoast_field()` в Post_Exporter
- [ ] Export Yoast meta:
  - `_yoast_wpseo_title` (SEO Title)
  - `_yoast_wpseo_metadesc` (Meta Description)
  - `_yoast_wpseo_focuskw` (Focus Keyword)
  - `_yoast_wpseo_canonical` (Canonical URL)
  - `_yoast_wpseo_meta-robots-noindex` (Noindex)
  - `_yoast_wpseo_meta-robots-nofollow` (Nofollow)
- [ ] Добавить в available_fields если Yoast активен

**Критерии завершения 5.9**:
- ✅ ACF fields экспортируются для posts
- ✅ Complex ACF fields форматируются правильно
- ✅ Yoast SEO fields экспортируются
- ✅ Поля появляются в available_fields только если плагины активны

---

### Критерии завершения Phase 5

**Функциональность**:
- ✅ 5-шаговый wizard работает полностью
- ✅ Export всех типов контента (Posts, Pages, Users, Comments, Products, Orders, Coupons, Taxonomies, Menus, Media)
- ✅ Все форматы (CSV с BOM, JSON с metadata, XLS, XLSX)
- ✅ Advanced filtering с meta queries
- ✅ Field transformation (Search/Replace + Custom Functions)
- ✅ Background processing для больших объемов
- ✅ Templates save/load
- ✅ Export History с download/preview/rerun
- ✅ ACF и Yoast SEO integration

**Performance**:
- ✅ Export 10,000 posts за <2 минуты
- ✅ CSV файлы >100MB без memory overflow
- ✅ Streaming write для всех форматов

**UI/UX**:
- ✅ Intuitive wizard flow
- ✅ Real-time progress tracking
- ✅ Preview на каждом шаге
- ✅ Field Settings Modal с live preview
- ✅ Drag & Drop field reorder

**Testing**:
```php
// Full export flow test
$wizard = new Export_Wizard_Controller();

// Step 1
$wizard->process_step_1('post');

// Step 2
$wizard->process_step_2(['post_status' => ['publish']]);

// Step 3
$wizard->process_step_3(['ID', 'post_title', 'post_date']);

// Step 4
$wizard->process_step_4(['format' => 'csv', 'processing' => 'background']);

// Step 5
$result = $wizard->start_export();
assert(!empty($result['job_id']));

// Wait for completion
sleep(5);

$tracker = new Export_Progress_Tracker($result['job_id']);
$progress = $tracker->get_progress();
assert($progress['status'] === 'completed');
assert(file_exists($progress['file_path']));
```

---

## 📋 PHASE 6: Background Processing - Фоновая обработка

### Цель фазы
Реализовать систему обработки больших файлов в фоне без таймаутов.

### Задачи

#### 6.1 Job System
- [ ] Создать `app/model/queue/job.php`
- [ ] Реализовать класс `Job`
- [ ] Структура:
  ```php
  class Job {
      protected $id;
      protected $type;            // import | export
      protected $data_type;       // posts | users | etc
      protected $status;          // pending | processing | completed | failed
      protected $total_items;
      protected $processed_items;
      protected $data;            // Настройки задания
      
      public function save();
      public function update($data);
      public function complete();
      public function fail($error);
  }
  ```

#### 6.2 Queue_Manager - Менеджер очереди
- [ ] Создать `app/model/queue/queue_manager.php`
- [ ] Реализовать класс `Queue_Manager`
- [ ] Методы:
  ```php
  - create_job($type, $data_type, $data)
  - get_job($job_id)
  - get_next_job()
  - process_job($job_id)
  - retry_job($job_id)
  - cancel_job($job_id)
  ```

#### 6.3 Batch_Processor - Пакетная обработка
- [ ] Создать `app/model/queue/batch_processor.php`
- [ ] Реализовать класс `Batch_Processor`
- [ ] Обработка по батчам (100-500 записей)
- [ ] Мониторинг памяти и времени:
  ```php
  public function should_pause() {
      $memory_usage = memory_get_usage(true);
      $time_elapsed = microtime(true) - $this->start_time;
      
      return $memory_usage > $this->memory_limit || 
             $time_elapsed > $this->time_limit;
  }
  ```

#### 6.4 WordPress Cron Integration
- [ ] Регистрация cron события:
  ```php
  add_action('aie_process_queue', [$queue_manager, 'process_next_job']);
  ```
- [ ] Настройка интервала выполнения
- [ ] Альтернатива: WP Background Processing library

#### 6.5 AJAX Heartbeat
- [ ] Для UI: AJAX запросы для обработки
- [ ] Цепочка запросов до завершения
- [ ] Обновление прогресс-бара в реальном времени

**Критерии завершения**:
- ✅ Jobs сохраняются в БД
- ✅ Обработка происходит по батчам
- ✅ Нет timeout при больших файлах
- ✅ Возможность отмены задания

**Тестирование**:
```php
// Создание задания
$job_id = Queue_Manager::create_job('import', 'posts', [
    'file_path' => '/path/to/file.csv',
    'options' => [...]
]);

// Обработка
$processor = new Batch_Processor($job_id);
$processor->process();

// Проверка статуса
$job = Queue_Manager::get_job($job_id);
echo $job->status; // processing | completed
echo $job->processed_items . '/' . $job->total_items;
```

---

## 📋 PHASE 7: Admin UI - Интерфейс администратора

### Цель фазы
Создать удобный интерфейс для работы с импортом/экспортом.

### Задачи

#### 7.1 Menu и Navigation
- [ ] Создать `app/controller/admin.php`
- [ ] Реализовать класс `Admin_Controller`
- [ ] Зарегистрировать menu:
  ```php
  - Import Data
  - Export Data
  - Custom Functions (новое!)
  - History
  - Settings
  ```
- [ ] Проверка capabilities

#### 7.2 Import Page - Страница импорта
- [ ] Создать `app/view/admin/import_page.php`
- [ ] UI компоненты:
  ```
  1. Выбор типа данных (posts, users, comments)
  2. Загрузка файла (drag & drop)
  3. Выбор формата (CSV, JSON, XML)
  4. Настройки формата (delimiter, encoding для CSV)
  5. Предпросмотр данных (первые 10 строк)
  6. Field Mapping (source → target)
  7. Validation Rules
  8. Duplicate Handling (skip, update, create)
  9. Start Import Button
  10. Progress Bar
  11. Real-time Logs
  ```

#### 7.3 Export Page - Страница экспорта
- [ ] Создать `app/view/admin/export_page.php`
- [ ] UI компоненты:
  ```
  1. Выбор типа данных
  2. Фильтры (date range, status, author, etc)
  3. Выбор полей для экспорта (checkboxes)
  4. Выбор формата
  5. Export Button
  6. Progress Bar
  7. Download Button
  ```

#### 7.4 History Page - История заданий
- [ ] Создать `app/view/admin/history_page.php`
- [ ] Таблица заданий:
  ```
  - ID | Type | Data Type | Status | Items | Created | Actions
  - Фильтры по type, status, date
  - Пагинация
  - View Logs (modal)
  - Re-run (для экспортов)
  - Delete
  ```

#### 7.5 Settings Page - Настройки
- [ ] Создать `app/view/admin/settings_page.php`
- [ ] Опции:
  ```
  - Default batch size
  - Memory limit
  - Time limit
  - Auto-delete old jobs (days)
  - Enable debug logging
  - Email notifications
  - Cron vs AJAX processing
  ```

#### 7.6 JavaScript Modules
- [ ] `src/js/modules/import.js`:
  ```javascript
  - File upload handler
  - Field mapping UI
  - Progress tracking
  - Real-time logs display
  ```
- [ ] `src/js/modules/export.js`:
  ```javascript
  - Filter UI
  - Field selector
  - Progress tracking
  - Download handler
  ```
- [ ] `src/js/modules/progress.js`:
  ```javascript
  - ProgressBar component
  - Polling for status updates
  - Error handling
  ```

#### 7.7 Стилизация (SCSS)
- [ ] `src/scss/admin/import.scss`
- [ ] `src/scss/admin/export.scss`
- [ ] `src/scss/components/progress-bar.scss`
- [ ] `src/scss/components/field-mapper.scss`

**Критерии завершения**:
- ✅ Все страницы созданы и доступны
- ✅ UI интуитивный и user-friendly
- ✅ Drag & drop работает
- ✅ Field mapping удобен
- ✅ Progress bar обновляется в реальном времени
- ✅ Responsive design

**Тестирование**:
- [ ] Загрузить файл через drag & drop
- [ ] Настроить field mapping
- [ ] Запустить импорт и следить за прогрессом
- [ ] Проверить логи
- [ ] Экспортировать данные
- [ ] Открыть History и посмотреть задания

---

## 📋 PHASE 8: AJAX & REST API

### Цель фазы
Создать API для коммуникации frontend ↔ backend.

### Задачи

#### 8.1 Ajax_Controller
- [ ] Создать `app/controller/ajax.php`
- [ ] Реализовать класс `Ajax_Controller`
- [ ] AJAX actions:
  ```php
  // Импорт
  - aie_upload_file
  - aie_validate_file
  - aie_preview_data
  - aie_start_import
  - aie_get_import_progress
  - aie_get_import_logs
  
  // Экспорт
  - aie_start_export
  - aie_get_export_progress
  - aie_download_export
  
  // История
  - aie_get_jobs
  - aie_get_job_details
  - aie_delete_job
  - aie_retry_job
  
  // Маппинг
  - aie_save_mapping
  - aie_load_mapping
  - aie_suggest_mapping
  ```

#### 8.2 REST API Endpoints
- [ ] Регистрация REST routes в `Import_Controller` и `Export_Controller`
- [ ] Endpoints:
  ```
  POST   /wp-json/aie/v1/import/upload
  POST   /wp-json/aie/v1/import/validate
  POST   /wp-json/aie/v1/import/start
  GET    /wp-json/aie/v1/import/{job_id}/progress
  GET    /wp-json/aie/v1/import/{job_id}/logs
  
  POST   /wp-json/aie/v1/export/start
  GET    /wp-json/aie/v1/export/{job_id}/progress
  GET    /wp-json/aie/v1/export/{job_id}/download
  
  GET    /wp-json/aie/v1/jobs
  GET    /wp-json/aie/v1/jobs/{job_id}
  DELETE /wp-json/aie/v1/jobs/{job_id}
  ```

#### 8.3 Безопасность API
- [ ] Nonce verification для AJAX
- [ ] Authentication для REST API
- [ ] Capability checks
- [ ] Rate limiting

#### 8.4 Response formatting
- [ ] Единый формат ответов:
  ```json
  {
    "success": true,
    "data": {},
    "message": "Success message",
    "errors": []
  }
  ```

**Критерии завершения**:
- ✅ Все AJAX actions работают
- ✅ REST API endpoints доступны
- ✅ Безопасность настроена
- ✅ Единый формат ответов

**Тестирование**:
```javascript
// AJAX
jQuery.post(ajaxurl, {
    action: 'aie_start_import',
    job_id: 123,
    _wpnonce: aie_nonce
}, function(response) {
    console.log(response);
});

// REST API
fetch('/wp-json/aie/v1/import/1/progress')
    .then(r => r.json())
    .then(data => console.log(data));
```

---

## 📋 PHASE 9: Advanced Features - Дополнительные функции

### Цель фазы
Добавить расширенные возможности для профессионального использования.

### Задачи

#### 9.1 Custom Post Types Support
- [ ] Автоопределение CPT через `get_post_types()`
- [ ] Dynamic field mapping для CPT
- [ ] Поддержка специфичных мета-полей

#### 9.2 Taxonomies Import/Export
- [ ] Создать `app/model/import/taxonomy_importer.php`
- [ ] Реализовать класс `Taxonomy_Importer`
- [ ] Импорт terms с иерархией
- [ ] Привязка terms к постам
- [ ] Экспорт taxonomies

#### 9.3 Media Advanced Import
- [ ] Импорт из URL с валидацией
- [ ] Импорт из ZIP архива
- [ ] Автопривязка к постам по имени
- [ ] Обработка дубликатов media
- [ ] Генерация alt text

#### 9.4 Scheduled Imports
- [ ] UI для создания расписания
- [ ] WP Cron для автоматического импорта
- [ ] Импорт из удаленного URL (FTP, HTTP)
- [ ] Email уведомления о результатах

#### 9.5 Field Mapping Presets
- [ ] UI для сохранения пресетов
- [ ] Библиотека готовых пресетов:
  ```
  - WooCommerce Products
  - Easy Digital Downloads
  - WordPress SEO (Yoast)
  - ACF Fields
  ```

#### 9.6 Data Transformation
- [ ] Добавление callbacks для трансформации:
  ```php
  add_filter('aie_transform_field', function($value, $fieldName) {
      if ($fieldName === 'price') {
          return floatval(str_replace(',', '.', $value));
      }
      return $value;
  }, 10, 2);
  ```
- [ ] Built-in трансформации:
  ```
  - Date formatting
  - String case conversion
  - HTML stripping
  - URL validation
  ```

#### 9.7 Custom Functions System - Пользовательские функции
- [ ] Создать `app/helper/function_executor.php`
- [ ] Реализовать класс `Function_Executor`:
  ```php
  - execute($function_id, $value, $context)      // Выполнить функцию
  - execute_in_sandbox($code, $value, $context)  // Безопасное выполнение
  - validate_function_code($code)                // Проверка безопасности
  - get_function($id)                            // Получить из БД
  ```

- [ ] Создать `app/model/custom_functions_manager.php`
- [ ] Реализовать класс `Custom_Functions_Manager`:
  ```php
  - create($data)                    // Создать функцию
  - update($id, $data)               // Обновить
  - delete($id)                      // Удалить
  - get_all($filters)                // Список всех
  - test_function($code, $value)     // Тестирование
  - can_edit_function($id)           // Проверка прав
  - create_from_snippet($key, $data) // Создать из сниппета
  ```

- [ ] Создать `app/library/function_snippets.php`
- [ ] Реализовать класс `Function_Snippets`:
  ```php
  - get_categories()                  // Получить категории
  - get_all_snippets()                // Все сниппеты
  - get_by_category($category)        // Фильтр по категории
  - search($query)                    // Поиск
  - get_snippet($key)                 // Получить один
  ```

- [ ] Наполнить библиотеку сниппетами (50+ примеров):
  ```
  String Operations:
    - uppercase, lowercase, trim, slug, replace, etc.
  
  Date & Time:
    - format_date_mysql, convert_format, extract_year, etc.
  
  Numeric Operations:
    - clean_price, round_number, format_currency, etc.
  
  HTML Operations:
    - strip_html, decode_entities, sanitize, etc.
  
  WordPress Functions:
    - find_user, find_term, create_category, etc.
  
  Validation:
    - validate_email, validate_url, check_required, etc.
  
  Advanced:
    - concat_fields, conditional_value, map_values, etc.
  ```

- [ ] Создать `app/view/admin/functions_page.php`
- [ ] UI компоненты:
  ```
  1. Таблица функций (Name, Description, Source, Status, Usage)
  2. [+ New Function] [📚 Browse Library] кнопки
  3. Edit/Delete Actions
  4. Function Editor Modal (как раньше)
  5. Snippets Library Modal:
     - Категории с иконками
     - Поиск по сниппетам
     - Grid/List view сниппетов
     - Preview snippet (код + пример)
     - [Use As Is] [Customize] кнопки
  6. Source badge на каждой функции (Custom/Library/Imported)
  ```

- [ ] JavaScript для библиотеки:
  - `src/js/modules/function_library.js`:
    ```javascript
    - openLibrary()              // Открыть модал библиотеки
    - filterByCategory()         // Фильтр по категории
    - searchSnippets()           // Поиск
    - previewSnippet()           // Предпросмотр кода
    - useAsIs()                  // Импорт без изменений
    - customize()                // Открыть редактор с кодом
    - loadSnippets()             // AJAX загрузка
    ```

- [ ] Стилизация библиотеки:
  - `src/scss/admin/function_library.scss`:
    ```scss
    - .snippet-library-modal
    - .snippet-categories
    - .snippet-card
    - .snippet-preview
    - .source-badge
    ```

- [ ] Интеграция в Field Mapping:
  - Добавить dropdown "Apply Function" для каждого поля
  - Загрузка списка активных функций (custom + library)
  - Индикатор источника функции (значок 📚 для library)
  - Сохранение связи поле→функция в маппинге
  - Применение функции при импорте/экспорте

- [ ] Система безопасности:
  ```php
  - Whitelist разрешенных PHP функций
  - Blacklist опасных конструкций (eval, exec, file operations)
  - Timeout для выполнения (5 сек)
  - Try-catch с возвратом исходного значения при ошибке
  - Логирование всех ошибок выполнения
  ```

**Критерии завершения**:
- ✅ CRUD операций для функций работает
- ✅ Библиотека с 50+ сниппетами
- ✅ Поиск и фильтрация сниппетов
- ✅ Preview перед импортом
- ✅ "Use As Is" и "Customize" режимы
- ✅ Code editor с подсветкой синтаксиса
- ✅ Тестирование функций в UI
- ✅ Интеграция в field mapping
- ✅ Безопасное выполнение с валидацией
- ✅ Source tracking (custom/library/imported)
- ✅ Логирование ошибок

**Тестирование**:
```php
// Библиотека сниппетов
$library = new Function_Snippets();
$snippets = $library->get_all_snippets();
// Должно быть 50+ сниппетов

$string_snippets = $library->get_by_category('string');
// Только строковые операции

$search_results = $library->search('email');
// Все сниппеты связанные с email

// Создание функции из сниппета
$manager = new Custom_Functions_Manager();
$func_id = $manager->create_from_snippet('uppercase', [
    'name' => 'My Uppercase Function'
]);

// Проверка источника
$function = $manager->get($func_id);
// $function->source === 'library:uppercase'

// Тестирование
$result = $manager->test_function('return strtoupper($value);', 'hello');
// $result = ['success' => true, 'result' => 'HELLO']

// Выполнение в импорте
$executor = new Function_Executor();
$transformed = $executor->execute($func_id, 'test value', []);
// $transformed = 'TEST VALUE'

// Проверка безопасности
$unsafe_code = "exec('rm -rf /');";
$is_valid = $executor->validate_function_code($unsafe_code);
// $is_valid = false
```

**Критерии завершения**:
- ✅ CPT импорт/экспорт работает
- ✅ Taxonomies поддерживаются
- ✅ Scheduled imports настраиваются
- ✅ Пресеты маппинга работают

**Тестирование**:
- [ ] Создать CPT и импортировать данные
- [ ] Импортировать taxonomy с иерархией
- [ ] Настроить расписание импорта
- [ ] Сохранить и применить пресет маппинга

---

## 📋 PHASE 9.8: Media Folder Sync - Синхронизация папок с медиа библиотекой

### Цель фазы
Реализовать синхронизацию файлов из папок на сервере (загруженных через FTP) с медиа библиотекой WordPress.

### Задачи

#### 9.8.1 Media_Folder_Sync класс
- [ ] Создать `app/sync/media_folder_sync.php`
- [ ] Реализовать класс `Media_Folder_Sync`:
  ```php
  - scan_folder($path, $options)         // Сканировать папку
  - sync_files($files, $options)         // Синхронизировать файлы
  - check_duplicate($file, $method)      // Проверка дубликатов
  - import_file($file, $options)         // Импорт файла
  - get_allowed_file_types()             // Разрешенные типы
  - get_sync_stats($job_id)              // Статистика
  ```

#### 9.8.2 Методы проверки дубликатов
- [ ] Реализовать `find_by_hash($file_path)`:
  - MD5 hash файла + размер
  - Поиск в postmeta по `_wp_attached_file_hash`
  - Самый точный метод

- [ ] Реализовать `find_by_filename($file_path)`:
  - Поиск по имени файла
  - Самый быстрый метод
  - Может давать false positives

- [ ] Реализовать `find_by_filesize($file_path)`:
  - Размер + имя файла
  - Баланс скорости и точности

#### 9.8.3 Функциональность сканирования
- [ ] Опции сканирования:
  ```php
  - recursive (bool)          // Включать вложенные папки
  - file_types (array)        // [] = все разрешенные WP
  - skip_duplicates (bool)    // Не добавлять дубликаты
  - check_method (string)     // hash, filename, filesize
  - max_files (int)           // Лимит файлов
  - min_file_size (int)       // Мин размер в байтах
  - max_file_size (int)       // Макс размер
  ```

- [ ] Валидация файлов:
  - Проверка разрешенных MIME типов WordPress
  - Проверка размера файла
  - Проверка читаемости файла

#### 9.8.4 Функциональность импорта
- [ ] Копирование в uploads директорию
- [ ] Сохранение структуры папок (опционально)
- [ ] Создание attachment post
- [ ] Генерация thumbnails для изображений
- [ ] Установка alt text из имени файла
- [ ] Установка title из имени файла
- [ ] Сохранение автора (текущий пользователь)

#### 9.8.5 Premium: Real Media Library интеграция
- [ ] Проверка наличия RML плагина
- [ ] Проверка Premium версии через Freemius:
  ```php
  if (aie_fs()->is_premium() && function_exists('wp_rml_create')) {
      // Включить RML функции
  }
  ```

- [ ] Создание структуры папок в RML:
  ```php
  - get_or_create_rml_folder($name, $parent)
  - assign_to_rml_folder($attachment_id, $path)
  ```

- [ ] Сохранение соответствия папок:
  - `/ftp-import/products/` → RML folder "Products"
  - Вложенные папки → вложенные RML папки

#### 9.8.6 Admin UI - media_sync_page.php
- [ ] Создать `app/view/admin/media_sync_page.php`
- [ ] UI компоненты:
  ```
  Step 1: Select Folder
  - Input с путем к папке
  - [Browse Server] button (file picker)
  - [Recent Folders] dropdown
  - Checkbox "Include subfolders"
  - Статистика: Files found, Total size
  
  Step 2: File Options
  - Radio: All types / Images only / Custom
  - Custom: Multi-select файловых расширений
  
  Step 3: Duplicate Handling
  - Checkbox "Skip duplicates"
  - Radio group: Hash / Filename / Filesize
  - Описание каждого метода
  
  Step 4: Import Options
  - Checkbox "Set alt text from filename"
  - Checkbox "Generate thumbnails"
  - Checkbox "Preserve folder structure"
  - Premium section:
    - Checkbox "Create folders in Real Media Library"
    - [Upgrade] button если не Premium
  
  Step 5: Actions
  - [Scan Folder] - предпросмотр
  - [Start Sync] - начать синхронизацию
  
  Recent Syncs Table:
  - Date, Folder, Files count, Status
  - [View Details] action
  ```

- [ ] Progress Modal:
  ```
  - Progress bar (0-100%)
  - Current file name
  - Статистика: Success, Skipped, Failed
  - Time elapsed / estimated
  - [Pause] [Cancel] buttons
  ```

#### 9.8.7 JavaScript модуль
- [ ] Создать `src/js/modules/media_sync.js`
- [ ] Реализовать класс `MediaFolderSync`:
  ```javascript
  - scanFolder(path, options)          // AJAX сканирование
  - startSync(files, options)          // Начать синхронизацию
  - updateProgress()                   // Обновить прогресс
  - renderProgress(data)               // Отрисовать UI
  - showProgressModal()                // Показать модал
  - pauseSync()                        // Пауза
  - cancelSync()                       // Отмена
  ```

- [ ] AJAX handlers:
  - `aie_scan_folder` - сканировать папку
  - `aie_start_media_sync` - начать синхронизацию
  - `aie_get_sync_progress` - получить прогресс
  - `aie_pause_media_sync` - пауза
  - `aie_cancel_media_sync` - отмена

#### 9.8.8 Стилизация
- [ ] Создать `src/scss/admin/media_sync.scss`:
  ```scss
  - .media-sync-page
  - .sync-steps
  - .folder-browser
  - .file-preview
  - .premium-feature-box
  - .sync-progress-modal
  - .sync-stats
  ```

#### 9.8.9 Database таблица
- [ ] Обновить Phase 0: добавить таблицу `aie_media_sync`:
  ```sql
  - job_id (link to aie_jobs)
  - folder_path
  - file_path
  - attachment_id
  - status (pending, synced, skipped, failed)
  - skip_reason (duplicate, invalid_type, error)
  - file_hash (MD5)
  - file_size
  - error_message
  - created_at
  ```

#### 9.8.10 Хуки и фильтры
- [ ] Action hooks:
  ```php
  - aie_before_sync_file
  - aie_after_sync_file
  - aie_sync_file_skipped
  - aie_sync_file_error
  ```

- [ ] Filter hooks:
  ```php
  - aie_media_sync_files           // Модифицировать список
  - aie_media_sync_allowed_types   // Разрешенные типы
  - aie_media_sync_alt_text        // Alt text
  - aie_media_sync_title           // Title
  ```

#### 9.8.11 Batch Processing
- [ ] Интеграция с Queue_Manager
- [ ] Пакетная обработка (50 файлов за раз)
- [ ] Background processing через WordPress Cron
- [ ] Progress tracking через aie_jobs таблицу

**Критерии завершения**:
- ✅ Сканирование папок работает
- ✅ Фильтрация по типам файлов
- ✅ 3 метода проверки дубликатов (hash, filename, filesize)
- ✅ Импорт файлов в медиа библиотеку
- ✅ Генерация thumbnails
- ✅ Alt text и title из имени файла
- ✅ Preserve folder structure опция
- ✅ Premium: интеграция с Real Media Library
- ✅ UI с progress tracking
- ✅ Пауза и отмена синхронизации
- ✅ История синхронизаций

**Тестирование**:
```php
// Создать тестовую папку с файлами
$test_folder = ABSPATH . 'wp-content/uploads/test-sync/';
mkdir($test_folder);
file_put_contents($test_folder . 'test1.jpg', 'fake image data');
file_put_contents($test_folder . 'test2.png', 'fake image data');
mkdir($test_folder . 'subfolder/');
file_put_contents($test_folder . 'subfolder/test3.jpg', 'fake image data');

// Сканирование
$sync = new Media_Folder_Sync();
$files = $sync->scan_folder($test_folder, [
    'recursive' => true,
    'file_types' => ['jpg', 'png'],
    'skip_duplicates' => true
]);
// Должно найти 3 файла

// Проверка дубликата
$duplicate_id = $sync->check_duplicate($test_folder . 'test1.jpg', 'hash');
// Должно вернуть false (первый раз)

// Импорт файла
$attachment_id = $sync->import_file($test_folder . 'test1.jpg', [
    'set_alt_text' => true,
    'generate_metadata' => true
]);
// Должно создать attachment

// Проверка дубликата снова
$duplicate_id = $sync->check_duplicate($test_folder . 'test1.jpg', 'hash');
// Должно вернуть $attachment_id

// Синхронизация всех файлов
$job_id = $sync->sync_files($files, [
    'skip_duplicates' => true,
    'preserve_structure' => true
]);

// Проверка статистики
$stats = $sync->get_sync_stats($job_id);
// $stats['success'] === 2 (test2.png + test3.jpg)
// $stats['skipped'] === 1 (test1.jpg - дубликат)

// Premium: Real Media Library
if (aie_fs()->is_premium()) {
    $rml_folder = $sync->get_or_create_rml_folder('Test Sync', -1);
    // Должна создаться папка в RML
}

// Очистка
wp_delete_attachment($attachment_id, true);
```

---

## 📋 PHASE 9.9: Site-to-Site Content Sync - Синхронизация между сайтами

### Цель фазы
Реализовать двустороннюю синхронизацию контента между WordPress сайтами с использованием API ключей.

### Задачи

#### 9.9.1 Site_Connection_Manager класс
- [ ] Создать `app/sync/site_connection_manager.php`
- [ ] Реализовать класс `Site_Connection_Manager`:
  ```php
  - create_connection($data)             // Создать подключение
  - get_connections($filters)            // Список подключений
  - test_connection($connection_id)      // Тестировать
  - verify_remote_site($url, $key)       // Верификация
  - generate_api_key()                   // Генератор ключей
  - update_connection($id, $data)        // Обновить
  - delete_connection($id)               // Удалить
  ```

#### 9.9.2 Content_Sync_Manager класс
- [ ] Создать `app/sync/content_sync_manager.php`
- [ ] Реализовать класс `Content_Sync_Manager`:
  ```php
  - pull_content($connection_id, $options)   // Pull с удаленного
  - push_content($connection_id, $options)   // Push на удаленный
  - sync_posts($job, $direction, $conn, $opts)
  - sync_users($job, $direction, $conn, $opts)
  - sync_media($job, $direction, $conn, $opts)
  - sync_terms($job, $direction, $conn, $opts)
  - import_posts($posts, $options)           // Импорт постов
  - import_users($users, $options)           // Импорт пользователей
  - import_media($media, $connection)        // Импорт медиа
  - find_existing_post($data)                // Найти существующий
  - find_existing_user($data)
  - download_remote_media($url, $data)       // Скачать медиа
  ```

#### 9.9.3 Site_Sync_API класс
- [ ] Создать `app/sync/site_sync_api.php`
- [ ] Реализовать класс `Site_Sync_API`:
  ```php
  - register_routes()                        // REST API endpoints
  - check_api_key($request)                  // Проверка ключа
  - verify_connection($request)              // Верификация
  - export_content($request)                 // Экспорт (для Pull)
  - import_content($request)                 // Импорт (для Push)
  - list_content($request)                   // Список доступного
  ```

#### 9.9.4 REST API endpoints
- [ ] POST `/wp-json/aie/v1/site-sync/verify`
  - Верификация подключения
  - Возврат: site info + capabilities

- [ ] POST `/wp-json/aie/v1/site-sync/export`
  - Экспорт контента для Pull
  - Parameters: content_type, filters, options
  - Возврат: массив данных

- [ ] POST `/wp-json/aie/v1/site-sync/import`
  - Импорт контента для Push
  - Parameters: content_type, data, options
  - Возврат: статистика импорта

- [ ] POST `/wp-json/aie/v1/site-sync/list`
  - Список доступного контента
  - Parameters: content_type, filters
  - Возврат: список items с metadata

#### 9.9.5 Content Types Support

- [ ] **Posts синхронизация**:
  - Post data (title, content, excerpt, status)
  - Meta fields
  - Taxonomies (categories, tags)
  - Featured image
  - Media в контенте
  - Author (опционально)
  - Comments (опционально)

- [ ] **Pages синхронизация**:
  - Аналогично постам
  - Parent/child relationships
  - Page templates

- [ ] **Users синхронизация**:
  - User data (username, email, display_name)
  - Roles
  - Meta fields
  - Avatar (если есть)

- [ ] **Media синхронизация**:
  - Скачивание файлов
  - Metadata (alt, title, caption)
  - Thumbnails regeneration
  - Hash для проверки дубликатов

- [ ] **Taxonomies синхронизация**:
  - Terms (categories, tags, custom)
  - Term meta
  - Hierarchy (parent/child)

#### 9.9.6 Admin UI - content_sync_page.php
- [ ] Создать `app/view/admin/content_sync_page.php`
- [ ] Секции UI:
  ```
  1. Connections List
     - Название подключения
     - Remote URL
     - Direction (Pull/Push/Both)
     - Status (Active/Inactive/Error)
     - Last sync time
     - Actions: Pull, Push, Edit, Delete

  2. [+ New Connection] button
  
  3. Recent Syncs Table
     - Date/Time
     - Site name
     - Direction (Pull ↓ / Push ↑)
     - Content type
     - Status (Success/Failed)
     - Items count
     - [View Details] action
  ```

- [ ] Modal: New Connection
  ```
  - Connection Name
  - Remote Site URL
  - API Key (input)
  - Direction (radio: Pull/Push/Both)
  - [Test Connection] button
  - Status indicator (✓ Verified / ✗ Failed)
  - [Save] [Cancel]
  ```

- [ ] Modal: Pull Content
  ```
  Select Content Types:
  - ☑ Posts  ☑ Pages  ☐ Users
  - ☑ Media  ☑ Taxonomies  ☐ Comments
  
  Posts Options:
  - Post Types multi-select
  - Selection: All / Filtered / Selected
  - Filters: Category, Date, Author
  
  Sync Options:
  - ☑ Sync meta fields
  - ☑ Sync taxonomies
  - ☑ Sync featured images
  - ☑ Sync media in content
  - ☐ Sync authors
  
  Existing Content:
  - ○ Skip if exists
  - ● Update if exists
  - ○ Create duplicate
  
  [Preview Selection] [Start Pull]
  ```

- [ ] Modal: Push Content
  - Аналогично Pull Content
  - Выбор локального контента для отправки

- [ ] Progress Modal
  ```
  - Connection name
  - Direction indicator (↓ Pull / ↑ Push)
  - Progress bar (0-100%)
  - Current item name
  - Statistics:
    - ✓ Created: X
    - ↻ Updated: Y
    - ⊘ Skipped: Z
    - ✗ Failed: W
  - Media progress (if syncing media)
  - Time elapsed / estimated
  - [Pause] [Cancel] buttons
  ```

#### 9.9.7 JavaScript модуль
- [ ] Создать `src/js/modules/content_sync.js`
- [ ] Реализовать класс `ContentSync`:
  ```javascript
  - createConnection(data)               // Создать подключение
  - testConnection(url, key)             // Тест
  - getConnections()                     // Список
  - deleteConnection(id)                 // Удалить
  - previewContent(id, options)          // Preview перед sync
  - startPull(id, options)               // Начать Pull
  - startPush(id, options)               // Начать Push
  - updateProgress()                     // Обновить прогресс
  - renderProgress(data)                 // Отрисовать
  - pauseSync()                          // Пауза
  - cancelSync()                         // Отмена
  ```

- [ ] AJAX handlers:
  - `aie_create_site_connection`
  - `aie_test_site_connection`
  - `aie_get_site_connections`
  - `aie_delete_site_connection`
  - `aie_preview_sync_content`
  - `aie_start_content_pull`
  - `aie_start_content_push`
  - `aie_get_sync_progress`
  - `aie_pause_content_sync`
  - `aie_cancel_content_sync`

#### 9.9.8 Стилизация
- [ ] Создать `src/scss/admin/content_sync.scss`:
  ```scss
  - .content-sync-page
  - .connections-list
  - .connection-card
  - .connection-status
  - .sync-direction-badge
  - .new-connection-modal
  - .pull-push-modal
  - .content-selector
  - .sync-progress-modal
  ```

#### 9.9.9 Database таблицы
- [ ] Обновить Phase 0: добавить таблицы:
  ```sql
  aie_site_connections      // Подключения
  aie_content_sync          // История синхронизации
  aie_api_keys              // API ключи (входящие)
  ```

#### 9.9.10 API Keys Management
- [ ] Страница управления API ключами:
  - Список ключей
  - [+ Generate New Key]
  - Permissions (что разрешено)
  - Allowed IPs (опционально)
  - Last used timestamp
  - Status (Active/Inactive)
  - [Copy] [Revoke] actions

- [ ] Генератор ключей:
  - 64-character random string
  - Unique constraint
  - Hash storage (security)

#### 9.9.11 Security
- [ ] API Key валидация:
  - Header: `X-AIE-API-Key`
  - Check в БД
  - Status check (active/inactive)
  - Permissions check
  - IP whitelist check (если настроено)

- [ ] Rate limiting:
  - Max requests per minute
  - Transient-based tracking
  - 429 status code при превышении

- [ ] Data validation:
  - Sanitize all inputs
  - Validate content structure
  - Check user permissions
  - Nonce для AJAX

#### 9.9.12 Conflict Resolution
- [ ] Стратегии при конфликтах:
  - **Skip** - пропустить если существует
  - **Update** - перезаписать существующий
  - **Duplicate** - создать копию
  - **Merge** - объединить (future)

- [ ] Detection методы:
  - Posts: slug + post_type
  - Users: email
  - Media: file hash
  - Terms: slug + taxonomy

#### 9.9.13 Background Processing
- [ ] Интеграция с Queue_Manager
- [ ] Batch processing (50 items)
- [ ] WordPress Cron для больших объемов
- [ ] Resume после ошибки
- [ ] Progress tracking в aie_jobs

#### 9.9.14 Hooks & Filters
- [ ] Action hooks:
  ```php
  - aie_before_content_sync
  - aie_before_sync_item
  - aie_after_sync_item
  - aie_sync_item_skipped
  - aie_sync_item_error
  - aie_after_content_sync
  ```

- [ ] Filter hooks:
  ```php
  - aie_sync_post_data
  - aie_sync_user_data
  - aie_sync_term_data
  - aie_sync_media_data
  - aie_find_existing_content
  - aie_api_key_permissions
  ```

**Критерии завершения**:
- ✅ Создание и управление подключениями
- ✅ API Key генерация и валидация
- ✅ Верификация удаленных сайтов
- ✅ Pull контента с удаленного сайта
- ✅ Push контента на удаленный сайт
- ✅ Синхронизация всех типов контента (posts, users, media, terms)
- ✅ Проверка дубликатов
- ✅ Conflict resolution (skip/update/duplicate)
- ✅ Media синхронизация с скачиванием
- ✅ Progress tracking UI
- ✅ Пауза и отмена процесса
- ✅ История синхронизаций
- ✅ Security (API keys, rate limiting)

**Тестирование**:
```php
// Создать подключение
$manager = new Site_Connection_Manager();
$connection_id = $manager->create_connection([
    'name' => 'Production Site',
    'remote_url' => 'https://example.com',
    'api_key' => 'generated_key_from_remote_site',
    'direction' => 'bidirectional'
]);

// Тестировать подключение
$test_result = $manager->test_connection($connection_id);
// $test_result['verified'] === true

// Pull контент
$sync = new Content_Sync_Manager();
$job_id = $sync->pull_content($connection_id, [
    'content_types' => ['posts', 'media'],
    'post_types' => ['post', 'page'],
    'selection' => 'all',
    'overwrite' => true,
    'sync_meta' => true,
    'sync_media' => true
]);

// Проверка прогресса
$progress = $sync->get_progress($job_id);
// $progress['status'] === 'processing'
// $progress['processed'] / $progress['total']

// Push контент
$job_id = $sync->push_content($connection_id, [
    'content_types' => ['posts'],
    'selected_ids' => [1, 5, 10],
    'overwrite' => false
]);

// Проверка истории
$history = $sync->get_sync_history($connection_id);
// Массив всех синхронизаций

// API Keys
$api_manager = new Site_Sync_API();
$new_key = $api_manager->generate_api_key();
// Должен быть уникальный 64-char key

// Верификация через REST API
$response = wp_remote_post('https://remote.com/wp-json/aie/v1/site-sync/verify', [
    'headers' => ['X-AIE-API-Key' => $api_key]
]);
// Должен вернуть site info

// Pull через REST API
$response = wp_remote_post('https://remote.com/wp-json/aie/v1/site-sync/export', [
    'headers' => ['X-AIE-API-Key' => $api_key],
    'body' => json_encode([
        'content_type' => 'posts',
        'filters' => ['category' => 'news']
    ])
]);
// Должен вернуть массив постов

// Проверка conflict resolution
$existing_post = get_page_by_path('test-post', OBJECT, 'post');
$import_data = ['post_title' => 'Test Post', 'post_name' => 'test-post'];
$result = $sync->import_posts([$import_data], ['overwrite' => false]);
// $result['skipped'] === 1 (пост существует)

$result = $sync->import_posts([$import_data], ['overwrite' => true]);
// $result['updated'] === 1 (пост обновлен)
```

---

## 📋 PHASE 10: WooCommerce Integration

### Цель фазы
Добавить полноценную поддержку WooCommerce продуктов.

### Задачи

#### 10.1 Product_Importer
- [ ] Создать `app/model/import/woocommerce/product_importer.php`
- [ ] Реализовать класс `Product_Importer`
- [ ] Поддержка типов продуктов:
  ```
  - Simple
  - Variable
  - Grouped
  - External
  ```

#### 10.2 Product Data Import
- [ ] Базовые поля:
  ```
  - SKU, Price, Sale Price
  - Stock quantity, Stock status
  - Weight, Dimensions
  - Categories, Tags
  ```
- [ ] Attributes и Variations
- [ ] Product Images (галерея)
- [ ] Product Meta

#### 10.3 Product_Exporter
- [ ] Создать `app/model/export/woocommerce/product_exporter.php`
- [ ] Реализовать класс `Product_Exporter`
- [ ] Экспорт всех типов продуктов
- [ ] Фильтры по категориям, stock status
- [ ] Экспорт variations как отдельные строки

#### 10.4 CSV Template
- [ ] Генерация CSV шаблона для продуктов
- [ ] Примеры данных в шаблоне
- [ ] Документация по полям

**Критерии завершения**:
- ✅ Импорт простых продуктов
- ✅ Импорт variable products с вариациями
- ✅ Экспорт продуктов в CSV
- ✅ Обработка SKU дубликатов

**Тестирование**:
```php
// Импорт продукта
$data = [
    'sku' => 'TEST-001',
    'name' => 'Test Product',
    'regular_price' => '99.99',
    'stock_quantity' => 10
];

$importer = Importer_Factory::create('woocommerce_products');
$product_id = $importer->import($data);

// Проверка
$product = wc_get_product($product_id);
```

---

## 📋 PHASE 11: ACF Integration

### Цель фазы
Поддержка Advanced Custom Fields.

### Задачи

#### 11.1 ACF Field Detection
- [ ] Автоопределение ACF полей для post type
- [ ] Получение типов полей (text, textarea, image, repeater, etc)

#### 11.2 ACF Import
- [ ] Импорт простых полей
- [ ] Импорт repeater fields (JSON формат)
- [ ] Импорт flexible content
- [ ] Импорт relationship fields

#### 11.3 ACF Export
- [ ] Экспорт ACF полей
- [ ] Форматирование repeater в удобный формат
- [ ] Экспорт image fields (URL или ID)

**Критерии завершения**:
- ✅ ACF поля импортируются
- ✅ Repeater fields поддерживаются
- ✅ Экспорт ACF данных

---

## 📋 PHASE 12: CLI Commands

### Цель фазы
Добавить WP-CLI команды для автоматизации.

### Задачи

#### 12.1 WP-CLI Integration
- [ ] Создать `app/cli/import_command.php`
- [ ] Создать `app/cli/export_command.php`
- [ ] Реализовать классы `Import_Command` и `Export_Command`

#### 12.2 Import Command
```bash
wp aie import <file> --type=<type> --format=<format> [--mapping=<preset>]

# Примеры
wp aie import data.csv --type=posts --format=csv
wp aie import users.json --type=users --format=json --mapping=custom-preset
```

#### 12.3 Export Command
```bash
wp aie export <type> <file> --format=<format> [--filters=<json>]

# Примеры
wp aie export posts export.csv --format=csv
wp aie export users users.json --format=json --filters='{"role":"subscriber"}'
```

#### 12.4 Additional Commands
```bash
wp aie jobs list                    # Список заданий
wp aie jobs status <job_id>         # Статус задания
wp aie jobs logs <job_id>           # Логи задания
wp aie mappings list                # Список пресетов
```

**Критерии завершения**:
- ✅ Все команды работают
- ✅ Progress bar в CLI
- ✅ Детальный вывод информации

---

## 📋 PHASE 13: Testing & Quality Assurance

### Цель фазы
Обеспечить качество кода и стабильность плагина.

### Задачи

#### 13.1 Unit Tests (PHPUnit)
- [ ] Настроить PHPUnit
- [ ] Тесты для Helper классов
- [ ] Тесты для Format парсеров
- [ ] Тесты для Validators
- [ ] Мокирование WordPress функций

#### 13.2 Integration Tests
- [ ] Тесты полного flow импорта
- [ ] Тесты экспорта
- [ ] Тесты с реальными файлами
- [ ] Тесты Background Processing

#### 13.3 Performance Tests
- [ ] Тест с файлом 10,000 строк
- [ ] Тест с файлом 100,000 строк
- [ ] Мониторинг памяти
- [ ] Измерение времени выполнения

#### 13.4 Security Audit
- [ ] Проверка nonce везде
- [ ] Проверка capabilities
- [ ] SQL injection prevention (prepared statements)
- [ ] XSS prevention (escaping)
- [ ] File upload security

#### 13.5 Code Quality
- [ ] PHP CodeSniffer (WordPress Coding Standards)
- [ ] PHPStan для статического анализа
- [ ] Исправление всех warnings

**Критерии завершения**:
- ✅ Покрытие тестами > 70%
- ✅ Все тесты проходят
- ✅ Нет критических security issues
- ✅ Code quality = A

---

## 📋 PHASE 14: Documentation & Release

### Цель фазы
Подготовить плагин к релизу.

### Задачи

#### 14.1 User Documentation
- [ ] README.md с инструкциями
- [ ] Screenshots интерфейса
- [ ] Видео-туториалы (опционально)
- [ ] FAQ раздел

#### 14.2 Developer Documentation
- [ ] Обновить `ARCHITECTURE.md`
- [ ] PHPDoc для всех классов
- [ ] Примеры использования фильтров и хуков
- [ ] API Reference

#### 14.3 Changelog
- [ ] CHANGELOG.md с историей версий
- [ ] Семантическое версионирование

#### 14.4 Freemius Setup
- [ ] Настроить лицензирование
- [ ] Создать pricing plans
- [ ] Настроить автообновления

#### 14.5 WordPress.org Submission
- [ ] readme.txt для WordPress.org
- [ ] Assets (banner, icon)
- [ ] SVN setup
- [ ] Submission review

**Критерии завершения**:
- ✅ Полная документация
- ✅ Changelog заполнен
- ✅ Freemius работает
- ✅ Готов к релизу

---

## 🎯 Итоговые метрики успеха

### Функциональность:
- ✅ Импорт/экспорт: Posts, Pages, Users, Comments, Media
- ✅ Форматы: CSV, JSON, XML
- ✅ WooCommerce Products
- ✅ ACF Fields
- ✅ Custom Post Types
- ✅ Taxonomies
- ✅ Пользовательские функции обработки данных

### Производительность:
- ✅ Обработка файлов 100,000+ строк без ошибок
- ✅ Использование памяти < 256MB
- ✅ Background processing без таймаутов

### Качество:
- ✅ 0 критических багов
- ✅ Покрытие тестами > 70%
- ✅ Соответствие WordPress Coding Standards
- ✅ Security audit пройден

### UX:
- ✅ Интуитивный интерфейс
- ✅ Прогресс-бар в реальном времени
- ✅ Детальные логи
- ✅ Сохранение пресетов
- ✅ Редактор пользовательских функций с тестированием

---

## 📝 Порядок работы с этим планом

### Для каждого Phase:
1. **Прочитать** описание фазы и задач
2. **Сообщить ИИ**: "Начинаем Phase X"
3. **Следовать** списку задач по порядку
4. **Тестировать** после каждой задачи
5. **Обновлять** чеклисты (отмечать ✅)
6. **Фиксировать** изменения в git

### Команды для ИИ:
```
"Начни Phase 0" - начать фазу 0
"Создай FileHelper из Phase 1.1" - конкретная задача
"Проверь тестирование Phase 2" - запустить тесты
"Покажи прогресс DEVELOPMENT_PLAN" - текущий статус
```

### Правила:
- ❌ **НЕ пропускать** фазы
- ❌ **НЕ начинать** следующую фазу пока не закончена текущая
- ✅ **Тестировать** после каждой задачи
- ✅ **Обновлять** этот файл при изменениях
- ✅ **Держать** ARCHITECTURE.md в актуальном состоянии

---

**Версия плана**: 1.0.0  
**Дата создания**: 27 ноября 2025  
**Последнее обновление**: 27 ноября 2025

---

## 🚀 Готовы начать? Команда: "Начни Phase 0"
