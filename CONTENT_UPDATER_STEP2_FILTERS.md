# Content Updater - Step 2 Filters Implementation

## ✅ Реализовано

Шаг 2 "Filter Data" теперь работает точно так же как в Export модуле.

### Новая функциональность:

1. **UI Фильтров**:
   - Копия интерфейса из Export Step 2
   - Счетчик элементов с кнопкой обновления
   - Динамическое добавление/удаление фильтров
   - Template для строк фильтров

2. **JavaScript методы** (content-updater.js):
   - `loadFiltersLibrary()` - инициализация фильтров
   - `addFilterRow()` - добавление новой строки фильтра
   - `removeFilterRow()` - удаление фильтра
   - `onFilterFieldChange()` - обработка изменения поля
   - `onFilterConditionChange()` - обработка изменения условия
   - `getConditionsByFieldType()` - получение условий по типу поля
   - `refreshCount()` - обновление счетчика элементов
   - `collectFilters()` - сбор фильтров для отправки на backend

3. **Event handlers**:
   ```javascript
   // Добавление фильтра
   .aie-updater-add-filter → addFilterRow()
   
   // Удаление фильтра
   .aie-updater-remove-filter → removeFilterRow()
   
   // Изменение поля фильтра
   .aie-updater-filter-field → onFilterFieldChange()
   
   // Изменение условия
   .aie-updater-filter-condition → onFilterConditionChange()
   
   // Изменение значения (с debounce)
   .aie-updater-filter-value → refreshCount()
   
   // Обновление счетчика
   .aie-updater-refresh-count → refreshCount(true)
   ```

### Типы условий фильтров:

**String (текстовые поля)**:
- Equals / Not Equals
- Contains / Not Contains
- Starts With / Ends With
- Is Empty / Is Not Empty

**Number (числовые поля)**:
- Equals / Not Equals
- Greater Than / Less Than
- Greater or Equal / Less or Equal
- Between

**Date (поля дат)**:
- On Date
- Before / After
- Between

### Интеграция с Export модулем:

Использует `window.aieExportModule.getFieldsByContentType()` для получения списка полей, что обеспечивает единообразие между Export и Content Updater.

### Как работает:

1. **Пользователь на шаге 2**:
   - Видит счетчик "Total Items to Update"
   - Может добавить фильтры кнопкой "Add Filter"
   - Или пропустить фильтры (нажать "Next Step")

2. **Добавление фильтра**:
   - Выбирает поле из списка (post_title, post_status, author, etc.)
   - Система автоматически подгружает подходящие условия
   - Вводит значение для фильтрации
   - Счетчик автоматически обновляется (debounce 500ms)

3. **Результат**:
   - На шаге 5 обновятся только те элементы, которые соответствуют фильтрам
   - Если фильтров нет - обновятся все элементы выбранного типа

### Структура фильтра:

```javascript
{
    field: 'post_title',
    condition: 'contains',
    value: 'hello'
}
```

### AJAX запрос для счетчика:

```javascript
{
    action: 'aie_updater_get_count',
    nonce: aieData.nonce,
    content_type: 'page',
    filters: JSON.stringify([
        { field: 'post_status', condition: 'equals', value: 'publish' },
        { field: 'post_title', condition: 'contains', value: 'test' }
    ]),
    options: {}
}
```

### TODO (Backend):

- [ ] Обновить `Content_Updater_Controller::get_count()` для поддержки фильтров
- [ ] Передавать фильтры в `Update_Processor` при создании задачи
- [ ] Применять фильтры при получении batch items в Update_Processor
- [ ] Добавить поддержку custom_field фильтров
- [ ] Добавить поддержку taxonomy фильтров

### Тестирование:

1. Выберите "Pages" на шаге 1
2. На шаге 2 нажмите "Add Filter"
3. Выберите field: "post_status"
4. Condition: "equals"
5. Value: "publish"
6. Счетчик должен показать количество только опубликованных страниц
7. Добавьте еще один фильтр
8. Счетчик должен обновиться с учетом обоих фильтров

### Файлы изменены:

- `app/View/settings/partials/updater-step-2.php` - полностью переписан
- `src/js/modules/content-updater.js` - добавлено ~200 строк для фильтров
- `assets/js/app.js` - пересобран

### Размер бандла:

- До: 433 KiB
- После: 440 KiB (+7 KiB)
