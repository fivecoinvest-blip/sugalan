<template>
  <div class="slot-games-page">
    <!-- Notification Toast -->
    <div v-if="notification.show" class="toast" :class="`toast-${notification.type}`">
      {{ notification.message }}
    </div>
    
    <div class="page-header">
      <div>
        <h1>Slot Games</h1>
        <p class="subtitle">{{ totalGames }} games from {{ providers.length }} providers</p>
      </div>
      <button @click="showFilters = !showFilters" class="btn btn-secondary">
        <i class="fas fa-filter"></i> Filters
      </button>
    </div>

    <!-- Filters -->
    <div v-if="showFilters" class="filters-panel">
      <div class="filter-row">
        <div class="filter-group">
          <label>Provider</label>
          <select v-model="filters.provider" @change="fetchGames">
            <option value="">All Providers</option>
            <option v-for="provider in providers" :key="provider.id" :value="provider.id">
              {{ provider.name }}
            </option>
          </select>
        </div>

        <div class="filter-group">
          <label>Category</label>
          <select v-model="filters.category" @change="fetchGames">
            <option value="">All Categories</option>
            <option v-for="cat in categories" :key="cat" :value="cat">
              {{ cat }}
            </option>
          </select>
        </div>

        <div class="filter-group">
          <label>Status</label>
          <select v-model="filters.status" @change="fetchGames">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Search</label>
          <input 
            v-model="filters.search" 
            type="text" 
            placeholder="Search games..."
            @input="debounceSearch"
          />
        </div>

        <div class="filter-group">
          <button @click="clearFilters" class="btn btn-sm btn-secondary">
            Clear Filters
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p>Loading games...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="fetchGames" class="btn btn-secondary">Retry</button>
    </div>

    <!-- Games Table -->
    <div v-else class="games-table-container">
      <table class="games-table">
        <thead>
          <tr>
            <th>Thumbnail</th>
            <th>Game Name</th>
            <th>Aggregator</th>
            <th>Manufacturer</th>
            <th>Category</th>
            <th>RTP</th>
            <th>Min/Max Bet</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="game in games" :key="game.id" :class="{ 'inactive-row': !game.is_active }">
            <td>
              <img 
                v-if="game.thumbnail_url" 
                :src="game.thumbnail_url" 
                :alt="game.name"
                class="game-thumbnail"
                @error="handleImageError"
              />
              <div v-else class="thumbnail-placeholder">
                🎰
              </div>
            </td>
            <td>
              <div class="game-info">
                <strong>{{ game.name }}</strong>
                <span class="game-id">{{ game.game_id }}</span>
              </div>
            </td>
            <td>
              <span class="provider-badge">{{ game.provider_name }}</span>
            </td>
            <td>
              <span v-if="game.manufacturer" class="manufacturer-badge">{{ game.manufacturer }}</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>{{ game.category || 'N/A' }}</td>
            <td>
              <span v-if="game.rtp" class="rtp-badge">{{ game.rtp }}%</span>
              <span v-else>-</span>
            </td>
            <td>
              <div class="bet-range">
                <span>₱{{ formatNumber(game.min_bet) }}</span>
                <span class="separator">-</span>
                <span>₱{{ formatNumber(game.max_bet) }}</span>
              </div>
            </td>
            <td>
              <span 
                class="status-badge" 
                :class="game.is_active ? 'active' : 'inactive'"
              >
                {{ game.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button 
                  @click="toggleGameStatus(game)" 
                  class="btn-icon"
                  :class="game.is_active ? 'text-danger' : 'text-success'"
                  :title="game.is_active ? 'Deactivate' : 'Activate'"
                >
                  <i class="fas" :class="game.is_active ? 'fa-ban' : 'fa-check'"></i>
                </button>
                <button 
                  @click="editGame(game)" 
                  class="btn-icon text-primary"
                  title="Edit"
                >
                  <i class="fas fa-edit"></i>
                </button>
                <button 
                  @click="deleteGame(game)" 
                  class="btn-icon text-danger"
                  title="Delete"
                >
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Empty State -->
      <div v-if="games.length === 0" class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No Games Found</h3>
        <p>No games match your current filters or no games have been synced yet.</p>
        <router-link to="/admin/slots/providers" class="btn btn-primary">
          Go to Providers
        </router-link>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="pagination">
        <button 
          @click="changePage(currentPage - 1)" 
          :disabled="currentPage === 1"
          class="btn btn-sm"
        >
          Previous
        </button>
        
        <span class="page-info">
          Page {{ currentPage }} of {{ totalPages }}
        </span>
        
        <button 
          @click="changePage(currentPage + 1)" 
          :disabled="currentPage === totalPages"
          class="btn btn-sm"
        >
          Next
        </button>
      </div>
    </div>

    <!-- Edit Game Modal -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Edit Game</h2>
          <button @click="closeModal" class="btn-close">&times;</button>
        </div>

        <form @submit.prevent="updateGame" class="game-form">
          <div class="form-group">
            <label>Game Name</label>
            <input v-model="editForm.name" type="text" required />
          </div>

          <div class="form-group">
            <label>Category</label>
            <input v-model="editForm.category" type="text" />
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Min Bet</label>
              <input v-model.number="editForm.min_bet" type="number" step="0.01" min="0" />
            </div>

            <div class="form-group">
              <label>Max Bet</label>
              <input v-model.number="editForm.max_bet" type="number" step="0.01" min="0" />
            </div>
          </div>

          <div class="form-group">
            <label>RTP (%)</label>
            <input v-model.number="editForm.rtp" type="number" step="0.01" min="0" max="100" />
          </div>

          <div class="form-group checkbox-group">
            <label>
              <input v-model="editForm.is_active" type="checkbox" />
              <span>Active</span>
            </label>
          </div>

          <div class="modal-actions">
            <button type="button" @click="closeModal" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              {{ submitting ? 'Saving...' : 'Update' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../utils/api';

// State
const games = ref([]);
const providers = ref([]);
const categories = ref([]);
const loading = ref(true);
const error = ref(null);
const showFilters = ref(false);
const showEditModal = ref(false);
const submitting = ref(false);

// Pagination
const currentPage = ref(1);
const perPage = ref(50);
const totalGames = ref(0);

// Filters
const filters = ref({
  provider: '',
  category: '',
  status: '',
  search: '',
});

const editForm = ref({
  name: '',
  category: '',
  min_bet: 0,
  max_bet: 0,
  rtp: 0,
  is_active: true,
});

const editingGame = ref(null);

// Notification
const notification = ref({ show: false, message: '', type: '' });

const showNotification = (message, type = 'success') => {
  notification.value = { show: true, message, type };
  setTimeout(() => {
    notification.value.show = false;
  }, 3000);
};

// Computed
const totalPages = computed(() => Math.ceil(totalGames.value / perPage.value));

// Methods
const fetchProviders = async () => {
  try {
    const response = await api.get('/admin/slots/providers');
    providers.value = response.data.data || [];
  } catch (err) {
    console.error('Failed to fetch providers:', err);
  }
};

const fetchGames = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      ...filters.value,
    };

    const response = await api.get('/admin/slots/games', { params });
    games.value = response.data.data || [];
    totalGames.value = response.data.total || games.value.length;
    
    // Extract unique categories
    const cats = new Set(games.value.map(g => g.category).filter(Boolean));
    categories.value = Array.from(cats).sort();
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load games';
    showNotification(error.value, 'error');
  } finally {
    loading.value = false;
  }
};

const clearFilters = () => {
  filters.value = {
    provider: '',
    category: '',
    status: '',
    search: '',
  };
  currentPage.value = 1;
  fetchGames();
};

let searchTimeout;
const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    currentPage.value = 1;
    fetchGames();
  }, 500);
};

