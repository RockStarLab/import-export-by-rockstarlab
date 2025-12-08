# Fix: wpAieAdmin is not defined

## Проблема
```
Uncaught ReferenceError: wpAieAdmin is not defined
```

## Причина
Код использовал неправильное имя глобальной переменной. В WordPress плагине переменная называется `aieData`, а не `wpAieAdmin`.

## Решение

### 1. Исправлены все ссылки на глобальную переменную

**Было:**
```javascript
url: wpAieAdmin.ajaxUrl,
nonce: wpAieAdmin.nonce
```

**Стало:**
```javascript
url: aieData.ajaxUrl,
nonce: aieData.nonce
```

### 2. Добавлены проверки наличия зависимостей

```javascript
init() {
    // Check dependencies
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded');
        return;
    }

    if (typeof aieData === 'undefined') {
        console.error('aieData is not defined. Make sure scripts are enqueued properly.');
    }
    
    // ... остальная инициализация
}
```

### 3. Добавлены проверки перед AJAX запросами

```javascript
loadFunctions() {
    // Check if aieData is available
    if (typeof aieData === 'undefined') {
        console.error('aieData is not defined');
        return;
    }

    jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        // ...
    });
}
```

## Где определяется aieData

В файле `app/Controller/Init.php` (строка ~159):

```php
wp_localize_script(
    'wp-advanced-import-export-scripts',
    'aieData',
    array(
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'aie_nonce' ),
        'pluginUrl'   => plugins_url( '', WP_AIE_FILE ),
        'currentPage' => isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '',
        // ...
    )
);
```

## Исправленные методы

1. ✅ `loadGroupFields()` - загрузка полей группы (ACF, Yoast, Meta)
2. ✅ `loadFunctions()` - загрузка доступных функций
3. ✅ `testFunctionPipeline()` - тестирование pipeline функций

## Проверка работоспособности

После исправления в консоли браузера должны исчезнуть ошибки:
- ✅ Нет `ReferenceError: wpAieAdmin is not defined`
- ✅ AJAX запросы работают корректно
- ✅ Функции загружаются
- ✅ Группы полей загружаются динамически

## Дополнительные улучшения

Добавлена обработка ошибок:
- Проверка наличия jQuery
- Проверка наличия aieData перед каждым AJAX запросом
- Информативные сообщения об ошибках в консоли

## Тестирование

1. Откройте страницу экспорта в WordPress админке
2. Откройте консоль браузера (F12)
3. Перейдите на шаг 3
4. Убедитесь, что нет ошибок о неопределенных переменных
5. Попробуйте:
   - Перетащить поле в CSV builder
   - Открыть модальное окно функций
   - Загрузить функции из библиотеки
