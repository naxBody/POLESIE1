/**
 * Основной JavaScript файл системы
 * ОАО "Полесьеэлектромаш"
 */

// Глобальные функции

/**
 * Показать уведомление
 */
function showNotification(message, type = 'info') {
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#06b6d4'
    };
    
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${colors[type] || colors.info};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        font-weight: 500;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/**
 * Подтверждение действия
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Форматирование даты
 */
function formatDate(dateString, format = 'DD.MM.YYYY') {
    if (!dateString) return '';
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    
    return format
        .replace('DD', day)
        .replace('MM', month)
        .replace('YYYY', year);
}

/**
 * Форматирование суммы
 */
function formatMoney(amount, currency = 'BYN') {
    const symbols = {
        BYN: 'Br',
        USD: '$',
        EUR: '€',
        RUB: '₽'
    };
    
    const symbol = symbols[currency] || currency;
    return new Intl.NumberFormat('ru-BY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount) + ' ' + symbol;
}

/**
 * AJAX запрос
 */
async function ajaxRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    if (data && method !== 'GET') {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Ошибка запроса');
        }
        
        return result;
    } catch (error) {
        console.error('AJAX Error:', error);
        showNotification(error.message, 'error');
        throw error;
    }
}

/**
 * Модальное окно
 */
function showModal(modalId) {
    const overlay = document.querySelector(modalId + '-overlay') || document.getElementById(modalId);
    if (overlay) {
        overlay.classList.add('active');
    }
}

function hideModal(modalId) {
    const overlay = document.querySelector(modalId + '-overlay') || document.getElementById(modalId);
    if (overlay) {
        overlay.classList.remove('active');
    }
}

/**
 * Табы
 */
function initTabs() {
    document.querySelectorAll('[data-tab]').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            const tabGroup = this.closest('[data-tab-group]');
            if (!tabGroup) return;
            
            // Удалить активный класс со всех табов
            tabGroup.querySelectorAll('[data-tab]').forEach(t => {
                t.classList.remove('active');
            });
            
            // Добавить активный класс текущему табу
            this.classList.add('active');
            
            // Скрыть все панели
            const target = this.getAttribute('data-tab');
            tabGroup.querySelectorAll('[data-tab-panel]').forEach(panel => {
                panel.style.display = 'none';
            });
            
            // Показать целевую панель
            const targetPanel = tabGroup.querySelector(`[data-tab-panel="${target}"]`);
            if (targetPanel) {
                targetPanel.style.display = 'block';
            }
        });
    });
}

/**
 * Автозаполнение поиска
 */
function initSearchAutocomplete() {
    const searchInput = document.querySelector('[data-search-autocomplete]');
    if (!searchInput) return;
    
    let debounceTimer;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const query = this.value.trim();
        
        if (query.length < 2) {
            hideSearchResults();
            return;
        }
        
        debounceTimer = setTimeout(() => {
            searchAjax(query);
        }, 300);
    });
}

/**
 * Печать документа
 */
function printDocument(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Печать документа</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f5f5f5; }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            ${element.innerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

/**
 * Экспорт таблицы в CSV
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            rowData.push('"' + col.textContent.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = '\ufeff' + csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

/**
 * Инициализация при загрузке страницы
 */
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initSearchAutocomplete();
    
    // Анимации для карточек
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
    
    // Подтверждение удаления
    document.querySelectorAll('[data-confirm-delete]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm-delete') || 'Вы уверены, что хотите удалить этот элемент?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});

/**
 * CSS анимации для уведомлений
 */
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
