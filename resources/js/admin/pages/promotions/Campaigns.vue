<template>
  <div class="campaigns-page">
    <div class="page-header">
      <div>
        <h1>Promotional Campaigns</h1>
        <p class="subtitle">Manage promotional campaigns and offers</p>
      </div>
      <button @click="showCreateModal = true" class="btn btn-primary">
        ➕ Create Campaign
      </button>
    </div>

    <!-- Filters -->
    <div class="filters-section">
      <div class="filter-group">
        <label>Status</label>
        <select v-model="filters.status" @change="loadCampaigns" class="form-select">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="scheduled">Scheduled</option>
          <option value="ended">Ended</option>
        </select>
      </div>

      <div class="filter-group">
        <label>Type</label>
        <select v-model="filters.type" @change="loadCampaigns" class="form-select">
          <option value="">All Types</option>
          <option value="bonus">Bonus</option>
          <option value="reload">Reload</option>
          <option value="cashback">Cashback</option>
          <option value="free_spins">Free Spins</option>
          <option value="tournament">Tournament</option>
        </select>
      </div>

      <div class="filter-group">
        <label>Search</label>
        <input 
          v-model="filters.search" 
          @input="debounceSearch"
          type="text" 
          placeholder="Search campaigns..."
          class="form-control"
        />
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">🎁</div>
        <div class="stat-info">
          <div class="stat-value">{{ stats.total_campaigns }}</div>
          <div class="stat-label">Total Campaigns</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
          <div class="stat-value">{{ stats.active_campaigns }}</div>
          <div class="stat-label">Active Campaigns</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-info">
          <div class="stat-value">{{ stats.total_claims }}</div>
          <div class="stat-label">Total Claims</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
          <div class="stat-value">₱{{ formatMoney(stats.total_bonus_value) }}</div>
          <div class="stat-label">Total Bonus Value</div>
        </div>
      </div>
    </div>

    <!-- Campaigns Table -->
    <div class="table-container">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading campaigns...</p>
      </div>

      <div v-else-if="campaigns.length === 0" class="empty-state">
        <div class="empty-icon">🎉</div>
        <p>No campaigns found</p>
        <button @click="showCreateModal = true" class="btn btn-primary">
          Create Your First Campaign
        </button>
      </div>

      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Campaign</th>
            <th>Type</th>
            <th>Value</th>
            <th>Status</th>
            <th>Dates</th>
            <th>Claims</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="campaign in campaigns" :key="campaign.id">
            <td>
              <div class="campaign-info">
                <div class="campaign-title">{{ campaign.title }}</div>
                <div class="campaign-code" v-if="campaign.code">
                  Code: <strong>{{ campaign.code }}</strong>
                </div>
              </div>
            </td>
            <td>
              <span class="type-badge" :class="campaign.type">
                {{ formatType(campaign.type) }}
              </span>
            </td>
            <td>
              <div v-if="campaign.type === 'bonus'">₱{{ formatMoney(campaign.value) }}</div>
              <div v-else-if="campaign.type === 'reload' || campaign.type === 'cashback'">
                {{ campaign.percentage }}%
                <small class="text-muted">max ₱{{ formatMoney(campaign.max_bonus) }}</small>
              </div>
              <div v-else-if="campaign.type === 'free_spins'">
                {{ campaign.value }} spins
              </div>
            </td>
            <td>
              <span class="status-badge" :class="campaign.status">
                {{ campaign.status }}
              </span>
            </td>
            <td>
              <div class="date-info">
                <div class="date-item">
                  <small>Start:</small> {{ formatDate(campaign.start_date) }}
                </div>
                <div class="date-item">
                  <small>End:</small> {{ formatDate(campaign.end_date) }}
                </div>
              </div>
            </td>
            <td>
              <div class="claims-info">
                <div>{{ campaign.claims_count }} claims</div>
                <div v-if="campaign.max_claims_total" class="text-muted">
                  of {{ campaign.max_claims_total }}
                </div>
              </div>
            </td>
            <td>
              <div class="action-buttons">
                <button @click="viewStatistics(campaign)" class="btn-icon" title="Statistics">
                  📊
                </button>
                <button @click="editCampaign(campaign)" class="btn-icon" title="Edit">
                  ✏️
                </button>
                <button @click="deleteCampaign(campaign)" class="btn-icon danger" title="Delete">
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Campaign Modal -->
    <div v-if="showCreateModal || showEditModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content large" @click.stop>
        <div class="modal-header">
          <h3>{{ showEditModal ? 'Edit Campaign' : 'Create Campaign' }}</h3>
          <button @click="closeModals" class="btn-close">×</button>
        </div>

        <div class="modal-body">
          <form @submit.prevent="saveCampaign">
            <div class="form-grid">
              <!-- Basic Info -->
              <div class="form-group col-span-2">
                <label>Title *</label>
                <input v-model="form.title" type="text" class="form-control" required />
              </div>

              <div class="form-group">
                <label>Code</label>
                <input v-model="form.code" type="text" class="form-control" placeholder="PROMO123" />
              </div>

              <div class="form-group">
                <label>Type *</label>
                <select v-model="form.type" class="form-control" required>
                  <option value="bonus">Bonus</option>
                  <option value="reload">Reload Bonus</option>
                  <option value="cashback">Cashback</option>
                  <option value="free_spins">Free Spins</option>
                  <option value="tournament">Tournament</option>
                </select>
              </div>

              <div class="form-group col-span-2">
                <label>Description</label>
                <textarea v-model="form.description" class="form-control" rows="3"></textarea>
              </div>

              <!-- Value Configuration -->
              <div class="form-group" v-if="form.type === 'bonus' || form.type === 'free_spins'">
                <label>Value *</label>
                <input v-model.number="form.value" type="number" class="form-control" step="0.01" />
              </div>

              <div class="form-group" v-if="form.type === 'reload' || form.type === 'cashback'">
                <label>Percentage *</label>
                <input v-model.number="form.percentage" type="number" class="form-control" max="100" />
              </div>

              <div class="form-group" v-if="form.type === 'reload' || form.type === 'cashback'">
                <label>Max Bonus</label>
                <input v-model.number="form.max_bonus" type="number" class="form-control" step="0.01" />
              </div>

              <div class="form-group" v-if="form.type !== 'cashback'">
                <label>Min Deposit</label>
                <input v-model.number="form.min_deposit" type="number" class="form-control" step="0.01" />
              </div>

              <!-- Wagering & Limits -->
              <div class="form-group">
                <label>Wagering Multiplier *</label>
                <input v-model.number="form.wagering_multiplier" type="number" class="form-control" />
              </div>

              <div class="form-group">
                <label>Bonus Expiry (days)</label>
                <input v-model.number="form.bonus_expiry_days" type="number" class="form-control" />
              </div>

              <div class="form-group">
                <label>Max Claims Total</label>
                <input v-model.number="form.max_claims_total" type="number" class="form-control" placeholder="Unlimited" />
              </div>

              <div class="form-group">
                <label>Max Claims Per User</label>
                <input v-model.number="form.max_claims_per_user" type="number" class="form-control" />
              </div>

              <!-- VIP Restrictions -->
              <div class="form-group">
                <label>Min VIP Level</label>
                <select v-model.number="form.min_vip_level" class="form-control">
                  <option :value="null">Any Level</option>
                  <option :value="1">Bronze</option>
                  <option :value="2">Silver</option>
                  <option :value="3">Gold</option>
                  <option :value="4">Platinum</option>
                  <option :value="5">Diamond</option>
                </select>
              </div>

              <div class="form-group">
                <label>Max VIP Level</label>
                <select v-model.number="form.max_vip_level" class="form-control">
                  <option :value="null">Any Level</option>
                  <option :value="1">Bronze</option>
                  <option :value="2">Silver</option>
                  <option :value="3">Gold</option>
                  <option :value="4">Platinum</option>
                  <option :value="5">Diamond</option>
                </select>
              </div>

              <!-- Dates -->
              <div class="form-group">
                <label>Start Date *</label>
                <input v-model="form.start_date" type="datetime-local" class="form-control" required />
              </div>

              <div class="form-group">
                <label>End Date *</label>
                <input v-model="form.end_date" type="datetime-local" class="form-control" required />
              </div>

              <!-- Status & Featured -->
              <div class="form-group">
                <label>Status *</label>
                <select v-model="form.status" class="form-control" required>
                  <option value="active">Active</option>
                  <option value="scheduled">Scheduled</option>
                  <option value="ended">Ended</option>
                </select>
              </div>

              <div class="form-group">
                <label class="checkbox-label">
                  <input v-model="form.is_featured" type="checkbox" />
                  <span>Featured Campaign</span>
                </label>
              </div>

              <!-- Terms -->
              <div class="form-group col-span-2">
                <label>Terms & Conditions</label>
                <textarea v-model="form.terms" class="form-control" rows="4"></textarea>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" @click="closeModals" class="btn btn-secondary">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save Campaign' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Statistics Modal -->
    <div v-if="showStatsModal" class="modal-overlay" @click="showStatsModal = false">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Campaign Statistics</h3>
          <button @click="showStatsModal = false" class="btn-close">×</button>
        </div>

        <div class="modal-body">
          <div v-if="campaignStats" class="stats-details">
            <h4>{{ campaignStats.campaign.title }}</h4>
            
            <div class="stats-grid-modal">
              <div class="stat-box">
                <div class="stat-label">Total Claims</div>
                <div class="stat-value">{{ campaignStats.total_claims }}</div>
              </div>
              <div class="stat-box">
                <div class="stat-label">Unique Users</div>
                <div class="stat-value">{{ campaignStats.unique_claimers }}</div>
              </div>
              <div class="stat-box">
                <div class="stat-label">Total Bonus Value</div>
                <div class="stat-value">₱{{ formatMoney(campaignStats.total_bonus_value) }}</div>
              </div>
              <div class="stat-box">
                <div class="stat-label">Average Bonus</div>
                <div class="stat-value">₱{{ formatMoney(campaignStats.average_bonus) }}</div>
              </div>
              <div class="stat-box" v-if="campaignStats.remaining_claims !== null">
                <div class="stat-label">Remaining Claims</div>
                <div class="stat-value">{{ campaignStats.remaining_claims }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import axios from 'axios';

const loading = ref(true);
const saving = ref(false);
const campaigns = ref([]);
const stats = ref({
  total_campaigns: 0,
  active_campaigns: 0,
  total_claims: 0,
  total_bonus_value: 0
});

const filters = reactive({
  status: '',
  type: '',
  search: ''
});

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showStatsModal = ref(false);
const campaignStats = ref(null);

const form = reactive({
  title: '',
  code: '',
  type: 'bonus',
  description: '',
  value: null,
  percentage: null,
  max_bonus: null,
  min_deposit: null,
  wagering_multiplier: 30,
  bonus_expiry_days: 7,
  max_claims_total: null,
  max_claims_per_user: null,
  min_vip_level: null,
  max_vip_level: null,
  start_date: '',
  end_date: '',
  status: 'active',
  is_featured: false,
  terms: ''
});

let editingCampaignId = null;
let searchTimeout = null;

onMounted(() => {
  loadCampaigns();
  loadStats();
});

const loadCampaigns = async () => {
  try {
    loading.value = true;
    const params = new URLSearchParams();
    if (filters.status) params.append('status', filters.status);
    if (filters.type) params.append('type', filters.type);
    if (filters.search) params.append('search', filters.search);

    const response = await axios.get(`/api/admin/promotions/campaigns?${params.toString()}`);
    campaigns.value = response.data.data;
  } catch (error) {
    console.error('Failed to load campaigns:', error);
    alert('Failed to load campaigns');
  } finally {
    loading.value = false;
  }
};

const loadStats = async () => {
  try {
    const response = await axios.get('/api/admin/promotions/campaigns');
    const allCampaigns = response.data.data;
    
    stats.value = {
      total_campaigns: allCampaigns.length,
      active_campaigns: allCampaigns.filter(c => c.status === 'active').length,
      total_claims: allCampaigns.reduce((sum, c) => sum + (c.claims_count || 0), 0),
      total_bonus_value: allCampaigns.reduce((sum, c) => sum + (c.total_bonus_value || 0), 0)
    };
  } catch (error) {
    console.error('Failed to load stats:', error);
  }
};

const debounceSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadCampaigns();
  }, 500);
};

