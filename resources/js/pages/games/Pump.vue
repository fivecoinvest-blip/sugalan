<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900 text-white">
    <div class="container mx-auto px-4 py-8">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-4xl font-bold flex items-center gap-3">
              <span class="text-5xl">💨</span>
              Pump
            </h1>
            <p class="text-gray-400 mt-2">Cash out before the pump bursts!</p>
          </div>
          <div class="text-right">
            <div class="text-sm text-gray-400">House Edge</div>
            <div class="text-2xl font-bold text-purple-400">1%</div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Game Area (Left - 2 columns) -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Game Display -->
          <div class="bg-gray-800 rounded-xl p-6 shadow-2xl border border-gray-700">
            <!-- Round Status -->
            <div class="text-center mb-4">
              <div v-if="gameState.status === 'waiting'" class="text-yellow-400 text-xl font-semibold">
                ⏳ Waiting for players... {{ countdown }}s
              </div>
              <div v-else-if="gameState.status === 'pumping'" class="text-green-400 text-xl font-semibold animate-pulse">
                💨 PUMPING!
              </div>
              <div v-else-if="gameState.status === 'burst'" class="text-red-400 text-xl font-semibold">
                💥 BURST at {{ gameState.burstPoint }}×
              </div>
            </div>

            <!-- Pump Visualization -->
            <div class="relative h-96 bg-gray-900 rounded-lg overflow-hidden border-4 border-gray-700">
              <!-- Pump Container -->
              <div class="absolute inset-0 flex items-end justify-center p-4">
                <!-- Pump Fill -->
                <div 
                  class="w-full rounded-lg transition-all duration-100 relative overflow-hidden"
                  :style="{ 
                    height: pumpFillHeight + '%',
                    background: pumpGradient
                  }"
                >
                  <!-- Bubbles Animation -->
                  <div v-if="gameState.status === 'pumping'" class="absolute inset-0">
                    <div v-for="i in 10" :key="i" 
                      class="bubble"
                      :style="{
                        left: (i * 10) + '%',
                        animationDelay: (i * 0.2) + 's'
                      }"
                    ></div>
                  </div>
                </div>
              </div>

              <!-- Current Multiplier Display -->
              <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="text-center">
                  <div 
                    class="font-bold transition-all duration-100"
                    :class="{
                      'text-6xl text-white': gameState.currentMultiplier < 2,
                      'text-7xl text-yellow-400': gameState.currentMultiplier >= 2 && gameState.currentMultiplier < 5,
                      'text-8xl text-orange-400': gameState.currentMultiplier >= 5 && gameState.currentMultiplier < 10,
                      'text-9xl text-red-400 animate-pulse': gameState.currentMultiplier >= 10
                    }"
                  >
                    {{ gameState.currentMultiplier.toFixed(2) }}×
                  </div>
                  <div v-if="currentBet && gameState.status === 'pumping'" class="text-2xl text-green-400 mt-2">
                    Potential Win: ₱{{ potentialWin.toFixed(2) }}
                  </div>
                </div>
              </div>

              <!-- Burst Effect -->
              <div v-if="gameState.status === 'burst'" class="absolute inset-0 flex items-center justify-center">
                <div class="text-9xl animate-bounce">💥</div>
              </div>
            </div>

            <!-- Active Players -->
            <div v-if="activePlayers.length > 0" class="mt-6">
              <div class="text-sm text-gray-400 mb-2">Active Players ({{ activePlayers.length }})</div>
              <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 max-h-32 overflow-y-auto">
                <div 
                  v-for="player in activePlayers" 
                  :key="player.id"
                  class="bg-gray-700 rounded px-3 py-2 text-sm"
                  :class="{
                    'border border-green-500': player.cashed_out,
                    'border border-yellow-500': !player.cashed_out && gameState.status === 'pumping'
                  }"
                >
                  <div class="font-semibold truncate">{{ player.username }}</div>
                  <div class="text-xs text-gray-400">
                    ₱{{ player.bet_amount }} 
                    <span v-if="player.cashed_out" class="text-green-400">
                      @ {{ player.cashout_multiplier }}×
                    </span>
                    <span v-else class="text-yellow-400">waiting...</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Rounds History -->
          <div class="bg-gray-800 rounded-xl p-6 shadow-xl border border-gray-700">
            <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
              <span>📊</span> Recent Rounds
            </h3>
            <div class="grid grid-cols-10 gap-2">
              <div 
                v-for="(round, index) in recentRounds" 
                :key="index"
                class="aspect-square rounded-lg flex items-center justify-center text-sm font-bold cursor-pointer hover:scale-110 transition-transform"
                :class="getRoundColor(round.burst_point)"
                :title="`Burst at ${round.burst_point}×`"
              >
                {{ round.burst_point }}×
              </div>
            </div>
          </div>

          <!-- How to Play -->
          <div class="bg-gray-800 rounded-xl p-6 shadow-xl border border-gray-700">
            <button 
              @click="showHowToPlay = !showHowToPlay"
              class="w-full flex items-center justify-between text-xl font-bold"
            >
              <span class="flex items-center gap-2">
                <span>📖</span> How to Play
              </span>
              <span>{{ showHowToPlay ? '▼' : '▶' }}</span>
            </button>
            
            <div v-if="showHowToPlay" class="mt-4 space-y-3 text-gray-300">
              <p><strong>1. Place Your Bet:</strong> Enter your bet amount while the round is waiting.</p>
              <p><strong>2. Watch the Pump:</strong> The pump starts filling and the multiplier increases 0.3× per second.</p>
              <p><strong>3. Cash Out:</strong> Click the cash out button before the pump bursts to win!</p>
              <p><strong>4. Burst Point:</strong> The pump will randomly burst between 1.00× and 50.00×.</p>
              <p><strong>5. Win Calculation:</strong> Your winnings = Bet Amount × Cash Out Multiplier</p>
              <p class="text-yellow-400"><strong>Note:</strong> If you don't cash out before the burst, you lose your bet!</p>
            </div>
          </div>

          <!-- Provably Fair -->
          <div class="bg-gray-800 rounded-xl p-6 shadow-xl border border-gray-700">
            <button 
              @click="showProvablyFair = !showProvablyFair"
              class="w-full flex items-center justify-between text-xl font-bold"
            >
              <span class="flex items-center gap-2">
                <span>🔒</span> Provably Fair
              </span>
              <span>{{ showProvablyFair ? '▼' : '▶' }}</span>
            </button>
            
            <div v-if="showProvablyFair" class="mt-4 space-y-4">
              <div>
                <label class="block text-sm text-gray-400 mb-1">Client Seed (Your Randomness)</label>
                <input 
                  v-model="clientSeed" 
                  type="text" 
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
                  placeholder="Enter your client seed"
                >
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Server Seed Hash (Next Round)</label>
                <input 
                  :value="gameState.serverSeedHash" 
                  type="text" 
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-400"
                  readonly
                >
              </div>
              <div>
                <label class="block text-sm text-gray-400 mb-1">Nonce</label>
                <input 
                  :value="nonce" 
                  type="text" 
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-400"
                  readonly
                >
              </div>
              <p class="text-sm text-gray-400">
                The burst point is determined by combining the server seed (hidden until round ends), 
                your client seed, and a nonce. This ensures the result is fair and verifiable.
              </p>
            </div>
          </div>
        </div>

        <!-- Bet Controls (Right - 1 column) -->
        <div class="space-y-6">
          <!-- Bet Control Panel -->
          <div class="bg-gray-800 rounded-xl p-6 shadow-xl border border-gray-700 sticky top-6">
            <h3 class="text-xl font-bold mb-4">Place Your Bet</h3>

            <!-- Bet Amount -->
            <div class="mb-4">
              <label class="block text-sm text-gray-400 mb-2">Bet Amount (₱)</label>
              <input 
                v-model.number="betAmount" 
                type="number" 
                min="1" 
                step="1"
                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                placeholder="Enter amount"
                :disabled="gameState.status !== 'waiting' || currentBet !== null"
              >
            </div>

            <!-- Quick Bet Buttons -->
            <div class="grid grid-cols-2 gap-2 mb-4">
              <button 
                @click="betAmount = Math.max(1, betAmount / 2)"
                class="bg-gray-700 hover:bg-gray-600 rounded-lg py-2 font-semibold transition-colors"
                :disabled="gameState.status !== 'waiting' || currentBet !== null"
              >
                ½
              </button>
              <button 
                @click="betAmount = betAmount * 2"
                class="bg-gray-700 hover:bg-gray-600 rounded-lg py-2 font-semibold transition-colors"
                :disabled="gameState.status !== 'waiting' || currentBet !== null"
              >
                2×
              </button>
            </div>

            <!-- Main Action Button -->
            <button 
              v-if="!currentBet"
              @click="placeBet"
              class="w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 rounded-lg py-4 font-bold text-lg transition-all transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
              :disabled="gameState.status !== 'waiting' || betAmount < 1"
            >
              💨 Place Bet
            </button>
            <button 
              v-else-if="gameState.status === 'pumping'"
              @click="cashOut"
              class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 rounded-lg py-4 font-bold text-lg transition-all transform hover:scale-105 animate-pulse"
            >
              💰 Cash Out {{ gameState.currentMultiplier.toFixed(2) }}×
            </button>
            <button 
              v-else
              class="w-full bg-gray-600 rounded-lg py-4 font-bold text-lg cursor-not-allowed"
              disabled
            >
              ⏳ Waiting for next round...
            </button>

            <!-- Current Bet Info -->
            <div v-if="currentBet" class="mt-4 p-4 bg-gray-700 rounded-lg">
              <div class="text-sm text-gray-400 mb-1">Your Bet</div>
              <div class="text-2xl font-bold text-purple-400">₱{{ currentBet.bet_amount }}</div>
              <div v-if="gameState.status === 'pumping'" class="text-green-400 mt-2">
                Potential: ₱{{ potentialWin.toFixed(2) }}
              </div>
            </div>

            <!-- Auto Cashout -->
            <div class="mt-6 pt-6 border-t border-gray-700">
              <label class="flex items-center gap-2 mb-3 cursor-pointer">
                <input 
                  v-model="autoCashoutEnabled" 
                  type="checkbox"
                  class="w-4 h-4 rounded"
                >
                <span class="font-semibold">Auto Cashout</span>
              </label>
              <div v-if="autoCashoutEnabled" class="space-y-2">
                <label class="block text-sm text-gray-400">At Multiplier</label>
                <input 
                  v-model.number="autoCashoutAt" 
                  type="number" 
                  min="1.01" 
                  step="0.01"
                  class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
                >
              </div>
            </div>

            <!-- Auto Bet -->
            <div class="mt-6 pt-6 border-t border-gray-700">
              <label class="flex items-center gap-2 mb-3 cursor-pointer">
                <input 
                  v-model="autoBetEnabled" 
                  type="checkbox"
                  class="w-4 h-4 rounded"
                  :disabled="gameState.status !== 'waiting'"
                >
                <span class="font-semibold">Auto Bet</span>
              </label>
              <div v-if="autoBetEnabled" class="space-y-3">
                <div>
                  <label class="block text-sm text-gray-400 mb-1">Number of Rounds</label>
                  <input 
                    v-model.number="autoBetRounds" 
                    type="number" 
                    min="1" 
                    max="100"
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
                  >
                </div>
                <div>
                  <label class="block text-sm text-gray-400 mb-1">Stop on Win (₱)</label>
                  <input 
                    v-model.number="autoBetStopWin" 
                    type="number" 
                    min="0"
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Leave 0 to disable"
                  >
                </div>
                <div>
                  <label class="block text-sm text-gray-400 mb-1">Stop on Loss (₱)</label>
                  <input 
                    v-model.number="autoBetStopLoss" 
                    type="number" 
                    min="0"
                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Leave 0 to disable"
                  >
                </div>
                <div class="text-xs text-gray-400">
                  Rounds remaining: {{ autoBetRoundsRemaining }}
                </div>
              </div>
            </div>
          </div>

          <!-- Game Stats -->
          <div class="bg-gray-800 rounded-xl p-6 shadow-xl border border-gray-700">
            <h3 class="text-xl font-bold mb-4">Your Stats</h3>
            <div class="space-y-3">
              <div class="flex justify-between items-center">
                <span class="text-gray-400">Total Bets</span>
                <span class="font-bold">{{ stats.totalBets }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-400">Total Wagered</span>
                <span class="font-bold text-purple-400">₱{{ stats.totalWagered.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-400">Total Won</span>
                <span class="font-bold text-green-400">₱{{ stats.totalWon.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-400">Net Profit</span>
                <span 
                  class="font-bold"
                  :class="stats.netProfit >= 0 ? 'text-green-400' : 'text-red-400'"
                >
                  ₱{{ stats.netProfit.toFixed(2) }}
                </span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-400">Best Multiplier</span>
                <span class="font-bold text-yellow-400">{{ stats.bestMultiplier.toFixed(2) }}×</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';

// Game State
const gameState = ref({
  roundId: null,
  status: 'waiting', // waiting, pumping, burst
  startTime: null,
  burstPoint: 0,
  currentMultiplier: 1.00,
  serverSeedHash: '',
  activePlayers: 0,
});

const countdown = ref(5);
const currentBet = ref(null);
const activePlayers = ref([]);
const recentRounds = ref([]);

// Bet Controls
const betAmount = ref(10);
const clientSeed = ref('');
const nonce = ref(0);

// Auto Features
const autoCashoutEnabled = ref(false);
const autoCashoutAt = ref(2.00);
const autoBetEnabled = ref(false);
const autoBetRounds = ref(10);
const autoBetRoundsRemaining = ref(0);
const autoBetStopWin = ref(0);
const autoBetStopLoss = ref(0);

// UI State
const showHowToPlay = ref(false);
const showProvablyFair = ref(false);

// Stats
const stats = ref({
  totalBets: 0,
  totalWagered: 0,
  totalWon: 0,
  netProfit: 0,
  bestMultiplier: 0,
});

// Intervals
let gameInterval = null;
let countdownInterval = null;

// Computed
const potentialWin = computed(() => {
  if (!currentBet.value) return 0;
  return currentBet.value.bet_amount * gameState.value.currentMultiplier;
});

const pumpFillHeight = computed(() => {
  // Map multiplier to fill height (1.00x = 0%, 50.00x = 100%)
  const maxMultiplier = 50.00;
  const percentage = Math.min(100, (gameState.value.currentMultiplier / maxMultiplier) * 100);
  return percentage;
});

const pumpGradient = computed(() => {
  const mult = gameState.value.currentMultiplier;
  if (mult < 2) return 'linear-gradient(to top, #3b82f6, #60a5fa)'; // Blue
  if (mult < 5) return 'linear-gradient(to top, #eab308, #fbbf24)'; // Yellow
  if (mult < 10) return 'linear-gradient(to top, #f97316, #fb923c)'; // Orange
  return 'linear-gradient(to top, #ef4444, #f87171)'; // Red
});

// Methods
const fetchCurrentRound = async () => {
  try {
    const response = await axios.get('/api/games/pump/round');
    if (response.data.success) {
      const round = response.data.data;
      gameState.value = {
        roundId: round.round_id,
        status: round.status,
        startTime: round.start_time,
        burstPoint: round.burst_point || 0,
        currentMultiplier: round.current_multiplier || 1.00,
        serverSeedHash: round.server_seed_hash || '',
        activePlayers: round.active_players || 0,
      };

      // Update active players list if available
      if (round.bets) {
        activePlayers.value = round.bets;
      }

      // Check if user has active bet in this round
      if (round.bets) {
        const userBet = round.bets.find(bet => bet.user_id === getUserId());
        if (userBet && !userBet.cashed_out) {
          currentBet.value = userBet;
        } else if (userBet && userBet.cashed_out && gameState.value.status === 'burst') {
          // Round ended, clear bet
          currentBet.value = null;
        }
      }

      // Handle round state changes
      if (gameState.value.status === 'waiting') {
        startCountdown();
      } else if (gameState.value.status === 'pumping') {
        stopCountdown();
        startMultiplierUpdate();
      } else if (gameState.value.status === 'burst') {
        stopMultiplierUpdate();
        // Clear current bet after short delay
        setTimeout(() => {
          currentBet.value = null;
        }, 3000);
      }
    }
  } catch (error) {
    console.error('Failed to fetch round:', error);
  }
};

const placeBet = async () => {
  if (betAmount.value < 1) {
    alert('Minimum bet is ₱1');
    return;
  }

  if (gameState.value.status !== 'waiting') {
    alert('Cannot place bet during active round');
    return;
  }

  try {
    const response = await axios.post('/api/games/pump/bet', {
      bet_amount: betAmount.value,
      client_seed: clientSeed.value || null,
    });

    if (response.data.success) {
      currentBet.value = response.data.data.bet;
      nonce.value = response.data.data.bet.nonce;

      // Update stats
      stats.value.totalBets++;
      stats.value.totalWagered += betAmount.value;

      // Handle auto-bet
      if (autoBetEnabled.value) {
        autoBetRoundsRemaining.value--;
        if (autoBetRoundsRemaining.value <= 0) {
          autoBetEnabled.value = false;
        }
      }
    } else {
      alert(response.data.message || 'Failed to place bet');
    }
  } catch (error) {
    console.error('Failed to place bet:', error);
    alert(error.response?.data?.message || 'Failed to place bet');
  }
};

const cashOut = async () => {
  if (!currentBet.value || gameState.value.status !== 'pumping') {
    return;
  }

  try {
    const response = await axios.post('/api/games/pump/cashout', {
      round_id: gameState.value.roundId,
    });

    if (response.data.success) {
      const result = response.data.data;
      
      // Update stats
      stats.value.totalWon += result.payout;
      stats.value.netProfit = stats.value.totalWon - stats.value.totalWagered;
      if (result.multiplier > stats.value.bestMultiplier) {
        stats.value.bestMultiplier = result.multiplier;
      }

      // Check auto-bet stop conditions
      if (autoBetEnabled.value) {
        const profit = result.payout - result.bet.bet_amount;
        if (autoBetStopWin.value > 0 && profit >= autoBetStopWin.value) {
          autoBetEnabled.value = false;
          alert(`Auto-bet stopped: Win target reached (₱${profit.toFixed(2)})`);
        }
      }

      // Clear current bet
      currentBet.value = null;

      // Show success message
      alert(`Cashed out at ${result.multiplier.toFixed(2)}× for ₱${result.payout.toFixed(2)}!`);
    } else {
      alert(response.data.message || 'Failed to cash out');
    }
  } catch (error) {
    console.error('Failed to cash out:', error);
    alert(error.response?.data?.message || 'Failed to cash out');
  }
};

const startCountdown = () => {
  stopCountdown();
  countdown.value = 5;
  
  countdownInterval = setInterval(() => {
    countdown.value--;
    if (countdown.value <= 0) {
      stopCountdown();
    }
  }, 1000);
};

const stopCountdown = () => {
  if (countdownInterval) {
    clearInterval(countdownInterval);
    countdownInterval = null;
  }
};

const startMultiplierUpdate = () => {
  stopMultiplierUpdate();
  
  gameInterval = setInterval(async () => {
    if (gameState.value.status === 'pumping') {
      // Fetch current round to get updated multiplier
      await fetchCurrentRound();

      // Check auto-cashout
      if (autoCashoutEnabled.value && currentBet.value && !currentBet.value.cashed_out) {
        if (gameState.value.currentMultiplier >= autoCashoutAt.value) {
          await cashOut();
        }
      }
    } else {
      stopMultiplierUpdate();
    }
  }, 100); // Update every 100ms
};

const stopMultiplierUpdate = () => {
  if (gameInterval) {
    clearInterval(gameInterval);
    gameInterval = null;
  }
};

const fetchRecentRounds = async () => {
  try {
    const response = await axios.get('/api/games/pump/recent');
    if (response.data.success) {
      recentRounds.value = response.data.data.slice(0, 20);
    }
  } catch (error) {
    console.error('Failed to fetch recent rounds:', error);
  }
};

const getRoundColor = (multiplier) => {
  if (multiplier < 2) return 'bg-gray-600 text-white';
  if (multiplier < 5) return 'bg-blue-600 text-white';
  if (multiplier < 10) return 'bg-yellow-500 text-gray-900';
  if (multiplier < 20) return 'bg-orange-500 text-white';
  return 'bg-red-500 text-white';
};

const getUserId = () => {
  // Get user ID from JWT token or localStorage
  try {
    const token = localStorage.getItem('token');
    if (token) {
      const payload = JSON.parse(atob(token.split('.')[1]));
      return payload.sub;
    }
  } catch (e) {
    console.error('Failed to get user ID:', e);
  }
  return null;
};

// Auto-bet handler
watch(() => autoBetEnabled.value, (enabled) => {
  if (enabled) {
    autoBetRoundsRemaining.value = autoBetRounds.value;
  }
});

watch(() => gameState.value.status, (newStatus, oldStatus) => {
  // Auto-place bet when new round starts
  if (autoBetEnabled.value && newStatus === 'waiting' && oldStatus === 'burst') {
    if (autoBetRoundsRemaining.value > 0) {
      setTimeout(() => {
        placeBet();
      }, 1000); // Wait 1 second before auto-betting
    }
  }
});

// Lifecycle
onMounted(() => {
  // Set up axios defaults
  const token = localStorage.getItem('token');
  if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  }

  // Generate random client seed if empty
  if (!clientSeed.value) {
    clientSeed.value = Math.random().toString(36).substring(2, 15);
  }

  // Initial fetch
  fetchCurrentRound();
  fetchRecentRounds();

  // Poll for updates every 500ms
  gameInterval = setInterval(() => {
    fetchCurrentRound();
  }, 500);
});

onUnmounted(() => {
  stopMultiplierUpdate();
  stopCountdown();
  if (gameInterval) {
    clearInterval(gameInterval);
  }
});
</script>

<style scoped>
/* Bubble Animation */
.bubble {
  position: absolute;
  bottom: 0;
  width: 20px;
  height: 20px;
  background: rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  animation: rise 3s infinite ease-in;
}

@keyframes rise {
  0% {
    bottom: 0;
    opacity: 1;
  }
  100% {
    bottom: 100%;
    opacity: 0;
  }
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #1f2937;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: #4b5563;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #6b7280;
}

/* Pulse animation for cash out button */
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.8;
  }
}

/* Responsive adjustments */
@media (max-width: 1024px) {
  .lg\\:col-span-2 {
    order: 1;
  }
  
  .space-y-6 > :last-child {
    order: 2;
  }
}
</style>
