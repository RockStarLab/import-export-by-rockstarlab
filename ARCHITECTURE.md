# Архитектура плагина WP Advanced Import Export

## Обзор

Плагин для импорта и экспорта данных WordPress с поддержкой различных типов контента и форматов файлов.

## Цели и задачи

### Основные цели:
- ✅ Универсальный импорт/экспорт любых типов данных WordPress
- ✅ Поддержка популярных форматов (CSV, JSON, XML)
- ✅ Обработка больших объемов данных без ограничений памяти
- ✅ Расширяемая архитектура для добавления новых типов данных
- ✅ Удобный UI с прогресс-баром и логами
- ✅ UI интерфейс по стилистике похож на стандартный стиль админки WordPress

### Функциональные требования:
1. Импорт/экспорт базовых типов WordPress (posts, pages, users, comments)
2. Поддержка custom post types и taxonomies
3. Работа с meta fields и custom fields
4. Импорт/экспорт медиафайлов
5. Маппинг полей при импорте и экспорте
6. Валидация данных
7. Обработка ошибок с детальными логами
8. Фоновая обработка данных

## Архитектурные принципы

### 1. Паттерны проектирования

#### Strategy Pattern (для форматов файлов)
```php
interface File_Format_Interface {
    public function read($file);
    public function write($data, $file);
    public function validate($file);
}

class Csv_Format implements File_Format_Interface { }
class Json_Format implements File_Format_Interface { }
class Xml_Format implements File_Format_Interface { }
```

#### Factory Pattern (для создания импортеров/экспортеров)
```php
class Importer_Factory {
    public static function create($data_type) {
        // posts, users, products, etc.
    }
}
```

#### Observer Pattern (для логирования и событий)
```php
class Import_Process {
    protected $observers = [];
    
    public function attach($observer) { }
    public function notify($event) { }
}
```

#### Chain of Responsibility (для валидации)
```php
abstract class Validation_Rule {
    protected $next;
    
    public function set_next($rule) { }
    public function validate($data) { }
}
```

### 2. Архитектура компонентов

```
wp-advanced-import-export/
├── app/
│   ├── controller/
│   │   ├── import.php              # Главный контроллер импорта
│   │   ├── export.php              # Главный контроллер экспорта
│   │   ├── ajax.php                # AJAX запросы
│   │   └── admin.php               # Админ интерфейс
│   │
│   ├── model/
│   │   ├── import/
│   │   │   ├── importer_interface.php
│   │   │   ├── abstract_importer.php
│   │   │   ├── post_importer.php
│   │   │   ├── user_importer.php
│   │   │   ├── comment_importer.php
│   │   │   └── media_importer.php
│   │   │
│   │   ├── export/
│   │   │   ├── exporter_interface.php
│   │   │   ├── abstract_exporter.php
│   │   │   ├── post_exporter.php
│   │   │   ├── user_exporter.php
│   │   │   └── comment_exporter.php
│   │   │
│   │   ├── format/
│   │   │   ├── file_format_interface.php
│   │   │   ├── csv_format.php
│   │   │   ├── json_format.php
│   │   │   └── xml_format.php
│   │   │
│   │   ├── validator/
│   │   │   ├── validation_rule.php
│   │   │   ├── required_field_rule.php
│   │   │   ├── data_type_rule.php
│   │   │   └── unique_value_rule.php
│   │   │
│   │   └── queue/
│   │       ├── queue_manager.php
│   │       ├── job.php
│   │       └── batch_processor.php
│   │
│   ├── helper/
│   │   ├── file.php                # Работа с файлами
│   │   ├── logger.php              # Логирование
│   │   ├── security.php            # Проверки безопасности
│   │   ├── data_mapper.php         # Маппинг полей
│   │   └── function_executor.php   # Выполнение пользовательских функций
│   │
│   ├── library/
│   │   └── function_snippets.php   # Библиотека готовых сниппетов функций
│   │
│   ├── sync/
│   │   ├── media_folder_sync.php   # Синхронизация папок с медиа библиотекой
│   │   ├── site_connection_manager.php  # Управление подключениями между сайтами
│   │   ├── content_sync_manager.php     # Синхронизация контента
│   │   └── site_sync_api.php            # REST API для site-to-site
│   │
│   └── view/
│       └── admin/
│           ├── import_page.php
│           ├── export_page.php
│           ├── history_page.php
│           ├── settings_page.php
│           ├── functions_page.php  # Редактор пользовательских функций
│           ├── media_sync_page.php # Синхронизация медиа папок
│           └── content_sync_page.php # Site-to-Site синхронизация
│
└── assets/
    ├── js/
    │   └── modules/
    │       ├── import.js
    │       ├── export.js
    │       └── progress.js
    └── css/
```

## Технический стек

### Backend:
- **PHP 7.4+**: Основной язык
- **WordPress API**: WP_Query, wpdb, REST API
- **WordPress Cron**: Фоновые задачи

### Frontend:
- **Vanilla JavaScript (ES6+)**: Интерактивность
- **SCSS**: Стилизация
- **WordPress AJAX API**: Асинхронные запросы

### Форматы данных:
- **CSV**: league/csv или custom parser
- **JSON**: Native PHP json_encode/decode
- **XML**: SimpleXML или XMLReader для больших файлов

## Ключевые компоненты

### 1. Import Flow

```
[Upload File] 
    ↓
[Validate File] (формат, размер, безопасность)
    ↓
[Parse File] (чтение в chunks)
    ↓
[Map Fields] (UI для сопоставления полей)
    ↓
[Validate Data] (цепочка правил валидации)
    ↓
[Create Queue Jobs] (разбить на батчи)
    ↓
[Process Batches] (фоновая обработка)
    ↓
[Log Results] (успешные/ошибочные записи)
```

### 2. Export Flow

```
[Select Data Type] (posts, users, etc.)
    ↓
[Configure Filters] (date range, status, etc.)
    ↓
[Select Fields] (какие поля экспортировать)
    ↓
[Choose Format] (CSV, JSON, XML)
    ↓
[Generate Query] (WP_Query или wpdb)
    ↓
[Process in Batches] (по 100-500 записей)
    ↓
[Write to File] (streaming для больших файлов)
    ↓
[Download/Save]
```

### 3. Batch Processing System

```php
class Batch_Processor {
    protected $batch_size = 100;
    protected $current_batch = 0;
    
    public function process($data) {
        $batches = array_chunk($data, $this->batch_size);
        
        foreach ($batches as $batch) {
            $this->process_batch($batch);
            $this->update_progress();
            
            // Предотвращение timeout
            if ($this->should_pause()) {
                $this->schedule_next_batch();
                break;
            }
        }
    }
    
    protected function should_pause() {
        // Проверка времени выполнения и памяти
    }
}
```

### 4. Security Layer

```php
class Security_Helper {
    public static function validate_upload($file) {
        // Проверка типа файла
        // Проверка расширения
        // Проверка MIME-type
        // Проверка размера
        // Scan на вредоносный код
    }
    
    public static function sanitize_data($data) {
        // Очистка данных перед сохранением
    }
    
    public static function check_capabilities($action) {
        // Проверка прав пользователя
    }
}
```

### 5. Logger System

```php
class Logger {
    public function log_import($job_id, $status, $message, $data = []) {
        // Сохранение в custom table
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aie_logs',
            [
                'job_id' => $job_id,
                'type' => 'import',
                'status' => $status,
                'message' => $message,
                'data' => json_encode($data),
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    public function get_job_logs($job_id) {
        // Получение логов для отображения
    }
}
```

### 6. Custom Functions System - Пользовательские функции обработки

Система позволяет пользователям создавать собственные PHP функции для обработки данных при импорте/экспорте.

#### Архитектура компонента

```php
class Function_Executor {
    /**
     * Выполнить пользовательскую функцию в безопасной среде
     */
    public function execute($function_id, $value, $context = []) {
        $function = $this->get_function($function_id);
        
        if (!$function || !$function->is_active) {
            return $value;
        }
        
        // Валидация и sandbox
        if (!$this->validate_function_code($function->function_code)) {
            throw new Exception('Unsafe function code');
        }
        
        // Выполнение в изолированной среде
        return $this->execute_in_sandbox($function->function_code, $value, $context);
    }
    
    /**
     * Выполнение в sandbox с ограничениями
     */
    protected function execute_in_sandbox($code, $value, $context) {
        // Создание анонимной функции из кода
        $wrapper = "return function(\$value, \$context) { $code };";
        
        try {
            $func = eval($wrapper);
            return $func($value, $context);
        } catch (Exception $e) {
            Logger::log(0, 'error', 'Function execution failed: ' . $e->getMessage());
            return $value; // Возврат исходного значения при ошибке
        }
    }
    
    /**
     * Валидация кода функции на опасные конструкции
     */
    protected function validate_function_code($code) {
        $dangerous_functions = [
            'exec', 'shell_exec', 'system', 'passthru', 'eval',
            'file_get_contents', 'file_put_contents', 'unlink',
            'rmdir', 'mysql_query', 'curl_exec'
        ];
        
        foreach ($dangerous_functions as $func) {
            if (stripos($code, $func) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Получить функцию из БД
     */
    protected function get_function($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aie_custom_functions WHERE id = %d AND is_active = 1",
            $id
        ));
    }
}
```

#### CRUD операции для функций

```php
class Custom_Functions_Manager {
    /**
     * Создать новую функцию
     */
    public function create($data) {
        global $wpdb;
        
        // Валидация
        if (!$this->validate_function_data($data)) {
            return new WP_Error('invalid_data', 'Invalid function data');
        }
        
        // Проверка безопасности кода
        $executor = new Function_Executor();
        if (!$executor->validate_function_code($data['function_code'])) {
            return new WP_Error('unsafe_code', 'Function contains unsafe operations');
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'aie_custom_functions',
            [
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'function_code' => $data['function_code'],
                'input_type' => $data['input_type'],
                'output_type' => $data['output_type'],
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Обновить функцию
     */
    public function update($id, $data) {
        global $wpdb;
        
        // Валидация прав
        if (!$this->can_edit_function($id)) {
            return new WP_Error('permission_denied', 'You cannot edit this function');
        }
        
        // Валидация кода
        if (isset($data['function_code'])) {
            $executor = new Function_Executor();
            if (!$executor->validate_function_code($data['function_code'])) {
                return new WP_Error('unsafe_code', 'Function contains unsafe operations');
            }
        }
        
        $update_data = [];
        if (isset($data['name'])) $update_data['name'] = sanitize_text_field($data['name']);
        if (isset($data['description'])) $update_data['description'] = sanitize_textarea_field($data['description']);
        if (isset($data['function_code'])) $update_data['function_code'] = $data['function_code'];
        if (isset($data['input_type'])) $update_data['input_type'] = $data['input_type'];
        if (isset($data['output_type'])) $update_data['output_type'] = $data['output_type'];
        if (isset($data['is_active'])) $update_data['is_active'] = (int) $data['is_active'];
        
        $update_data['updated_at'] = current_time('mysql');
        
        return $wpdb->update(
            $wpdb->prefix . 'aie_custom_functions',
            $update_data,
            ['id' => $id]
        );
    }
    
    /**
     * Удалить функцию
     */
    public function delete($id) {
        if (!$this->can_edit_function($id)) {
            return new WP_Error('permission_denied', 'You cannot delete this function');
        }
        
        global $wpdb;
        return $wpdb->delete(
            $wpdb->prefix . 'aie_custom_functions',
            ['id' => $id]
        );
    }
    
    /**
     * Получить все функции
     */
    public function get_all($filters = []) {
        global $wpdb;
        
        $where = ['1=1'];
        
        if (isset($filters['is_active'])) {
            $where[] = $wpdb->prepare('is_active = %d', $filters['is_active']);
        }
        
        if (isset($filters['user_id'])) {
            $where[] = $wpdb->prepare('user_id = %d', $filters['user_id']);
        }
        
        $sql = "SELECT * FROM {$wpdb->prefix}aie_custom_functions WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY name ASC";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Тестировать функцию
     */
    public function test_function($function_code, $test_value) {
        $executor = new Function_Executor();
        
        // Валидация
        if (!$executor->validate_function_code($function_code)) {
            return [
                'success' => false,
                'error' => 'Function contains unsafe operations'
            ];
        }
        
        try {
            $result = $executor->execute_in_sandbox($function_code, $test_value, []);
            return [
                'success' => true,
                'result' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
```

#### UI Компоненты

**Functions Page** - Управление функциями:
```
┌─────────────────────────────────────────────┐
│ Custom Functions                             │
│                                              │
│ [+ Add New Function]                         │
│                                              │
│ ┌──────────────────────────────────────────┐│
│ │ Function Name    │ Status │ Used │ Actions││
│ ├──────────────────────────────────────────┤│
│ │ Uppercase        │ Active │  15  │ E │ D ││
│ │ Remove HTML      │ Active │   8  │ E │ D ││
│ │ Format Date      │ Inactive│  0  │ E │ D ││
│ └──────────────────────────────────────────┘│
└─────────────────────────────────────────────┘
```

**Function Editor Modal**:
```
┌─────────────────────────────────────────────┐
│ Edit Function: "Uppercase"              [X] │
├─────────────────────────────────────────────┤
│ Name: [Uppercase                        ]   │
│                                              │
│ Description:                                 │
│ [Converts text to uppercase              ]  │
│                                              │
│ Input Type: [String ▼]  Output: [String ▼] │
│                                              │
│ Function Code:                               │
│ ┌──────────────────────────────────────────┐│
│ │ return strtoupper($value);              ││
│ │                                          ││
│ │                                          ││
│ └──────────────────────────────────────────┘│
│                                              │
│ Test Function:                               │
│ Input: [hello world    ] [Test]             │
│ Result: HELLO WORLD                          │
│                                              │
│ ☑ Active  [Cancel]  [Save Function]         │
└─────────────────────────────────────────────┘
```

**Field Mapping с функциями**:
```
┌─────────────────────────────────────────────┐
│ Field Mapping                                │
├─────────────────────────────────────────────┤
│ Source Field    → Target Field  → Function  │
│ ────────────────────────────────────────────│
│ title           → post_title    → [None ▼]  │
│ description     → post_content  → [Remove HTML ▼]│
│ date            → post_date     → [Format Date ▼]│
│ author_name     → post_author   → [Find User ▼] │
└─────────────────────────────────────────────┘
```

#### Примеры готовых функций

**1. Конвертация в верхний регистр:**
```php
return strtoupper($value);
```

**2. Удаление HTML тегов:**
```php
return strip_tags($value);
```

**3. Форматирование даты:**
```php
$date = strtotime($value);
return date('Y-m-d H:i:s', $date);
```

**4. Извлечение домена из email:**
```php
if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
    return substr(strrchr($value, "@"), 1);
}
return '';
```

**5. Преобразование цены:**
```php
// Удалить символы валюты и пробелы
$cleaned = preg_replace('/[^0-9.,]/', '', $value);
// Заменить запятую на точку
$cleaned = str_replace(',', '.', $cleaned);
return floatval($cleaned);
```

**6. Конкатенация полей:**
```php
// $context содержит другие поля строки
$first_name = isset($context['first_name']) ? $context['first_name'] : '';
$last_name = isset($context['last_name']) ? $context['last_name'] : '';
return trim($first_name . ' ' . $last_name);
```

**7. Генерация slug:**
```php
return sanitize_title($value);
```

**8. Поиск пользователя по email:**
```php
$user = get_user_by('email', $value);
return $user ? $user->ID : 0;
```

#### Безопасность

**Whitelist разрешенных функций:**
```php
$allowed_functions = [
    // Строковые
    'strtoupper', 'strtolower', 'ucfirst', 'ucwords', 'trim', 'ltrim', 'rtrim',
    'str_replace', 'str_pad', 'substr', 'strlen', 'strpos', 'strip_tags',
    'sanitize_title', 'sanitize_text_field', 'sanitize_email',
    
    // Дата/время
    'date', 'strtotime', 'gmdate',
    
    // Числа
    'intval', 'floatval', 'abs', 'round', 'ceil', 'floor',
    'number_format',
    
    // Массивы
    'array_map', 'array_filter', 'implode', 'explode', 'in_array',
    
    // WordPress функции
    'get_user_by', 'get_term_by', 'get_post',
    
    // Фильтрация
    'filter_var', 'preg_match', 'preg_replace'
];

// Применить через фильтр
$allowed = apply_filters('aie_custom_function_sandbox', $allowed_functions);
```

**Ограничения:**
- ❌ Запрещены файловые операции
- ❌ Запрещены системные вызовы
- ❌ Запрещены прямые SQL запросы
- ❌ Запрещен `eval()` и подобные конструкции
- ✅ Таймаут выполнения: 5 секунд
- ✅ Логирование всех ошибок
- ✅ Возврат исходного значения при ошибке

### 7. Function Snippets Library - Библиотека готовых сниппетов

Встроенная коллекция готовых функций, которые пользователь может использовать как есть или редактировать под свои нужды.

#### Архитектура библиотеки

