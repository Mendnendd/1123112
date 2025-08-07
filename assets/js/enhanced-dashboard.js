// Enhanced Dashboard JavaScript

class EnhancedDashboard {
    constructor() {
        this.charts = {};
        this.updateIntervals = {};
        this.websocket = null;
        this.init();
    }
    
    init() {
        this.initializeCharts();
        this.setupEventListeners();
        this.startLiveUpdates();
        this.initializeWebSocket();
    }
    
    initializeCharts() {
        // Portfolio Performance Chart
        this.initPortfolioChart();
        
        // AI Signals Performance Chart
        this.initSignalsChart();
        
        // Market Heatmap
        this.initMarketHeatmap();
    }
    
    initPortfolioChart() {
        const ctx = document.getElementById('portfolioChart');
        if (!ctx) return;
        
        this.charts.portfolio = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: performanceData.map(item => item.date),
                datasets: [{
                    label: 'Portfolio Value',
                    data: performanceData.map(item => parseFloat(item.ending_balance)),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }, {
                    label: 'Daily P&L',
                    data: performanceData.map(item => parseFloat(item.total_pnl)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: false,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: '#3b82f6',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
    
    initSignalsChart() {
        const ctx = document.getElementById('signalsChart');
        if (!ctx) return;
        
        const totalSignals = performanceData.reduce((sum, item) => sum + parseInt(item.ai_signals_generated), 0);
        const executedSignals = performanceData.reduce((sum, item) => sum + parseInt(item.ai_signals_executed), 0);
        const successfulSignals = performanceData.reduce((sum, item) => sum + parseInt(item.winning_trades), 0);
        
        this.charts.signals = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Executed', 'Pending', 'Successful'],
                datasets: [{
                    data: [executedSignals, totalSignals - executedSignals, successfulSignals],
                    backgroundColor: [
                        '#3b82f6',
                        '#f59e0b',
                        '#10b981'
                    ],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed * 100) / total).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
    
    initMarketHeatmap() {
        // Initialize market heatmap visualization
        this.updateMarketHeatmap();
    }
    
    setupEventListeners() {
        // Chart control buttons
        document.querySelectorAll('[onclick*="toggleChartType"]').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const chartType = e.target.getAttribute('onclick').match(/'([^']+)'/)[1];
                this.toggleChartType(chartType);
            });
        });
        
        // Refresh buttons
        document.querySelectorAll('[onclick*="refresh"]').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const refreshType = e.target.getAttribute('onclick').match(/refresh(\w+)/)[1];
                this.refreshData(refreshType.toLowerCase());
            });
        });
        
        // Notification actions
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                this.markNotificationAsRead(item);
            });
        });
    }
    
    startLiveUpdates() {
        // Update market data every 10 seconds
        this.updateIntervals.market = setInterval(() => {
            this.refreshMarketData();
        }, 10000);
        
        // Update portfolio data every 30 seconds
        this.updateIntervals.portfolio = setInterval(() => {
            this.refreshPortfolioData();
        }, 30000);
        
        // Update charts every 60 seconds
        this.updateIntervals.charts = setInterval(() => {
            this.refreshCharts();
        }, 60000);
        
        // Update notifications every 30 seconds
        this.updateIntervals.notifications = setInterval(() => {
            this.refreshNotifications();
        }, 30000);
    }
    
    initializeWebSocket() {
        // Initialize WebSocket connection for real-time updates
        if (window.WebSocket) {
            try {
                this.websocket = new WebSocket('ws://localhost:8080');
                
                this.websocket.onopen = () => {
                    console.log('WebSocket connected');
                    this.showNotification('Real-time updates connected', 'success');
                };
                
                this.websocket.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    this.handleWebSocketMessage(data);
                };
                
                this.websocket.onclose = () => {
                    console.log('WebSocket disconnected');
                    // Attempt to reconnect after 5 seconds
                    setTimeout(() => this.initializeWebSocket(), 5000);
                };
                
                this.websocket.onerror = (error) => {
                    console.error('WebSocket error:', error);
                };
            } catch (error) {
                console.error('WebSocket connection failed:', error);
            }
        }
    }
    
    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'price_update':
                this.updatePriceDisplay(data.symbol, data.prices);
                break;
            case 'portfolio_update':
                this.updatePortfolioDisplay(data.portfolio);
                break;
            case 'new_signal':
                this.addNewSignal(data.signal);
                this.showNotification(`New AI signal: ${data.signal.signal} ${data.signal.symbol}`, 'info');
                break;
            case 'trade_executed':
                this.addNewTrade(data.trade);
                this.showNotification(`Trade executed: ${data.trade.side} ${data.trade.symbol}`, 'success');
                break;
            case 'position_update':
                this.updatePositionsDisplay(data.positions);
                break;
            case 'balance_update':
                this.updateBalanceDisplay(data.balances);
                break;
        }
    }
    
    refreshMarketData() {
        fetch('../api/enhanced-market-data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateMarketDisplay(data.prices);
                    this.updateMarketHeatmap(data.prices);
                }
            })
            .catch(error => {
                console.error('Error fetching market data:', error);
                this.showNotification('Failed to update market data', 'error');
            });
    }
    
    refreshPortfolioData() {
        fetch('../api/portfolio-data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updatePortfolioDisplay(data.portfolio);
                    this.updatePortfolioStats(data.stats);
                }
            })
            .catch(error => {
                console.error('Error fetching portfolio data:', error);
            });
    }
    
    refreshCharts() {
        // Refresh portfolio chart
        fetch('../api/performance-data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && this.charts.portfolio) {
                    this.charts.portfolio.data.labels = data.performance.map(item => item.date);
                    this.charts.portfolio.data.datasets[0].data = data.performance.map(item => parseFloat(item.ending_balance));
                    this.charts.portfolio.data.datasets[1].data = data.performance.map(item => parseFloat(item.total_pnl));
                    this.charts.portfolio.update('none');
                }
            })
            .catch(error => console.error('Error refreshing charts:', error));
    }
    
    refreshNotifications() {
        fetch('../api/notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateNotificationsDisplay(data.notifications);
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }
    
    updateMarketDisplay(prices) {
        Object.keys(prices).forEach(symbol => {
            const marketItem = document.querySelector(`[data-symbol="${symbol}"]`);
            if (marketItem) {
                // Update futures price
                const futuresPrice = marketItem.querySelector('.price-row:first-child .price-value');
                const futuresChange = marketItem.querySelector('.price-row:first-child .price-change');
                
                if (futuresPrice && prices[symbol].futures) {
                    futuresPrice.textContent = '$' + parseFloat(prices[symbol].futures.price).toFixed(4);
                    
                    // Add price change animation
                    futuresPrice.classList.add('price-updated');
                    setTimeout(() => futuresPrice.classList.remove('price-updated'), 1000);
                }
                
                if (futuresChange && prices[symbol].futures) {
                    const change = parseFloat(prices[symbol].futures.change);
                    futuresChange.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '%';
                    futuresChange.className = 'price-change ' + (change >= 0 ? 'positive' : 'negative');
                }
                
                // Update spot price
                const spotPrice = marketItem.querySelector('.price-row:last-child .price-value');
                const spotChange = marketItem.querySelector('.price-row:last-child .price-change');
                
                if (spotPrice && prices[symbol].spot) {
                    spotPrice.textContent = '$' + parseFloat(prices[symbol].spot.price).toFixed(4);
                    
                    // Add price change animation
                    spotPrice.classList.add('price-updated');
                    setTimeout(() => spotPrice.classList.remove('price-updated'), 1000);
                }
                
                if (spotChange && prices[symbol].spot) {
                    const change = parseFloat(prices[symbol].spot.change);
                    spotChange.textContent = (change >= 0 ? '+' : '') + change.toFixed(2) + '%';
                    spotChange.className = 'price-change ' + (change >= 0 ? 'positive' : 'negative');
                }
            }
        });
    }
    
    updateMarketHeatmap(prices) {
        // Update market heatmap visualization
        const heatmapContainer = document.getElementById('marketHeatmap');
        if (!heatmapContainer) return;
        
        // Implementation for market heatmap
        // This would create a visual heatmap of price changes
    }
    
    updatePortfolioDisplay(portfolio) {
        // Update portfolio value
        const portfolioValue = document.querySelector('.portfolio-value .stat-value');
        if (portfolioValue) {
            portfolioValue.textContent = '$' + parseFloat(portfolio.total_portfolio_value).toLocaleString();
        }
        
        // Update daily P&L
        const dailyPnl = document.querySelector('.portfolio-value .stat-change');
        if (dailyPnl) {
            const pnl = parseFloat(portfolio.daily_pnl);
            const pnlPercent = parseFloat(portfolio.daily_pnl_percentage);
            dailyPnl.textContent = (pnl >= 0 ? '+' : '') + '$' + pnl.toFixed(2) + ' (' + pnlPercent.toFixed(2) + '%)';
            dailyPnl.className = 'stat-change ' + (pnl >= 0 ? 'positive' : 'negative');
        }
        
        // Update spot balance
        const spotBalance = document.querySelector('.spot-balance .stat-value');
        if (spotBalance) {
            spotBalance.textContent = '$' + parseFloat(portfolio.spot_balance_usdt).toLocaleString();
        }
        
        // Update futures balance
        const futuresBalance = document.querySelector('.futures-balance .stat-value');
        if (futuresBalance) {
            futuresBalance.textContent = '$' + parseFloat(portfolio.futures_balance_usdt).toLocaleString();
        }
        
        // Update unrealized P&L
        const unrealizedPnl = document.querySelector('.unrealized-pnl .stat-value');
        if (unrealizedPnl) {
            const pnl = parseFloat(portfolio.total_unrealized_pnl);
            unrealizedPnl.textContent = (pnl >= 0 ? '+' : '') + '$' + pnl.toFixed(2);
            unrealizedPnl.className = 'stat-value ' + (pnl >= 0 ? 'positive' : 'negative');
        }
    }
    
    addNewSignal(signal) {
        const signalsList = document.querySelector('.signals-list');
        if (!signalsList) return;
        
        const signalElement = this.createSignalElement(signal);
        signalsList.insertBefore(signalElement, signalsList.firstChild);
        
        // Remove oldest signal if more than 10
        const signals = signalsList.querySelectorAll('.signal-item');
        if (signals.length > 10) {
            signals[signals.length - 1].remove();
        }
        
        // Animate new signal
        signalElement.style.opacity = '0';
        signalElement.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            signalElement.style.transition = 'all 0.3s ease';
            signalElement.style.opacity = '1';
            signalElement.style.transform = 'translateX(0)';
        }, 100);
    }
    
    addNewTrade(trade) {
        const tradesList = document.querySelector('.trades-list');
        if (!tradesList) return;
        
        const tradeElement = this.createTradeElement(trade);
        tradesList.insertBefore(tradeElement, tradesList.firstChild);
        
        // Remove oldest trade if more than 10
        const trades = tradesList.querySelectorAll('.trade-item');
        if (trades.length > 10) {
            trades[trades.length - 1].remove();
        }
        
        // Animate new trade
        tradeElement.style.opacity = '0';
        tradeElement.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            tradeElement.style.transition = 'all 0.3s ease';
            tradeElement.style.opacity = '1';
            tradeElement.style.transform = 'translateX(0)';
        }, 100);
    }
    
    createSignalElement(signal) {
        const element = document.createElement('div');
        element.className = 'signal-item';
        element.innerHTML = `
            <div class="signal-info">
                <span class="signal-symbol">${signal.symbol}</span>
                <span class="signal-type ${signal.signal.toLowerCase()}">${signal.signal}</span>
                <span class="signal-strength ${signal.strength.toLowerCase()}">${signal.strength}</span>
                <span class="trading-type ${signal.trading_type.toLowerCase()}">${signal.trading_type}</span>
            </div>
            <div class="signal-meta">
                <div class="confidence-display">
                    <div class="confidence-bar">
                        <div class="confidence-fill" style="width: ${signal.confidence * 100}%"></div>
                    </div>
                    <span class="confidence-text">${(signal.confidence * 100).toFixed(1)}%</span>
                </div>
                <span class="signal-time">Now</span>
            </div>
        `;
        return element;
    }
    
    createTradeElement(trade) {
        const element = document.createElement('div');
        element.className = 'trade-item';
        element.innerHTML = `
            <div class="trade-info">
                <span class="trade-symbol">${trade.symbol}</span>
                <span class="trading-type ${trade.trading_type.toLowerCase()}">${trade.trading_type}</span>
                <span class="trade-side ${trade.side.toLowerCase()}">${trade.side}</span>
                <span class="trade-quantity">${trade.quantity}</span>
            </div>
            <div class="trade-meta">
                <span class="trade-pnl ${trade.profit_loss >= 0 ? 'positive' : 'negative'}">
                    ${trade.profit_loss >= 0 ? '+' : ''}$${trade.profit_loss.toFixed(2)}
                </span>
                <span class="trade-time">Now</span>
                ${trade.strategy_used ? `<span class="strategy-badge">${trade.strategy_used}</span>` : ''}
            </div>
        `;
        return element;
    }
    
    toggleChartType(chartType) {
        if (chartType === 'portfolio' && this.charts.portfolio) {
            // Toggle between line and bar chart
            const currentType = this.charts.portfolio.config.type;
            const newType = currentType === 'line' ? 'bar' : 'line';
            
            this.charts.portfolio.config.type = newType;
            this.charts.portfolio.update();
        }
    }
    
    refreshData(dataType) {
        switch (dataType) {
            case 'portfolio':
                this.refreshPortfolioData();
                break;
            case 'market':
                this.refreshMarketData();
                break;
            case 'signals':
                this.refreshSignalsData();
                break;
            case 'spotbalances':
                this.refreshSpotBalances();
                break;
            default:
                console.warn('Unknown data type:', dataType);
        }
    }
    
    refreshSignalsData() {
        fetch('../api/ai-signals.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateSignalsDisplay(data.signals);
                }
            })
            .catch(error => console.error('Error fetching signals:', error));
    }
    
    refreshSpotBalances() {
        fetch('../api/spot-balances.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateSpotBalancesDisplay(data.balances);
                }
            })
            .catch(error => console.error('Error fetching spot balances:', error));
    }
    
    updateNotificationsDisplay(notifications) {
        const notificationsBar = document.querySelector('.notifications-bar');
        const notificationsList = document.querySelector('.notifications-list');
        
        if (!notificationsList) return;
        
        if (notifications.length === 0) {
            if (notificationsBar) {
                notificationsBar.style.display = 'none';
            }
            return;
        }
        
        if (notificationsBar) {
            notificationsBar.style.display = 'block';
        }
        
        notificationsList.innerHTML = '';
        notifications.forEach(notification => {
            const element = this.createNotificationElement(notification);
            notificationsList.appendChild(element);
        });
    }
    
    createNotificationElement(notification) {
        const element = document.createElement('div');
        element.className = `notification-item priority-${notification.priority.toLowerCase()}`;
        element.innerHTML = `
            <div class="notification-content">
                <strong>${notification.title}</strong>
                <p>${notification.message}</p>
            </div>
            <div class="notification-time">
                ${new Date(notification.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
            </div>
        `;
        
        element.addEventListener('click', () => {
            this.markNotificationAsRead(element, notification.id);
        });
        
        return element;
    }
    
    markNotificationAsRead(element, notificationId) {
        if (notificationId) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    action: 'mark_read', 
                    notification_id: notificationId 
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.style.opacity = '0.5';
                    setTimeout(() => element.remove(), 300);
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
        }
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `toast-notification toast-${type}`;
        notification.innerHTML = `
            <div class="toast-content">
                <span class="toast-icon">${this.getToastIcon(type)}</span>
                <span class="toast-message">${message}</span>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        // Add to page
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        
        toastContainer.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    getToastIcon(type) {
        const icons = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        return icons[type] || icons.info;
    }
    
    destroy() {
        // Clean up intervals
        Object.values(this.updateIntervals).forEach(interval => {
            clearInterval(interval);
        });
        
        // Close WebSocket
        if (this.websocket) {
            this.websocket.close();
        }
        
        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
    }
}

// Global functions for backward compatibility
function toggleChartType(chartType) {
    if (window.enhancedDashboard) {
        window.enhancedDashboard.toggleChartType(chartType);
    }
}

function refreshPortfolioChart() {
    if (window.enhancedDashboard) {
        window.enhancedDashboard.refreshData('portfolio');
    }
}

function refreshMarketData() {
    if (window.enhancedDashboard) {
        window.enhancedDashboard.refreshData('market');
    }
}

function refreshSignalsChart() {
    if (window.enhancedDashboard) {
        window.enhancedDashboard.refreshData('signals');
    }
}

function refreshSpotBalances() {
    if (window.enhancedDashboard) {
        window.enhancedDashboard.refreshData('spotbalances');
    }
}

function markAllAsRead() {
    fetch('../api/notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'mark_all_read' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notificationsBar = document.querySelector('.notifications-bar');
            if (notificationsBar) {
                notificationsBar.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error marking notifications as read:', error));
}

// Initialize enhanced dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.enhancedDashboard = new EnhancedDashboard();
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (window.enhancedDashboard) {
        window.enhancedDashboard.destroy();
    }
});