## General Rules ##
1. Follow the coding standards and conventions of the project.
2. Do not modify files that are not directly related to the current task.
3. Do not modify code formatting unless explicitly asked to.
4. Only change code that is directly related to the current task.
5. If you notice unrelated issues, mention them but do not fix them unless instructed.
6. All comments must be in English.
7. Only add meaningful comments that clarify logic.
8. Follow SOLID, KISS, and DRY principles.
9. Do not write documentation or test scripts unless explicitly requested.
10. If something is unclear, ask questions before making any changes.
11. **NEVER create .md documentation files for features or fixes** - only code changes are needed. Do not document bug fixes.
12. Always use yarn instead of npm for building assets, as the project is configured with yarn.
13. Always use Playwright in headless mode for browser testing unless a task explicitly requires a visible window.

## Naming Conventions ##
**КРИТИЧЕСКИ ВАЖНО**: Используйте WordPress Coding Standards для всех имен:

### Файлы:
- ✅ `file_helper.php` (snake_case, lowercase)
- ✅ `import_controller.php`
- ❌ `FileHelper.php` (PascalCase - НЕ использовать)
- ❌ `ImportController.php`

### Классы:
- ✅ `class File_Helper {}` (Class names with underscores)
- ✅ `class Import_Controller {}`
- ❌ `class FileHelper {}` (PascalCase - НЕ использовать)

### Методы и функции:
- ✅ `public function upload_file()` (snake_case)
- ✅ `public function get_job_logs()`
- ❌ `public function uploadFile()` (camelCase - НЕ использовать)

### Переменные:
- ✅ `$file_path` (snake_case)
- ✅ `$job_id`
- ❌ `$filePath` (camelCase - НЕ использовать)

### View файлы:
- ✅ `import_page.php` (snake_case)
- ✅ `history_page.php`
- ❌ `import-page.php` (kebab-case для view файлов допустим, но используем snake_case)


## PHP (Backend) ##
1. Do not write styles or JavaScript inside PHP files.
2. Use named callback functions only. Avoid anonymous functions.
3. Do not modify business logic or unrelated files.

## SCSS (Styles) ##
1. Write all styles in SCSS files located in the 'src/scss' directory.
2. Use desktop-first approach for media queries.
3. Use variables from '_vars.scss'.
4. Avoid repeating selectors. Use a clean parent > child structure.
5. Do not use hardcoded text in styles. Use variables unless explicitly told otherwise.

## JavaScript ##
1. Write JavaScript in separate files inside the 'src/js' directory.
2. Use and write JavaScript modules in 'src/js/modules' directory.
3. Do not embed JavaScript in PHP or HTML templates.

# Assistant Guide:

## 🔗 Important Documentation

**КРИТИЧЕСКИ ВАЖНО**: Перед началом любой разработки ознакомьтесь с этими документами:

1. **[ARCHITECTURE.md](./ARCHITECTURE.md)** - Полная архитектура плагина
   - Паттерны проектирования
   - Структура компонентов
   - Технический стек
   - API и хуки
   - База данных
   - Безопасность
   - **Интеграционный мост трансформаций полей для PRO addon**
   - **Синхронизация медиа папок (Media Folder Sync)**
   - **Синхронизация контента между сайтами (Site-to-Site Content Sync)**

2. **[DEVELOPMENT_PLAN.md](./DEVELOPMENT_PLAN.md)** - Поэтапный план разработки
   - 14 фаз разработки (Phase 0-13)
   - Phase 9.7: Field Transformation bridge for PRO addon
   - Phase 9.8: Media Folder Sync (FTP uploads → WordPress Media Library)
   - Phase 9.9: Site-to-Site Content Sync (Multi-site synchronization)
   - **Phase 4: Import UI System (7-step wizard with advanced field mapping)** ← **NEW**
   - Детальные задачи для каждой фазы
   - Критерии завершения
   - Примеры тестирования
   - Команды для ИИ

