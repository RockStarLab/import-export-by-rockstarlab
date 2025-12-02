# Автоматическое хэширование медиафайлов - Краткая сводка

## Что было реализовано

Добавлена система автоматического вычисления и сохранения MD5 хэшей для **ВСЕХ** файлов, загружаемых в медиа-библиотеку WordPress, включая:
- ✅ Загрузка через стандартный интерфейс WordPress
- ✅ Загрузка через Media Sync плагина
- ✅ Загрузка через WP-CLI
- ✅ Любой другой способ загрузки, использующий стандартные WordPress функции

## Новые файлы

1. **`app/Helper/Media_Hash.php`** - Основной класс для работы с хэшами
2. **`app/Controller/Media_Hash_Controller.php`** - AJAX контроллер для API
3. **`MEDIA_HASH_FEATURE.md`** - Полная документация
4. **`MEDIA_HASH_TESTING.md`** - Руководство по тестированию

## Изменённые файлы

1. **`app/App.php`** - Добавлена инициализация `Media_Hash::init()`
2. **`app/Controller/Init.php`** - Добавлена инициализация `Media_Hash_Controller`

## Как это работает

### Автоматическое добавление хэша

```
Пользователь загружает файл
         ↓
WordPress обрабатывает файл
         ↓
Срабатывает хук wp_generate_attachment_metadata
         ↓
Media_Hash::add_hash_to_attachment()
         ↓
Вычисляется MD5 хэш файла
         ↓
Сохраняется в post meta:
  - aie_file_hash (MD5 хэш)
  - aie_file_size (размер)
  - aie_hash_added (дата)
```

### Для файлов без метаданных (PDF, DOC и т.д.)

```
add_attachment action
         ↓
Media_Hash::add_hash_to_attachment_fallback()
         ↓
Проверка: хэш уже существует?
         ↓ (нет)
Вычисление и сохранение хэша
```

## Основные возможности

### 1. Автоматическое хэширование
- Хэш добавляется автоматически при любой загрузке
- Поддерживаются все типы файлов
- Не требует настройки от пользователя

### 2. Поиск дубликатов
```php
use WP_AIE\Helper\Media_Hash;

$duplicate_id = Media_Hash::find_duplicate('/path/to/file.jpg');
if ($duplicate_id) {
    echo "Файл уже существует! ID: $duplicate_id";
}
```

### 3. Массовая обработка существующих файлов
```php
// Добавить хэши к 50 файлам
$result = Media_Hash::bulk_add_hashes(50, 0);
echo "Обработано: {$result['processed']}, осталось: {$result['remaining']}";
```

### 4. Статистика
```php
$stats = Media_Hash::get_statistics();
echo "Всего файлов: {$stats['total']}\n";
echo "С хэшем: {$stats['hashed']} ({$stats['percentage']}%)\n";
echo "Без хэша: {$stats['unhashed']}\n";
```

## AJAX API

### Получить статистику
```javascript
jQuery.post(ajaxurl, {
    action: 'aie_get_hash_statistics',
    nonce: aie_nonce
}, function(response) {
    console.log(response.data);
});
```

### Массовое добавление хэшей
```javascript
jQuery.post(ajaxurl, {
    action: 'aie_bulk_add_hashes',
    nonce: aie_nonce,
    batch_size: 50,
    offset: 0
}, function(response) {
    console.log('Processed:', response.data.processed);
    console.log('Remaining:', response.data.remaining);
});
```

### Проверка на дубликаты
```javascript
jQuery.post(ajaxurl, {
    action: 'aie_check_duplicate_hash',
    nonce: aie_nonce,
    file_path: '/path/to/file.jpg'
}, function(response) {
    if (response.data.is_duplicate) {
        console.log('Duplicate found!', response.data.attachment_id);
    }
});
```

## Интеграция с существующим кодом

### Media Sync уже использует хэши

В `app/Helper/Media_Sync.php` метод `import_file()` уже добавляет хэш:

```php
// Строка 325
$hash = md5_file( $dest_path );
update_post_meta( $attach_id, 'aie_file_hash', $hash );
update_post_meta( $attach_id, 'aie_file_size', filesize( $dest_path ) );
```

Теперь этот же хэш будет добавляться автоматически через хуки WordPress для **всех** загрузок, не только через Media Sync.

### Проверка дубликатов в Media Sync

Метод `check_duplicate_by_hash()` в Media_Sync ищет файлы по `aie_file_hash`:

```php
// Строка 174-190
$hash = md5_file( $file_path );
$args = [
    'post_type'   => 'attachment',
    'meta_query'  => [
        [
            'key'   => 'aie_file_hash',
            'value' => $hash,
        ],
    ],
];
```

Теперь эта проверка будет находить дубликаты для **всех** файлов, не только загруженных через Media Sync.

## Преимущества

1. **Универсальность** - работает для любого способа загрузки
2. **Прозрачность** - не требует действий от пользователя
3. **Производительность** - использует индексы WordPress
4. **Совместимость** - полная интеграция с Media Sync
5. **Безопасность** - все AJAX endpoints защищены

## Миграция существующих файлов

Если у вас уже есть медиафайлы без хэшей:

```php
// Получить статистику
$stats = Media_Hash::get_statistics();
echo "Файлов без хэша: {$stats['unhashed']}\n";

// Обработать все файлы порциями по 50
$offset = 0;
while (true) {
    $result = Media_Hash::bulk_add_hashes(50, $offset);
    echo "Обработано: {$result['processed']}\n";
    
    if ($result['remaining'] === 0) {
        break;
    }
    
    $offset += $result['processed'];
}

echo "Готово! Все файлы обработаны.\n";
```

## Быстрый тест

1. Загрузите любой файл через WordPress Media Library
2. Проверьте через WP-CLI:
```bash
# Замените 123 на ID файла
wp post meta get 123 aie_file_hash
```

Должен вернуть MD5 хэш файла.

## Проверка работоспособности

```bash
# Проверить синтаксис
php -l app/Helper/Media_Hash.php
php -l app/Controller/Media_Hash_Controller.php
php -l app/App.php
php -l app/Controller/Init.php

# Все файлы должны вернуть "No syntax errors detected"
```

## Что дальше?

1. **Тестирование** - следуйте `MEDIA_HASH_TESTING.md`
2. **Документация** - полная информация в `MEDIA_HASH_FEATURE.md`
3. **UI (опционально)** - можно добавить админ-панель для управления хэшами
4. **WP-CLI (опционально)** - команды для массовой обработки

## Поддержка

Все функции протестированы на синтаксис. Для полного тестирования:
1. Активируйте плагин
2. Загрузите тестовый файл
3. Проверьте наличие мета-данных

Если возникнут проблемы, проверьте `wp-content/debug.log` при включенном `WP_DEBUG`.
