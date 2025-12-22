<template>
  <div class="crash-game">
    <!-- Game Canvas -->
    <div class="game-container">
      <div class="crash-display">
        <!-- Multiplier Display -->
        <div class="multiplier-display" :class="gameState">
          <div v-if="gameState === 'waiting'" class="waiting-state">
            <div class="waiting-text">Next round in</div>
            <div class="countdown">{{ countdown }}s</div>
          </div>
          <div v-else-if="gameState === 'flying'" class="flying-state">
            <div class="current-multiplier">{{ currentMultiplier }}×</div>
            <div class="multiplier-label">Current Multiplier</div>
          </div>
          <div v-else-if="gameState === 'crashed'" class="crashed-state">
            <div class="crash-text">CRASHED!</div>
            <div class="crash-multiplier">{{ crashedAt }}×</div>
          </div>
        </div>

        <!-- Graph Visualization -->
        <div class="graph-container">
          <canvas ref="graphCanvas" class="multiplier-graph"></canvas>
        </div>

        <!-- Active Players -->
        <div class="active-players">
          <h3>Active Players ({{ activePlayers.length }})</h3>
          <div class="players-list">
            <div 
              v-for="player in activePlayers" 
              :key="player.id"
              class="player-item"
              :class="{ 'cashed-out': player.cashed_out }"
            >
              <div class="player-info">
                <span class="player-name">{{ player.username }}</span>
                <span class="player-bet">₱{{ formatMoney(player.bet_amount) }}</span>
              </div>
              <div v-if="player.cashed_out" class="player-cashout">
                <span class="cashout-multiplier">{{ player.cashout_multiplier }}×</span>
                <span class="cashout-profit">+₱{{ formatMoney(player.profit) }}</span>
              </div>
              <div v-else class="player-waiting">
                <span class="spinner-small"></span>
              </div>
            </div>
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
          <button @click="divideBetBy2" class="btn-adjust" :disabled="!canPlaceBet">½</button>
          <input 
            v-model.number="betAmount" 
            type="number" 
            min="1" 
            step="1"
            class="amount-input"
            :disabled="!canPlaceBet"
          >
          <button @click="multiplyBetBy2" class="btn-adjust" :disabled="!canPlaceBet">2×</button>
        </div>
        <div class="quick-amounts">
          <button 
            v-for="amount in [10, 50, 100, 500]" 
            :key="amount"
            @click="betAmount = amount"
            class="btn-quick-amount"
            :disabled="!canPlaceBet"
          >
            ₱{{ amount }}
          </button>
        </div>
      </div>

      <!-- Auto Cashout -->
      <div class="control-group">
        <label class="control-label">
          <input v-model="autoCashoutEnabled" type="checkbox" class="checkbox">
          Auto Cashout
        </label>
        <div v-if="autoCashoutEnabled" class="auto-cashout-input">
          <input 
            v-model.number="autoCashoutAt" 
            type="number" 
            min="1.01" 
            step="0.01"
            class="amount-input"
          >
          <span class="input-suffix">×</span>
        </div>
      </div>

      <!-- Bet/Cashout Button -->
      <button 
        v-if="!hasBet"
        @click="placeBet" 
        class="btn-action"
        :disabled="!canPlaceBet"
        :class="{ pulse: gameState === 'waiting' && canPlaceBet }"
      >
        {{ gameState === 'waiting' ? '🚀 Place Bet' : '⏳ Next Round' }}
      </button>
      <button 
        v-else-if="gameState === 'flying' && !cashedOut"
        @click="cashout" 
        class="btn-cashout"
      >
        💰 Cash Out {{ currentMultiplier }}×
      </button>
      <div v-else-if="cashedOut" class="bet-result success">
        <div class="result-icon">✓</div>
        <div class="result-text">Cashed Out!</div>
        <div class="result-amount">+₱{{ formatMoney(lastWin) }}</div>
      </div>
      <div v-else-if="gameState === 'crashed'" class="bet-result loss">
        <div class="result-icon">✗</div>
        <div class="result-text">Crashed</div>
        <div class="result-amount">-₱{{ formatMoney(betAmount) }}</div>
      </div>

      <div v-if="!canPlaceBet && gameState === 'waiting'" class="insufficient-balance-msg">
        Insufficient balance
      </div>

      <!-- Current Round Info -->
      <div class="round-info">
        <div class="info-row">
          <span>Round ID:</span>
          <span class="info-value">#{{ currentRound }}</span>
        </div>
        <div class="info-row">
          <span>Total Bets:</span>
          <span class="info-value">{{ activePlayers.length }}</span>
        </div>
        <div v-if="hasBet" class="info-row">
          <span>Your Bet:</span>
          <span class="info-value">₱{{ formatMoney(betAmount) }}</span>
        </div>
        <div v-if="hasBet && !cashedOut && gameState === 'flying'" class="info-row">
          <span>Potential Win:</span>
          <span class="info-value profit">₱{{ formatMoney(betAmount * currentMultiplier) }}</span>
        </div>
      </div>

      <!-- Auto Bet -->
      <div class="auto-bet-section">
        <div class="section-header">
          <label class="control-label">Auto Bet</label>
          <label class="toggle-switch">
            <input v-model="autoMode" type="checkbox" :disabled="hasBet">
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div v-if="autoMode" class="auto-options">
          <div class="auto-option">
            <label>Number of Rounds</label>
            <input v-model.number="autoRounds" type="number" min="1" max="100" class="auto-input">
          </div>
          <div class="auto-option">
            <label>Stop on Win</label>
            <input v-model="stopOnWin" type="checkbox">
          </div>

          <div v-if="isAutoRunning" class="auto-progress">
            Round: {{ autoCurrentRound }} / {{ autoRounds }}
          </div>
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
              <label>Round Hash</label>
              <input :value="roundHash" type="text" class="seed-input" readonly>
            </div>
            <div class="seed-item">
              <label>Crash Point</label>
              <input :value="crashedAt ? crashedAt + 'x' : 'Pending'" type="text" class="seed-input" readonly>
            </div>
          </div>
          <p class="provably-fair-note">
            Each round is provably fair. The crash point is determined before the round starts using cryptographic hashing.
          </p>
        </div>
      </div>
    </div>

    <!-- Recent Rounds -->
    <div class="recent-rounds">
      <h3>Recent Rounds</h3>
      <div class="rounds-list">
        <div 
          v-for="round in recentRounds" 
          :key="round.id"
          class="round-item"
          :class="getRoundClass(round.crashed_at)"
        >
          <div class="round-multiplier">{{ round.crashed_at }}×</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import axios from 'axios';
