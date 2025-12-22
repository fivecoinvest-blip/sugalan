<template>
  <div class="admin-dashboard min-h-screen bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
        <div class="flex items-center space-x-4">
          <span class="text-gray-700">{{ adminUser?.full_name }}</span>
          <button 
            @click="logout" 
            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
          >
            Logout
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium">Pending Deposits</h3>
          <p class="text-3xl font-bold text-blue-600 mt-2">{{ statistics.deposits?.pending || 0 }}</p>
          <p class="text-gray-600 text-sm mt-2">₱{{ formatAmount(statistics.deposits?.pending_amount) }}</p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium">Pending Withdrawals</h3>
          <p class="text-3xl font-bold text-orange-600 mt-2">{{ statistics.withdrawals?.pending || 0 }}</p>
          <p class="text-gray-600 text-sm mt-2">₱{{ formatAmount(statistics.withdrawals?.pending_amount) }}</p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium">Approved Today</h3>
          <p class="text-3xl font-bold text-green-600 mt-2">{{ statistics.deposits?.approved || 0 }}</p>
          <p class="text-gray-600 text-sm mt-2">₱{{ formatAmount(statistics.deposits?.total_amount) }}</p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-gray-500 text-sm font-medium">Withdrawals Today</h3>
          <p class="text-3xl font-bold text-purple-600 mt-2">{{ statistics.withdrawals?.approved || 0 }}</p>
          <p class="text-gray-600 text-sm mt-2">₱{{ formatAmount(statistics.withdrawals?.total_amount) }}</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px">
            <button
              @click="activeTab = 'deposits'"
              :class="[
                'px-6 py-4 text-sm font-medium border-b-2',
                activeTab === 'deposits' 
                  ? 'border-blue-500 text-blue-600' 
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              Pending Deposits ({{ pendingDeposits.length }})
            </button>
            <button
              @click="activeTab = 'withdrawals'"
              :class="[
                'px-6 py-4 text-sm font-medium border-b-2',
                activeTab === 'withdrawals' 
                  ? 'border-blue-500 text-blue-600' 
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              Pending Withdrawals ({{ pendingWithdrawals.length }})
            </button>
          </nav>
        </div>

        <!-- Deposits Tab -->
        <div v-if="activeTab === 'deposits'" class="p-6">
          <div v-if="pendingDeposits.length === 0" class="text-center text-gray-500 py-8">
            No pending deposits
          </div>
          
          <div v-else class="space-y-4">
            <div 
              v-for="deposit in pendingDeposits" 
              :key="deposit.id"
              class="border rounded-lg p-4 hover:bg-gray-50"
            >
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <div class="flex items-center space-x-3 mb-2">
                    <span class="font-semibold text-lg">₱{{ formatAmount(deposit.amount) }}</span>
                    <span class="text-sm text-gray-500">ID: {{ deposit.id }}</span>
                  </div>
                  
                  <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <span class="text-gray-600">User:</span>
                      <span class="font-medium ml-2">{{ deposit.user?.phone_number || `ID: ${deposit.user_id}` }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Reference:</span>
                      <span class="font-medium ml-2">{{ deposit.reference_number }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">GCash Account:</span>
                      <span class="font-medium ml-2">{{ deposit.gcash_account?.account_name }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Submitted:</span>
                      <span class="font-medium ml-2">{{ formatDate(deposit.created_at) }}</span>
                    </div>
                  </div>
                  
                  <div v-if="deposit.screenshot_url" class="mt-3">
                    <a 
                      :href="`/storage/${deposit.screenshot_url}`" 
                      target="_blank"
                      class="text-blue-600 hover:underline text-sm"
                    >
                      View Screenshot →
                    </a>
                  </div>
                </div>

                <div class="flex flex-col space-y-2 ml-4">
                  <button 
                    @click="approveDeposit(deposit.id)"
                    :disabled="processing"
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
                  >
                    Approve
                  </button>
                  <button 
                    @click="rejectDeposit(deposit.id)"
                    :disabled="processing"
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                  >
                    Reject
                  </button>
                  <button 
                    @click="viewDepositDetails(deposit.id)"
                    class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700"
                  >
                    Details
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Withdrawals Tab -->
        <div v-if="activeTab === 'withdrawals'" class="p-6">
          <div v-if="pendingWithdrawals.length === 0" class="text-center text-gray-500 py-8">
            No pending withdrawals
          </div>
          
          <div v-else class="space-y-4">
            <div 
              v-for="withdrawal in pendingWithdrawals" 
              :key="withdrawal.id"
              class="border rounded-lg p-4 hover:bg-gray-50"
            >
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <div class="flex items-center space-x-3 mb-2">
                    <span class="font-semibold text-lg">₱{{ formatAmount(withdrawal.amount) }}</span>
                    <span class="text-sm text-gray-500">ID: {{ withdrawal.id }}</span>
                  </div>
                  
                  <div class="grid grid-cols-2 gap-4 text-sm mb-3">
                    <div>
                      <span class="text-gray-600">User:</span>
                      <span class="font-medium ml-2">{{ withdrawal.user?.phone_number || `ID: ${withdrawal.user_id}` }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">GCash:</span>
                      <span class="font-medium ml-2">{{ withdrawal.gcash_number }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Name:</span>
                      <span class="font-medium ml-2">{{ withdrawal.gcash_name }}</span>
                    </div>
                    <div>
                      <span class="text-gray-600">Submitted:</span>
                      <span class="font-medium ml-2">{{ formatDate(withdrawal.created_at) }}</span>
                    </div>
                  </div>

                  <!-- Validation Checks -->
                  <div class="flex space-x-4 text-sm">
                    <span :class="withdrawal.wagering_complete ? 'text-green-600' : 'text-red-600'">
                      {{ withdrawal.wagering_complete ? '✓' : '✗' }} Wagering
                    </span>
                    <span :class="withdrawal.phone_verified ? 'text-green-600' : 'text-red-600'">
                      {{ withdrawal.phone_verified ? '✓' : '✗' }} Phone Verified
                    </span>
                    <span :class="withdrawal.vip_limit_passed ? 'text-green-600' : 'text-red-600'">
                      {{ withdrawal.vip_limit_passed ? '✓' : '✗' }} VIP Limit
                    </span>
                  </div>
                </div>

                <div class="flex flex-col space-y-2 ml-4">
                  <button 
                    @click="approveWithdrawal(withdrawal.id)"
                    :disabled="processing || !canApproveWithdrawal(withdrawal)"
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
                  >
                    Approve
                  </button>
                  <button 
                    @click="rejectWithdrawal(withdrawal.id)"
                    :disabled="processing"
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                  >
                    Reject
                  </button>
                  <button 
                    @click="viewWithdrawalDetails(withdrawal.id)"
                    class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700"
                  >
                    Details
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Approve Withdrawal Modal -->
    <div v-if="showApproveModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <h3 class="text-lg font-bold mb-4">Approve Withdrawal</h3>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            GCash Reference Number *
          </label>
          <input 
            v-model="approveForm.gcash_reference"
            type="text"
            placeholder="Enter GCash reference"
            class="w-full px-3 py-2 border rounded"
          />
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Admin Notes (Optional)
          </label>
          <textarea 
            v-model="approveForm.admin_notes"
            rows="3"
            class="w-full px-3 py-2 border rounded"
          ></textarea>
        </div>
        <div class="flex space-x-3">
          <button 
            @click="confirmApproveWithdrawal"
            :disabled="!approveForm.gcash_reference || processing"
            class="flex-1 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
          >
            Confirm
          </button>
          <button 
            @click="showApproveModal = false"
            class="flex-1 px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 max-w-md w-full">
        <h3 class="text-lg font-bold mb-4">Reject {{ rejectType }}</h3>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Rejection Reason *
          </label>
          <textarea 
            v-model="rejectForm.reason"
            rows="4"
            placeholder="Enter reason for rejection..."
            class="w-full px-3 py-2 border rounded"
          ></textarea>
        </div>
        <div class="flex space-x-3">
          <button 
            @click="confirmReject"
            :disabled="!rejectForm.reason || processing"
            class="flex-1 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
          >
            Confirm Rejection
          </button>
          <button 
            @click="showRejectModal = false"
            class="flex-1 px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const adminUser = ref(null);
const activeTab = ref('deposits');
const pendingDeposits = ref([]);
const pendingWithdrawals = ref([]);
const statistics = ref({});
const processing = ref(false);

const showApproveModal = ref(false);
const showRejectModal = ref(false);
const rejectType = ref('');
const selectedId = ref(null);

const approveForm = ref({
  gcash_reference: '',
  admin_notes: ''
});

const rejectForm = ref({
  reason: ''
});

onMounted(async () => {
  await loadAdminProfile();
  await loadData();
  
  // Auto-refresh every 30 seconds
  setInterval(loadData, 30000);
});

async function loadAdminProfile() {
  try {
    const response = await axios.get('/api/admin/auth/profile');
    adminUser.value = response.data.admin;
  } catch (error) {
    console.error('Failed to load admin profile:', error);
  }
}

async function loadData() {
  await Promise.all([
    loadPendingDeposits(),
    loadPendingWithdrawals(),
    loadStatistics()
  ]);
}

async function loadPendingDeposits() {
  try {
    const response = await axios.get('/api/admin/payments/deposits/pending');
    pendingDeposits.value = response.data.deposits;
  } catch (error) {
    console.error('Failed to load deposits:', error);
  }
}

async function loadPendingWithdrawals() {
  try {
    const response = await axios.get('/api/admin/payments/withdrawals/pending');
    pendingWithdrawals.value = response.data.withdrawals;
  } catch (error) {
    console.error('Failed to load withdrawals:', error);
  }
}

async function loadStatistics() {
  try {
    const response = await axios.get('/api/admin/payments/statistics?period=today');
    statistics.value = response.data;
  } catch (error) {
    console.error('Failed to load statistics:', error);
  }
}

async function approveDeposit(depositId) {
  if (!confirm('Are you sure you want to approve this deposit?')) return;
  
  processing.value = true;
  try {
    await axios.post(`/api/admin/payments/deposits/${depositId}/approve`, {
      admin_notes: ''
    });
    alert('Deposit approved successfully');
    await loadData();
  } catch (error) {
    alert('Failed to approve deposit: ' + (error.response?.data?.error || error.message));
  } finally {
    processing.value = false;
  }
}

function rejectDeposit(depositId) {
  selectedId.value = depositId;
  rejectType.value = 'Deposit';
  rejectForm.value.reason = '';
  showRejectModal.value = true;
}

function approveWithdrawal(withdrawalId) {
  selectedId.value = withdrawalId;
  approveForm.value.gcash_reference = '';
  approveForm.value.admin_notes = '';
  showApproveModal.value = true;
}

async function confirmApproveWithdrawal() {
  processing.value = true;
  try {
    await axios.post(`/api/admin/payments/withdrawals/${selectedId.value}/approve`, approveForm.value);
    alert('Withdrawal approved successfully');
    showApproveModal.value = false;
    await loadData();
  } catch (error) {
    alert('Failed to approve withdrawal: ' + (error.response?.data?.error || error.message));
  } finally {
    processing.value = false;
  }
}

function rejectWithdrawal(withdrawalId) {
  selectedId.value = withdrawalId;
  rejectType.value = 'Withdrawal';
  rejectForm.value.reason = '';
  showRejectModal.value = true;
}

async function confirmReject() {
  processing.value = true;
  try {
    const endpoint = rejectType.value === 'Deposit' 
      ? `/api/admin/payments/deposits/${selectedId.value}/reject`
      : `/api/admin/payments/withdrawals/${selectedId.value}/reject`;
    
    await axios.post(endpoint, rejectForm.value);
    alert(`${rejectType.value} rejected successfully`);
    showRejectModal.value = false;
    await loadData();
  } catch (error) {
    alert(`Failed to reject ${rejectType.value.toLowerCase()}: ` + (error.response?.data?.error || error.message));
  } finally {
    processing.value = false;
  }
}

function viewDepositDetails(depositId) {
  // TODO: Open deposit details modal
  console.log('View deposit details:', depositId);
}

function viewWithdrawalDetails(withdrawalId) {
  // TODO: Open withdrawal details modal
  console.log('View withdrawal details:', withdrawalId);
}

function canApproveWithdrawal(withdrawal) {
  return withdrawal.wagering_complete && 
         withdrawal.phone_verified && 
         withdrawal.vip_limit_passed;
}

function formatAmount(amount) {
  return parseFloat(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

async function logout() {
  try {
    await axios.post('/api/admin/auth/logout');
    window.location.href = '/admin/login';
  } catch (error) {
    console.error('Logout failed:', error);
  }
}
</script>
