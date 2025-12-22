<template>
  <div class="wheel-game">
    <!-- Game Area -->
    <div class="game-container">
      <!-- Wheel Display -->
      <div class="wheel-section">
        <div class="wheel-container">
          <!-- Pointer -->
          <div class="wheel-pointer">▼</div>
          
          <!-- Wheel -->
          <svg 
            class="wheel-svg" 
            :class="{ spinning: isSpinning }"
            :style="{ transform: `rotate(${rotation}deg)` }"
            viewBox="0 0 400 400"
          >
            <g v-for="(segment, index) in segments" :key="index">
              <!-- Segment Path -->
              <path
                :d="getSegmentPath(index)"
                :fill="getSegmentColor(segment.multiplier)"
                stroke="#1a202c"
                stroke-width="2"
              />
              
              <!-- Segment Text -->
              <text
                :x="getTextX(index)"
                :y="getTextY(index)"
                :transform="getTextTransform(index)"
                text-anchor="middle"
                dominant-baseline="middle"
                :fill="getTextColor(segment.multiplier)"
                font-size="16"
                font-weight="800"
              >
                {{ segment.multiplier }}×
              </text>
            </g>
            
            <!-- Center Circle -->
            <circle cx="200" cy="200" r="30" fill="#1a202c" stroke="#667eea" stroke-width="3" />
            <text x="200" y="200" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="12" font-weight="700">SPIN</text>
          </svg>
        </div>

        <!-- Last Result -->
        <div v-if="lastResult && !isSpinning" class="last-result" :class="getResultClass(lastResult.multiplier)">
          <div class="result-label">Last Spin</div>
          <div class="result-multiplier">{{ lastResult.multiplier }}×</div>
          <div class="result-payout">{{ lastResult.payout >= betAmount ? '+' : '' }}₱{{ formatMoney(lastResult.payout - betAmount) }}</div>
        </div>
      </div>

      <!-- Game Stats -->
      <div class="game-stats">
        <div class="stat-card">
          <div class="stat-label">Bet Amount</div>
          <div class="stat-value">₱{{ formatMoney(betAmount) }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Risk Level</div>
          <div class="stat-value risk">{{ riskLevel.toUpperCase() }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Max Multiplier</div>
          <div class="stat-value multiplier">{{ getMaxMultiplier() }}×</div>
        </div>
        <div class="stat-card profit">
          <div class="stat-label">Max Win</div>
          <div class="stat-value">₱{{ formatMoney(betAmount * getMaxMultiplier()) }}</div>
        </div>
      </div>
    </div>

    <!-- Controls Sidebar -->
    <div class="controls-sidebar">
      <!-- Bet Amount -->
      <div class="control-group">
        <label class="control-label">Bet Amount</label>
        <div class="amount-input-group">
          <button @click="divideBetBy2" class="btn-adjust" :disabled="isSpinning">½</button>
          <input 
            v-model.number="betAmount" 
            type="number" 
            min="1" 
            step="1"
            class="amount-input"
            :disabled="isSpinning"
          >
          <button @click="multiplyBetBy2" class="btn-adjust" :disabled="isSpinning">2×</button>
        </div>
        <div class="quick-amounts">
          <button 
            v-for="amount in [10, 50, 100, 500]" 
            :key="amount"
            @click="betAmount = amount"
            class="btn-quick-amount"
            :disabled="isSpinning"
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
            @click="changeRisk(risk)"
            class="btn-risk"
            :class="{ active: riskLevel === risk }"
            :disabled="isSpinning"
          >
            {{ risk.toUpperCase() }}
          </button>
        </div>
        <div class="risk-description">
          <p v-if="riskLevel === 'low'">Safer bets with more frequent wins (up to 2×)</p>
          <p v-if="riskLevel === 'medium'">Balanced risk and reward (up to 10×)</p>
          <p v-if="riskLevel === 'high'">High risk, high reward (up to 50×)</p>
        </div>
      </div>

      <!-- Spin Button -->
      <button 
        @click="spin" 
        class="btn-spin"
        :disabled="!canSpin"
      >
        <span v-if="isSpinning" class="spinner-icon">🎡</span>
        <span v-else>🎰 Spin Wheel</span>
      </button>

      <div v-if="!canSpin && !isSpinning" class="insufficient-balance-msg">
        Insufficient balance
      </div>

      <!-- Auto Spin -->
      <div class="auto-spin-section">
        <div class="section-header">
          <label class="control-label">Auto Spin</label>
          <label class="toggle-switch">
            <input v-model="autoMode" type="checkbox" :disabled="isSpinning">
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div v-if="autoMode" class="auto-options">
          <div class="auto-option">
            <label>Number of Spins</label>
            <input v-model.number="autoSpins" type="number" min="1" max="100" class="auto-input">
          </div>
          <div class="auto-option">
            <label>Stop on Win > 10×</label>
            <input v-model="stopOnBigWin" type="checkbox">
          </div>

          <div v-if="isAutoRunning" class="auto-progress">
            Spins: {{ autoCurrentSpin }} / {{ autoSpins }}
          </div>
        </div>
      </div>

      <!-- Multiplier Distribution -->
      <div class="multiplier-distribution">
        <h3>Multiplier Distribution</h3>
        <div class="distribution-list">
          <div v-for="item in getMultiplierDistribution()" :key="item.multiplier" class="distribution-item">
            <div class="dist-multiplier" :style="{ color: getSegmentColor(item.multiplier) }">
              {{ item.multiplier }}×
            </div>
            <div class="dist-bar">
              <div class="dist-fill" :style="{ width: item.percentage + '%', background: getSegmentColor(item.multiplier) }"></div>
            </div>
            <div class="dist-percentage">{{ item.percentage }}%</div>
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
            <li>Choose your bet amount</li>
            <li>Select a risk level (Low, Medium, or High)</li>
            <li>Click "Spin Wheel" to play</li>
            <li>The wheel will spin and land on a multiplier</li>
            <li>Your bet is multiplied by the result</li>
            <li>Higher risk = higher potential multipliers!</li>
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
                <button @click="regenerateClientSeed" class="btn-regenerate" :disabled="isSpinning">🔄</button>
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
            Spin result is determined using cryptographic hashing before the wheel spins.
          </p>
        </div>
      </div>
    </div>

    <!-- Recent Spins -->
    <div class="recent-spins">
      <h3>Recent Spins</h3>
      <div class="spins-list">
        <div v-for="spin in recentSpins" :key="spin.id" class="spin-item" :class="getResultClass(spin.multiplier)">
          <div class="spin-multiplier">{{ spin.multiplier }}×</div>
          <div class="spin-payout">₱{{ formatMoney(spin.payout) }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useWalletStore } from '../../stores/wallet';

const walletStore = useWalletStore();

// Game State
const betAmount = ref(10);
const riskLevel = ref('medium');
const isSpinning = ref(false);
const rotation = ref(0);
const lastResult = ref(null);
const recentSpins = ref([]);

// Auto Spin
const autoMode = ref(false);
const autoSpins = ref(10);
const stopOnBigWin = ref(false);
const isAutoRunning = ref(false);
const autoCurrentSpin = ref(0);

// UI State
const showHowToPlay = ref(false);
const showProvablyFair = ref(false);

// Provably Fair
const clientSeed = ref('');
const serverSeedHash = ref('');
const nonce = ref(0);

// Wheel Segments Configuration
const wheelConfigs = {
  low: {
    segments: [
      { multiplier: 1.2, count: 15 },
      { multiplier: 1.5, count: 10 },
      { multiplier: 2, count: 5 },
    ],
    total: 30
  },
  medium: {
    segments: [
      { multiplier: 0, count: 10 },
      { multiplier: 1.5, count: 8 },
      { multiplier: 2, count: 6 },
      { multiplier: 3, count: 4 },
      { multiplier: 5, count: 2 },
      { multiplier: 10, count: 1 },
    ],
    total: 31
  },
  high: {
    segments: [
      { multiplier: 0, count: 15 },
      { multiplier: 2, count: 5 },
      { multiplier: 5, count: 3 },
      { multiplier: 10, count: 2 },
      { multiplier: 20, count: 1 },
      { multiplier: 50, count: 1 },
    ],
    total: 27
  }
};

const segments = ref([]);

// Computed
const canSpin = computed(() => {
  return walletStore.balance.real_balance >= betAmount.value && !isSpinning.value;
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

function changeRisk(risk) {
  riskLevel.value = risk;
  generateSegments();
}

function generateSegments() {
  const config = wheelConfigs[riskLevel.value];
  segments.value = [];
  
  config.segments.forEach(seg => {
    for (let i = 0; i < seg.count; i++) {
      segments.value.push({ multiplier: seg.multiplier });
    }
  });
  
  // Shuffle segments
  for (let i = segments.value.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [segments.value[i], segments.value[j]] = [segments.value[j], segments.value[i]];
  }
}

function getMaxMultiplier() {
  const config = wheelConfigs[riskLevel.value];
  return Math.max(...config.segments.map(s => s.multiplier));
}

function getMultiplierDistribution() {
  const config = wheelConfigs[riskLevel.value];
  return config.segments.map(seg => ({
    multiplier: seg.multiplier,
    percentage: ((seg.count / config.total) * 100).toFixed(1)
  })).sort((a, b) => b.multiplier - a.multiplier);
}

function getSegmentPath(index) {
  const total = segments.value.length;
  const anglePerSegment = 360 / total;
  const startAngle = index * anglePerSegment - 90;
  const endAngle = startAngle + anglePerSegment;
  
  const startRad = (startAngle * Math.PI) / 180;
  const endRad = (endAngle * Math.PI) / 180;
  
  const radius = 180;
  const centerX = 200;
  const centerY = 200;
  
  const x1 = centerX + radius * Math.cos(startRad);
  const y1 = centerY + radius * Math.sin(startRad);
  const x2 = centerX + radius * Math.cos(endRad);
  const y2 = centerY + radius * Math.sin(endRad);
  
  return `M ${centerX} ${centerY} L ${x1} ${y1} A ${radius} ${radius} 0 0 1 ${x2} ${y2} Z`;
}

function getTextX(index) {
  const total = segments.value.length;
  const anglePerSegment = 360 / total;
  const angle = index * anglePerSegment + anglePerSegment / 2 - 90;
  const rad = (angle * Math.PI) / 180;
  return 200 + 120 * Math.cos(rad);
}

function getTextY(index) {
  const total = segments.value.length;
  const anglePerSegment = 360 / total;
  const angle = index * anglePerSegment + anglePerSegment / 2 - 90;
  const rad = (angle * Math.PI) / 180;
  return 200 + 120 * Math.sin(rad);
}

function getTextTransform(index) {
  const total = segments.value.length;
  const anglePerSegment = 360 / total;
  const angle = index * anglePerSegment + anglePerSegment / 2;
  const x = getTextX(index);
  const y = getTextY(index);
  return `rotate(${angle}, ${x}, ${y})`;
}

function getSegmentColor(multiplier) {
  if (multiplier === 0) return '#4a5568';
  if (multiplier >= 20) return '#c53030';
  if (multiplier >= 10) return '#d69e2e';
  if (multiplier >= 5) return '#d53f8c';
  if (multiplier >= 3) return '#805ad5';
  if (multiplier >= 2) return '#38a169';
  return '#667eea';
}

function getTextColor(multiplier) {
  return multiplier === 0 ? '#a0aec0' : '#ffffff';
}

function getResultClass(multiplier) {
  if (multiplier === 0) return 'loss';
  if (multiplier >= 10) return 'mega';
  if (multiplier >= 5) return 'high';
  if (multiplier >= 2) return 'medium';
  return 'low';
}

async function spin() {
  if (!canSpin.value) return;

  isSpinning.value = true;

  try {
    const response = await axios.post('/api/games/wheel/spin', {
      bet_amount: betAmount.value,
      risk_level: riskLevel.value,
      client_seed: clientSeed.value,
    });

    const result = response.data;
    
    // Find segment index
    const segmentIndex = segments.value.findIndex(s => s.multiplier === result.multiplier);
    const anglePerSegment = 360 / segments.value.length;
    const targetAngle = segmentIndex * anglePerSegment + anglePerSegment / 2;
    
    // Calculate final rotation (multiple spins + target)
    const spins = 5;
    const finalRotation = 360 * spins + (360 - targetAngle) + Math.random() * 10 - 5;
    
    // Animate rotation
    rotation.value += finalRotation;
    
    // Wait for animation
    setTimeout(() => {
      lastResult.value = {
        multiplier: result.multiplier,
        payout: result.payout,
      };

      recentSpins.value.unshift({
        id: Date.now(),
        multiplier: result.multiplier,
        payout: result.payout - betAmount.value,
      });

      if (recentSpins.value.length > 20) {
        recentSpins.value.pop();
      }

      nonce.value = result.nonce;
      walletStore.fetchBalance();
      isSpinning.value = false;

      // Auto spin
      if (isAutoRunning.value) {
        autoCurrentSpin.value++;
        
        if (autoCurrentSpin.value >= autoSpins.value) {
          stopAutoSpin();
        } else if (stopOnBigWin.value && result.multiplier >= 10) {
          stopAutoSpin();
        } else {
          setTimeout(() => spin(), 1000);
        }
      }
    }, 4000);

  } catch (error) {
    isSpinning.value = false;
    alert(error.response?.data?.message || 'Failed to spin wheel');
  }
}

function stopAutoSpin() {
  isAutoRunning.value = false;
  autoCurrentSpin.value = 0;
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
  generateSegments();
});

watch(autoMode, (newVal) => {
  if (newVal) {
    isAutoRunning.value = true;
    autoCurrentSpin.value = 0;
  } else {
    isAutoRunning.value = false;
  }
});
</script>

<style scoped>
.wheel-game {
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
  padding: 40px;
  display: flex;
  flex-direction: column;
  gap: 40px;
  align-items: center;
  justify-content: center;
}

.wheel-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 30px;
}

.wheel-container {
  position: relative;
  width: 500px;
  height: 500px;
}

.wheel-pointer {
  position: absolute;
  top: -20px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 40px;
  color: #667eea;
  z-index: 10;
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.5));
}

