# Тестирование автоматического добавления MD5 хэшей

## Быстрый тест

### 1. Проверка автоматического добавления хэша при загрузке

1. Откройте WordPress Admin → Медиафайлы → Добавить новый
2. Загрузите любой файл (изображение, PDF, документ)
3. После загрузки перейдите к файлу и проверьте его ID
4. В консоли браузера выполните:

```javascript
// Замените 123 на ID вашего файла
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'get_post_meta',
        post_id: 123
    }
});
```

Или через WP-CLI:
```bash
wp post meta get 123 aie_file_hash
wp post meta get 123 aie_file_size
wp post meta get 123 aie_hash_added
```

**Ожидаемый результат:** Должны быть сохранены все три meta поля с корректными значениями.

### 2. Проверка обнаружения дубликатов

1. Загрузите файл через WordPress Media Library
2. Попробуйте загрузить тот же файл еще раз (даже с другим именем)
3. Через консоль разработчика проверьте, что у обоих файлов одинаковый хэш

```javascript
// Для ID 123 и 124
wp.ajax.post('get_post_meta', {post_id: 123}).done(console.log);
wp.ajax.post('get_post_meta', {post_id: 124}).done(console.log);
```

**Ожидаемый результат:** Оба файла должны иметь одинаковый `aie_file_hash`.

### 3. Проверка статистики

В консоли браузера на странице админки WP:

```javascript
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'aie_get_hash_statistics',
        nonce: '<?php echo wp_create_nonce("aie_media_hash_stats"); ?>'
    },
    success: function(response) {
        console.log('Статистика хэшей:', response.data);
    }
});
```

**Ожидаемый результат:** JSON с количеством хэшированных и нехэшированных файлов.

### 4. Тест массового добавления хэшей

Если у вас есть старые файлы без хэшей:

```javascript
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'aie_bulk_add_hashes',
        nonce: '<?php echo wp_create_nonce("aie_bulk_add_hashes"); ?>',
        batch_size: 10,
        offset: 0
    },
    success: function(response) {
        console.log('Результат обработки:', response.data);
    }
});
```

**Ожидаемый результат:** Хэши добавлены к 10 файлам, возвращена статистика обработки.

## Полное тестирование

### Тест 1: Загрузка изображения

```bash
# Создайте тестовый файл
echo "test content" > /tmp/test-image.jpg

# Загрузите через WP-CLI
wp media import /tmp/test-image.jpg --porcelain

# Получите ID (последняя строка вывода)
ID=<полученный_id>

# Проверьте хэш
wp post meta get $ID aie_file_hash
```

**Ожидается:** Хэш должен быть `9a0364b9e99bb480dd25e1f0284c8555` (MD5 строки "test content")

### Тест 2: Загрузка PDF

```bash
# Создайте тестовый PDF
echo "%PDF test" > /tmp/test-doc.pdf

# Загрузите
ID=$(wp media import /tmp/test-doc.pdf --porcelain)

# Проверьте хэш
wp post meta get $ID aie_file_hash
wp post meta get $ID aie_file_size
```

**Ожидается:** Оба мета-поля должны быть заполнены.

### Тест 3: Поиск дубликатов через PHP

Создайте файл `test-hash.php` в корне плагина:

```php
<?php
require_once '../../../wp-load.php';

use WP_AIE\Helper\Media_Hash;

// Получить статистику
$stats = Media_Hash::get_statistics();
print_r($stats);

// Загрузить тестовый файл и проверить дубликат
$test_file = '/tmp/test-duplicate.jpg';
file_put_contents($test_file, 'duplicate test content');

// Первая загрузка
$attach_id_1 = wp_insert_attachment([
    'post_title' => 'Test File 1',
    'post_status' => 'inherit',
], $test_file);

// Принудительно добавить хэш
Media_Hash::add_hash_to_attachment_fallback($attach_id_1);

// Проверить дубликат
$duplicate = Media_Hash::find_duplicate($test_file);

if ($duplicate === $attach_id_1) {
    echo "✓ Дубликат найден корректно!\n";
} else {
    echo "✗ Ошибка: дубликат не найден\n";
}

// Очистка
wp_delete_attachment($attach_id_1, true);
unlink($test_file);
```

Запустите:
```bash
php test-hash.php
```

