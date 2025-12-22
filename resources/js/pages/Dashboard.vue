<template>
  <div class="dashboard-page">
    <div class="container">
      <h1 class="page-title">Dashboard</h1>

      <!-- Wallet Overview -->
      <div class="wallet-section">
        <div class="wallet-card main-balance">
          <div class="card-header">
            <h2>💰 Total Balance</h2>
            <router-link to="/wallet" class="link-btn">View Details →</router-link>
          </div>
          <div class="balance-display">
            <div class="balance-item">
              <span class="label">Real Balance</span>
              <span class="value real">₱{{ formatMoney(balance.real_balance) }}</span>
            </div>
            <div class="balance-item">
              <span class="label">Bonus Balance</span>
              <span class="value bonus">₱{{ formatMoney(balance.bonus_balance) }}</span>
            </div>
            <div class="balance-item">
              <span class="label">Locked Balance</span>
              <span class="value locked">₱{{ formatMoney(balance.locked_balance) }}</span>
            </div>
          </div>
          <div class="wallet-actions">
            <router-link to="/deposit" class="btn btn-primary">
              💳 Deposit
            </router-link>
            <router-link to="/withdraw" class="btn btn-secondary">
              💸 Withdraw
            </router-link>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon">🎲</div>
            <div class="stat-content">
              <span class="stat-value">{{ stats.total_bets }}</span>
              <span class="stat-label">Total Bets</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon">🏆</div>
            <div class="stat-content">
              <span class="stat-value">{{ stats.total_wins }}</span>
              <span class="stat-label">Total Wins</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon">💎</div>
            <div class="stat-content">
              <span class="stat-value">{{ vipLevel }}</span>
              <span class="stat-label">VIP Level</span>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-content">
              <span class="stat-value">{{ stats.referral_count }}</span>
              <span class="stat-label">Referrals</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Active Bonuses -->
      <div v-if="activeBonuses.length > 0" class="section">
        <div class="section-header">
          <h2>🎁 Active Bonuses</h2>
          <router-link to="/bonuses" class="link-btn">View All →</router-link>
        </div>
        <div class="bonuses-grid">
          <div v-for="bonus in activeBonuses" :key="bonus.id" class="bonus-card">
            <div class="bonus-header">
              <span class="bonus-type">{{ bonus.bonus_type }}</span>
              <span class="bonus-amount">₱{{ formatMoney(bonus.amount) }}</span>
            </div>
            <div class="bonus-progress">
              <div class="progress-bar">
                <div class="progress-fill" :style="{ width: bonusProgress(bonus) + '%' }"></div>
              </div>
              <span class="progress-text">
                ₱{{ formatMoney(bonus.wagered) }} / ₱{{ formatMoney(bonus.wager_requirement) }}
              </span>
            </div>
            <div class="bonus-footer">
              <span class="bonus-expires">Expires {{ formatDate(bonus.expires_at) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="section">
        <div class="section-header">
          <h2>📊 Recent Activity</h2>
          <router-link to="/bet-history" class="link-btn">View All →</router-link>
        </div>
        <div class="activity-list">
          <div v-if="recentBets.length === 0" class="empty-state">
            <div class="empty-icon">🎲</div>
            <p>No recent activity</p>
            <router-link to="/games" class="btn btn-primary">Start Playing</router-link>
          </div>
          <div v-else v-for="bet in recentBets" :key="bet.id" class="activity-item">
            <div class="activity-icon">{{ getGameIcon(bet.game_type) }}</div>
            <div class="activity-content">
              <div class="activity-main">
                <span class="activity-game">{{ getGameName(bet.game_type) }}</span>
                <span class="activity-amount">₱{{ formatMoney(bet.amount) }}</span>
              </div>
              <div class="activity-details">
                <span class="activity-time">{{ formatTime(bet.created_at) }}</span>
                <span class="activity-multiplier">{{ bet.multiplier }}x</span>
              </div>
            </div>
            <div class="activity-result" :class="bet.payout > bet.amount ? 'win' : 'loss'">
              <span class="result-label">{{ bet.payout > bet.amount ? 'Won' : 'Lost' }}</span>
              <span class="result-amount">
                {{ bet.payout > bet.amount ? '+' : '' }}₱{{ formatMoney(bet.payout - bet.amount) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="section">
        <h2>⚡ Quick Actions</h2>
        <div class="quick-links">
          <router-link to="/games" class="quick-link">
            <div class="quick-link-icon">🎮</div>
            <span>Play Games</span>
          </router-link>
          <router-link to="/deposit" class="quick-link">
            <div class="quick-link-icon">💳</div>
            <span>Deposit</span>
          </router-link>
          <router-link to="/bonuses" class="quick-link">
            <div class="quick-link-icon">🎁</div>
            <span>Bonuses</span>
          </router-link>
          <router-link to="/referrals" class="quick-link">
            <div class="quick-link-icon">👥</div>
            <span>Refer Friends</span>
          </router-link>
          <router-link to="/vip" class="quick-link">
            <div class="quick-link-icon">💎</div>
            <span>VIP Program</span>
          </router-link>
          <router-link to="/profile" class="quick-link">
            <div class="quick-link-icon">⚙️</div>
            <span>Settings</span>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useWalletStore } from '../stores/wallet';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const walletStore = useWalletStore();
const authStore = useAuthStore();

const balance = computed(() => walletStore.balance);
const user = computed(() => authStore.user);
const vipLevel = computed(() => user.value?.vip_level?.name || 'Bronze');

const stats = ref({
  total_bets: 0,
  total_wins: 0,
  referral_count: 0,
});

const activeBonuses = ref([]);
const recentBets = ref([]);

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

function formatMoney(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(date) {
  return new Date(date).toLocaleDateString();
}

function formatTime(timestamp) {
  const date = new Date(timestamp);
  const now = new Date();
  const diff = Math.floor((now - date) / 1000);

  if (diff < 60) return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return date.toLocaleDateString();
}

function bonusProgress(bonus) {
  return Math.min((bonus.wagered / bonus.wager_requirement) * 100, 100);
}

function getGameIcon(gameType) {
  return gameIcons[gameType] || '🎮';
}

function getGameName(gameType) {
  return gameNames[gameType] || gameType;
}

async function fetchDashboardData() {
  try {
    // Fetch wallet balance
    await walletStore.fetchBalance();

    // Fetch stats
    const statsResponse = await axios.get('/api/user/stats');
    stats.value = statsResponse.data.data;

    // Fetch active bonuses
    const bonusesResponse = await axios.get('/api/bonuses/active');
    activeBonuses.value = bonusesResponse.data.data.slice(0, 3);

    // Fetch recent bets
    const betsResponse = await axios.get('/api/bets/history', {
      params: { limit: 5 },
    });
    recentBets.value = betsResponse.data.data;
  } catch (error) {
    console.error('Failed to fetch dashboard data:', error);
  }
}

onMounted(() => {
  fetchDashboardData();
});
</script>

<style scoped>
.dashboard-page {
  min-height: 100vh;
  padding: 40px 20px;
}

.container {
  max-width: 1200px;
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

.wallet-section {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 20px;
  margin-bottom: 40px;
}

.wallet-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.card-header h2 {
  font-size: 20px;
  font-weight: 700;
}

.balance-display {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.balance-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.balance-item .label {
  color: rgba(255, 255, 255, 0.6);
  font-size: 14px;
}

.balance-item .value {
  font-size: 24px;
  font-weight: 700;
}

.value.real {
  color: #48bb78;
}

.value.bonus {
  color: #ed8936;
}

.value.locked {
  color: #718096;
}

.wallet-actions {
  display: flex;
  gap: 12px;
}

.stats-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.stat-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.stat-icon {
  font-size: 32px;
}

.stat-content {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: white;
}

.stat-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.section {
  margin-bottom: 40px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.section h2, .section-header h2 {
  font-size: 24px;
  font-weight: 700;
}

.bonuses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.bonus-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 20px;
}

.bonus-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.bonus-type {
  background: rgba(102, 126, 234, 0.2);
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  color: #667eea;
  text-transform: capitalize;
}

.bonus-amount {
  font-size: 20px;
  font-weight: 700;
  color: #ed8936;
}

.bonus-progress {
  margin-bottom: 12px;
}

.progress-bar {
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  transition: width 0.3s;
}

.progress-text {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.bonus-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.bonus-expires {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
}

.activity-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.empty-state p {
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 20px;
}

.activity-item {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.activity-icon {
  font-size: 32px;
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
}

.activity-content {
  flex: 1;
}

.activity-main {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.activity-game {
  font-weight: 600;
}

.activity-amount {
  color: rgba(255, 255, 255, 0.8);
}

.activity-details {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.activity-result {
  text-align: right;
}

.result-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.result-amount {
  font-size: 18px;
  font-weight: 700;
}

.activity-result.win {
  color: #48bb78;
}

.activity-result.loss {
  color: #fc8181;
}

.quick-links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 16px;
}

.quick-link {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
  text-decoration: none;
  color: white;
  transition: all 0.2s;
}

.quick-link:hover {
  background: rgba(255, 255, 255, 0.08);
  transform: translateY(-2px);
}

.quick-link-icon {
  font-size: 36px;
  margin-bottom: 8px;
}

.quick-link span {
  font-size: 14px;
  font-weight: 600;
}

.link-btn {
  background: none;
  border: none;
  color: #667eea;
  cursor: pointer;
  font-weight: 600;
  text-decoration: none;
  font-size: 14px;
}

.link-btn:hover {
  color: #764ba2;
}

.btn {
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  font-size: 14px;
  text-decoration: none;
  display: inline-block;
  text-align: center;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  flex: 1;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.2);
  flex: 1;
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.15);
}

@media (max-width: 768px) {
  .wallet-section {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }

  .wallet-actions {
    flex-direction: column;
  }

  .quick-links {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
