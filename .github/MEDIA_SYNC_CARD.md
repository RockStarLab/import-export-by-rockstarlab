# 📁 Media Folder Sync

> Синхронизация папок с сервера (FTP) → WordPress Media Library

---

## 🎯 Что делает?

Позволяет пользователям легко импортировать файлы, загруженные через FTP, в медиа библиотеку WordPress с автоматической организацией и проверкой дубликатов.

---

## ✨ Ключевые фичи

```
📂 Сканирование папок
   • Рекурсивное сканирование вложенных папок
   • Предпросмотр списка файлов перед импортом
   • Статистика: количество файлов, общий размер

🎨 Фильтры файлов  
   • Все типы (разрешенные WordPress)
   • Только изображения (jpg, png, gif, webp)
   • Пользовательский выбор типов

🚫 Проверка дубликатов (3 метода)
   • Hash (MD5) - самый точный
   • Filename - самый быстрый  
   • Filesize - баланс скорости/точности

⚙️ Опции импорта
   • Alt text из имени файла
   • Генерация thumbnails
   • Сохранение структуры папок

👑 Premium: Real Media Library
   • Автоматическое создание папок в RML
   • Сохранение иерархии папок
   • Организация медиа по категориям
```

---

## 🎬 Процесс работы

```
1. 📤 Загрузка через FTP
   └─► /wp-content/uploads/ftp-import/

2. 🔍 Сканирование
   └─► Выбор папки → Настройка фильтров → Предпросмотр

3. ⚡ Синхронизация  
   └─► Пакетная обработка → Progress tracking → Готово

4. 📊 Результат
   └─► Все файлы в Media Library с thumbnails
```

---

## 💡 Сценарии использования

### 📷 Массовая загрузка фото
**Проблема:** 1000 фотографий загружены через FTP  
**Решение:** Один клик → все в Media Library с thumbnails

### 🔄 Миграция сайта
**Проблема:** Перенос медиа со старого сайта  
**Решение:** Hash метод → дубликаты пропускаются автоматически

### 🛍️ WooCommerce каталог (Premium)
**Проблема:** 500 фото продуктов в папках по категориям  
**Решение:** Структура папок → автоматические RML папки

---

## 🔧 Технологии

```php
// Backend
class Media_Folder_Sync {
    scan_folder()      // Сканирование
    check_duplicate()  // 3 метода проверки
    import_file()      // Импорт с metadata
    sync_files()       // Пакетная обработка
}
```

```javascript
// Frontend  
class MediaFolderSync {
    scanFolder()       // AJAX сканирование
    startSync()        // Запуск синхронизации
    updateProgress()   // Real-time прогресс
}
```

```sql
-- Database
aie_media_sync
  ├─ job_id
  ├─ file_path
  ├─ attachment_id
  ├─ file_hash (MD5)
  └─ status
```

---

## 🎨 UI Preview

```
┌──────────────────────────────────────────┐
│ Media Folder Sync                        │
├──────────────────────────────────────────┤
│ 📁 Select Folder                         │
│ /wp-content/uploads/ftp-import/          │
│ [Browse] [Recent ▼]                      │
│ ☑ Include subfolders                     │
│ Files found: 247 (23.5 MB)               │
├──────────────────────────────────────────┤
│ 🎨 File Types: ○ All ● Images ○ Custom  │
├──────────────────────────────────────────┤
│ 🚫 Duplicates: ○ Hash ● Filename         │
├──────────────────────────────────────────┤
│ ⚙️ Options:                              │
│ ☑ Alt text  ☑ Thumbnails  ☑ Structure   │
│                                          │
│ 👑 Premium:                              │
│ ☑ Create RML folders                     │
├──────────────────────────────────────────┤
│ [Scan Folder]  [Start Sync]              │
└──────────────────────────────────────────┘
```

### Progress Modal

```
┌─────────────────────────────┐
│ Syncing...             [✕] │
├─────────────────────────────┤
│ 47 / 247 (19%)              │
│ ████████░░░░░░░░░           │
│                             │
│ ✓ Success: 45               │
│ ⊘ Skipped: 2                │
│ ✗ Failed: 0                 │
│                             │
│ [Pause] [Cancel]            │
└─────────────────────────────┘
```

---

## 🔐 Безопасность

✅ WordPress MIME types only  
✅ File size validation  
✅ Permission checks  
✅ Nonce verification  
✅ Capability checks (`manage_options`)

---

## 📊 Статистика

```
Total: 247 files
✓ Success: 245 (imported)
⊘ Skipped: 2 (duplicates)  
✗ Failed: 0 (errors)
Size: 23.5 MB
Time: 00:02:05
```

---

## 🚀 Performance

- **Batch Processing:** 50 files за раз
- **Background Jobs:** WordPress Cron
- **Memory Efficient:** Файл-по-файлу обработка
- **Resumable:** Продолжение после перезапуска

---

## 📚 Документация

- **[MEDIA_SYNC_FEATURE.md](../MEDIA_SYNC_FEATURE.md)** - Полная документация
- **[MEDIA_SYNC_FLOW.md](../MEDIA_SYNC_FLOW.md)** - Архитектура и flow
- **[MEDIA_SYNC_SUMMARY.md](../MEDIA_SYNC_SUMMARY.md)** - Краткий обзор
- **[ARCHITECTURE.md](../ARCHITECTURE.md)** - Section 8
- **[DEVELOPMENT_PLAN.md](../DEVELOPMENT_PLAN.md)** - Phase 9.8

---

## 👑 Premium vs Free

| Feature | Free | Premium |
|---------|------|---------|
| Folder scanning | ✅ | ✅ |
| Duplicate detection | ✅ | ✅ |
| File import | ✅ | ✅ |
| Alt text generation | ✅ | ✅ |
| Preserve structure | ✅ | ✅ |
| **Real Media Library** | ❌ | ✅ |
| **Auto RML folders** | ❌ | ✅ |
| **Priority Support** | ❌ | ✅ |

---

## 📈 Roadmap

**Phase 9.8 (Current)**
- ✅ Planning & Architecture
- 🚧 Backend Implementation
- ⏳ Frontend UI
- ⏳ Testing
- ⏳ Release

**Future Enhancements**
- 🔮 Scheduled auto-sync
- 🔮 Direct FTP connector
- 🔮 Image optimization on import
- 🔮 EXIF metadata extraction

---

## 🎯 Status

```
Planning:    ████████████████████████████ 100%
Development: ░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 0%
Testing:     ░░░░░░░░░░░░░░░░░░░░░░░░░░░░ 0%
Overall:     ███░░░░░░░░░░░░░░░░░░░░░░░░░ 10%
```

**Current Phase:** 9.8  
**Status:** ✅ Planning Complete → 🚧 Ready for Development  
**Next:** "Начни Phase 9.8"

---

**Version:** 1.0.0 | **Date:** 2025-11-27 | **License:** GPL v2+
