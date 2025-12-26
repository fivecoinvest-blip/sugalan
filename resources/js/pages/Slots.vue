<template>
  <div class="slots-page">
    <div class="container">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">🎰 Slot Games</h1>
          <p class="page-subtitle">Play slots from top providers with seamless wallet integration</p>
        </div>
        <div class="search-bar">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Search slots..."
            class="search-input"
            @input="handleSearch"
          />
        </div>
      </div>

      <!-- Providers Filter -->
      <div class="filters">
        <button 
          @click="selectedProvider = null"
          class="filter-btn"
          :class="{ active: selectedProvider === null }"
        >
          🎰 All Providers
        </button>
        <button 
          v-for="provider in providers" 
          :key="provider.code"
          @click="selectedProvider = provider.code"
          class="filter-btn"
          :class="{ active: selectedProvider === provider.code }"
        >
          {{ provider.name }}
        </button>
      </div>

      <!-- Categories Filter -->
      <div class="filters secondary">
        <button 
          @click="selectedCategory = null"
          class="filter-btn small"
          :class="{ active: selectedCategory === null }"
        >
          All Categories
        </button>
        <button 
          v-for="category in categories" 
          :key="category"
          @click="selectedCategory = category"
          class="filter-btn small"
          :class="{ active: selectedCategory === category }"
        >
          {{ category }}
        </button>
      </div>

      <!-- Popular Games -->
      <div v-if="!searchQuery && !selectedProvider && !selectedCategory" class="popular-section">
        <h2 class="section-title">🔥 Popular Games</h2>
        <div class="games-grid">
          <div 
            v-for="game in popularGames" 
            :key="game.id"
            @click="launchGame(game)"
            class="game-card"
          >
            <div class="game-card-inner">
              <img 
                v-if="game.thumbnail_url" 
                :src="game.thumbnail_url" 
                :alt="game.name"
                class="game-thumbnail"
              />
              <div v-else class="game-placeholder">🎰</div>
              
              <div class="game-info">
                <h3 class="game-name">{{ game.name }}</h3>
                <div class="game-badges">
                  <span v-if="game.manufacturer" class="manufacturer-badge">{{ game.manufacturer }}</span>
                  <span class="provider-badge-small">{{ game.provider_name }}</span>
                </div>
                
                <div class="game-meta">
                  <span class="meta-item">
                    <span class="meta-label">RTP:</span>
                    <span class="meta-value">{{ game.rtp }}%</span>
                  </span>
                  <span class="meta-item">
                    <span class="meta-label">Max Win:</span>
                    <span class="meta-value">{{ game.max_bet }}x</span>
                  </span>
                </div>
                
                <div v-if="game.category" class="game-category">
                  {{ game.category }}
                </div>
              </div>

              <div class="play-overlay">
                <span class="play-btn">▶ Play Now</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- All Games -->
      <div class="all-games-section">
        <h2 class="section-title">
          {{ getSectionTitle() }}
        </h2>
        
        <!-- Loading State -->
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading games...</p>
        </div>

        <!-- Games Grid -->
        <div v-else-if="filteredGames.length > 0" class="games-grid">
          <div 
            v-for="game in filteredGames" 
            :key="game.id"
            @click="launchGame(game)"
            class="game-card"
          >
            <div class="game-card-inner">
              <img 
                v-if="game.thumbnail_url" 
                :src="game.thumbnail_url" 
                :alt="game.name"
                class="game-thumbnail"
              />
              <div v-else class="game-placeholder">🎰</div>
              
              <div class="game-info">
                <h3 class="game-name">{{ game.name }}</h3>
                <div class="game-badges">
                  <span v-if="game.manufacturer" class="manufacturer-badge">{{ game.manufacturer }}</span>
                  <span class="provider-badge-small">{{ game.provider_name }}</span>
                </div>
                
                <div class="game-meta">
                  <span v-if="game.rtp" class="meta-item">
                    <span class="meta-label">RTP:</span>
                    <span class="meta-value">{{ game.rtp }}%</span>
                  </span>
                  <span v-if="game.volatility" class="meta-item">
                    <span class="meta-label">Volatility:</span>
                    <span class="meta-value">{{ game.volatility }}</span>
                  </span>
                </div>
                
                <div v-if="game.category" class="game-category">
                  {{ game.category }}
                </div>
              </div>

              <div class="play-overlay">
                <span class="play-btn">▶ Play Now</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state">
          <div class="empty-icon">🎰</div>
          <p>No games found matching your criteria</p>
          <button @click="clearFilters" class="clear-filters-btn">Clear Filters</button>
        </div>
      </div>

      <!-- Active Session Indicator -->
      <div v-if="activeSession" class="active-session-bar">
        <div class="session-info">
          <span class="session-icon">🎮</span>
          <span class="session-text">Playing: {{ activeSession.game_name }}</span>
          <span class="session-balance">Balance: ₱{{ formatNumber(activeSession.final_balance) }}</span>
        </div>
        <button @click="endSession" class="end-session-btn">End Session</button>
      </div>
    </div>

    <!-- Game Launch Modal -->
    <div v-if="launchModal.show" class="modal-overlay" @click="closeLaunchModal">
      <div class="modal-content game-modal" @click.stop>
        <button @click="closeLaunchModal" class="modal-close">×</button>
        
        <div v-if="launchModal.loading" class="modal-loading">
          <div class="spinner"></div>
          <p>Launching game...</p>
        </div>

        <div v-else-if="launchModal.error" class="modal-error">
          <div class="error-icon">⚠️</div>
          <p class="error-message">{{ launchModal.error }}</p>
          <button @click="closeLaunchModal" class="btn-primary">Close</button>
        </div>

        <iframe 
          v-else-if="launchModal.url"
          :src="launchModal.url"
          class="game-iframe"
          frameborder="0"
          allowfullscreen
        ></iframe>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useWalletStore } from '../stores/wallet';