```php
class Function_Snippets {
    /**
     * Получить все категории сниппетов
     */
    public function get_categories() {
        return apply_filters('aie_snippet_categories', [
            'string' => [
                'name' => 'String Operations',
                'icon' => 'dashicons-editor-code'
            ],
            'date' => [
                'name' => 'Date & Time',
                'icon' => 'dashicons-calendar-alt'
            ],
            'number' => [
                'name' => 'Numeric Operations',
                'icon' => 'dashicons-calculator'
            ],
            'wordpress' => [
                'name' => 'WordPress Functions',
                'icon' => 'dashicons-wordpress'
            ]
        ]);
    }
    
    /**
     * Получить все сниппеты
     */
    public function get_all_snippets() {
        $snippets = [
            'uppercase' => [
                'category' => 'string',
                'name' => 'Uppercase',
                'description' => 'Convert text to uppercase',
                'code' => 'return strtoupper($value);',
                'tags' => ['string', 'case']
            ],
            // ... больше сниппетов
        ];
        
        return apply_filters('aie_function_snippets', $snippets);
    }
    
    /**
     * Поиск сниппетов
     */
    public function search($query) {
        // Поиск по названию, описанию, тегам
    }
    
    /**
     * Импорт сниппета как функции
     */
    public function import_to_custom_function($snippet_key, $custom_name = null) {
        // Создать пользовательскую функцию из сниппета
    }
}
```

#### UI библиотеки

- **Browse Library Button** - на странице Custom Functions
- **Категории с фильтрацией**
- **Поиск по названию/тегам**
- **Preview Code перед импортом**
- **"Use As Is" или "Customize"**
- **Примеры входа/выхода для каждого сниппета**

#### Расширение через фильтры

```php
// Добавление своих сниппетов
add_filter('aie_function_snippets', function($snippets) {
    $snippets['my_snippet'] = [
        'category' => 'custom',
        'name' => 'My Transform',
        'description' => 'Custom transformation',
        'code' => 'return custom_logic($value);',
        'tags' => ['custom']
    ];
    return $snippets;
});
```

### 8. Media Folder Sync - Синхронизация папок с медиа библиотекой

Система синхронизации файлов из папок на сервере (загруженных через FTP) с медиа библиотекой WordPress.

#### Архитектура Media Sync

```php
class Media_Folder_Sync {
    /**
     * Сканировать папку и получить список файлов
     * 
     * @param string $folder_path Абсолютный путь к папке
     * @param array $options Опции сканирования
     * @return array Список файлов для синхронизации
     */
    public function scan_folder($folder_path, $options = []) {
        // Опции по умолчанию
        $defaults = [
            'recursive' => true,              // Включать вложенные папки
            'file_types' => [],               // [] = все разрешенные WP типы
            'skip_duplicates' => true,        // Не добавлять если файл существует
            'check_method' => 'hash',         // 'hash', 'filename', 'filesize'
            'max_files' => 0,                 // 0 = без ограничений
            'min_file_size' => 0,             // В байтах
            'max_file_size' => 0,             // 0 = без ограничений
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Сканирование с фильтрами
        // Возвращает массив файлов с метаданными
    }
    
    /**
     * Синхронизировать файлы с медиа библиотекой
     * 
     * @param array $files Список файлов из scan_folder()
     * @param array $options Опции импорта
     * @return array Результаты синхронизации
     */
    public function sync_files($files, $options = []) {
        // Опции
        $defaults = [
            'preserve_structure' => true,     // Сохранять структуру папок
            'real_media_library' => false,    // Интеграция с RML (Premium)
            'set_alt_text' => true,           // Установить alt из имени файла
            'generate_metadata' => true,      // Генерировать метаданные
            'author_id' => get_current_user_id(),
            'batch_size' => 50,               // Пакетная обработка
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Пакетная обработка через Queue_Manager
        // Возвращает job_id для отслеживания прогресса
    }
    
    /**
     * Проверить существование файла в медиа библиотеке
     * 
     * @param string $file_path Путь к файлу
     * @param string $method Метод проверки (hash, filename, filesize)
     * @return int|false ID attachment или false
     */
    public function check_duplicate($file_path, $method = 'hash') {
        switch ($method) {
            case 'hash':
                // MD5 hash файла + размер
                return $this->find_by_hash($file_path);
            
            case 'filename':
                // Только имя файла
                return $this->find_by_filename($file_path);
            
            case 'filesize':
                // Размер + имя файла
                return $this->find_by_filesize($file_path);
        }
        
        return false;
    }
    
    /**
     * Импортировать один файл в медиа библиотеку
     * 
     * @param string $file_path Путь к файлу
     * @param array $options Опции импорта
     * @return int|WP_Error ID attachment или ошибка
     */
    public function import_file($file_path, $options = []) {
        // Валидация типа файла
        if (!$this->is_allowed_file_type($file_path)) {
            return new WP_Error('invalid_type', 'File type not allowed');
        }
        
        // Проверка дубликатов
        if ($options['skip_duplicates']) {
            $existing = $this->check_duplicate($file_path, $options['check_method']);
            if ($existing) {
                return new WP_Error('duplicate', 'File already exists', ['attachment_id' => $existing]);
            }
        }
        
        // Копирование в uploads
        $upload_result = $this->copy_to_uploads($file_path, $options);
        
        if (is_wp_error($upload_result)) {
            return $upload_result;
        }
        
        // Создание attachment
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $upload_result['type'],
            'post_title' => $this->get_title_from_filename($file_path),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => $options['author_id'],
        ], $upload_result['file']);
        
        // Генерация метаданных
        if ($options['generate_metadata']) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            wp_update_attachment_metadata(
                $attachment_id,
                wp_generate_attachment_metadata($attachment_id, $upload_result['file'])
            );
        }
        
        // Alt text
        if ($options['set_alt_text']) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', 
                $this->get_alt_from_filename($file_path));
        }
        
        // Интеграция с Real Media Library (Premium)
        if ($options['real_media_library'] && $this->is_rml_available()) {
            $this->assign_to_rml_folder($attachment_id, $file_path, $options);
        }
        
        return $attachment_id;
    }
    
    /**
     * Проверить доступность Real Media Library
     */
    protected function is_rml_available() {
        return function_exists('wp_rml_create') && aie_fs()->is_premium();
    }
    
    /**
     * Создать структуру папок в RML и назначить файл
     * 
     * @param int $attachment_id ID вложения
     * @param string $file_path Исходный путь файла
     * @param array $options Опции
     */
    protected function assign_to_rml_folder($attachment_id, $file_path, $options) {
        if (!$options['preserve_structure']) {
            return;
        }
        
        // Получить относительный путь от базовой папки
        $relative_path = $this->get_relative_path($file_path, $options['base_folder']);
        
        // Разбить на папки
        $folders = explode('/', dirname($relative_path));
        
        // Создать структуру папок в RML
        $parent_id = -1; // Root
        foreach ($folders as $folder_name) {
            if (empty($folder_name)) continue;
            
            $folder_id = $this->get_or_create_rml_folder($folder_name, $parent_id);
            $parent_id = $folder_id;
        }
        
        // Назначить файл в папку
        wp_rml_move($parent_id, [$attachment_id]);
    }
    
    /**
     * Получить или создать папку в RML
     */
    protected function get_or_create_rml_folder($name, $parent = -1) {
        // Проверить существование
        $existing = $this->find_rml_folder($name, $parent);
        if ($existing) {
            return $existing;
        }
        
        // Создать новую папку
        return wp_rml_create($parent, $name);
    }
    
    /**
     * Получить разрешенные типы файлов WordPress
     */
    public function get_allowed_file_types() {
        $mime_types = get_allowed_mime_types();
        
        return apply_filters('aie_media_sync_allowed_types', $mime_types);
    }
    
    /**
     * Получить статистику синхронизации
     */
    public function get_sync_stats($job_id) {
        return [
            'total' => 0,
            'processed' => 0,
            'success' => 0,
            'skipped' => 0,      // Дубликаты
            'failed' => 0,
            'size_total' => 0,   // Байты
            'duration' => 0,     // Секунды
        ];
    }
}
```

#### UI для Media Folder Sync

**Страница:** `app/view/admin/media_sync_page.php`

```
┌─────────────────────────────────────────────────────────────────┐
│  Media Folder Sync                                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Step 1: Select Folder                                          │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ Folder Path: /wp-content/uploads/ftp-import/            │  │
│  │ [Browse Server...] [Recent Folders ▼]                   │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ☑ Include subfolders                                          │
│  Files found: 247 (23.5 MB)                                    │
│                                                                 │
│  Step 2: File Options                                           │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ File Types:                                              │  │
│  │ ○ All allowed by WordPress                               │  │
│  │ ○ Images only (jpg, png, gif, webp)                      │  │
│  │ ○ Custom: [jpg] [png] [pdf] [+Add]                      │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
│  Step 3: Duplicate Handling                                     │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ ☑ Skip duplicates                                        │  │
│  │                                                           │  │
│  │ Check method:                                            │  │
│  │ ○ File hash (MD5) - Most accurate                        │  │
│  │ ● Filename - Faster                                      │  │
│  │ ○ File size + name - Balanced                            │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
│  Step 4: Import Options                                         │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ ☑ Set alt text from filename                            │  │
│  │ ☑ Generate image thumbnails                              │  │
│  │ ☑ Preserve folder structure                              │  │
│  │                                                           │  │
│  │ 👑 Premium Features                                       │  │
│  │ ☑ Create folders in Real Media Library                   │  │
│  │   [Upgrade to Premium] (если не активна)                │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
│  [Scan Folder] [Start Sync]                                    │
│                                                                 │
│  ═══════════════════════════════════════════════════════════  │
│                                                                 │
│  Recent Syncs                                                   │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ Date       | Folder              | Files | Status        │  │
│  │────────────┼────────────────────┼───────┼──────────────│  │
│  │ 2025-11-27 | /ftp-import/       │ 247   │ ✓ Completed  │  │
│  │ 2025-11-25 | /old-media/        │ 89    │ ✓ Completed  │  │
│  │ 2025-11-20 | /products/         │ 456   │ ⚠ 3 failed   │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Модальное окно прогресса:**
```
┌─────────────────────────────────────────────┐
│  Syncing Files...                      [✕] │
├─────────────────────────────────────────────┤
│                                             │
│  Progress: 47 / 247 files (19%)             │
│  ████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░      │
│                                             │
│  Current: image-047.jpg (245 KB)            │
│  Status: Checking for duplicates...         │
│                                             │
│  ✓ Success: 45                              │
│  ⊘ Skipped: 2 (duplicates)                  │
│  ✗ Failed: 0                                │
│                                             │
│  Time elapsed: 00:01:23                     │
│  Estimated: 00:03:45 remaining              │
│                                             │
│  [Pause] [Cancel]                           │
│                                             │
└─────────────────────────────────────────────┘
```

#### JavaScript модуль

`src/js/modules/media_sync.js`:
```javascript
class MediaFolderSync {
    constructor() {
        this.currentJobId = null;
        this.progressInterval = null;
    }
    
    /**
     * Сканировать папку на сервере
     */
    async scanFolder(folderPath, options) {
        const response = await fetch(aieAdmin.ajaxUrl, {
            method: 'POST',
            body: new FormData({
                action: 'aie_scan_folder',
                nonce: aieAdmin.nonce,
                folder_path: folderPath,
                options: JSON.stringify(options)
            })
        });
        
        return await response.json();
    }
    
