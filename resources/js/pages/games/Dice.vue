<template>
  <div class="dice-game">
    <!-- Game Canvas -->
    <div class="game-container">
      <div class="dice-display">
        <div class="dice-result" :class="{ rolling: isRolling, win: lastResult?.won, loss: lastResult && !lastResult.won }">
          <div class="dice-number">{{ displayNumber }}</div>
          <div class="dice-label">{{ isRolling ? 'Rolling...' : 'Result' }}</div>
        </div>

        <div v-if="lastResult" class="result-info">
          <div class="prediction-display">
            <span class="prediction-label">{{ lastResult.prediction === 'over' ? 'Roll Over' : 'Roll Under' }}</span>
            <span class="prediction-value">{{ lastResult.target_number }}</span>
          </div>
          <div class="result-status" :class="{ win: lastResult.won, loss: !lastResult.won }">
            {{ lastResult.won ? `Won ₱${formatMoney(lastResult.payout)}` : 'Lost' }}
          </div>
        </div>

        <!-- Number Line Visualization -->
        <div class="number-line">
          <div class="number-line-track">
            <div 
              v-if="prediction === 'under'" 
              class="win-zone under" 
              :style="{ width: `${(targetNumber / 100) * 100}%` }"
            ></div>
            <div 
              v-if="prediction === 'over'" 
              class="win-zone over" 
              :style="{ width: `${((100 - targetNumber) / 100) * 100}%`, left: `${(targetNumber / 100) * 100}%` }"
            ></div>
            <div 
              v-if="lastResult" 
              class="result-marker" 
              :style="{ left: `${lastResult.result}%` }"
            ></div>
          </div>
          <div class="number-line-labels">
            <span>0</span>
            <span>{{ targetNumber }}</span>
            <span>100</span>
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
          <button @click="divideBetBy2" class="btn-adjust">½</button>
          <input 
            v-model.number="betAmount" 
            type="number" 
            min="1" 
            step="1"
            class="amount-input"
            :disabled="isRolling"
          >
          <button @click="multiplyBetBy2" class="btn-adjust">2×</button>
        </div>
        <div class="quick-amounts">
          <button 
            v-for="amount in [10, 50, 100, 500]" 
            :key="amount"
            @click="betAmount = amount"
            class="btn-quick-amount"
            :disabled="isRolling"
          >
            ₱{{ amount }}
          </button>
        </div>
      </div>

      <!-- Prediction Type -->
      <div class="control-group">
        <label class="control-label">Prediction</label>
        <div class="prediction-buttons">
          <button 
            @click="prediction = 'under'" 
            class="btn-prediction" 
            :class="{ active: prediction === 'under' }"
            :disabled="isRolling"
          >
            <span class="prediction-icon">⬇️</span>
            Roll Under
          </button>
          <button 
            @click="prediction = 'over'" 
            class="btn-prediction" 
            :class="{ active: prediction === 'over' }"
            :disabled="isRolling"
          >
            <span class="prediction-icon">⬆️</span>
            Roll Over
          </button>
        </div>
      </div>

      <!-- Target Number -->
      <div class="control-group">
        <label class="control-label">
          Target Number: <span class="target-value">{{ targetNumber }}</span>
        </label>
        <input 
          v-model.number="targetNumber" 
          type="range" 
          min="2" 
          max="98" 
          step="1"
          class="target-slider"
          :disabled="isRolling"
        >
        <div class="slider-labels">
          <span>2</span>
          <span>50</span>
          <span>98</span>
        </div>
      </div>

      <!-- Win Chance & Multiplier -->
      <div class="stats-row">
        <div class="stat-box">
          <div class="stat-label">Win Chance</div>
          <div class="stat-value">{{ winChance }}%</div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Multiplier</div>
          <div class="stat-value">{{ multiplier }}×</div>
        </div>
        <div class="stat-box">
          <div class="stat-label">Potential Win</div>
          <div class="stat-value">₱{{ formatMoney(potentialWin) }}</div>
        </div>
      </div>

      <!-- Roll Button -->
      <button 
        @click="rollDice" 
        class="btn-roll"
        :disabled="isRolling || !canPlay"
        :class="{ rolling: isRolling }"
      >
        <span v-if="isRolling" class="spinner"></span>
        <span v-else>🎲 {{ isRolling ? 'Rolling...' : 'Roll Dice' }}</span>
      </button>

      <div v-if="!canPlay" class="insufficient-balance-msg">
        Insufficient balance
      </div>

      <!-- Auto Bet Options -->
      <div class="auto-bet-section">
        <div class="section-header">
          <label class="control-label">Auto Bet</label>
          <label class="toggle-switch">
            <input v-model="autoMode" type="checkbox" :disabled="isRolling">
            <span class="toggle-slider"></span>
          </label>
        </div>

        <div v-if="autoMode" class="auto-options">
          <div class="auto-option">
            <label>Number of Rolls</label>
            <input v-model.number="autoRolls" type="number" min="1" max="1000" class="auto-input">
          </div>
          <div class="auto-option">
            <label>Stop on Win</label>
            <input v-model="stopOnWin" type="checkbox">
          </div>
          <div class="auto-option">
            <label>Stop on Loss</label>
            <input v-model="stopOnLoss" type="checkbox">
          </div>

          <button 
            @click="autoMode ? stopAutoBet() : startAutoBet()" 
            class="btn-auto"
            :class="{ active: isAutoRunning }"
          >
            {{ isAutoRunning ? 'Stop Auto Bet' : 'Start Auto Bet' }}
          </button>
          <div v-if="isAutoRunning" class="auto-progress">
            Rolls: {{ autoCurrentRoll }} / {{ autoRolls }}
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
              <input v-model="serverSeedHash" type="text" class="seed-input" readonly>
            </div>
            <div class="seed-item">
              <label>Nonce</label>
              <input :value="nonce" type="text" class="seed-input" readonly>
            </div>
          </div>
          <p class="provably-fair-note">
            Each bet is provably fair using cryptographic hashing. You can verify the fairness after each roll.
          </p>
        </div>
      </div>
    </div>

    <!-- Recent Bets -->
    <div class="recent-bets">
      <h3>Recent Rolls</h3>
      <div class="bets-list">
        <div v-for="bet in recentBets" :key="bet.id" class="bet-item" :class="{ win: bet.won, loss: !bet.won }">
          <div class="bet-result">{{ bet.result }}</div>
          <div class="bet-info">
            <span>{{ bet.prediction === 'over' ? '⬆️' : '⬇️' }} {{ bet.target_number }}</span>
            <span class="bet-amount">₱{{ formatMoney(bet.bet_amount) }}</span>
          </div>
          <div class="bet-payout" :class="{ win: bet.won }">
            {{ bet.won ? `+₱${formatMoney(bet.payout)}` : '-' }}
          </div>
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
const prediction = ref('over');
const targetNumber = ref(50);
const isRolling = ref(false);
const displayNumber = ref(50);
const lastResult = ref(null);
const recentBets = ref([]);

