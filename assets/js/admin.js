// Admin Dashboard JavaScript

// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    }
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });
});

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Number formatting
function formatNumber(num, decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function formatCurrency(num, decimals = 2) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

// Real-time updates
function updateDashboard() {
    // This would typically make AJAX calls to update dashboard data
    // For now, we'll just reload the page
    if (window.location.pathname.includes('index.php')) {
        location.reload();
    }
}

// Auto-refresh dashboard every 30 seconds
if (window.location.pathname.includes('index.php') || window.location.pathname.endsWith('/admin/')) {
    // Reduce auto-refresh frequency to improve performance
    setInterval(updateDashboard, 60000); // Changed from 30s to 60s
}

// Confirmation dialogs
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Optimized price update function
const debouncedPriceUpdate = debounce(updatePriceDisplay, 1000);

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('Copied to clipboard!', 'success');
    }).catch(function() {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Copied to clipboard!', 'success');
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.innerHTML = message;
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        notification.style.opacity = '0';
        setTimeout(function() {
            notification.remove();
        }, 300);
    }, 3000);
}

// Trading form helpers
function calculatePositionSize() {
    const balance = parseFloat(document.getElementById('available_balance')?.value || 0);
    const riskPercent = parseFloat(document.getElementById('risk_percentage')?.value || 2);
    const price = parseFloat(document.getElementById('entry_price')?.value || 0);
    
    if (balance > 0 && price > 0) {
        const riskAmount = balance * (riskPercent / 100);
        const quantity = riskAmount / price;
        
        const quantityField = document.getElementById('quantity');
        if (quantityField) {
            quantityField.value = quantity.toFixed(6);
        }
    }
}

// Chart helpers (if using Chart.js)
function createChart(canvasId, data, options = {}) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    };
    
    return new Chart(ctx, {
        type: 'line',
        data: data,
        options: { ...defaultOptions, ...options }
    });
}

// WebSocket connection for real-time updates
let ws = null;

function connectWebSocket() {
    if (window.WebSocket) {
        try {
            ws = new WebSocket('ws://localhost:8080');
            
            ws.onopen = function() {
                console.log('WebSocket connected');
            };
            
            ws.onmessage = function(event) {
                const data = JSON.parse(event.data);
                handleWebSocketMessage(data);
            };
            
            ws.onclose = function() {
                console.log('WebSocket disconnected');
                // Attempt to reconnect after 5 seconds
                setTimeout(connectWebSocket, 5000);
            };
            
            ws.onerror = function(error) {
                console.error('WebSocket error:', error);
            };
        } catch (error) {
            console.error('WebSocket connection failed:', error);
        }
    }
}

function handleWebSocketMessage(data) {
    switch (data.type) {
        case 'price_update':
            updatePriceDisplay(data.symbol, data.price);
            break;
        case 'balance_update':
            updateBalanceDisplay(data.balance);
            break;
        case 'new_signal':
            showNotification(`New AI signal: ${data.signal} ${data.symbol}`, 'info');
            break;
        case 'trade_executed':
            showNotification(`Trade executed: ${data.side} ${data.symbol}`, 'success');
            break;
    }
}

function updatePriceDisplay(symbol, price) {
    const priceElements = document.querySelectorAll(`[data-symbol="${symbol}"]`);
    priceElements.forEach(function(element) {
        element.textContent = formatCurrency(price);
    });
}

function updateBalanceDisplay(balance) {
    const balanceElements = document.querySelectorAll('[data-balance]');
    balanceElements.forEach(function(element) {
        element.textContent = formatCurrency(balance);
    });
}

// Initialize WebSocket connection
// connectWebSocket();

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    // Ctrl/Cmd + R for refresh
    if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
        event.preventDefault();
        location.reload();
    }
    
    // Escape to close modals/dropdowns
    if (event.key === 'Escape') {
        const openDropdowns = document.querySelectorAll('.dropdown.open');
        openDropdowns.forEach(function(dropdown) {
            dropdown.classList.remove('open');
        });
    }
});

// Initialize tooltips (if using a tooltip library)
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(function(element) {
        element.addEventListener('mouseenter', function() {
            showTooltip(this, this.getAttribute('data-tooltip'));
        });
        
        element.addEventListener('mouseleave', function() {
            hideTooltip();
        });
    });
}

function showTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.position = 'absolute';
    tooltip.style.background = '#1f2937';
    tooltip.style.color = 'white';
    tooltip.style.padding = '8px 12px';
    tooltip.style.borderRadius = '4px';
    tooltip.style.fontSize = '12px';
    tooltip.style.zIndex = '9999';
    tooltip.style.pointerEvents = 'none';
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
}

function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    
    // Add loading states to forms
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function() {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Loading...';
            }
        });
    });
});