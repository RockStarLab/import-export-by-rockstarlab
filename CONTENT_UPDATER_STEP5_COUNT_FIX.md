# Исправление отображения количества элементов на Step 5

## Проблема
На Step 5 (Start Update) отображалось общее количество элементов без учета фильтров, примененных на Step 2.

## Решение

### 1. Добавлена переменная для хранения отфильтрованного количества

```javascript
const ContentUpdater = {
    // ...
    filteredCount: null,  // Store filtered item count
    // ...
};
```

### 2. Обновлен метод `refreshCount()` - сохраняет количество

Когда пользователь меняет фильтры на Step 2, количество сохраняется:

```javascript
success: ( response ) => {
    $spinner.removeClass( 'is-active' );
    if ( response.success ) {
        $countValue.text( response.data.count );
        // Save the filtered count for later use
        this.filteredCount = response.data.count;
    }
}
```

### 3. Обновлен метод `getItemCount()` - учитывает фильтры

Добавлена отправка фильтров в AJAX запросе:

```javascript
const filters = this.selectedFilters || [];

jQuery.ajax( {
    url: aieData.ajaxUrl,
    method: 'POST',
    data: {
        action: 'aie_updater_get_count',
        nonce: aieData.nonce,
        content_type: contentType,
        filters: JSON.stringify( filters ),  // NEW
        options: {}
    },
    success: ( response ) => {
        if ( response.success ) {
            $countValue.text( response.data.count );
            // Save the filtered count
            this.filteredCount = response.data.count;
        }
    }
} );
```

### 4. Оптимизирован метод `prepareUpdateSummary()`

Использует сохраненное количество, если оно доступно (избегает лишнего AJAX запроса):

```javascript
prepareUpdateSummary() {
    // ...
    
    // If we already have a filtered count from Step 2, use it
    if ( this.filteredCount !== undefined && this.filteredCount !== null ) {
        jQuery( '.aie-total-items-summary' ).text( this.filteredCount );
    } else {
        this.getItemCount();
    }
}
```

### 5. Сброс при изменении типа контента

```javascript
onContentTypeChange( e ) {
    // ...
    // Reset selections for new content type
    this.selectedFields = [];
    this.fieldFunctions = {};
    this.selectedFilters = [];
    this.filteredCount = null;  // Reset filtered count
}
```

## Как это работает

1. **Step 2**: Пользователь добавляет фильтр
2. `refreshCount()` запрашивает количество с фильтрами
3. Количество сохраняется в `this.filteredCount`
4. **Step 3-4**: Пользователь выбирает поля и функции
5. **Step 5**: `prepareUpdateSummary()` использует сохраненное `this.filteredCount`
6. Отображается правильное отфильтрованное количество

## Преимущества

- ✅ Правильное отображение количества с учетом фильтров
- ✅ Оптимизация: не делается лишний AJAX запрос на Step 5
- ✅ Количество синхронизировано между Step 2 и Step 5
- ✅ Сброс при изменении типа контента

## Тестирование

1. Выберите тип контента (например, Blog Posts)
2. На Step 2 добавьте фильтр (например, post_status = publish)
3. Проверьте количество на Step 2: например, "94 items"
4. Перейдите на Step 3, 4, 5
5. На Step 5 в "Total Items to Update" должно быть то же количество: "94"
6. Запустите обновление - должно обновиться ровно 94 элемента
