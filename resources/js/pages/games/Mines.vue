<template>
  <div class="mines-game">
    <!-- Game Canvas -->
    <div class="game-container">
      <div class="mines-display">
        <!-- Game Stats -->
        <div class="game-stats">
          <div class="stat-item">
            <div class="stat-label">Bet Amount</div>
            <div class="stat-value">₱{{ formatMoney(currentBet) }}</div>
          </div>
          <div class="stat-item">
            <div class="stat-label">Mines</div>
            <div class="stat-value">💣 {{ mineCount }}</div>
          </div>
          <div class="stat-item">
            <div class="stat-label">Gems Found</div>
            <div class="stat-value">💎 {{ gemsFound }}</div>
          </div>
          <div class="stat-item highlight">
            <div class="stat-label">Current Multiplier</div>
            <div class="stat-value multiplier">{{ currentMultiplier }}×</div>
          </div>
          <div class="stat-item highlight">
            <div class="stat-label">Potential Win</div>
            <div class="stat-value profit">₱{{ formatMoney(potentialWin) }}</div>
          </div>
        </div>

        <!-- Mines Grid -->
        <div class="mines-grid">
          <button
            v-for="(tile, index) in tiles"
            :key="index"
            @click="revealTile(index)"
            class="mine-tile"
            :class="{
              revealed: tile.revealed,
              gem: tile.revealed && tile.type === 'gem',
              mine: tile.revealed && tile.type === 'mine',
              disabled: !gameActive || tile.revealed
            }"
            :disabled="!gameActive || tile.revealed"
          >
            <span v-if="!tile.revealed" class="tile-number">{{ index + 1 }}</span>
            <span v-else-if="tile.type === 'gem'" class="tile-icon gem-icon">💎</span>
            <span v-else-if="tile.type === 'mine'" class="tile-icon mine-icon">💣</span>
          </button>
        </div>

        <!-- Game Status Message -->
        <div v-if="gameResult" class="game-result" :class="gameResult.type">
          <div class="result-icon">{{ gameResult.type === 'win' ? '🎉' : '💥' }}</div>
          <div class="result-text">{{ gameResult.message }}</div>
          <div class="result-amount">{{ gameResult.amount }}</div>
        </div>
      </div>
    </div>

    <!-- Controls Sidebar -->
    <div class="controls-sidebar">
      <!-- Bet Amount -->
      <div class="control-group" v-if="!gameActive">
        <label class="control-label">Bet Amount</label>
        <div class="amount-input-group">
          <button @click="divideBetBy2" class="btn-adjust">½</button>
          <input 
            v-model.number="betAmount" 
            type="number" 
            min="1" 
            step="1"
            class="amount-input"
          >
          <button @click="multiplyBetBy2" class="btn-adjust">2×</button>
        </div>
        <div class="quick-amounts">
          <button 
            v-for="amount in [10, 50, 100, 500]" 
            :key="amount"
            @click="betAmount = amount"
            class="btn-quick-amount"
          >
            ₱{{ amount }}
          </button>
        </div>
      </div>

      <!-- Mine Count Selection -->
      <div class="control-group" v-if="!gameActive">
        <label class="control-label">Number of Mines</label>
        <div class="mines-selector">
          <button
            v-for="count in [3, 5, 7, 10, 15, 20]"
            :key="count"
            @click="mineCount = count"
            class="btn-mine-count"
            :class="{ active: mineCount === count }"
          >
            {{ count }}
          </button>
        </div>
      </div>

      <!-- Start/Cashout Button -->
      <button 
        v-if="!gameActive"
        @click="startGame" 
        class="btn-start"
        :disabled="!canStart"
      >
        🎮 Start Game
      </button>
      <button 
        v-else
        @click="cashout" 
        class="btn-cashout"
        :disabled="gemsFound === 0"
      >
        💰 Cash Out {{ currentMultiplier }}×
      </button>

      <div v-if="!canStart" class="insufficient-balance-msg">
        Insufficient balance
      </div>

      <!-- Multiplier Table -->
      <div class="multipliers-section">
        <h3>Multiplier Table</h3>
        <div class="multipliers-list">
          <div 
            v-for="(mult, index) in multiplierTable" 
            :key="index"
            class="multiplier-row"
            :class="{ 
              current: index === gemsFound - 1,
              passed: index < gemsFound 
            }"
          >
            <span class="gems-count">{{ index + 1 }} Gems</span>
            <span class="multiplier-value">{{ mult }}×</span>
          </div>
        </div>
      </div>

      <!-- Game Rules -->
      <div class="rules-section">
        <button @click="showRules = !showRules" class="btn-toggle-rules">
          <span>📖 How to Play</span>
          <span>{{ showRules ? '▼' : '▶' }}</span>
        </button>
        
        <div v-if="showRules" class="rules-content">
          <ul>
            <li>Select bet amount and number of mines</li>
            <li>Click tiles to reveal gems (💎)</li>
            <li>Each gem increases your multiplier</li>
            <li>Hit a mine (💣) and you lose your bet</li>
            <li>Cash out anytime to secure your winnings</li>
            <li>More mines = higher multipliers but riskier</li>
          </ul>
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
            Mine positions are determined before the game starts using cryptographic hashing.
          </p>
        </div>
      </div>
    </div>

    <!-- Recent Games -->
    <div class="recent-games">
      <h3>Recent Games</h3>
      <div class="games-list">
        <div v-for="game in recentGames" :key="game.id" class="game-item" :class="{ win: game.won }">
          <div class="game-info">
            <span>💣 {{ game.mine_count }} mines</span>
            <span>💎 {{ game.gems_found }} gems</span>
          </div>
          <div class="game-result">
            <span class="game-multiplier">{{ game.multiplier }}×</span>
            <span class="game-payout" :class="{ win: game.won }">
              {{ game.won ? `+₱${formatMoney(game.payout)}` : '-₱' + formatMoney(game.bet_amount) }}
            </span>
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
const mineCount = ref(3);
const gameActive = ref(false);
const currentBet = ref(0);
const gemsFound = ref(0);
const tiles = ref([]);
const gameResult = ref(null);
const recentGames = ref([]);

