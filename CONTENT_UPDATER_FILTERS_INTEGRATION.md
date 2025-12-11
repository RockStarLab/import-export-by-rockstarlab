# Content Updater Filters Integration

## Обзор
Фильтры теперь полностью интегрированы в Content Updater и работают точно так же, как в Export модуле.

## Изменения

### Frontend (content-updater.js)

1. **nextStep()** - добавлено сохранение фильтров при переходе со Step 2:
```javascript
// Save filters when leaving step 2
if ( this.currentStep === 2 ) {
    this.selectedFilters = this.collectFilters();
    console.log( 'Saved filters:', this.selectedFilters );
}
```

2. **startUpdate()** - добавлена отправка фильтров в AJAX запросе:
```javascript
data: {
    action: 'aie_updater_start',
    nonce: aieData.nonce,
    content_type: contentType,
    fields: JSON.stringify( this.selectedFields ),
    field_functions: JSON.stringify( fieldFunctionsArray ),
    filters: JSON.stringify( this.selectedFilters || [] ), // NEW
    options: JSON.stringify( {
        items_per_iteration: itemsPerIteration
    } )
}
```

3. **refreshCount()** - уже отправляет фильтры для подсчета (было реализовано ранее).

### Backend (Content_Updater_Controller.php)

1. **get_count()** - добавлена обработка фильтров:
```php
// Get filters
$filters_json = $this->get_request_param( 'filters', '[]' );
$filters      = json_decode( $filters_json, true );
if ( ! is_array( $filters ) ) {
    $filters = [];
}

// Add filters to options
if ( ! empty( $filters ) ) {
    $options['filters'] = $filters;
}
```

2. **start_update()** - добавлена обработка и сохранение фильтров:
```php
$filters_json = $this->get_request_param( 'filters', '[]' );
$filters      = json_decode( $filters_json, true );
if ( ! is_array( $filters ) ) {
    $filters = [];
}

$options_json = $this->get_request_param( 'options', '{}' );
$options      = json_decode( $options_json, true );
if ( ! is_array( $options ) ) {
    $options = [];
}

// Add filters to options
if ( ! empty( $filters ) ) {
    $options['filters'] = $filters;
}
```

Фильтры сохраняются в `parameters` задачи через `$options`, которые затем используются в Update_Processor.

## Формат фильтров

Фильтры передаются как массив объектов:
```javascript
[
    {
        field: 'post_status',
        condition: 'equals',
        value: 'publish'
    },
    {
        field: 'post_date',
        condition: 'after',
        value: '2024-01-01'
    }
]
```

## Применение фильтров

Фильтры применяются в экспортерах через метод `apply_dynamic_filters()` в Post_Exporter:
- Базовые поля (post_status, post_type, post_date и т.д.) применяются к WP_Query
- Мета-поля (custom fields, WooCommerce поля) применяются через meta_query
- Таксономии применяются через tax_query

## Поддерживаемые условия

- **Строки**: equals, not_equals, contains, not_contains, starts_with, ends_with
- **Числа**: equals, not_equals, greater, less, equals_or_greater, equals_or_less
- **Даты**: before, after, between
- **Специальные**: is_empty, is_not_empty

## Проверка работы

1. Откройте Content Updater
2. Выберите тип контента (например, Blog Posts)
3. На Step 2 добавьте фильтр (например, post_status = publish)
4. Проверьте "Total Items to Update" - должно показать отфильтрованное количество
5. Продолжите процесс и запустите обновление
6. Проверьте, что обновляются только отфильтрованные элементы

## Отладка

В консоли браузера будут логи:
```javascript
console.log( 'Saved filters:', this.selectedFilters );
```

В логах WordPress можно увидеть применение фильтров в экспортере.