.wheel-svg {
  width: 100%;
  height: 100%;
  filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.3));
  transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
}

.wheel-svg.spinning {
  transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
}

.last-result {
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px 40px;
  text-align: center;
  min-width: 300px;
}

.last-result.mega {
  border-color: rgba(197, 48, 48, 0.5);
  background: rgba(197, 48, 48, 0.1);
}

.last-result.high {
  border-color: rgba(214, 158, 46, 0.5);
  background: rgba(214, 158, 46, 0.1);
}

.last-result.medium {
  border-color: rgba(72, 187, 120, 0.5);
  background: rgba(72, 187, 120, 0.1);
}

.last-result.low {
  border-color: rgba(102, 126, 234, 0.5);
  background: rgba(102, 126, 234, 0.1);
}

.last-result.loss {
  border-color: rgba(74, 85, 104, 0.5);
  background: rgba(74, 85, 104, 0.1);
}

.result-label {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 8px;
  text-transform: uppercase;
}

.result-multiplier {
  font-size: 48px;
  font-weight: 900;
  margin-bottom: 8px;
}

.result-payout {
  font-size: 24px;
  font-weight: 700;
  color: #48bb78;
}

.last-result.loss .result-payout {
  color: #fc8181;
}

.game-stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
  width: 100%;
  max-width: 900px;
}

