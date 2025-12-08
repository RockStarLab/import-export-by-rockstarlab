# Отладка AJAX Endpoints

## Проверка что делать если возвращается 0

Если AJAX запросы возвращают `0`, это означает что WordPress не может найти обработчик.

### Шаг 1: Проверить что Export_Controller инициализирован

Добавьте временный debug код в `app/Controller/Export_Controller.php` в конструктор:

```php
public function __construct() {
    error_log('Export_Controller initialized');
    parent::__construct();
}
```

### Шаг 2: Проверить что actions зарегистрированы

Добавьте debug в `app/Controller/Base_Controller.php` метод `init()`:

```php
public function init() {
    $actions = $this->get_ajax_actions();
    error_log('Registering AJAX actions: ' . print_r(array_keys($actions), true));
    
    foreach ( $actions as $action => $config ) {
        $callback = $config['callback'] ?? $action;
        $nopriv   = $config['nopriv'] ?? false;
        
        error_log('Registering: wp_ajax_aie_' . $action . ' -> ' . $callback);

        // Admin AJAX
        add_action( 'wp_ajax_aie_' . $action, [ $this, $callback ] );

        // Non-admin AJAX (if allowed)
        if ( $nopriv ) {
            add_action( 'wp_ajax_nopriv_aie_' . $action, [ $this, $callback ] );
        }
    }
}
```

### Шаг 3: Проверить что методы вызываются

Добавьте debug в начале каждого метода:

```php
public function get_taxonomies() {
    error_log('get_taxonomies called');
    error_log('POST data: ' . print_r($_POST, true));
    
    $verification = $this->verify_request( 'export_fields' );
    // ... остальной код
}
```

### Шаг 4: Проверить WordPress error log

```bash
tail -f /home/brovatar/Local\ Sites/wp-advanced-import-export/app/public/wp-content/debug.log
```

### Шаг 5: Проверить browser console

Откройте Developer Tools (F12) и проверьте:
1. Network tab - что отправляется в запросе
2. Console - что возвращается

### Возможные причины проблемы:

1. **Export_Controller не инициализирован**
   - Проверить что Init.php вызывает `$this->export_controller->init()`

2. **Nonce не совпадает**
   - Проверить что используется `aie_nonce` везде

3. **Capability check fails**
   - Убедиться что у пользователя есть права `manage_options`

4. **WordPress не видит action**
   - Проверить что файл Export_Controller.php загружается
   - Проверить что autoload работает

### Временное решение для теста:

Добавьте в `wp-advanced-import-export.php` прямо после подключения autoload:

```php
add_action('wp_ajax_aie_get_taxonomies', function() {
    error_log('Direct test handler called');
    wp_send_json_success([
        'test' => 'Direct handler works',
        'taxonomies' => []
    ]);
});
```

Если это работает - значит проблема в инициализации Export_Controller.
Если не работает - значит проблема в самом WordPress или конфигурации.

### Проверить что класс загружен:

Добавьте в любое место после инициализации:

```php
add_action('admin_init', function() {
    error_log('Export_Controller exists: ' . class_exists('WP_AIE\\Controller\\Export_Controller') ? 'YES' : 'NO');
});
```
