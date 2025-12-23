<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900">Slot Games</h1>
      <p class="text-gray-600 mt-1">Manage slot game catalog</p>
    </div>

    <!-- Filters -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search games..."
        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
      />

      <select
        v-model="filterProvider"
        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
      >
        <option value="">All Providers</option>
        <option v-for="provider in providers" :key="provider.id" :value="provider.id">
          {{ provider.name }}
        </option>
      </select>

      <select
        v-model="filterCategory"
        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
      >
        <option value="">All Categories</option>
        <option value="slots">Slots</option>
        <option value="table">Table Games</option>
        <option value="fishing">Fishing</option>
        <option value="arcade">Arcade</option>
      </select>

      <select
        v-model="filterStatus"
        class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
      >
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-600">Total Games</div>
        <div class="text-2xl font-bold text-gray-900">{{ stats.total }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-600">Active Games</div>
        <div class="text-2xl font-bold text-green-600">{{ stats.active }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-600">Featured</div>
        <div class="text-2xl font-bold text-yellow-600">{{ stats.featured }}</div>
      </div>
      <div class="bg-white rounded-lg shadow p-4">
        <div class="text-sm text-gray-600">New Games</div>
        <div class="text-2xl font-bold text-blue-600">{{ stats.new }}</div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
      <p class="text-gray-600 mt-4">Loading games...</p>
    </div>

    <!-- Games Grid -->
    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Game</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RTP</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Badges</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="game in filteredGames" :key="game.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <img
                    :src="game.thumbnail_url || '/images/game-placeholder.png'"
                    :alt="game.name"
                    class="h-12 w-12 rounded object-cover"
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
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">
                  {{ game.category || 'N/A' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ game.rtp ? game.rtp + '%' : '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex space-x-1">
                  <span v-if="game.is_featured" class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded">
                    ⭐ Featured
                  </span>
                  <span v-if="game.is_new" class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">
                    🆕 New
                  </span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <button
                  @click="toggleGameStatus(game)"
                  :class="[
                    'px-3 py-1 text-xs font-semibold rounded-full cursor-pointer',
                    game.is_active
                      ? 'bg-green-100 text-green-800 hover:bg-green-200'
                      : 'bg-red-100 text-red-800 hover:bg-red-200'
                  ]"
                >
                  {{ game.is_active ? 'Active' : 'Inactive' }}
                </button>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                <button
                  @click="openEditModal(game)"
                  class="text-indigo-600 hover:text-indigo-900"
                >
                  Edit
                </button>
                <button
                  @click="deleteGame(game)"
                  class="text-red-600 hover:text-red-900"
                >
                  Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="filteredGames.length === 0" class="text-center py-12">
          <p class="text-gray-500">No games found</p>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <div class="flex items-center justify-between">
          <div class="text-sm text-gray-700">
            Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
          </div>
          <div class="flex space-x-2">
            <button
              @click="currentPage--"
              :disabled="currentPage === 1"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Previous
            </button>
            <button
              @click="currentPage++"
              :disabled="currentPage === pagination.last_page"
              class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
      @click.self="closeModal"
    >
      <div class="bg-white rounded-lg max-w-3xl w-full p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-2xl font-bold mb-6">Edit Game</h2>

        <form @submit.prevent="saveGame" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Provider *</label>
              <select
                v-model="form.provider_id"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
              >
                <option v-for="provider in providers" :key="provider.id" :value="provider.id">
                  {{ provider.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
              <select
                v-model="form.category"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
              >
                <option value="slots">Slots</option>
                <option value="table">Table Games</option>
                <option value="fishing">Fishing</option>
                <option value="arcade">Arcade</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">RTP (%)</label>
              <input
                v-model.number="form.rtp"
                type="number"
                step="0.01"
                min="0"
                max="100"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <label class="flex items-center space-x-2 cursor-pointer">
              <input v-model="form.is_active" type="checkbox" class="rounded text-purple-600" />
              <span class="text-sm font-medium text-gray-700">Active</span>
            </label>

            <label class="flex items-center space-x-2 cursor-pointer">
              <input v-model="form.is_featured" type="checkbox" class="rounded text-purple-600" />
              <span class="text-sm font-medium text-gray-700">Featured</span>
            </label>

            <label class="flex items-center space-x-2 cursor-pointer">
              <input v-model="form.is_new" type="checkbox" class="rounded text-purple-600" />
              <span class="text-sm font-medium text-gray-700">New</span>
            </label>
          </div>

          <div class="flex justify-end space-x-4 pt-4">
            <button
              type="button"
              @click="closeModal"
              class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg disabled:opacity-50"
            >
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';

const loading = ref(false);
const saving = ref(false);
const games = ref([]);
const providers = ref([]);
const searchQuery = ref('');
const filterProvider = ref('');
const filterCategory = ref('');
const filterStatus = ref('');
const currentPage = ref(1);
const pagination = ref({});
const showModal = ref(false);
const editingGame = ref(null);

const form = ref({
  name: '',
  provider_id: null,
  category: 'slots',
  rtp: null,
  is_active: true,
  is_featured: false,
  is_new: false
});

const stats = computed(() => ({
  total: games.value.length,
  active: games.value.filter(g => g.is_active).length,
  featured: games.value.filter(g => g.is_featured).length,
  new: games.value.filter(g => g.is_new).length
}));

const filteredGames = computed(() => {
  let filtered = [...games.value];

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(g =>
      g.name.toLowerCase().includes(query) ||
      g.game_code.toLowerCase().includes(query)
    );
  }

  if (filterProvider.value) {
    filtered = filtered.filter(g => g.provider_id === parseInt(filterProvider.value));
  }

  if (filterCategory.value) {
    filtered = filtered.filter(g => g.category === filterCategory.value);
  }

  if (filterStatus.value === 'active') {
    filtered = filtered.filter(g => g.is_active);
  } else if (filterStatus.value === 'inactive') {
    filtered = filtered.filter(g => !g.is_active);
  }

  return filtered;
});

const loadGames = async () => {
  loading.value = true;
  try {
    const [gamesRes, providersRes] = await Promise.all([
      axios.get('/api/admin/slots/games', {
        params: {
          page: currentPage.value,
          per_page: 50,
          provider_id: filterProvider.value || undefined,
          category: filterCategory.value || undefined
        }
      }),
      axios.get('/api/admin/slots/providers')
    ]);

    games.value = gamesRes.data.data;
    pagination.value = gamesRes.data.meta;
    providers.value = providersRes.data.data;
  } catch (error) {
    console.error('Error loading games:', error);
    alert('Failed to load games');
  } finally {
    loading.value = false;
  }
};

const toggleGameStatus = async (game) => {
  try {
    await axios.post(`/api/admin/slots/games/${game.id}/toggle-status`);
    game.is_active = !game.is_active;
  } catch (error) {
    console.error('Error toggling game status:', error);
    alert('Failed to update game status');
  }
};

const openEditModal = (game) => {
  editingGame.value = game;
  form.value = {
    name: game.name,
    provider_id: game.provider_id,
    category: game.category || 'slots',
    rtp: game.rtp,
    is_active: game.is_active,
    is_featured: game.is_featured,
    is_new: game.is_new
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingGame.value = null;
};

const saveGame = async () => {
  saving.value = true;
  try {
    await axios.put(`/api/admin/slots/games/${editingGame.value.id}`, form.value);
    await loadGames();
    closeModal();
    alert('Game updated successfully');
  } catch (error) {
    console.error('Error saving game:', error);
    alert(error.response?.data?.message || 'Failed to save game');
  } finally {
    saving.value = false;
  }
};

const deleteGame = async (game) => {
  if (!confirm(`Delete ${game.name}?`)) return;

  try {
    await axios.delete(`/api/admin/slots/games/${game.id}`);
    await loadGames();
    alert('Game deleted successfully');
  } catch (error) {
    console.error('Error deleting game:', error);
    alert('Failed to delete game');
  }
};

watch([currentPage], () => {
  loadGames();
});

onMounted(() => {
  loadGames();
});
</script>
