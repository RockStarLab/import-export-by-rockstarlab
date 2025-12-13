# Import: Dynamic ACF & Yoast Fields Implementation

## Обзор изменений

Реализована динамическая загрузка ACF и Yoast SEO полей для импорта, полностью соответствующая реализации в export модуле.

---

## 🎯 Что было сделано

### 1. **PHP Backend (Import_Controller.php)**

Добавлены два новых AJAX обработчика:

#### `get_acf_fields()`
- Проверяет наличие ACF (`acf_get_field_groups`)
- Получает группы полей для конкретного post_type
- Поддержка mapping для WooCommerce типов:
  - `woo_product` → `product`
  - `woo_order` → `shop_order`
  - `woo_coupon` → `shop_coupon`
- Поддержка user fields (`user_form = all`)
- Поддержка media/attachment fields (`attachment = all`)
- Возвращает массив полей: `name`, `label`, `type`

#### `get_yoast_fields()`
- Проверяет наличие Yoast SEO (`WPSEO_VERSION`)
- Возвращает стандартный набор из 12 полей:
  - SEO Title, Meta Description, Focus Keyword
  - Canonical URL, Robots (Index/Follow)
  - Open Graph (Title, Description, Image)
  - Twitter Cards (Title, Description, Image)

**Файл:** `/app/Controller/Import_Controller.php`

```php
protected function get_ajax_actions() {
    return [
        // ... existing actions
        'get_acf_fields'       => [ 'callback' => 'get_acf_fields' ],
        'get_yoast_fields'     => [ 'callback' => 'get_yoast_fields' ],
    ];
}
```

---

### 2. **JavaScript Frontend (import.js)**

#### Удалены статические поля
Убраны все статические ACF и Yoast поля из метода `getFieldsByContentType()`:
- ❌ Удалена секция "ACF Fields" со всеми типами полей
- ❌ Удалена секция "Yoast SEO" со всеми полями
- ✅ Оставлена только секция "Custom Fields (Meta)" для произвольных полей

#### Добавлены методы динамической загрузки

**`loadACFFields(contentType)`**
```javascript
// AJAX запрос к aie_get_acf_fields
// Автоматически вызывается в buildFieldMapping()
// Рендерит ACF поля если они найдены
```

**`renderACFFields(fields)`**
```javascript
// Создает группу "🔧 ACF Fields"
// Добавляет поля с префиксом acf_
// Иконка: dashicons-admin-settings
// Badge: acf:{type}
```

**`loadYoastFields(contentType)`**
```javascript
// AJAX запрос к aie_get_yoast_fields
// Проверяет excluded types (media, user, menu, etc.)
// Автоматически вызывается в buildFieldMapping()
```

**`renderYoastFields(fields)`**
```javascript
// Создает группу "📊 Yoast SEO"
// Убирает _ префикс из имен полей
// Иконка: dashicons-chart-line
// Badge: yoast
```

#### Обновлен buildFieldMapping()
```javascript
buildFieldMapping() {
    // ... existing code
    this.buildTargetFields(contentType);
    
    // Load dynamic ACF fields
    this.loadACFFields(contentType);
    
    // Load dynamic Yoast fields  
    this.loadYoastFields(contentType);
    
    // ... rest of code
}
```

---

### 3. **Валидация маппинга**

Добавлена валидация на Step 4 - кнопка "Next" disabled пока нет маппингов:

#### Изменения в `showStep()`
```javascript
if (step === 4) {
    // Initially disable Next button
    jQuery('.aie-wizard-nav .aie-btn-primary')
        .prop('disabled', true)
        .addClass('disabled');
    
    this.buildFieldMapping();
}
```

#### Изменения в `updateMappingStats()`
```javascript
// Disable/enable Next button based on mapping count
if (this.currentStep === 4) {
    const $nextButton = jQuery('.aie-wizard-nav .aie-btn-primary');
    if (mappedCount === 0) {
        $nextButton.prop('disabled', true).addClass('disabled');
    } else {
        $nextButton.prop('disabled', false).removeClass('disabled');
    }
}
```

---

## 📋 Поддерживаемые типы контента

### ACF поля загружаются для:
- ✅ Posts (`post`)
- ✅ Pages (`page`)
- ✅ Users (`user`)
- ✅ Media/Attachment (`media`, `attachment`)
- ✅ WooCommerce Products (`woo_product` → `product`)
- ✅ WooCommerce Orders (`woo_order` → `shop_order`)
- ✅ WooCommerce Coupons (`woo_coupon` → `shop_coupon`)
- ✅ Custom Post Types

