<template>
  <div class="game-layout">
    <header class="game-header">
      <div class="header-left">
        <router-link to="/" class="back-btn">← Back</router-link>
        <div class="game-title">
          <span class="game-icon">{{ gameIcon }}</span>
          <h1>{{ gameTitle }}</h1>
        </div>
      </div>

      <div class="header-right">
        <div class="balance-display">
          <span class="balance-label">Balance:</span>
          <span class="balance-value">₱{{ formatMoney(walletBalance.real_balance) }}</span>
        </div>
        <router-link to="/dashboard" class="icon-btn" title="Dashboard">
          📊
        </router-link>
      </div>
    </header>

    <div class="game-container">
      <div class="game-main">
        <router-view />
      </div>

      <aside class="game-sidebar">
        <!-- Bet Panel -->
        <div class="sidebar-section bet-panel">
          <h3>Place Bet</h3>
          <slot name="bet-controls"></slot>
        </div>

        <!-- Provably Fair -->
        <div class="sidebar-section provably-fair">
          <div class="section-header">
            <h3>Provably Fair</h3>
            <button @click="toggleProvablyFair" class="toggle-btn">
              {{ showProvablyFair ? '−' : '+' }}
            </button>
          </div>
          
          <div v-if="showProvablyFair" class="provably-fair-content">
            <div class="seed-info">
              <div class="seed-item">
                <label>Server Seed (Hash)</label>
                <input 
                  :value="serverSeedHash" 
                  readonly 
                  class="seed-input"
                  @click="copyToClipboard(serverSeedHash)"
                />
              </div>
              <div class="seed-item">
                <label>Client Seed</label>
                <input 
                  v-model="clientSeed" 
                  class="seed-input"
                  placeholder="Enter custom seed"
                />
              </div>
              <div class="seed-item">
                <label>Nonce</label>
                <input 
                  :value="nonce" 
                  readonly 
                  class="seed-input"
                />
              </div>
            </div>
            <button @click="rotateSeed" class="btn btn-secondary btn-sm">
              🔄 Rotate Seed
            </button>
          </div>
        </div>

        <!-- Recent Bets -->
        <div class="sidebar-section recent-bets">
          <h3>Recent Bets</h3>
          <div class="bets-list">
            <div v-if="recentBets.length === 0" class="empty-state">
              No recent bets
            </div>
            <div v-else v-for="bet in recentBets" :key="bet.id" class="bet-item">
              <div class="bet-header">
                <span class="bet-amount">₱{{ formatMoney(bet.amount) }}</span>
                <span :class="['bet-result', bet.payout > 0 ? 'win' : 'loss']">
                  {{ bet.payout > 0 ? '+' : '' }}₱{{ formatMoney(bet.payout - bet.amount) }}
                </span>
              </div>
              <div class="bet-details">
                <span class="bet-multiplier">{{ bet.multiplier }}x</span>
                <span class="bet-time">{{ formatTime(bet.created_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useWalletStore } from '../stores/wallet';

const route = useRoute();
const walletStore = useWalletStore();

const showProvablyFair = ref(false);
const serverSeedHash = ref('a1b2c3d4e5f6...');
const clientSeed = ref('');
const nonce = ref(0);
const recentBets = ref([]);

const walletBalance = computed(() => walletStore.balance);

const gameIcon = computed(() => {
  const icons = {
    dice: '🎲',
    hilo: '🃏',
    mines: '💣',
    plinko: '🏀',
    keno: '🎱',
    wheel: '🎡',
    crash: '🚀',
  };
  return icons[route.params.game] || '🎮';
});

const gameTitle = computed(() => {
  const titles = {
    dice: 'Dice',
    hilo: 'Hi-Lo',
    mines: 'Mines',
    plinko: 'Plinko',
    keno: 'Keno',
    wheel: 'Wheel',
    crash: 'Crash',
  };
  return titles[route.params.game] || 'Game';
});

function formatMoney(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
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

function toggleProvablyFair() {
  showProvablyFair.value = !showProvablyFair.value;
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text);
}

function rotateSeed() {
  // TODO: Call API to rotate seed
  console.log('Rotating seed...');
}

onMounted(() => {
  walletStore.fetchBalance();
  // TODO: Fetch recent bets for this game
});
</script>

<style scoped>
.game-layout {
  min-height: 100vh;
  background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 100%);
  display: flex;
  flex-direction: column;
}

.game-header {
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding: 15px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.back-btn {
  background: rgba(255, 255, 255, 0.1);
  padding: 8px 16px;
  border-radius: 8px;
  color: white;
  text-decoration: none;
  transition: all 0.2s;
}

.back-btn:hover {
  background: rgba(255, 255, 255, 0.15);
}

.game-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.game-icon {
  font-size: 32px;
}

.game-title h1 {
  font-size: 24px;
  margin: 0;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 15px;
}

.balance-display {
  background: rgba(255, 255, 255, 0.1);
  padding: 8px 16px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.balance-label {
  color: rgba(255, 255, 255, 0.6);
  font-size: 14px;
}

.balance-value {
  color: #48bb78;
  font-weight: 700;
  font-size: 16px;
}

.icon-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  transition: all 0.2s;
  text-decoration: none;
}

.icon-btn:hover {
  background: rgba(255, 255, 255, 0.15);
  transform: scale(1.05);
}

.game-container {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 350px;
  gap: 20px;
  padding: 20px;
}

.game-main {
  background: rgba(255, 255, 255, 0.03);
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 20px;
}

.game-sidebar {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.sidebar-section {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  padding: 20px;
}

.sidebar-section h3 {
  font-size: 16px;
  margin: 0 0 15px 0;
  color: rgba(255, 255, 255, 0.9);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.toggle-btn {
  width: 24px;
  height: 24px;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  cursor: pointer;
  font-size: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.provably-fair-content {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.seed-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.seed-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.seed-item label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.seed-input {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  padding: 8px;
  color: white;
  font-size: 12px;
  font-family: monospace;
}

.seed-input:focus {
  outline: none;
  border-color: #667eea;
}

.seed-input[readonly] {
  cursor: pointer;
}

.btn {
  padding: 8px 16px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.15);
}

.btn-sm {
  font-size: 13px;
  padding: 6px 12px;
}

.bets-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 300px;
  overflow-y: auto;
}

.empty-state {
  text-align: center;
  color: rgba(255, 255, 255, 0.4);
  padding: 20px;
}

.bet-item {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  padding: 12px;
}

.bet-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
}

.bet-amount {
  font-weight: 600;
  color: white;
}

.bet-result {
  font-weight: 700;
}

.bet-result.win {
  color: #48bb78;
}

.bet-result.loss {
  color: #fc8181;
}

.bet-details {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

@media (max-width: 1024px) {
  .game-container {
    grid-template-columns: 1fr;
  }

  .game-sidebar {
    order: -1;
  }
}
</style>
