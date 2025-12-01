# Отладка проблемы с обновлением статистики Media Sync

## Проблема
Статистика (Processed, Success, Skipped, Failed) и прогресс не обновляются в UI во время синхронизации.

## Добавленное логирование

### 1. Frontend (JavaScript Console)
Откройте DevTools (F12) → Console и следите за логами:

```javascript
=== Progress Response ===
  Status: processing
  Progress: 25
  Result (raw): {"processed":20,"success":18,"skipped":2,"failed":0}
  Result type: object

=== Update Progress Called ===
  Raw data: {status: "processing", progress: 25, result: {...}}
  Parsed progress: 25
  Status: processing
  Result (before parse): {processed: 20, success: 18, ...}
  Result type: object
  Final result object: {processed: 20, success: 18, skipped: 2, failed: 0}
  Processed: 20
  Success: 18
  Skipped: 2
  Failed: 0
  Setting values: {processed: 20, success: 18, skipped: 2, failed: 0}
  DOM updated
  Setting status text: Processing files...
```

### 2. Backend (PHP Error Log)
Проверьте файл error_log WordPress:

**Локация (Local by Flywheel):**
```bash
tail -f ~/Local\ Sites/wp-advanced-import-export/app/public/wp-content/debug.log
```

**Или стандартный PHP error log:**
```bash
tail -f /var/log/apache2/error.log
# или
tail -f /var/log/php-fpm/error.log
```

**Ожидаемые логи:**
```
[Media Sync] Updating job #123: progress=25%, result={"processed":20,"success":18,"skipped":2,"failed":0}
[Media Sync] Get progress for job #123: status=processing, progress=25, result={"processed":20,"success":18,"skipped":2,"failed":0}
```

## Диагностика

### Шаг 1: Проверить что данные сохраняются в БД
```sql
-- Проверить последнюю джобу
SELECT id, status, progress, result 
FROM wp_aie_jobs 
WHERE type = 'media_sync' 
ORDER BY id DESC 
LIMIT 1;
```

**Ожидаемый результат:**
```
id  | status     | progress | result
123 | processing | 25.00    | {"processed":20,"success":18,"skipped":2,"failed":0}
```

**Если result = NULL или пустая строка:**
- Проблема в процессоре - данные не сохраняются
- Проверьте логи PHP на ошибки при обновлении

### Шаг 2: Проверить что данные приходят из API
В Console DevTools выполните:
```javascript
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'aie_get_sync_progress',
        nonce: window.aieData.nonce,
        job_id: YOUR_JOB_ID // Замените на реальный ID
    }
}).done(function(response) {
    console.log('API Response:', response);
});
```

**Ожидаемый результат:**
```javascript
{
    success: true,
    data: {
        status: "processing",
        progress: "25.00",
        result: {
            processed: 20,
            success: 18,
            skipped: 2,
            failed: 0
        }
    }
}
```

**Если result = null:**
- Проблема в контроллере или модели Job
- Проверьте что колонка `result` в БД содержит данные

### Шаг 3: Проверить что DOM-элементы существуют
В Console DevTools:
```javascript
jQuery('#aie-stat-processed').length  // Должно быть 1
jQuery('#aie-stat-success').length    // Должно быть 1
jQuery('#aie-stat-skipped').length    // Должно быть 1
jQuery('#aie-stat-failed').length     // Должно быть 1
jQuery('#aie-sync-status').length     // Должно быть 1
```

**Если 0:**
- HTML-структура не соответствует селекторам
- Проверьте template файл

### Шаг 4: Проверить что обработка запущена
```sql
-- Проверить логи обработки
SELECT * FROM wp_aie_logs 
WHERE job_id = YOUR_JOB_ID 
ORDER BY created_at DESC 
LIMIT 10;
```

**Ожидаемые записи:**
```
Batch completed. Progress: 25%. Total: Processed: 20, Success: 18, Skipped: 2, Failed: 0
Processing batch: files 1-20 of 80
Imported: photo1.jpg (ID: 456)
```

**Если нет записей:**
- Async processing не запустилась
- Проверьте что `wp_remote_post` работает (могут быть проблемы с loopback requests)

## Возможные проблемы и решения

### 1. Статус остается "pending"
**Причина:** Обработка не запускается  
**Решение:**
```php
// Добавьте в wp-config.php для отладки
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Проверьте что loopback requests работают
$response = wp_remote_post(admin_url('admin-ajax.php'), [
    'timeout' => 5,
    'blocking' => true,
    'body' => ['action' => 'aie_process_media_sync_batch', 'job_id' => 123]
]);
var_dump($response);
```

### 2. Result = null в БД
**Причина:** Процессор не обновляет result  
**Решение:** Проверьте строку 217-231 в `Media_Sync_Processor.php`:
```php
error_log('[Media Sync] cumulative_result = ' . print_r($cumulative_result, true));
```

### 3. Result не парсится в JS
**Причина:** Неправильный формат JSON  
**Решение:** Проверьте в Console:
```javascript
// Если result - строка, должен быть валидный JSON
JSON.parse(response.data.result)
```

### 4. Progress = 0 всегда
**Причина:** $total_files = 0 или не сохраняется  
**Решение:** Проверьте settings джобы:
```sql
SELECT settings FROM wp_aie_jobs WHERE id = YOUR_JOB_ID;
```
Должно содержать `"total_files":80`

## Быстрый тест

1. Запустите синхронизацию
2. Откройте Console (F12)
3. Через 5 секунд вы должны увидеть:
   - `=== Progress Response ===` каждые 2 секунды
   - `Result (raw): {processed: X, ...}` с ненулевыми значениями
   - `DOM updated` подтверждение

4. Если логов нет или result = null:
   - Перейдите к диагностике выше
   - Проверьте PHP error log
   - Проверьте БД

## Включение WP_DEBUG

Добавьте в `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Логи будут в `wp-content/debug.log`