// Provably Fair
const showProvablyFair = ref(false);
const showRules = ref(false);
const clientSeed = ref('');
const serverSeedHash = ref('');
const nonce = ref(0);

// Game Session
let gameId = null;
let minePositions = [];

// Computed
const canStart = computed(() => {
  return walletStore.balance.real_balance >= betAmount.value && !gameActive.value;
});

const currentMultiplier = computed(() => {
  if (gemsFound.value === 0) return 1.00;
  return multiplierTable.value[gemsFound.value - 1] || 1.00;
});

const potentialWin = computed(() => {
  return currentBet.value * currentMultiplier.value;
});

const multiplierTable = computed(() => {
  // Calculate multipliers based on mine count
  const totalTiles = 25;
  const safeTiles = totalTiles - mineCount.value;
  const multipliers = [];
  
  for (let i = 1; i <= safeTiles; i++) {
    const remaining = safeTiles - i + 1;
    const mines = mineCount.value;
    const houseEdge = 0.01;
    
    // Probability-based multiplier calculation
    const prob = remaining / (totalTiles - i + 1);
    const mult = (1 / prob) * (1 - houseEdge);
    
    // Cumulative multiplier
    if (i === 1) {
      multipliers.push(parseFloat(mult.toFixed(2)));
    } else {
      multipliers.push(parseFloat((multipliers[i - 2] * mult).toFixed(2)));
    }
  }
  
  return multipliers;
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

function initializeGrid() {
  tiles.value = Array.from({ length: 25 }, () => ({
    revealed: false,
    type: null,
  }));
}

async function startGame() {
  if (!canStart.value) return;

  gameResult.value = null;
  gemsFound.value = 0;
  currentBet.value = betAmount.value;
  initializeGrid();

  try {
    const response = await axios.post('/api/games/mines/start', {
      bet_amount: betAmount.value,
      mine_count: mineCount.value,
      client_seed: clientSeed.value,
    });

    gameId = response.data.game_id;
    gameActive.value = true;
    minePositions = response.data.mine_positions || [];
    
    walletStore.fetchBalance();

  } catch (error) {
    alert(error.response?.data?.message || 'Failed to start game');
  }
}

async function revealTile(index) {
  if (!gameActive.value || tiles.value[index].revealed) return;

  try {
    const response = await axios.post('/api/games/mines/reveal', {
      game_id: gameId,
      tile_index: index,
    });

    tiles.value[index].revealed = true;
    tiles.value[index].type = response.data.tile_type;

    if (response.data.tile_type === 'gem') {
      gemsFound.value++;
      nonce.value++;
      
      // Check if all safe tiles revealed (auto win)
      if (gemsFound.value === 25 - mineCount.value) {
        await cashout();
      }
    } else {
      // Hit a mine
      gameActive.value = false;
      revealAllMines();
      
      gameResult.value = {
        type: 'loss',
        message: 'Hit a Mine!',
        amount: `-₱${formatMoney(currentBet.value)}`,
      };

      // Add to recent games
      recentGames.value.unshift({
        id: Date.now(),
        mine_count: mineCount.value,
        gems_found: gemsFound.value,
        multiplier: currentMultiplier.value,
        bet_amount: currentBet.value,
        payout: 0,
        won: false,
      });

      setTimeout(() => {
        gameResult.value = null;
      }, 5000);
    }

  } catch (error) {
    alert(error.response?.data?.message || 'Failed to reveal tile');
  }
}

async function cashout() {
  if (!gameActive.value || gemsFound.value === 0) return;

  try {
    const response = await axios.post('/api/games/mines/cashout', {
      game_id: gameId,
    });

    gameActive.value = false;
    const winnings = response.data.payout;

    gameResult.value = {
      type: 'win',
      message: 'Cashed Out Successfully!',
      amount: `+₱${formatMoney(winnings)}`,
    };

    // Add to recent games
    recentGames.value.unshift({
      id: Date.now(),
      mine_count: mineCount.value,
      gems_found: gemsFound.value,
      multiplier: currentMultiplier.value,
      bet_amount: currentBet.value,
      payout: winnings,
      won: true,
    });

    revealAllMines();
    walletStore.fetchBalance();

    setTimeout(() => {
      gameResult.value = null;
    }, 5000);

  } catch (error) {
    alert(error.response?.data?.message || 'Failed to cash out');
  }
}

function revealAllMines() {
  // Reveal all remaining tiles
  tiles.value.forEach((tile, index) => {
    if (!tile.revealed) {
      tile.revealed = true;
      // In a real game, this info would come from backend
      tile.type = Math.random() < (mineCount.value / 25) ? 'mine' : 'gem';
    }
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
  initializeGrid();
});
</script>

<style scoped>
.mines-game {
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
  align-items: center;
  justify-content: center;
}

.mines-display {
  width: 100%;
  max-width: 650px;
}

.game-stats {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 12px;
  margin-bottom: 30px;
}

.stat-item {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
  text-align: center;
}

.stat-item.highlight {
  background: rgba(102, 126, 234, 0.1);
  border-color: rgba(102, 126, 234, 0.3);
}

.stat-label {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-value {
  font-size: 18px;
  font-weight: 700;
  color: white;
}

.stat-value.multiplier {
  color: #667eea;
  font-size: 24px;
}

.stat-value.profit {
  color: #48bb78;
  font-size: 20px;
}

.mines-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px;
  margin-bottom: 20px;
}

.mine-tile {
  aspect-ratio: 1;
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  cursor: pointer;
  font-size: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  position: relative;
  overflow: hidden;
}

.mine-tile:hover:not(:disabled):not(.revealed) {
  background: rgba(102, 126, 234, 0.2);
  border-color: rgba(102, 126, 234, 0.5);
  transform: scale(1.05);
}

.mine-tile:disabled:not(.revealed) {
  opacity: 0.5;
  cursor: not-allowed;
}

.mine-tile.revealed {
  cursor: default;
  animation: revealTile 0.3s ease-out;
}

@keyframes revealTile {
  0% {
    transform: scale(0.9) rotateY(0deg);
  }
  50% {
    transform: scale(1.1) rotateY(90deg);
  }
  100% {
    transform: scale(1) rotateY(0deg);
  }
}

.mine-tile.gem {
  background: linear-gradient(135deg, rgba(72, 187, 120, 0.3), rgba(72, 187, 120, 0.1));
  border-color: rgba(72, 187, 120, 0.5);
}

.mine-tile.mine {
  background: linear-gradient(135deg, rgba(252, 129, 129, 0.3), rgba(252, 129, 129, 0.1));
  border-color: rgba(252, 129, 129, 0.5);
  animation: mineExplode 0.5s ease-out;
}

@keyframes mineExplode {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.2);
  }
  100% {
    transform: scale(1);
  }
}

.tile-number {
  font-size: 16px;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.4);
}