    /**
     * Начать синхронизацию
     */
    async startSync(folderPath, files, options) {
        const response = await fetch(aieAdmin.ajaxUrl, {
            method: 'POST',
            body: new FormData({
                action: 'aie_start_media_sync',
                nonce: aieAdmin.nonce,
                folder_path: folderPath,
                files: JSON.stringify(files),
                options: JSON.stringify(options)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.currentJobId = data.job_id;
            this.startProgressTracking();
            this.showProgressModal();
        }
        
        return data;
    }
    
    /**
     * Отслеживать прогресс
     */
    startProgressTracking() {
        this.progressInterval = setInterval(() => {
            this.updateProgress();
        }, 1000);
    }
    
    /**
     * Обновить прогресс
     */
    async updateProgress() {
        const response = await fetch(aieAdmin.ajaxUrl, {
            method: 'POST',
            body: new FormData({
                action: 'aie_get_sync_progress',
                nonce: aieAdmin.nonce,
                job_id: this.currentJobId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.renderProgress(data.progress);
            
            if (data.progress.status === 'completed' || 
                data.progress.status === 'failed') {
                this.stopProgressTracking();
                this.showResults(data.progress);
            }
        }
    }
    
    /**
     * Отрисовать прогресс
     */
    renderProgress(progress) {
        const percent = (progress.processed / progress.total) * 100;
        
        document.querySelector('.progress-bar').style.width = `${percent}%`;
        document.querySelector('.progress-text').textContent = 
            `${progress.processed} / ${progress.total} files (${Math.round(percent)}%)`;
        
        document.querySelector('.stats-success').textContent = progress.success;
        document.querySelector('.stats-skipped').textContent = progress.skipped;
        document.querySelector('.stats-failed').textContent = progress.failed;
    }
}
```

#### Интеграция с Freemius Premium

```php
// Проверка Premium версии
if (aie_fs()->is_premium()) {
    // Показать опции Real Media Library
    add_filter('aie_media_sync_show_rml', '__return_true');
}

// Хук для отображения RML опций
add_action('aie_media_sync_options', function() {
    if (!apply_filters('aie_media_sync_show_rml', false)) {
        ?>
        <div class="premium-feature-box">
            <span class="dashicons dashicons-crown"></span>
            <strong>Premium Feature:</strong> 
            Automatically create folder structure in Real Media Library
            <a href="<?php echo aie_fs()->get_upgrade_url(); ?>" class="button button-primary">
                Upgrade to Premium
            </a>
        </div>
        <?php
        return;
    }
    
    // Показать RML опции
    ?>
    <label>
        <input type="checkbox" name="real_media_library" value="1" />
        Create folders in Real Media Library
    </label>
    <?php
});
```

#### Database Schema Update

Добавить таблицу для отслеживания синхронизированных папок:

```sql
CREATE TABLE {prefix}_aie_media_sync (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT(20) UNSIGNED NOT NULL,
    folder_path VARCHAR(500) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    attachment_id BIGINT(20) UNSIGNED,
    status ENUM('pending', 'synced', 'skipped', 'failed') DEFAULT 'pending',
    skip_reason VARCHAR(100), -- 'duplicate', 'invalid_type', 'error'
    file_hash VARCHAR(32),    -- MD5 hash
    file_size BIGINT(20),
    error_message TEXT,
    created_at DATETIME NOT NULL,
    INDEX job_id_idx (job_id),
    INDEX folder_path_idx (folder_path(255)),
    INDEX file_hash_idx (file_hash),
    INDEX attachment_id_idx (attachment_id),
    INDEX status_idx (status),
    FOREIGN KEY (job_id) REFERENCES {prefix}_aie_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Фильтры и хуки

```php
// Фильтр для модификации списка файлов перед синхронизацией
apply_filters('aie_media_sync_files', $files, $folder_path, $options);

// Фильтр для разрешенных типов файлов
apply_filters('aie_media_sync_allowed_types', $mime_types);

// Действие перед синхронизацией файла
do_action('aie_before_sync_file', $file_path, $options);

// Действие после успешной синхронизации
do_action('aie_after_sync_file', $attachment_id, $file_path, $options);

// Действие при пропуске файла (дубликат)
do_action('aie_sync_file_skipped', $file_path, $reason, $existing_id);

// Действие при ошибке
do_action('aie_sync_file_error', $file_path, $error);

// Фильтр для генерации alt text
apply_filters('aie_media_sync_alt_text', $alt_text, $file_path);

// Фильтр для генерации title
apply_filters('aie_media_sync_title', $title, $file_path);
```

### 9. Site-to-Site Content Sync - Синхронизация контента между сайтами

Двусторонняя синхронизация контента между WordPress сайтами с использованием безопасного API ключа.

#### Архитектура Content Sync

```php
class Site_Connection_Manager {
    /**
     * Создать новое подключение между сайтами
     * 
     * @param array $data Данные подключения
     * @return int|WP_Error ID подключения или ошибка
     */
    public function create_connection($data) {
        // Данные:
        // - name (display name)
        // - remote_url (полный URL удаленного сайта)
        // - api_key (сгенерированный ключ)
        // - direction ('pull', 'push', 'bidirectional')
        
        // Валидация удаленного сайта
        $verified = $this->verify_remote_site($data['remote_url'], $data['api_key']);
        
        if (is_wp_error($verified)) {
            return $verified;
        }
        
        // Сохранить подключение
        return $this->save_connection($data);
    }
    
    /**
     * Верифицировать удаленный сайт
     */
    protected function verify_remote_site($url, $api_key) {
        $response = wp_remote_post($url . '/wp-json/aie/v1/site-sync/verify', [
            'headers' => [
                'X-AIE-API-Key' => $api_key
            ],
            'timeout' => 15
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$body['verified']) {
            return new WP_Error('verification_failed', 'Remote site verification failed');
        }
        
        return $body;
    }
    
    /**
     * Получить список подключений
     */
    public function get_connections($filters = []) {
        global $wpdb;
        
        $where = ['1=1'];
        
        if (!empty($filters['status'])) {
            $where[] = $wpdb->prepare('status = %s', $filters['status']);
        }
        
        $sql = "SELECT * FROM {$wpdb->prefix}aie_site_connections 
                WHERE " . implode(' AND ', $where);
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Тестировать подключение
     */
    public function test_connection($connection_id) {
        $connection = $this->get_connection($connection_id);
        
        if (!$connection) {
            return new WP_Error('not_found', 'Connection not found');
        }
        
        return $this->verify_remote_site($connection->remote_url, $connection->api_key);
    }
    
    /**
     * Генерировать API ключ
     */
    public function generate_api_key() {
        return wp_generate_password(64, false);
    }
}

class Content_Sync_Manager {
    /**
     * Pull контент с удаленного сайта
     * 
     * @param int $connection_id ID подключения
     * @param array $options Опции синхронизации
     * @return int Job ID
     */
    public function pull_content($connection_id, $options) {
        $connection = $this->get_connection($connection_id);
        
        // Опции по умолчанию
        $defaults = [
            'content_types' => ['posts'],     // posts, pages, users, media, terms
            'post_types' => ['post'],         // Какие post types
            'taxonomies' => [],               // Какие taxonomies
            'filters' => [],                  // Фильтры (category, date, author)
            'selection' => 'all',             // 'all' или 'selected'
            'selected_ids' => [],             // Если selection = 'selected'
            'overwrite' => false,             // Перезаписывать существующие
            'sync_meta' => true,              // Синхронизировать meta fields
            'sync_taxonomies' => true,        // Синхронизировать термины
            'sync_media' => true,             // Синхронизировать медиа файлы
            'sync_author' => false,           // Синхронизировать автора
            'batch_size' => 50,
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        // Создать job
        $job_id = $this->create_sync_job([
            'connection_id' => $connection_id,
            'direction' => 'pull',
            'options' => $options
        ]);
        
        // Запустить background процесс
        $this->start_sync_process($job_id);
        
        return $job_id;
    }
    
    /**
     * Push контент на удаленный сайт
     * 
     * @param int $connection_id ID подключения
     * @param array $options Опции синхронизации
     * @return int Job ID
     */
    public function push_content($connection_id, $options) {
        $connection = $this->get_connection($connection_id);
        
        // Аналогичные опции как в pull
        $options = wp_parse_args($options, [/* defaults */]);
        
        // Создать job
        $job_id = $this->create_sync_job([
            'connection_id' => $connection_id,
            'direction' => 'push',
            'options' => $options
        ]);
        
        // Запустить background процесс
        $this->start_sync_process($job_id);
        
        return $job_id;
    }
    
    /**
     * Синхронизировать посты
     */
    protected function sync_posts($job_id, $direction, $connection, $options) {
        if ($direction === 'pull') {
            return $this->pull_posts($connection, $options);
        } else {
            return $this->push_posts($connection, $options);
        }
    }
    
    /**
     * Pull посты с удаленного сайта
     */
    protected function pull_posts($connection, $options) {
        // Запрос к удаленному API
        $response = wp_remote_post($connection->remote_url . '/wp-json/aie/v1/site-sync/export', [
            'headers' => [
                'X-AIE-API-Key' => $connection->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'content_type' => 'posts',
                'post_types' => $options['post_types'],
                'filters' => $options['filters'],
                'selection' => $options['selection'],
                'selected_ids' => $options['selected_ids'],
                'include_meta' => $options['sync_meta'],
                'include_taxonomies' => $options['sync_taxonomies'],
                'include_media' => $options['sync_media']
            ]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Импортировать полученные данные
        return $this->import_posts($data['posts'], $options);
    }
    
    /**
     * Push посты на удаленный сайт
     */
    protected function push_posts($connection, $options) {
        // Получить локальные посты
        $posts = $this->get_local_posts($options);
        
        // Отправить на удаленный сайт
        $response = wp_remote_post($connection->remote_url . '/wp-json/aie/v1/site-sync/import', [
            'headers' => [
                'X-AIE-API-Key' => $connection->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'content_type' => 'posts',
                'posts' => $posts,
                'options' => [
                    'overwrite' => $options['overwrite'],
                    'sync_meta' => $options['sync_meta'],
                    'sync_taxonomies' => $options['sync_taxonomies'],
                    'sync_media' => $options['sync_media']
                ]
            ]),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    /**
     * Импортировать посты
     */
    protected function import_posts($posts, $options) {
        $results = [
            'total' => count($posts),
            'success' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0
        ];
        
        foreach ($posts as $post_data) {
            // Проверить существование
            $existing = $this->find_existing_post($post_data);
            
            if ($existing && !$options['overwrite']) {
                $results['skipped']++;
                continue;
            }
            
            if ($existing) {
                // Обновить существующий
                $post_data['ID'] = $existing;
                $result = wp_update_post($post_data, true);
                
                if (!is_wp_error($result)) {
                    $results['updated']++;
                }
            } else {
                // Создать новый
                $result = wp_insert_post($post_data, true);
                
                if (!is_wp_error($result)) {
                    $results['success']++;
                }
            }
            
            if (is_wp_error($result)) {
                $results['failed']++;
                continue;
            }
            
            // Синхронизировать мета
            if ($options['sync_meta'] && !empty($post_data['meta'])) {
                $this->sync_post_meta($result, $post_data['meta']);
            }
            
            // Синхронизировать таксономии
            if ($options['sync_taxonomies'] && !empty($post_data['taxonomies'])) {
                $this->sync_post_taxonomies($result, $post_data['taxonomies']);
            }
            
            // Синхронизировать медиа
            if ($options['sync_media'] && !empty($post_data['media'])) {
                $this->sync_post_media($result, $post_data['media'], $connection);
            }
        }
        
        return $results;
    }
    
    /**
     * Синхронизировать медиа файлы
     */
    protected function sync_post_media($post_id, $media_data, $connection) {
        foreach ($media_data as $media) {
            // Проверить существование по hash
            $existing = $this->find_media_by_hash($media['hash']);
            
            if ($existing) {
                // Использовать существующий
                $attachment_id = $existing;
            } else {
                // Скачать с удаленного сайта
                $file_url = $connection->remote_url . $media['url'];
                $attachment_id = $this->download_remote_media($file_url, $media);
            }
            
            // Установить как featured image если нужно
            if ($media['is_featured']) {
                set_post_thumbnail($post_id, $attachment_id);
            }
            
            // Добавить в контент если нужно
            if ($media['in_content']) {
                $this->update_media_urls_in_content($post_id, $media['old_url'], 
                    wp_get_attachment_url($attachment_id));
            }
        }
    }
    
    /**
     * Скачать медиа с удаленного сайта
     */
    protected function download_remote_media($url, $media_data) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        $file_array = [
            'name' => $media_data['filename'],
            'tmp_name' => $tmp
        ];
        
        $attachment_id = media_handle_sideload($file_array, 0);
        
        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
            return $attachment_id;
        }
        
        // Установить meta данные
        if (!empty($media_data['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $media_data['alt']);
        }
        
        return $attachment_id;
    }
}

class Site_Sync_API {
    /**
     * Регистрация REST API endpoints
     */
    public function register_routes() {
        // Верификация подключения
        register_rest_route('aie/v1', '/site-sync/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verify_connection'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        
        // Экспорт контента (для Pull с другого сайта)
        register_rest_route('aie/v1', '/site-sync/export', [
            'methods' => 'POST',
            'callback' => [$this, 'export_content'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        
        // Импорт контента (для Push с другого сайта)
        register_rest_route('aie/v1', '/site-sync/import', [
            'methods' => 'POST',
            'callback' => [$this, 'import_content'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        
        // Получить список доступного контента
        register_rest_route('aie/v1', '/site-sync/list', [
            'methods' => 'POST',
            'callback' => [$this, 'list_content'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
    }
    
    /**
     * Проверить API ключ
     */
    public function check_api_key($request) {
        $api_key = $request->get_header('X-AIE-API-Key');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key is required', ['status' => 401]);
        }
        
        // Проверить в БД
        global $wpdb;
        $connection = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aie_site_connections 
             WHERE api_key = %s AND status = 'active'",
            $api_key
        ));
        
        if (!$connection) {
            return new WP_Error('invalid_api_key', 'Invalid API key', ['status' => 403]);
        }
        
        return true;
    }
    
    /**
     * Верификация подключения
     */
    public function verify_connection($request) {
        return [
            'verified' => true,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => AIE_VERSION,
            'capabilities' => [
                'posts' => true,
                'pages' => true,
                'users' => true,
                'media' => true,
                'taxonomies' => true,
                'comments' => true
            ]
        ];
    }
}
```

#### UI для Site-to-Site Sync

**Страница:** `app/view/admin/content_sync_page.php`

```
┌─────────────────────────────────────────────────────────────────┐
│  Content Sync - Site-to-Site Synchronization                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ═══ Connections ═══                                            │
│                                                                 │
│  [+ New Connection]                                             │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ 📡 Production Site                           [Active]    │  │
│  │ https://example.com                                      │  │
│  │ Direction: Push & Pull                                   │  │
│  │ Last sync: 2 hours ago                                   │  │
│  │                                                           │  │
│  │ [Pull Content] [Push Content] [Edit] [Delete]           │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ 📡 Staging Site                              [Active]    │  │
│  │ https://staging.example.com                              │  │
│  │ Direction: Pull only                                     │  │
│  │ Last sync: Never                                         │  │
│  │                                                           │  │
│  │ [Pull Content] [Edit] [Delete]                           │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ═══ Recent Syncs ═══                                           │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐  │
│  │ Date      | Site        | Direction | Type  | Status    │  │
│  │───────────┼─────────────┼───────────┼───────┼──────────│  │
│  │ 2h ago    │ Production  │ Pull ↓    │ Posts │ ✓ 25/25  │  │
│  │ 1d ago    │ Production  │ Push ↑    │ Media │ ✓ 12/12  │  │
│  │ 3d ago    │ Staging     │ Pull ↓    │ Posts │ ⚠ 18/20  │  │
│  └─────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Modal: New Connection**
```
┌─────────────────────────────────────────────┐
│  Create New Connection               [✕]   │
├─────────────────────────────────────────────┤
│                                             │
│  Connection Name:                           │
│  [Production Site________________]          │
│                                             │
│  Remote Site URL:                           │
│  [https://example.com____________]          │
│                                             │
│  API Key (from remote site):                │
│  [••••••••••••••••••••••••••••]             │
│                                             │
│  Direction:                                 │
│  ○ Pull only (← Import from remote)         │
│  ○ Push only (→ Export to remote)           │
│  ● Bidirectional (↔ Both ways)              │
│                                             │
│  [Test Connection]                          │
│  ✓ Connection verified successfully         │
│                                             │
│  [Cancel] [Save Connection]                 │
│                                             │
└─────────────────────────────────────────────┘
```

**Modal: Pull/Push Content**
```
┌─────────────────────────────────────────────────────────┐
│  Pull Content from Production Site              [✕]    │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Select Content Types:                                  │
│  ☑ Posts        ☑ Pages        ☐ Users                 │
│  ☑ Media Files  ☑ Taxonomies   ☐ Comments              │
│                                                         │
│  ═══ Posts Options ═══                                  │
│  Post Types:                                            │
│  ☑ Posts   ☐ Products   ☐ Projects                     │
│                                                         │
│  Selection:                                             │
│  ● All posts                                            │
│  ○ Filtered (by category, date, author)                │
│  ○ Selected (choose specific posts)                    │
│                                                         │
│  Filters: (if Filtered selected)                       │
│  Categories: [All ▼]                                    │
│  Date Range: [Last 30 days ▼]                          │
│  Author: [All authors ▼]                                │
│                                                         │
│  ═══ Sync Options ═══                                   │
│  ☑ Sync meta fields                                     │
│  ☑ Sync taxonomies (categories, tags)                  │
│  ☑ Sync featured images                                │
│  ☑ Sync media in content                                │
│  ☐ Sync authors (create if not exists)                 │
│                                                         │
│  Existing Content:                                      │
│  ○ Skip if exists                                       │
│  ● Update if exists                                     │
│  ○ Create duplicate                                     │
│                                                         │
│  [Preview Selection] [Start Sync]                      │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Progress Modal:**
```
┌─────────────────────────────────────────────┐
│  Syncing Content...                    [✕] │
├─────────────────────────────────────────────┤
│                                             │
│  Connection: Production Site                │
│  Direction: Pull ↓                          │
│                                             │
│  Progress: 18 / 25 posts (72%)              │
│  ████████████████░░░░░░░░                   │
│                                             │
│  Current: "How to optimize WordPress"       │
│                                             │
│  ✓ Created: 12                              │
│  ↻ Updated: 6                               │
│  ⊘ Skipped: 0                               │
│  ✗ Failed: 0                                │
│                                             │
│  Media: 5 / 8 files downloaded              │
│                                             │
│  Time: 00:01:15 / ~00:01:45                 │
│                                             │
│  [Pause] [Cancel]                           │
│                                             │
└─────────────────────────────────────────────┘
```

#### JavaScript модуль

`src/js/modules/content_sync.js`:
```javascript
class ContentSync {
    constructor() {
        this.currentJobId = null;
        this.progressInterval = null;
    }
    
    /**
     * Создать новое подключение
     */
    async createConnection(data) {
        const response = await fetch(aieAdmin.ajaxUrl, {
            method: 'POST',
            body: new FormData({
                action: 'aie_create_site_connection',
                nonce: aieAdmin.nonce,
                name: data.name,
                remote_url: data.remote_url,
                api_key: data.api_key,
                direction: data.direction
            })
        });
        
        return await response.json();
    }
    
    /**
     * Тестировать подключение
     */
    async testConnection(remote_url, api_key) {
        const response = await fetch(aieAdmin.ajaxUrl, {
            method: 'POST',
            body: new FormData({
                action: 'aie_test_site_connection',
                nonce: aieAdmin.nonce,
                remote_url: remote_url,
                api_key: api_key
            })
        });
        
        return await response.json();
    }
    
    /**
     * Получить список контента для preview
     */
    async previewContent(connection_id, options) {
        const response = await fetch(aieAdmin.ajaxUrl, {
            method: 'POST',
            body: new FormData({
                action: 'aie_preview_sync_content',
                nonce: aieAdmin.nonce,
                connection_id: connection_id,
                options: JSON.stringify(options)
            })
        });
        
        return await response.json();
    }
    
    /**
     * Начать Pull синхронизацию
     */
    async startPull(connection_id, options) {
        const response = await fetch(aieAdmin.ajaxUrl, {
            method: 'POST',
            body: new FormData({
                action: 'aie_start_content_pull',
                nonce: aieAdmin.nonce,
                connection_id: connection_id,
                options: JSON.stringify(options)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.currentJobId = data.job_id;
            this.startProgressTracking();
            this.showProgressModal('pull');
        }
        
        return data;
    }
    
    /**
     * Начать Push синхронизацию
     */
    async startPush(connection_id, options) {
        // Аналогично startPull
    }
    
    /**
     * Отслеживать прогресс
     */
    async updateProgress() {
        const response = await fetch(aieAdmin.ajaxUrl, {
            method: 'POST',
            body: new FormData({
                action: 'aie_get_sync_progress',
                nonce: aieAdmin.nonce,
                job_id: this.currentJobId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.renderProgress(data.progress);
            
            if (data.progress.status === 'completed') {
                this.stopProgressTracking();
                this.showResults(data.progress);
            }
        }
    }
}
```

#### Database Schema Update

```sql
-- Подключения между сайтами
CREATE TABLE {prefix}_aie_site_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    remote_url VARCHAR(500) NOT NULL,
    api_key VARCHAR(100) NOT NULL UNIQUE,
    direction ENUM('pull', 'push', 'bidirectional') DEFAULT 'bidirectional',
    status ENUM('active', 'inactive', 'error') DEFAULT 'active',
    last_sync_at DATETIME,
    last_error TEXT,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX remote_url_idx (remote_url(255)),
    INDEX status_idx (status),
    INDEX created_by_idx (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- История синхронизации
CREATE TABLE {prefix}_aie_content_sync (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT(20) UNSIGNED NOT NULL,
    connection_id BIGINT(20) UNSIGNED NOT NULL,
    direction ENUM('pull', 'push') NOT NULL,
    content_type VARCHAR(50) NOT NULL, -- 'posts', 'users', 'media', 'terms'
    local_id BIGINT(20),               -- ID в локальной БД
    remote_id BIGINT(20),              -- ID на удаленном сайте
    action ENUM('created', 'updated', 'skipped', 'failed') NOT NULL,
    error_message TEXT,
    created_at DATETIME NOT NULL,
    INDEX job_id_idx (job_id),
    INDEX connection_id_idx (connection_id),
    INDEX content_type_idx (content_type),
    INDEX local_id_idx (local_id),
    INDEX remote_id_idx (remote_id),
    FOREIGN KEY (job_id) REFERENCES {$wpdb->prefix}aie_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (connection_id) REFERENCES {$wpdb->prefix}aie_site_connections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API ключи (для входящих подключений)
CREATE TABLE {prefix}_aie_api_keys (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    api_key VARCHAR(100) NOT NULL UNIQUE,
    permissions TEXT,                   -- JSON с разрешениями
    allowed_ips TEXT,                   -- JSON с разрешенными IP
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_used_at DATETIME,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Фильтры и хуки

```php
// Перед синхронизацией
do_action('aie_before_content_sync', $job_id, $direction, $connection_id);

// Перед обработкой элемента
do_action('aie_before_sync_item', $item_data, $content_type, $direction);

// После успешной синхронизации элемента
do_action('aie_after_sync_item', $local_id, $remote_id, $content_type, $action);

// При пропуске элемента
do_action('aie_sync_item_skipped', $item_data, $reason);

// При ошибке
do_action('aie_sync_item_error', $item_data, $error);

// После завершения синхронизации
do_action('aie_after_content_sync', $job_id, $stats);

// Фильтры для модификации данных
apply_filters('aie_sync_post_data', $post_data, $direction);
apply_filters('aie_sync_user_data', $user_data, $direction);
apply_filters('aie_sync_term_data', $term_data, $direction);
apply_filters('aie_sync_media_data', $media_data, $direction);

// Фильтр для кастомной логики определения существующего контента
apply_filters('aie_find_existing_content', $existing_id, $item_data, $content_type);

// Фильтр для разрешений API ключа
apply_filters('aie_api_key_permissions', $permissions, $api_key);
```

---

## 10. Import System - Расширенная система импорта

Многошаговый визард импорта с drag-and-drop, расширенным маппингом полей и поддержкой всех типов контента WordPress.

### 10.1 Import_Wizard_Controller - Контроллер импорта

**Файл**: `app/controller/import_wizard_controller.php`

```php
<?php
namespace AIE\Controller;

class Import_Wizard_Controller {
    
    protected $file_uploader;
    protected $column_parser;
    protected $field_mapper;
    protected $importer_factory;
    protected $duplicate_handler;
    
    public function __construct() {
        $this->file_uploader = new File_Uploader();
        $this->column_parser = new Column_Parser();
        $this->field_mapper = new Field_Mapper();
        $this->importer_factory = new Importer_Factory();
        $this->duplicate_handler = new Duplicate_Handler();
    }
    
    /**
     * Step 1: File Upload & Format Detection
     * Загрузка файла и определение формата
     */
    public function process_step_1($file) {
        // Валидация файла
        $validation = $this->file_uploader->validate($file);
        if (!$validation['valid']) {
            return new \WP_Error('invalid_file', $validation['message']);
        }
        
        // Определение формата (CSV, JSON, XLS, XLSX)
        $format = $this->detect_format($file);
        
        // Сохранение файла
        $file_path = $this->file_uploader->save($file);
        
        // Предварительное чтение данных (first 3 rows)
        $preview_data = $this->get_preview_data($file_path, $format);
        
        // Сохранение в сессию
        $this->save_session_data('step_1', [
            'file_path' => $file_path,
            'format' => $format,
            'preview' => $preview_data,
            'total_rows' => $this->count_rows($file_path, $format)
        ]);
        
        return [
            'success' => true,
            'file_path' => $file_path,
            'format' => $format,
            'preview' => $preview_data,
            'total_rows' => $this->count_rows($file_path, $format)
        ];
    }
    
    /**
     * Step 2: Content Type Selection
     * Выбор типа контента для импорта
     */
    public function process_step_2($content_type, $sub_type = null) {
        // Валидация типа контента
        $allowed_types = $this->get_allowed_content_types();
        if (!in_array($content_type, $allowed_types)) {
            return new \WP_Error('invalid_content_type', 'Invalid content type');
        }
        
        // Для Custom MySQL Table - валидация таблицы
        if ($content_type === 'custom_table') {
            global $wpdb;
            if (!$this->table_exists($sub_type)) {
                return new \WP_Error('table_not_found', 'Table does not exist');
            }
        }
        
        // Получение доступных полей для выбранного типа
        $available_fields = $this->get_available_fields($content_type, $sub_type);
        
        // Сохранение в сессию
        $this->save_session_data('step_2', [
            'content_type' => $content_type,
            'sub_type' => $sub_type,
            'available_fields' => $available_fields
        ]);
        
        return [
            'success' => true,
            'available_fields' => $available_fields
        ];
    }
    
    /**
     * Step 3: Column Selection (Drag & Drop)
     * Выбор колонок для импорта
     */
    public function process_step_3($selected_columns) {
        $file_data = $this->get_session_data('step_1');
        
        // Валидация выбранных колонок
        $all_columns = $this->column_parser->parse($file_data['file_path'], $file_data['format']);
        foreach ($selected_columns as $column) {
            if (!in_array($column, $all_columns)) {
                return new \WP_Error('invalid_column', "Column {$column} not found");
            }
        }
        
        // Предпросмотр выбранных колонок
        $preview = $this->get_columns_preview($file_data['file_path'], $selected_columns, $file_data['format']);
        
        // Сохранение в сессию
        $this->save_session_data('step_3', [
            'selected_columns' => $selected_columns,
            'preview' => $preview
        ]);
        
        return [
            'success' => true,
            'preview' => $preview
        ];
    }
    
    /**
     * Step 4: Field Mapping
     * Маппинг полей и настройки для каждого поля
     */
    public function process_step_4($field_mapping, $field_settings = []) {
        $content_type_data = $this->get_session_data('step_2');
        $column_data = $this->get_session_data('step_3');
        
        // Валидация маппинга
        foreach ($field_mapping as $column => $field) {
            if (!in_array($column, $column_data['selected_columns'])) {
                return new \WP_Error('invalid_mapping', "Column {$column} not in selected columns");
            }
            
            if (!in_array($field, $content_type_data['available_fields'])) {
                return new \WP_Error('invalid_field', "Field {$field} not available for this content type");
            }
        }
        
        // Обработка настроек полей (search/replace, functions, transformations)
        $processed_settings = $this->process_field_settings($field_settings);
        
        // Предпросмотр трансформаций
        $preview = $this->preview_transformations($field_mapping, $processed_settings);
        
        // Сохранение в сессию
        $this->save_session_data('step_4', [
            'field_mapping' => $field_mapping,
            'field_settings' => $processed_settings,
            'preview' => $preview
        ]);
        
        return [
            'success' => true,
            'preview' => $preview
        ];
    }
    
    /**
     * Step 5: Import Options & Duplicate Handling
     * Настройки импорта и обработка дубликатов
     */
    public function process_step_5($options) {
        // Валидация опций
        $default_options = [
            'duplicate_check' => ['post_title'], // or 'post_id', 'custom_field'
            'duplicate_action' => 'skip', // or 'update', 'delete_recreate', 'create_duplicate'
            'update_strategy' => 'update_mapped', // or 'replace_all', 'dont_update_if_value'
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'post_date_source' => 'import_date', // or 'current_date', 'from_column'
            'background_processing' => true,
            'batch_size' => 50,
            'email_notification' => false,
            'create_log' => true,
        ];
        
        $options = wp_parse_args($options, $default_options);
        
        // Сохранение в сессию
        $this->save_session_data('step_5', [
            'options' => $options
        ]);
        
        // Подготовка статистики для отображения
        $file_data = $this->get_session_data('step_1');
        $mapping_data = $this->get_session_data('step_4');
        
        $summary = [
            'total_rows' => $file_data['total_rows'],
            'columns_mapped' => count($mapping_data['field_mapping']),
            'estimated_time' => $this->estimate_import_time($file_data['total_rows'], $options['batch_size']),
        ];
        
        return [
            'success' => true,
            'summary' => $summary
        ];
    }
    
    /**
     * Step 6: Start Import
     * Запуск процесса импорта
     */
    public function start_import() {
        // Получение всех данных из сессии
        $file_data = $this->get_session_data('step_1');
        $content_type_data = $this->get_session_data('step_2');
        $column_data = $this->get_session_data('step_3');
        $mapping_data = $this->get_session_data('step_4');
        $options = $this->get_session_data('step_5');
        
        // Создание задачи импорта
        $job = $this->create_import_job([
            'file_path' => $file_data['file_path'],
            'format' => $file_data['format'],
            'content_type' => $content_type_data['content_type'],
            'sub_type' => $content_type_data['sub_type'],
            'selected_columns' => $column_data['selected_columns'],
            'field_mapping' => $mapping_data['field_mapping'],
            'field_settings' => $mapping_data['field_settings'],
            'options' => $options['options'],
            'total_rows' => $file_data['total_rows'],
        ]);
        
        if (is_wp_error($job)) {
            return $job;
        }
        
        // Запуск фонового процесса или синхронная обработка
        if ($options['options']['background_processing']) {
            $this->schedule_background_import($job['job_id']);
        } else {
            $this->process_import_sync($job['job_id']);
        }
        
        // Очистка сессии
        $this->clear_session_data();
        
        return [
            'success' => true,
            'job_id' => $job['job_id'],
            'redirect_url' => admin_url('admin.php?page=aie-import-progress&job_id=' . $job['job_id'])
        ];
    }
    
    /**
     * Get allowed content types
     */
    protected function get_allowed_content_types() {
        $types = [
            // WordPress Core
            'post',
            'page',
            'user',
            'comment',
            'taxonomy',
            'menu',
            'nav_menu_item',
            'attachment',
            
            // Custom Post Types (динамически)
            ...get_post_types(['public' => true, '_builtin' => false]),
            
            // WooCommerce
            'product',
            'product_variation',
            'shop_order',
            'shop_coupon',
            'product_attribute',
            
            // Custom Table
            'custom_table'
        ];
        
        return apply_filters('aie_import_content_types', $types);
    }
    
    /**
     * Get available fields for content type
     */
    protected function get_available_fields($content_type, $sub_type = null) {
        $fields = [];
        
        switch ($content_type) {
            case 'post':
            case 'page':
                $fields = [
                    // Core fields
                    'post_title',
                    'post_content',
                    'post_excerpt',
                    'post_date',
                    'post_status',
                    'post_author',
                    'post_parent',
                    'post_slug',
                    'post_password',
                    'menu_order',
                    'comment_status',
                    'ping_status',
                    'post_format',
                    
                    // Media
                    'featured_image',
                    'gallery_images',
                    
                    // Taxonomies
                    'categories',
                    'tags',
                    ...get_object_taxonomies($content_type),
                    
                    // Custom Fields
                    '_custom_field_*', // Динамические
                ];
                
                // ACF Fields (если установлен)
                if (function_exists('acf_get_field_groups')) {
                    $fields = array_merge($fields, $this->get_acf_fields($content_type));
                }
                break;
                
            case 'user':
                $fields = [
                    'user_login',
                    'user_email',
                    'user_pass',
                    'user_nicename',
                    'user_url',
                    'display_name',
                    'first_name',
                    'last_name',
                    'description',
                    'role',
                    '_user_meta_*', // Динамические
                ];
                break;
                
            case 'product':
                $fields = [
                    // WooCommerce Product fields
                    'post_title',
                    'post_content',
                    'post_excerpt',
                    '_sku',
                    '_regular_price',
                    '_sale_price',
                    '_price',
                    '_stock',
                    '_stock_status',
                    '_manage_stock',
                    '_backorders',
                    '_weight',
                    '_length',
                    '_width',
                    '_height',
                    '_tax_status',
                    '_tax_class',
                    '_featured',
                    '_virtual',
                    '_downloadable',
                    'product_cat',
                    'product_tag',
                    'product_attributes',
                    'featured_image',
                    'gallery_images',
                ];
                
                if (function_exists('acf_get_field_groups')) {
                    $fields = array_merge($fields, $this->get_acf_fields('product'));
                }
                break;
                
            case 'custom_table':
                // Получение колонок из таблицы
                global $wpdb;
                $columns = $wpdb->get_col("DESCRIBE {$sub_type}");
                $fields = $columns;
                break;
        }
        
        return apply_filters('aie_import_available_fields', $fields, $content_type, $sub_type);
    }
    
    /**
     * Get ACF fields for content type
     */
    protected function get_acf_fields($content_type) {
        $fields = [];
        
        if (!function_exists('acf_get_field_groups')) {
            return $fields;
        }
        
        $field_groups = acf_get_field_groups(['post_type' => $content_type]);
        
        foreach ($field_groups as $group) {
            $group_fields = acf_get_fields($group['key']);
            foreach ($group_fields as $field) {
                $fields[] = $field['name'];
                
                // Для Repeater добавляем подполя
                if ($field['type'] === 'repeater' && !empty($field['sub_fields'])) {
                    foreach ($field['sub_fields'] as $sub_field) {
                        $fields[] = $field['name'] . '_' . $sub_field['name'];
                    }
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Process field settings (search/replace, functions, transformations)
     */
    protected function process_field_settings($settings) {
        $processed = [];
        
        foreach ($settings as $column => $column_settings) {
            $processed[$column] = [];
            
            // Default value
            if (!empty($column_settings['default_value'])) {
                $processed[$column]['default_value'] = sanitize_text_field($column_settings['default_value']);
            }
            
            // Search & Replace rules
            if (!empty($column_settings['search_replace'])) {
                $processed[$column]['search_replace'] = array_map(function($rule) {
                    return [
                        'search' => $rule['search'],
                        'replace' => $rule['replace']
                    ];
                }, $column_settings['search_replace']);
            }
            
            // Custom function
            if (!empty($column_settings['function_id'])) {
                $processed[$column]['function_id'] = absint($column_settings['function_id']);
            }
            
            // Data transformations
            if (!empty($column_settings['transformations'])) {
                $processed[$column]['transformations'] = array_map('sanitize_text_field', $column_settings['transformations']);
            }
        }
        
        return $processed;
    }
    
    /**
     * Create import job in database
     */
    protected function create_import_job($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'aie_jobs';
        
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => get_current_user_id(),
                'type' => 'import',
                'data_type' => $data['content_type'],
                'file_format' => $data['format'],
                'status' => 'pending',
                'total_items' => $data['total_rows'],
                'processed_items' => 0,
                'success_items' => 0,
                'failed_items' => 0,
                'file_path' => $data['file_path'],
                'settings' => json_encode([
                    'sub_type' => $data['sub_type'],
                    'selected_columns' => $data['selected_columns'],
                    'field_mapping' => $data['field_mapping'],
                    'field_settings' => $data['field_settings'],
                    'options' => $data['options'],
                ]),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', $wpdb->last_error);
        }
        
        return [
            'job_id' => $wpdb->insert_id
        ];
    }
    
    /**
     * Schedule background import
     */
    protected function schedule_background_import($job_id) {
        $queue_manager = Queue_Manager::get_instance();
        $queue_manager->push_to_queue([
            'type' => 'import',
            'job_id' => $job_id,
        ]);
        $queue_manager->save()->dispatch();
    }
    
    /**
     * Detect file format
     */
    protected function detect_format($file) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $format_map = [
            'csv' => 'csv',
            'json' => 'json',
            'xls' => 'xls',
            'xlsx' => 'xlsx',
        ];
        
        return $format_map[$extension] ?? 'csv';
    }
    
    /**
     * Session management
     */
    protected function save_session_data($step, $data) {
        if (!isset($_SESSION['aie_import_wizard'])) {
            $_SESSION['aie_import_wizard'] = [];
        }
        $_SESSION['aie_import_wizard'][$step] = $data;
    }
    
    protected function get_session_data($step) {
        return $_SESSION['aie_import_wizard'][$step] ?? null;
    }
    
    protected function clear_session_data() {
        unset($_SESSION['aie_import_wizard']);
    }
}
```

### 10.2 Content Type Importers

Отдельные классы-импортеры для каждого типа контента.

#### Post_Importer - Импорт постов/страниц

**Файл**: `app/importer/post_importer.php`

```php
<?php
namespace AIE\Importer;

class Post_Importer extends Base_Importer {
    
    /**
     * Import single post
     */
    public function import_item($data, $mapping, $settings, $options) {
        // Маппинг данных
        $post_data = $this->map_post_data($data, $mapping);
        
        // Применение настроек полей (search/replace, functions)
        $post_data = $this->apply_field_settings($post_data, $settings);
        
        // Проверка дубликата
        $existing_id = $this->check_duplicate($post_data, $options['duplicate_check']);
        
        if ($existing_id && $options['duplicate_action'] === 'skip') {
            return [
                'status' => 'skipped',
                'message' => 'Post already exists',
                'existing_id' => $existing_id
            ];
        }
        
        if ($existing_id && $options['duplicate_action'] === 'update') {
            $post_data['ID'] = $existing_id;
            $result = $this->update_post($post_data, $options['update_strategy']);
        } elseif ($existing_id && $options['duplicate_action'] === 'delete_recreate') {
            wp_delete_post($existing_id, true);
            $result = $this->create_post($post_data);
        } else {
            $result = $this->create_post($post_data);
        }
        
        if (is_wp_error($result)) {
            return [
                'status' => 'failed',
                'message' => $result->get_error_message()
            ];
        }
        
        // Импорт featured image (если URL)
        if (!empty($post_data['featured_image'])) {
            $this->import_featured_image($result, $post_data['featured_image'], $options);
        }
        
        // Импорт taxonomies
        if (!empty($post_data['categories'])) {
            $this->import_categories($result, $post_data['categories']);
        }
        if (!empty($post_data['tags'])) {
            $this->import_tags($result, $post_data['tags']);
        }
        
        // Импорт custom fields
        if (!empty($post_data['meta'])) {
            $this->import_meta($result, $post_data['meta']);
        }
        
        // Импорт ACF fields
        if (!empty($post_data['acf'])) {
            $this->import_acf_fields($result, $post_data['acf']);
        }
        
        return [
            'status' => 'success',
            'post_id' => $result,
            'action' => $existing_id ? 'updated' : 'created'
        ];
    }
    
    /**
     * Map import data to post data
     */
    protected function map_post_data($data, $mapping) {
        $post_data = [
            'post_type' => 'post',
            'post_status' => 'publish',
        ];
        
        $meta = [];
        $acf = [];
        $categories = [];
        $tags = [];
        $featured_image = null;
        
        foreach ($mapping as $column => $field) {
            $value = $data[$column] ?? '';
            
            // Core WordPress fields
            if (in_array($field, ['post_title', 'post_content', 'post_excerpt', 'post_date', 'post_status', 'post_author', 'post_parent', 'post_slug', 'post_password', 'menu_order', 'comment_status', 'ping_status'])) {
                $post_data[$field] = $value;
            }
            // Featured image
            elseif ($field === 'featured_image') {
                $featured_image = $value;
            }
            // Categories
            elseif ($field === 'categories') {
                $categories = array_map('trim', explode(',', $value));
            }
            // Tags
            elseif ($field === 'tags') {
                $tags = array_map('trim', explode(',', $value));
            }
            // ACF fields
            elseif (strpos($field, 'acf_') === 0 || $this->is_acf_field($field)) {
                $acf[$field] = $value;
            }
            // Custom fields (meta)
            else {
                $meta[$field] = $value;
            }
        }
        
        $post_data['meta'] = $meta;
        $post_data['acf'] = $acf;
        $post_data['categories'] = $categories;
        $post_data['tags'] = $tags;
        $post_data['featured_image'] = $featured_image;
        
        return $post_data;
    }
    
    /**
     * Import featured image from URL
     */
    protected function import_featured_image($post_id, $image_url, $options) {
        // Check if already exists by URL
        if ($options['skip_duplicate_images']) {
            $existing = $this->find_attachment_by_url($image_url);
            if ($existing) {
                set_post_thumbnail($post_id, $existing);
                return $existing;
            }
        }
        
        // Download image
        $image_downloader = new Image_Downloader();
        $attachment_id = $image_downloader->download($image_url, [
            'post_id' => $post_id,
            'alt_text' => get_the_title($post_id),
        ]);
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
        
        return $attachment_id;
    }
    
    /**
     * Import ACF Repeater field
     */
    protected function import_acf_repeater($post_id, $field_key, $rows_data) {
        if (!function_exists('update_field')) {
            return;
        }
        
        $rows = [];
        
        // Parse rows from different formats
        if (is_string($rows_data)) {
            // JSON format
            $rows = json_decode($rows_data, true);
        } elseif (is_array($rows_data)) {
            $rows = $rows_data;
        }
        
        update_field($field_key, $rows, $post_id);
    }
}
```

#### User_Importer - Импорт пользователей

**Файл**: `app/importer/user_importer.php`

```php
<?php
namespace AIE\Importer;

class User_Importer extends Base_Importer {
    
    public function import_item($data, $mapping, $settings, $options) {
        $user_data = $this->map_user_data($data, $mapping);
        
        // Check duplicate by email
        $existing = get_user_by('email', $user_data['user_email']);
        
        if ($existing && $options['duplicate_action'] === 'skip') {
            return ['status' => 'skipped', 'user_id' => $existing->ID];
        }
        
        if ($existing && $options['duplicate_action'] === 'update') {
            $user_data['ID'] = $existing->ID;
            $user_id = wp_update_user($user_data);
        } else {
            $user_id = wp_insert_user($user_data);
        }
        
        if (is_wp_error($user_id)) {
            return ['status' => 'failed', 'message' => $user_id->get_error_message()];
        }
        
        // Import user meta
        if (!empty($user_data['meta'])) {
            foreach ($user_data['meta'] as $meta_key => $meta_value) {
                update_user_meta($user_id, $meta_key, $meta_value);
            }
        }
        
        return ['status' => 'success', 'user_id' => $user_id];
    }
}
```

#### Product_Importer - Импорт WooCommerce товаров

**Файл**: `app/importer/product_importer.php`

```php
<?php
namespace AIE\Importer;

class Product_Importer extends Base_Importer {
    
    public function import_item($data, $mapping, $settings, $options) {
        if (!class_exists('WooCommerce')) {
            return new \WP_Error('woocommerce_not_active', 'WooCommerce is not active');
        }
        
        $product_data = $this->map_product_data($data, $mapping);
        
        // Check duplicate by SKU
        if (!empty($product_data['_sku'])) {
            $existing_id = wc_get_product_id_by_sku($product_data['_sku']);
            
            if ($existing_id && $options['duplicate_action'] === 'skip') {
                return ['status' => 'skipped', 'product_id' => $existing_id];
            }
        }
        
        // Create/Update product
        if ($existing_id && $options['duplicate_action'] === 'update') {
            $product = wc_get_product($existing_id);
        } else {
            $product = new \WC_Product_Simple();
        }
        
        // Set properties
        $product->set_name($product_data['post_title']);
        $product->set_description($product_data['post_content']);
        $product->set_short_description($product_data['post_excerpt']);
        $product->set_status($product_data['post_status']);
        
        // Prices
        if (isset($product_data['_regular_price'])) {
            $product->set_regular_price($product_data['_regular_price']);
        }
        if (isset($product_data['_sale_price'])) {
            $product->set_sale_price($product_data['_sale_price']);
        }
        
        // Stock
        if (isset($product_data['_stock'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($product_data['_stock']);
        }
        
        // SKU
        if (isset($product_data['_sku'])) {
            $product->set_sku($product_data['_sku']);
        }
        
        $product_id = $product->save();
        
        // Categories
        if (!empty($product_data['product_cat'])) {
            $this->import_product_categories($product_id, $product_data['product_cat']);
        }
        
        // Images
        if (!empty($product_data['featured_image'])) {
            $this->import_featured_image($product_id, $product_data['featured_image'], $options);
        }
        
        return ['status' => 'success', 'product_id' => $product_id];
    }
}
```

#### Custom_Table_Importer - Импорт в кастомную таблицу MySQL

**Файл**: `app/importer/custom_table_importer.php`

```php
<?php
namespace AIE\Importer;

class Custom_Table_Importer extends Base_Importer {
    
    protected $table_name;
    
    public function __construct($table_name) {
        $this->table_name = $table_name;
    }
    
    public function import_item($data, $mapping, $settings, $options) {
        global $wpdb;
        
        // Map data to table columns
        $insert_data = [];
        foreach ($mapping as $column => $db_column) {
            $insert_data[$db_column] = $data[$column] ?? '';
        }
        
        // Apply field settings
        $insert_data = $this->apply_field_settings($insert_data, $settings);
        
        // Check duplicate
        if (!empty($options['duplicate_check'])) {
            $where = [];
            foreach ($options['duplicate_check'] as $check_column) {
                if (isset($insert_data[$check_column])) {
                    $where[$check_column] = $insert_data[$check_column];
                }
            }
            
            if (!empty($where)) {
                $existing = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$this->table_name} WHERE " . 
                        implode(' AND ', array_map(function($col) {
                            return "{$col} = %s";
                        }, array_keys($where))),
                        array_values($where)
                    )
                );
                
                if ($existing && $options['duplicate_action'] === 'skip') {
                    return ['status' => 'skipped'];
                }
                
                if ($existing && $options['duplicate_action'] === 'update') {
                    $result = $wpdb->update($this->table_name, $insert_data, $where);
                    return ['status' => $result !== false ? 'success' : 'failed', 'action' => 'updated'];
                }
            }
        }
        
        // Insert new row
        $result = $wpdb->insert($this->table_name, $insert_data);
        
        return [
            'status' => $result !== false ? 'success' : 'failed',
            'action' => 'created',
            'insert_id' => $wpdb->insert_id
        ];
    }
}
```

### 10.3 Duplicate_Handler - Обработка дубликатов

**Файл**: `app/import/duplicate_handler.php`

```php
<?php
namespace AIE\Import;

class Duplicate_Handler {
    
    /**
     * Check if post/item exists
     * 
     * @param array $data Post data
     * @param array $check_methods ['post_title', 'post_id', 'custom_field']
     * @return int|false Post ID if exists, false otherwise
     */
    public function check_duplicate($data, $check_methods, $content_type = 'post') {
        foreach ($check_methods as $method) {
            switch ($method) {
                case 'post_title':
                    $existing = $this->find_by_title($data['post_title'], $content_type);
                    if ($existing) return $existing;
                    break;
                    
                case 'post_id':
                    if (!empty($data['ID']) && get_post($data['ID'])) {
                        return $data['ID'];
                    }
                    break;
                    
                case 'custom_field':
                    // Check by specific custom field (e.g., SKU, external_id)
                    if (!empty($data['_custom_field_key']) && !empty($data['_custom_field_value'])) {
                        $existing = $this->find_by_meta(
                            $data['_custom_field_key'],
                            $data['_custom_field_value'],
                            $content_type
                        );
                        if ($existing) return $existing;
                    }
                    break;
            }
        }
        
        return false;
    }
    
    /**
     * Find post by title
     */
    protected function find_by_title($title, $post_type = 'post') {
        global $wpdb;
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_title = %s 
            AND post_type = %s 
            LIMIT 1",
            $title,
            $post_type
        ));
        
        return $post_id ? (int) $post_id : false;
    }
    
    /**
     * Find post by meta field
     */
    protected function find_by_meta($meta_key, $meta_value, $post_type = 'post') {
        $args = [
            'post_type' => $post_type,
            'meta_query' => [
                [
                    'key' => $meta_key,
                    'value' => $meta_value,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ];
        
        $posts = get_posts($args);
        
        return !empty($posts) ? $posts[0] : false;
    }
}
```

### 10.4 Image_Downloader - Автоматическая загрузка изображений

**Файл**: `app/import/image_downloader.php`

```php
<?php
namespace AIE\Import;

class Image_Downloader {
    
    /**
     * Download image from URL and create attachment
     * 
     * @param string $url Image URL
     * @param array $args Additional arguments
     * @return int|WP_Error Attachment ID or error
     */
    public function download($url, $args = []) {
        $defaults = [
            'post_id' => 0,
            'alt_text' => '',
            'title' => '',
            'description' => '',
            'timeout' => 30,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', 'Invalid image URL');
        }
        
        // Download file
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($url, $args['timeout']);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        // Get filename
        $filename = basename(parse_url($url, PHP_URL_PATH));
        if (empty($filename)) {
            $filename = 'image-' . time() . '.jpg';
        }
        
        // Prepare file array
        $file_array = [
            'name' => $filename,
            'tmp_name' => $tmp
        ];
        
        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, $args['post_id']);
        
        // Clean up temp file
        if (file_exists($tmp)) {
            @unlink($tmp);
        }
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Set alt text and other meta
        if (!empty($args['alt_text'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $args['alt_text']);
        }
        
        if (!empty($args['title'])) {
            wp_update_post([
                'ID' => $attachment_id,
                'post_title' => $args['title']
            ]);
        }
        
        if (!empty($args['description'])) {
            wp_update_post([
                'ID' => $attachment_id,
                'post_content' => $args['description']
            ]);
        }
        
        return $attachment_id;
    }
    
    /**
     * Find attachment by URL
     */
    public function find_by_url($url) {
        global $wpdb;
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_source_url' 
            AND meta_value = %s 
            LIMIT 1",
            $url
        ));
        
        return $attachment_id ? (int) $attachment_id : false;
    }
}
```

### 10.5 Import Progress Tracker

**Файл**: `app/import/progress_tracker.php`

```php
<?php
namespace AIE\Import;

class Progress_Tracker {
    
    protected $job_id;
    
    public function __construct($job_id) {
        $this->job_id = $job_id;
    }
    
    /**
     * Update progress
     */
    public function update($processed, $success, $failed, $status = 'processing') {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'aie_jobs',
            [
                'processed_items' => $processed,
                'success_items' => $success,
                'failed_items' => $failed,
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $this->job_id],
            ['%d', '%d', '%d', '%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Mark as completed
     */
    public function complete() {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'aie_jobs',
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $this->job_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        // Trigger action
        do_action('aie_import_completed', $this->job_id);
    }
    
    /**
     * Mark as failed
     */
    public function fail($error_message) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'aie_jobs',
            [
                'status' => 'failed',
                'completed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $this->job_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        // Log error
        $this->log_error($error_message);
        
        // Trigger action
        do_action('aie_import_failed', $this->job_id, $error_message);
    }
    
    /**
     * Get current progress
     */
    public function get_progress() {
        global $wpdb;
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aie_jobs WHERE id = %d",
            $this->job_id
        ), ARRAY_A);
        
        if (!$job) {
            return false;
        }
        
        $progress_percent = 0;
        if ($job['total_items'] > 0) {
            $progress_percent = ($job['processed_items'] / $job['total_items']) * 100;
        }
        
        return [
            'job_id' => $this->job_id,
            'status' => $job['status'],
            'total' => $job['total_items'],
            'processed' => $job['processed_items'],
            'success' => $job['success_items'],
            'failed' => $job['failed_items'],
            'progress_percent' => round($progress_percent, 2),
        ];
    }
}
```

### 10.6 UI Wireframes

Детальные wireframes уже описаны в **IMPORT_UI_SPECIFICATION.md**.

Основные страницы:
- `app/view/import/import_wizard.php` - Главная страница визарда
- `app/view/import/step_*.php` - Отдельные шаги (1-7)
- `app/view/import/import_progress.php` - Страница прогресса
- `app/view/import/import_history.php` - История импортов

### 10.7 JavaScript Modules

**Файл**: `src/js/modules/import_wizard.js`

```javascript
class ImportWizard {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 7;
        this.data = {};
        
        this.init();
    }
    
    init() {
        this.bindEvents();
    }
    
    bindEvents() {
        // Step navigation
        jQuery('.aie-wizard-next').on('click', () => this.nextStep());
        jQuery('.aie-wizard-prev').on('click', () => this.prevStep());
        
        // File upload
        jQuery('#aie-file-upload').on('change', (e) => this.handleFileUpload(e));
        
        // Drag & Drop
        this.initDragDrop();
    }
    
    nextStep() {
        // Validate current step
        if (!this.validateStep(this.currentStep)) {
            return;
        }
        
        // Save step data
        this.saveStepData(this.currentStep);
        
        // Move to next step
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.renderStep(this.currentStep);
        }
    }
    
    handleFileUpload(e) {
        const file = e.target.files[0];
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'aie_upload_import_file');
        formData.append('nonce', aieImportData.nonce);
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    this.data.file = response.data;
                    this.showPreview(response.data.preview);
                }
            }
        });
    }
    