3. **[MEDIA_SYNC_FEATURE.md](./MEDIA_SYNC_FEATURE.md)** - Документация Media Folder Sync
   - Полное описание функциональности
   - UI/UX wireframes
   - Технические детали и API
   - Сценарии использования
   - Premium интеграция с Real Media Library

4. **[CONTENT_SYNC_FEATURE.md](./CONTENT_SYNC_FEATURE.md)** - Документация Site-to-Site Content Sync
   - API-based синхронизация между двумя сайтами
   - Pull/Push операции для всех типов контента
   - Управление соединениями с API ключами
   - Conflict resolution и безопасность
   - Background processing для больших операций

5. **[IMPORT_UI_SPECIFICATION.md](./IMPORT_UI_SPECIFICATION.md)** - Спецификация UI импорта
   - 7-шаговый визард импорта
   - Drag & Drop column selection
   - Расширенный field mapping (WordPress + ACF + WooCommerce)
   - Per-field настройки и PRO-managed transformations
   - Duplicate handling (по Title, ID, Custom Field)
   - Auto-download изображений
   - Поддержка всех типов контента + Custom MySQL Tables
   - **[Architecture: Section 10 в ARCHITECTURE.md](./ARCHITECTURE.md)**
   - **[Development: Phase 4 в DEVELOPMENT_PLAN.md](./DEVELOPMENT_PLAN.md)**

6. **[EXPORT_UI_SPECIFICATION.md](./EXPORT_UI_SPECIFICATION.md)** - Спецификация UI экспорта ← **NEW**
   - 5-шаговый визард экспорта
   - Content Type Selection (Posts, Users, Products, Comments, Taxonomies, Menus)
   - Advanced Filtering с Query Builder (Meta Queries, Tax Queries)
   - Field Selection с Drag & Drop
   - Field Transformation bridge for PRO addon
   - Export Options (CSV/JSON/XLS/XLSX)
   - Background Processing
   - Export Templates (Save/Load)
   - Export History (Download, Preview, Rerun)
   - **[Architecture: Section 11 в ARCHITECTURE.md](./ARCHITECTURE.md)** ← **NEW**
   - **[Development: Phase 5 в DEVELOPMENT_PLAN.md](./DEVELOPMENT_PLAN.md)** ← **NEW**

**Quick Reference - Export System**:
- 📄 **[EXPORT_UI_SUMMARY.md](./.github/EXPORT_UI_SUMMARY.md)** - Краткий обзор и приоритеты реализации
- 📋 **[EXPORT_ARCHITECTURE_UPDATE_SUMMARY.md](./.github/EXPORT_ARCHITECTURE_UPDATE_SUMMARY.md)** - Summary добавленной документации

**Правила работы с документацией**:
- ✅ Всегда держите ARCHITECTURE.md и DEVELOPMENT_PLAN.md в контексте
- ✅ Следуйте плану разработки строго по фазам
- ✅ НЕ пропускайте фазы
- ✅ Тестируйте после каждой задачи
- ✅ Обновляйте чеклисты в DEVELOPMENT_PLAN.md
- ✅ При изменении архитектуры обновляйте ARCHITECTURE.md

**Текущий статус разработки**: 
- Phase 0-3: Completed (Infrastructure, Helpers, Parsers, Validation)
- **Phase 4: Import UI System - Documented (Ready for implementation)** ✅
- **Phase 5: Export System - Documented (Ready for implementation)** ✅
- Phase 6-14: Pending

## Project Overview

Плагин для импорта и экспорта данных WordPress с поддержкой:
- Типов данных: Posts, Users, Comments, Media, WooCommerce, ACF
- Форматов: CSV, JSON, XML, XLS, XLSX
- **7-step Import Wizard** с расширенным field mapping
- **5-step Export Wizard** с advanced filtering и трансформацией полей
- Фоновой обработки больших файлов (50 items/batch)
- **Пользовательских PHP функций для трансформации данных**
- **Библиотеки готовых сниппетов (50+ примеров)**
- **Синхронизации папок с медиа библиотекой (FTP → WordPress)**
- **Синхронизации контента между сайтами (API-based Pull/Push)**
- **Premium: интеграция с Real Media Library**
- REST API и WP-CLI
- Расширяемой архитектуры



