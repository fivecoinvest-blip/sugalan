<template>
  <div class="payment-history-page">
    <!-- Filters -->
    <div class="filters-section">
      <div class="filter-group">
        <select v-model="filters.type" @change="applyFilters" class="filter-select">
          <option value="">All Types</option>
          <option value="deposit">Deposits</option>
          <option value="withdrawal">Withdrawals</option>
        </select>

        <select v-model="filters.status" @change="applyFilters" class="filter-select">
          <option value="">All Status</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="cancelled">Cancelled</option>
        </select>

        <input 
          v-model="filters.user_id" 
          type="number"
          placeholder="User ID"
          @input="debouncedSearch"
          class="filter-input"
        />

        <input 
          v-model="filters.date_from" 
          type="date"
          @change="applyFilters"
          class="filter-input"
        />

        <input 
          v-model="filters.date_to" 
          type="date"
          @change="applyFilters"
          class="filter-input"
        />

        <button @click="resetFilters" class="reset-btn">🔄 Reset</button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading payment history...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="loadHistory" class="retry-btn">Try Again</button>
    </div>

    <!-- Payment History Table -->
    <div v-else class="history-table-container">
      <div class="table-header">
        <h3>{{ pagination.total }} Transactions</h3>
        <div class="summary">
          <span class="summary-item">Total Deposits: <strong>₱{{ formatMoney(summary.total_deposits) }}</strong></span>
          <span class="summary-item">Total Withdrawals: <strong>₱{{ formatMoney(summary.total_withdrawals) }}</strong></span>
        </div>
      </div>

      <table class="history-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Type</th>
            <th>User</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Status</th>
            <th>Admin</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in payments" :key="payment.id">
            <td class="payment-id">{{ payment.id }}</td>
            
            <td>
              <span class="type-badge" :class="`type-${payment.type}`">
                {{ payment.type === 'deposit' ? '💰' : '💸' }} {{ capitalizeFirst(payment.type) }}
              </span>
            </td>
            
            <td class="user-cell">
              <div class="username">{{ payment.user?.username }}</div>
              <div class="user-id">ID: {{ payment.user_id }}</div>
            </td>
            
            <td class="amount-cell">
              <span :class="payment.type === 'deposit' ? 'amount-positive' : 'amount-negative'">
                {{ payment.type === 'deposit' ? '+' : '-' }}₱{{ formatMoney(payment.amount) }}
              </span>
            </td>
            
            <td class="method-cell">
              <div v-if="payment.type === 'deposit'">
                {{ payment.payment_method?.name || 'N/A' }}
              </div>
              <div v-else>
                <div>{{ payment.gcash_number }}</div>
                <div class="gcash-name">{{ payment.gcash_account_name }}</div>
              </div>
            </td>
            
            <td>
              <span class="status-badge" :class="`status-${payment.status}`">
                {{ capitalizeFirst(payment.status) }}
              </span>
            </td>
            
            <td class="admin-cell">
              <div v-if="payment.processed_by">
                <div class="admin-name">{{ payment.admin?.email }}</div>
                <div class="processed-date">{{ formatDate(payment.processed_at) }}</div>
              </div>
              <div v-else class="not-processed">-</div>
            </td>
            
            <td class="date-cell">
              {{ formatDate(payment.created_at) }}
            </td>
            
            <td class="actions-cell">
              <button @click="viewDetails(payment)" class="action-btn view-btn" title="View Details">
                👁️
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="pagination">
        <button 
          @click="changePage(pagination.current_page - 1)" 
          :disabled="pagination.current_page === 1"
          class="page-btn"
        >
          ← Previous
        </button>
        
        <div class="page-numbers">
          <button 
            v-for="page in visiblePages" 
            :key="page"
            @click="changePage(page)"
            class="page-btn"
            :class="{ active: page === pagination.current_page }"
          >
            {{ page }}
          </button>
        </div>
        
        <button 
          @click="changePage(pagination.current_page + 1)" 
          :disabled="pagination.current_page === pagination.last_page"
          class="page-btn"
        >
          Next →
        </button>
      </div>
    </div>

    <!-- Payment Details Modal -->
    <div v-if="showDetailsModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content large" @click.stop>
        <div class="modal-header">
          <h3>💳 Payment Details</h3>
          <button @click="closeModal" class="close-btn">✕</button>
        </div>

        <div v-if="selectedPayment" class="payment-details">
          <div class="detail-section">
            <h4>Transaction Information</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="label">Transaction ID:</span>
                <span class="value">#{{ selectedPayment.id }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Type:</span>
                <span class="type-badge" :class="`type-${selectedPayment.type}`">
                  {{ capitalizeFirst(selectedPayment.type) }}
                </span>
              </div>
              <div class="detail-item">
                <span class="label">Amount:</span>
                <span class="value amount">₱{{ formatMoney(selectedPayment.amount) }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Status:</span>
                <span class="status-badge" :class="`status-${selectedPayment.status}`">
                  {{ capitalizeFirst(selectedPayment.status) }}
                </span>
              </div>
            </div>
          </div>

          <div class="detail-section">
            <h4>User Information</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="label">Username:</span>
                <span class="value">{{ selectedPayment.user?.username }}</span>
              </div>
              <div class="detail-item">
                <span class="label">User ID:</span>
                <span class="value">{{ selectedPayment.user_id }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Email:</span>
                <span class="value">{{ selectedPayment.user?.email || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Phone:</span>
                <span class="value">{{ selectedPayment.user?.phone || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <div class="detail-section">
            <h4>Payment Details</h4>
            <div class="detail-grid">
              <div v-if="selectedPayment.type === 'deposit'">
                <div class="detail-item">
                  <span class="label">Payment Method:</span>
                  <span class="value">{{ selectedPayment.payment_method?.name }}</span>
                </div>
                <div class="detail-item">
                  <span class="label">Reference Number:</span>
                  <span class="value">{{ selectedPayment.reference_number }}</span>
                </div>
                <div v-if="selectedPayment.screenshot_path" class="detail-item full-width">
                  <span class="label">Screenshot:</span>
                  <img :src="getScreenshotUrl(selectedPayment.screenshot_path)" alt="Payment Screenshot" class="screenshot-img" />
                </div>
              </div>
              <div v-else>
                <div class="detail-item">
                  <span class="label">GCash Number:</span>
                  <span class="value">{{ selectedPayment.gcash_number }}</span>
                </div>
                <div class="detail-item">
                  <span class="label">GCash Account Name:</span>
                  <span class="value">{{ selectedPayment.gcash_account_name }}</span>
                </div>
                <div v-if="selectedPayment.transaction_reference" class="detail-item">
                  <span class="label">Transaction Reference:</span>
                  <span class="value">{{ selectedPayment.transaction_reference }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="selectedPayment.processed_by" class="detail-section">
            <h4>Processing Information</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="label">Processed By:</span>
                <span class="value">{{ selectedPayment.admin?.email }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Processed At:</span>
                <span class="value">{{ formatDate(selectedPayment.processed_at) }}</span>
              </div>
              <div v-if="selectedPayment.notes" class="detail-item full-width">
                <span class="label">Notes:</span>
                <span class="value">{{ selectedPayment.notes }}</span>
              </div>
              <div v-if="selectedPayment.rejection_reason" class="detail-item full-width">
                <span class="label">Rejection Reason:</span>
                <span class="value rejection">{{ selectedPayment.rejection_reason }}</span>
              </div>
            </div>
          </div>

          <div class="detail-section">
            <h4>Timestamps</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="label">Created:</span>
                <span class="value">{{ formatDate(selectedPayment.created_at) }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Updated:</span>
                <span class="value">{{ formatDate(selectedPayment.updated_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';

const payments = ref([]);
const selectedPayment = ref(null);
const showDetailsModal = ref(false);
const loading = ref(false);
const error = ref(null);

const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
});

const summary = ref({
  total_deposits: 0,
  total_withdrawals: 0,
});

const filters = reactive({
  type: '',
  status: '',
  user_id: '',
  date_from: '',
  date_to: '',
  page: 1,
});

let searchTimeout = null;

const visiblePages = computed(() => {
  const pages = [];
  const current = pagination.value.current_page;
  const last = pagination.value.last_page;
  
  for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
    pages.push(i);
  }
  
  return pages;
});

onMounted(() => {
  loadHistory();
});

async function loadHistory() {
  loading.value = true;
  error.value = null;

  try {
    const params = {
      page: filters.page,
      per_page: pagination.value.per_page,
      type: filters.type || null,
      status: filters.status || null,
      user_id: filters.user_id || null,
      date_from: filters.date_from || null,
      date_to: filters.date_to || null,
    };

    const response = await axios.get('/api/admin/payments/history', { params });
    payments.value = response.data.data;
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      per_page: response.data.per_page,
      total: response.data.total,
    };
    
    // Calculate summary
    summary.value.total_deposits = response.data.summary?.total_deposits || 0;
    summary.value.total_withdrawals = response.data.summary?.total_withdrawals || 0;
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to load payment history';
    console.error('Error loading history:', err);
  } finally {
    loading.value = false;
  }
}

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    filters.page = 1;
    loadHistory();
  }, 500);
}

function applyFilters() {
  filters.page = 1;
  loadHistory();
}

function resetFilters() {
  filters.type = '';
  filters.status = '';
  filters.user_id = '';
  filters.date_from = '';
  filters.date_to = '';
  filters.page = 1;
  loadHistory();
}

function changePage(page) {
  if (page < 1 || page > pagination.value.last_page) return;
  filters.page = page;
  loadHistory();
}

function viewDetails(payment) {
  selectedPayment.value = payment;
  showDetailsModal.value = true;
}

function closeModal() {
  showDetailsModal.value = false;
  selectedPayment.value = null;
}

function getScreenshotUrl(path) {
  return `/storage/${path}`;
}

function formatMoney(value) {
  if (!value) return '0.00';
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function capitalizeFirst(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>

<style scoped>
.payment-history-page {
  max-width: 1600px;
}

.filters-section {
  background: white;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 20px;
}

.filter-group {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-select, .filter-input {
  padding: 10px 15px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  background: white;
}

.filter-select:focus, .filter-input:focus {
  outline: none;
  border-color: #667eea;
}

.reset-btn {
  padding: 10px 20px;
  background: #f7fafc;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.reset-btn:hover {
  background: #edf2f7;
  border-color: #cbd5e0;
}

.loading-state, .error-state {
  text-align: center;
  padding: 60px 20px;
  background: white;
  border-radius: 12px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #e2e8f0;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-state {
  color: #e53e3e;
}

.retry-btn {
  margin-top: 15px;
  padding: 10px 24px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}

.history-table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.table-header {
  padding: 20px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.summary {
  display: flex;
  gap: 30px;
}

.summary-item {
  font-size: 14px;
  color: #718096;
}

.summary-item strong {
  color: #2d3748;
  font-weight: 700;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
}

.history-table thead {
  background: #f7fafc;
}

.history-table th {
  padding: 15px 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.history-table td {
  padding: 15px 12px;
  border-top: 1px solid #e2e8f0;
  color: #2d3748;
  font-size: 14px;
}

.payment-id {
  color: #a0aec0;
  font-weight: 600;
}

.type-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.type-deposit { background: #c6f6d5; color: #22543d; }
.type-withdrawal { background: #fed7d7; color: #742a2a; }

.user-cell .username {
  font-weight: 600;
}

.user-cell .user-id {
  font-size: 12px;
  color: #718096;
}

.amount-cell {
  font-weight: 700;
  font-size: 15px;
}

.amount-positive { color: #48bb78; }
.amount-negative { color: #f56565; }

.method-cell .gcash-name {
  font-size: 12px;
  color: #718096;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.status-approved { background: #c6f6d5; color: #22543d; }
.status-rejected { background: #fed7d7; color: #742a2a; }
.status-cancelled { background: #feebc8; color: #7c2d12; }

.admin-cell .admin-name {
  font-weight: 600;
  font-size: 13px;
}

.admin-cell .processed-date {
  font-size: 11px;
  color: #718096;
}

.not-processed {
  color: #a0aec0;
}

.date-cell {
  color: #718096;
  font-size: 13px;
  white-space: nowrap;
}

.actions-cell {
  text-align: center;
}

.action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.view-btn {
  background: #bee3f8;
}

.view-btn:hover {
  background: #90cdf4;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
  padding: 20px;
  border-top: 1px solid #e2e8f0;
}

.page-numbers {
  display: flex;
  gap: 5px;
}

.page-btn {
  padding: 8px 16px;
  border: 2px solid #e2e8f0;
  border-radius: 6px;
  background: white;
  color: #4a5568;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
  border-color: #667eea;
  background: #f7fafc;
}

.page-btn.active {
  background: #667eea;
  color: white;
  border-color: #667eea;
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-content {
  background: white;
  border-radius: 12px;
  padding: 30px;
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-content.large {
  max-width: 800px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.close-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: #f7fafc;
  border-radius: 50%;
  cursor: pointer;
  font-size: 18px;
  transition: background 0.2s;
}

.close-btn:hover {
  background: #e2e8f0;
}

.payment-details {
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.detail-section h4 {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid #e2e8f0;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-item.full-width {
  grid-column: 1 / -1;
}

.detail-item .label {
  font-size: 12px;
  color: #718096;
  font-weight: 600;
}

.detail-item .value {
  font-size: 14px;
  color: #2d3748;
  font-weight: 600;
}

.detail-item .value.amount {
  font-size: 18px;
  color: #48bb78;
}

.detail-item .value.rejection {
  color: #e53e3e;
}

.screenshot-img {
  max-width: 100%;
  border-radius: 8px;
  border: 2px solid #e2e8f0;
  margin-top: 5px;
}
</style>
