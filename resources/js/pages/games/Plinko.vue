<template>
  <div class="plinko-game">
    <!-- Game Canvas -->
    <div class="game-container">
      <div class="plinko-display">
        <!-- Canvas for Plinko Board -->
        <canvas ref="plinkoCanvas" class="plinko-canvas"></canvas>
        
        <!-- Multipliers Display -->
        <div class="multipliers-row">
          <div 
            v-for="(mult, index) in currentMultipliers" 
            :key="index"
            class="multiplier-box"
            :class="getMultiplierClass(mult)"
          >
            {{ mult }}×
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
          <button @click="divideBetBy2" class="btn-adjust" :disabled="isDropping">½</button>
          <input 
            v-model.number="betAmount" 
            type="number" 
            min="1" 
            step="1"
            class="amount-input"
            :disabled="isDropping"
          >
          <button @click="multiplyBetBy2" class="btn-adjust" :disabled="isDropping">2×</button>
        </div>
        <div class="quick-amounts">
          <button 
            v-for="amount in [10, 50, 100, 500]" 
            :key="amount"
            @click="betAmount = amount"
            class="btn-quick-amount"
            :disabled="isDropping"
          >
            ₱{{ amount }}
          </button>
        </div>
      </div>

      <!-- Risk Level -->
      <div class="control-group">
        <label class="control-label">Risk Level</label>
        <div class="risk-buttons">
          <button
            v-for="risk in ['low', 'medium', 'high']"
            :key="risk"
            @click="riskLevel = risk"
            class="btn-risk"
            :class="{ active: riskLevel === risk }"
            :disabled="isDropping"
          >
            {{ risk.toUpperCase() }}
          </button>
        </div>
      </div>

      <!-- Rows Selection -->
      <div class="control-group">
        <label class="control-label">Number of Rows</label>
        <div class="rows-selector">
          <button
            v-for="rows in [8, 12, 16]"
            :key="rows"
            @click="rowCount = rows"
            class="btn-rows"
            :class="{ active: rowCount === rows }"
            :disabled="isDropping"
          >
            {{ rows }}
          </button>
        </div>
      </div>

      <!-- Drop Button -->
      <button 
        @click="dropBall" 
        class="btn-drop"
        :disabled="!canDrop"
        :class="{ dropping: isDropping }"
      >
        <span v-if="isDropping" class="spinner"></span>
        <span v-else>🔵 {{ isDropping ? 'Dropping...' : 'Drop Ball' }}</span>
      </button>

      <div v-if="!canDrop && !isDropping" class="insufficient-balance-msg">
        Insufficient balance
      </div>

      <!-- Last Result -->
      <div v-if="lastResult" class="last-result" :class="{ win: lastResult.multiplier >= 2 }">
        <div class="result-label">Last Drop</div>
        <div class="result-multiplier">{{ lastResult.multiplier }}×</div>
        <div class="result-payout">
          {{ lastResult.multiplier >= 1 ? '+' : '' }}₱{{ formatMoney(lastResult.payout) }}
        </div>
      </div>

      <!-- Auto Drop -->
      <div class="auto-drop-section">
        <div class="section-header">
          <label class="control-label">Auto Drop</label>
          <label class="toggle-switch">
            <input v-model="autoMode" type="checkbox" :disabled="isDropping">
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div v-if="autoMode" class="auto-options">
          <div class="auto-option">
            <label>Number of Drops</label>
            <input v-model.number="autoDrops" type="number" min="1" max="100" class="auto-input">
          </div>
          <div class="auto-option">
            <label>Stop on Win > 10×</label>
            <input v-model="stopOnBigWin" type="checkbox">
          </div>

          <div v-if="isAutoRunning" class="auto-progress">
            Drops: {{ autoCurrentDrop }} / {{ autoDrops }}
          </div>
        </div>
      </div>

      <!-- Multiplier Table -->
      <div class="multipliers-info">
        <h3>Risk Level Info</h3>
        <div class="risk-info">
          <div class="info-row">
            <span>Max Multiplier:</span>
            <span class="info-value">{{ getMaxMultiplier() }}×</span>
          </div>
          <div class="info-row">
            <span>Potential Win:</span>
            <span class="info-value profit">₱{{ formatMoney(betAmount * getMaxMultiplier()) }}</span>
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
              <label>Client Seed</label>
              <div class="seed-input-group">
                <input v-model="clientSeed" type="text" class="seed-input" readonly>
                <button @click="regenerateClientSeed" class="btn-regenerate">🔄</button>
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
            Ball path is determined using cryptographic hashing before the drop begins.
          </p>
        </div>
      </div>
    </div>

    <!-- Recent Drops -->
    <div class="recent-drops">
      <h3>Recent Drops</h3>
      <div class="drops-list">
        <div v-for="drop in recentDrops" :key="drop.id" class="drop-item" :class="getDropClass(drop.multiplier)">
          <div class="drop-multiplier">{{ drop.multiplier }}×</div>
          <div class="drop-payout">₱{{ formatMoney(drop.payout) }}</div>
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
const betAmount = ref(10);
const riskLevel = ref('medium');
const rowCount = ref(16);
const isDropping = ref(false);
const lastResult = ref(null);
const recentDrops = ref([]);