### Yoast поля загружаются для:
- ✅ Posts
- ✅ Pages
- ✅ WooCommerce Products
- ✅ Custom Post Types

### Yoast НЕ загружается для:
- ❌ Media
- ❌ User
- ❌ Menu
- ❌ Block Theme Settings
- ❌ Taxonomy
- ❌ Database Table
- ❌ WooCommerce Attributes
- ❌ WooCommerce Coupons
- ❌ WooCommerce Orders

---

## 🎨 UI элементы

### ACF Fields Group
```html
<div class="aie-field-group aie-acf-fields-group">
    <div class="aie-field-group-label">🔧 ACF Fields</div>
    <!-- Fields here -->
</div>
```

### Yoast SEO Group
```html
<div class="aie-field-group aie-yoast-fields-group">
    <div class="aie-field-group-label">📊 Yoast SEO</div>
    <!-- Fields here -->
</div>
```

### Field Card (ACF)
```html
<div class="aie-target-field" 
     data-target-field="acf_field_name" 
     data-field-type="text">
    <div class="aie-field-icon">
        <span class="dashicons dashicons-admin-settings"></span>
    </div>
    <div class="aie-field-info">
        <div class="aie-field-label">Field Label</div>
        <span class="aie-field-type-badge">acf:text</span>
    </div>
</div>
```

### Field Card (Yoast)
```html
<div class="aie-target-field" 
     data-target-field="yoast_wpseo_title" 
     data-field-type="string">
    <div class="aie-field-icon">
        <span class="dashicons dashicons-chart-line"></span>
    </div>
    <div class="aie-field-info">
        <div class="aie-field-label">SEO Title</div>
        <span class="aie-field-type-badge">yoast</span>
    </div>
</div>
```

---

## 🔄 Workflow

### Пользовательский сценарий:

1. **Step 1:** Выбор типа контента (Post, User, Product, etc.)
2. **Step 2:** Загрузка файла (CSV/JSON)
3. **Step 3:** Просмотр превью данных
4. **Step 4:** Field Mapping
   - Статические поля отображаются сразу (Standard, Author, Taxonomy, etc.)
   - **AJAX запрос** → загрузка ACF полей (если ACF активен и есть поля)
   - **AJAX запрос** → загрузка Yoast полей (если Yoast активен)
   - ACF/Yoast группы добавляются в Target Fields
   - Пользователь делает drag & drop маппинг
   - Кнопка Next активируется только после создания хотя бы одного маппинга
5. **Step 5:** Import options
6. **Step 6:** Import execution

---

## 🔧 Технические детали

### AJAX Endpoints:
- `aie_get_acf_fields` - получение ACF полей
- `aie_get_yoast_fields` - получение Yoast полей

### Nonce Verification:
Оба метода используют `verify_request('import_fields')`

### Error Handling:
- Если ACF не установлен → возвращает пустой массив
- Если Yoast не установлен → возвращает пустой массив
- AJAX errors логируются в консоль

### Performance:
- Поля загружаются асинхронно
- Не блокирует отображение статических полей
- Кешируется на стороне клиента (пока не перезагрузится Step 4)

---

## ✅ Результат

### Преимущества:
1. ✅ **Динамические поля:** ACF и Yoast загружаются только если плагины активны
2. ✅ **Соответствие export:** Идентичная логика с export модулем
3. ✅ **Гибкость:** Автоматически подхватывает все зарегистрированные ACF поля
4. ✅ **Валидация:** Невозможно перейти дальше без маппинга
5. ✅ **UX:** Четкие иконки и badges для идентификации типов полей

### Что дальше:
- Step 5: Import Options (update existing, skip duplicates, etc.)
- Step 6: Import Execution (batch processing, progress bar)
- Error handling & logging
- Import history & rollback

---

## 📝 Пример использования

### ACF Field Mapping:
```
CSV Column: "company_name"  →  ACF Field: "company" (acf:text)
CSV Column: "logo_url"      →  ACF Field: "logo" (acf:image)
CSV Column: "team_members"  →  ACF Field: "team" (acf:repeater)
```

### Yoast Field Mapping:
```
CSV Column: "seo_title"       →  Yoast: "SEO Title"
CSV Column: "meta_desc"       →  Yoast: "Meta Description"
CSV Column: "focus_keyword"   →  Yoast: "Focus Keyword"
CSV Column: "og_image"        →  Yoast: "Facebook Image"
```

---

## 🎉 Итог

Импорт теперь полностью поддерживает динамическую загрузку ACF и Yoast полей, работает идентично экспорту, и включает валидацию маппинга!