// Auto Bet State
const autoMode = ref(false);
const autoRolls = ref(10);
const stopOnWin = ref(false);
const stopOnLoss = ref(false);
const isAutoRunning = ref(false);
const autoCurrentRoll = ref(0);
let autoIntervalId = null;

// Provably Fair
const showProvablyFair = ref(false);
const clientSeed = ref('');
const serverSeedHash = ref('');
const nonce = ref(0);

// Computed
const winChance = computed(() => {
  if (prediction.value === 'over') {
    return 100 - targetNumber.value;
  } else {
    return targetNumber.value;
  }
});

const multiplier = computed(() => {
  const chance = winChance.value;
  if (chance <= 0) return 0;
  const houseEdge = 0.01; // 1% house edge
  return Number(((99 / chance) * (1 - houseEdge)).toFixed(2));
});

const potentialWin = computed(() => {
  return betAmount.value * multiplier.value;
});

const canPlay = computed(() => {
  return walletStore.balance.real_balance >= betAmount.value;
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

async function rollDice() {
  if (isRolling.value || !canPlay.value) return;

  isRolling.value = true;
  
  // Animate rolling
  const rollInterval = setInterval(() => {
    displayNumber.value = Math.floor(Math.random() * 100) + 1;
  }, 50);

  try {
    const response = await axios.post('/api/games/dice/play', {
      bet_amount: betAmount.value,
      prediction: prediction.value,
      target_number: targetNumber.value,
      client_seed: clientSeed.value,
    });

    const result = response.data;
    
    // Stop animation and show result
    setTimeout(() => {
      clearInterval(rollInterval);
      displayNumber.value = result.result;
      lastResult.value = result;
      
      // Add to recent bets
      recentBets.value.unshift(result);
      if (recentBets.value.length > 10) {
        recentBets.value.pop();
      }

      // Update nonce
      nonce.value = result.nonce;

      // Update balance
      walletStore.fetchBalance();

      isRolling.value = false;

      // Handle auto bet
      if (isAutoRunning.value) {
        autoCurrentRoll.value++;
        
        if (autoCurrentRoll.value >= autoRolls.value) {
          stopAutoBet();
        } else if (stopOnWin.value && result.won) {
          stopAutoBet();
        } else if (stopOnLoss.value && !result.won) {
          stopAutoBet();
        } else {
          // Continue auto betting
          setTimeout(() => rollDice(), 1000);
        }
      }
    }, 1500);

  } catch (error) {
    clearInterval(rollInterval);
    isRolling.value = false;
    alert(error.response?.data?.message || 'Failed to roll dice');
  }
}

function startAutoBet() {
  if (!canPlay.value) {
    alert('Insufficient balance');
    return;
  }

  isAutoRunning.value = true;
  autoCurrentRoll.value = 0;
  rollDice();
}

function stopAutoBet() {
  isAutoRunning.value = false;
  autoCurrentRoll.value = 0;
  if (autoIntervalId) {
    clearInterval(autoIntervalId);
    autoIntervalId = null;
  }
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

// Watch for auto mode changes
watch(autoMode, (newVal) => {
  if (!newVal && isAutoRunning.value) {
    stopAutoBet();
  }
});
</script>

<style scoped>
.dice-game {
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
  align-items: center;
  justify-content: center;
}

.dice-display {
  width: 100%;
  max-width: 600px;
}

.dice-result {
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  padding: 60px;
  text-align: center;
  margin-bottom: 30px;
  transition: all 0.3s;
}

.dice-result.rolling {
  border-color: rgba(102, 126, 234, 0.5);
  box-shadow: 0 0 30px rgba(102, 126, 234, 0.3);
  animation: pulse 0.5s infinite;
}

.dice-result.win {
  border-color: rgba(72, 187, 120, 0.5);
  box-shadow: 0 0 30px rgba(72, 187, 120, 0.3);
}

.dice-result.loss {
  border-color: rgba(252, 129, 129, 0.5);
  box-shadow: 0 0 30px rgba(252, 129, 129, 0.3);
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.02); }
}

.dice-number {
  font-size: 120px;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1;
  margin-bottom: 12px;
}

.dice-label {
  font-size: 18px;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 2px;
}

.result-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  margin-bottom: 30px;
}

