<template>
  <div class="slot-providers-page">
    <!-- Notification Toast -->
    <div v-if="notification.show" class="toast" :class="`toast-${notification.type}`">
      {{ notification.message }}
    </div>
    
    <div class="page-header">
      <h1>Slot Providers</h1>
      <button @click="showAddModal = true" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Provider
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <div class="spinner"></div>
      <p>Loading providers...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-container">
      <i class="fas fa-exclamation-triangle"></i>
      <p>{{ error }}</p>
      <button @click="fetchProviders" class="btn btn-secondary">Retry</button>
    </div>

    <!-- Providers List -->
    <div v-else class="providers-grid">
      <div 
        v-for="provider in providers" 
        :key="provider.id" 
        class="provider-card"
        :class="{ 'inactive': !provider.is_active }"
      >
        <div class="provider-header">
          <div class="provider-info">
            <h3>{{ provider.name }}</h3>
            <span class="provider-code">{{ provider.code }}</span>
          </div>
          <div class="provider-status">
            <span 
              class="status-badge" 
              :class="provider.is_active ? 'active' : 'inactive'"
            >
              {{ provider.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
        </div>

        <div class="provider-details">
          <div class="detail-row">
            <span class="label">API URL:</span>
            <span class="value">{{ provider.api_url }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Agency UID:</span>
            <span class="value">{{ provider.agency_uid }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Player Prefix:</span>
            <span class="value">{{ provider.player_prefix }}</span>
          </div>
          <div class="detail-row">
            <span class="label">Wallet Type:</span>
            <span class="value">
              {{ provider.supports_seamless_wallet ? 'Seamless' : 'Transfer' }}
            </span>
          </div>
          <div class="detail-row">
            <span class="label">Demo Mode:</span>
            <span class="value">
              {{ provider.supports_demo_mode ? 'Yes' : 'No' }}
            </span>
          </div>
          <div class="detail-row">
            <span class="label">Session Timeout:</span>
            <span class="value">{{ provider.session_timeout_minutes }} minutes</span>
          </div>
          <div class="detail-row">
            <span class="label">Currency:</span>
            <span class="value">{{ provider.currency }}</span>
          </div>
        </div>

        <div class="provider-actions">
          <button 
            @click="editProvider(provider)" 
            class="btn btn-sm btn-secondary"
          >
            <i class="fas fa-edit"></i> Edit
          </button>
          <button 
            @click="toggleProviderStatus(provider)" 
            class="btn btn-sm"
            :class="provider.is_active ? 'btn-danger' : 'btn-success'"
          >
            <i class="fas" :class="provider.is_active ? 'fa-ban' : 'fa-check'"></i>
            {{ provider.is_active ? 'Deactivate' : 'Activate' }}
          </button>
          <button 
            @click="syncProvider(provider)" 
            class="btn btn-sm btn-info"
          >
            <i class="fas fa-sync"></i> Sync Games
          </button>
          <button 
            @click="confirmDelete(provider)" 
            class="btn btn-sm btn-danger"
          >
            <i class="fas fa-trash"></i> Delete
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="providers.length === 0" class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No Providers Found</h3>
        <p>Click "Add Provider" to add your first slot provider.</p>
      </div>
    </div>

    <!-- Add/Edit Provider Modal -->
    <div v-if="showAddModal || showEditModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ showEditModal ? 'Edit Provider' : 'Add Provider' }}</h2>
          <button @click="closeModal" class="btn-close">&times;</button>
        </div>

        <form @submit.prevent="submitProvider" class="provider-form">
          <div class="form-group">
            <label>Provider Code *</label>
            <input 
              v-model="form.code" 
              type="text" 
              required 
              :disabled="showEditModal"
              placeholder="e.g., AYUT, PGSOFT"
            />
          </div>

          <div class="form-group">
            <label>Provider Name *</label>
            <input 
              v-model="form.name" 
              type="text" 
              required 
              placeholder="e.g., AYUT GAMES"
            />
          </div>

          <div class="form-group">
            <label>API URL *</label>
            <input 
              v-model="form.api_url" 
              type="url" 
              required 
              placeholder="https://api.provider.com"
            />
          </div>

          <div class="form-group">
            <label>Agency UID *</label>
            <input 
              v-model="form.agency_uid" 
              type="text" 
              required 
              placeholder="Your agency identifier"
            />
          </div>

          <div class="form-group">
            <label>AES Key *</label>
            <input 
              v-model="form.aes_key" 
              type="text" 
              required 
              placeholder="32-character AES encryption key"
            />
          </div>

          <div class="form-group">
            <label>Player Prefix *</label>
            <input 
              v-model="form.player_prefix" 
              type="text" 
              required 
              placeholder="e.g., ayut, pg"
            />
          </div>

          <div class="form-group">
            <label>Session Timeout (minutes) *</label>
            <input 
              v-model.number="form.session_timeout_minutes" 
              type="number" 
              required 
              min="5"
              max="1440"
            />
          </div>

          <div class="form-group">
            <label>Currency *</label>
            <select v-model="form.currency" required>
              <option value="PHP">PHP</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </div>

          <div class="form-group checkbox-group">
            <label>
              <input v-model="form.supports_seamless_wallet" type="checkbox" />
              <span>Supports Seamless Wallet</span>
            </label>
          </div>

          <div class="form-group checkbox-group">
            <label>
              <input v-model="form.supports_transfer_wallet" type="checkbox" />
              <span>Supports Transfer Wallet</span>
            </label>
          </div>

          <div class="form-group checkbox-group">
            <label>
              <input v-model="form.supports_demo_mode" type="checkbox" />
              <span>Supports Demo Mode</span>
            </label>
          </div>

          <div class="form-group checkbox-group">
            <label>
              <input v-model="form.is_active" type="checkbox" />
              <span>Active</span>
            </label>
          </div>

          <div class="modal-actions">
            <button type="button" @click="closeModal" class="btn btn-secondary">
              Cancel
            </button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              {{ submitting ? 'Saving...' : (showEditModal ? 'Update' : 'Add') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="closeDeleteModal">
      <div class="modal-content modal-small">
        <div class="modal-header">
          <h2>Confirm Delete</h2>
          <button @click="closeDeleteModal" class="btn-close">&times;</button>
        </div>

        <div class="modal-body">
          <div class="warning-icon">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
          <p class="warning-text">
            Are you sure you want to delete provider <strong>{{ providerToDelete?.name }}</strong>?
          </p>
          <p class="warning-subtext">
            This action cannot be undone. All games associated with this provider will also be removed.
          </p>
        </div>

        <div class="modal-footer">
          <button 
            @click="closeDeleteModal" 
            class="btn btn-secondary"
            :disabled="deleting"
          >
            Cancel
          </button>
          <button 
            @click="deleteProvider" 
            class="btn btn-danger"
            :disabled="deleting"
          >
            <span v-if="deleting">
              <i class="fas fa-spinner fa-spin"></i> Deleting...
            </span>
            <span v-else>
              <i class="fas fa-trash"></i> Delete Provider
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '../../utils/api';

// State
const providers = ref([]);
const loading = ref(true);
const error = ref(null);
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const submitting = ref(false);
const deleting = ref(false);
const providerToDelete = ref(null);

// Notification state
const notification = ref({ show: false, message: '', type: '' });

const showNotification = (message, type = 'success') => {
  notification.value = { show: true, message, type };
  setTimeout(() => {
    notification.value.show = false;
  }, 3000);
};

// Form data
const form = ref({
  code: '',
  name: '',
  api_url: '',
  agency_uid: '',
  aes_key: '',
  player_prefix: '',
  session_timeout_minutes: 30,
  currency: 'PHP',
  supports_seamless_wallet: true,
  supports_transfer_wallet: false,
  supports_demo_mode: true,
  is_active: true,
});

const editingId = ref(null);

// Methods
const fetchProviders = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const response = await api.get('/admin/slots/providers');
    providers.value = response.data.data || [];
  } catch (err) {
    error.value = err.message || 'Failed to load providers';
    showNotification(error.value, 'error');
  } finally {
    loading.value = false;
  }
};

const editProvider = (provider) => {
  editingId.value = provider.id;
  form.value = { ...provider };
  showEditModal.value = true;
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  resetForm();
};

const resetForm = () => {
  form.value = {
    code: '',
    name: '',
    api_url: '',
    agency_uid: '',
    aes_key: '',
    player_prefix: '',
    session_timeout_minutes: 30,
    currency: 'PHP',
    supports_seamless_wallet: true,
    supports_transfer_wallet: false,
    supports_demo_mode: true,
    is_active: true,
  };
  editingId.value = null;
};

const submitProvider = async () => {
  submitting.value = true;
  
  try {
    if (showEditModal.value) {
      await api.put(`/admin/slots/providers/${editingId.value}`, form.value);
      showNotification('Provider updated successfully');
    } else {
      await api.post('/admin/slots/providers', form.value);
      showNotification('Provider added successfully');
    }
    
    closeModal();
    await fetchProviders();
  } catch (err) {
    showNotification(err.response?.data?.message || 'Failed to save provider', 'error');
  } finally {
    submitting.value = false;
  }
};

const toggleProviderStatus = async (provider) => {
  try {
    await api.put(`/admin/slots/providers/${provider.id}`, {
      ...provider,
      is_active: !provider.is_active,
    });
    
    showNotification(`Provider ${provider.is_active ? 'deactivated' : 'activated'} successfully`);
    await fetchProviders();
  } catch (err) {
    showNotification('Failed to update provider status', 'error');
  }
};

const syncProvider = async (provider) => {
  try {
    showNotification('Syncing games... This may take a moment.', 'info');
    await api.post(`/admin/slots/providers/${provider.id}/sync`);
    showNotification('Games synced successfully');
  } catch (err) {
    showNotification(err.response?.data?.message || 'Failed to sync games', 'error');
  }
};

const confirmDelete = (provider) => {
  providerToDelete.value = provider;
  showDeleteModal.value = true;
};

const closeDeleteModal = () => {
  if (!deleting.value) {
    showDeleteModal.value = false;
    providerToDelete.value = null;
  }
};

const deleteProvider = async () => {
  if (!providerToDelete.value) return;
  
  deleting.value = true;
  
  try {
    await api.delete(`/admin/slots/providers/${providerToDelete.value.id}`);
    showNotification(`Provider ${providerToDelete.value.name} deleted successfully`);
    closeDeleteModal();
    await fetchProviders();
  } catch (err) {
    showNotification(err.response?.data?.message || 'Failed to delete provider', 'error');
  } finally {
    deleting.value = false;
  }
};

// Lifecycle
onMounted(() => {
  fetchProviders();
});
</script>

<style scoped>
.slot-providers-page {
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

.toast-info {
  background: #d1ecf1;
  color: #0c5460;
  border-left: 4px solid #17a2b8;
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

.loading-container,
.error-container {
  text-align: center;
  padding: 48px;
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

.providers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 24px;
}

.provider-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.provider-card:hover {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.provider-card.inactive {
  opacity: 0.6;
}

.provider-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e0e0e0;
}

.provider-info h3 {
  margin: 0 0 8px 0;
  font-size: 20px;
  color: #1a1a2e;
}

.provider-code {
  display: inline-block;
  padding: 4px 8px;
  background: #f0f0f0;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
  color: #666;
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

.provider-details {
  margin-bottom: 16px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
}

.detail-row .label {
  font-weight: 600;
  color: #666;
}

.detail-row .value {
  color: #333;
  text-align: right;
  max-width: 60%;
  word-break: break-all;
}

.provider-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 64px 24px;
  color: #999;
}

.empty-state i {
  font-size: 64px;
  margin-bottom: 16px;
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
  color: #1a1a2e;
}

.btn-close {
  background: none;
  border: none;
  font-size: 32px;
  color: #999;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-close:hover {
  color: #333;
}

.provider-form {
  padding: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #333;
  font-size: 14px;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.3s;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #667eea;
}

.checkbox-group label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-group input[type="checkbox"] {
  width: auto;
  margin: 0;
}

.modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding-top: 24px;
  border-top: 1px solid #e0e0e0;
}

/* Button Styles */
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

.btn-danger {
  background: #dc3545;
  color: white;
}

.btn-danger:hover {
  background: #c82333;
}

.btn-success {
  background: #28a745;
  color: white;
}

.btn-success:hover {
  background: #218838;
}

.btn-info {
  background: #17a2b8;
  color: white;
}

.btn-info:hover {
  background: #138496;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

/* Delete Modal Styles */
.modal-small {
  max-width: 500px;
}

.modal-body {
  padding: 24px;
  text-align: center;
}

.warning-icon {
  margin-bottom: 20px;
}

.warning-icon i {
  font-size: 64px;
  color: #ffc107;
}

.warning-text {
  font-size: 18px;
  color: #333;
  margin-bottom: 12px;
  line-height: 1.5;
}

.warning-subtext {
  font-size: 14px;
  color: #666;
  line-height: 1.5;
}

.modal-footer {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  padding: 16px 24px;
  border-top: 1px solid #e0e0e0;
  background: #f8f9fa;
  border-radius: 0 0 12px 12px;
}

@media (max-width: 768px) {
  .providers-grid {
    grid-template-columns: 1fr;
  }
  
  .provider-actions {
    flex-direction: column;
  }
  
  .provider-actions .btn {
    width: 100%;
    justify-content: center;
  }

  .modal-footer {
    flex-direction: column;
  }

  .modal-footer .btn {
    width: 100%;
  }
}
</style>