const editCampaign = (campaign) => {
  editingCampaignId = campaign.id;
  Object.assign(form, {
    title: campaign.title,
    code: campaign.code,
    type: campaign.type,
    description: campaign.description,
    value: campaign.value,
    percentage: campaign.percentage,
    max_bonus: campaign.max_bonus,
    min_deposit: campaign.min_deposit,
    wagering_multiplier: campaign.wagering_multiplier,
    bonus_expiry_days: campaign.bonus_expiry_days,
    max_claims_total: campaign.max_claims_total,
    max_claims_per_user: campaign.max_claims_per_user,
    min_vip_level: campaign.min_vip_level,
    max_vip_level: campaign.max_vip_level,
    start_date: campaign.start_date ? new Date(campaign.start_date).toISOString().slice(0, 16) : '',
    end_date: campaign.end_date ? new Date(campaign.end_date).toISOString().slice(0, 16) : '',
    status: campaign.status,
    is_featured: campaign.is_featured,
    terms: campaign.terms
  });
  showEditModal.value = true;
};

const saveCampaign = async () => {
  try {
    saving.value = true;
    
    const payload = { ...form };
    
    if (showEditModal.value && editingCampaignId) {
      await axios.put(`/api/admin/promotions/campaigns/${editingCampaignId}`, payload);
      alert('Campaign updated successfully!');
    } else {
      await axios.post('/api/admin/promotions/campaigns', payload);
      alert('Campaign created successfully!');
    }
    
    closeModals();
    await Promise.all([loadCampaigns(), loadStats()]);
  } catch (error) {
    console.error('Failed to save campaign:', error);
    alert(error.response?.data?.message || 'Failed to save campaign');
  } finally {
    saving.value = false;
  }
};