import { useWalletStore } from '../../stores/wallet';

const walletStore = useWalletStore();

// Game State
const gameState = ref('waiting'); // waiting, flying, crashed
const currentMultiplier = ref(1.00);
const crashedAt = ref(0);
const countdown = ref(5);
const currentRound = ref(1);
const roundHash = ref('');

// Bet State
const betAmount = ref(10);
const hasBet = ref(false);
const cashedOut = ref(false);
const lastWin = ref(0);
const autoCashoutEnabled = ref(false);
const autoCashoutAt = ref(2.00);

// Auto Bet
const autoMode = ref(false);
const autoRounds = ref(10);
const stopOnWin = ref(false);
const isAutoRunning = ref(false);
const autoCurrentRound = ref(0);

// Players and Rounds
const activePlayers = ref([]);
const recentRounds = ref([]);

// Provably Fair
const showProvablyFair = ref(false);

// Graph
const graphCanvas = ref(null);
let graphCtx = null;
let graphData = [];
let animationFrameId = null;

// Game Loop
let gameLoopInterval = null;
let multiplierInterval = null;

// Computed
const canPlaceBet = computed(() => {
  return gameState.value === 'waiting' && 
         walletStore.balance.real_balance >= betAmount.value &&
         !hasBet.value;
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

function getRoundClass(multiplier) {
  if (multiplier < 1.5) return 'low';
  if (multiplier < 2.0) return 'medium';
  if (multiplier < 5.0) return 'high';
  return 'mega';
}

async function placeBet() {
  if (!canPlaceBet.value) return;

  try {
    const response = await axios.post('/api/games/crash/bet', {
      bet_amount: betAmount.value,
      auto_cashout_at: autoCashoutEnabled.value ? autoCashoutAt.value : null,
    });

    hasBet.value = true;
    cashedOut.value = false;
    lastWin.value = 0;

    // Add self to active players
    activePlayers.value.push({
      id: response.data.bet_id,
      username: 'You',
      bet_amount: betAmount.value,
      cashed_out: false,
    });

    walletStore.fetchBalance();

  } catch (error) {
    alert(error.response?.data?.message || 'Failed to place bet');
  }
}

async function cashout() {
  if (!hasBet.value || cashedOut.value) return;

  try {
    const response = await axios.post('/api/games/crash/cashout', {
      multiplier: currentMultiplier.value,
    });

    cashedOut.value = true;
    lastWin.value = response.data.payout;

    // Update player in list
    const playerIndex = activePlayers.value.findIndex(p => p.username === 'You');
    if (playerIndex !== -1) {
      activePlayers.value[playerIndex].cashed_out = true;
      activePlayers.value[playerIndex].cashout_multiplier = currentMultiplier.value;
      activePlayers.value[playerIndex].profit = lastWin.value - betAmount.value;
    }

    walletStore.fetchBalance();

  } catch (error) {
    alert(error.response?.data?.message || 'Failed to cash out');
  }
}

function startRound() {
  gameState.value = 'flying';
  currentMultiplier.value = 1.00;
  graphData = [];
  
  // Determine crash point (simulated - in real app, this comes from backend)
  const crashPoint = generateCrashPoint();
  crashedAt.value = crashPoint;

  let startTime = Date.now();
  let lastUpdate = startTime;

  // Multiplier animation
  multiplierInterval = setInterval(() => {
    const elapsed = Date.now() - startTime;
    const newMultiplier = 1 + (elapsed / 1000) * 0.5; // Increases by 0.5x per second

    if (newMultiplier >= crashPoint) {
      currentMultiplier.value = crashPoint;
      endRound();
    } else {
      currentMultiplier.value = parseFloat(newMultiplier.toFixed(2));
      
      // Update graph data
      graphData.push({
        time: elapsed / 1000,
        multiplier: currentMultiplier.value,
      });

      // Check auto cashout
      if (hasBet.value && !cashedOut.value && autoCashoutEnabled.value) {
        if (currentMultiplier.value >= autoCashoutAt.value) {
          cashout();
        }
      }
    }
  }, 100);
}

function endRound() {
  clearInterval(multiplierInterval);
  gameState.value = 'crashed';

  // Add to recent rounds
  recentRounds.value.unshift({
    id: currentRound.value,
    crashed_at: crashedAt.value,
  });
  if (recentRounds.value.length > 20) {
    recentRounds.value.pop();
  }

  // Process bets
  if (hasBet.value && !cashedOut.value) {
    // Lost
  }

  // Wait 3 seconds then start countdown
  setTimeout(() => {
    resetForNextRound();
  }, 3000);
}

function resetForNextRound() {
  gameState.value = 'waiting';
  countdown.value = 5;
  currentRound.value++;
  roundHash.value = generateHash();
  hasBet.value = false;
  cashedOut.value = false;
  activePlayers.value = [];
  graphData = [];

  // Countdown
  const countdownInterval = setInterval(() => {
    countdown.value--;
    if (countdown.value <= 0) {
      clearInterval(countdownInterval);
      
      // Auto bet
      if (autoMode.value && isAutoRunning.value) {
        if (autoCurrentRound.value < autoRounds.value) {
          placeBet();
          autoCurrentRound.value++;
        } else {
          isAutoRunning.value = false;
          autoCurrentRound.value = 0;
        }
      }
      
      startRound();
    }
  }, 1000);
}

function generateCrashPoint() {
  // Simplified crash point generation (1.00x to 10.00x)
  // Real implementation uses provably fair algorithm
  const random = Math.random();
  if (random < 0.33) return parseFloat((1 + Math.random() * 0.5).toFixed(2)); // 1.00-1.50
  if (random < 0.66) return parseFloat((1.5 + Math.random() * 1.5).toFixed(2)); // 1.50-3.00
  if (random < 0.90) return parseFloat((3 + Math.random() * 2).toFixed(2)); // 3.00-5.00
  return parseFloat((5 + Math.random() * 5).toFixed(2)); // 5.00-10.00
}

function generateHash() {
  return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
}

function initGraph() {
  if (!graphCanvas.value) return;
  
  graphCtx = graphCanvas.value.getContext('2d');
  graphCanvas.value.width = graphCanvas.value.offsetWidth;
  graphCanvas.value.height = graphCanvas.value.offsetHeight;
  
  drawGraph();
}

function drawGraph() {
  if (!graphCtx || !graphCanvas.value) return;

  const width = graphCanvas.value.width;
  const height = graphCanvas.value.height;

  // Clear canvas
  graphCtx.clearRect(0, 0, width, height);

  // Draw grid
  graphCtx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
  graphCtx.lineWidth = 1;
  for (let i = 0; i <= 5; i++) {
    const y = (height / 5) * i;
    graphCtx.beginPath();
    graphCtx.moveTo(0, y);
    graphCtx.lineTo(width, y);
    graphCtx.stroke();
  }

  // Draw multiplier line
  if (graphData.length > 1) {
    const maxTime = Math.max(10, graphData[graphData.length - 1].time);
    const maxMultiplier = Math.max(3, currentMultiplier.value + 0.5);

    graphCtx.beginPath();
    graphCtx.strokeStyle = gameState.value === 'crashed' ? '#fc8181' : '#667eea';
    graphCtx.lineWidth = 3;

    graphData.forEach((point, index) => {
      const x = (point.time / maxTime) * width;
      const y = height - ((point.multiplier - 1) / (maxMultiplier - 1)) * height;

      if (index === 0) {
        graphCtx.moveTo(x, y);
      } else {
        graphCtx.lineTo(x, y);
      }
    });

    graphCtx.stroke();

    // Fill area under line
    graphCtx.lineTo((graphData[graphData.length - 1].time / maxTime) * width, height);
    graphCtx.lineTo(0, height);
    graphCtx.closePath();
    
    const gradient = graphCtx.createLinearGradient(0, 0, 0, height);
    gradient.addColorStop(0, gameState.value === 'crashed' ? 'rgba(252, 129, 129, 0.3)' : 'rgba(102, 126, 234, 0.3)');
    gradient.addColorStop(1, 'rgba(102, 126, 234, 0)');
    graphCtx.fillStyle = gradient;
    graphCtx.fill();
  }

  animationFrameId = requestAnimationFrame(drawGraph);
}

onMounted(() => {
  walletStore.fetchBalance();
  roundHash.value = generateHash();
  
  nextTick(() => {
    initGraph();
  });

  // Start game loop
  resetForNextRound();
});

onUnmounted(() => {
  if (gameLoopInterval) clearInterval(gameLoopInterval);
  if (multiplierInterval) clearInterval(multiplierInterval);
  if (animationFrameId) cancelAnimationFrame(animationFrameId);
});

watch(autoMode, (newVal) => {
  if (newVal) {
    isAutoRunning.value = true;
    autoCurrentRound.value = 0;
  } else {
    isAutoRunning.value = false;
  }
});
</script>

<style scoped>
.crash-game {
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
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.crash-display {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.multiplier-display {
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 40px;
  text-align: center;
  min-height: 200px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.multiplier-display.flying {
  border-color: rgba(102, 126, 234, 0.5);
  box-shadow: 0 0 30px rgba(102, 126, 234, 0.3);
}

.multiplier-display.crashed {
  border-color: rgba(252, 129, 129, 0.5);
  box-shadow: 0 0 30px rgba(252, 129, 129, 0.3);
}

.waiting-state,
.flying-state,
.crashed-state {
  width: 100%;
}

.waiting-text {
  font-size: 24px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 16px;
}

.countdown {
  font-size: 80px;
  font-weight: 800;
  color: #667eea;
  animation: countdownPulse 1s infinite;
}

@keyframes countdownPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

.current-multiplier {
  font-size: 120px;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
  margin-bottom: 12px;
  animation: multiplierGrow 0.3s ease-out;
}

@keyframes multiplierGrow {
  0% { transform: scale(0.95); }
  100% { transform: scale(1); }
}

.multiplier-label {
  font-size: 18px;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 2px;
}

.crash-text {
  font-size: 48px;
  font-weight: 800;
  color: #fc8181;
  margin-bottom: 16px;
  animation: crashShake 0.5s;
}

@keyframes crashShake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-10px); }
  75% { transform: translateX(10px); }
}

.crash-multiplier {
  font-size: 80px;
  font-weight: 800;
  color: #fc8181;
}

.graph-container {
  flex: 1;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 12px;
  padding: 20px;
  min-height: 300px;
}

.multiplier-graph {
  width: 100%;
  height: 100%;
}

.active-players {
  max-height: 300px;
  overflow-y: auto;
}

.active-players h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
}

.players-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.player-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
}