const changePage = (page) => {
  currentPage.value = page;
  fetchGames();
};

const editGame = (game) => {
  editingGame.value = game;
  editForm.value = {
    name: game.name,
    category: game.category,
    min_bet: game.min_bet,
    max_bet: game.max_bet,
    rtp: game.rtp,
    is_active: game.is_active,
  };
  showEditModal.value = true;
};

const closeModal = () => {
  showEditModal.value = false;
  editingGame.value = null;
};

const updateGame = async () => {
  submitting.value = true;
  
  try {
    await api.put(`/admin/slots/games/${editingGame.value.id}`, editForm.value);
    showNotification('Game updated successfully');
    closeModal();
    await fetchGames();
  } catch (err) {
    showNotification(err.response?.data?.message || 'Failed to update game', 'error');
  } finally {
    submitting.value = false;
  }
};

const toggleGameStatus = async (game) => {
  try {
    await api.put(`/admin/slots/games/${game.id}`, {
      is_active: !game.is_active,
    });
    
    showNotification(`Game ${game.is_active ? 'deactivated' : 'activated'} successfully`);
    await fetchGames();
  } catch (err) {
    showNotification('Failed to update game status', 'error');
  }
};

const deleteGame = async (game) => {
  if (!confirm(`Are you sure you want to delete "${game.name}"?`)) {
    return;
  }
  
  try {
    await api.delete(`/admin/slots/games/${game.id}`);
    showNotification('Game deleted successfully');
    await fetchGames();
  } catch (err) {
    showNotification('Failed to delete game', 'error');
  }
};