// Auto Drop
const autoMode = ref(false);
const autoDrops = ref(10);
const stopOnBigWin = ref(false);
const isAutoRunning = ref(false);
const autoCurrentDrop = ref(0);

// Provably Fair
const showProvablyFair = ref(false);
const clientSeed = ref('');
const serverSeedHash = ref('');
const nonce = ref(0);

// Canvas
const plinkoCanvas = ref(null);
let ctx = null;
let animationFrameId = null;
let balls = [];

// Multipliers based on risk level and rows
const multipliers = {
  low: {
    8: [5.6, 2.1, 1.1, 1, 0.5, 1, 1.1, 2.1, 5.6],
    12: [10, 3, 1.6, 1.4, 1.1, 1, 0.5, 1, 1.1, 1.4, 1.6, 3, 10],
    16: [16, 9, 2, 1.4, 1.4, 1.2, 1.1, 1, 0.5, 1, 1.1, 1.2, 1.4, 1.4, 2, 9, 16]
  },
  medium: {
    8: [13, 3, 1.3, 0.7, 0.4, 0.7, 1.3, 3, 13],
    12: [18, 4, 1.7, 0.9, 0.5, 0.3, 0.2, 0.3, 0.5, 0.9, 1.7, 4, 18],
    16: [33, 11, 4, 2, 1.1, 0.6, 0.3, 0.2, 0.2, 0.2, 0.3, 0.6, 1.1, 2, 4, 11, 33]
  },
  high: {
    8: [29, 4, 1.5, 0.3, 0.2, 0.3, 1.5, 4, 29],
    12: [43, 7, 2, 0.6, 0.2, 0.2, 0.1, 0.2, 0.2, 0.6, 2, 7, 43],
    16: [110, 41, 10, 5, 3, 1.5, 1, 0.5, 0.3, 0.5, 1, 1.5, 3, 5, 10, 41, 110]
  }
};

// Computed
const currentMultipliers = computed(() => {
  return multipliers[riskLevel.value][rowCount.value];
});