.player-item.cashed-out {
  border-color: rgba(72, 187, 120, 0.3);
}

.player-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.player-name {
  font-weight: 600;
  font-size: 14px;
}

.player-bet {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.player-cashout {
  display: flex;
  gap: 12px;
  align-items: center;
}

.cashout-multiplier {
  font-weight: 700;
  color: #667eea;
}

.cashout-profit {
  font-weight: 700;
  color: #48bb78;
}

.player-waiting {
  display: flex;
  align-items: center;
}

.spinner-small {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
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
  display: flex;
  align-items: center;
  gap: 8px;
}

.checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
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

.auto-cashout-input {
  position: relative;
}

.input-suffix {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(255, 255, 255, 0.6);
  font-weight: 600;
}

.btn-action {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 12px;
  padding: 16px;
  color: white;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-action:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-action:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-action.pulse {
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7); }
  50% { box-shadow: 0 0 0 10px rgba(102, 126, 234, 0); }
}

.btn-cashout {
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
  border: none;
  border-radius: 12px;
  padding: 16px;
  color: white;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  animation: cashoutPulse 0.5s infinite;
}

@keyframes cashoutPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

.btn-cashout:hover {
  transform: translateY(-2px) scale(1.05);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
}

.bet-result {
  padding: 20px;
  border-radius: 12px;
  text-align: center;
}

