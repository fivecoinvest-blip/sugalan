<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-gray-900">Game Providers</h1>
      <p class="text-gray-600 mt-1">Manage slot game providers</p>
    </div>

    <!-- Action Bar -->
    <div class="mb-6 flex justify-between items-center">
      <div class="flex space-x-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search providers..."
          class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
        />
      </div>
      <button
        @click="openCreateModal"
        class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-medium flex items-center space-x-2"
      >
        <span>+</span>
        <span>Add Provider</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500"></div>
      <p class="text-gray-600 mt-4">Loading providers...</p>
    </div>

    <!-- Providers Table -->
    <div v-else class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logo</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Brand ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Games</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="provider in filteredProviders" :key="provider.id">
            <td class="px-6 py-4 whitespace-nowrap">
              <img
                v-if="provider.logo_url"
                :src="provider.logo_url"
                :alt="provider.name"
                class="h-10 w-10 rounded object-cover"
              />
              <div v-else class="h-10 w-10 bg-gray-200 rounded flex items-center justify-center text-gray-400">
                No Logo
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">{{ provider.name }}</div>
              <div class="text-sm text-gray-500">{{ provider.api_provider }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
              <code class="bg-gray-100 px-2 py-1 rounded">{{ provider.code }}</code>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ provider.brand_id || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ provider.games_count || 0 }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span
                :class="[
                  'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                  provider.is_active
                    ? 'bg-green-100 text-green-800'
                    : 'bg-red-100 text-red-800'
                ]"
              >
                {{ provider.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
              <button
                @click="syncProviderGames(provider)"
                class="text-blue-600 hover:text-blue-900"
                title="Sync games from API"
              >
                🔄 Sync
              </button>
              <button
                @click="openEditModal(provider)"
                class="text-indigo-600 hover:text-indigo-900"
              >
                Edit
              </button>
              <button
                @click="deleteProvider(provider)"
                class="text-red-600 hover:text-red-900"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="filteredProviders.length === 0" class="text-center py-12">
        <p class="text-gray-500">No providers found</p>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
      @click.self="closeModal"
    >
      <div class="bg-white rounded-lg max-w-2xl w-full p-6">
        <h2 class="text-2xl font-bold mb-6">{{ editingProvider ? 'Edit Provider' : 'Add Provider' }}</h2>

        <form @submit.prevent="saveProvider" class="space-y-4">
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
              <label class="block text-sm font-medium text-gray-700 mb-2">Code *</label>
              <input
                v-model="form.code"
                type="text"
                required
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">API Provider</label>
              <input
                v-model="form.api_provider"
                type="text"
                placeholder="e.g., SoftAPI"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Brand ID</label>
              <input
                v-model="form.brand_id"
                type="text"
                placeholder="For API sync"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Logo URL</label>
            <input
              v-model="form.logo_url"
              type="url"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
              <input
                v-model.number="form.sort_order"
                type="number"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>

            <div class="flex items-end">
              <label class="flex items-center space-x-2 cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="rounded text-purple-600" />
                <span class="text-sm font-medium text-gray-700">Active</span>
              </label>
            </div>
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const loading = ref(false);
const saving = ref(false);
const providers = ref([]);
const searchQuery = ref('');
const showModal = ref(false);
const editingProvider = ref(null);

const form = ref({
  name: '',
  code: '',
  api_provider: 'SoftAPI',
  brand_id: '',
  logo_url: '',
  sort_order: 0,
  is_active: true
});

const filteredProviders = computed(() => {
  if (!searchQuery.value) return providers.value;
  
  const query = searchQuery.value.toLowerCase();
  return providers.value.filter(p =>
    p.name.toLowerCase().includes(query) ||
    p.code.toLowerCase().includes(query)
  );
});

const loadProviders = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/admin/slots/providers');
    providers.value = response.data.data;
  } catch (error) {
    console.error('Error loading providers:', error);
    alert('Failed to load providers');
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingProvider.value = null;
  form.value = {
    name: '',
    code: '',
    api_provider: 'SoftAPI',
    brand_id: '',
    logo_url: '',
    sort_order: 0,
    is_active: true
  };
  showModal.value = true;
};

const openEditModal = (provider) => {
  editingProvider.value = provider;
  form.value = { ...provider };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingProvider.value = null;
};

const saveProvider = async () => {
  saving.value = true;
  try {
    if (editingProvider.value) {
      await axios.put(`/api/admin/slots/providers/${editingProvider.value.id}`, form.value);
    } else {
      await axios.post('/api/admin/slots/providers', form.value);
    }
    
    await loadProviders();
    closeModal();
    alert('Provider saved successfully');
  } catch (error) {
    console.error('Error saving provider:', error);
    alert(error.response?.data?.message || 'Failed to save provider');
  } finally {
    saving.value = false;
  }
};

const syncProviderGames = async (provider) => {
  if (!provider.brand_id) {
    alert('Brand ID is required for syncing games');
    return;
  }

  if (!confirm(`Sync games from ${provider.name}? This will fetch games from the API.`)) {
    return;
  }

  try {
    const response = await axios.post(`/api/admin/slots/providers/${provider.id}/sync`);
    alert(`Successfully synced ${response.data.data.synced_count} games`);
    await loadProviders();
  } catch (error) {
    console.error('Error syncing games:', error);
    alert(error.response?.data?.message || 'Failed to sync games');
  }
};

const deleteProvider = async (provider) => {
  if (!confirm(`Delete ${provider.name}? This will also delete all associated games.`)) {
    return;
  }

  try {
    await axios.delete(`/api/admin/slots/providers/${provider.id}`);
    await loadProviders();
    alert('Provider deleted successfully');
  } catch (error) {
    console.error('Error deleting provider:', error);
    alert(error.response?.data?.message || 'Failed to delete provider');
  }
};

onMounted(() => {
  loadProviders();
});
</script>
