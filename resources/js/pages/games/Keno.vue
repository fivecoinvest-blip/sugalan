<template>
  <div class="keno-game">
    <!-- Game Area -->
    <div class="game-container">
      <!-- Number Grid -->
      <div class="keno-grid-section">
        <div class="grid-header">
          <div class="selection-info">
            <span class="selected-count">{{ selectedNumbers.length }}</span>
            <span class="selection-label">/ 10 numbers selected</span>
          </div>
          <button 
            @click="clearSelection" 
            class="btn-clear"
            :disabled="selectedNumbers.length === 0 || isPlaying"
          >
            Clear
          </button>
          <button 
            @click="quickPick" 
            class="btn-quick-pick"
            :disabled="selectedNumbers.length >= 10 || isPlaying"
          >
            🎲 Quick Pick
          </button>
        </div>

        <div class="keno-grid">
          <button
            v-for="num in 40"
            :key="num"
            @click="toggleNumber(num)"
            class="keno-number"
            :class="{
              selected: selectedNumbers.includes(num),
              hit: drawnNumbers.includes(num) && selectedNumbers.includes(num),
              drawn: drawnNumbers.includes(num) && !selectedNumbers.includes(num),
              disabled: isPlaying || (selectedNumbers.length >= 10 && !selectedNumbers.includes(num))
            }"
            :disabled="isPlaying || (selectedNumbers.length >= 10 && !selectedNumbers.includes(num))"
          >
            {{ num }}
          </button>
        </div>
      </div>

      <!-- Draw Animation -->
      <div v-if="isDrawing" class="draw-animation">
        <div class="animation-content">
          <div class="drawing-ball">🔵</div>
          <div class="drawing-text">Drawing Numbers...</div>
          <div class="drawn-count">{{ drawnNumbers.length }} / 20</div>
        </div>
      </div>

      <!-- Result Display -->
      <div v-if="lastResult && !isDrawing" class="result-display" :class="{ win: lastResult.payout > betAmount }">
        <div class="result-header">
          <div class="result-icon">{{ lastResult.payout > betAmount ? '🎉' : '😔' }}</div>
          <div class="result-title">
            {{ lastResult.payout > betAmount ? 'Winner!' : 'Try Again!' }}
          </div>
        </div>
        <div class="result-stats">
          <div class="result-stat">
            <div class="stat-label">Hits</div>
            <div class="stat-value">{{ lastResult.hits }} / {{ selectedNumbers.length }}</div>
          </div>
          <div class="result-stat">
            <div class="stat-label">Multiplier</div>
            <div class="stat-value">{{ lastResult.multiplier }}×</div>
          </div>
          <div class="result-stat profit">
            <div class="stat-label">Payout</div>
            <div class="stat-value">₱{{ formatMoney(lastResult.payout) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Controls Sidebar -->
    <div class="controls-sidebar">
      <!-- Bet Amount -->
      <div class="control-group">
        <label class="control-label">Bet Amount</label>
        <div class="amount-input-group">
          <button @click="divideBetBy2" class="btn-adjust" :disabled="isPlaying">½</button>
          <input 
            v-model.number="betAmount" 
            type="number" 
            min="1" 
            step="1"
            class="amount-input"
            :disabled="isPlaying"
          >
          <button @click="multiplyBetBy2" class="btn-adjust" :disabled="isPlaying">2×</button>
        </div>
        <div class="quick-amounts">
          <button 
            v-for="amount in [10, 50, 100, 500]" 
            :key="amount"
            @click="betAmount = amount"
            class="btn-quick-amount"
            :disabled="isPlaying"
          >
            ₱{{ amount }}
          </button>
        </div>
      </div>

      <!-- Play Button -->
      <button 
        @click="play" 
        class="btn-play"
        :disabled="!canPlay"
      >
        <span v-if="isPlaying" class="spinner"></span>
        <span v-else>🎱 Play ({{ selectedNumbers.length }} numbers)</span>
      </button>

      <div v-if="!canPlay && !isPlaying" class="error-msg">
        {{ selectedNumbers.length === 0 ? 'Select at least 1 number' : 'Insufficient balance' }}
      </div>

      <!-- Payout Table -->
      <div class="payout-table-section">
        <h3>Payout Table ({{ selectedNumbers.length || 1 }} spots)</h3>
        <div class="payout-table">
          <div 
            v-for="(payout, hits) in currentPayoutTable" 
            :key="hits"
            class="payout-row"
            :class="{ active: lastResult && lastResult.hits === parseInt(hits) }"
          >
            <span class="hits-label">{{ hits }} hit{{ hits > 1 ? 's' : '' }}</span>
            <span class="payout-value">{{ payout }}×</span>
          </div>
        </div>
      </div>

      <!-- How to Play -->
      <div class="how-to-play">
        <button @click="showHowToPlay = !showHowToPlay" class="btn-section-toggle">
          <span>📖 How to Play</span>
          <span>{{ showHowToPlay ? '▼' : '▶' }}</span>
        </button>
        
        <div v-if="showHowToPlay" class="section-content">
          <ol class="how-to-list">
            <li>Select 1-10 numbers from the grid (1-40)</li>
            <li>Choose your bet amount</li>
            <li>Click Play to draw 20 random numbers</li>
            <li>Win based on how many of your numbers match</li>
            <li>More numbers selected = higher payouts for more hits</li>
          </ol>
        </div>
      </div>

      <!-- Provably Fair -->
      <div class="provably-fair-section">
        <button @click="showProvablyFair = !showProvablyFair" class="btn-provably-fair">
          <span>🔐 Provably Fair</span>
          <span>{{ showProvablyFair ? '▼' : '▶' }}</span>
        </button>
        
        <div v-if="showProvablyFair" class="provably-fair-content">
          <div class="seed-info">
            <div class="seed-item">
              <label>Client Seed</label>
              <div class="seed-input-group">
                <input v-model="clientSeed" type="text" class="seed-input" readonly>
                <button @click="regenerateClientSeed" class="btn-regenerate" :disabled="isPlaying">🔄</button>
              </div>
            </div>
            <div class="seed-item">
              <label>Server Seed (Hashed)</label>
              <input :value="serverSeedHash" type="text" class="seed-input" readonly>
            </div>
            <div class="seed-item">
              <label>Nonce</label>
              <input :value="nonce" type="text" class="seed-input" readonly>
            </div>
          </div>
          <p class="provably-fair-note">
            Draw results are determined using cryptographic hashing before the draw begins.
          </p>
        </div>
      </div>
    </div>

    <!-- Recent Games -->
    <div class="recent-games">
      <h3>Recent Games</h3>
      <div class="games-list">
        <div v-for="game in recentGames" :key="game.id" class="game-item" :class="{ win: game.won, loss: !game.won }">
          <div class="game-icon">{{ game.won ? '✅' : '❌' }}</div>
          <div class="game-details">
            <div class="game-hits">{{ game.hits }} / {{ game.spots }} hits</div>
            <div class="game-multiplier">{{ game.multiplier }}×</div>
          </div>
          <div class="game-payout" :class="{ profit: game.won, loss: !game.won }">
            {{ game.won ? '+' : '' }}₱{{ formatMoney(game.payout) }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useWalletStore } from '../../stores/wallet';

const walletStore = useWalletStore();

// Game State
const betAmount = ref(10);
const selectedNumbers = ref([]);
const drawnNumbers = ref([]);
const isPlaying = ref(false);
const isDrawing = ref(false);
const lastResult = ref(null);
const recentGames = ref([]);

// UI State
const showHowToPlay = ref(false);
const showProvablyFair = ref(false);

// Provably Fair
const clientSeed = ref('');
const serverSeedHash = ref('');
const nonce = ref(0);

// Payout Tables (hits -> multiplier)
const payoutTables = {
  1: { 1: 3.5 },
  2: { 1: 0.5, 2: 8 },
  3: { 2: 2, 3: 20 },
  4: { 2: 0.5, 3: 5, 4: 50 },
  5: { 3: 2, 4: 10, 5: 100 },
  6: { 3: 0.5, 4: 4, 5: 25, 6: 250 },
  7: { 4: 2, 5: 10, 6: 50, 7: 500 },
  8: { 5: 5, 6: 20, 7: 100, 8: 1000 },
  9: { 5: 2, 6: 10, 7: 40, 8: 200, 9: 2000 },
  10: { 5: 1, 6: 5, 7: 20, 8: 100, 9: 500, 10: 5000 }
};

// Computed
const currentPayoutTable = computed(() => {
  const spots = selectedNumbers.value.length || 1;
  return payoutTables[spots] || payoutTables[1];
});

const canPlay = computed(() => {
  return selectedNumbers.value.length > 0 && 
         walletStore.balance.real_balance >= betAmount.value && 
         !isPlaying.value;
});

// Methods
function formatMoney(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function divideBetBy2() {
  betAmount.value = Math.max(1, Math.floor(betAmount.value / 2));
}

function multiplyBetBy2() {
  betAmount.value = Math.min(walletStore.balance.real_balance, betAmount.value * 2);
}

function generateClientSeed() {
  return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
}

function regenerateClientSeed() {
  clientSeed.value = generateClientSeed();
}

function toggleNumber(num) {
  const index = selectedNumbers.value.indexOf(num);
  if (index > -1) {
    selectedNumbers.value.splice(index, 1);
  } else if (selectedNumbers.value.length < 10) {
    selectedNumbers.value.push(num);
  }
}

function clearSelection() {
  selectedNumbers.value = [];
  drawnNumbers.value = [];
  lastResult.value = null;
}

function quickPick() {
  const remaining = 10 - selectedNumbers.value.length;
  const available = [];
  
  for (let i = 1; i <= 40; i++) {
    if (!selectedNumbers.value.includes(i)) {
      available.push(i);
    }
  }
  
  for (let i = 0; i < remaining && available.length > 0; i++) {
    const randomIndex = Math.floor(Math.random() * available.length);
    selectedNumbers.value.push(available[randomIndex]);
    available.splice(randomIndex, 1);
  }
}

async function play() {
  if (!canPlay.value) return;

  isPlaying.value = true;
  isDrawing.value = true;
  drawnNumbers.value = [];
  lastResult.value = null;

  try {
    const response = await axios.post('/api/games/keno/play', {
      bet_amount: betAmount.value,
      selected_numbers: selectedNumbers.value,
      client_seed: clientSeed.value,
    });

    const result = response.data;
    
    // Animate drawing numbers
    await animateDraw(result.drawn_numbers);
    
    // Show result
    lastResult.value = {
      hits: result.hits,
      multiplier: result.multiplier,
      payout: result.payout,
    };

    // Add to recent games
    recentGames.value.unshift({
      id: Date.now(),
      won: result.payout > betAmount.value,
      hits: result.hits,
      spots: selectedNumbers.value.length,
      multiplier: result.multiplier,
      payout: result.payout - betAmount.value,
    });

    if (recentGames.value.length > 10) {
      recentGames.value.pop();
    }

    nonce.value = result.nonce;
    walletStore.fetchBalance();

  } catch (error) {
    alert(error.response?.data?.message || 'Failed to play game');
  } finally {
    isPlaying.value = false;
    isDrawing.value = false;
  }
}

async function animateDraw(numbers) {
  return new Promise((resolve) => {
    let index = 0;
    const interval = setInterval(() => {
      if (index < numbers.length) {
        drawnNumbers.value.push(numbers[index]);
        index++;
      } else {
        clearInterval(interval);
        resolve();
      }
    }, 100);
  });
}

async function fetchSeedInfo() {
  try {
    const response = await axios.get('/api/games/seed');
    serverSeedHash.value = response.data.server_seed_hash;
    nonce.value = response.data.nonce;
  } catch (error) {
    console.error('Failed to fetch seed info:', error);
  }
}

onMounted(() => {
  clientSeed.value = generateClientSeed();
  fetchSeedInfo();
  walletStore.fetchBalance();
});
</script>

<style scoped>
.keno-game {
  display: grid;
  grid-template-columns: 1fr 400px;
  grid-template-rows: 1fr auto;
  gap: 20px;
  height: calc(100vh - 80px);
  padding: 20px;
}

.game-container {
  grid-column: 1;
  grid-row: 1 / 3;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 30px;
  display: flex;
  flex-direction: column;
  gap: 30px;
  position: relative;
}

.keno-grid-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.grid-header {
  display: flex;
  align-items: center;
  gap: 16px;
}

.selection-info {
  flex: 1;
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.selected-count {
  font-size: 36px;
  font-weight: 900;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.selection-label {
  font-size: 16px;
  color: rgba(255, 255, 255, 0.7);
}

.btn-clear,
.btn-quick-pick {
  padding: 12px 24px;
  border-radius: 10px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 15px;
}

.btn-clear {
  background: rgba(252, 129, 129, 0.2);
  border: 1px solid rgba(252, 129, 129, 0.3);
  color: #fc8181;
}

.btn-clear:hover:not(:disabled) {
  background: rgba(252, 129, 129, 0.3);
  transform: translateY(-2px);
}

.btn-quick-pick {
  background: rgba(102, 126, 234, 0.2);
  border: 1px solid rgba(102, 126, 234, 0.3);
  color: #667eea;
}

.btn-quick-pick:hover:not(:disabled) {
  background: rgba(102, 126, 234, 0.3);
  transform: translateY(-2px);
}

.btn-clear:disabled,
.btn-quick-pick:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.keno-grid {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 10px;
}

.keno-number {
  aspect-ratio: 1;
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  color: white;
  font-size: 24px;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.keno-number:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.1);
  transform: scale(1.05);
}

.keno-number.selected {
  background: rgba(102, 126, 234, 0.3);
  border-color: rgba(102, 126, 234, 0.8);
  color: #667eea;
}

.keno-number.hit {
  background: rgba(72, 187, 120, 0.3);
  border-color: rgba(72, 187, 120, 0.8);
  color: #48bb78;
  animation: hitPulse 0.5s ease-out;
}

@keyframes hitPulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); }
}

.keno-number.drawn {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.3);
  opacity: 0.6;
}