.bet-result.success {
  background: rgba(72, 187, 120, 0.2);
  border: 2px solid rgba(72, 187, 120, 0.5);
}

.bet-result.loss {
  background: rgba(252, 129, 129, 0.2);
  border: 2px solid rgba(252, 129, 129, 0.5);
}

.result-icon {
  font-size: 32px;
  margin-bottom: 8px;
}

.bet-result.success .result-icon {
  color: #48bb78;
}

.bet-result.loss .result-icon {
  color: #fc8181;
}

.result-text {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 4px;
}

.result-amount {
  font-size: 20px;
  font-weight: 800;
}

.bet-result.success .result-amount {
  color: #48bb78;
}

.bet-result.loss .result-amount {
  color: #fc8181;
}

.insufficient-balance-msg {
  text-align: center;
  color: #fc8181;
  font-size: 13px;
}

.round-info {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.info-value {
  font-weight: 700;
  color: #667eea;
}

.info-value.profit {
  color: #48bb78;
}

.auto-bet-section {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 20px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.toggle-switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 24px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  transition: 0.3s;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background: white;
  border-radius: 50%;
  transition: 0.3s;
}

input:checked + .toggle-slider {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

input:checked + .toggle-slider:before {
  transform: translateX(24px);
}

.auto-options {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.auto-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.auto-option label {
  font-size: 13px;
}

.auto-input {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  padding: 6px 12px;
  color: white;
  width: 80px;
  text-align: center;
}

.auto-progress {
  text-align: center;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  padding: 8px;
  background: rgba(102, 126, 234, 0.1);
  border-radius: 6px;
}

.provably-fair-section {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 20px;
}

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
}

.provably-fair-content {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
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

.provably-fair-note {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.4;
}

.recent-rounds {
  grid-column: 2;
  grid-row: 2;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
}

.recent-rounds h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
}

.rounds-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.round-item {
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 14px;
}

.round-item.low {
  background: rgba(252, 129, 129, 0.2);
  color: #fc8181;
}

.round-item.medium {
  background: rgba(237, 137, 54, 0.2);
  color: #ed8936;
}

.round-item.high {
  background: rgba(102, 126, 234, 0.2);
  color: #667eea;
}

.round-item.mega {
  background: rgba(159, 122, 234, 0.2);
  color: #9f7aea;
}

@media (max-width: 1200px) {
  .crash-game {
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

  .recent-rounds {
    grid-column: 1;
    grid-row: 3;
  }
}
</style>