    initDragDrop() {
        const columnSelector = new ColumnSelector('#aie-column-selector');
        columnSelector.onSelectionChange((selected) => {
            this.data.selectedColumns = selected;
        });
    }
    
    startImport() {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aie_start_import',
                nonce: aieImportData.nonce,
                data: JSON.stringify(this.data)
            },
            success: (response) => {
                if (response.success) {
                    this.trackProgress(response.data.job_id);
                }
            }
        });
    }
    
    trackProgress(jobId) {
        const tracker = new ProgressTracker(jobId);
        tracker.start();
    }
}

// Initialize
jQuery(document).ready(() => {
    new ImportWizard();
});
```

### 10.8 REST API Endpoints

```php
// Import wizard endpoints
register_rest_route('aie/v1', '/import/upload', [
    'methods' => 'POST',
    'callback' => [$import_controller, 'upload_file'],
    'permission_callback' => [$this, 'check_permissions']
]);

register_rest_route('aie/v1', '/import/columns', [
    'methods' => 'GET',
    'callback' => [$import_controller, 'get_columns'],
]);

register_rest_route('aie/v1', '/import/start', [
    'methods' => 'POST',
    'callback' => [$import_controller, 'start_import'],
]);

register_rest_route('aie/v1', '/import/progress/(?P<job_id>\d+)', [
    'methods' => 'GET',
    'callback' => [$import_controller, 'get_progress'],
]);
```

### 10.9 Hooks & Filters

```php
// Before import starts
do_action('aie_before_import', $job_id, $settings);

