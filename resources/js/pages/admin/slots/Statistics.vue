<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900">Slot Statistics</h1>
      <p class="text-gray-600 mt-1">Overview of slot game performance</p>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
      <p class="text-gray-600 mt-4">Loading statistics...</p>
    </div>

    <!-- Main Content -->
    <div v-else>
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Total Bets</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ formatNumber(stats.total_bets) }}</p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
              <span class="text-2xl">🎰</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Total Wagered</p>
              <p class="text-3xl font-bold text-blue-600 mt-2">₱{{ formatMoney(stats.total_wagered) }}</p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
              <span class="text-2xl">💰</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Total Won</p>
              <p class="text-3xl font-bold text-green-600 mt-2">₱{{ formatMoney(stats.total_won) }}</p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
              <span class="text-2xl">🏆</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">House Profit</p>
              <p class="text-3xl font-bold text-red-600 mt-2">₱{{ formatMoney(stats.house_profit) }}</p>
              <p class="text-xs text-gray-500 mt-1">
                Edge: {{ stats.house_edge ? stats.house_edge.toFixed(2) : '0.00' }}%
              </p>
            </div>
            <div class="bg-red-100 rounded-full p-3">
              <span class="text-2xl">📊</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Secondary Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
          <p class="text-sm text-gray-600 mb-2">Unique Players</p>
          <p class="text-2xl font-bold text-gray-900">{{ formatNumber(stats.unique_players) }}</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <p class="text-sm text-gray-600 mb-2">Average Bet</p>
          <p class="text-2xl font-bold text-gray-900">₱{{ formatMoney(stats.average_bet) }}</p>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <p class="text-sm text-gray-600 mb-2">Average Win</p>
          <p class="text-2xl font-bold text-gray-900">₱{{ formatMoney(stats.average_win) }}</p>
        </div>
      </div>

      <!-- Top Games -->
      <div class="bg-white rounded-lg shadow mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-bold text-gray-900">Top 10 Games by Revenue</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Game</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plays</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wagered</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Won</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RTP</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(game, index) in stats.top_games" :key="game.id">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <span
                      :class="[
                        'text-lg font-bold',
                        index === 0 ? 'text-yellow-500' : index === 1 ? 'text-gray-400' : index === 2 ? 'text-orange-500' : 'text-gray-600'
                      ]"
                    >
                      {{ index < 3 ? ['🥇', '🥈', '🥉'][index] : `#${index + 1}` }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <img
                      :src="game.thumbnail_url || '/images/game-placeholder.png'"
                      :alt="game.name"
                      class="h-10 w-10 rounded object-cover"
                    />
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900">{{ game.name }}</div>
                      <div class="text-sm text-gray-500">{{ game.game_code }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ game.provider?.name || 'Unknown' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatNumber(game.plays_count || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  ₱{{ formatMoney(game.total_wagered || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                  ₱{{ formatMoney(game.total_won || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                  ₱{{ formatMoney(game.profit || 0) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ game.actual_rtp ? game.actual_rtp.toFixed(2) + '%' : '-' }}
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="!stats.top_games || stats.top_games.length === 0" class="text-center py-12">
            <p class="text-gray-500">No game data available yet</p>
          </div>
        </div>
      </div>

      <!-- Recent Bets -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h2 class="text-xl font-bold text-gray-900">Recent Bets</h2>
          <div class="flex space-x-2">
            <select
              v-model="betFilter"
              class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
            >
              <option value="">All Status</option>
              <option value="pending">Pending</option>
              <option value="completed">Completed</option>
              <option value="refunded">Refunded</option>
            </select>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Player</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Game</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Win</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit/Loss</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="bet in filteredBets" :key="bet.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatDate(bet.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ bet.user?.username || 'N/A' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ bet.slot_game?.name || 'N/A' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  ₱{{ formatMoney(bet.bet_amount) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">
                  ₱{{ formatMoney(bet.win_amount) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <span :class="bet.payout < 0 ? 'text-red-600' : 'text-green-600'">
                    {{ bet.payout >= 0 ? '+' : '' }}₱{{ formatMoney(Math.abs(bet.payout)) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="[
                      'px-2 py-1 text-xs font-medium rounded-full',
                      bet.status === 'completed' ? 'bg-green-100 text-green-800' :
                      bet.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    ]"
                  >
                    {{ bet.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredBets.length === 0" class="text-center py-12">
            <p class="text-gray-500">No bets found</p>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="betPagination.last_page > 1" class="px-6 py-4 bg-gray-50 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Showing {{ betPagination.from }} to {{ betPagination.to }} of {{ betPagination.total }} results
            </div>
            <div class="flex space-x-2">
              <button
                @click="betPage--"
                :disabled="betPage === 1"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 disabled:opacity-50"
              >
                Previous
              </button>
              <button
                @click="betPage++"
                :disabled="betPage === betPagination.last_page"
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 disabled:opacity-50"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

const loading = ref(false);
const stats = ref({
  total_bets: 0,
  total_wagered: 0,
  total_won: 0,
  house_profit: 0,
  house_edge: 0,
  unique_players: 0,
  average_bet: 0,
  average_win: 0,
  top_games: []
});

const bets = ref([]);
const betFilter = ref('');
const betPage = ref(1);
const betPagination = ref({});

const filteredBets = computed(() => {
  if (!betFilter.value) return bets.value;
  return bets.value.filter(bet => bet.status === betFilter.value);
});

const loadStatistics = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/slots/statistics');
    stats.value = response.data.data;
  } catch (error) {
    console.error('Error loading statistics:', error);
    alert('Failed to load statistics');
  } finally {
    loading.value = false;
  }
};

const loadBets = async () => {
  try {
    const response = await axios.get('/api/admin/slots/bets/history', {
      params: {
        page: betPage.value,
        per_page: 20,
        status: betFilter.value || undefined
      }
    });
    bets.value = response.data.data;
    betPagination.value = response.data.meta;
  } catch (error) {
    console.error('Error loading bets:', error);
  }
};

const formatNumber = (num) => {
  return new Intl.NumberFormat().format(num || 0);
};

const formatMoney = (amount) => {
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(amount || 0);
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

watch([betPage, betFilter], () => {
  loadBets();
});

onMounted(() => {
  loadStatistics();
  loadBets();
});
</script>
