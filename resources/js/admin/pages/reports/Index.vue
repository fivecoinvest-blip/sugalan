<template>
  <div class="reports-page">
    <!-- Report Type Selector -->
    <div class="report-selector">
      <button 
        v-for="type in reportTypes" 
        :key="type.value"
        @click="selectedReportType = type.value"
        class="report-type-btn"
        :class="{ active: selectedReportType === type.value }"
      >
        <span class="btn-icon">{{ type.icon }}</span>
        <span class="btn-text">{{ type.label }}</span>
      </button>
    </div>

    <!-- Financial Report -->
    <div v-if="selectedReportType === 'financial'" class="report-section">
      <div class="section-header">
        <h2>💰 Financial Report</h2>
        <div class="date-range">
          <input v-model="financialFilters.date_from" type="date" class="date-input" @change="loadFinancialReport" />
          <span>to</span>
          <input v-model="financialFilters.date_to" type="date" class="date-input" @change="loadFinancialReport" />
          <button @click="exportReport('financial')" class="export-btn">📥 Export CSV</button>
        </div>
      </div>

      <div v-if="loadingFinancial" class="loading-state">
        <div class="spinner"></div>
        <p>Loading financial report...</p>
      </div>

      <div v-else class="report-content">
        <!-- Summary Cards -->
        <div class="summary-cards">
          <div class="summary-card deposits">
            <div class="card-icon">💵</div>
            <div class="card-content">
              <div class="card-label">Total Deposits</div>
              <div class="card-value">₱{{ formatMoney(financialReport.total_deposits) }}</div>
              <div class="card-count">{{ financialReport.deposit_count }} transactions</div>
            </div>
          </div>

          <div class="summary-card withdrawals">
            <div class="card-icon">💸</div>
            <div class="card-content">
              <div class="card-label">Total Withdrawals</div>
              <div class="card-value">₱{{ formatMoney(financialReport.total_withdrawals) }}</div>
              <div class="card-count">{{ financialReport.withdrawal_count }} transactions</div>
            </div>
          </div>

          <div class="summary-card wagered">
            <div class="card-icon">🎲</div>
            <div class="card-content">
              <div class="card-label">Total Wagered</div>
              <div class="card-value">₱{{ formatMoney(financialReport.total_wagered) }}</div>
              <div class="card-count">{{ financialReport.bet_count }} bets</div>
            </div>
          </div>

          <div class="summary-card profit">
            <div class="card-icon">📈</div>
            <div class="card-content">
              <div class="card-label">Net Revenue</div>
              <div class="card-value" :class="financialReport.net_revenue >= 0 ? 'positive' : 'negative'">
                ₱{{ formatMoney(financialReport.net_revenue) }}
              </div>
              <div class="card-count">{{ financialReport.profit_margin }}% margin</div>
            </div>
          </div>
        </div>

        <!-- Daily Breakdown -->
        <div class="daily-breakdown">
          <h3>📊 Daily Breakdown</h3>
          <table class="breakdown-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Deposits</th>
                <th>Withdrawals</th>
                <th>Wagered</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="day in financialReport.daily" :key="day.date">
                <td class="date-cell">{{ formatDate(day.date) }}</td>
                <td class="amount-cell positive">₱{{ formatMoney(day.deposits) }}</td>
                <td class="amount-cell negative">₱{{ formatMoney(day.withdrawals) }}</td>
                <td class="amount-cell">₱{{ formatMoney(day.wagered) }}</td>
                <td class="amount-cell" :class="day.revenue >= 0 ? 'positive' : 'negative'">
                  ₱{{ formatMoney(day.revenue) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- User Activity Report -->
    <div v-if="selectedReportType === 'users'" class="report-section">
      <div class="section-header">
        <h2>👥 User Activity Report</h2>
        <div class="date-range">
          <input v-model="userFilters.date_from" type="date" class="date-input" @change="loadUserReport" />
          <span>to</span>
          <input v-model="userFilters.date_to" type="date" class="date-input" @change="loadUserReport" />
          <button @click="exportReport('users')" class="export-btn">📥 Export CSV</button>
        </div>
      </div>

      <div v-if="loadingUsers" class="loading-state">
        <div class="spinner"></div>
        <p>Loading user report...</p>
      </div>

      <div v-else class="report-content">
        <!-- User Stats -->
        <div class="summary-cards">
          <div class="summary-card">
            <div class="card-icon">👤</div>
            <div class="card-content">
              <div class="card-label">Total Users</div>
              <div class="card-value">{{ formatNumber(userReport.total_users) }}</div>
            </div>
          </div>

          <div class="summary-card">
            <div class="card-icon">✨</div>
            <div class="card-content">
              <div class="card-label">New Users</div>
              <div class="card-value">{{ formatNumber(userReport.new_users) }}</div>
            </div>
          </div>

          <div class="summary-card">
            <div class="card-icon">🎯</div>
            <div class="card-content">
              <div class="card-label">Active Users</div>
              <div class="card-value">{{ formatNumber(userReport.active_users) }}</div>
            </div>
          </div>

          <div class="summary-card">
            <div class="card-icon">💎</div>
            <div class="card-content">
              <div class="card-label">VIP Users</div>
              <div class="card-value">{{ formatNumber(userReport.vip_users) }}</div>
            </div>
          </div>
        </div>

        <!-- Registration Breakdown -->
        <div class="vip-breakdown">
          <h3>🏆 VIP Distribution</h3>
          <div class="vip-grid">
            <div v-for="tier in userReport.vip_distribution" :key="tier.name" class="vip-card">
              <div class="vip-name" :class="`vip-${tier.name.toLowerCase()}`">{{ tier.name }}</div>
              <div class="vip-count">{{ tier.count }} users</div>
              <div class="vip-percentage">{{ tier.percentage }}%</div>
            </div>
          </div>
        </div>

        <!-- Auth Methods -->
        <div class="auth-breakdown">
          <h3>🔐 Authentication Methods</h3>
          <div class="auth-grid">
            <div v-for="method in userReport.auth_methods" :key="method.method" class="auth-card">
              <div class="auth-icon">{{ getAuthIcon(method.method) }}</div>
              <div class="auth-name">{{ capitalizeFirst(method.method) }}</div>
              <div class="auth-count">{{ method.count }} users</div>
              <div class="auth-percentage">{{ method.percentage }}%</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Game Performance Report -->
    <div v-if="selectedReportType === 'games'" class="report-section">
      <div class="section-header">
        <h2>🎮 Game Performance Report</h2>
        <div class="date-range">
          <input v-model="gameFilters.date_from" type="date" class="date-input" @change="loadGameReport" />
          <span>to</span>
          <input v-model="gameFilters.date_to" type="date" class="date-input" @change="loadGameReport" />
          <button @click="exportReport('games')" class="export-btn">📥 Export CSV</button>
        </div>
      </div>

      <div v-if="loadingGames" class="loading-state">
        <div class="spinner"></div>
        <p>Loading game report...</p>
      </div>

      <div v-else class="report-content">
        <div class="games-table">
          <table class="breakdown-table">
            <thead>
              <tr>
                <th>Game</th>
                <th>Bets</th>
                <th>Wagered</th>
                <th>Payouts</th>
                <th>House Edge</th>
                <th>Profit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="game in gameReport.games" :key="game.name">
                <td class="game-cell">
                  <span class="game-icon">{{ getGameIcon(game.name) }}</span>
                  {{ capitalizeFirst(game.name) }}
                </td>
                <td class="number-cell">{{ formatNumber(game.bets) }}</td>
                <td class="amount-cell">₱{{ formatMoney(game.wagered) }}</td>
                <td class="amount-cell">₱{{ formatMoney(game.payouts) }}</td>
                <td class="percentage-cell">{{ game.house_edge }}%</td>
                <td class="amount-cell" :class="game.profit >= 0 ? 'positive' : 'negative'">
                  ₱{{ formatMoney(game.profit) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const selectedReportType = ref('financial');
const loadingFinancial = ref(false);
const loadingUsers = ref(false);
const loadingGames = ref(false);

const reportTypes = [
  { value: 'financial', label: 'Financial', icon: '💰' },
  { value: 'users', label: 'Users', icon: '👥' },
  { value: 'games', label: 'Games', icon: '🎮' },
];

const financialFilters = reactive({
  date_from: '',
  date_to: '',
});

const userFilters = reactive({
  date_from: '',
  date_to: '',
});

const gameFilters = reactive({
  date_from: '',
  date_to: '',
});

const financialReport = ref({
  total_deposits: 0,
  total_withdrawals: 0,
  total_wagered: 0,
  net_revenue: 0,
  deposit_count: 0,
  withdrawal_count: 0,
  bet_count: 0,
  profit_margin: 0,
  daily: [],
});

const userReport = ref({
  total_users: 0,
  new_users: 0,
  active_users: 0,
  vip_users: 0,
  vip_distribution: [],
  auth_methods: [],
});

const gameReport = ref({
  games: [],
});

onMounted(() => {
  setDefaultDates();
  loadFinancialReport();
});

function setDefaultDates() {
  const today = new Date();
  const monthAgo = new Date(today);
  monthAgo.setMonth(monthAgo.getMonth() - 1);
  
  const dateStr = (d) => d.toISOString().split('T')[0];
  
  financialFilters.date_from = dateStr(monthAgo);
  financialFilters.date_to = dateStr(today);
  userFilters.date_from = dateStr(monthAgo);
  userFilters.date_to = dateStr(today);
  gameFilters.date_from = dateStr(monthAgo);
  gameFilters.date_to = dateStr(today);
}

async function loadFinancialReport() {
  loadingFinancial.value = true;

  try {
    const response = await axios.get('/api/admin/reports/financial', {
      params: financialFilters,
    });
    financialReport.value = response.data;
  } catch (err) {
    console.error('Error loading financial report:', err);
  } finally {
    loadingFinancial.value = false;
  }
}

async function loadUserReport() {
  loadingUsers.value = true;

  try {
    const response = await axios.get('/api/admin/reports/users', {
      params: userFilters,
    });
    userReport.value = response.data;
  } catch (err) {
    console.error('Error loading user report:', err);
  } finally {
    loadingUsers.value = false;
  }
}

async function loadGameReport() {
  loadingGames.value = true;

  try {
    const response = await axios.get('/api/admin/reports/games', {
      params: gameFilters,
    });
    gameReport.value = response.data;
  } catch (err) {
    console.error('Error loading game report:', err);
  } finally {
    loadingGames.value = false;
  }
}

function exportReport(type) {
  const filters = type === 'financial' ? financialFilters : 
                  type === 'users' ? userFilters : gameFilters;
  
  const params = new URLSearchParams(filters);
  window.open(`/api/admin/reports/${type}/export?${params}`, '_blank');
}

function getGameIcon(gameName) {
  const icons = {
    dice: '🎲',
    hilo: '🃏',
    mines: '💣',
    plinko: '🔴',
    keno: '🎱',
    wheel: '🎡',
    crash: '🚀',
  };
  return icons[gameName] || '🎮';
}

function getAuthIcon(method) {
  const icons = {
    phone: '📱',
    metamask: '🦊',
    telegram: '✈️',
    guest: '👤',
  };
  return icons[method] || '🔐';
}

function formatMoney(value) {
  if (!value) return '0.00';
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatNumber(value) {
  if (!value) return '0';
  return Number(value).toLocaleString('en-US');
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function capitalizeFirst(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>

<style scoped>
.reports-page {
  max-width: 1600px;
}

.report-selector {
  display: flex;
  gap: 15px;
  margin-bottom: 30px;
}

.report-type-btn {
  flex: 1;
  padding: 20px;
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.report-type-btn:hover {
  border-color: #cbd5e0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.report-type-btn.active {
  border-color: #667eea;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-icon {
  font-size: 32px;
}

.btn-text {
  font-weight: 600;
  font-size: 16px;
}

.report-section {
  background: white;
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid #e2e8f0;
}

.section-header h2 {
  font-size: 24px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.date-range {
  display: flex;
  align-items: center;
  gap: 10px;
}

.date-input {
  padding: 10px 15px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
}

.date-input:focus {
  outline: none;
  border-color: #667eea;
}

.export-btn {
  padding: 10px 20px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.export-btn:hover {
  background: #38a169;
}

.loading-state {
  text-align: center;
  padding: 60px 20px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.report-content {
  display: flex;
  flex-direction: column;
  gap: 30px;
}

.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 25px;
  background: #f7fafc;
  border-radius: 12px;
  border: 2px solid #e2e8f0;
}

.card-icon {
  font-size: 42px;
  width: 70px;
  height: 70px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border-radius: 12px;
}

.card-content {
  flex: 1;
}

.card-label {
  font-size: 13px;
  color: #718096;
  font-weight: 600;
  margin-bottom: 5px;
}

.card-value {
  font-size: 24px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 3px;
}

.card-value.positive {
  color: #48bb78;
}

.card-value.negative {
  color: #f56565;
}

.card-count {
  font-size: 12px;
  color: #a0aec0;
}

.daily-breakdown, .vip-breakdown, .auth-breakdown, .games-table {
  background: #f7fafc;
  padding: 25px;
  border-radius: 12px;
}

.daily-breakdown h3, .vip-breakdown h3, .auth-breakdown h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin: 0 0 20px;
}

.breakdown-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 8px;
  overflow: hidden;
}

.breakdown-table thead {
  background: #e2e8f0;
}

.breakdown-table th {
  padding: 12px 15px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
}

.breakdown-table td {
  padding: 12px 15px;
  border-top: 1px solid #e2e8f0;
  font-size: 14px;
}

.date-cell {
  color: #718096;
  font-weight: 600;
}

.amount-cell {
  font-weight: 600;
  text-align: right;
}

.amount-cell.positive {
  color: #48bb78;
}

.amount-cell.negative {
  color: #f56565;
}

.number-cell {
  font-weight: 600;
  text-align: right;
}

.percentage-cell {
  text-align: center;
  font-weight: 600;
}

.game-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.game-icon {
  font-size: 20px;
}

.vip-grid, .auth-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
}

.vip-card, .auth-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
  border: 2px solid #e2e8f0;
}

.vip-name {
  font-size: 16px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 8px;
  display: inline-block;
  margin-bottom: 10px;
}

.vip-bronze { background: #fef3c7; color: #92400e; }
.vip-silver { background: #e2e8f0; color: #2d3748; }
.vip-gold { background: #fef9c3; color: #854d0e; }
.vip-platinum { background: #e0e7ff; color: #3730a3; }
.vip-diamond { background: #ddd6fe; color: #5b21b6; }

.vip-count {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 5px;
}

.vip-percentage {
  font-size: 14px;
  color: #718096;
}

.auth-icon {
  font-size: 40px;
  margin-bottom: 10px;
}

.auth-name {
  font-size: 16px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 8px;
}

.auth-count {
  font-size: 18px;
  font-weight: 700;
  color: #667eea;
  margin-bottom: 5px;
}

.auth-percentage {
  font-size: 14px;
  color: #718096;
}
</style>