// Before importing each item
do_action('aie_before_import_item', $item_data, $content_type);

// After successfully importing item
do_action('aie_after_import_item', $post_id, $item_data, $content_type);

// On duplicate found
do_action('aie_import_duplicate_found', $existing_id, $item_data, $action);

// After import completes
do_action('aie_after_import', $job_id, $stats);

// Filters
apply_filters('aie_import_post_data', $post_data, $raw_data, $mapping);
apply_filters('aie_import_field_value', $value, $field, $raw_value);
apply_filters('aie_import_acf_repeater_rows', $rows, $field_key);
apply_filters('aie_import_duplicate_check_methods', $methods);
```

---

## 11. Export System - Система экспорта

5-шаговый визард экспорта с расширенными фильтрами и поддержкой всех типов контента WordPress.

### 11.1 Export_Wizard_Controller - Контроллер экспорта

**Файл**: `app/controller/export_wizard_controller.php`

```php
<?php
namespace AIE\Controller;

class Export_Wizard_Controller {
    
    protected $field_mapper;
    protected $exporter_factory;
    protected $progress_tracker;
    
    public function __construct() {
        $this->field_mapper = new Field_Mapper();
        $this->exporter_factory = new Exporter_Factory();
        $this->progress_tracker = new Export_Progress_Tracker();
    }
    
