# Примеры пользовательских функций

Это коллекция готовых примеров функций для обработки данных при импорте/экспорте.

## 📚 Содержание

1. [Строковые операции](#строковые-операции)
2. [Работа с датами](#работа-с-датами)
3. [Числовые операции](#числовые-операции)
4. [Работа с HTML](#работа-с-html)
5. [Email и URL](#email-и-url)
6. [WordPress специфичные](#wordpress-специфичные)
7. [Сложные трансформации](#сложные-трансформации)

---

## Строковые операции

### 1. Преобразование в верхний регистр
```php
return strtoupper($value);
```
**Вход:** `hello world`  
**Выход:** `HELLO WORLD`

---

### 2. Преобразование в нижний регистр
```php
return strtolower($value);
```
**Вход:** `HELLO WORLD`  
**Выход:** `hello world`

---

### 3. Первая буква заглавная
```php
return ucfirst(strtolower($value));
```
**Вход:** `hELLO wORLD`  
**Выход:** `Hello world`

---

### 4. Первая буква каждого слова заглавная
```php
return ucwords(strtolower($value));
```
**Вход:** `hELLO wORLD`  
**Выход:** `Hello World`

---

### 5. Удаление пробелов по краям
```php
return trim($value);
```
**Вход:** `  hello world  `  
**Выход:** `hello world`

---

### 6. Удаление множественных пробелов
```php
return preg_replace('/\s+/', ' ', trim($value));
```
**Вход:** `hello    world`  
**Выход:** `hello world`

---

### 7. Замена символов
```php
// Заменить дефисы на пробелы
return str_replace('-', ' ', $value);
```
**Вход:** `hello-world-test`  
**Выход:** `hello world test`

---

### 8. Обрезка строки
```php
// Обрезать до 100 символов
if (strlen($value) > 100) {
    return substr($value, 0, 97) . '...';
}
return $value;
```

---

### 9. Удаление специальных символов
```php
return preg_replace('/[^a-zA-Z0-9\s]/', '', $value);
```
**Вход:** `Hello! @World# 2024`  
**Выход:** `Hello World 2024`

---

### 10. Генерация slug
```php
return sanitize_title($value);
```
**Вход:** `Hello World! 2024`  
**Выход:** `hello-world-2024`

---

## Работа с датами

### 11. Форматирование даты (MySQL формат)
```php
$timestamp = strtotime($value);
if ($timestamp === false) {
    return $value;
}
return date('Y-m-d H:i:s', $timestamp);
```
**Вход:** `12/31/2024`  
**Выход:** `2024-12-31 00:00:00`

---

### 12. Конвертация DD/MM/YYYY в YYYY-MM-DD
```php
$parts = explode('/', $value);
if (count($parts) === 3) {
    return sprintf('%s-%s-%s', $parts[2], $parts[1], $parts[0]);
}
return $value;
```
**Вход:** `31/12/2024`  
**Выход:** `2024-12-31`

---

### 13. Добавление времени к дате
```php
$date = strtotime($value);
if ($date === false) {
    return $value;
}
// Добавить текущее время
return date('Y-m-d H:i:s', $date);
```

---

### 14. Относительная дата
```php
// Добавить 7 дней к дате
$date = strtotime($value);
if ($date === false) {
    return $value;
}
return date('Y-m-d', strtotime('+7 days', $date));
```

---

### 15. Извлечение года из даты
```php
$date = strtotime($value);
if ($date === false) {
    return '';
}
return date('Y', $date);
```
**Вход:** `2024-12-31`  
**Выход:** `2024`

---

## Числовые операции

### 16. Преобразование в целое число
```php
return intval($value);
```
**Вход:** `123.45`  
**Выход:** `123`

---

### 17. Преобразование в число с плавающей точкой
```php
return floatval($value);
```
**Вход:** `123,45`  
**Выход:** `123`

---

### 18. Форматирование цены
```php
// Удалить символы валюты и пробелы
$cleaned = preg_replace('/[^0-9.,]/', '', $value);
// Заменить запятую на точку
$cleaned = str_replace(',', '.', $cleaned);
return floatval($cleaned);
```
**Вход:** `$1,234.56`  
**Выход:** `1234.56`

---

### 19. Округление числа
```php
return round(floatval($value), 2);
```
**Вход:** `123.456789`  
**Выход:** `123.46`

---

### 20. Форматирование числа с разделителями
```php
$number = floatval($value);
return number_format($number, 2, '.', ',');
```
**Вход:** `1234.56`  
**Выход:** `1,234.56`

---

### 21. Процентное значение
```php
// Конвертировать десятичное в проценты
return round(floatval($value) * 100, 2) . '%';
```
**Вход:** `0.1234`  
**Выход:** `12.34%`

---

### 22. Абсолютное значение
```php
return abs(floatval($value));
```
**Вход:** `-123.45`  
**Выход:** `123.45`

---

## Работа с HTML

### 23. Удаление всех HTML тегов
```php
return strip_tags($value);
```
**Вход:** `<p>Hello <strong>World</strong></p>`  
**Выход:** `Hello World`

---

### 24. Удаление HTML с сохранением определенных тегов
```php
return strip_tags($value, '<p><br><strong><em>');
```

---

### 25. Декодирование HTML entities
```php
return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
```
**Вход:** `Hello &amp; World`  
**Выход:** `Hello & World`

---

### 26. Экранирование HTML
```php
return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
```
**Вход:** `<script>alert('XSS')</script>`  
**Выход:** `&lt;script&gt;alert('XSS')&lt;/script&gt;`

---

### 27. Извлечение текста из HTML
```php
$text = strip_tags($value);
return preg_replace('/\s+/', ' ', trim($text));
```

---

## Email и URL

### 28. Валидация и очистка email
```php
$email = filter_var($value, FILTER_SANITIZE_EMAIL);
if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return strtolower($email);
}
return '';
```
**Вход:** `Test@Example.COM`  
**Выход:** `test@example.com`

---

### 29. Извлечение домена из email
```php
if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
    return substr(strrchr($value, "@"), 1);
}
return '';
```
**Вход:** `user@example.com`  
**Выход:** `example.com`

---

### 30. Валидация URL
```php
$url = filter_var($value, FILTER_SANITIZE_URL);
if (filter_var($url, FILTER_VALIDATE_URL)) {
    return $url;
}
return '';
```

---

### 31. Добавление протокола к URL
```php
if (!preg_match('/^https?:\/\//', $value)) {
    return 'https://' . $value;
}
return $value;
```
**Вход:** `example.com`  
**Выход:** `https://example.com`

---

### 32. Извлечение домена из URL
```php
$parsed = parse_url($value);
return isset($parsed['host']) ? $parsed['host'] : '';
```
**Вход:** `https://www.example.com/path`  
**Выход:** `www.example.com`

---

## WordPress специфичные

### 33. Поиск пользователя по email
```php
if (empty($value)) {
    return 0;
}

$user = get_user_by('email', $value);
return $user ? $user->ID : 0;
```
**Вход:** `user@example.com`  
**Выход:** `123` (ID пользователя)

---

### 34. Поиск пользователя по имени
```php
if (empty($value)) {
    return 0;
}

$user = get_user_by('login', $value);
return $user ? $user->ID : 0;
```

---

### 35. Поиск категории по slug
```php
if (empty($value)) {
    return 0;
}

$term = get_term_by('slug', sanitize_title($value), 'category');
return $term ? $term->term_id : 0;
```

---

### 36. Создание категории если не существует
```php
if (empty($value)) {
    return 0;
}

$term = get_term_by('name', $value, 'category');
if ($term) {
    return $term->term_id;
}

$new_term = wp_insert_term($value, 'category');
return is_wp_error($new_term) ? 0 : $new_term['term_id'];
```

---

### 37. Поиск поста по заголовку
```php
if (empty($value)) {
    return 0;
}

$post = get_page_by_title($value, OBJECT, 'post');
return $post ? $post->ID : 0;
```

---

### 38. Sanitize текстового поля
```php
return sanitize_text_field($value);
```

---

### 39. Sanitize textarea
```php
return sanitize_textarea_field($value);
```

---

### 40. Создание excerpt из контента
```php
$text = strip_tags($value);
if (strlen($text) > 150) {
    return substr($text, 0, 147) . '...';
}
return $text;
```

---

## Сложные трансформации

### 41. Конкатенация нескольких полей
```php
// $context содержит все поля текущей строки
$first_name = isset($context['first_name']) ? $context['first_name'] : '';
$last_name = isset($context['last_name']) ? $context['last_name'] : '';
return trim($first_name . ' ' . $last_name);
```
**Использование:** Объединить имя и фамилию в одно поле

---

### 42. Условное значение
```php
// Если значение "Yes", вернуть 1, иначе 0
return (strtolower($value) === 'yes' || $value === '1') ? 1 : 0;
```
**Вход:** `Yes`, `yes`, `1`  
**Выход:** `1`

---

### 43. Маппинг значений
```php
$mapping = [
    'active' => 'publish',
    'inactive' => 'draft',
    'pending' => 'pending'
];

$key = strtolower($value);
return isset($mapping[$key]) ? $mapping[$key] : 'draft';
```
**Вход:** `active`  
**Выход:** `publish`

---

### 44. Разбивка строки в массив
```php
// Разбить по запятой и очистить
$items = array_map('trim', explode(',', $value));
return array_filter($items);
```
**Вход:** `apple, banana, orange`  
**Выход:** `['apple', 'banana', 'orange']`

---

### 45. Извлечение числа из строки
```php
preg_match('/\d+/', $value, $matches);
return !empty($matches) ? intval($matches[0]) : 0;
```
**Вход:** `Order #12345`  
**Выход:** `12345`

---

### 46. Проверка на пустоту с дефолтным значением
```php
return !empty($value) ? $value : 'Default Value';
```

---

### 47. Преобразование булевых значений
```php
$true_values = ['yes', 'true', '1', 'on', 'enabled'];
$false_values = ['no', 'false', '0', 'off', 'disabled'];

$lower = strtolower(trim($value));

if (in_array($lower, $true_values)) {
    return true;
}
if (in_array($lower, $false_values)) {
    return false;
}
return null;
```

---

### 48. Генерация уникального SKU
```php
// Префикс + timestamp + случайное число
$prefix = 'SKU';
$timestamp = time();
$random = rand(1000, 9999);
return $prefix . '-' . $timestamp . '-' . $random;
```
**Выход:** `SKU-1701234567-3456`

---

### 49. Извлечение имени файла из URL
```php
$path = parse_url($value, PHP_URL_PATH);
return $path ? basename($path) : '';
```
**Вход:** `https://example.com/images/photo.jpg`  
**Выход:** `photo.jpg`

---

### 50. Форматирование телефона
```php
// Удалить все кроме цифр
$phone = preg_replace('/[^0-9]/', '', $value);

// Форматировать как (XXX) XXX-XXXX
if (strlen($phone) === 10) {
    return sprintf('(%s) %s-%s',
        substr($phone, 0, 3),
        substr($phone, 3, 3),
        substr($phone, 6)
    );
}

return $phone;
```
**Вход:** `123-456-7890`  
**Выход:** `(123) 456-7890`

---

## 📝 Советы по использованию

### Безопасность:
- ✅ Всегда валидируйте входные данные
- ✅ Используйте WordPress функции sanitize_*
- ✅ Избегайте прямых SQL запросов
- ✅ Не используйте `eval()`, `exec()`, файловые операции

### Производительность:
- ⚡ Функции выполняются для каждой строки
- ⚡ Избегайте сложных операций в цикле
- ⚡ Используйте кеширование для lookup операций
- ⚡ Таймаут выполнения: 5 секунд

### Отладка:
- 🔍 Используйте функцию Test в редакторе
- 🔍 Проверяйте логи при ошибках
- 🔍 Тестируйте на маленьком файле сначала

### Доступные переменные:
- `$value` - текущее значение поля
- `$context` - массив всех полей текущей строки
- `$context['_row_index']` - номер строки (если доступно)

---

**Версия документа**: 1.0.0  
**Дата создания**: 27 ноября 2025