import axios from 'axios';

const authStore = useAuthStore();
const walletStore = useWalletStore();

// Create API instance
const api = axios.create({
  baseURL: '/api',
});

// Add auth token to requests
api.interceptors.request.use((config) => {
  if (authStore.token) {
    config.headers.Authorization = `Bearer ${authStore.token}`;
  }
  return config;
});

// State
const providers = ref([]);
const categories = ref([]);
const popularGames = ref([]);
const allGames = ref([]);
const activeSession = ref(null);
const loading = ref(false);
const searchQuery = ref('');
const selectedProvider = ref(null);
const selectedCategory = ref(null);
const launchModal = ref({
  show: false,
  url: null,
  loading: false,
  error: null,
  game: null,
});

// Computed
const filteredGames = computed(() => {
  let games = allGames.value;

  if (selectedProvider.value) {
    games = games.filter(g => g.provider_code === selectedProvider.value);
  }

  if (selectedCategory.value) {
    games = games.filter(g => g.category === selectedCategory.value);
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    games = games.filter(g => 
      (g.name || '').toLowerCase().includes(query) ||
      (g.provider_name || g.provider?.name || '').toLowerCase().includes(query)
    );
  }

  return games;
});

// Methods
const fetchProviders = async () => {
  try {
    const response = await api.get('/slots/providers');
    providers.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to fetch providers:', error);
  }
};

const fetchCategories = async () => {
  try {
    const params = {};
    if (selectedProvider.value) {
      params.provider = selectedProvider.value;
    }
    const response = await api.get('/slots/games/categories', { params });
    categories.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to fetch categories:', error);
  }
};

const fetchPopularGames = async () => {
  try {
    const response = await api.get('/slots/games/popular', {
      params: { limit: 12 }
    });
    popularGames.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to fetch popular games:', error);
  }
};

const fetchGames = async () => {
  loading.value = true;
  try {
    const params = {};
    if (selectedProvider.value) {
      params.provider = selectedProvider.value;
    }
    if (selectedCategory.value) {
      params.category = selectedCategory.value;
    }
    const response = await api.get('/slots/games', { params });
    allGames.value = response.data.data || [];
  } catch (error) {
    console.error('Failed to fetch games:', error);
  } finally {
    loading.value = false;
  }
};

const handleSearch = async () => {
  if (searchQuery.value.length < 3 && searchQuery.value.length > 0) {
    return; // Wait for at least 3 characters
  }

  if (searchQuery.value.length >= 3) {
    loading.value = true;
    try {
      const response = await api.get('/slots/games/search', {
        params: { q: searchQuery.value }
      });
      allGames.value = response.data.data || [];
    } catch (error) {
      console.error('Failed to search games:', error);
    } finally {
      loading.value = false;
    }
  } else {
    // Reset to all games
    await fetchGames();
  }
};

