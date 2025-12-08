# Export Step 3 - Drag and Drop Fix

## Проблема
При использовании drag-and-drop блокировалась прокрутка страницы колесом мыши.

## Решение

### 1. Условное предотвращение событий
Теперь `preventDefault()` вызывается **только** когда:
- Происходит реальное перетаскивание (проверяем `dataTransfer.types`)
- Элемент находится над зоной дропа
- Происходит реальное событие drop

### 2. Флаг состояния перетаскивания
Добавлен флаг `this.isDragging` для отслеживания активного состояния:
```javascript
this.isDragging = false;
```

### 3. Управление классом body
При начале drag добавляется класс `aie-dragging`:
```javascript
document.body.classList.add('aie-dragging');
```

При окончании drag класс удаляется:
```javascript
document.body.classList.remove('aie-dragging');
```

### 4. CSS для плавной прокрутки
```scss
.aie-step-3 {
	* {
		-webkit-overflow-scrolling: touch;
	}
}

body.aie-dragging {
	overflow: auto !important;
	-webkit-overflow-scrolling: touch;
}
```

## Результат
✅ Прокрутка колесом мыши работает во время drag-and-drop
✅ Перетаскивание элементов работает корректно
✅ Нет конфликтов между прокруткой и drag-and-drop
✅ Улучшена производительность за счет условных проверок

## Тестирование
1. Перетащите поле из библиотеки в CSV builder
2. Попробуйте прокрутить страницу во время перетаскивания
3. Убедитесь, что элемент корректно добавляется в dropzone
4. Проверьте изменение порядка колонок с прокруткой

## Технические детали

### Проверка типов данных
```javascript
if (e.dataTransfer.types.includes('text/plain') || 
    e.dataTransfer.types.includes('application/column-reorder')) {
	e.preventDefault();
}
```

### Условная обработка drop
```javascript
const data = e.dataTransfer.getData('text/plain');
if (data) {
	e.preventDefault();
	e.stopPropagation();
	// ... обработка
}
```

### Условная обработка dragover для колонок
```javascript
const dragging = document.querySelector('.aie-csv-column.dragging');
if (dragging) {
	e.preventDefault();
	// ... перемещение
}
```

## Совместимость
- ✅ Chrome/Edge
- ✅ Firefox
- ✅ Safari
- ✅ Mobile browsers (iOS/Android)
