# Автоматическое добавление MD5 хэшей к медиафайлам

## Обзор

Система автоматически добавляет MD5 хэш ко ВСЕМ файлам, загружаемым в медиа-библиотеку WordPress, не только при синхронизации через плагин, но и при обычной загрузке пользователем через стандартный интерфейс WordPress.

## Назначение

MD5 хэши используются для:
- **Точного обнаружения дубликатов** - два файла с одинаковым содержимым имеют одинаковый хэш, даже если имена файлов разные
- **Предотвращения повторной загрузки** - перед загрузкой можно проверить, существует ли уже файл с таким хэшем
- **Оптимизации хранилища** - избежание хранения дубликатов файлов

## Архитектура

### Компоненты

#### 1. Media_Hash Helper (`app/Helper/Media_Hash.php`)

Основной класс для работы с хэшами медиафайлов.

**Методы:**

- `init()` - Инициализирует WordPress хуки для автоматического добавления хэшей
- `add_hash_to_attachment()` - Добавляет хэш при генерации метаданных (для изображений)
- `add_hash_to_attachment_fallback()` - Fallback для файлов без метаданных (PDF, документы и т.д.)
- `cleanup_hash_meta()` - Очищает мета-данные при удалении вложения
- `get_attachment_by_hash()` - Находит вложение по MD5 хэшу
- `find_duplicate()` - Проверяет, существует ли дубликат файла
- `bulk_add_hashes()` - Массовое добавление хэшей к существующим файлам
- `get_statistics()` - Получение статистики о хэшированных файлах

#### 2. Media_Hash_Controller (`app/Controller/Media_Hash_Controller.php`)

AJAX контроллер для управления хэшами через API.

**Endpoints:**

- `wp_ajax_aie_get_hash_statistics` - Получить статистику
- `wp_ajax_aie_bulk_add_hashes` - Массовое добавление хэшей
- `wp_ajax_aie_check_duplicate_hash` - Проверка на дубликаты

### WordPress Hooks

Система использует следующие хуки WordPress:

```php
// Основной хук для файлов с метаданными (изображения)
add_filter( 'wp_generate_attachment_metadata', [ __CLASS__, 'add_hash_to_attachment' ], 10, 2 );

// Fallback для файлов без метаданных (PDF, DOC и т.д.)
add_action( 'add_attachment', [ __CLASS__, 'add_hash_to_attachment_fallback' ] );

// Очистка при удалении
add_action( 'delete_attachment', [ __CLASS__, 'cleanup_hash_meta' ] );
```

## Сохраняемые данные

Для каждого загруженного файла сохраняются следующие post meta:

| Meta Key | Описание | Пример |
|----------|----------|--------|
| `aie_file_hash` | MD5 хэш содержимого файла | `5d41402abc4b2a76b9719d911017c592` |
| `aie_file_size` | Размер файла в байтах | `1234567` |
| `aie_hash_added` | Дата и время добавления хэша | `2025-12-02 10:30:45` |

## Использование

### Автоматическое добавление хэшей

После активации плагина хэши автоматически добавляются ко всем новым загрузкам:

1. Пользователь загружает файл через WordPress Media Library
2. WordPress генерирует метаданные для файла
3. Хук `wp_generate_attachment_metadata` срабатывает
4. Система вычисляет MD5 хэш файла
5. Хэш сохраняется в post meta

### Проверка дубликатов перед загрузкой

```php
use WP_AIE\Helper\Media_Hash;

// Проверка файла на дубликаты
$file_path = '/path/to/file.jpg';
$duplicate_id = Media_Hash::find_duplicate( $file_path );

if ( $duplicate_id ) {
    echo "Файл уже существует! Attachment ID: " . $duplicate_id;
} else {
    echo "Дубликатов не найдено, можно загружать";
}
```

### Поиск вложения по хэшу

```php
use WP_AIE\Helper\Media_Hash;

$hash = md5_file( '/path/to/file.jpg' );
$attachment_id = Media_Hash::get_attachment_by_hash( $hash );

if ( $attachment_id ) {
    $url = wp_get_attachment_url( $attachment_id );
    echo "Файл найден: " . $url;
}
```

### Массовое добавление хэшей к существующим файлам

Для добавления хэшей к файлам, которые были загружены до активации этой функции:

```php
use WP_AIE\Helper\Media_Hash;

// Обработать 50 файлов начиная с позиции 0
$result = Media_Hash::bulk_add_hashes( 50, 0 );

/*
Результат:
[
    'processed' => 50,    // Обработано успешно
    'errors' => 0,        // Ошибок
    'remaining' => 450,   // Осталось обработать
    'total' => 500        // Всего файлов без хэша
]
*/

// Обработать следующие 50 файлов
$result = Media_Hash::bulk_add_hashes( 50, 50 );
```

### Получение статистики

```php
use WP_AIE\Helper\Media_Hash;

$stats = Media_Hash::get_statistics();

/*
Результат:
[
    'total' => 1000,       // Всего файлов в медиа-библиотеке
    'hashed' => 800,       // Файлов с хэшем
    'unhashed' => 200,     // Файлов без хэша
    'percentage' => 80.00  // Процент файлов с хэшем
]
*/
```