.stat-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}

.stat-card.profit {
  border-color: rgba(72, 187, 120, 0.3);
  background: rgba(72, 187, 120, 0.1);
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
  color: white;
}

.stat-value.risk {
  color: #f6ad55;
}

.stat-value.multiplier {
  color: #667eea;
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

.risk-buttons {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.btn-risk {
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

.btn-risk:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.1);
}

.btn-risk.active {
  background: rgba(102, 126, 234, 0.2);
  border-color: rgba(102, 126, 234, 0.5);
  color: #667eea;
}

.btn-risk:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.risk-description {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.5;
  padding: 8px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 6px;
}

.btn-spin {
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

.btn-spin:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-spin:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.spinner-icon {
  animation: rotate 1s linear infinite;
}

@keyframes rotate {
  to { transform: rotate(360deg); }
}

.insufficient-balance-msg {
  text-align: center;
  color: #fc8181;
  font-size: 13px;
}

.auto-spin-section {
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

.multiplier-distribution {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 16px;
}

.multiplier-distribution h3 {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
}

.distribution-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.distribution-item {
  display: grid;
  grid-template-columns: 50px 1fr 50px;
  gap: 12px;
  align-items: center;
}

.dist-multiplier {
  font-weight: 700;
  font-size: 14px;
}

.dist-bar {
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  overflow: hidden;
}

.dist-fill {
  height: 100%;
  transition: width 0.3s;
}

.dist-percentage {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  text-align: right;
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

.recent-spins {
  grid-column: 2;
  grid-row: 2;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  max-height: 300px;
  overflow-y: auto;
}

.recent-spins h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
}

.spins-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.spin-item {
  padding: 8px 12px;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 70px;
}

.spin-item.loss {
  background: rgba(74, 85, 104, 0.2);
}

.spin-item.low {
  background: rgba(102, 126, 234, 0.2);
}

.spin-item.medium {
  background: rgba(72, 187, 120, 0.2);
}

.spin-item.high {
  background: rgba(214, 158, 46, 0.2);
}

.spin-item.mega {
  background: linear-gradient(135deg, rgba(197, 48, 48, 0.3), rgba(159, 122, 234, 0.3));
}

.spin-multiplier {
  font-weight: 700;
  font-size: 14px;
}

.spin-payout {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
}

@media (max-width: 1200px) {
  .wheel-game {
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

  .recent-spins {
    grid-column: 1;
    grid-row: 3;
  }

  .wheel-container {
    width: 400px;
    height: 400px;
  }

  .game-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
