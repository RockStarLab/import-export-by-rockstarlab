# Import Fields - Complete Reference

## Обзор добавленных полей

В Step 4 (Field Mapping) для импорта теперь доступны все необходимые поля для каждого типа контента, включая поддержку Template, Taxonomies, Custom Fields, ACF и Yoast SEO.

---

## Posts (Записи)

### Standard
- `post_title` - Заголовок
- `post_content` - Контент
- `post_excerpt` - Краткое описание
- `post_date` - Дата публикации
- `post_status` - Статус (publish, draft, pending, etc.)
- `post_name` - Slug (ЧПУ)

### Author
- `post_author` - ID автора
- `author_name` - Имя автора
- `author_email` - Email автора

### Taxonomy
- `categories` - Категории (массив имен)
- `category_ids` - ID категорий (массив)
- `tags` - Теги (массив имен)
- `tag_ids` - ID тегов (массив)
- `term_id` - ID термина
- `term_name` - Название термина
- `term_slug` - Slug термина

### Media
- `featured_image` - URL избранного изображения
- `featured_image_id` - ID избранного изображения

### Other
- `comment_status` - Статус комментариев (open, closed)
- `post_modified` - Дата изменения
- `_wp_page_template` - Шаблон страницы
- `menu_order` - Порядок меню
- `post_parent` - ID родительского поста

### Custom Fields (Meta)
- `meta_key` - Ключ произвольного поля
- `meta_value` - Значение произвольного поля

### ACF Fields
- `acf_field` - ACF поле (по имени)
- `acf_text` - Текстовое поле
- `acf_textarea` - Текстовая область
- `acf_number` - Число
- `acf_email` - Email
- `acf_url` - URL
- `acf_wysiwyg` - WYSIWYG редактор
- `acf_image` - Изображение (ID или URL)
- `acf_file` - Файл (ID или URL)
- `acf_gallery` - Галерея (массив ID)
- `acf_select` - Выпадающий список
- `acf_checkbox` - Чекбокс (массив)
- `acf_radio` - Радио-кнопка
- `acf_true_false` - Истина/Ложь
- `acf_date_picker` - Выбор даты
- `acf_color_picker` - Выбор цвета
- `acf_relationship` - Связь (массив ID постов)
- `acf_post_object` - Объект поста (ID)
- `acf_taxonomy` - Таксономия (массив)
- `acf_user` - Пользователь (ID)
- `acf_repeater` - Повторитель (JSON)
- `acf_group` - Группа (JSON)
- `acf_flexible_content` - Гибкое содержимое (JSON)

### Yoast SEO
- `yoast_title` - SEO заголовок
- `yoast_description` - Meta описание
- `yoast_focus_keyword` - Фокусное ключевое слово
- `yoast_canonical` - Канонический URL
- `yoast_robots_noindex` - No-Index
- `yoast_robots_nofollow` - No-Follow
- `yoast_opengraph_title` - Open Graph заголовок
- `yoast_opengraph_description` - Open Graph описание
- `yoast_opengraph_image` - Open Graph изображение
- `yoast_twitter_title` - Twitter заголовок
- `yoast_twitter_description` - Twitter описание
- `yoast_twitter_image` - Twitter изображение
- `yoast_breadcrumb_title` - Заголовок хлебных крошек
- `yoast_primary_category` - Основная категория (ID)
- `yoast_schema` - Schema.org (JSON)

---

## Pages (Страницы)

Использует те же поля что и Posts, включая:
- Standard fields
- Author fields
- Taxonomy fields
- Media fields
- Other fields (включая Template)
- Custom Fields
- ACF Fields
- Yoast SEO

---

## Media (Медиафайлы)

### Basic
- `post_title` - Заголовок
- `post_content` - Описание
- `post_excerpt` - Подпись
- `alt_text` - Alt текст

### File Information
- `guid` - File URL (GUID)
- `file_url` - URL файла
- `file_path` - Относительный путь
- `file_name` - Имя файла
- `file_extension` - Расширение
- `post_mime_type` - MIME тип
- `file_size` - Размер в байтах

### Image Dimensions
- `width` - Ширина (px)
- `height` - Высота (px)

### Dates
- `post_date` - Дата загрузки
- `post_modified` - Дата изменения

### Author
- `post_author` - ID автора
- `author_name` - Имя автора
- `author_email` - Email автора

### Attachment
- `post_parent` - ID прикрепленного поста
- `attached_post_title` - Заголовок прикрепленного поста