const deleteCampaign = async (campaign) => {
  if (!confirm(`Are you sure you want to delete "${campaign.title}"?`)) return;

  try {
    await axios.delete(`/api/admin/promotions/campaigns/${campaign.id}`);
    alert('Campaign deleted successfully!');
    await Promise.all([loadCampaigns(), loadStats()]);
  } catch (error) {
    console.error('Failed to delete campaign:', error);
    alert('Failed to delete campaign');
  }
};

const viewStatistics = async (campaign) => {
  try {
    const response = await axios.get(`/api/admin/promotions/campaigns/${campaign.id}/statistics`);
    campaignStats.value = response.data.data;
    showStatsModal.value = true;
  } catch (error) {
    console.error('Failed to load statistics:', error);
    alert('Failed to load statistics');
  }
};

const closeModals = () => {
  showCreateModal.value = false;
  showEditModal.value = false;
  editingCampaignId = null;
  resetForm();
};

const resetForm = () => {
  Object.assign(form, {
    title: '',
    code: '',
    type: 'bonus',
    description: '',
    value: null,
    percentage: null,
    max_bonus: null,
    min_deposit: null,
    wagering_multiplier: 30,
    bonus_expiry_days: 7,
    max_claims_total: null,
    max_claims_per_user: null,
    min_vip_level: null,
    max_vip_level: null,
    start_date: '',
    end_date: '',
    status: 'active',
    is_featured: false,
    terms: ''
  });
};

