<template>
  <div class="game-stats-page">
    <!-- Summary Cards -->
    <div class="stats-summary">
      <div class="stat-card">
        <div class="stat-icon">🎲</div>
        <div class="stat-content">
          <div class="stat-label">Total Bets</div>
          <div class="stat-value">{{ formatNumber(summary.total_bets) }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-content">
          <div class="stat-label">Total Wagered</div>
          <div class="stat-value">₱{{ formatMoney(summary.total_wagered) }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">🏆</div>
        <div class="stat-content">
          <div class="stat-label">Total Payouts</div>
          <div class="stat-value">₱{{ formatMoney(summary.total_payouts) }}</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-content">
          <div class="stat-label">House Profit</div>
          <div class="stat-value" :class="summary.house_profit >= 0 ? 'profit' : 'loss'">
            ₱{{ formatMoney(summary.house_profit) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
      <div class="filter-group">
        <select v-model="filters.game" @change="applyFilters" class="filter-select">
          <option value="">All Games</option>
          <option value="dice">🎲 Dice</option>
          <option value="hilo">🃏 Hi-Lo</option>
          <option value="mines">💣 Mines</option>
          <option value="plinko">🔴 Plinko</option>
          <option value="keno">🎱 Keno</option>
          <option value="wheel">🎡 Wheel</option>
          <option value="crash">🚀 Crash</option>
        </select>

        <input 
          v-model="filters.date_from" 
          type="date"
          @change="applyFilters"
          class="filter-input"
        />

        <input 
          v-model="filters.date_to" 
          type="date"
          @change="applyFilters"
          class="filter-input"
        />

        <button @click="resetFilters" class="reset-btn">🔄 Reset</button>
        <button @click="loadStats" class="refresh-btn" :disabled="loading">
          {{ loading ? '⟳' : '🔄' }} Refresh
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading game statistics...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="loadStats" class="retry-btn">Try Again</button>
    </div>

    <!-- Game Statistics Table -->
    <div v-else class="stats-table-container">
      <div class="table-header">
        <h3>🎮 Game Performance</h3>
      </div>

      <table class="stats-table">
        <thead>
          <tr>
            <th>Game</th>
            <th>Total Bets</th>
            <th>Total Wagered</th>
            <th>Total Payouts</th>
            <th>House Profit</th>
            <th>House Edge</th>
            <th>Avg Bet Size</th>
            <th>Biggest Win</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="game in gameStats" :key="game.game_name">
            <td class="game-name-cell">
              <span class="game-icon">{{ getGameIcon(game.game_name) }}</span>
              <span class="game-name">{{ capitalizeFirst(game.game_name) }}</span>
            </td>
            
            <td class="number-cell">{{ formatNumber(game.total_bets) }}</td>
            
            <td class="amount-cell">₱{{ formatMoney(game.total_wagered) }}</td>
            
            <td class="amount-cell">₱{{ formatMoney(game.total_payouts) }}</td>
            
            <td class="profit-cell">
              <span :class="game.house_profit >= 0 ? 'profit' : 'loss'">
                ₱{{ formatMoney(game.house_profit) }}
              </span>
            </td>
            
            <td class="percentage-cell">
              <span class="house-edge" :class="getEdgeClass(game.house_edge)">
                {{ formatPercentage(game.house_edge) }}%
              </span>
            </td>
            
            <td class="amount-cell">₱{{ formatMoney(game.avg_bet_size) }}</td>
            
            <td class="amount-cell highlight">₱{{ formatMoney(game.biggest_win) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Top Players -->
    <div class="top-players-section">
      <div class="section-header">
        <h3>🏆 Top Players</h3>
        <select v-model="topPlayersFilter" @change="loadTopPlayers" class="filter-select">
          <option value="wagered">Most Wagered</option>
          <option value="won">Highest Winnings</option>
          <option value="bets">Most Bets</option>
        </select>
      </div>

      <div class="top-players-grid">
        <div v-for="(player, index) in topPlayers" :key="player.user_id" class="player-card">
          <div class="player-rank" :class="`rank-${index + 1}`">
            {{ index + 1 }}
          </div>
          <div class="player-info">
            <div class="player-name">{{ player.username }}</div>
            <div class="player-id">ID: {{ player.user_id }}</div>
          </div>
          <div class="player-stats">
            <div class="player-stat">
              <span class="stat-label">Wagered:</span>
              <span class="stat-value">₱{{ formatMoney(player.total_wagered) }}</span>
            </div>
            <div class="player-stat">
              <span class="stat-label">Won:</span>
              <span class="stat-value" :class="player.net_profit >= 0 ? 'profit' : 'loss'">
                ₱{{ formatMoney(player.net_profit) }}
              </span>
            </div>
            <div class="player-stat">
              <span class="stat-label">Bets:</span>
              <span class="stat-value">{{ formatNumber(player.total_bets) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(false);
const error = ref(null);
const gameStats = ref([]);
const topPlayers = ref([]);
const topPlayersFilter = ref('wagered');

const summary = ref({
  total_bets: 0,
  total_wagered: 0,
  total_payouts: 0,
  house_profit: 0,
});

const filters = reactive({
  game: '',
  date_from: '',
  date_to: '',
});

onMounted(() => {
  setDefaultDates();
  loadStats();
  loadTopPlayers();
});

function setDefaultDates() {
  const today = new Date();
  const weekAgo = new Date(today);
  weekAgo.setDate(weekAgo.getDate() - 7);
  
  filters.date_from = weekAgo.toISOString().split('T')[0];
  filters.date_to = today.toISOString().split('T')[0];
}

async function loadStats() {
  loading.value = true;
  error.value = null;

  try {
    const params = {
      game: filters.game || null,
      date_from: filters.date_from || null,
      date_to: filters.date_to || null,
    };

    const response = await axios.get('/api/admin/games/stats', { params });
    gameStats.value = response.data.games || [];
    summary.value = response.data.summary || summary.value;
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load game statistics';
    console.error('Error loading stats:', err);
  } finally {
    loading.value = false;
  }
}

async function loadTopPlayers() {
  try {
    const params = {
      sort_by: topPlayersFilter.value,
      limit: 10,
    };

    const response = await axios.get('/api/admin/games/top-players', { params });
    topPlayers.value = response.data || [];
  } catch (err) {
    console.error('Error loading top players:', err);
  }
}

function applyFilters() {
  loadStats();
}

function resetFilters() {
  filters.game = '';
  setDefaultDates();
  loadStats();
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

function getEdgeClass(edge) {
  if (edge < 1) return 'edge-low';
  if (edge < 3) return 'edge-medium';
  return 'edge-high';
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

function formatPercentage(value) {
  if (!value) return '0.00';
  return Number(value).toFixed(2);
}

function capitalizeFirst(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>

<style scoped>
.game-stats-page {
  max-width: 1600px;
}

.stats-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: white;
  padding: 25px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  gap: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  font-size: 42px;
  width: 70px;
  height: 70px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 13px;
  color: #718096;
  font-weight: 600;
  margin-bottom: 5px;
}

.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: #2d3748;
}

.stat-value.profit {
  color: #48bb78;
}

.stat-value.loss {
  color: #f56565;
}

.filters-section {
  background: white;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.filter-group {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-select, .filter-input {
  padding: 10px 15px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  background: white;
}

.filter-select:focus, .filter-input:focus {
  outline: none;
  border-color: #667eea;
}

.reset-btn, .refresh-btn {
  padding: 10px 20px;
  background: #f7fafc;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.reset-btn:hover, .refresh-btn:hover:not(:disabled) {
  background: #edf2f7;
  border-color: #cbd5e0;
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.loading-state, .error-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
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

.error-state {
  color: #e53e3e;
}

.retry-btn {
  margin-top: 15px;
  padding: 10px 24px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}

.stats-table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  margin-bottom: 30px;
}

.table-header {
  padding: 20px;
  border-bottom: 1px solid #e2e8f0;
}

.table-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.stats-table {
  width: 100%;
  border-collapse: collapse;
}

.stats-table thead {
  background: #f7fafc;
}

.stats-table th {
  padding: 15px 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stats-table td {
  padding: 15px 12px;
  border-top: 1px solid #e2e8f0;
  color: #2d3748;
  font-size: 14px;
}

.game-name-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.game-icon {
  font-size: 24px;
}

.game-name {
  font-weight: 600;
}

.number-cell {
  font-weight: 600;
  color: #4a5568;
}

.amount-cell {
  font-weight: 600;
}

.amount-cell.highlight {
  color: #f6ad55;
  font-size: 15px;
}

.profit-cell .profit {
  color: #48bb78;
  font-weight: 700;
}

.profit-cell .loss {
  color: #f56565;
  font-weight: 700;
}

.house-edge {
  padding: 4px 10px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 13px;
}

.edge-low { background: #c6f6d5; color: #22543d; }
.edge-medium { background: #fef5e7; color: #7c2d12; }
.edge-high { background: #fed7d7; color: #742a2a; }

.top-players-section {
  background: white;
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  padding-bottom: 15px;
  border-bottom: 2px solid #e2e8f0;
}

.section-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.top-players-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 15px;
}

.player-card {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  background: #f7fafc;
  border-radius: 8px;
  border: 2px solid #e2e8f0;
  transition: all 0.2s;
}

.player-card:hover {
  border-color: #cbd5e0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.player-rank {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  color: white;
}

.rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #7c2d12; }
.rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #2d3748; }
.rank-3 { background: linear-gradient(135deg, #cd7f32, #f4a460); color: white; }
.player-rank:not(.rank-1):not(.rank-2):not(.rank-3) { background: #cbd5e0; color: #2d3748; }

.player-info {
  flex: 1;
}

.player-name {
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 3px;
}

.player-id {
  font-size: 12px;
  color: #718096;
}

.player-stats {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.player-stat {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 12px;
}

.player-stat .stat-label {
  color: #718096;
}

.player-stat .stat-value {
  font-weight: 600;
  color: #2d3748;
}

.player-stat .stat-value.profit {
  color: #48bb78;
}

.player-stat .stat-value.loss {
  color: #f56565;
}
</style>
