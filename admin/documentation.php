<?php
require_once '../config/app.php';

$auth = new Auth();
$auth->requireAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation - <?php echo APP_NAME; ?></title>
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1>Documentation</h1>
        </div>
        
        <div class="documentation-container">
            <div class="doc-sidebar">
                <nav class="doc-nav">
                    <ul>
                        <li><a href="#getting-started" class="doc-link active">Getting Started</a></li>
                        <li><a href="#api-setup" class="doc-link">API Setup</a></li>
                        <li><a href="#trading-configuration" class="doc-link">Trading Configuration</a></li>
                        <li><a href="#ai-system" class="doc-link">AI System</a></li>
                        <li><a href="#risk-management" class="doc-link">Risk Management</a></li>
                        <li><a href="#troubleshooting" class="doc-link">Troubleshooting</a></li>
                        <li><a href="#api-reference" class="doc-link">API Reference</a></li>
                    </ul>
                </nav>
            </div>
            
            <div class="doc-content">
                <section id="getting-started" class="doc-section">
                    <h2>🚀 Getting Started</h2>
                    <p>Welcome to the Binance AI Trader! This comprehensive guide will help you set up and configure your automated trading system.</p>
                    
                    <h3>Quick Setup Checklist</h3>
                    <div class="checklist">
                        <div class="checklist-item">
                            <input type="checkbox" disabled> Complete installation via install.php
                        </div>
                        <div class="checklist-item">
                            <input type="checkbox" disabled> Create Binance account and API keys
                        </div>
                        <div class="checklist-item">
                            <input type="checkbox" disabled> Configure API credentials in Settings
                        </div>
                        <div class="checklist-item">
                            <input type="checkbox" disabled> Set up trading pairs
                        </div>
                        <div class="checklist-item">
                            <input type="checkbox" disabled> Configure risk management settings
                        </div>
                        <div class="checklist-item">
                            <input type="checkbox" disabled> Set up cron job for automated trading
                        </div>
                    </div>
                </section>
                
                <section id="api-setup" class="doc-section">
                    <h2>🔑 Binance API Setup</h2>
                    
                    <h3>Creating API Keys</h3>
                    <ol>
                        <li>Go to <a href="https://testnet.binancefuture.com" target="_blank">Binance Testnet</a> (for testing) or <a href="https://binance.com" target="_blank">Binance</a> (for live trading)</li>
                        <li>Create an account and complete verification</li>
                        <li>Enable 2FA (Two-Factor Authentication)</li>
                        <li>Navigate to API Management</li>
                        <li>Create a new API key</li>
                        <li>Enable "Futures" permissions</li>
                        <li>Restrict IP addresses (recommended)</li>
                    </ol>
                    
                    <div class="alert alert-warning">
                        <strong>Security Warning:</strong> Never share your API keys. Use testnet for development and testing.
                    </div>
                    
                    <h3>Configuring in System</h3>
                    <ol>
                        <li>Go to Settings in the admin panel</li>
                        <li>Enter your API Key and Secret</li>
                        <li>Enable Testnet mode for testing</li>
                        <li>Click "Test Connection" to verify</li>
                    </ol>
                </section>
                
                <section id="trading-configuration" class="doc-section">
                    <h2>💹 Trading Configuration</h2>
                    
                    <h3>Risk Management Settings</h3>
                    <table class="doc-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Description</th>
                                <th>Recommended</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Max Position Size</td>
                                <td>Maximum USDT per trade</td>
                                <td>$100</td>
                            </tr>
                            <tr>
                                <td>Risk Percentage</td>
                                <td>% of account balance to risk</td>
                                <td>2%</td>
                            </tr>
                            <tr>
                                <td>Stop Loss</td>
                                <td>Maximum loss per trade</td>
                                <td>5%</td>
                            </tr>
                            <tr>
                                <td>Take Profit</td>
                                <td>Target profit per trade</td>
                                <td>10%</td>
                            </tr>
                            <tr>
                                <td>Leverage</td>
                                <td>Trading leverage</td>
                                <td>10x</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                
                <section id="ai-system" class="doc-section">
                    <h2>🤖 AI Trading System</h2>
                    
                    <h3>Technical Indicators</h3>
                    <ul>
                        <li><strong>RSI (14):</strong> Identifies overbought/oversold conditions</li>
                        <li><strong>MACD:</strong> Trend following momentum indicator</li>
                        <li><strong>Bollinger Bands:</strong> Volatility and mean reversion signals</li>
                        <li><strong>Moving Averages:</strong> SMA 20 and SMA 50 for trend analysis</li>
                        <li><strong>Volume Analysis:</strong> Confirms price movements</li>
                    </ul>
                    
                    <h3>Signal Generation</h3>
                    <p>The AI system generates signals based on:</p>
                    <ul>
                        <li>Technical indicator convergence</li>
                        <li>Volume confirmation</li>
                        <li>Market momentum analysis</li>
                        <li>Risk-reward assessment</li>
                    </ul>
                    
                    <p>Only signals with >75% confidence are executed automatically.</p>
                </section>
                
                <section id="risk-management" class="doc-section">
                    <h2>🛡️ Risk Management</h2>
                    
                    <h3>Built-in Protections</h3>
                    <ul>
                        <li>Position size limits</li>
                        <li>Maximum drawdown monitoring</li>
                        <li>Automatic stop losses</li>
                        <li>Take profit orders</li>
                        <li>API rate limiting</li>
                    </ul>
                    
                    <h3>Best Practices</h3>
                    <ul>
                        <li>Start with small position sizes</li>
                        <li>Use testnet for development</li>
                        <li>Monitor trades closely</li>
                        <li>Regular strategy review</li>
                        <li>Never risk more than you can afford to lose</li>
                    </ul>
                </section>
                
                <section id="troubleshooting" class="doc-section">
                    <h2>🔧 Troubleshooting</h2>
                    
                    <h3>Common Issues</h3>
                    
                    <h4>API Connection Errors</h4>
                    <ul>
                        <li>Verify API keys are correct</li>
                        <li>Check API permissions (Futures enabled)</li>
                        <li>Ensure IP restrictions allow your server</li>
                        <li>Test with API Test page</li>
                    </ul>
                    
                    <h4>Trading Bot Not Running</h4>
                    <ul>
                        <li>Check cron job configuration</li>
                        <li>Verify trading is enabled in settings</li>
                        <li>Review system logs for errors</li>
                        <li>Ensure sufficient account balance</li>
                    </ul>
                    
                    <h4>No AI Signals Generated</h4>
                    <ul>
                        <li>Check if AI analysis is enabled</li>
                        <li>Verify trading pairs are active</li>
                        <li>Review market conditions</li>
                        <li>Check system logs for errors</li>
                    </ul>
                </section>
                
                <section id="api-reference" class="doc-section">
                    <h2>📚 API Reference</h2>
                    
                    <h3>REST Endpoints</h3>
                    
                    <h4>GET /api/signals.php</h4>
                    <p>Retrieve AI trading signals</p>
                    <pre><code>{
  "success": true,
  "data": [
    {
      "id": 1,
      "symbol": "BTCUSDT",
      "signal": "BUY",
      "confidence": 0.85,
      "price": 50000.00,
      "created_at": "2025-01-27 10:00:00"
    }
  ]
}</code></pre>
                    
                    <h4>POST /api/signals.php</h4>
                    <p>Generate new signal for symbol</p>
                    <pre><code>{
  "symbol": "BTCUSDT"
}</code></pre>
                </section>
            </div>
        </div>
    </main>
    
    <script src="../assets/js/admin.js"></script>
    <script>
        // Smooth scrolling for documentation links
        document.querySelectorAll('.doc-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                    
                    // Update active link
                    document.querySelectorAll('.doc-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
    </script>
    <style>
        .documentation-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        
        .doc-sidebar {
            position: sticky;
            top: 20px;
            height: fit-content;
        }
        
        .doc-nav ul {
            list-style: none;
            padding: 0;
        }
        
        .doc-nav li {
            margin-bottom: 5px;
        }
        
        .doc-link {
            display: block;
            padding: 10px 15px;
            color: #64748b;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .doc-link:hover,
        .doc-link.active {
            background: #f1f5f9;
            color: #3b82f6;
        }
        
        .doc-content {
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 30px;
        }
        
        .doc-section {
            margin-bottom: 40px;
        }
        
        .doc-section h2 {
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .doc-section h3 {
            color: #374151;
            margin: 25px 0 15px 0;
        }
        
        .doc-section h4 {
            color: #4b5563;
            margin: 20px 0 10px 0;
        }
        
        .checklist {
            background: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .doc-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .doc-table th,
        .doc-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .doc-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        pre {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 14px;
            line-height: 1.4;
        }
        
        code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 14px;
            color: #3b82f6;
        }
        
        pre code {
            background: none;
            padding: 0;
            color: inherit;
        }
        
        @media (max-width: 768px) {
            .documentation-container {
                grid-template-columns: 1fr;
            }
            
            .doc-sidebar {
                position: static;
            }
        }
    </style>
</body>
</html>