<template>
  <div class="withdrawal-queue">
    <div class="queue-header">
      <div>
        <h2 class="queue-title">💸 Pending Withdrawals</h2>
        <p class="queue-subtitle">Review and process customer withdrawals</p>
      </div>
      <button @click="refreshWithdrawals" class="refresh-btn" :disabled="loading">
        🔄 Refresh
      </button>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading withdrawals...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="refreshWithdrawals" class="retry-btn">Try Again</button>
    </div>

    <div v-else-if="withdrawals.length === 0" class="empty-state">
      <div class="empty-icon">✅</div>
      <h3>All Clear!</h3>
      <p>No pending withdrawals at the moment</p>
    </div>

    <div v-else class="withdrawals-grid">
      <div v-for="withdrawal in withdrawals" :key="withdrawal.id" class="withdrawal-card">
        <div class="withdrawal-header">
          <div class="withdrawal-id">ID: {{ withdrawal.id }}</div>
          <div class="withdrawal-time">{{ formatDate(withdrawal.created_at) }}</div>
        </div>

        <div class="withdrawal-body">
          <div class="user-section">
            <div class="user-info">
              <div class="user-name">{{ withdrawal.user?.username || 'N/A' }}</div>
              <div class="user-meta">
                <span class="user-id">User #{{ withdrawal.user_id }}</span>
                <span class="vip-badge">{{ withdrawal.user?.vip_level?.name || 'Bronze' }}</span>
              </div>
            </div>
          </div>

          <div class="info-row highlight">
            <span class="label">Amount:</span>
            <span class="value amount">₱{{ formatMoney(withdrawal.amount) }}</span>
          </div>

          <div class="info-row">
            <span class="label">GCash Number:</span>
            <span class="value gcash">{{ withdrawal.gcash_number }}</span>
          </div>

          <div class="info-row">
            <span class="label">GCash Name:</span>
            <span class="value">{{ withdrawal.gcash_name }}</span>
          </div>

          <div v-if="withdrawal.user?.phone" class="info-row">
            <span class="label">Phone:</span>
            <span class="value">{{ withdrawal.user.phone }}</span>
          </div>

          <div class="balance-info">
            <div class="balance-item">
              <span class="balance-label">Real Balance:</span>
              <span class="balance-value">₱{{ formatMoney(withdrawal.user?.wallet?.real_balance) }}</span>
            </div>
            <div class="balance-item">
              <span class="balance-label">Bonus Balance:</span>
              <span class="balance-value">₱{{ formatMoney(withdrawal.user?.wallet?.bonus_balance) }}</span>
            </div>
          </div>

          <div v-if="withdrawal.notes" class="notes-section">
            <div class="notes-label">User Notes:</div>
            <div class="notes-content">{{ withdrawal.notes }}</div>
          </div>
        </div>

        <div class="withdrawal-actions">
          <button 
            @click="handleApprove(withdrawal)" 
            class="approve-btn"
            :disabled="processingId === withdrawal.id"
          >
            ✅ Approve & Pay
          </button>
          <button 
            @click="handleReject(withdrawal)" 
            class="reject-btn"
            :disabled="processingId === withdrawal.id"
          >
            ❌ Reject
          </button>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApproveModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content" @click.stop>
        <h3>Approve Withdrawal</h3>
        <p>Confirm GCash payment to <strong>{{ selectedWithdrawal?.user?.username }}</strong></p>
        
        <div class="modal-info">
          <div class="info-item">
            <span>Amount:</span>
            <strong>₱{{ formatMoney(selectedWithdrawal?.amount) }}</strong>
          </div>
          <div class="info-item">
            <span>GCash Number:</span>
            <strong>{{ selectedWithdrawal?.gcash_number }}</strong>
          </div>
          <div class="info-item">
            <span>GCash Name:</span>
            <strong>{{ selectedWithdrawal?.gcash_name }}</strong>
          </div>
        </div>

        <div class="form-group">
          <label>Transaction Reference Number *</label>
          <input 
            v-model="approveForm.transaction_ref" 
            type="text"
            placeholder="GCash transaction reference"
            required
          />
          <small>Enter the GCash transaction reference number</small>
        </div>

        <div class="form-group">
          <label>Admin Notes (optional)</label>
          <textarea 
            v-model="approveForm.notes" 
            placeholder="Any notes about this withdrawal..."
            rows="3"
          ></textarea>
        </div>

        <div class="warning-box">
          ⚠️ Make sure you have sent the GCash payment before approving!
        </div>

        <div class="modal-actions">
          <button 
            @click="confirmApprove" 
            class="btn-primary" 
            :disabled="processing || !approveForm.transaction_ref"
          >
            {{ processing ? 'Processing...' : 'Confirm Payment Sent' }}
          </button>
          <button @click="closeModals" class="btn-secondary" :disabled="processing">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content" @click.stop>
        <h3>Reject Withdrawal</h3>
        <p>Provide a reason for rejecting this withdrawal request</p>

        <div class="form-group">
          <label>Rejection Reason *</label>
          <textarea 
            v-model="rejectReason" 
            placeholder="e.g., Invalid GCash number, wagering requirements not met, etc."
            rows="4"
            required
          ></textarea>
        </div>

        <div class="modal-actions">
          <button 
            @click="confirmReject" 
            class="btn-danger" 
            :disabled="processing || !rejectReason"
          >
            {{ processing ? 'Processing...' : 'Confirm Rejection' }}
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
import { ref, reactive, onMounted } from 'vue';
import { usePaymentStore } from '../../stores/payment';
import { storeToRefs } from 'pinia';