.prediction-display {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.prediction-label {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
}

.prediction-value {
  font-size: 24px;
  font-weight: 700;
  color: #667eea;
}

.result-status {
  font-size: 20px;
  font-weight: 700;
  padding: 8px 20px;
  border-radius: 12px;
}

.result-status.win {
  background: rgba(72, 187, 120, 0.2);
  color: #48bb78;
}

.result-status.loss {
  background: rgba(252, 129, 129, 0.2);
  color: #fc8181;
}

.number-line {
  margin-bottom: 20px;
}

.number-line-track {
  height: 40px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 20px;
  position: relative;
  overflow: hidden;
  margin-bottom: 8px;
}

.win-zone {
  position: absolute;
  height: 100%;
  top: 0;
  transition: all 0.3s;
}

.win-zone.under {
  left: 0;
  background: linear-gradient(90deg, rgba(72, 187, 120, 0.3), rgba(72, 187, 120, 0.1));
  border-right: 2px solid rgba(72, 187, 120, 0.5);
}

.win-zone.over {
  background: linear-gradient(90deg, rgba(72, 187, 120, 0.1), rgba(72, 187, 120, 0.3));
  border-left: 2px solid rgba(72, 187, 120, 0.5);
}

.result-marker {
  position: absolute;
  top: 0;
  width: 4px;
  height: 100%;
  background: white;
  box-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
  transform: translateX(-2px);
  animation: markerBounce 0.5s;
}

@keyframes markerBounce {
  0% { transform: translateX(-2px) scaleY(0); }
  50% { transform: translateX(-2px) scaleY(1.2); }
  100% { transform: translateX(-2px) scaleY(1); }
}

.number-line-labels {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
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

.target-value {
  color: #667eea;
  font-size: 16px;
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

.prediction-buttons {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.btn-prediction {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 12px;
  color: white;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.btn-prediction:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.1);
}

.btn-prediction.active {
  background: rgba(102, 126, 234, 0.2);
  border-color: rgba(102, 126, 234, 0.5);
}

.prediction-icon {
  font-size: 20px;
}

.target-slider {
  width: 100%;
  height: 8px;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.1);
  outline: none;
  -webkit-appearance: none;
}

.target-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  cursor: pointer;
}

.slider-labels {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
}

.stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.stat-box {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 12px;
  text-align: center;
}

.stat-label {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 4px;
  text-transform: uppercase;
}

.stat-value {
  font-size: 16px;
  font-weight: 700;
  color: #667eea;
}

.btn-roll {
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

.btn-roll:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-roll:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-roll.rolling {
  animation: pulse 0.5s infinite;
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

.btn-auto {
  background: rgba(102, 126, 234, 0.2);
  border: 1px solid rgba(102, 126, 234, 0.3);
  border-radius: 8px;
  padding: 12px;
  color: white;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-auto.active {
  background: rgba(252, 129, 129, 0.2);
  border-color: rgba(252, 129, 129, 0.3);
  color: #fc8181;
}

.auto-progress {
  text-align: center;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
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

.recent-bets {
  grid-column: 2;
  grid-row: 2;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  max-height: 300px;
  overflow-y: auto;
}

.recent-bets h3 {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 12px;
}

.bets-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.bet-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
}

.bet-item.win {
  border-color: rgba(72, 187, 120, 0.3);
}

.bet-item.loss {
  border-color: rgba(252, 129, 129, 0.3);
}

.bet-result {
  width: 48px;
  height: 48px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  color: #667eea;
}

.bet-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.bet-amount {
  color: rgba(255, 255, 255, 0.6);
}

.bet-payout {
  font-weight: 700;
  color: #fc8181;
}

.bet-payout.win {
  color: #48bb78;
}

@media (max-width: 1200px) {
  .dice-game {
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

  .recent-bets {
    grid-column: 1;
    grid-row: 3;
  }
}
</style>