const launchGame = async (game) => {
  if (!authStore.isAuthenticated) {
    authStore.showLoginModal = true;
    return;
  }

  launchModal.value = {
    show: true,
    url: null,
    loading: true,
    error: null,
    game: game,
  };

  try {
    const response = await api.post(`/slots/games/${game.id}/launch`, {
      demo_mode: false,
    });

    launchModal.value.loading = false;
    launchModal.value.url = response.data.data.game_url;
    
    // Update active session
    activeSession.value = {
      id: response.data.data.session_id,
      game_name: game.name,
      expires_at: response.data.data.expires_at,
    };
    
    // Refresh wallet balance
    await walletStore.fetchBalance();
  } catch (error) {
    launchModal.value.loading = false;
    launchModal.value.error = error.response?.data?.message || 'Failed to launch game. Please try again.';
    console.error('Failed to launch game:', error);
  }
};

const closeLaunchModal = () => {
  launchModal.value = {
    show: false,
    url: null,
    loading: false,
    error: null,
    game: null,
  };
};

const endSession = async () => {
  if (!activeSession.value) return;

  try {
    await api.post('/slots/session/end');
    activeSession.value = null;
    closeLaunchModal();
    
    // Refresh wallet balance
    await walletStore.fetchBalance();
  } catch (error) {
    console.error('Failed to end session:', error);
  }
};

const checkActiveSession = async () => {
  try {
    const response = await api.get('/slots/session/active');
    if (response.data.data) {
      activeSession.value = response.data.data;
    }
  } catch (error) {
    // No active session or error - ignore
  }
};

const getSectionTitle = () => {
  if (searchQuery.value) {
    return `Search Results for "${searchQuery.value}"`;
  }
  if (selectedProvider.value) {
    const provider = providers.value.find(p => p.code === selectedProvider.value);
    return provider ? `${provider.name} Games` : 'All Games';
  }
  if (selectedCategory.value) {
    return `${selectedCategory.value} Games`;
  }
  return 'All Games';
};

const clearFilters = () => {
  searchQuery.value = '';
  selectedProvider.value = null;
  selectedCategory.value = null;
  fetchGames();
};

const formatNumber = (num) => {
  return parseFloat(num).toFixed(2);
};

// Lifecycle
onMounted(async () => {
  await fetchProviders();
  await fetchCategories();
  await fetchPopularGames();
  await fetchGames();
  
  if (authStore.isAuthenticated) {
    await checkActiveSession();
  }
});
</script>

<style scoped>
.slots-page {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 2rem 0;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 1rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 1rem;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 700;
  color: #fff;
  margin: 0;
}

.page-subtitle {
  color: rgba(255, 255, 255, 0.8);
  margin: 0.5rem 0 0;
}

.search-bar {
  flex: 1;
  max-width: 400px;
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  font-size: 1rem;
  transition: all 0.3s ease;
}

.search-input::placeholder {
  color: rgba(255, 255, 255, 0.5);
}

.search-input:focus {
  outline: none;
  border-color: rgba(255, 255, 255, 0.5);
  background: rgba(255, 255, 255, 0.15);
}

.filters {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.filters.secondary {
  margin-top: -0.5rem;
}

.filter-btn {
  padding: 0.75rem 1.5rem;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  font-size: 1rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
}

.filter-btn.small {
  padding: 0.5rem 1rem;
  font-size: 0.9rem;
}

.filter-btn:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.4);
}

.filter-btn.active {
  background: rgba(255, 255, 255, 0.3);
  border-color: #fff;
}

.popular-section,
.all-games-section {
  margin-bottom: 3rem;
}

.section-title {
  font-size: 1.8rem;
  font-weight: 600;
  color: #fff;
  margin-bottom: 1.5rem;
}

.games-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.5rem;
}

.game-card {
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
}