.keno-number.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.draw-animation {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.8);
  backdrop-filter: blur(10px);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 16px;
  z-index: 10;
}

.animation-content {
  text-align: center;
}

.drawing-ball {
  font-size: 80px;
  animation: bounce 0.6s infinite;
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-30px); }
}

.drawing-text {
  font-size: 28px;
  font-weight: 800;
  margin-top: 20px;
  margin-bottom: 12px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.drawn-count {
  font-size: 18px;
  color: rgba(255, 255, 255, 0.7);
}

.result-display {
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 30px;
}

.result-display.win {
  border-color: rgba(72, 187, 120, 0.5);
  background: rgba(72, 187, 120, 0.1);
}

.result-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 24px;
}

.result-icon {
  font-size: 48px;
}

.result-title {
  font-size: 28px;
  font-weight: 800;
}

.result-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

.result-stat {
  text-align: center;
  padding: 16px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
}

.result-stat.profit {
  background: rgba(72, 187, 120, 0.15);
}

.stat-label {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 8px;
  text-transform: uppercase;
}

.stat-value {
  font-size: 24px;
  font-weight: 800;
}

.result-stat.profit .stat-value {
  color: #48bb78;
}

.controls-sidebar {
  grid-column: 2;
  grid-row: 1;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.control-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.control-label {
  font-size: 14px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.9);
}

