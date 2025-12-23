<template>
  <div class="min-h-screen bg-gray-900 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-4xl font-bold text-white mb-2">🎰 Slot Games</h1>
        <p class="text-gray-400">Play exciting slot games from top providers</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
        <p class="text-gray-400 mt-4">Loading games...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-900/20 border border-red-500 rounded-lg p-6 text-center">
        <p class="text-red-400">{{ error }}</p>
        <button @click="loadInitialData" class="mt-4 px-6 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-white">
          Try Again
        </button>
      </div>

      <!-- Main Content -->
      <div v-else>
        <!-- Provider Tabs -->
        <div class="mb-8 overflow-x-auto">
          <div class="flex space-x-2 min-w-max pb-4">
            <button
              @click="selectedProvider = null"
              :class="[
                'px-6 py-3 rounded-lg font-medium transition-all',
                selectedProvider === null
                  ? 'bg-purple-600 text-white shadow-lg'
                  : 'bg-gray-800 text-gray-400 hover:bg-gray-700'
              ]"
            >
              All Games ({{ totalGames }})
            </button>
            <button
              v-for="provider in providers"
              :key="provider.id"
              @click="selectedProvider = provider.id"
              :class="[
                'px-6 py-3 rounded-lg font-medium transition-all flex items-center space-x-2',
                selectedProvider === provider.id
                  ? 'bg-purple-600 text-white shadow-lg'
                  : 'bg-gray-800 text-gray-400 hover:bg-gray-700'
              ]"
            >
              <img v-if="provider.logo_url" :src="provider.logo_url" :alt="provider.name" class="h-6 w-6 rounded" />
              <span>{{ provider.name }} ({{ provider.games_count }})</span>
            </button>
          </div>
        </div>

        <!-- Filters and Search -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-12 gap-4">
          <!-- Search -->
          <div class="md:col-span-5">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search games..."
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-purple-500"
            />
          </div>

          <!-- Category Filter -->
          <div class="md:col-span-3">
            <select
              v-model="selectedCategory"
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-purple-500"
            >
              <option value="">All Categories</option>
              <option value="slots">Slots</option>
              <option value="table">Table Games</option>
              <option value="fishing">Fishing</option>
              <option value="arcade">Arcade</option>
            </select>
          </div>

          <!-- Sort -->
          <div class="md:col-span-2">
            <select
              v-model="sortBy"
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-purple-500"
            >
              <option value="popular">Popular</option>
              <option value="new">New</option>
              <option value="name">Name</option>
            </select>
          </div>

          <!-- Filters Toggle -->
          <div class="md:col-span-2">
            <button
              @click="showFilters = !showFilters"
              class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white hover:bg-gray-700 transition-colors"
            >
              Filters {{ showFilters ? '▲' : '▼' }}
            </button>
          </div>
        </div>

        <!-- Extended Filters -->
        <div v-show="showFilters" class="mb-6 bg-gray-800 rounded-lg p-6 border border-gray-700">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="flex items-center space-x-2 text-white cursor-pointer">
              <input v-model="showFeatured" type="checkbox" class="rounded bg-gray-700 border-gray-600 text-purple-600" />
              <span>Featured Games</span>
            </label>
            <label class="flex items-center space-x-2 text-white cursor-pointer">
              <input v-model="showNew" type="checkbox" class="rounded bg-gray-700 border-gray-600 text-purple-600" />
              <span>New Games</span>
            </label>
          </div>
        </div>

        <!-- Games Grid -->
        <div v-if="filteredGames.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <div
            v-for="game in filteredGames"
            :key="game.id"
            class="bg-gray-800 rounded-lg overflow-hidden hover:ring-2 hover:ring-purple-500 transition-all cursor-pointer group"
            @click="launchGame(game)"
          >
            <!-- Game Thumbnail -->
            <div class="relative aspect-square overflow-hidden bg-gray-900">
              <img
                :src="game.thumbnail_url || '/images/game-placeholder.png'"
                :alt="game.name"
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
              />
              
              <!-- Badges -->
              <div class="absolute top-2 left-2 flex flex-col gap-1">
                <span v-if="game.is_featured" class="bg-yellow-500 text-black text-xs px-2 py-1 rounded font-bold">
                  ⭐ Featured
                </span>
                <span v-if="game.is_new" class="bg-green-500 text-white text-xs px-2 py-1 rounded font-bold">
                  🆕 New
                </span>
              </div>

              <!-- Play Overlay -->
              <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                <button class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-bold flex items-center space-x-2">
                  <span>▶</span>
                  <span>Play Now</span>
                </button>
              </div>
            </div>

            <!-- Game Info -->
            <div class="p-3">
              <h3 class="text-white font-medium truncate mb-1">{{ game.name }}</h3>
              <div class="flex items-center justify-between text-xs text-gray-400">
                <span>{{ game.provider?.name || 'Unknown' }}</span>
                <span v-if="game.rtp" class="bg-gray-700 px-2 py-1 rounded">RTP: {{ game.rtp }}%</span>
              </div>
            </div>
          </div>
        </div>

        <!-- No Games Found -->
        <div v-else class="text-center py-12">
          <p class="text-gray-400 text-lg">No games found matching your criteria</p>
          <button @click="clearFilters" class="mt-4 px-6 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white">
            Clear Filters
          </button>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="mt-8 flex justify-center">
          <nav class="flex space-x-2">
            <button
              @click="currentPage--"
              :disabled="currentPage === 1"
              class="px-4 py-2 bg-gray-800 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-700"
            >
              Previous
            </button>
            <span class="px-4 py-2 bg-gray-800 text-white rounded-lg">
              Page {{ currentPage }} of {{ totalPages }}
            </span>
            <button
              @click="currentPage++"
              :disabled="currentPage === totalPages"
              class="px-4 py-2 bg-gray-800 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-700"
            >
              Next
            </button>
          </nav>
        </div>
      </div>
    </div>

    <!-- Game Launch Modal -->
    <div
      v-if="launchingGame"
      class="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
      @click.self="closeLaunchModal"
    >
      <div class="bg-gray-900 rounded-lg max-w-6xl w-full h-5/6 flex flex-col">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-800">
          <h2 class="text-xl font-bold text-white">{{ launchingGame.name }}</h2>
          <button @click="closeLaunchModal" class="text-gray-400 hover:text-white text-2xl">
            ×
          </button>
        </div>

        <!-- Game Frame -->
        <div class="flex-1 relative">
          <div v-if="loadingGameUrl" class="absolute inset-0 flex items-center justify-center">
            <div class="text-center">
              <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500 mb-4"></div>
              <p class="text-white">Launching game...</p>
            </div>
          </div>
          <iframe
            v-if="gameUrl"
            :src="gameUrl"
            class="w-full h-full"
            frameborder="0"
            allowfullscreen
          ></iframe>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const authStore = useAuthStore();