.tile-icon {
  font-size: 36px;
  animation: iconBounce 0.5s ease-out;
}

@keyframes iconBounce {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.3);
  }
}

.game-result {
  padding: 24px;
  border-radius: 16px;
  text-align: center;
  animation: resultSlideIn 0.5s ease-out;
}

@keyframes resultSlideIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.game-result.win {
  background: rgba(72, 187, 120, 0.2);
  border: 2px solid rgba(72, 187, 120, 0.5);
}

.game-result.loss {
  background: rgba(252, 129, 129, 0.2);
  border: 2px solid rgba(252, 129, 129, 0.5);
}

.result-icon {
  font-size: 48px;
  margin-bottom: 12px;
}

.result-text {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
}

.result-amount {
  font-size: 28px;
  font-weight: 800;
}

.game-result.win .result-amount {
  color: #48bb78;
}

.game-result.loss .result-amount {
  color: #fc8181;
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

.btn-adjust:hover {
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

.btn-quick-amount:hover {
  background: rgba(102, 126, 234, 0.2);
  border-color: rgba(102, 126, 234, 0.3);
}

.mines-selector {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.btn-mine-count {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 10px;
  color: white;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-mine-count:hover {
  background: rgba(255, 255, 255, 0.1);
}

.btn-mine-count.active {
  background: rgba(102, 126, 234, 0.2);
  border-color: rgba(102, 126, 234, 0.5);
  color: #667eea;
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
}

.btn-start:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-start:disabled {
  opacity: 0.5;
  cursor: not-allowed;
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
  animation: cashoutPulse 1s infinite;
}

@keyframes cashoutPulse {
  0%, 100% {
    box-shadow: 0 0 0 0 rgba(72, 187, 120, 0.7);
  }
  50% {
    box-shadow: 0 0 0 10px rgba(72, 187, 120, 0);
  }
}

.btn-cashout:hover:not(:disabled) {
  transform: translateY(-2px);
}

.btn-cashout:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  animation: none;
}

.insufficient-balance-msg {
  text-align: center;
  color: #fc8181;
  font-size: 13px;
}

.multipliers-section {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 16px;
}

.multipliers-section h3 {
  font-size: 14px;
  font-weight: 700;
  margin-bottom: 12px;
  color: rgba(255, 255, 255, 0.9);
}

.multipliers-list {
  max-height: 200px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.multiplier-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 12px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 6px;
  font-size: 13px;
}

.multiplier-row.current {
  background: rgba(102, 126, 234, 0.2);
  border: 1px solid rgba(102, 126, 234, 0.5);
}

.multiplier-row.passed {
  background: rgba(72, 187, 120, 0.1);
  color: rgba(255, 255, 255, 0.6);
}

.gems-count {
  color: rgba(255, 255, 255, 0.7);
}

.multiplier-value {
  font-weight: 700;
  color: #667eea;
}

.multiplier-row.current .multiplier-value {
  color: white;
}

.rules-section,
.provably-fair-section {
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 16px;
}

.btn-toggle-rules,
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
  font-size: 14px;
}

.rules-content,
.provably-fair-content {
  margin-top: 12px;
  font-size: 13px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.8);
}

.rules-content ul {
  padding-left: 20px;
}

.rules-content li {
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

.btn-regenerate:hover {
  background: rgba(102, 126, 234, 0.3);
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
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
}

.game-item.win {
  border-color: rgba(72, 187, 120, 0.3);
}

.game-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.game-result {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.game-multiplier {
  font-weight: 700;
  color: #667eea;
  font-size: 14px;
}

.game-payout {
  font-weight: 700;
  color: #fc8181;
  font-size: 14px;
}

.game-payout.win {
  color: #48bb78;
}

@media (max-width: 1200px) {
  .mines-game {
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

  .game-stats {
    grid-template-columns: repeat(3, 1fr);
  }

  .mines-grid {
    gap: 8px;
  }
}

@media (max-width: 600px) {
  .game-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
