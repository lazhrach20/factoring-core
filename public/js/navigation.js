/**
 * Unified Navigation Component
 * Automatically determines active page and shows/hides links based on user roles
 */

class Navigation {
    constructor() {
        this.currentUser = null;
        this.currentPath = window.location.pathname;
    }

    /**
     * Initialize navigation - load user info and render
     */
    async init() {
        const currentPage = window.location.pathname;

        // Don't try to load user info on login or register pages
        if (currentPage === '/login.html' || currentPage === '/register.html') {
            this.render();
            return;
        }

        // Quick check for token before any async operations
        const accessToken = localStorage.getItem('accessToken');
        if (!accessToken) {
            // No token, save current page and redirect to login
            if (currentPage !== '/' && currentPage !== '/index.html') {
                localStorage.setItem('redirect_after_login', currentPage);
            }
            window.location.href = '/login.html';
            return;
        }

        await this.loadUserInfo();
        this.render();
    }

    /**
     * Load current user information
     */
    async loadUserInfo() {
        try {
            const accessToken = localStorage.getItem('accessToken');
            if (!accessToken) {
                // No token found, redirect to login
                window.location.href = '/login.html';
                return;
            }

            const response = await fetch('/api/users/me', {
                headers: { 'Authorization': `Bearer ${accessToken}` }
            });

            if (response.ok) {
                this.currentUser = await response.json();
            } else if (response.status === 401) {
                // Unauthorized, clear tokens and redirect
                const currentPage = window.location.pathname;
                if (currentPage !== '/login.html' && currentPage !== '/') {
                    localStorage.setItem('redirect_after_login', currentPage);
                }
                localStorage.removeItem('accessToken');
                localStorage.removeItem('refreshToken');
                localStorage.removeItem('jwt_token'); // Also remove jwt_token for compatibility
                window.location.href = '/login.html';
                return;
            }
        } catch (error) {
            // Network error or other issues - don't redirect, just log
            console.error('Error loading user info:', error);
            // Don't redirect on network errors - user might be offline or temporary issue
        }
    }

    /**
     * Check if current page matches the given path
     */
    isActivePage(path) {
        return this.currentPath === path || this.currentPath.endsWith(path);
    }

    /**
     * Check if user has specific role
     */
    hasRole(role) {
        return this.currentUser?.roles?.includes(role) || false;
    }