    /**
     * Step 1: Content Type Selection
     * Выбор типа контента для экспорта
     */
    public function process_step_1($content_type, $sub_type = null) {
        // Валидация типа контента
        $allowed_types = $this->get_allowed_content_types();
        if (!in_array($content_type, $allowed_types)) {
            return new \WP_Error('invalid_content_type', 'Invalid content type');
        }
        
        // Получение доступных полей
        $available_fields = $this->get_available_fields($content_type, $sub_type);
        
        // Оценка количества элементов
        $estimated_count = $this->estimate_items_count($content_type, []);
        
        // Сохранение в сессию
        $this->save_session_data('step_1', [
            'content_type' => $content_type,
            'sub_type' => $sub_type,
            'available_fields' => $available_fields,
            'estimated_count' => $estimated_count
        ]);
        
        return [
            'success' => true,
            'available_fields' => $available_fields,
            'estimated_count' => $estimated_count
        ];
    }
    
    /**
     * Step 2: Filters & Query Builder
     * Применение фильтров для сужения выборки
     */
    public function process_step_2($filters) {
        $content_type_data = $this->get_session_data('step_1');
        
        // Валидация фильтров
        $validated_filters = $this->validate_filters($filters, $content_type_data['content_type']);
        
        if (is_wp_error($validated_filters)) {
            return $validated_filters;
        }
        
        // Оценка количества отфильтрованных элементов
        $filtered_count = $this->estimate_items_count(
            $content_type_data['content_type'],
            $validated_filters
        );
        
        // Preview отфильтрованных элементов (первые 5)
        $preview_items = $this->get_preview_items(
            $content_type_data['content_type'],
            $validated_filters,
            5
        );
        
        // Сохранение в сессию
        $this->save_session_data('step_2', [
            'filters' => $validated_filters,
            'filtered_count' => $filtered_count,
            'preview' => $preview_items
        ]);
        
        return [
            'success' => true,
            'filtered_count' => $filtered_count,
            'preview' => $preview_items
        ];
    }
    
    /**
     * Step 3: Field Selection & Mapping
     * Выбор полей для экспорта и настройка трансформации
     */
    public function process_step_3($selected_fields, $field_settings = []) {
        $content_type_data = $this->get_session_data('step_1');
        
        // Валидация выбранных полей
        foreach ($selected_fields as $field) {
            if (!in_array($field, $content_type_data['available_fields'])) {
                return new \WP_Error('invalid_field', "Field {$field} not available");
            }
        }
        
        // Обработка настроек полей
        $processed_settings = $this->process_field_settings($field_settings);
        
        // Preview трансформаций (первые 3 элемента)
        $filter_data = $this->get_session_data('step_2');
        $preview = $this->preview_field_transformations(
            $content_type_data['content_type'],
            $filter_data['filters'],
            $selected_fields,
            $processed_settings,
            3
        );
        
        // Сохранение в сессию
        $this->save_session_data('step_3', [
            'selected_fields' => $selected_fields,
            'field_settings' => $processed_settings,
            'preview' => $preview
        ]);
        
        return [
            'success' => true,
            'preview' => $preview
        ];
    }
    
    /**
     * Step 4: Export Options & Format
     * Настройка формата экспорта и опций
     */
    public function process_step_4($options) {
        // Валидация опций
        $default_options = [
            'format' => 'csv', // csv, json, xls, xlsx
            'csv_delimiter' => ',',
            'csv_enclosure' => '"',
            'csv_include_bom' => true,
            'csv_include_headers' => true,
            'json_pretty_print' => true,
            'json_include_metadata' => false,
            'filename' => null, // auto-generate
            'processing' => 'background', // background or direct
            'batch_size' => 50,
            'email_notification' => false,
            'email' => get_option('admin_email'),
            'save_template' => false,
            'template_name' => null,
        ];
        
        $options = wp_parse_args($options, $default_options);
        
        // Генерация имени файла если не указано
        if (empty($options['filename'])) {
            $content_data = $this->get_session_data('step_1');
            $options['filename'] = $this->generate_filename(
                $content_data['content_type'],
                $options['format']
            );
        }
        
        // Оценка размера файла и времени
        $filter_data = $this->get_session_data('step_2');
        $estimates = $this->estimate_export(
            $filter_data['filtered_count'],
            $options['format'],
            $options['batch_size']
        );
        
        // Сохранение в сессию
        $this->save_session_data('step_4', [
            'options' => $options,
            'estimates' => $estimates
        ]);
        
        return [
            'success' => true,
            'options' => $options,
            'estimates' => $estimates
        ];
    }
    
    /**
     * Step 5: Start Export
     * Запуск процесса экспорта
     */
    public function start_export() {
        // Получение всех данных из сессии
        $content_data = $this->get_session_data('step_1');
        $filter_data = $this->get_session_data('step_2');
        $field_data = $this->get_session_data('step_3');
        $options_data = $this->get_session_data('step_4');
        
        // Создание задачи экспорта
        $job = $this->create_export_job([
            'content_type' => $content_data['content_type'],
            'sub_type' => $content_data['sub_type'],
            'filters' => $filter_data['filters'],
            'selected_fields' => $field_data['selected_fields'],
            'field_settings' => $field_data['field_settings'],
            'options' => $options_data['options'],
            'total_items' => $filter_data['filtered_count'],
        ]);
        
        if (is_wp_error($job)) {
            return $job;
        }
        
        // Сохранение шаблона если нужно
        if ($options_data['options']['save_template']) {
            $this->save_export_template([
                'name' => $options_data['options']['template_name'],
                'content_type' => $content_data['content_type'],
                'filters' => $filter_data['filters'],
                'fields' => $field_data['selected_fields'],
                'field_settings' => $field_data['field_settings'],
                'options' => $options_data['options'],
            ]);
        }
        
        // Запуск фонового процесса или прямой экспорт
        if ($options_data['options']['processing'] === 'background') {
            $this->schedule_background_export($job['job_id']);
        } else {
            // Прямой экспорт (только для небольших объемов)
            if ($filter_data['filtered_count'] > 1000) {
                return new \WP_Error('too_many_items', 'Direct export limited to 1000 items. Use background processing.');
            }
            $this->process_export_direct($job['job_id']);
        }
        
        // Очистка сессии
        $this->clear_session_data();
        
        return [
            'success' => true,
            'job_id' => $job['job_id'],
            'redirect_url' => admin_url('admin.php?page=aie-export-progress&job_id=' . $job['job_id'])
        ];
    }
    
    /**
     * Get allowed content types
     */
    protected function get_allowed_content_types() {
        $types = [
            // WordPress Core
            'post',
            'page',
            'user',
            'comment',
            'category',
            'post_tag',
            'taxonomy',
            'nav_menu',
            'nav_menu_item',
            
            // Custom Post Types (динамически)
            ...get_post_types(['public' => true, '_builtin' => false]),
            
            // WooCommerce
            'product',
            'product_variation',
            'shop_order',
            'shop_coupon',
        ];
        
        return apply_filters('aie_export_content_types', $types);
    }
    
    /**
     * Get available fields for content type
     */
    protected function get_available_fields($content_type, $sub_type = null) {
        $fields = [];
        
        switch ($content_type) {
            case 'post':
            case 'page':
                $fields = [
                    // Core fields
                    'ID',
                    'post_title',
                    'post_content',
                    'post_excerpt',
                    'post_date',
                    'post_modified',
                    'post_status',
                    'post_author',
                    'post_parent',
                    'post_slug',
                    'post_password',
                    'menu_order',
                    'comment_status',
                    'ping_status',
                    'post_format',
                    
                    // Media
                    'featured_image',
                    'featured_image_url',
                    'gallery_images',
                    
                    // Taxonomies
                    'categories',
                    'tags',
                ];
                
                // ACF Fields
                if (function_exists('acf_get_field_groups')) {
                    $fields = array_merge($fields, $this->get_acf_fields($content_type));
                }
                
                // Yoast SEO
                if (defined('WPSEO_VERSION')) {
                    $fields = array_merge($fields, [
                        'yoast_seo_title',
                        'yoast_meta_description',
                        'yoast_focus_keyword',
                        'yoast_canonical_url',
                        'yoast_robots_noindex',
                        'yoast_robots_nofollow',
                    ]);
                }
                break;
                
            case 'user':
                $fields = [
                    'ID',
                    'user_login',
                    'user_email',
                    'user_nicename',
                    'user_url',
                    'user_registered',
                    'display_name',
                    'first_name',
                    'last_name',
                    'description',
                    'role',
                    'roles',
                ];
                break;
                
            case 'product':
                $fields = [
                    // WooCommerce Product fields
                    'ID',
                    'post_title',
                    'post_content',
                    'post_excerpt',
                    'post_status',
                    '_sku',
                    '_regular_price',
                    '_sale_price',
                    '_price',
                    '_stock',
                    '_stock_status',
                    '_manage_stock',
                    '_backorders',
                    '_sold_individually',
                    '_weight',
                    '_length',
                    '_width',
                    '_height',
                    '_tax_status',
                    '_tax_class',
                    '_featured',
                    '_virtual',
                    '_downloadable',
                    'product_cat',
                    'product_tag',
                    'product_attributes',
                    'featured_image_url',
                    'gallery_images',
                ];
                
                if (function_exists('acf_get_field_groups')) {
                    $fields = array_merge($fields, $this->get_acf_fields('product'));
                }
                break;
                
            case 'comment':
                $fields = [
                    'comment_ID',
                    'comment_post_ID',
                    'comment_author',
                    'comment_author_email',
                    'comment_author_url',
                    'comment_author_IP',
                    'comment_date',
                    'comment_content',
                    'comment_approved',
                    'comment_parent',
                    'user_id',
                ];
                break;
        }
        
        return apply_filters('aie_export_available_fields', $fields, $content_type, $sub_type);
    }
    
    /**
     * Validate filters
     */
    protected function validate_filters($filters, $content_type) {
        $validated = [];
        
        switch ($content_type) {
            case 'post':
            case 'page':
                // Post status
                if (isset($filters['post_status'])) {
                    $validated['post_status'] = array_map('sanitize_text_field', (array) $filters['post_status']);
                }
                
                // Date range
                if (isset($filters['date_from'])) {
                    $validated['date_from'] = sanitize_text_field($filters['date_from']);
                }
                if (isset($filters['date_to'])) {
                    $validated['date_to'] = sanitize_text_field($filters['date_to']);
                }
                
                // Author
                if (isset($filters['author'])) {
                    $validated['author'] = array_map('absint', (array) $filters['author']);
                }
                
                // Categories
                if (isset($filters['category'])) {
                    $validated['category'] = array_map('absint', (array) $filters['category']);
                }
                
                // Tags
                if (isset($filters['tags'])) {
                    $validated['tags'] = array_map('absint', (array) $filters['tags']);
                }
                
                // Meta queries
                if (isset($filters['meta_query'])) {
                    $validated['meta_query'] = $this->validate_meta_query($filters['meta_query']);
                }
                
                // Post IDs
                if (isset($filters['post__in'])) {
                    $validated['post__in'] = array_map('absint', (array) $filters['post__in']);
                }
                break;
                
            case 'product':
                // Product type
                if (isset($filters['product_type'])) {
                    $validated['product_type'] = array_map('sanitize_text_field', (array) $filters['product_type']);
                }
                
                // Stock status
                if (isset($filters['stock_status'])) {
                    $validated['stock_status'] = array_map('sanitize_text_field', (array) $filters['stock_status']);
                }
                
                // Price range
                if (isset($filters['price_min'])) {
                    $validated['price_min'] = floatval($filters['price_min']);
                }
                if (isset($filters['price_max'])) {
                    $validated['price_max'] = floatval($filters['price_max']);
                }
                
                // Product categories
                if (isset($filters['product_cat'])) {
                    $validated['product_cat'] = array_map('absint', (array) $filters['product_cat']);
                }
                break;
                
            case 'user':
                // User roles
                if (isset($filters['role'])) {
                    $validated['role'] = array_map('sanitize_text_field', (array) $filters['role']);
                }
                
                // Registration date
                if (isset($filters['date_from'])) {
                    $validated['date_from'] = sanitize_text_field($filters['date_from']);
                }
                if (isset($filters['date_to'])) {
                    $validated['date_to'] = sanitize_text_field($filters['date_to']);
                }
                break;
        }
        
        return $validated;
    }
    
    /**
     * Estimate items count
     */
    protected function estimate_items_count($content_type, $filters) {
        $exporter = $this->exporter_factory->create($content_type);
        return $exporter->count($filters);
    }
    
    /**
     * Generate filename
     */
    protected function generate_filename($content_type, $format) {
        $date = date('Y-m-d');
        $time = date('His');
        return sanitize_file_name("{$content_type}-export-{$date}-{$time}.{$format}");
    }
    
    /**
     * Create export job
     */
    protected function create_export_job($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'aie_jobs';
        
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => get_current_user_id(),
                'type' => 'export',
                'data_type' => $data['content_type'],
                'file_format' => $data['options']['format'],
                'status' => 'pending',
                'total_items' => $data['total_items'],
                'processed_items' => 0,
                'success_items' => 0,
                'failed_items' => 0,
                'file_path' => null,
                'settings' => json_encode([
                    'sub_type' => $data['sub_type'],
                    'filters' => $data['filters'],
                    'selected_fields' => $data['selected_fields'],
                    'field_settings' => $data['field_settings'],
                    'options' => $data['options'],
                ]),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', $wpdb->last_error);
        }
        
        return [
            'job_id' => $wpdb->insert_id
        ];
    }
    
    /**
     * Schedule background export
     */
    protected function schedule_background_export($job_id) {
        $queue_manager = Queue_Manager::get_instance();
        $queue_manager->push_to_queue([
            'type' => 'export',
            'job_id' => $job_id,
        ]);
        $queue_manager->save()->dispatch();
    }
    
    /**
     * Session management
     */
    protected function save_session_data($step, $data) {
        if (!isset($_SESSION['aie_export_wizard'])) {
            $_SESSION['aie_export_wizard'] = [];
        }
        $_SESSION['aie_export_wizard'][$step] = $data;
    }
    
    protected function get_session_data($step) {
        return $_SESSION['aie_export_wizard'][$step] ?? null;
    }
    
    protected function clear_session_data() {
        unset($_SESSION['aie_export_wizard']);
    }
}
```

### 11.2 Content Type Exporters

Отдельные классы-экспортеры для каждого типа контента.

#### Post_Exporter - Экспорт постов/страниц

**Файл**: `app/exporter/post_exporter.php`

```php
<?php
namespace AIE\Exporter;

class Post_Exporter extends Base_Exporter {
    
    /**
     * Count posts matching filters
     */
    public function count($filters = []) {
        $args = $this->build_query_args($filters);
        $args['fields'] = 'ids';
        $args['no_found_rows'] = false;
        
        $query = new \WP_Query($args);
        return $query->found_posts;
    }
    
    /**
     * Export posts
     */
    public function export($filters, $fields, $field_settings, $format_options) {
        $batch_size = $format_options['batch_size'] ?? 50;
        $total = $this->count($filters);
        
        $exported = 0;
        $offset = 0;
        
        while ($offset < $total) {
            $posts = $this->get_posts_batch($filters, $offset, $batch_size);
            
            foreach ($posts as $post) {
                $data = $this->map_post_data($post, $fields, $field_settings);
                yield $data;
                $exported++;
            }
            
            $offset += $batch_size;
        }
    }
    
    /**
     * Build WP_Query args from filters
     */
    protected function build_query_args($filters) {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ];
        
        // Post status
        if (!empty($filters['post_status'])) {
            $args['post_status'] = $filters['post_status'];
        }
        
