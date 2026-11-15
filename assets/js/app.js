/**
 * Medis - Main Application JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initDropdowns();
    initFlashMessage();
    initGlobalSearch();
});

/**
 * Sidebar Toggle Functionality
 */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('medis_sidebar_collapsed', sidebar.classList.contains('collapsed'));
        });

        // Restore collapsed state
        if (localStorage.getItem('medis_sidebar_collapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }

    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
        });

        // Close mobile sidebar on outside click
        document.addEventListener('click', function (e) {
            if (!sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
            }
        });
    }
}

/**
 * Dropdown Menu Handlers
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');

    dropdowns.forEach(function (drop) {
        const toggle = drop.querySelector('.dropdown-toggle');
        const menu = drop.querySelector('.dropdown-menu');

        if (toggle && menu) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                // Close other dropdowns
                dropdowns.forEach(function (other) {
                    if (other !== drop) {
                        const otherMenu = other.querySelector('.dropdown-menu');
                        if (otherMenu) otherMenu.classList.remove('show');
                    }
                });
                menu.classList.toggle('show');
            });
        }
    });

    // Close on click outside
    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) {
            menu.classList.remove('show');
        });
    });
}

/**
 * Auto-dismiss flash messages
 */
function initFlashMessage() {
    const flash = document.getElementById('flashMessage');
    if (flash) {
        setTimeout(function () {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.4s ease';
            setTimeout(function () {
                flash.remove();
            }, 400);
        }, 5000);
    }
}

/**
 * Global search across tables
 */
function initGlobalSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const table = document.querySelector('.data-table tbody, table tbody');
        if (!table) return;

        const rows = table.querySelectorAll('tr');
        rows.forEach(function (row) {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    });
}

/**
 * Toast Notification System
 * @param {string} message
 * @param {string} type - 'success' | 'error' | 'warning' | 'info'
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    toast.innerHTML = `
        <i class="fas ${icons[type] || 'fa-info-circle'} toast-icon"></i>
        <div class="toast-content">${message}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;

    container.appendChild(toast);

    setTimeout(function () {
        toast.classList.add('toast-show');
    }, 10);

    setTimeout(function () {
        toast.classList.remove('toast-show');
        setTimeout(function () {
            toast.remove();
        }, 300);
    }, 4000);
}

/**
 * Modal Helper
 */
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Close modals on backdrop click or Escape key
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-backdrop') || e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.show').forEach(function (m) {
            m.classList.remove('show');
        });
        document.body.style.overflow = '';
    }
});

/**
 * Confirmation Dialog
 */
function confirmAction(message, onConfirm) {
    if (confirm(message)) {
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
        return true;
    }
    return false;
}

/**
 * Format Currency Helper
 */
function formatCurrency(amount) {
    return 'PKR ' + parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