const paymentStore = usePaymentStore();
const { withdrawals, loading, error } = storeToRefs(paymentStore);

const showApproveModal = ref(false);
const showRejectModal = ref(false);
const selectedWithdrawal = ref(null);
const processing = ref(false);
const processingId = ref(null);
const rejectReason = ref('');

const approveForm = reactive({
  transaction_ref: '',
  notes: '',
});

onMounted(() => {
  refreshWithdrawals();
});

async function refreshWithdrawals() {
  await paymentStore.fetchPendingWithdrawals();
}

function handleApprove(withdrawal) {
  selectedWithdrawal.value = withdrawal;
  showApproveModal.value = true;
  approveForm.transaction_ref = '';
  approveForm.notes = '';
}

function handleReject(withdrawal) {
  selectedWithdrawal.value = withdrawal;
  showRejectModal.value = true;
  rejectReason.value = '';
}

async function confirmApprove() {
  if (!selectedWithdrawal.value || !approveForm.transaction_ref) return;
  
  processing.value = true;
  processingId.value = selectedWithdrawal.value.id;

  const data = {
    transaction_reference: approveForm.transaction_ref,
    notes: approveForm.notes || null,
  };

  const result = await paymentStore.approveWithdrawal(selectedWithdrawal.value.id, data);
  
  processing.value = false;
  processingId.value = null;

  if (result.success) {
    closeModals();
    alert('Withdrawal approved successfully!');
  } else {
    alert(`Error: ${result.message}`);
  }
}

async function confirmReject() {
  if (!selectedWithdrawal.value || !rejectReason.value) return;
  
  processing.value = true;
  processingId.value = selectedWithdrawal.value.id;

  const result = await paymentStore.rejectWithdrawal(
    selectedWithdrawal.value.id,
    rejectReason.value
  );
  
  processing.value = false;
  processingId.value = null;

  if (result.success) {
    closeModals();
    alert('Withdrawal rejected successfully!');
  } else {
    alert(`Error: ${result.message}`);
  }
}

function closeModals() {
  showApproveModal.value = false;
  showRejectModal.value = false;
  selectedWithdrawal.value = null;
}