        // Date range
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $args['date_query'] = [];
            if (!empty($filters['date_from'])) {
                $args['date_query']['after'] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $args['date_query']['before'] = $filters['date_to'];
            }
        }
        
        // Author
        if (!empty($filters['author'])) {
            $args['author__in'] = $filters['author'];
        }
        
        // Categories
        if (!empty($filters['category'])) {
            $args['cat'] = implode(',', $filters['category']);
        }
        
        // Tags
        if (!empty($filters['tags'])) {
            $args['tag__in'] = $filters['tags'];
        }
        
        // Meta query
        if (!empty($filters['meta_query'])) {
            $args['meta_query'] = $filters['meta_query'];
        }
        
        // Specific post IDs
        if (!empty($filters['post__in'])) {
            $args['post__in'] = $filters['post__in'];
        }
        
        return apply_filters('aie_export_query_args', $args, 'post', $filters);
    }
    
    /**
     * Get posts batch
     */
    protected function get_posts_batch($filters, $offset, $limit) {
        $args = $this->build_query_args($filters);
        $args['posts_per_page'] = $limit;
        $args['offset'] = $offset;
        
        $query = new \WP_Query($args);
        return $query->posts;
    }
    
    /**
     * Map post data to export fields
     */
    protected function map_post_data($post, $fields, $field_settings) {
        $data = [];
        
        foreach ($fields as $field) {
            $value = $this->get_field_value($post, $field);
            
            // Apply field settings (search/replace, functions)
            if (isset($field_settings[$field])) {
                $value = $this->apply_field_settings($value, $field_settings[$field]);
            }
            
            $data[$field] = $value;
        }
        
        return apply_filters('aie_export_item_data', $data, $post, $fields);
    }
    
    /**
     * Get field value from post
     */
    protected function get_field_value($post, $field) {
        // Core WP fields
        $core_fields = ['ID', 'post_title', 'post_content', 'post_excerpt', 'post_date', 
                        'post_modified', 'post_status', 'post_author', 'post_parent', 
                        'post_slug', 'post_password', 'menu_order', 'comment_status', 'ping_status'];
        
        if (in_array($field, $core_fields)) {
            $field_map = [
                'post_slug' => 'post_name',
            ];
            $wp_field = $field_map[$field] ?? $field;
            return $post->$wp_field ?? '';
        }
        
        // Featured image
        if ($field === 'featured_image') {
            return get_post_thumbnail_id($post->ID);
        }
        
        if ($field === 'featured_image_url') {
            return get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        // Categories
        if ($field === 'categories') {
            $categories = get_the_category($post->ID);
            return implode(',', wp_list_pluck($categories, 'name'));
        }
        
        // Tags
        if ($field === 'tags') {
            $tags = get_the_tags($post->ID);
            return $tags ? implode(',', wp_list_pluck($tags, 'name')) : '';
        }
        
        // ACF fields
        if (function_exists('get_field') && strpos($field, 'acf_') === 0) {
            $acf_field = str_replace('acf_', '', $field);
            return get_field($acf_field, $post->ID);
        }
        
        // Yoast SEO
        if (strpos($field, 'yoast_') === 0) {
            return $this->get_yoast_field($post->ID, $field);
        }
        
        // Post meta
        return get_post_meta($post->ID, $field, true);
    }
    
    /**
     * Get Yoast SEO field
     */
    protected function get_yoast_field($post_id, $field) {
        $meta_map = [
            'yoast_seo_title' => '_yoast_wpseo_title',
            'yoast_meta_description' => '_yoast_wpseo_metadesc',
            'yoast_focus_keyword' => '_yoast_wpseo_focuskw',
            'yoast_canonical_url' => '_yoast_wpseo_canonical',
            'yoast_robots_noindex' => '_yoast_wpseo_meta-robots-noindex',
            'yoast_robots_nofollow' => '_yoast_wpseo_meta-robots-nofollow',
        ];
        
        $meta_key = $meta_map[$field] ?? null;
        if (!$meta_key) {
            return '';
        }
        
        return get_post_meta($post_id, $meta_key, true);
    }
    
    /**
     * Apply field settings
     */
    protected function apply_field_settings($value, $settings) {
        // Default value if empty
        if (empty($value) && isset($settings['default_value'])) {
            $value = $settings['default_value'];
        }
        
        // Search & Replace
        if (!empty($settings['search_replace'])) {
            foreach ($settings['search_replace'] as $rule) {
                if (empty($rule['search'])) {
                    continue;
                }
                
                $search = $rule['search'];
                $replace = $rule['replace'] ?? '';
                
                if (!empty($rule['regex'])) {
                    $value = preg_replace($search, $replace, $value);
                } else {
                    $case_sensitive = !empty($rule['case_sensitive']);
                    if ($case_sensitive) {
                        $value = str_replace($search, $replace, $value);
                    } else {
                        $value = str_ireplace($search, $replace, $value);
                    }
                }
            }
        }
        
        // Custom function
        if (!empty($settings['function_id'])) {
            $function_manager = new Custom_Function_Manager();
            $value = $function_manager->execute($settings['function_id'], $value);
        }
        
        return apply_filters('aie_export_field_value', $value, $settings);
    }
}
```

#### User_Exporter - Экспорт пользователей

**Файл**: `app/exporter/user_exporter.php`

```php
<?php
namespace AIE\Exporter;

class User_Exporter extends Base_Exporter {
    
    public function count($filters = []) {
        $args = $this->build_query_args($filters);
        $args['fields'] = 'ID';
        $args['count_total'] = true;
        
        $query = new \WP_User_Query($args);
        return $query->get_total();
    }
    
    public function export($filters, $fields, $field_settings, $format_options) {
        $batch_size = $format_options['batch_size'] ?? 50;
        $total = $this->count($filters);
        
        $offset = 0;
        
        while ($offset < $total) {
            $users = $this->get_users_batch($filters, $offset, $batch_size);
            
            foreach ($users as $user) {
                $data = $this->map_user_data($user, $fields, $field_settings);
                yield $data;
            }
            
            $offset += $batch_size;
        }
    }
    
    protected function build_query_args($filters) {
        $args = [
            'orderby' => 'ID',
            'order' => 'ASC',
        ];
        
        // User roles
        if (!empty($filters['role'])) {
            $args['role__in'] = $filters['role'];
        }
        
        // Registration date
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $args['date_query'] = [];
            if (!empty($filters['date_from'])) {
                $args['date_query']['after'] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $args['date_query']['before'] = $filters['date_to'];
            }
        }
        
        return $args;
    }
    
    protected function get_users_batch($filters, $offset, $limit) {
        $args = $this->build_query_args($filters);
        $args['number'] = $limit;
        $args['offset'] = $offset;
        
        $query = new \WP_User_Query($args);
        return $query->get_results();
    }
    
    protected function map_user_data($user, $fields, $field_settings) {
        $data = [];
        
        foreach ($fields as $field) {
            $value = $this->get_user_field_value($user, $field);
            
            if (isset($field_settings[$field])) {
                $value = $this->apply_field_settings($value, $field_settings[$field]);
            }
            
            $data[$field] = $value;
        }
        
        return $data;
    }
    
    protected function get_user_field_value($user, $field) {
        $core_fields = ['ID', 'user_login', 'user_email', 'user_nicename', 'user_url', 
                        'user_registered', 'display_name'];
        
        if (in_array($field, $core_fields)) {
            return $user->$field ?? '';
        }
        
        if ($field === 'role') {
            $roles = $user->roles;
            return !empty($roles) ? $roles[0] : '';
        }
        
        if ($field === 'roles') {
            return implode(',', $user->roles);
        }
        
        // User meta
        return get_user_meta($user->ID, $field, true);
    }
}
```

#### Product_Exporter - Экспорт WooCommerce товаров

**Файл**: `app/exporter/product_exporter.php`

```php
<?php
namespace AIE\Exporter;

class Product_Exporter extends Base_Exporter {
    
    public function count($filters = []) {
        $args = $this->build_query_args($filters);
        $args['fields'] = 'ids';
        $args['no_found_rows'] = false;
        
        $query = new \WP_Query($args);
        return $query->found_posts;
    }
    
    public function export($filters, $fields, $field_settings, $format_options) {
        if (!class_exists('WooCommerce')) {
            throw new \Exception('WooCommerce is not active');
        }
        
        $batch_size = $format_options['batch_size'] ?? 50;
        $total = $this->count($filters);
        
        $offset = 0;
        
        while ($offset < $total) {
            $products = $this->get_products_batch($filters, $offset, $batch_size);
            
            foreach ($products as $product_id) {
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                $data = $this->map_product_data($product, $fields, $field_settings);
                yield $data;
            }
            
            $offset += $batch_size;
        }
    }
    
    protected function build_query_args($filters) {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ];
        
        // Product type
        if (!empty($filters['product_type'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_type',
                'field' => 'slug',
                'terms' => $filters['product_type'],
            ];
        }
        
        // Stock status
        if (!empty($filters['stock_status'])) {
            $args['meta_query'][] = [
                'key' => '_stock_status',
                'value' => $filters['stock_status'],
                'compare' => 'IN',
            ];
        }
        
        // Price range
        if (isset($filters['price_min']) || isset($filters['price_max'])) {
            $price_query = ['key' => '_price'];
            
            if (isset($filters['price_min']) && isset($filters['price_max'])) {
                $price_query['value'] = [$filters['price_min'], $filters['price_max']];
                $price_query['compare'] = 'BETWEEN';
                $price_query['type'] = 'NUMERIC';
            } elseif (isset($filters['price_min'])) {
                $price_query['value'] = $filters['price_min'];
                $price_query['compare'] = '>=';
                $price_query['type'] = 'NUMERIC';
            } elseif (isset($filters['price_max'])) {
                $price_query['value'] = $filters['price_max'];
                $price_query['compare'] = '<=';
                $price_query['type'] = 'NUMERIC';
            }
            
            $args['meta_query'][] = $price_query;
        }
        
        // Product categories
        if (!empty($filters['product_cat'])) {
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $filters['product_cat'],
            ];
        }
        
        if (!empty($args['meta_query'])) {
            $args['meta_query']['relation'] = 'AND';
        }
        
        if (!empty($args['tax_query'])) {
            $args['tax_query']['relation'] = 'AND';
        }
        
        return $args;
    }
    
    protected function get_products_batch($filters, $offset, $limit) {
        $args = $this->build_query_args($filters);
        $args['posts_per_page'] = $limit;
        $args['offset'] = $offset;
        $args['fields'] = 'ids';
        
        $query = new \WP_Query($args);
        return $query->posts;
    }
    
    protected function map_product_data($product, $fields, $field_settings) {
        $data = [];
        
        foreach ($fields as $field) {
            $value = $this->get_product_field_value($product, $field);
            
            if (isset($field_settings[$field])) {
                $value = $this->apply_field_settings($value, $field_settings[$field]);
            }
            
            $data[$field] = $value;
        }
        
        return $data;
    }
    
    protected function get_product_field_value($product, $field) {
        // Core WP fields
        if (in_array($field, ['ID', 'post_title', 'post_content', 'post_excerpt', 'post_status'])) {
            return $product->get_id() ? get_post_field($field, $product->get_id()) : '';
        }
        
        // WooCommerce getters
        $wc_getters = [
            '_sku' => 'get_sku',
            '_regular_price' => 'get_regular_price',
            '_sale_price' => 'get_sale_price',
            '_price' => 'get_price',
            '_stock' => 'get_stock_quantity',
            '_stock_status' => 'get_stock_status',
            '_manage_stock' => 'get_manage_stock',
            '_backorders' => 'get_backorders',
            '_weight' => 'get_weight',
            '_length' => 'get_length',
            '_width' => 'get_width',
            '_height' => 'get_height',
            '_featured' => 'get_featured',
            '_virtual' => 'get_virtual',
            '_downloadable' => 'get_downloadable',
        ];
        
        if (isset($wc_getters[$field])) {
            $method = $wc_getters[$field];
            return $product->$method();
        }
        
        // Product categories
        if ($field === 'product_cat') {
            $terms = get_the_terms($product->get_id(), 'product_cat');
            return $terms ? implode(',', wp_list_pluck($terms, 'name')) : '';
        }
        
        // Product tags
        if ($field === 'product_tag') {
            $terms = get_the_terms($product->get_id(), 'product_tag');
            return $terms ? implode(',', wp_list_pluck($terms, 'name')) : '';
        }
        
        // Featured image
        if ($field === 'featured_image_url') {
            return get_the_post_thumbnail_url($product->get_id(), 'full');
        }
        
        // Gallery images
        if ($field === 'gallery_images') {
            $gallery_ids = $product->get_gallery_image_ids();
            $urls = array_map(function($id) {
                return wp_get_attachment_url($id);
            }, $gallery_ids);
            return implode(',', $urls);
        }
        
        // Product attributes
        if ($field === 'product_attributes') {
            $attributes = $product->get_attributes();
            $attr_data = [];
            foreach ($attributes as $attr) {
                $attr_data[] = $attr->get_name() . ':' . implode('|', $attr->get_options());
            }
            return implode(';', $attr_data);
        }
        
        // Meta
        return get_post_meta($product->get_id(), $field, true);
    }
}
```

### 11.3 Format Writers

Классы для записи в различные форматы.

#### CSV_Writer

**Файл**: `app/format/csv_writer.php`

```php
<?php
namespace AIE\Format;

class CSV_Writer {
    
    protected $file_handle;
    protected $file_path;
    protected $delimiter = ',';
    protected $enclosure = '"';
    protected $include_bom = true;
    protected $include_headers = true;
    protected $headers_written = false;
    
    public function __construct($file_path, $options = []) {
        $this->file_path = $file_path;
        
        if (isset($options['delimiter'])) {
            $this->delimiter = $options['delimiter'];
        }
        if (isset($options['enclosure'])) {
            $this->enclosure = $options['enclosure'];
        }
        if (isset($options['include_bom'])) {
            $this->include_bom = $options['include_bom'];
        }
        if (isset($options['include_headers'])) {
            $this->include_headers = $options['include_headers'];
        }
        
        $this->open();
    }
    
    protected function open() {
        $this->file_handle = fopen($this->file_path, 'w');
        
        if ($this->file_handle === false) {
            throw new \Exception("Failed to open file: {$this->file_path}");
        }
        
        // Write BOM for UTF-8
        if ($this->include_bom) {
            fwrite($this->file_handle, "\xEF\xBB\xBF");
        }
    }
    
    public function write_row($data) {
        // Write headers on first row
        if (!$this->headers_written && $this->include_headers) {
            $headers = array_keys($data);
            fputcsv($this->file_handle, $headers, $this->delimiter, $this->enclosure);
            $this->headers_written = true;
        }
        
        fputcsv($this->file_handle, array_values($data), $this->delimiter, $this->enclosure);
    }
    