### Custom Fields (Meta)
- `meta_key` - Ключ метаданных
- `meta_value` - Значение метаданных

### ACF Fields
- `acf_field` - ACF поле (по имени)
- `acf_text` - Текстовое поле
- `acf_image` - Изображение
- `acf_file` - Файл
- `acf_gallery` - Галерея

---

## Users (Пользователи)

### Basic
- `user_login` - Имя пользователя
- `user_email` - Email
- `display_name` - Отображаемое имя
- `user_nicename` - "Красивое" имя

### Profile
- `first_name` - Имя
- `last_name` - Фамилия
- `nickname` - Ник
- `description` - Биография
- `user_url` - Сайт
- `avatar_url` - URL аватара

### Role & Permissions
- `role` - Роль
- `capabilities` - Возможности (массив)

### Preferences
- `locale` - Язык
- `admin_color` - Цветовая схема админки
- `rich_editing` - Визуальный редактор

### Stats
- `posts_count` - Количество постов
- `user_registered` - Дата регистрации
- `user_status` - Статус пользователя

### Custom Fields (User Meta)
- `meta_key` - Ключ пользовательских метаданных
- `meta_value` - Значение метаданных

### ACF Fields
- `acf_field` - ACF поле (по имени)
- `acf_text` - Текстовое поле
- `acf_textarea` - Текстовая область
- `acf_number` - Число
- `acf_email` - Email
- `acf_url` - URL
- `acf_image` - Изображение
- `acf_select` - Выпадающий список
- `acf_checkbox` - Чекбокс
- `acf_true_false` - Истина/Ложь
- `acf_date_picker` - Выбор даты

---

## WooCommerce Products (Товары)

### Basic
- `ID` - ID товара
- `post_title` - Название товара
- `post_name` - Slug
- `post_status` - Статус
- `sku` - SKU
- `post_author` - ID автора

### Content
- `post_content` - Описание
- `post_excerpt` - Краткое описание

### Pricing
- `regular_price` - Обычная цена
- `sale_price` - Цена со скидкой
- `tax_status` - Налоговый статус
- `tax_class` - Налоговый класс

### Inventory
- `stock_quantity` - Количество на складе
- `stock_status` - Статус наличия
- `manage_stock` - Управление запасами
- `backorders` - Предзаказы

### Product Type
- `product_type` - Тип товара
- `downloadable` - Загружаемый
- `virtual` - Виртуальный

### Shipping
- `weight` - Вес
- `length` - Длина
- `width` - Ширина
- `height` - Высота
- `shipping_class` - Класс доставки

### Media
- `featured_image` - Избранное изображение
- `product_gallery` - Галерея (массив)

### Taxonomy
- `product_cat` - Категории
- `product_tag` - Теги

### Reviews
- `average_rating` - Средний рейтинг
- `review_count` - Количество отзывов
- `comment_status` - Отзывы включены

### Visibility
- `featured` - Рекомендуемый
- `visibility` - Видимость в каталоге
- `total_sales` - Всего продаж

### Dates
- `post_date` - Дата создания
- `post_modified` - Дата изменения

### Custom Fields (Meta)
- `meta_key` - Ключ метаданных
- `meta_value` - Значение метаданных

### ACF Fields
Все те же ACF поля что и для постов (23 типа полей)

### Yoast SEO
Все те же Yoast поля что и для постов (15 полей)

---

## Примечания

### Custom Fields (Meta)
Для импорта произвольных мета-полей используйте:
- `meta_key` - для указания ключа поля
- `meta_value` - для значения

Пример в CSV:
```
post_title,meta_key,meta_value
"Test Post","custom_field_name","custom_value"
```

### ACF Fields
Для ACF полей можно использовать:
1. **По имени поля**: `acf_field` - универсальное поле, укажите имя ACF поля
2. **По типу**: Конкретные типы (acf_text, acf_image, etc.)

### Yoast SEO
Все поля Yoast автоматически сохраняются в соответствующие мета-ключи WordPress.

### Taxonomies
Можно импортировать таксономии:
- По именам: `categories`, `tags`
- По ID: `category_ids`, `tag_ids`
- По отдельным полям: `term_id`, `term_name`, `term_slug`

---

## Использование

1. Загрузите CSV/JSON файл в Step 2
2. Просмотрите превью данных в Step 3
3. В Step 4 перетащите поля из источника (левая панель) на целевые поля (правая панель)
4. При необходимости назначьте функции трансформации для каждого маппинга
5. Переходите к Step 5 для настройки опций импорта