// State
const loading = ref(false);
const error = ref('');
const providers = ref([]);
const games = ref([]);
const selectedProvider = ref(null);
const selectedCategory = ref('');
const searchQuery = ref('');
const sortBy = ref('popular');
const showFilters = ref(false);
const showFeatured = ref(false);
const showNew = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);
const totalGames = ref(0);

// Game Launch
const launchingGame = ref(null);
const loadingGameUrl = ref(false);
const gameUrl = ref('');

// Computed
const filteredGames = computed(() => {
  let filtered = [...games.value];

  // Apply filters
  if (selectedProvider.value) {
    filtered = filtered.filter(g => g.provider_id === selectedProvider.value);
  }

  if (selectedCategory.value) {
    filtered = filtered.filter(g => g.category === selectedCategory.value);
  }

  if (showFeatured.value) {
    filtered = filtered.filter(g => g.is_featured);
  }

  if (showNew.value) {
    filtered = filtered.filter(g => g.is_new);
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(g => 
      g.name.toLowerCase().includes(query) ||
      g.name_en?.toLowerCase().includes(query)
    );
  }

  // Sort
  if (sortBy.value === 'name') {
    filtered.sort((a, b) => a.name.localeCompare(b.name));
  } else if (sortBy.value === 'new') {
    filtered.sort((a, b) => b.is_new - a.is_new);
  }

  return filtered;
});

// Methods
const loadInitialData = async () => {
  loading.value = true;
  error.value = '';

  try {
    // Load providers and games in parallel
    const [providersRes, gamesRes] = await Promise.all([
      axios.get('/api/slots/providers'),
      axios.get('/api/slots/games', {
        params: {
          page: currentPage.value,
          per_page: 50
        }
      })
    ]);

    providers.value = providersRes.data.data;
    games.value = gamesRes.data.data;
    totalPages.value = gamesRes.data.meta.last_page;
    totalGames.value = gamesRes.data.meta.total;
  } catch (err) {
    console.error('Error loading slot games:', err);
    error.value = err.response?.data?.message || 'Failed to load games. Please try again.';
  } finally {
    loading.value = false;
  }
};

const launchGame = async (game) => {
  launchingGame.value = game;
  loadingGameUrl.value = true;
  gameUrl.value = '';

  try {
    const response = await axios.post(`/api/slots/games/${game.id}/launch`, {
      mode: 'real', // or 'demo' for free play
      return_url: window.location.href
    });

    gameUrl.value = response.data.data.game_url;
  } catch (err) {
    console.error('Error launching game:', err);
    const message = err.response?.data?.message || 'Failed to launch game';
    alert(message);
    closeLaunchModal();
  } finally {
    loadingGameUrl.value = false;
  }
};

const closeLaunchModal = () => {
  launchingGame.value = null;
  gameUrl.value = '';
  loadingGameUrl.value = false;
};

const clearFilters = () => {
  selectedProvider.value = null;
  selectedCategory.value = '';
  searchQuery.value = '';
  showFeatured.value = false;
  showNew.value = false;
  sortBy.value = 'popular';
};

// Watchers
watch([selectedProvider, selectedCategory, showFeatured, showNew], () => {
  currentPage.value = 1;
});

// Lifecycle
onMounted(() => {
  loadInitialData();
});
</script>