## Development Environment

The development workflow relies on **Node.js** for dependency management and **Gulp** for automating build tasks.

- **Node.js**: Used for managing project dependencies listed in `package.json`.
- **Gulp**: The `gulpfile.js` in the project root defines tasks for compiling assets (SCSS, JS), and other development workflows.

To set up the environment, you need to have Node.js and npm installed. Then, run `npm install` in the project root to install all the necessary Gulp plugins and other dependencies.

## Project Structure

The project follows the standard WordPress structure, with the main application logic encapsulated within the `import-export-by-rockstarlab` plugin.

```
.
├── <wordpress-content-dir>/
│   ├── plugins/
│   │   ├── import-export-by-rockstarlab/
│   │   │   ├── app/                 						# Core application logic (MVC)
│   │   │   │   ├── controller/      						# Controllers (business logic)
│   │   │   │   ├── model/           						# Models (data handling)
│   │   │   │   ├── view/            						# Views (templates)
│   │   │   │   └── helper/          						# Helper classes
│   │   │   │   ├── app.php          						# Core application class
│   │   │   │   ├── config.php       						# Configuration settings
│   │   │   ├── assets/              						# Compiled assets
│   │   │   │   ├── css/
│   │   │   │   └── js/
│   │   │   ├── languages/           						# PO / MO fies for translations
│   │   │   ├── node_modules/        						# NODE.JS dependencies
│   │   │   ├── src/                  					# Source files (uncompiled)
│   │   │   │   ├── scss/
│   │   │   │   └── js/
│   │   │   ├── import-export-by-rockstarlab.php		# Main plugin initialization file
│   │   └── ... (other plugins)
│   └── ... (other WordPress content folders)
└── ... (other WordPress root files)
```

### Architecture Overview

The `import-export-by-rockstarlab` plugin follows a **custom MVC-like architecture** with object-oriented principles:

#### Core Components

1. **Application Core** (`app/app.php`)
   - Singleton pattern implementation
   - Central controller for the entire theme
   - Accessible globally via `WP_AIE()` function

2. **Autoloader** (`functions.php`)
   - PSR-4 compliant autoloader
   - Namespace: `WP_AIE`
   - Automatic class loading from `app/` directory

3. **Configuration** (`app/config.php`)
   - Centralized configuration management
   - Email settings, templates, and system parameters

### Directory Structure

```
app/
├── app.php              # Core application class (singleton)
├── config.php           # Configuration settings
├── controller/          # Business logic controllers
├── model/               # Data handling classes
├── view/                # Display logic
└── helper/              # Utility classes
```
### Code Standards

#### PHP Guidelines:
- Follow PSR-4 autoloading standards
- Use meaningful comments in English
- Implement SOLID, KISS, and DRY principles
- Avoid anonymous functions; use named callbacks
- Business logic stays in controllers

#### Frontend Guidelines:

- **SCSS**: Follow the guidelines in the "SCSS (Styles)" section of this document. All styles should be written in the `assets/css` directory.
- **JavaScript**: Follow the guidelines in the "JavaScript" section. All custom JS should be in the `assets/js` directory.

## Common Tasks

### Adding New Functionality

1. **New Controller**: Create in `app/controller/`
2. **Update Loading Order**: Add to `$controllers` array in `app/app.php`
3. **Configuration**: Add settings to `app/config.php` if needed
4. **Autoloading**: Classes are automatically loaded via PSR-4

## Important Notes

- The plugin uses a singleton pattern for its core application class (`import-export-by-rockstarlab\app`), which can be accessed globally via the `WP_AIE()` function.
- The autoloader in `import-export-by-rockstarlab.php` follows PSR-4 standards, so you don't need to manually include class files.
- When adding new controllers, remember to update the `$controllers` array in `app/app.php` to ensure they are loaded correctly.
- Plugin uses Freemius for licensing and updates; ensure to follow their guidelines when making changes related to licensing.
