<template>
  <div class="hilo-game">
    <!-- Game Area -->
    <div class="game-container">
      <!-- Current Card Display -->
      <div class="card-display">
        <div v-if="!gameStarted" class="start-prompt">
          <div class="prompt-icon">🎴</div>
          <h2>Ready to Play Hi-Lo?</h2>
          <p>Predict if the next card will be higher or lower!</p>
        </div>

        <div v-else class="card-container">
          <!-- Current Card -->
          <div class="playing-card" :class="[currentCard.suit, { flipping: isFlipping }]">
            <div class="card-front">
              <div class="card-rank">{{ currentCard.rank }}</div>
              <div class="card-suit" v-html="getSuitSymbol(currentCard.suit)"></div>
              <div class="card-rank-bottom">{{ currentCard.rank }}</div>
            </div>
          </div>

          <!-- Card Value -->
          <div class="card-value">
            <div class="value-label">Card Value</div>
            <div class="value-number">{{ getCardValue(currentCard.rank) }}</div>
          </div>
        </div>
      </div>

      <!-- Game Stats -->
      <div class="game-stats">
        <div class="stat-card">
          <div class="stat-label">Current Bet</div>
          <div class="stat-value">₱{{ formatMoney(betAmount) }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Win Streak</div>
          <div class="stat-value streak">{{ winStreak }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Multiplier</div>
          <div class="stat-value multiplier">{{ currentMultiplier.toFixed(2) }}×</div>
        </div>
        <div class="stat-card profit">
          <div class="stat-label">Potential Win</div>
          <div class="stat-value">₱{{ formatMoney(potentialWin) }}</div>
        </div>
      </div>

      <!-- Prediction Buttons -->
      <div v-if="gameStarted && !gameEnded" class="prediction-buttons">
        <button 
          @click="makePrediction('lower')" 
          class="btn-predict lower"
          :disabled="isPredicting"
        >
          <div class="btn-icon">📉</div>
          <div class="btn-text">LOWER</div>
          <div class="btn-chance">{{ lowerChance.toFixed(1) }}% chance</div>
        </button>

        <button 
          @click="cashOut" 
          class="btn-cashout"
          :disabled="isPredicting || winStreak === 0"
        >
          <div class="cashout-text">💰 Cash Out</div>
          <div class="cashout-amount">₱{{ formatMoney(potentialWin) }}</div>
        </button>

        <button 
          @click="makePrediction('higher')" 
          class="btn-predict higher"
          :disabled="isPredicting"
        >
          <div class="btn-icon">📈</div>
          <div class="btn-text">HIGHER</div>
          <div class="btn-chance">{{ higherChance.toFixed(1) }}% chance</div>
        </button>
      </div>

      <!-- Game Result -->
      <div v-if="gameEnded" class="game-result" :class="{ win: lastResult.won, loss: !lastResult.won }">
        <div class="result-icon">{{ lastResult.won ? '🎉' : '😔' }}</div>
        <div class="result-title">{{ lastResult.won ? 'You Won!' : 'Better Luck Next Time!' }}</div>
        <div class="result-amount">{{ lastResult.won ? '+' : '' }}₱{{ formatMoney(lastResult.payout) }}</div>
        <button @click="startNewGame" class="btn-new-game">Play Again</button>
      </div>

      <!-- Deck Progress -->
      <div v-if="gameStarted && !gameEnded" class="deck-progress">
        <div class="progress-label">Cards Remaining: {{ cardsRemaining }}</div>
        <div class="progress-bar">
          <div class="progress-fill" :style="{ width: `${(cardsRemaining / 52) * 100}%` }"></div>
        </div>
      </div>
    </div>

    <!-- Controls Sidebar -->
    <div class="controls-sidebar">
      <!-- Bet Amount -->
      <div class="control-group">
        <label class="control-label">Bet Amount</label>
        <div class="amount-input-group">
          <button @click="divideBetBy2" class="btn-adjust" :disabled="gameStarted">½</button>
          <input 
            v-model.number="betAmount" 
            type="number" 
            min="1" 
            step="1"
            class="amount-input"
            :disabled="gameStarted"
          >
          <button @click="multiplyBetBy2" class="btn-adjust" :disabled="gameStarted">2×</button>
        </div>
        <div class="quick-amounts">
          <button 
            v-for="amount in [10, 50, 100, 500]" 
            :key="amount"
            @click="betAmount = amount"
            class="btn-quick-amount"
            :disabled="gameStarted"
          >
            ₱{{ amount }}
          </button>
        </div>
      </div>

      <!-- Start Button -->
      <button 
        v-if="!gameStarted"
        @click="startGame" 
        class="btn-start"
        :disabled="!canStart"
      >
        <span v-if="isStarting" class="spinner"></span>
        <span v-else>🎲 Start Game</span>
      </button>

      <div v-if="!canStart && !gameStarted" class="insufficient-balance-msg">
        Insufficient balance
      </div>

      <!-- How to Play -->
      <div class="how-to-play">
        <button @click="showHowToPlay = !showHowToPlay" class="btn-section-toggle">
          <span>📖 How to Play</span>
          <span>{{ showHowToPlay ? '▼' : '▶' }}</span>
        </button>
        
        <div v-if="showHowToPlay" class="section-content">
          <ol class="how-to-list">
            <li>Place your bet and start the game</li>
            <li>A card will be drawn from a standard 52-card deck</li>
            <li>Predict if the next card will be Higher or Lower</li>
            <li>Each correct prediction multiplies your win by 1.5×</li>
            <li>Cash out anytime or continue your streak</li>
            <li>Game ends if prediction is wrong or deck runs out</li>
          </ol>
          <div class="card-values-ref">
            <h4>Card Values:</h4>
            <p>2 = 2, 3 = 3, ..., 10 = 10, J = 11, Q = 12, K = 13, A = 14</p>
          </div>
        </div>
      </div>

      <!-- Multiplier Table -->
      <div class="multiplier-table">
        <h3>Progressive Multipliers</h3>
        <div class="multiplier-list">
          <div 
            v-for="streak in 10" 
            :key="streak"
            class="multiplier-item"
            :class="{ active: winStreak === streak, passed: winStreak > streak }"
          >
            <span class="streak-num">{{ streak }} win{{ streak > 1 ? 's' : '' }}</span>
            <span class="streak-mult">{{ (Math.pow(1.5, streak)).toFixed(2) }}×</span>
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
                <button @click="regenerateClientSeed" class="btn-regenerate" :disabled="gameStarted">🔄</button>
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
            Deck shuffle is determined before game starts using cryptographic seeds.
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
            <div class="game-streak">{{ game.streak }} win{{ game.streak !== 1 ? 's' : '' }}</div>
            <div class="game-multiplier">{{ game.multiplier.toFixed(2) }}×</div>
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
const gameStarted = ref(false);
const gameEnded = ref(false);
const isStarting = ref(false);
const isPredicting = ref(false);
const isFlipping = ref(false);

const currentCard = ref({ rank: 'A', suit: 'spades' });
const winStreak = ref(0);
const currentMultiplier = ref(1);
const cardsRemaining = ref(52);
const gameId = ref(null);

const lastResult = ref(null);
const recentGames = ref([]);

// UI State
const showHowToPlay = ref(false);
const showProvablyFair = ref(false);

// Provably Fair
const clientSeed = ref('');
const serverSeedHash = ref('');
const nonce = ref(0);

// Computed
const potentialWin = computed(() => {
  return betAmount.value * currentMultiplier.value;
});

const canStart = computed(() => {
  return walletStore.balance.real_balance >= betAmount.value && !gameStarted.value;
});

const higherChance = computed(() => {
  const currentValue = getCardValue(currentCard.value.rank);
  const remainingHigher = Math.max(0, (14 - currentValue) * 4);
  return (remainingHigher / cardsRemaining.value) * 100;
});

const lowerChance = computed(() => {
  const currentValue = getCardValue(currentCard.value.rank);
  const remainingLower = Math.max(0, (currentValue - 2) * 4);
  return (remainingLower / cardsRemaining.value) * 100;
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

function getCardValue(rank) {
  const values = {
    '2': 2, '3': 3, '4': 4, '5': 5, '6': 6,
    '7': 7, '8': 8, '9': 9, '10': 10,
    'J': 11, 'Q': 12, 'K': 13, 'A': 14
  };
  return values[rank] || 0;
}

function getSuitSymbol(suit) {
  const symbols = {
    hearts: '♥',
    diamonds: '♦',
    clubs: '♣',
    spades: '♠'
  };
  return symbols[suit] || '';
}

async function startGame() {
  if (!canStart.value) return;

  isStarting.value = true;

  try {
    const response = await axios.post('/api/games/hilo/start', {
      bet_amount: betAmount.value,
      client_seed: clientSeed.value,
    });

    gameId.value = response.data.game_id;
    currentCard.value = response.data.current_card;
    cardsRemaining.value = response.data.cards_remaining;
    nonce.value = response.data.nonce;

    gameStarted.value = true;
    gameEnded.value = false;
    winStreak.value = 0;
    currentMultiplier.value = 1;
    lastResult.value = null;

    walletStore.fetchBalance();

  } catch (error) {
    alert(error.response?.data?.message || 'Failed to start game');
  } finally {
    isStarting.value = false;
  }
}

async function makePrediction(prediction) {
  if (isPredicting.value) return;

  isPredicting.value = true;
  isFlipping.value = true;

  try {
    const response = await axios.post('/api/games/hilo/predict', {
      game_id: gameId.value,
      prediction: prediction,
    });

    // Wait for card flip animation
    setTimeout(() => {
      const result = response.data;
      
      currentCard.value = result.next_card;
      cardsRemaining.value = result.cards_remaining;
      
      if (result.won) {
        winStreak.value++;
        currentMultiplier.value = Math.pow(1.5, winStreak.value);
      } else {
        endGame(false, result.payout);
      }

      isFlipping.value = false;
      isPredicting.value = false;
    }, 600);

  } catch (error) {
    isFlipping.value = false;
    isPredicting.value = false;
    alert(error.response?.data?.message || 'Failed to make prediction');
  }
}

async function cashOut() {
  if (isPredicting.value) return;

  isPredicting.value = true;

  try {
    const response = await axios.post('/api/games/hilo/cashout', {
      game_id: gameId.value,
    });

    endGame(true, response.data.payout);
    walletStore.fetchBalance();

  } catch (error) {
    alert(error.response?.data?.message || 'Failed to cash out');
  } finally {
    isPredicting.value = false;
  }
}

function endGame(won, payout) {
  gameEnded.value = true;
  
  lastResult.value = {
    won: won,
    payout: payout,
    streak: winStreak.value,
    multiplier: currentMultiplier.value,
  };

  recentGames.value.unshift({
    id: Date.now(),
    won: won,
    payout: payout,
    streak: winStreak.value,
    multiplier: currentMultiplier.value,
  });

  if (recentGames.value.length > 10) {
    recentGames.value.pop();
  }

  walletStore.fetchBalance();
}

function startNewGame() {
  gameStarted.value = false;
  gameEnded.value = false;
  winStreak.value = 0;
  currentMultiplier.value = 1;
  cardsRemaining.value = 52;
  gameId.value = null;
  lastResult.value = null;
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
.hilo-game {
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
  gap: 30px;
  align-items: center;
  justify-content: center;
}

.card-display {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.start-prompt {
  text-align: center;
}

.prompt-icon {
  font-size: 80px;
  margin-bottom: 20px;
}

.start-prompt h2 {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 12px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.start-prompt p {
  font-size: 18px;
  color: rgba(255, 255, 255, 0.7);
}

.card-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 30px;
}

.playing-card {
  width: 280px;
  height: 400px;
  background: white;
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  transition: transform 0.3s;
}

.playing-card:hover {
  transform: translateY(-10px);
}

.playing-card.flipping {
  animation: cardFlip 0.6s ease-in-out;
}

@keyframes cardFlip {
  0% { transform: rotateY(0deg); }
  50% { transform: rotateY(90deg); }
  100% { transform: rotateY(0deg); }
}

.playing-card.hearts,
.playing-card.diamonds {
  color: #e53e3e;
}

.playing-card.clubs,
.playing-card.spades {
  color: #2d3748;
}

.card-front {
  padding: 30px;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.card-rank {
  font-size: 48px;
  font-weight: 800;
  align-self: flex-start;
}

.card-suit {
  font-size: 120px;
  text-align: center;
}

.card-rank-bottom {
  font-size: 48px;
  font-weight: 800;
  align-self: flex-end;
  transform: rotate(180deg);
}

.card-value {
  text-align: center;
}

.value-label {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 8px;
  text-transform: uppercase;
}

.value-number {
  font-size: 36px;
  font-weight: 800;
  color: #667eea;
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

.stat-value.streak {
  color: #f6ad55;
}

.stat-value.multiplier {
  color: #667eea;
}

.prediction-buttons {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 20px;
  width: 100%;
  max-width: 900px;
}

.btn-predict {
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 30px;
  color: white;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  transition: all 0.3s;
}

.btn-predict:hover:not(:disabled) {
  transform: translateY(-5px);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.btn-predict.lower {
  border-color: rgba(237, 137, 54, 0.5);
}

.btn-predict.lower:hover:not(:disabled) {
  background: rgba(237, 137, 54, 0.1);
  border-color: rgba(237, 137, 54, 0.8);
}

.btn-predict.higher {
  border-color: rgba(72, 187, 120, 0.5);
}

.btn-predict.higher:hover:not(:disabled) {
  background: rgba(72, 187, 120, 0.1);
  border-color: rgba(72, 187, 120, 0.8);
}

.btn-predict:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-icon {
  font-size: 40px;
}

.btn-text {
  font-size: 24px;
  font-weight: 800;
}

.btn-chance {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
}

.btn-cashout {
  background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
  border: none;
  border-radius: 16px;
  padding: 30px 40px;
  color: white;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  transition: all 0.3s;
}

.btn-cashout:hover:not(:disabled) {
  transform: translateY(-5px);
  box-shadow: 0 10px 30px rgba(72, 187, 120, 0.4);
}

.btn-cashout:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.cashout-text {
  font-size: 20px;
  font-weight: 800;
}

.cashout-amount {
  font-size: 28px;
  font-weight: 900;
}

.game-result {
  text-align: center;
  padding: 40px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 16px;
  border: 2px solid rgba(255, 255, 255, 0.1);
}

.game-result.win {
  border-color: rgba(72, 187, 120, 0.5);
  background: rgba(72, 187, 120, 0.1);
}

.game-result.loss {
  border-color: rgba(252, 129, 129, 0.5);
  background: rgba(252, 129, 129, 0.1);
}

.result-icon {
  font-size: 64px;
  margin-bottom: 20px;
}

.result-title {
  font-size: 28px;
  font-weight: 800;
  margin-bottom: 12px;
}

.result-amount {
  font-size: 36px;
  font-weight: 900;
  color: #48bb78;
  margin-bottom: 24px;
}

.game-result.loss .result-amount {
  color: #fc8181;
}

.btn-new-game {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 12px;
  padding: 16px 40px;
  color: white;
  font-size: 18px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-new-game:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.deck-progress {
  width: 100%;
  max-width: 600px;
}

.progress-label {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 8px;
  text-align: center;
}

.progress-bar {
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  transition: width 0.3s;
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

.btn-start {
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

.btn-start:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-start:disabled {
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

.insufficient-balance-msg {
  text-align: center;
  color: #fc8181;
  font-size: 13px;
}

.how-to-play,
.multiplier-table,
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

.card-values-ref {
  background: rgba(255, 255, 255, 0.05);
  padding: 12px;
  border-radius: 8px;
}

.card-values-ref h4 {
  font-size: 13px;
  margin-bottom: 6px;
  color: rgba(255, 255, 255, 0.9);
}

.card-values-ref p {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.5;
}

.multiplier-table h3 {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
}

.multiplier-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 300px;
  overflow-y: auto;
}

.multiplier-item {
  display: flex;
  justify-content: space-between;
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  font-size: 14px;
}

.multiplier-item.active {
  background: rgba(102, 126, 234, 0.2);
  border: 1px solid rgba(102, 126, 234, 0.5);
}

.multiplier-item.passed {
  opacity: 0.5;
}

.streak-mult {
  font-weight: 700;
  color: #667eea;
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

.game-streak {
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
  .hilo-game {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
    height: auto;
  }

  .game-container {
    grid-column: 1;
    grid-row: 1;
    min-height: 600px;
  }

  .controls-sidebar {
    grid-column: 1;
    grid-row: 2;
  }

  .recent-games {
    grid-column: 1;
    grid-row: 3;
  }

  .game-stats {
    grid-template-columns: repeat(2, 1fr);
  }

  .prediction-buttons {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
  }
}
</style>