    public function close() {
        if ($this->file_handle) {
            fclose($this->file_handle);
            $this->file_handle = null;
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}
```

#### JSON_Writer

**Файл**: `app/format/json_writer.php`

```php
<?php
namespace AIE\Format;

class JSON_Writer {
    
    protected $file_handle;
    protected $file_path;
    protected $pretty_print = true;
    protected $include_metadata = false;
    protected $first_item = true;
    protected $item_count = 0;
    
    public function __construct($file_path, $options = []) {
        $this->file_path = $file_path;
        
        if (isset($options['pretty_print'])) {
            $this->pretty_print = $options['pretty_print'];
        }
        if (isset($options['include_metadata'])) {
            $this->include_metadata = $options['include_metadata'];
        }
        
        $this->open();
    }
    
    protected function open() {
        $this->file_handle = fopen($this->file_path, 'w');
        
        if ($this->file_handle === false) {
            throw new \Exception("Failed to open file: {$this->file_path}");
        }
        
        // Start JSON structure
        if ($this->include_metadata) {
            fwrite($this->file_handle, "{\n");
            fwrite($this->file_handle, '  "metadata": ' . json_encode([
                'exported_at' => current_time('mysql'),
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => AIE_VERSION,
            ], JSON_PRETTY_PRINT) . ",\n");
            fwrite($this->file_handle, '  "data": [' . "\n");
        } else {
            fwrite($this->file_handle, "[\n");
        }
    }
    
    public function write_item($data) {
        if (!$this->first_item) {
            fwrite($this->file_handle, ",\n");
        }
        
        $json_flags = 0;
        if ($this->pretty_print) {
            $json_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        }
        
        $json = json_encode($data, $json_flags);
        
        if ($this->pretty_print) {
            // Indent each line
            $json = preg_replace('/^/m', '    ', $json);
        }
        
        fwrite($this->file_handle, $json);
        
        $this->first_item = false;
        $this->item_count++;
    }
    
    public function close() {
        if ($this->file_handle) {
            fwrite($this->file_handle, "\n");
            
            if ($this->include_metadata) {
                fwrite($this->file_handle, "  ],\n");
                fwrite($this->file_handle, '  "count": ' . $this->item_count . "\n");
                fwrite($this->file_handle, "}\n");
            } else {
                fwrite($this->file_handle, "]\n");
            }
            
            fclose($this->file_handle);
            $this->file_handle = null;
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}
```

#### Excel_Writer

**Файл**: `app/format/excel_writer.php`

```php
<?php
namespace AIE\Format;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class Excel_Writer {
    
    protected $spreadsheet;
    protected $worksheet;
    protected $file_path;
    protected $format; // xlsx or xls
    protected $current_row = 1;
    protected $headers_written = false;
    
    public function __construct($file_path, $format = 'xlsx') {
        $this->file_path = $file_path;
        $this->format = $format;
        
        $this->spreadsheet = new Spreadsheet();
        $this->worksheet = $this->spreadsheet->getActiveSheet();
    }
    
    public function write_row($data) {
        // Write headers on first row
        if (!$this->headers_written) {
            $col = 'A';
            foreach (array_keys($data) as $header) {
                $this->worksheet->setCellValue($col . $this->current_row, $header);
                $col++;
            }
            $this->current_row++;
            $this->headers_written = true;
        }
        
        // Write data
        $col = 'A';
        foreach (array_values($data) as $value) {
            $this->worksheet->setCellValue($col . $this->current_row, $value);
            $col++;
        }
        $this->current_row++;
    }
    
    public function save() {
        if ($this->format === 'xlsx') {
            $writer = new Xlsx($this->spreadsheet);
        } else {
            $writer = new Xls($this->spreadsheet);
        }
        
        $writer->save($this->file_path);
    }
    
    public function __destruct() {
        if ($this->spreadsheet) {
            $this->spreadsheet->disconnectWorksheets();
        }
    }
}
```

### 11.4 Export Progress Tracker

**Файл**: `app/export/export_progress_tracker.php`

```php
<?php
namespace AIE\Export;

class Export_Progress_Tracker {
    
    protected $job_id;
    
    public function __construct($job_id = null) {
        $this->job_id = $job_id;
    }
    
    public function update($processed, $success, $failed, $status = 'processing') {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'aie_jobs',
            [
                'processed_items' => $processed,
                'success_items' => $success,
                'failed_items' => $failed,
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $this->job_id],
            ['%d', '%d', '%d', '%s', '%s'],
            ['%d']
        );
    }
    
    public function complete($file_path) {
        global $wpdb;
        
        $file_size = file_exists($file_path) ? filesize($file_path) : 0;
        
        $wpdb->update(
            $wpdb->prefix . 'aie_jobs',
            [
                'status' => 'completed',
                'file_path' => $file_path,
                'file_size' => $file_size,
                'completed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $this->job_id],
            ['%s', '%s', '%d', '%s', '%s'],
            ['%d']
        );
        
        do_action('aie_export_complete', $this->job_id, $file_path);
    }
    
    public function fail($error_message) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'aie_jobs',
            [
                'status' => 'failed',
                'completed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $this->job_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        // Log error
        $this->log_error($error_message);
        
        do_action('aie_export_failed', $this->job_id, $error_message);
    }
    
    public function get_progress() {
        global $wpdb;
        
        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aie_jobs WHERE id = %d",
            $this->job_id
        ), ARRAY_A);
        
        if (!$job) {
            return false;
        }
        
        $progress_percent = 0;
        if ($job['total_items'] > 0) {
            $progress_percent = ($job['processed_items'] / $job['total_items']) * 100;
        }
        
        return [
            'job_id' => $this->job_id,
            'status' => $job['status'],
            'total' => $job['total_items'],
            'processed' => $job['processed_items'],
            'success' => $job['success_items'],
            'failed' => $job['failed_items'],
            'progress_percent' => round($progress_percent, 2),
            'file_path' => $job['file_path'],
            'file_size' => $job['file_size'],
        ];
    }
}
```

### 11.5 UI Wireframes

Детальные wireframes находятся в **EXPORT_UI_SPECIFICATION.md**.

Основные страницы:
- `app/view/export/export_wizard.php` - Главная страница визарда
- `app/view/export/step_*.php` - Отдельные шаги (1-5)
- `app/view/export/export_progress.php` - Страница прогресса
- `app/view/export/export_history.php` - История экспортов
- `app/view/export/export_templates.php` - Шаблоны экспортов

### 11.6 JavaScript Modules

**Файл**: `src/js/modules/export_wizard.js`

```javascript
class ExportWizard {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 5;
        this.data = {};
        
        this.init();
    }
    
    init() {
        this.bindEvents();
    }
    
    bindEvents() {
        // Step navigation
        jQuery('.aie-wizard-next').on('click', () => this.nextStep());
        jQuery('.aie-wizard-prev').on('click', () => this.prevStep());
        
        // Content type selection
        jQuery('input[name="content_type"]').on('change', (e) => {
            this.handleContentTypeChange(e.target.value);
        });
        
        // Field selector
        this.initFieldSelector();
    }
    
    nextStep() {
        if (!this.validateStep(this.currentStep)) {
            return;
        }
        
        this.saveStepData(this.currentStep);
        
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.renderStep(this.currentStep);
        }
    }
    
    handleContentTypeChange(contentType) {
        // Load available fields via AJAX
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aie_get_export_fields',
                content_type: contentType,
                nonce: aieExportData.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.data.availableFields = response.data.fields;
                    this.data.estimatedCount = response.data.count;
                    this.updateFieldsUI();
                }
            }
        });
    }
    
    initFieldSelector() {
        const fieldSelector = new FieldSelector('#aie-field-selector');
        fieldSelector.onSelectionChange((selected) => {
            this.data.selectedFields = selected;
        });
    }
    
    startExport() {
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aie_start_export',
                nonce: aieExportData.nonce,
                data: JSON.stringify(this.data)
            },
            success: (response) => {
                if (response.success) {
                    this.trackProgress(response.data.job_id);
                }
            }
        });
    }
    
    trackProgress(jobId) {
        const tracker = new ExportProgress(jobId);
        tracker.start();
    }
}

// Initialize
jQuery(document).ready(() => {
    new ExportWizard();
});
```

### 11.7 REST API Endpoints

```php
// Export wizard endpoints
register_rest_route('aie/v1', '/export/estimate', [
    'methods' => 'POST',
    'callback' => [$export_controller, 'estimate'],
    'permission_callback' => [$this, 'check_permissions']
]);

register_rest_route('aie/v1', '/export/start', [
    'methods' => 'POST',
    'callback' => [$export_controller, 'start_export'],
]);

register_rest_route('aie/v1', '/export/progress/(?P<job_id>\d+)', [
    'methods' => 'GET',
    'callback' => [$export_controller, 'get_progress'],
]);

register_rest_route('aie/v1', '/export/download/(?P<job_id>\d+)', [
    'methods' => 'GET',
    'callback' => [$export_controller, 'download_file'],
]);

// Templates
register_rest_route('aie/v1', '/export/templates', [
    'methods' => 'GET',
    'callback' => [$export_controller, 'get_templates'],
]);

register_rest_route('aie/v1', '/export/template/save', [
    'methods' => 'POST',
    'callback' => [$export_controller, 'save_template'],
]);
```

### 11.8 Hooks & Filters

```php
// Before export starts
do_action('aie_before_export', $job_id, $settings);

// Before exporting each item
do_action('aie_before_export_item', $item_data, $content_type);

// After successfully exporting item
do_action('aie_after_export_item', $exported_data, $item_data);

// After export completes
do_action('aie_after_export', $job_id, $file_path, $stats);

// On export complete
do_action('aie_export_complete', $job_id, $file_path);

// On export failed
do_action('aie_export_failed', $job_id, $error_message);

// Filters
apply_filters('aie_export_query_args', $args, $content_type, $filters);
apply_filters('aie_export_item_data', $data, $item, $fields);
apply_filters('aie_export_field_value', $value, $field, $item);
apply_filters('aie_export_file_path', $file_path, $job_id);
apply_filters('aie_export_batch_size', $batch_size, $content_type);
apply_filters('aie_export_formats', $formats);
apply_filters('aie_export_available_fields', $fields, $content_type);
apply_filters('aie_export_content_types', $types);
```

---

## База данных

### Custom Tables

```sql
-- История импорта/экспорта
CREATE TABLE {prefix}_aie_jobs (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    type ENUM('import', 'export') NOT NULL,
    data_type VARCHAR(50) NOT NULL,
    file_format VARCHAR(10) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    total_items INT DEFAULT 0,
    processed_items INT DEFAULT 0,
    success_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    file_path VARCHAR(255),
    settings TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    completed_at DATETIME,
    INDEX user_id_idx (user_id),
    INDEX status_idx (status),
    INDEX type_idx (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Логи выполнения
CREATE TABLE {prefix}_aie_logs (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT(20) UNSIGNED NOT NULL,
    level ENUM('info', 'warning', 'error') DEFAULT 'info',
    message TEXT NOT NULL,
    data LONGTEXT,
    created_at DATETIME NOT NULL,
    INDEX job_id_idx (job_id),
    INDEX level_idx (level),
    FOREIGN KEY (job_id) REFERENCES {prefix}_aie_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Маппинг полей (сохраненные пресеты)
CREATE TABLE {prefix}_aie_field_maps (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    data_type VARCHAR(50) NOT NULL,
    mapping TEXT NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX user_id_idx (user_id),
    INDEX data_type_idx (data_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Пользовательские функции обработки данных
CREATE TABLE {prefix}_aie_custom_functions (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    function_code LONGTEXT NOT NULL,
    source VARCHAR(100) DEFAULT 'custom',
    input_type VARCHAR(50) DEFAULT 'string',
    output_type VARCHAR(50) DEFAULT 'string',
    is_active TINYINT(1) DEFAULT 1,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    last_used_at DATETIME,
    usage_count INT DEFAULT 0,
    INDEX name_idx (name),
    INDEX user_id_idx (user_id),
    INDEX is_active_idx (is_active),
    INDEX source_idx (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- source values: 'custom', 'library:snippet_key', 'imported'

-- Синхронизация медиа папок
CREATE TABLE {prefix}_aie_media_sync (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT(20) UNSIGNED NOT NULL,
    folder_path VARCHAR(500) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    attachment_id BIGINT(20) UNSIGNED,
    status ENUM('pending', 'synced', 'skipped', 'failed') DEFAULT 'pending',
    skip_reason VARCHAR(100), -- 'duplicate', 'invalid_type', 'error'
    file_hash VARCHAR(32),    -- MD5 hash
    file_size BIGINT(20),
    error_message TEXT,
    created_at DATETIME NOT NULL,
    INDEX job_id_idx (job_id),
    INDEX folder_path_idx (folder_path(255)),
    INDEX file_hash_idx (file_hash),
    INDEX attachment_id_idx (attachment_id),
    INDEX status_idx (status),
    FOREIGN KEY (job_id) REFERENCES {prefix}_aie_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Подключения между сайтами (Site-to-Site)
CREATE TABLE {prefix}_aie_site_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    remote_url VARCHAR(500) NOT NULL,
    api_key VARCHAR(100) NOT NULL UNIQUE,
    direction ENUM('pull', 'push', 'bidirectional') DEFAULT 'bidirectional',
    status ENUM('active', 'inactive', 'error') DEFAULT 'active',
    last_sync_at DATETIME,
    last_error TEXT,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX remote_url_idx (remote_url(255)),
    INDEX status_idx (status),
    INDEX created_by_idx (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- История синхронизации контента между сайтами
CREATE TABLE {prefix}_aie_content_sync (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT(20) UNSIGNED NOT NULL,
    connection_id BIGINT(20) UNSIGNED NOT NULL,
    direction ENUM('pull', 'push') NOT NULL,
    content_type VARCHAR(50) NOT NULL, -- 'posts', 'users', 'media', 'terms'
    local_id BIGINT(20),               -- ID в локальной БД
    remote_id BIGINT(20),              -- ID на удаленном сайте
    action ENUM('created', 'updated', 'skipped', 'failed') NOT NULL,
    error_message TEXT,
    created_at DATETIME NOT NULL,
    INDEX job_id_idx (job_id),
    INDEX connection_id_idx (connection_id),
    INDEX content_type_idx (content_type),
    INDEX local_id_idx (local_id),
    INDEX remote_id_idx (remote_id),
    FOREIGN KEY (job_id) REFERENCES {prefix}_aie_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (connection_id) REFERENCES {prefix}_aie_site_connections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API ключи для входящих подключений
CREATE TABLE {prefix}_aie_api_keys (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    api_key VARCHAR(100) NOT NULL UNIQUE,
    permissions TEXT,                   -- JSON с разрешениями
    allowed_ips TEXT,                   -- JSON с разрешенными IP
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_used_at DATETIME,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX status_idx (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## API и хуки

### REST API Endpoints

```php
// Импорт
POST /wp-json/aie/v1/import/upload
POST /wp-json/aie/v1/import/validate
POST /wp-json/aie/v1/import/start
GET  /wp-json/aie/v1/import/progress/{job_id}
GET  /wp-json/aie/v1/import/logs/{job_id}

// Экспорт
POST /wp-json/aie/v1/export/start
GET  /wp-json/aie/v1/export/progress/{job_id}
GET  /wp-json/aie/v1/export/download/{job_id}

// Media Sync
POST /wp-json/aie/v1/media-sync/scan
POST /wp-json/aie/v1/media-sync/start
GET  /wp-json/aie/v1/media-sync/progress/{job_id}
GET  /wp-json/aie/v1/media-sync/check-duplicate

// Site-to-Site Content Sync
POST /wp-json/aie/v1/site-sync/verify
POST /wp-json/aie/v1/site-sync/export
POST /wp-json/aie/v1/site-sync/import
POST /wp-json/aie/v1/site-sync/list

// История
GET  /wp-json/aie/v1/jobs
GET  /wp-json/aie/v1/jobs/{job_id}
DELETE /wp-json/aie/v1/jobs/{job_id}
```

### Action Hooks для расширения

```php
// Импорт
do_action('aie_before_import', $jobId, $dataType);
do_action('aie_import_row', $rowData, $rowIndex);
do_action('aie_after_import_row', $itemId, $rowData);
do_action('aie_after_import', $jobId, $results);

// Экспорт
do_action('aie_before_export', $jobId, $dataType);
do_action('aie_export_row', $item, $index);
do_action('aie_after_export', $jobId, $filePath);

// Валидация
apply_filters('aie_validation_rules', $rules, $dataType);
apply_filters('aie_validate_row', $isValid, $rowData);

// Маппинг
apply_filters('aie_field_mapping', $mapping, $dataType);
apply_filters('aie_transform_value', $value, $fieldName);

// Пользовательские функции
apply_filters('aie_execute_custom_function', $result, $function_name, $value, $context);
apply_filters('aie_custom_function_sandbox', $allowed_functions);
do_action('aie_before_function_execute', $function_name, $value);
do_action('aie_after_function_execute', $function_name, $result);
```

## Производительность и оптимизация

### Memory Management
- Streaming для чтения больших файлов
- Batch processing (100-500 записей за раз)
- Очистка памяти между батчами (`gc_collect_cycles()`)
- Увеличение memory_limit при необходимости

### Timeout Prevention
- WP Cron для фоновой обработки
- Heartbeat для длительных операций
- Разбивка на multiple AJAX запросы

### Database Optimization
- Bulk insert вместо одиночных
- Использование prepared statements
- Индексы на часто используемых полях
- Транзакции для атомарности

### Caching
- Transient API для временных данных
- Object caching для повторяющихся запросов
- Кеширование маппингов и настроек

## Безопасность

### File Upload Security
- Проверка nonce при загрузке
- Валидация MIME-type
- Ограничение размера файла
- Сканирование содержимого
- Хранение в защищенной директории

### Data Validation
- Sanitization всех входных данных
- Escape при выводе
- Prepared statements для SQL
- Проверка capabilities пользователя

### Rate Limiting
- Ограничение количества импортов в час
- Защита от DOS атак
- Throttling для API запросов

## Расширяемость

### Регистрация custom импортера

```php
add_filter('aie_importers', function($importers) {
    $importers['my_custom_type'] = My_Custom_Importer::class;
    return $importers;
});
```

### Добавление нового формата файла

```php
add_filter('aie_file_formats', function($formats) {
    $formats['excel'] = Excel_Format::class;
    return $formats;
});
```

### Добавление правил валидации

```php
add_filter('aie_validation_rules', function($rules, $data_type) {
    if ($data_type === 'products') {
        $rules[] = new Price_Validation_Rule();
    }
    return $rules;
}, 10, 2);
```

## Тестирование

### Unit Tests
- Тестирование каждого компонента отдельно
- Мокирование WordPress функций
- PHPUnit

### Integration Tests
- Тестирование полного flow импорта/экспорта
- Реальные файлы и данные
- Проверка базы данных

### Performance Tests
- Тестирование с большими файлами (10K+ строк)
- Мониторинг использования памяти
- Измерение времени выполнения

## Совместимость

### WordPress версии
- Минимум: WordPress 5.8+
- PHP: 7.4+
- MySQL: 5.6+ / MariaDB 10.1+

### Популярные плагины
- WooCommerce
- Advanced Custom Fields (ACF)
- Yoast SEO
- Contact Form 7
- Custom Post Type UI

## Roadmap

### Phase 1 (MVP):
- ✅ Базовая структура
- ✅ Импорт/экспорт Posts
- ✅ CSV формат
- ✅ Простой UI

### Phase 2:
- ✅ Users, Comments
- ✅ JSON/XML форматы
- ✅ Field mapping
- ✅ Background processing

### Phase 3:
- ✅ Custom post types
- ✅ Taxonomies и meta
- ✅ Media import
- ✅ Validation system

### Phase 4:
- ✅ WooCommerce support
- ✅ ACF support
- ✅ Scheduled imports
- ✅ CLI commands

## Мониторинг и отладка

### Debug Mode
```php
define('AIE_DEBUG', true);
```

### Логи
- Файловые логи в `/wp-content/uploads/aie-logs/`
- Database логи в custom table
- Integration с WordPress Debug Log

### Метрики
- Количество успешных/неудачных импортов
- Среднее время обработки
- Использование памяти
- Размеры файлов

---

**Последнее обновление**: 27 ноября 2025
**Версия документа**: 1.0.0
