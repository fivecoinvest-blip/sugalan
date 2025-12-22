<template>
  <div class="bet-history-page">
    <div class="container">
      <h1 class="page-title">Bet History</h1>

      <!-- Filters -->
      <div class="filters-section">
        <div class="filter-group">
          <label>Game</label>
          <select v-model="filters.game" class="filter-select">
            <option value="all">All Games</option>
            <option value="dice">Dice</option>
            <option value="crash">Crash</option>
            <option value="mines">Mines</option>
            <option value="plinko">Plinko</option>
            <option value="hilo">Hi-Lo</option>
            <option value="keno">Keno</option>
            <option value="wheel">Wheel</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Result</label>
          <select v-model="filters.result" class="filter-select">
            <option value="all">All Results</option>
            <option value="win">Wins Only</option>
            <option value="loss">Losses Only</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Date Range</label>
          <select v-model="filters.dateRange" class="filter-select">
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="all">All Time</option>
          </select>
        </div>

        <button @click="applyFilters" class="btn btn-primary">
          Apply Filters
        </button>
      </div>

      <!-- Stats Summary -->
      <div class="stats-summary">
        <div class="stat-card">
          <div class="stat-icon">🎲</div>
          <div class="stat-content">
            <span class="stat-label">Total Bets</span>
            <span class="stat-value">{{ stats.total_bets }}</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">💰</div>
          <div class="stat-content">
            <span class="stat-label">Total Wagered</span>
            <span class="stat-value">₱{{ formatMoney(stats.total_wagered) }}</span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🏆</div>
          <div class="stat-content">
            <span class="stat-label">Total Won</span>
            <span class="stat-value" :class="stats.profit >= 0 ? 'positive' : 'negative'">
              ₱{{ formatMoney(stats.total_won) }}
            </span>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📊</div>
          <div class="stat-content">
            <span class="stat-label">Profit/Loss</span>
            <span class="stat-value" :class="stats.profit >= 0 ? 'positive' : 'negative'">
              {{ stats.profit >= 0 ? '+' : '' }}₱{{ formatMoney(stats.profit) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Bets List -->
      <div class="bets-section">
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading bets...</p>
        </div>

        <div v-else-if="bets.length === 0" class="empty-state">
          <div class="empty-icon">🎲</div>
          <p>No bets found</p>
          <router-link to="/games" class="btn btn-primary">Start Playing</router-link>
        </div>

        <div v-else class="bets-table">
          <div class="table-header">
            <div class="col-game">Game</div>
            <div class="col-bet">Bet Amount</div>
            <div class="col-multiplier">Multiplier</div>
            <div class="col-payout">Payout</div>
            <div class="col-profit">Profit</div>
            <div class="col-date">Date</div>
            <div class="col-action">Action</div>
          </div>

          <div v-for="bet in bets" :key="bet.id" class="table-row">
            <div class="col-game">
              <div class="game-info">
                <span class="game-icon">{{ getGameIcon(bet.game_type) }}</span>
                <span class="game-name">{{ getGameName(bet.game_type) }}</span>
              </div>
            </div>
            <div class="col-bet">
              ₱{{ formatMoney(bet.amount) }}
            </div>
            <div class="col-multiplier">
              <span class="multiplier-badge">{{ bet.multiplier }}x</span>
            </div>
            <div class="col-payout">
              ₱{{ formatMoney(bet.payout) }}
            </div>
            <div class="col-profit">
              <span :class="['profit-value', bet.payout > bet.amount ? 'win' : 'loss']">
                {{ bet.payout > bet.amount ? '+' : '' }}₱{{ formatMoney(bet.payout - bet.amount) }}
              </span>
            </div>
            <div class="col-date">
              {{ formatDateTime(bet.created_at) }}
            </div>
            <div class="col-action">
              <button @click="viewBetDetails(bet)" class="btn-icon">
                👁️
              </button>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="pagination">
          <button 
            @click="currentPage--" 
            :disabled="currentPage === 1"
            class="page-btn"
          >
            ← Previous
          </button>
          <span class="page-info">Page {{ currentPage }} of {{ totalPages }}</span>
          <button 
            @click="currentPage++" 
            :disabled="currentPage === totalPages"
            class="page-btn"
          >
            Next →
          </button>
        </div>
      </div>
    </div>

    <!-- Bet Details Modal -->
    <div v-if="selectedBet" class="modal-overlay" @click="selectedBet = null">
      <div class="modal-content" @click.stop>
        <button @click="selectedBet = null" class="close-btn">✕</button>
        <h2>Bet Details</h2>
        
        <div class="bet-details">
          <div class="detail-row">
            <span class="detail-label">Game</span>
            <span class="detail-value">{{ getGameName(selectedBet.game_type) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Bet ID</span>
            <span class="detail-value monospace">{{ selectedBet.id }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Bet Amount</span>
            <span class="detail-value">₱{{ formatMoney(selectedBet.amount) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Multiplier</span>
            <span class="detail-value">{{ selectedBet.multiplier }}x</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Payout</span>
            <span class="detail-value">₱{{ formatMoney(selectedBet.payout) }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Profit/Loss</span>
            <span :class="['detail-value', selectedBet.payout > selectedBet.amount ? 'win' : 'loss']">
              {{ selectedBet.payout > selectedBet.amount ? '+' : '' }}₱{{ formatMoney(selectedBet.payout - selectedBet.amount) }}
            </span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Date</span>
            <span class="detail-value">{{ formatDateTime(selectedBet.created_at) }}</span>
          </div>
          <div v-if="selectedBet.game_data" class="detail-row">
            <span class="detail-label">Game Data</span>
            <pre class="game-data">{{ JSON.stringify(selectedBet.game_data, null, 2) }}</pre>
          </div>
        </div>

        <div class="provably-fair">
          <h3>🔐 Provably Fair Verification</h3>
          <div class="seed-info">
            <div class="seed-item">
              <span class="seed-label">Server Seed</span>
              <span class="seed-value monospace">{{ selectedBet.server_seed || 'N/A' }}</span>
            </div>
            <div class="seed-item">
              <span class="seed-label">Client Seed</span>
              <span class="seed-value monospace">{{ selectedBet.client_seed || 'N/A' }}</span>
            </div>
            <div class="seed-item">
              <span class="seed-label">Nonce</span>
              <span class="seed-value">{{ selectedBet.nonce || 'N/A' }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(false);
const currentPage = ref(1);
const perPage = 20;
const bets = ref([]);
const selectedBet = ref(null);

const filters = ref({
  game: 'all',
  result: 'all',
  dateRange: 'all',
});

const stats = ref({
  total_bets: 0,
  total_wagered: 0,
  total_won: 0,
  profit: 0,
});

const totalPages = computed(() => {
  return Math.ceil(stats.value.total_bets / perPage);
});

const gameIcons = {
  dice: '🎲',
  hilo: '🃏',
  mines: '💣',
  plinko: '🏀',
  keno: '🎱',
  wheel: '🎡',
  crash: '🚀',
};

const gameNames = {
  dice: 'Dice',
  hilo: 'Hi-Lo',
  mines: 'Mines',
  plinko: 'Plinko',
  keno: 'Keno',
  wheel: 'Wheel',
  crash: 'Crash',
};

function getGameIcon(gameType) {
  return gameIcons[gameType] || '🎮';
}

function getGameName(gameType) {
  return gameNames[gameType] || gameType;
}

function formatMoney(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDateTime(timestamp) {
  return new Date(timestamp).toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function viewBetDetails(bet) {
  selectedBet.value = bet;
}

async function fetchBets() {
  loading.value = true;

  try {
    const params = {
      page: currentPage.value,
      per_page: perPage,
    };

    if (filters.value.game !== 'all') {
      params.game_type = filters.value.game;
    }

    const response = await axios.get('/api/bets/history', { params });
    bets.value = response.data.data;
    
    // Fetch stats
    const statsResponse = await axios.get('/api/bets/stats', { params: filters.value });
    stats.value = statsResponse.data.data;
  } catch (error) {
    console.error('Failed to fetch bets:', error);
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  currentPage.value = 1;
  fetchBets();
}

watch(currentPage, () => {
  fetchBets();
});

onMounted(() => {
  fetchBets();
});
</script>

<style scoped>
.bet-history-page {
  min-height: 100vh;
  padding: 40px 20px;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
}

.page-title {
  font-size: 36px;
  font-weight: 800;
  margin-bottom: 30px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.filters-section {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
  min-width: 150px;
}

.filter-group label {
  font-size: 14px;
  font-weight: 600;
}

.filter-select {
  padding: 10px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  cursor: pointer;
}

.stats-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.stat-icon {
  font-size: 36px;
}

.stat-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-label {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
}

.stat-value {
  font-size: 22px;
  font-weight: 700;
}

.stat-value.positive {
  color: #48bb78;
}

.stat-value.negative {
  color: #fc8181;
}

.bets-section {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.loading-state, .empty-state {
  text-align: center;
  padding: 60px 20px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid rgba(255, 255, 255, 0.1);
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.bets-table {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.table-header, .table-row {
  display: grid;
  grid-template-columns: 150px 120px 100px 120px 120px 140px 60px;
  gap: 12px;
  align-items: center;
  padding: 12px;
}

.table-header {
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
  font-weight: 600;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
}

.table-row {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  transition: all 0.2s;
}

.table-row:hover {
  background: rgba(255, 255, 255, 0.05);
}

.game-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.game-icon {
  font-size: 20px;
}

.game-name {
  font-weight: 600;
}

.multiplier-badge {
  background: rgba(102, 126, 234, 0.2);
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 12px;
  color: #667eea;
  font-weight: 700;
}

.profit-value.win {
  color: #48bb78;
  font-weight: 700;
}

.profit-value.loss {
  color: #fc8181;
  font-weight: 700;
}

.btn-icon {
  background: rgba(255, 255, 255, 0.05);
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 16px;
}

.btn-icon:hover {
  background: rgba(255, 255, 255, 0.1);
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.page-btn {
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  cursor: pointer;
}

.page-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: linear-gradient(135deg, #1e1e32 0%, #2a2a40 100%);
  border-radius: 16px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  padding: 30px;
  position: relative;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.close-btn {
  position: absolute;
  top: 15px;
  right: 15px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  cursor: pointer;
}

.bet-details {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 24px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
}

.detail-label {
  color: rgba(255, 255, 255, 0.6);
  font-size: 14px;
}

.detail-value {
  font-weight: 600;
}

.detail-value.monospace {
  font-family: monospace;
  font-size: 12px;
}

.detail-value.win {
  color: #48bb78;
}

.detail-value.loss {
  color: #fc8181;
}

.game-data {
  background: rgba(0, 0, 0, 0.3);
  padding: 12px;
  border-radius: 8px;
  font-size: 11px;
  overflow-x: auto;
  margin: 0;
  color: #48bb78;
}

.provably-fair {
  background: rgba(102, 126, 234, 0.1);
  border: 1px solid rgba(102, 126, 234, 0.3);
  border-radius: 12px;
  padding: 16px;
}

.provably-fair h3 {
  font-size: 16px;
  margin-bottom: 12px;
}

.seed-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.seed-item {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
}

.seed-label {
  color: rgba(255, 255, 255, 0.6);
}

.seed-value.monospace {
  font-family: monospace;
  color: #667eea;
}

@media (max-width: 1024px) {
  .table-header, .table-row {
    grid-template-columns: 1fr;
    gap: 8px;
  }
  
  .table-header {
    display: none;
  }
}
</style>
