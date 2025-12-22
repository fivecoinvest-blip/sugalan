<template>
  <div class="users-page">
    <!-- Filters -->
    <div class="filters-section">
      <div class="search-box">
        <input 
          v-model="filters.search" 
          @input="debouncedSearch"
          type="text" 
          placeholder="Search by username, email, or phone..."
          class="search-input"
        />
        <span class="search-icon">🔍</span>
      </div>

      <div class="filter-group">
        <select v-model="filters.vip_level" @change="applyFilters" class="filter-select">
          <option value="">All VIP Levels</option>
          <option value="1">Bronze</option>
          <option value="2">Silver</option>
          <option value="3">Gold</option>
          <option value="4">Platinum</option>
          <option value="5">Diamond</option>
        </select>

        <select v-model="filters.status" @change="applyFilters" class="filter-select">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
          <option value="banned">Banned</option>
        </select>

        <select v-model="filters.sort_by" @change="applyFilters" class="filter-select">
          <option value="created_at">Registration Date</option>
          <option value="username">Username</option>
          <option value="total_deposited">Total Deposited</option>
          <option value="total_withdrawn">Total Withdrawn</option>
        </select>

        <button @click="toggleSortOrder" class="sort-btn">
          {{ filters.sort_order === 'desc' ? '↓' : '↑' }}
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading users...</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="loadUsers" class="retry-btn">Try Again</button>
    </div>

    <!-- Users Table -->
    <div v-else class="users-table-container">
      <div class="table-header">
        <h3>{{ pagination.total }} Users</h3>
      </div>

      <table class="users-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Contact</th>
            <th>VIP Level</th>
            <th>Balance</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id">
            <td class="user-id">{{ user.id }}</td>
            
            <td class="user-info">
              <div class="username">{{ user.username }}</div>
              <div class="user-meta">
                <span v-if="user.referral_code" class="ref-code">{{ user.referral_code }}</span>
                <span v-if="user.referred_by" class="referred">Referred</span>
              </div>
            </td>
            
            <td class="contact-info">
              <div v-if="user.email" class="email">{{ user.email }}</div>
              <div v-if="user.phone" class="phone">{{ user.phone }}</div>
              <div class="auth-method">{{ formatAuthMethod(user.auth_method) }}</div>
            </td>
            
            <td>
              <span class="vip-badge" :class="`vip-${user.vip_level?.name?.toLowerCase()}`">
                {{ user.vip_level?.name || 'Bronze' }}
              </span>
            </td>
            
            <td class="balance-cell">
              <div class="balance-item">
                <span class="balance-label">Real:</span>
                <span class="balance-value">₱{{ formatMoney(user.wallet?.real_balance) }}</span>
              </div>
              <div class="balance-item">
                <span class="balance-label">Bonus:</span>
                <span class="balance-value bonus">₱{{ formatMoney(user.wallet?.bonus_balance) }}</span>
              </div>
            </td>
            
            <td>
              <span class="status-badge" :class="`status-${user.status}`">
                {{ user.status }}
              </span>
            </td>
            
            <td class="date-cell">
              {{ formatDate(user.created_at) }}
            </td>
            
            <td class="actions-cell">
              <button @click="viewUser(user)" class="action-btn view-btn" title="View Details">
                👁️
              </button>
              <button @click="openBalanceModal(user)" class="action-btn balance-btn" title="Adjust Balance">
                💰
              </button>
              <button @click="openStatusModal(user)" class="action-btn status-btn" title="Change Status">
                ⚙️
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

    <!-- User Details Modal -->
    <div v-if="showUserModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content large" @click.stop>
        <div class="modal-header">
          <h3>👤 User Details</h3>
          <button @click="closeModals" class="close-btn">✕</button>
        </div>

        <div v-if="selectedUser" class="user-details">
          <div class="detail-section">
            <h4>Basic Information</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="label">Username:</span>
                <span class="value">{{ selectedUser.username }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Email:</span>
                <span class="value">{{ selectedUser.email || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Phone:</span>
                <span class="value">{{ selectedUser.phone || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Auth Method:</span>
                <span class="value">{{ formatAuthMethod(selectedUser.auth_method) }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Status:</span>
                <span class="status-badge" :class="`status-${selectedUser.status}`">
                  {{ selectedUser.status }}
                </span>
              </div>
              <div class="detail-item">
                <span class="label">VIP Level:</span>
                <span class="vip-badge" :class="`vip-${selectedUser.vip_level?.name?.toLowerCase()}`">
                  {{ selectedUser.vip_level?.name || 'Bronze' }}
                </span>
              </div>
            </div>
          </div>

          <div class="detail-section">
            <h4>Financial Summary</h4>
            <div class="financial-grid">
              <div class="financial-card">
                <div class="financial-label">Real Balance</div>
                <div class="financial-value">₱{{ formatMoney(selectedUser.wallet?.real_balance) }}</div>
              </div>
              <div class="financial-card">
                <div class="financial-label">Bonus Balance</div>
                <div class="financial-value">₱{{ formatMoney(selectedUser.wallet?.bonus_balance) }}</div>
              </div>
              <div class="financial-card">
                <div class="financial-label">Total Deposited</div>
                <div class="financial-value">₱{{ formatMoney(selectedUser.total_deposited) }}</div>
              </div>
              <div class="financial-card">
                <div class="financial-label">Total Withdrawn</div>
                <div class="financial-value">₱{{ formatMoney(selectedUser.total_withdrawn) }}</div>
              </div>
            </div>
          </div>

          <div class="detail-section">
            <h4>Referral Information</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="label">Referral Code:</span>
                <span class="value">{{ selectedUser.referral_code || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Referred By:</span>
                <span class="value">{{ selectedUser.referred_by || 'N/A' }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Total Referred:</span>
                <span class="value">{{ selectedUser.referral_count || 0 }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Referral Earnings:</span>
                <span class="value">₱{{ formatMoney(selectedUser.total_referral_earnings) }}</span>
              </div>
            </div>
          </div>

          <div class="detail-section">
            <h4>Account Activity</h4>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="label">Registered:</span>
                <span class="value">{{ formatDate(selectedUser.created_at) }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Last Login:</span>
                <span class="value">{{ formatDate(selectedUser.last_login_at) }}</span>
              </div>
              <div class="detail-item">
                <span class="label">Last Login IP:</span>
                <span class="value">{{ selectedUser.last_login_ip || 'N/A' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Balance Adjustment Modal -->
    <div v-if="showBalanceModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content" @click.stop>
        <h3>💰 Adjust Balance</h3>
        <p>User: <strong>{{ selectedUser?.username }}</strong></p>

        <div class="form-group">
          <label>Balance Type *</label>
          <select v-model="balanceForm.type" required>
            <option value="real">Real Balance</option>
            <option value="bonus">Bonus Balance</option>
          </select>
        </div>

        <div class="form-group">
          <label>Action *</label>
          <select v-model="balanceForm.action" required>
            <option value="add">Add</option>
            <option value="subtract">Subtract</option>
          </select>
        </div>

        <div class="form-group">
          <label>Amount *</label>
          <input 
            v-model="balanceForm.amount" 
            type="number" 
            step="0.01" 
            min="0.01"
            placeholder="0.00"
            required
          />
        </div>

        <div class="form-group">
          <label>Reason *</label>
          <textarea 
            v-model="balanceForm.reason" 
            placeholder="Reason for balance adjustment..."
            rows="3"
            required
          ></textarea>
        </div>

        <div class="modal-actions">
          <button 
            @click="confirmBalanceAdjustment" 
            class="btn-primary"
            :disabled="processing || !isBalanceFormValid"
          >
            {{ processing ? 'Processing...' : 'Confirm Adjustment' }}
          </button>
          <button @click="closeModals" class="btn-secondary" :disabled="processing">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Status Change Modal -->
    <div v-if="showStatusModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content" @click.stop>
        <h3>⚙️ Change User Status</h3>
        <p>User: <strong>{{ selectedUser?.username }}</strong></p>
        <p>Current Status: <span class="status-badge" :class="`status-${selectedUser?.status}`">{{ selectedUser?.status }}</span></p>

        <div class="form-group">
          <label>New Status *</label>
          <select v-model="statusForm.status" required>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="banned">Banned</option>
          </select>
        </div>

        <div v-if="statusForm.status !== 'active'" class="form-group">
          <label>Reason *</label>
          <textarea 
            v-model="statusForm.reason" 
            placeholder="Reason for status change..."
            rows="3"
            required
          ></textarea>
        </div>

        <div class="modal-actions">
          <button 
            @click="confirmStatusChange" 
            class="btn-primary"
            :disabled="processing || !isStatusFormValid"
          >
            {{ processing ? 'Processing...' : 'Confirm Change' }}
          </button>
          <button @click="closeModals" class="btn-secondary" :disabled="processing">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useUserStore } from '../../stores/user';
import { storeToRefs } from 'pinia';

const userStore = useUserStore();
const { users, selectedUser, pagination, loading, error } = storeToRefs(userStore);

const showUserModal = ref(false);
const showBalanceModal = ref(false);
const showStatusModal = ref(false);
const processing = ref(false);

const filters = reactive({
  search: '',
  vip_level: '',
  status: '',
  sort_by: 'created_at',
  sort_order: 'desc',
  page: 1,
});

const balanceForm = reactive({
  type: 'real',
  action: 'add',
  amount: '',
  reason: '',
});

const statusForm = reactive({
  status: 'active',
  reason: '',
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

const isBalanceFormValid = computed(() => {
  return balanceForm.amount > 0 && balanceForm.reason.trim().length > 0;
});

const isStatusFormValid = computed(() => {
  if (statusForm.status === 'active') return true;
  return statusForm.reason.trim().length > 0;
});

onMounted(() => {
  loadUsers();
});

async function loadUsers() {
  await userStore.fetchUsers(filters);
}

function debouncedSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    filters.page = 1;
    loadUsers();
  }, 500);
}

function applyFilters() {
  filters.page = 1;
  loadUsers();
}

function toggleSortOrder() {
  filters.sort_order = filters.sort_order === 'desc' ? 'asc' : 'desc';
  loadUsers();
}

function changePage(page) {
  if (page < 1 || page > pagination.value.last_page) return;
  filters.page = page;
  loadUsers();
}

async function viewUser(user) {
  const result = await userStore.fetchUserDetails(user.id);
  if (result.success) {
    showUserModal.value = true;
  }
}

function openBalanceModal(user) {
  selectedUser.value = user;
  balanceForm.type = 'real';
  balanceForm.action = 'add';
  balanceForm.amount = '';
  balanceForm.reason = '';
  showBalanceModal.value = true;
}

function openStatusModal(user) {
  selectedUser.value = user;
  statusForm.status = user.status;
  statusForm.reason = '';
  showStatusModal.value = true;
}

async function confirmBalanceAdjustment() {
  if (!isBalanceFormValid.value) return;
  
  processing.value = true;

  const data = {
    type: balanceForm.type,
    action: balanceForm.action,
    amount: parseFloat(balanceForm.amount),
    reason: balanceForm.reason,
  };

  const result = await userStore.adjustBalance(selectedUser.value.id, data);
  
  processing.value = false;

  if (result.success) {
    closeModals();
    alert('Balance adjusted successfully!');
    loadUsers();
  } else {
    alert(`Error: ${result.message}`);
  }
}

async function confirmStatusChange() {
  if (!isStatusFormValid.value) return;
  
  processing.value = true;

  const result = await userStore.updateUserStatus(
    selectedUser.value.id,
    statusForm.status
  );
  
  processing.value = false;

  if (result.success) {
    closeModals();
    alert('User status updated successfully!');
    loadUsers();
  } else {
    alert(`Error: ${result.message}`);
  }
}

function closeModals() {
  showUserModal.value = false;
  showBalanceModal.value = false;
  showStatusModal.value = false;
  selectedUser.value = null;
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

function formatAuthMethod(method) {
  const methods = {
    phone: 'Phone',
    metamask: 'MetaMask',
    telegram: 'Telegram',
    guest: 'Guest',
  };
  return methods[method] || method;
}
</script>

<style scoped>
.users-page {
  max-width: 1600px;
}

.filters-section {
  background: white;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.search-box {
  flex: 1;
  min-width: 300px;
  position: relative;
}

.search-input {
  width: 100%;
  padding: 12px 40px 12px 15px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
}

.search-input:focus {
  outline: none;
  border-color: #667eea;
}

.search-icon {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 18px;
}

.filter-group {
  display: flex;
  gap: 10px;
}

.filter-select {
  padding: 10px 15px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  cursor: pointer;
  background: white;
}

.filter-select:focus {
  outline: none;
  border-color: #667eea;
}

.sort-btn {
  width: 44px;
  padding: 10px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  cursor: pointer;
  font-size: 18px;
  transition: all 0.2s;
}

.sort-btn:hover {
  border-color: #667eea;
  background: #f7fafc;
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

.users-table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.table-header {
  padding: 20px;
  border-bottom: 1px solid #e2e8f0;
}

.table-header h3 {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
}

.users-table thead {
  background: #f7fafc;
}

.users-table th {
  padding: 15px 12px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.users-table td {
  padding: 15px 12px;
  border-top: 1px solid #e2e8f0;
  color: #2d3748;
  font-size: 14px;
}

.user-id {
  color: #a0aec0;
  font-weight: 600;
}

.user-info {
  min-width: 150px;
}

.username {
  font-weight: 600;
  margin-bottom: 4px;
}

.user-meta {
  display: flex;
  gap: 6px;
  font-size: 11px;
}

.ref-code {
  background: #e6fffa;
  color: #234e52;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
}

.referred {
  background: #fef5e7;
  color: #7c2d12;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: 600;
}

.contact-info {
  min-width: 180px;
}

.email, .phone {
  font-size: 13px;
  color: #4a5568;
}

.auth-method {
  font-size: 11px;
  color: #a0aec0;
  margin-top: 2px;
}

.vip-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.vip-bronze { background: #fef3c7; color: #92400e; }
.vip-silver { background: #e2e8f0; color: #2d3748; }
.vip-gold { background: #fef9c3; color: #854d0e; }
.vip-platinum { background: #e0e7ff; color: #3730a3; }
.vip-diamond { background: #ddd6fe; color: #5b21b6; }

.balance-cell {
  min-width: 120px;
}

.balance-item {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
  margin: 3px 0;
}

.balance-label {
  color: #718096;
}

.balance-value {
  font-weight: 600;
  color: #48bb78;
}

.balance-value.bonus {
  color: #ed8936;
}

.status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}

.status-active { background: #c6f6d5; color: #22543d; }
.status-suspended { background: #fed7d7; color: #742a2a; }
.status-banned { background: #feb2b2; color: #63171b; }

.date-cell {
  color: #718096;
  font-size: 13px;
  white-space: nowrap;
}

.actions-cell {
  display: flex;
  gap: 5px;
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

.balance-btn {
  background: #c6f6d5;
}

.balance-btn:hover {
  background: #9ae6b4;
}

.status-btn {
  background: #feebc8;
}

.status-btn:hover {
  background: #fbd38d;
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

.modal-content h3 {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 10px;
}

.modal-content > p {
  color: #718096;
  margin-bottom: 20px;
}

.user-details {
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

.financial-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.financial-card {
  background: #f7fafc;
  padding: 15px;
  border-radius: 8px;
  text-align: center;
}

.financial-label {
  font-size: 12px;
  color: #718096;
  margin-bottom: 8px;
}

.financial-value {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #2d3748;
  font-size: 14px;
}

.form-group input, .form-group textarea, .form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
  outline: none;
  border-color: #667eea;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 25px;
}

.modal-actions button {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: #667eea;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #5568d3;
}

.btn-secondary {
  background: #e2e8f0;
  color: #2d3748;
}

.btn-secondary:hover:not(:disabled) {
  background: #cbd5e0;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
