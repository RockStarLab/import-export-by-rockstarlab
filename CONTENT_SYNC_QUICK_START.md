# Content Sync - Quick Start Guide

## 🎯 Что реализовано

Полностью готовый админ экран для управления подключениями между WordPress сайтами для синхронизации контента.

## 📦 Компоненты

### Backend
- ✅ **Model**: `app/Model/Connected_Site.php` - CRUD операции
- ✅ **Controller**: `app/Controller/Content_Sync_Controller.php` - AJAX обработка
- ✅ **View**: `app/View/settings/content_sync.php` - Админ интерфейс
- ✅ **Database**: Таблица `wp_aie_site_connections` (уже создана)

### Frontend
- ✅ **JavaScript**: `src/js/content-sync.js` - Логика UI
- ✅ **Styles**: `src/scss/content-sync.scss` - Оформление
- ✅ **Assets**: Скомпилированы в `assets/js/app.js` и `assets/css/app.css`

### Integration
- ✅ **Init**: Контроллер зарегистрирован в `app/Controller/Init.php`
- ✅ **Menu**: Пункт меню "Content Sync" уже добавлен
- ✅ **Localization**: Nonce для AJAX запросов

## 🚀 Как использовать

### 1. Доступ к странице
```
WordPress Admin → Advanced Import Export → Content Sync
```

### 2. Подключение двух сайтов

**На Сайте A (который будет подключаться):**
1. Открыть Content Sync
2. Нажать "Show Details" в секции "This Site Configuration"
3. Скопировать API Key

**На Сайте B (который подключается к A):**
1. Открыть Content Sync
2. Нажать "Add New Site"
3. Заполнить форму:
   - **Site Name**: Любое имя (например "Site A")
   - **Remote Site URL**: URL Сайта A (например https://sitea.com)
   - **Remote API Key**: Вставить скопированный ключ
4. Нажать "Save Connection"

**Примечание:** Все подключения автоматически работают в обе стороны (bidirectional).

### 3. Управление подключениями

#### Тестирование соединения
Нажать иконку 🔄 на строке сайта

#### Редактирование
Нажать иконку ✏️ на строке сайта

#### Удаление
Нажать иконку 🗑️ на строке сайта → Подтвердить

## 📊 Возможности

### Статистика
- Общее количество сайтов
- Активные подключения
- Неактивные подключения
- Подключения с ошибками

### Направление синхронизации
Все подключения работают в **обе стороны (bidirectional)** автоматически.

### Статусы
- **Active** - Работает нормально
- **Inactive** - Отключено вручную
- **Error** - Произошла ошибка

## 🔐 Безопасность

- ✅ Nonce verification на всех AJAX запросах
- ✅ Capability checks (manage_options)
- ✅ Input sanitization
- ✅ URL validation
- ✅ SQL injection prevention
- ✅ XSS prevention
- ✅ Уникальные API ключи

## 🎨 UI Компоненты

### Карточки статистики
Отображают общую информацию о подключениях

### Секция "This Site Configuration"
- Название сайта (readonly)
- URL сайта (readonly)
- API ключ с кнопкой копирования
- Toggle для показа/скрытия

### Таблица подключенных сайтов
- Имя сайта
- URL удаленного сайта
- Направление синхронизации (с иконками)
- Статус (с цветными бейджами)
- Время последней синхронизации
- Кнопки действий

### Модальные окна
- Добавление/редактирование сайта
- Просмотр деталей (заготовка)

## 🔧 AJAX Endpoints

Все endpoints требуют nonce и capability `manage_options`:

```javascript
// Получить список сайтов
aie_content_sync_get_sites

// Добавить сайт
aie_content_sync_add_site

// Обновить сайт
aie_content_sync_update_site

// Удалить сайт
aie_content_sync_delete_site

// Тестировать соединение
aie_content_sync_test_connection

// Получить API ключ этого сайта
aie_content_sync_get_my_key

// Регенерировать API ключ
aie_content_sync_regenerate_key
```

## 📱 Responsive Design

Полностью адаптивный дизайн для:
- Desktop (широкие экраны)
- Tablet (средние экраны)
- Mobile (маленькие экраны)

Breakpoint: 782px (стандартный WordPress)

## 🐛 Отладка

### Проверить в консоли браузера
```javascript
// Проверить что объект существует
console.log(aieContentSync);

// Проверить что скрипт загружен
console.log(jQuery('#wp-aie-content-sync').length);
```

### Проверить в WordPress
```php
// Проверить что таблица существует
global $wpdb;
$table = $wpdb->prefix . 'aie_site_connections';
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
var_dump($exists);

// Проверить записи
$sites = $wpdb->get_results("SELECT * FROM {$table}");
var_dump($sites);
```

### Включить WordPress Debug
```php
// В wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## 📝 Database Schema

```sql
CREATE TABLE wp_aie_site_connections (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    remote_url VARCHAR(500) NOT NULL,
    api_key VARCHAR(100) NOT NULL UNIQUE,
    direction ENUM('pull', 'push', 'bidirectional') DEFAULT 'bidirectional',
    status ENUM('active', 'inactive', 'error') DEFAULT 'active',
    last_sync_at DATETIME,
    last_error TEXT,
    created_by BIGINT(20) UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```

## 🔄 Следующие шаги (в будущем)

1. Реализовать актуальную синхронизацию контента
2. Добавить историю синхронизаций
3. Реализовать REST API endpoints
4. Добавить scheduled sync (cron)
5. Реализовать conflict resolution
6. Добавить sync preview
7. Реализовать selective sync (фильтры)

## 📚 Документация

Полная документация: `CONTENT_SYNC_ADMIN_UI.md`

## ✅ Checklist для тестирования

- [ ] Открыть страницу Content Sync
- [ ] Проверить отображение статистики
- [ ] Показать/скрыть "This Site Configuration"
- [ ] Скопировать API ключ этого сайта
- [ ] Добавить новое подключение
- [ ] Редактировать подключение
- [ ] Удалить подключение
- [ ] Тестировать соединение
- [ ] Проверить responsive design
- [ ] Проверить валидацию форм
- [ ] Проверить уведомления об ошибках

## 🎉 Готово к использованию!

Все файлы созданы, ассеты скомпилированы, страница зарегистрирована в админке WordPress.

Перейдите в **WordPress Admin → Advanced Import Export → Content Sync** чтобы начать работу!
