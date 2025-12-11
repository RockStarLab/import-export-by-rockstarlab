# Content Updater Fix - ID Field Always Included

## Проблема
Страницы дублировались вместо обновления, потому что:
1. Пользователь выбирал только поле `post_title` для обновления
2. Экспортер возвращал данные БЕЗ поля `ID` (только выбранные поля)
3. Update_Processor получал `item_id = 0` 
4. `wp_update_post()` с ID=0 создавал НОВУЮ страницу вместо обновления существующей

## Решение
ID поле теперь ВСЕГДА включается в данные, независимо от выбора пользователя.

### Изменения в экспортерах:

#### 1. Post_Exporter.php (для posts, pages, media, меню)
```php
protected function prepare_post_data( $post, $fields ) {
    $data = [];
    
    // ALWAYS include ID for identification, even if not requested
    $data['ID'] = $post->ID;
    
    // Затем добавляем остальные поля по выбору
    foreach ( $basic_fields as $field ) {
        if ( $field === 'ID' ) {
            continue; // Skip ID as it's already added
        }
        if ( in_array( $field, $fields, true ) ) {
            $data[ $field ] = $post->$field;
        }
    }
    ...
}
```

#### 2. User_Exporter.php
```php
protected function format_user( $user, $options ) {
    $fields = $options['fields'] ?? $this->get_default_fields();
    $data   = [];
    
    // ALWAYS include ID for identification
    $data['ID'] = $user->ID;
    
    foreach ( $fields as $field ) {
        switch ( $field ) {
            case 'ID':
                // Already added above, skip
                break;
            ...
        }
    }
}
```

#### 3. Comment_Exporter.php
```php
protected function format_comment( $comment, $options ) {
    ...
    // ALWAYS include comment_ID for identification
    $data['comment_ID'] = $comment->comment_ID;
    ...
}
```

#### 4. Taxonomy_Exporter.php
```php
protected function format_term( $term, $options ) {
    ...
    // ALWAYS include term_id for identification
    $data['term_id'] = $term->term_id;
    ...
}
```

## Логика Content Updater:

**Шаг 1:** Выбрать тип контента (Pages)
  → Определяет КАКИЕ записи обновлять

**Шаг 2:** Выбрать поля (post_title)
  → Определяет КАКИЕ ПОЛЯ обновлять

**Шаг 3:** Назначить функции (uppercase)
  → Определяет КАК изменять поля

**Шаг 4:** Запуск обновления
  → Получить ВСЕ страницы с ID + выбранными полями
  → Применить функции к полям
  → Обновить страницы по их ID

## Результат:

✅ ID всегда присутствует в данных
✅ Страницы обновляются, а не дублируются
✅ Правильная логика: получаем все записи выбранного типа, обновляем указанные поля
✅ Работает для всех типов контента: posts, pages, users, comments, taxonomies

## Тестирование:

1. Удалить дубликаты:
   http://your-site.local/wp-content/plugins/wp-advanced-import-export/remove_duplicates.php

2. Проверить что ID включается:
   http://your-site.local/wp-content/plugins/wp-advanced-import-export/test_id_fix.php

3. Запустить Content Updater:
   - Выбрать Pages
   - Выбрать только post_title
   - Назначить uppercase
   - Запустить с items_per_iteration=1
   - Проверить логи: должны быть правильные ID (не 0)
   - Проверить страницы: должны обновиться, без дубликатов
