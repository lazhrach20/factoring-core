# Navigation Component

> 🇬🇧 [English version](./README.en.md)

Компонент единой навигации для всех страниц платформы ФинансФактор.

## Использование

### 1. Подключение в HTML

В любом HTML файле замените старый `<header>` блок на:

```html
<div id="navigation-container"></div>
```

### 2. Подключение JavaScript

Добавьте перед закрывающим тегом `</body>`:

```html
<script src="/js/navigation.js"></script>
```

## Что делает компонент

- ✅ Автоматически определяет текущую страницу и помечает ее как активную
- ✅ Загружает информацию о текущем пользователе
- ✅ Показывает/скрывает ссылки в зависимости от ролей:
  - **ROLE_ADMIN**: видит все ссылки (Админ-панель, Компании, Аудит)
  - **ROLE_AUDIT_VIEWER**: видит только ссылку Аудит
  - **Обычные пользователи**: видят только базовые ссылки (Банковские счета, API Keys)
- ✅ Отображает аватар и информацию о пользователе
- ✅ Обрабатывает выход из системы

## Обновленные страницы

- `/public/banking.html` - Банковские счета
- `/public/profile.html` - Профиль пользователя
- `/public/api-keys.html` - API ключи
- `/public/admin-users.html` - Администрирование пользователей
- `/public/admin-companies.html` - Управление компаниями
- `/public/audit-logs.html` - Журнал аудита

## Технические детали

### Класс Navigation

```javascript
class Navigation {
    constructor() // Инициализация
    async init() // Загрузка данных и рендеринг
    async loadUserInfo() // Загрузка информации о пользователе
    isActivePage(path) // Проверка активной страницы
    hasRole(role) // Проверка роли пользователя
    getHTML() // Генерация HTML навигации
    render() // Рендеринг в DOM
}
```

### Функция выхода

```javascript
navigationLogout(event) // Глобальная функция для выхода
```

## Миграция существующих страниц

Если у вас есть старая страница с навигацией, выполните следующие шаги:

1. Удалите весь блок `<header class="navbar...">...</header>`
2. Замените его на `<div id="navigation-container"></div>`
3. Удалите функцию `loadUserInfo()` (если есть)
4. Удалите вызовы `loadUserInfo()` из инициализации
5. Удалите обработчики кнопки logout (если есть)
6. Добавьте `<script src="/js/navigation.js"></script>` перед `</body>`

## Пример

### До:
```html
<body>
    <header class="navbar navbar-expand-md navbar-light d-print-none">
        <!-- 100 строк навигации -->
    </header>

    <script>
        async function loadUserInfo() {
            // 30 строк кода
        }

        loadUserInfo();
    </script>
</body>
```

### После:
```html
<body>
    <div id="navigation-container"></div>

    <script>
        // loadUserInfo больше не нужна - компонент всё делает сам
    </script>
    <script src="/js/navigation.js"></script>
</body>
```

## Преимущества

- 🎯 **Единообразие**: Все страницы выглядят одинаково
- 🔧 **Простота обслуживания**: Изменения вносятся в одном месте
- 🚀 **Автоматизация**: Активная страница определяется автоматически
- 🔒 **Безопасность**: Роли проверяются централизованно
- 📉 **Меньше кода**: Не нужно дублировать loadUserInfo на каждой странице