function formatMoney(value) {
  if (!value) return '0.00';
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<style scoped>
.withdrawal-queue {
  max-width: 1400px;
}

.queue-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.queue-title {
  font-size: 24px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 5px;
}

.queue-subtitle {
  color: #718096;
  font-size: 14px;
}

.refresh-btn {
  padding: 10px 20px;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: background 0.2s;
}

.refresh-btn:hover:not(:disabled) {
  background: #5568d3;
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.loading-state, .error-state, .empty-state {
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

.empty-icon {
  font-size: 64px;
  margin-bottom: 15px;
}

.empty-state h3 {
  font-size: 20px;
  color: #2d3748;
  margin-bottom: 8px;
}

.empty-state p {
  color: #718096;
}

.withdrawals-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 20px;
}

.withdrawal-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}

.withdrawal-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.withdrawal-header {
  background: #f7fafc;
  padding: 15px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
}

.withdrawal-id {
  font-weight: 700;
  color: #2d3748;
}

.withdrawal-time {
  font-size: 13px;
  color: #718096;
}

.withdrawal-body {
  padding: 20px;
}

.user-section {
  margin-bottom: 15px;
  padding-bottom: 15px;
  border-bottom: 1px solid #e2e8f0;
}

.user-name {
  font-size: 18px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 5px;
}

.user-meta {
  display: flex;
  gap: 10px;
  align-items: center;
}

.user-id {
  color: #a0aec0;
  font-size: 13px;
}

.vip-badge {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #f7fafc;
}

.info-row.highlight {
  background: #fffbeb;
  margin: 0 -20px;
  padding: 15px 20px;
  border: none;
  border-top: 1px solid #fbbf24;
  border-bottom: 1px solid #fbbf24;
}

.label {
  color: #718096;
  font-size: 14px;
}

.value {
  font-weight: 600;
  color: #2d3748;
}

.amount {
  color: #f59e0b;
  font-size: 20px;
}

.gcash {
  font-family: monospace;
  background: #f7fafc;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 14px;
}

.balance-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin: 15px 0;
  padding: 15px;
  background: #f7fafc;
  border-radius: 8px;
}

.balance-item {
  text-align: center;
}

.balance-label {
  display: block;
  font-size: 12px;
  color: #718096;
  margin-bottom: 5px;
}

.balance-value {
  display: block;
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
}

.notes-section {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid #e2e8f0;
}

.notes-label {
  font-size: 13px;
  color: #718096;
  margin-bottom: 8px;
}

.notes-content {
  background: #f7fafc;
  padding: 12px;
  border-radius: 6px;
  font-size: 14px;
  color: #4a5568;
}

.withdrawal-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 15px 20px;
  background: #f7fafc;
  border-top: 1px solid #e2e8f0;
}

.approve-btn, .reject-btn {
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}

.approve-btn {
  background: #48bb78;
  color: white;
}

.approve-btn:hover:not(:disabled) {
  background: #38a169;
}

.reject-btn {
  background: #fc8181;
  color: white;
}

.reject-btn:hover:not(:disabled) {
  background: #f56565;
}

.approve-btn:disabled, .reject-btn:disabled {
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

.modal-info {
  background: #f7fafc;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.info-item {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
}

.info-item:not(:last-child) {
  border-bottom: 1px solid #e2e8f0;
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

.form-group input, .form-group textarea {
  width: 100%;
  padding: 10px 12px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input:focus, .form-group textarea:focus {
  outline: none;
  border-color: #667eea;
}

.form-group small {
  display: block;
  margin-top: 5px;
  color: #a0aec0;
  font-size: 12px;
}

.warning-box {
  background: #fffbeb;
  border: 2px solid #fbbf24;
  color: #92400e;
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 20px;
  font-size: 14px;
  font-weight: 600;
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
  background: #48bb78;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #38a169;
}

.btn-danger {
  background: #fc8181;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #f56565;
}

.btn-secondary {
  background: #e2e8f0;
  color: #2d3748;
}

.btn-secondary:hover:not(:disabled) {
  background: #cbd5e0;
}

.btn-primary:disabled, .btn-danger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