const formatType = (type) => {
  const types = {
    bonus: 'Bonus',
    reload: 'Reload',
    cashback: 'Cashback',
    free_spins: 'Free Spins',
    tournament: 'Tournament'
  };
  return types[type] || type;
};

const formatMoney = (amount) => {
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount || 0);
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};
</script>

<style scoped>
.campaigns-page {
  padding: 2rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.page-header h1 {
  font-size: 2rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
}

.subtitle {
  color: #666;
}

.filters-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
  background: white;
  padding: 1.5rem;
  border-radius: 0.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.filter-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  font-size: 0.875rem;
}

.form-select, .form-control {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 0.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  align-items: center;
  gap: 1rem;
}

.stat-icon {
  font-size: 2.5rem;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: bold;
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.875rem;
  color: #666;
}

.table-container {
  background: white;
  border-radius: 0.5rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.loading-state, .empty-state {
  text-align: center;
  padding: 3rem;
}

.spinner {
  border: 4px solid #f3f3f3;
  border-top: 4px solid #667eea;
  border-radius: 50%;
  width: 50px;
  height: 50px;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table th {
  background: #f9fafb;
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  font-size: 0.875rem;
  color: #374151;
  border-bottom: 1px solid #e5e7eb;
}

.data-table td {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.campaign-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.campaign-title {
  font-weight: 600;
}

.campaign-code {
  font-size: 0.75rem;
  color: #666;
}

.type-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.type-badge.bonus {
  background: #dbeafe;
  color: #1e40af;
}

.type-badge.reload {
  background: #dcfce7;
  color: #166534;
}

.type-badge.cashback {
  background: #fef3c7;
  color: #92400e;
}

.type-badge.free_spins {
  background: #fce7f3;
  color: #9f1239;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-badge.active {
  background: #dcfce7;
  color: #166534;
}

.status-badge.scheduled {
  background: #dbeafe;
  color: #1e40af;
}

.status-badge.ended {
  background: #fee2e2;
  color: #991b1b;
}

.date-info, .claims-info {
  font-size: 0.875rem;
}

.date-item, .text-muted {
  color: #666;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-icon {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.25rem;
  padding: 0.25rem;
  transition: transform 0.2s;
}

.btn-icon:hover {
  transform: scale(1.2);
}

.btn-icon.danger:hover {
  filter: hue-rotate(180deg);
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
  padding: 1rem;
}

.modal-content {
  background: white;
  border-radius: 0.5rem;
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-content.large {
  max-width: 900px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  font-size: 1.5rem;
  font-weight: bold;
}

.btn-close {
  background: none;
  border: none;
  font-size: 2rem;
  cursor: pointer;
  color: #666;
  line-height: 1;
}

.modal-body {
  padding: 1.5rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group.col-span-2 {
  grid-column: span 2;
}

.form-group label {
  margin-bottom: 0.5rem;
  font-weight: 600;
  font-size: 0.875rem;
}

.form-control {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: 1.25rem;
  height: 1.25rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 0.375rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #e5e7eb;
  color: #374151;
}

.btn-secondary:hover {
  background: #d1d5db;
}

.stats-grid-modal {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-top: 1rem;
}

.stat-box {
  background: #f9fafb;
  padding: 1rem;
  border-radius: 0.5rem;
  text-align: center;
}

.stat-box .stat-label {
  font-size: 0.875rem;
  color: #666;
  margin-bottom: 0.5rem;
}

.stat-box .stat-value {
  font-size: 1.5rem;
  font-weight: bold;
  color: #111;
}
</style>