const canDrop = computed(() => {
  return walletStore.balance.real_balance >= betAmount.value && !isDropping.value;
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

function getMaxMultiplier() {
  const mults = currentMultipliers.value;
  return Math.max(...mults);
}

function getMultiplierClass(mult) {
  if (mult >= 10) return 'mega';
  if (mult >= 5) return 'high';
  if (mult >= 2) return 'medium';
  if (mult >= 1) return 'low';
  return 'loss';
}

function getDropClass(mult) {
  return getMultiplierClass(mult);
}

async function dropBall() {
  if (!canDrop.value) return;

  isDropping.value = true;

  try {
    const response = await axios.post('/api/games/plinko/play', {
      bet_amount: betAmount.value,
      risk_level: riskLevel.value,
      rows: rowCount.value,
      client_seed: clientSeed.value,
    });

    const result = response.data;
    
    // Animate ball drop
    animateBallDrop(result.path, result.multiplier, () => {
      lastResult.value = {
        multiplier: result.multiplier,
        payout: result.payout,
      };

      recentDrops.value.unshift({
        id: Date.now(),
        multiplier: result.multiplier,
        payout: result.payout,
      });
      if (recentDrops.value.length > 20) {
        recentDrops.value.pop();
      }

      nonce.value = result.nonce;
      walletStore.fetchBalance();
      isDropping.value = false;

      // Auto drop
      if (isAutoRunning.value) {
        autoCurrentDrop.value++;
        
        if (autoCurrentDrop.value >= autoDrops.value) {
          stopAutoDrop();
        } else if (stopOnBigWin.value && result.multiplier >= 10) {
          stopAutoDrop();
        } else {
          setTimeout(() => dropBall(), 500);
        }
      }
    });

  } catch (error) {
    isDropping.value = false;
    alert(error.response?.data?.message || 'Failed to drop ball');
  }
}

function animateBallDrop(path, finalMultiplier, callback) {
  if (!ctx || !plinkoCanvas.value) return;

  const canvas = plinkoCanvas.value;
  const width = canvas.width;
  const height = canvas.height;
  
  const pegRadius = 4;
  const ballRadius = 8;
  const rows = rowCount.value;
  const cols = rows + 1;
  
  const rowHeight = (height - 100) / (rows + 1);
  const colWidth = width / (cols + 1);
  
  // Create ball object
  const ball = {
    x: width / 2,
    y: 20,
    vx: 0,
    vy: 0,
    path: path || [],
    pathIndex: 0,
    row: 0,
  };

  balls.push(ball);

  function animate() {
    // Clear canvas
    ctx.clearRect(0, 0, width, height);
    
    // Draw pegs
    drawPegs();
    
    // Update and draw balls
    for (let i = balls.length - 1; i >= 0; i--) {
      const b = balls[i];
      
      // Physics
      b.vy += 0.5; // Gravity
      b.x += b.vx;
      b.y += b.vy;
      
      // Follow path
      if (b.pathIndex < b.path.length) {
        const targetRow = b.pathIndex + 1;
        const targetY = targetRow * rowHeight + 40;
        
        if (b.y >= targetY) {
          b.row = targetRow;
          const direction = b.path[b.pathIndex];
          b.vx = direction === 'R' ? 2 : -2;
          b.pathIndex++;
        }
      }
      
      // Friction
      b.vx *= 0.95;
      b.vy *= 0.98;
      
      // Draw ball
      ctx.beginPath();
      ctx.arc(b.x, b.y, ballRadius, 0, Math.PI * 2);
      ctx.fillStyle = '#667eea';
      ctx.fill();
      ctx.strokeStyle = '#764ba2';
      ctx.lineWidth = 2;
      ctx.stroke();
      
      // Check if ball reached bottom
      if (b.y > height - 80) {
        balls.splice(i, 1);
        if (callback) callback();
      }
    }
    
    if (balls.length > 0) {
      animationFrameId = requestAnimationFrame(animate);
    }
  }

  animate();
}

function drawPegs() {
  if (!ctx || !plinkoCanvas.value) return;

  const canvas = plinkoCanvas.value;
  const width = canvas.width;
  const height = canvas.height;
  
  const pegRadius = 4;
  const rows = rowCount.value;
  const rowHeight = (height - 100) / (rows + 1);
  const colWidth = width / (rows + 2);

  ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
  
  for (let row = 0; row <= rows; row++) {
    const pegsInRow = row + 1;
    const y = row * rowHeight + 40;
    
    for (let col = 0; col < pegsInRow; col++) {
      const x = (width - (pegsInRow - 1) * colWidth) / 2 + col * colWidth;
      
      ctx.beginPath();
      ctx.arc(x, y, pegRadius, 0, Math.PI * 2);
      ctx.fill();
    }
  }
}

function stopAutoDrop() {
  isAutoRunning.value = false;
  autoCurrentDrop.value = 0;
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

function initCanvas() {
  if (!plinkoCanvas.value) return;
  
  ctx = plinkoCanvas.value.getContext('2d');
  plinkoCanvas.value.width = plinkoCanvas.value.offsetWidth;
  plinkoCanvas.value.height = plinkoCanvas.value.offsetHeight;
  
  drawPegs();
}

onMounted(() => {
  clientSeed.value = generateClientSeed();
  fetchSeedInfo();
  walletStore.fetchBalance();
  
  nextTick(() => {
    initCanvas();
  });
});

onUnmounted(() => {
  if (animationFrameId) cancelAnimationFrame(animationFrameId);
});

watch(autoMode, (newVal) => {
  if (newVal) {
    isAutoRunning.value = true;
    autoCurrentDrop.value = 0;
  } else {
    isAutoRunning.value = false;
  }
});

watch([riskLevel, rowCount], () => {
  if (!isDropping.value) {
    nextTick(() => {
      initCanvas();
    });
  }
});
</script>

<style scoped>
.plinko-game {
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

.plinko-display {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.plinko-canvas {
  flex: 1;
  width: 100%;
  background: rgba(0, 0, 0, 0.3);
  border-radius: 12px;
}

.multipliers-row {
  display: flex;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
}

.multiplier-box {
  min-width: 50px;
  padding: 8px 12px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 14px;
  text-align: center;
}

.multiplier-box.loss {
  background: rgba(252, 129, 129, 0.2);
  color: #fc8181;
}

.multiplier-box.low {
  background: rgba(237, 137, 54, 0.2);
  color: #ed8936;
}

.multiplier-box.medium {
  background: rgba(102, 126, 234, 0.2);
  color: #667eea;
}

.multiplier-box.high {
  background: rgba(159, 122, 234, 0.2);
  color: #9f7aea;
}

.multiplier-box.mega {
  background: linear-gradient(135deg, rgba(245, 101, 101, 0.3), rgba(159, 122, 234, 0.3));
  color: #f6ad55;
  animation: megaPulse 1s infinite;
}

@keyframes megaPulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
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

.risk-buttons,
.rows-selector {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.btn-risk,
.btn-rows {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 10px;
  color: white;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
  font-size: 13px;
}

.btn-risk:hover:not(:disabled),
.btn-rows:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.1);
}

.btn-risk.active,
.btn-rows.active {
  background: rgba(102, 126, 234, 0.2);
  border-color: rgba(102, 126, 234, 0.5);
  color: #667eea;
}

.btn-drop {
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

.btn-drop:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-drop:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-drop.dropping {
  animation: pulse 0.5s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
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

.insufficient-balance-msg {
  text-align: center;
  color: #fc8181;
  font-size: 13px;
}

.last-result {
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}

.last-result.win {
  border-color: rgba(72, 187, 120, 0.5);
  background: rgba(72, 187, 120, 0.1);
}

.result-label {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 8px;
  text-transform: uppercase;
}

.result-multiplier {
  font-size: 32px;
  font-weight: 800;
  color: #667eea;
  margin-bottom: 4px;
}

.result-payout {
  font-size: 20px;
  font-weight: 700;
  color: #48bb78;
}

.auto-drop-section {
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

.multipliers-info {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 16px;
}

.multipliers-info h3 {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
}

.risk-info {
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

.btn-regenerate:hover {
  background: rgba(102, 126, 234, 0.3);
}

.provably-fair-note {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.4;
}

.recent-drops {
  grid-column: 2;
  grid-row: 2;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  max-height: 300px;
  overflow-y: auto;
}

.recent-drops h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
}

.drops-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.drop-item {
  padding: 8px 12px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 70px;
}

.drop-item.loss {
  background: rgba(252, 129, 129, 0.2);
}

.drop-item.low {
  background: rgba(237, 137, 54, 0.2);
}

.drop-item.medium {
  background: rgba(102, 126, 234, 0.2);
}

.drop-item.high {
  background: rgba(159, 122, 234, 0.2);
}

.drop-item.mega {
  background: linear-gradient(135deg, rgba(245, 101, 101, 0.3), rgba(159, 122, 234, 0.3));
}

.drop-multiplier {
  font-weight: 700;
  font-size: 14px;
}

.drop-payout {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
}

@media (max-width: 1200px) {
  .plinko-game {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
    height: auto;
  }

  .game-container {
    grid-column: 1;
    grid-row: 1;
    min-height: 500px;
  }

  .controls-sidebar {
    grid-column: 1;
    grid-row: 2;
  }

  .recent-drops {
    grid-column: 1;
    grid-row: 3;
  }
}
</style>