.amount-input-group {
  display: grid;
  grid-template-columns: 50px 1fr 50px;
  gap: 8px;
}

.btn-adjust {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-adjust:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.1);
}

.btn-adjust:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.amount-input {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 12px;
  color: white;
  font-size: 16px;
  font-weight: 600;
  text-align: center;
}

.quick-amounts {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.btn-quick-amount {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 8px;
  color: white;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-quick-amount:hover:not(:disabled) {
  background: rgba(102, 126, 234, 0.2);
  border-color: rgba(102, 126, 234, 0.3);
}

.btn-quick-amount:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-play {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 12px;
  padding: 16px;
  color: white;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-play:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-play:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-msg {
  text-align: center;
  color: #fc8181;
  font-size: 13px;
}

.payout-table-section {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 20px;
}

.payout-table-section h3 {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
}

.payout-table {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 250px;
  overflow-y: auto;
}

.payout-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  font-size: 14px;
}

.payout-row.active {
  background: rgba(102, 126, 234, 0.2);
  border: 1px solid rgba(102, 126, 234, 0.5);
}

.payout-value {
  font-weight: 700;
  color: #667eea;
}

.how-to-play,
.provably-fair-section {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 20px;
}

.btn-section-toggle,
.btn-provably-fair {
  width: 100%;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 12px;
  color: white;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-section-toggle:hover,
.btn-provably-fair:hover {
  background: rgba(255, 255, 255, 0.08);
}

.section-content,
.provably-fair-content {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.how-to-list {
  padding-left: 20px;
  font-size: 14px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.8);
}

.how-to-list li {
  margin-bottom: 8px;
}

.seed-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.seed-item label {
  display: block;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 4px;
}

.seed-input {
  width: 100%;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  padding: 8px 12px;
  color: white;
  font-size: 12px;
  font-family: monospace;
}

.seed-input-group {
  display: flex;
  gap: 8px;
}

.btn-regenerate {
  background: rgba(102, 126, 234, 0.2);
  border: 1px solid rgba(102, 126, 234, 0.3);
  border-radius: 6px;
  padding: 8px 12px;
  color: white;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-regenerate:hover:not(:disabled) {
  background: rgba(102, 126, 234, 0.3);
}

.btn-regenerate:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.provably-fair-note {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.4;
}

.recent-games {
  grid-column: 2;
  grid-row: 2;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  max-height: 300px;
  overflow-y: auto;
}

.recent-games h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
}

.games-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.game-item {
  display: grid;
  grid-template-columns: 40px 1fr auto;
  gap: 12px;
  align-items: center;
  padding: 12px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.game-item.win {
  border-color: rgba(72, 187, 120, 0.3);
  background: rgba(72, 187, 120, 0.1);
}

.game-item.loss {
  border-color: rgba(252, 129, 129, 0.3);
  background: rgba(252, 129, 129, 0.1);
}

.game-icon {
  font-size: 24px;
}

.game-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.game-hits {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.9);
}

.game-multiplier {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.game-payout {
  font-size: 16px;
  font-weight: 700;
}

.game-payout.profit {
  color: #48bb78;
}

.game-payout.loss {
  color: #fc8181;
}

@media (max-width: 1200px) {
  .keno-game {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
    height: auto;
  }

  .game-container {
    grid-column: 1;
    grid-row: 1;
  }

  .controls-sidebar {
    grid-column: 1;
    grid-row: 2;
  }

  .recent-games {
    grid-column: 1;
    grid-row: 3;
  }

  .keno-grid {
    grid-template-columns: repeat(5, 1fr);
  }

  .result-stats {
    grid-template-columns: 1fr;
  }
}
</style>