const formatNumber = (num) => {
  if (!num) return '0';
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2 }).format(num);
};

const handleImageError = (e) => {
  e.target.style.display = 'none';
  const sibling = e.target.nextElementSibling;
  if (sibling) {
    sibling.style.display = 'flex';
  }
};

// Lifecycle
onMounted(() => {
  fetchProviders();
  fetchGames();
});
</script>

<style scoped>
.slot-games-page {
  padding: 24px;
  position: relative;
}

.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  padding: 16px 24px;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  z-index: 10000;
  animation: slideIn 0.3s ease;
  font-weight: 500;
}

@keyframes slideIn {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.toast-success {
  background: #d4edda;
  color: #155724;
  border-left: 4px solid #28a745;
}

.toast-error {
  background: #f8d7da;
  color: #721c24;
  border-left: 4px solid #dc3545;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.page-header h1 {
  margin: 0;
  font-size: 28px;
  color: #1a1a2e;
}

.subtitle {
  margin: 4px 0 0 0;
  color: #666;
  font-size: 14px;
}

.filters-panel {
  background: white;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  align-items: end;
}

.filter-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

.filter-group input,
.filter-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.games-table-container {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.games-table {
  width: 100%;
  border-collapse: collapse;
}

.games-table thead {
  background: #f8f9fa;
}

.games-table th {
  padding: 16px 12px;
  text-align: left;
  font-weight: 600;
  color: #333;
  font-size: 14px;
  border-bottom: 2px solid #e0e0e0;
}

.games-table td {
  padding: 12px;
  border-bottom: 1px solid #f0f0f0;
}

.games-table tr:hover {
  background: #f8f9fa;
}

.inactive-row {
  opacity: 0.6;
}

.game-thumbnail {
  width: 60px;
  height: 60px;
  object-fit: cover;
  border-radius: 8px;
}

.thumbnail-placeholder {
  width: 60px;
  height: 60px;
  background: #f0f0f0;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.game-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.game-id {
  font-size: 12px;
  color: #999;
}

.provider-badge {
  display: inline-block;
  padding: 4px 12px;
  background: #e3f2fd;
  color: #1976d2;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.manufacturer-badge {
  display: inline-block;
  padding: 4px 12px;
  background: #f3e5f5;
  color: #7b1fa2;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.rtp-badge {
  display: inline-block;
  padding: 4px 8px;
  background: #fff3cd;
  color: #856404;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.bet-range {
  display: flex;
  flex-direction: column;
  font-size: 12px;
}

.bet-range .separator {
  display: none;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.active {
  background: #d4edda;
  color: #155724;
}

.status-badge.inactive {
  background: #f8d7da;
  color: #721c24;
}

.action-buttons {
  display: flex;
  gap: 8px;
}

.btn-icon {
  background: none;
  border: none;
  padding: 8px;
  cursor: pointer;
  font-size: 16px;
  transition: transform 0.2s;
}

.btn-icon:hover {
  transform: scale(1.2);
}

.text-danger { color: #dc3545; }
.text-success { color: #28a745; }
.text-primary { color: #007bff; }

.empty-state {
  text-align: center;
  padding: 64px 24px;
  color: #999;
}

.empty-state i {
  font-size: 64px;
  margin-bottom: 16px;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 16px;
  padding: 20px;
  border-top: 1px solid #e0e0e0;
}

.page-info {
  color: #666;
  font-size: 14px;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 24px;
}

.modal-content {
  background: white;
  border-radius: 12px;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-bottom: 1px solid #e0e0e0;
}

.modal-header h2 {
  margin: 0;
  font-size: 24px;
}

.btn-close {
  background: none;
  border: none;
  font-size: 32px;
  color: #999;
  cursor: pointer;
}

.game-form {
  padding: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

.form-group input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.checkbox-group label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
  width: auto;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 24px;
  border-top: 1px solid #e0e0e0;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background: #5a6268;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

.loading-container,
.error-container {
  text-align: center;
  padding: 48px;
  background: white;
  border-radius: 12px;
}

.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