## AJAX API

### Получить статистику

```javascript
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'aie_get_hash_statistics',
        nonce: aie_nonce,
        _wpnonce: wp.nonce // для проверки
    },
    success: function(response) {
        if (response.success) {
            console.log('Total files:', response.data.total);
            console.log('Hashed files:', response.data.hashed);
            console.log('Percentage:', response.data.percentage + '%');
        }
    }
});
```

### Массовое добавление хэшей

```javascript
function processNextBatch(offset = 0) {
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'aie_bulk_add_hashes',
            nonce: aie_nonce,
            batch_size: 50,
            offset: offset
        },
        success: function(response) {
            if (response.success) {
                const result = response.data;
                console.log('Processed:', result.processed);
                console.log('Remaining:', result.remaining);
                
                // Если остались файлы, обработать следующую партию
                if (result.remaining > 0) {
                    processNextBatch(offset + result.processed);
                } else {
                    console.log('All files processed!');
                }
            }
        }
    });
}

// Начать обработку
processNextBatch(0);
```

### Проверка на дубликаты

```javascript
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'aie_check_duplicate_hash',
        nonce: aie_nonce,
        file_path: '/path/to/uploads/file.jpg'
    },
    success: function(response) {
        if (response.success) {
            if (response.data.is_duplicate) {
                console.log('Duplicate found!');
                console.log('Attachment ID:', response.data.attachment_id);
                console.log('URL:', response.data.attachment_url);
            } else {
                console.log('No duplicates found');
            }
        }
    }
});
```

## Интеграция с Media Sync

Функциональность хэширования полностью интегрирована с существующей системой Media Sync:

```php
// В Media_Sync::import_file()
// Хэш автоматически добавляется после создания вложения
$attach_id = wp_insert_attachment( $attachment, $dest_path );

// Хэш добавляется автоматически через хуки WordPress
// Но также добавляется явно для совместимости:
$hash = md5_file( $dest_path );
update_post_meta( $attach_id, 'aie_file_hash', $hash );
```

## Производительность

### Оптимизации

1. **Индексация по meta_key** - WordPress автоматически индексирует post_meta по ключу
2. **Ограничение результатов** - при поиске дубликатов используется `posts_per_page => 1`
3. **Batch обработка** - массовое добавление хэшей выполняется порциями по 50 файлов
4. **Кэширование** - WordPress кэширует meta queries

### Рекомендации

- Для сайтов с большим количеством медиафайлов (>10000) используйте batch обработку
- Максимальный размер batch - 100 файлов за раз
- При первичной миграции выполняйте обработку в фоне или в maintenance mode

## Безопасность

### Проверки

1. **Nonce verification** - все AJAX запросы требуют валидный nonce
2. **Path validation** - все пути проверяются на нахождение внутри uploads directory
3. **realpath() проверка** - защита от path traversal атак
4. **Capabilities check** - проверка прав пользователя (через Base_Controller)

### Пример проверки пути

```php
$upload_dir = wp_upload_dir();
$base_dir   = $upload_dir['basedir'];
$real_path  = realpath( $file_path );
$real_base  = realpath( $base_dir );

if ( false === $real_path || false === strpos( $real_path, $real_base ) ) {
    // Путь за пределами uploads directory - отклонить
    return new \WP_Error( 'invalid_path', 'Path must be within uploads directory' );
}
```

## Troubleshooting

### Хэши не добавляются автоматически

**Проблема:** После загрузки файла хэш отсутствует

**Решения:**
1. Проверьте, что плагин активирован
2. Убедитесь, что `Media_Hash::init()` вызывается в `App::run()`
3. Проверьте WordPress error log на наличие ошибок

### Дубликаты не обнаруживаются

**Проблема:** Система не находит существующие дубликаты

**Решения:**
1. Проверьте, что у файла есть meta `aie_file_hash`
2. Запустите `bulk_add_hashes()` для старых файлов
3. Проверьте integrity файла (возможно, файл был изменен)

### Низкая производительность при bulk обработке

**Проблема:** Массовое добавление хэшей работает медленно

**Решения:**
1. Уменьшите `batch_size` до 20-30
2. Добавьте задержку между запросами
3. Используйте WP-CLI для фоновой обработки

## Будущие улучшения

1. **WP-CLI команды** для управления хэшами
2. **Администраторская панель** для мониторинга и управления
3. **Альтернативные алгоритмы** хэширования (SHA256, perceptual hashing)
4. **Автоматическое обнаружение** и удаление дубликатов
5. **Уведомления** при загрузке дубликатов

## Миграция с предыдущих версий

Если у вас уже есть медиафайлы без хэшей:

1. Получите статистику: `Media_Hash::get_statistics()`
2. Запустите массовое добавление: `Media_Hash::bulk_add_hashes()`
3. Повторите шаг 2 пока `remaining > 0`

## Лицензия

Часть плагина WP Advanced Import Export
GPL v2 или позднее