### Тест 4: Массовая обработка

```php
<?php
require_once '../../../wp-load.php';

use WP_AIE\Helper\Media_Hash;

// Создать несколько тестовых файлов без хэшей
for ($i = 1; $i <= 5; $i++) {
    $file = "/tmp/test-bulk-$i.jpg";
    file_put_contents($file, "test content $i");
    
    $attach_id = wp_insert_attachment([
        'post_title' => "Test Bulk $i",
        'post_status' => 'inherit',
    ], $file);
    
    echo "Создан attachment $attach_id\n";
}

// Получить статистику до
$stats_before = Media_Hash::get_statistics();
echo "До обработки: {$stats_before['unhashed']} без хэша\n";

// Обработать
$result = Media_Hash::bulk_add_hashes(10, 0);
print_r($result);

// Получить статистику после
$stats_after = Media_Hash::get_statistics();
echo "После обработки: {$stats_after['unhashed']} без хэша\n";
```

## Проверка производительности

### Бенчмарк хэширования

```php
<?php
$file = '/path/to/large/file.jpg'; // Используйте большой файл (10-50 MB)

$start = microtime(true);
$hash = md5_file($file);
$end = microtime(true);

$time = ($end - $start) * 1000; // в миллисекундах
echo "Время хэширования: {$time}ms\n";
echo "Хэш: $hash\n";
```

**Ожидаемое время:**
- Файл 1 MB: ~5-10ms
- Файл 10 MB: ~50-100ms
- Файл 50 MB: ~250-500ms

### Бенчмарк поиска

```php
<?php
use WP_AIE\Helper\Media_Hash;

$hash = 'test_hash_value_123456789';

$start = microtime(true);
$result = Media_Hash::get_attachment_by_hash($hash);
$end = microtime(true);

$time = ($end - $start) * 1000;
echo "Время поиска: {$time}ms\n";
```

**Ожидаемое время:** <50ms для любого размера медиабиблиотеки (благодаря индексации).

## Проблемы и решения

### Проблема: Хэш не добавляется

**Диагностика:**
```bash
# Проверить, что плагин активен
wp plugin list

# Проверить хуки
wp eval "print_r(array_keys(\$GLOBALS['wp_filter']['wp_generate_attachment_metadata']));"

# Проверить логи
tail -f /path/to/wordpress/wp-content/debug.log
```

**Решение:** Убедитесь, что `Media_Hash::init()` вызывается в `App::run()`.

### Проблема: Дубликаты не находятся

**Диагностика:**
```bash
# Проверить мета-данные
wp post meta list <attachment_id>

# Поиск по хэшу в БД
wp db query "SELECT * FROM wp_postmeta WHERE meta_key='aie_file_hash' LIMIT 5;"
```

**Решение:** Запустите `bulk_add_hashes()` для добавления хэшей к старым файлам.

## Интеграционное тестирование

### С Media Sync

1. Запустите Media Sync для папки с тестовыми файлами
2. Проверьте, что у всех импортированных файлов есть хэш
3. Попробуйте импортировать те же файлы снова с опцией `skip_duplicates`
4. Убедитесь, что дубликаты обнаружены

### С WP Media Library

1. Загрузите файлы через стандартный интерфейс WP
2. Проверьте наличие хэшей
3. Замените файл через "Заменить медиафайл" (если установлен плагин)
4. Проверьте, что хэш обновлен

## Очистка после тестов

```bash
# Удалить все тестовые вложения
wp post delete $(wp post list --post_type=attachment --format=ids --post_title="Test*") --force

# Удалить тестовые файлы
rm /tmp/test-*.jpg
rm /tmp/test-*.pdf

# Очистить транзиенты
wp transient delete --all
```

## Checklist тестирования

- [ ] Хэш добавляется при загрузке изображения
- [ ] Хэш добавляется при загрузке PDF
- [ ] Хэш добавляется при загрузке других файлов
- [ ] Дубликаты корректно обнаруживаются
- [ ] Статистика возвращает корректные данные
- [ ] Массовое добавление хэшей работает
- [ ] AJAX endpoints защищены nonce
- [ ] Проверка путей работает корректно
- [ ] Производительность приемлема
- [ ] Интеграция с Media Sync работает