    /**
     * Generate navigation HTML
     */
    getHTML() {
        const fullName = this.currentUser
            ? `${this.currentUser.firstName} ${this.currentUser.lastName}`
            : 'Загрузка...';
        const email = this.currentUser?.email || '';

        const isAdmin = this.hasRole('ROLE_ADMIN');
        const isAuditor = this.hasRole('ROLE_AUDIT_VIEWER');
        const isFactor = this.currentUser?.company?.type === 'factor';

        // Get company type badge
        const companyBadge = this.getCompanyTypeBadge();

        return `
            <header class="navbar navbar-expand-md navbar-light d-print-none">
                <div class="container-xl">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <h1 class="navbar-brand">
                        <i class="ti ti-chart-line"></i> ФинансФактор ${companyBadge}
                    </h1>
                    <div class="navbar-nav flex-row order-md-last">
                        <!-- Admin Panel Link (only for admins) -->
                        ${isAdmin ? `
                        <div class="nav-item">
                            <a href="/admin-users.html" class="nav-link ${this.isActivePage('/admin-users.html') ? 'active' : ''}">
                                <i class="ti ti-shield-lock me-1"></i>
                                Админ-панель
                            </a>
                        </div>
                        ` : ''}

                        <!-- Companies Management Link (only for factor) -->
                        ${isFactor ? `
                        <div class="nav-item">
                            <a href="/admin-companies.html" class="nav-link ${this.isActivePage('/admin-companies.html') ? 'active' : ''}">
                                <i class="ti ti-building me-1"></i>
                                Компании
                            </a>
                        </div>
                        ` : ''}

                        <!-- Banking Link -->
                        <div class="nav-item">
                            <a href="/banking.html" class="nav-link ${this.isActivePage('/banking.html') ? 'active' : ''}">
                                <i class="ti ti-wallet me-1"></i>
                                Банковские счета
                            </a>
                        </div>

                        <!-- Financing Requests Link (only for admins) -->
                        ${isAdmin ? `
                        <div class="nav-item">
                            <a href="/financing-requests.html" class="nav-link ${this.isActivePage('/financing-requests.html') ? 'active' : ''}">
                                <i class="ti ti-file-invoice me-1"></i>
                                Заявки на финансирование
                            </a>
                        </div>
                        ` : ''}

                        <!-- API Keys Link -->
                        <div class="nav-item">
                            <a href="/api-keys.html" class="nav-link ${this.isActivePage('/api-keys.html') ? 'active' : ''}">
                                <i class="ti ti-key me-1"></i>
                                API Keys
                            </a>
                        </div>

                        <!-- Audit Logs Link (only for factor admins/auditors) -->
                        ${isFactor && (isAdmin || isAuditor) ? `
                        <div class="nav-item">
                            <a href="/audit-logs.html" class="nav-link ${this.isActivePage('/audit-logs.html') ? 'active' : ''}">
                                <i class="ti ti-file-text me-1"></i>
                                Аудит
                            </a>
                        </div>
                        ` : ''}

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-0 dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="dynamic" aria-expanded="false" role="button" id="userDropdown">
                                <span class="avatar avatar-sm" style="background-image: url(https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=206bc4&color=fff); pointer-events: none;"></span>
                                <div class="d-none d-xl-block ps-2" style="pointer-events: none;">
                                    <div style="pointer-events: none;">${fullName}</div>
                                    <div class="mt-1 small text-secondary" style="pointer-events: none;">${email}</div>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow" aria-labelledby="userDropdown">
                                <li><a href="/profile.html" class="dropdown-item ${this.isActivePage('/profile.html') ? 'active' : ''}">
                                    Профиль
                                </a></li>
                                <li><a href="/banking.html" class="dropdown-item ${this.isActivePage('/banking.html') ? 'active' : ''}">
                                    <i class="ti ti-wallet me-1"></i>
                                    Банковские счета
                                </a></li>
                                ${isAdmin ? `
                                <li><a href="/financing-requests.html" class="dropdown-item ${this.isActivePage('/financing-requests.html') ? 'active' : ''}">
                                    <i class="ti ti-file-invoice me-1"></i>
                                    Заявки на финансирование
                                </a></li>
                                ` : ''}
                                <li><a href="/api-keys.html" class="dropdown-item ${this.isActivePage('/api-keys.html') ? 'active' : ''}">
                                    <i class="ti ti-key me-1"></i>
                                    API Keys
                                </a></li>
                                ${isFactor && (isAdmin || isAuditor) ? `
                                <li><a href="/audit-logs.html" class="dropdown-item ${this.isActivePage('/audit-logs.html') ? 'active' : ''}">
                                    <i class="ti ti-file-text me-1"></i>
                                    Журнал аудита
                                </a></li>
                                ` : ''}
                                ${isAdmin ? `
                                <li><a href="/admin-users.html" class="dropdown-item ${this.isActivePage('/admin-users.html') ? 'active' : ''}">
                                    <i class="ti ti-shield-lock me-1"></i>
                                    Админ-панель
                                </a></li>
                                ` : ''}
                                ${isFactor ? `
                                <li><a href="/admin-companies.html" class="dropdown-item ${this.isActivePage('/admin-companies.html') ? 'active' : ''}">
                                    <i class="ti ti-building me-1"></i>
                                    Управление компаниями
                                </a></li>
                                ` : ''}
                                <li><hr class="dropdown-divider"></li>
                                <li><a href="#" class="dropdown-item" onclick="navigationLogout(event)">
                                    <i class="ti ti-logout me-1"></i>
                                    Выход
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>
        `;
    }

    /**
     * Get company type badge HTML
     */
    getCompanyTypeBadge() {
        if (!this.currentUser?.company?.type) return '';

        const companyType = this.currentUser.company.type.toLowerCase();
        const badges = {
            'factor': '<span class="badge bg-blue text-white ms-2" style="padding: 0.25rem 0.5rem;">Фактор</span>',
            'supplier': '<span class="badge bg-green text-white ms-2" style="padding: 0.25rem 0.5rem;">Поставщик</span>',
            'debtor': '<span class="badge bg-orange text-white ms-2" style="padding: 0.25rem 0.5rem;">Дебитор</span>'
        };

        return badges[companyType] || '';
    }

    /**
     * Render navigation into the page
     */
    render() {
        const navContainer = document.getElementById('navigation-container');
        if (navContainer) {
            navContainer.innerHTML = this.getHTML();

            // Initialize Bootstrap dropdowns manually for dynamically added content
            setTimeout(() => {
                // Check if Bootstrap is available - try both module and global scope
                const Bootstrap = typeof bootstrap !== 'undefined' ? bootstrap : window.bootstrap;

                if (!Bootstrap) {
                    console.warn('Bootstrap not loaded, dropdowns may not work');
                    return;
                }

                const dropdownTriggers = navContainer.querySelectorAll('[data-bs-toggle="dropdown"]');
                dropdownTriggers.forEach(trigger => {
                    // Dispose existing dropdown instance if any
                    const existingDropdown = Bootstrap.Dropdown.getInstance(trigger);
                    if (existingDropdown) {
                        existingDropdown.dispose();
                    }
                    // Create new dropdown instance
                    new Bootstrap.Dropdown(trigger);
                });
            }, 50);
        }
    }
}

/**
 * Logout function (called from navigation)
 */
function navigationLogout(event) {
    event.preventDefault();
    localStorage.removeItem('accessToken');
    localStorage.removeItem('refreshToken');
    window.location.href = '/login.html';
}

/**
 * Initialize navigation when DOM is ready
 */
document.addEventListener('DOMContentLoaded', async () => {
    const nav = new Navigation();
    await nav.init();
});
