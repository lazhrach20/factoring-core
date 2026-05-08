# Navigation Component

> 🇷🇺 [Русская версия](./README.md)

Shared navigation component for all pages of the FinansFactor platform.

## Usage

### 1. Add the container to HTML

In any HTML file, replace the old `<header>` block with:

```html
<div id="navigation-container"></div>
```

### 2. Include the script

Add before the closing `</body>` tag:

```html
<script src="/js/navigation.js"></script>
```

## What it does

- ✅ Automatically detects the current page and marks it as active
- ✅ Loads current user info from the API
- ✅ Shows/hides links based on user roles:
  - **ROLE_ADMIN**: sees all links (Admin panel, Companies, Audit)
  - **ROLE_AUDIT_VIEWER**: sees only the Audit link
  - **Regular users**: see only basic links (Banking, API Keys)
- ✅ Displays the user avatar and name
- ✅ Handles logout

## Pages using this component

- `/public/banking.html` — Banking accounts
- `/public/profile.html` — User profile
- `/public/api-keys.html` — API keys
- `/public/admin-users.html` — User administration
- `/public/admin-companies.html` — Company management
- `/public/audit-logs.html` — Audit log

## Technical Details

### `Navigation` class

```javascript
class Navigation {
    constructor()          // Initialize
    async init()           // Load data and render
    async loadUserInfo()   // Fetch current user from API
    isActivePage(path)     // Check if a path is the current page
    hasRole(role)          // Check if current user has a role
    getHTML()              // Generate navigation HTML
    render()               // Inject HTML into the DOM
}
```

### Global logout function

```javascript
navigationLogout(event)    // Called from the logout button's onclick
```

## Migrating an existing page

If you have a page with an inline navigation, follow these steps:

1. Remove the entire `<header class="navbar...">...</header>` block
2. Replace it with `<div id="navigation-container"></div>`
3. Remove any `loadUserInfo()` function defined in the page script
4. Remove all `loadUserInfo()` calls
5. Remove any inline logout button handlers
6. Add `<script src="/js/navigation.js"></script>` before `</body>`

## Example

### Before:
```html
<body>
    <header class="navbar navbar-expand-md navbar-light d-print-none">
        <!-- ~100 lines of nav markup -->
    </header>

    <script>
        async function loadUserInfo() {
            // ~30 lines of code
        }
        loadUserInfo();
    </script>
</body>
```

### After:
```html
<body>
    <div id="navigation-container"></div>

    <script src="/js/navigation.js"></script>
</body>
```