.game-card:hover {
  transform: translateY(-5px);
  border-color: rgba(255, 255, 255, 0.5);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.game-card-inner {
  position: relative;
  overflow: hidden;
}

.game-thumbnail {
  width: 100%;
  height: 180px;
  object-fit: cover;
  display: block;
}

.game-placeholder {
  width: 100%;
  height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
  font-size: 4rem;
}

.game-info {
  padding: 1rem;
}

.game-name {
  font-size: 1.2rem;
  font-weight: 600;
  color: #fff;
  margin: 0 0 0.5rem;
}

.game-badges {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  flex-wrap: wrap;
}

.manufacturer-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  background: rgba(167, 139, 250, 0.3);
  border: 1px solid rgba(167, 139, 250, 0.5);
  border-radius: 12px;
  font-size: 0.75rem;
  color: #c4b5fd;
  font-weight: 600;
}

.provider-badge-small {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  background: rgba(96, 165, 250, 0.3);
  border: 1px solid rgba(96, 165, 250, 0.5);
  border-radius: 12px;
  font-size: 0.75rem;
  color: #93c5fd;
  font-weight: 600;
}

.game-provider {
  font-size: 0.9rem;
  color: rgba(255, 255, 255, 0.7);
  margin: 0 0 0.75rem;
}

.game-meta {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.meta-item {
  display: flex;
  gap: 0.25rem;
  font-size: 0.85rem;
}

.meta-label {
  color: rgba(255, 255, 255, 0.6);
}

.meta-value {
  color: #fff;
  font-weight: 600;
}

.game-category {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  font-size: 0.8rem;
  color: #fff;
  margin-top: 0.5rem;
}

.play-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(102, 126, 234, 0.95);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.game-card:hover .play-overlay {
  opacity: 1;
}

.play-btn {
  padding: 0.75rem 2rem;
  background: #fff;
  color: #667eea;
  border-radius: 8px;
  font-weight: 600;
  font-size: 1.1rem;
}

.loading-state {
  text-align: center;
  padding: 3rem;
  color: #fff;
}

.spinner {
  width: 50px;
  height: 50px;
  margin: 0 auto 1rem;
  border: 4px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-state {
  text-align: center;
  padding: 3rem;
  color: #fff;
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.clear-filters-btn {
  margin-top: 1rem;
  padding: 0.75rem 1.5rem;
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 8px;
  color: #fff;
  font-size: 1rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
}

.clear-filters-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  border-color: #fff;
}

.active-session-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: rgba(30, 30, 30, 0.95);
  border-top: 2px solid #667eea;
  padding: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 999;
}

.session-info {
  display: flex;
  align-items: center;
  gap: 1rem;
  color: #fff;
}

.session-icon {
  font-size: 1.5rem;
}

.session-text {
  font-weight: 600;
}

.session-balance {
  color: #4ade80;
}

.end-session-btn {
  padding: 0.5rem 1.5rem;
  background: #ef4444;
  border: none;
  border-radius: 8px;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.3s ease;
}

.end-session-btn:hover {
  background: #dc2626;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-content {
  background: #1e1e1e;
  border-radius: 12px;
  max-width: 100%;
  max-height: 100%;
  position: relative;
}

.modal-content.game-modal {
  width: 95vw;
  height: 90vh;
  max-width: 1400px;
}

.modal-close {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 40px;
  height: 40px;
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  color: #fff;
  font-size: 1.5rem;
  cursor: pointer;
  z-index: 1001;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: #fff;
}

.modal-loading,
.modal-error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #fff;
  padding: 2rem;
}

.error-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.error-message {
  font-size: 1.1rem;
  margin-bottom: 1.5rem;
  text-align: center;
}

.btn-primary {
  padding: 0.75rem 2rem;
  background: #667eea;
  border: none;
  border-radius: 8px;
  color: #fff;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.3s ease;
}

.btn-primary:hover {
  background: #5568d3;
}

.game-iframe {
  width: 100%;
  height: 100%;
  border-radius: 12px;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .search-bar {
    max-width: 100%;
  }

  .page-title {
    font-size: 2rem;
  }

  .games-grid {
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
  }

  .active-session-bar {
    flex-direction: column;
    gap: 1rem;
    padding: 1rem;
  }

  .session-info {
    flex-direction: column;
    gap: 0.5rem;
    text-align: center;
  }

  .modal-content.game-modal {
    width: 100vw;
    height: 100vh;
    border-radius: 0;
  }
}
</style>
