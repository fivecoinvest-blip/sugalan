<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Analytics Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="/css/enhanced-ui.css">
    <link rel="stylesheet" href="/css/animations.css">
    <link rel="stylesheet" href="/css/mobile-responsive.css">
    <style>
        .analytics-dashboard {
            padding: 24px;
            background: #f9fafb;
            min-height: 100vh;
        }
        
        .dashboard-header {
            margin-bottom: 32px;
        }
        
        .dashboard-title {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }
        
        .dashboard-subtitle {
            color: #6b7280;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon-primary {
            background: #dbeafe;
            color: #3b82f6;
        }
        
        .stat-icon-success {
            background: #d1fae5;
            color: #10b981;
        }
        
        .stat-icon-warning {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .stat-icon-purple {
            background: #ede9fe;
            color: #8b5cf6;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .stat-trend {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .stat-trend-up {
            color: #10b981;
        }
        
        .stat-trend-down {
            color: #ef4444;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .chart-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }
        
        .chart-filter {
            display: flex;
            gap: 8px;
        }
        
        .filter-btn {
            padding: 6px 12px;
            font-size: 14px;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-btn:hover {
            background: #f9fafb;
        }
        
        .filter-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .data-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }
        
        .table-actions {
            display: flex;
            gap: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            padding: 12px 24px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        td {
            padding: 16px 24px;
            border-top: 1px solid #f3f4f6;
            color: #374151;
        }
        
        tbody tr:hover {
            background: #f9fafb;
        }
        
        .export-btn {
            padding: 8px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .export-btn:hover {
            background: #2563eb;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .chart-filter {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="analytics-dashboard">
        <!-- Header -->
        <div class="dashboard-header animate-fade-in-down">
            <h1 class="dashboard-title">Analytics Dashboard</h1>
            <p class="dashboard-subtitle">Real-time platform metrics and insights</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card animate-fade-in-up stagger-1">
                <div class="stat-card-header">
                    <div class="stat-icon stat-icon-primary">💰</div>
                </div>
                <div class="stat-value" id="totalRevenue">₱0</div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-trend stat-trend-up">
                    ↑ <span id="revenueTrend">0%</span> from last month
                </div>
            </div>

            <div class="stat-card animate-fade-in-up stagger-2">
                <div class="stat-card-header">
                    <div class="stat-icon stat-icon-success">👥</div>
                </div>
                <div class="stat-value" id="totalPlayers">0</div>
                <div class="stat-label">Active Players</div>
                <div class="stat-trend stat-trend-up">
                    ↑ <span id="playersTrend">0%</span> from last month
                </div>
            </div>

            <div class="stat-card animate-fade-in-up stagger-3">
                <div class="stat-card-header">
                    <div class="stat-icon stat-icon-warning">🎮</div>
                </div>
                <div class="stat-value" id="totalBets">0</div>
                <div class="stat-label">Total Bets</div>
                <div class="stat-trend stat-trend-up">
                    ↑ <span id="betsTrend">0%</span> from yesterday
                </div>
            </div>

            <div class="stat-card animate-fade-in-up stagger-4">
                <div class="stat-card-header">
                    <div class="stat-icon stat-icon-purple">⭐</div>
                </div>
                <div class="stat-value" id="vipPlayers">0</div>
                <div class="stat-label">VIP Players</div>
                <div class="stat-trend stat-trend-up">
                    ↑ <span id="vipTrend">0%</span> from last week
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <!-- Revenue Chart -->
            <div class="chart-card animate-fade-in-up stagger-1">
                <div class="chart-header">
                    <h3 class="chart-title">Revenue Overview</h3>
                    <div class="chart-filter">
                        <button class="filter-btn active" onclick="updateRevenueChart('7d')">7 Days</button>
                        <button class="filter-btn" onclick="updateRevenueChart('30d')">30 Days</button>
                        <button class="filter-btn" onclick="updateRevenueChart('90d')">90 Days</button>
                    </div>
                </div>
                <canvas id="revenueChart" height="300"></canvas>
            </div>

            <!-- Player Activity Chart -->
            <div class="chart-card animate-fade-in-up stagger-2">
                <div class="chart-header">
                    <h3 class="chart-title">Player Activity</h3>
                    <div class="chart-filter">
                        <button class="filter-btn active" onclick="updateActivityChart('7d')">7 Days</button>
                        <button class="filter-btn" onclick="updateActivityChart('30d')">30 Days</button>
                    </div>
                </div>
                <canvas id="activityChart" height="300"></canvas>
            </div>

            <!-- Game Popularity Chart -->
            <div class="chart-card animate-fade-in-up stagger-3">
                <div class="chart-header">
                    <h3 class="chart-title">Game Popularity</h3>
                </div>
                <canvas id="gameChart" height="300"></canvas>
            </div>

            <!-- VIP Distribution Chart -->
            <div class="chart-card animate-fade-in-up stagger-4">
                <div class="chart-header">
                    <h3 class="chart-title">VIP Tier Distribution</h3>
                </div>
                <canvas id="vipChart" height="300"></canvas>
            </div>
        </div>

        <!-- Recent Transactions Table -->
        <div class="data-table animate-fade-in-up">
            <div class="table-header">
                <h3 class="table-title">Recent Transactions</h3>
                <div class="table-actions">
                    <button class="export-btn" onclick="exportTransactions()">Export CSV</button>
                </div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="transactionsTable">
                        <!-- Populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner spinner-lg"></div>
    </div>

    <script>
        // Initialize charts
        let revenueChart, activityChart, gameChart, vipChart;

        // API base URL
        const API_URL = '/api/admin';

        // Fetch dashboard data
        async function fetchDashboardData() {
            showLoading();
            try {
                const response = await fetch(`${API_URL}/analytics/dashboard`, {
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`,
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) throw new Error('Failed to fetch dashboard data');
                
                const data = await response.json();
                updateDashboard(data);
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
                alert('Failed to load dashboard data');
            } finally {
                hideLoading();
            }
        }

        // Update dashboard with data
        function updateDashboard(data) {
            // Update stats
            document.getElementById('totalRevenue').textContent = formatCurrency(data.totalRevenue);
            document.getElementById('revenueTrend').textContent = data.revenueTrend + '%';
            
            document.getElementById('totalPlayers').textContent = formatNumber(data.totalPlayers);
            document.getElementById('playersTrend').textContent = data.playersTrend + '%';
            
            document.getElementById('totalBets').textContent = formatNumber(data.totalBets);
            document.getElementById('betsTrend').textContent = data.betsTrend + '%';
            
            document.getElementById('vipPlayers').textContent = formatNumber(data.vipPlayers);
            document.getElementById('vipTrend').textContent = data.vipTrend + '%';

            // Initialize charts
            initRevenueChart(data.revenueData);
            initActivityChart(data.activityData);
            initGameChart(data.gameData);
            initVipChart(data.vipData);

            // Update transactions table
            updateTransactionsTable(data.recentTransactions);
        }

        // Revenue Chart
        function initRevenueChart(data) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Revenue',
                        data: data.values,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => '₱' + formatNumber(value)
                            }
                        }
                    }
                }
            });
        }

        // Player Activity Chart
        function initActivityChart(data) {
            const ctx = document.getElementById('activityChart').getContext('2d');
            activityChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Active Players',
                        data: data.values,
                        backgroundColor: '#10b981'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Game Popularity Chart
        function initGameChart(data) {
            const ctx = document.getElementById('gameChart').getContext('2d');
            gameChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
                            '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }

        // VIP Distribution Chart
        function initVipChart(data) {
            const ctx = document.getElementById('vipChart').getContext('2d');
            vipChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: ['#9ca3af', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Update transactions table
        function updateTransactionsTable(transactions) {
            const tbody = document.getElementById('transactionsTable');
            tbody.innerHTML = transactions.map(tx => `
                <tr>
                    <td>${formatDate(tx.created_at)}</td>
                    <td>${tx.user_name}</td>
                    <td><span class="badge badge-${getBadgeColor(tx.type)}">${tx.type}</span></td>
                    <td>${formatCurrency(tx.amount)}</td>
                    <td><span class="badge badge-${getStatusColor(tx.status)}">${tx.status}</span></td>
                </tr>
            `).join('');
        }

        // Export transactions
        function exportTransactions() {
            window.location.href = `${API_URL}/analytics/export?token=${localStorage.getItem('admin_token')}`;
        }

        // Helper functions
        function formatCurrency(value) {
            return '₱' + parseFloat(value).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatNumber(value) {
            return parseInt(value).toLocaleString('en-PH');
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function getBadgeColor(type) {
            const colors = {
                'deposit': 'success',
                'withdrawal': 'warning',
                'bet': 'primary',
                'win': 'success'
            };
            return colors[type] || 'gray';
        }

        function getStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'approved': 'success',
                'rejected': 'error',
                'completed': 'success'
            };
            return colors[status] || 'gray';
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        // Load dashboard on page load
        document.addEventListener('DOMContentLoaded', fetchDashboardData);

        // Refresh every 60 seconds
        setInterval(fetchDashboardData, 60000);
    </script>
</body>
</html>
