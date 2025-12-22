<template>
  <div class="deposit-queue">
    <div class="queue-header">
      <div>
        <h2 class="queue-title">💰 Pending Deposits</h2>
        <p class="queue-subtitle">Review and approve customer deposits</p>
      </div>
      <button @click="refreshDeposits" class="refresh-btn" :disabled="loading">
        🔄 Refresh
      </button>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading deposits...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
      <button @click="refreshDeposits" class="retry-btn">Try Again</button>
    </div>

    <div v-else-if="deposits.length === 0" class="empty-state">
      <div class="empty-icon">✅</div>
      <h3>All Clear!</h3>
      <p>No pending deposits at the moment</p>
    </div>

    <div v-else class="deposits-grid">
      <div v-for="deposit in deposits" :key="deposit.id" class="deposit-card">
        <div class="deposit-header">
          <div class="deposit-id">ID: {{ deposit.id }}</div>
          <div class="deposit-time">{{ formatDate(deposit.created_at) }}</div>
        </div>

        <div class="deposit-body">
          <div class="info-row">
            <span class="label">User:</span>
            <span class="value">
              {{ deposit.user?.username || 'N/A' }}
              <span class="user-id">(#{{ deposit.user_id }})</span>
            </span>
          </div>

          <div class="info-row">
            <span class="label">Amount:</span>
            <span class="value amount">₱{{ formatMoney(deposit.amount) }}</span>
          </div>

          <div class="info-row">
            <span class="label">Payment Method:</span>
            <span class="value">{{ deposit.payment_method?.name || 'GCash' }}</span>
          </div>

          <div class="info-row">
            <span class="label">Reference:</span>
            <span class="value reference">{{ deposit.reference_number }}</span>
          </div>

          <div v-if="deposit.screenshot_path" class="screenshot-section">
            <div class="label">Proof of Payment:</div>
            <img 
              :src="getScreenshotUrl(deposit.screenshot_path)" 
              alt="Proof of payment"
              class="screenshot"
              @click="openScreenshot(deposit.screenshot_path)"
            />
          </div>
        </div>

        <div class="deposit-actions">
          <button 
            @click="handleApprove(deposit)" 
            class="approve-btn"
            :disabled="processingId === deposit.id"
          >
            ✅ Approve
          </button>
          <button 
            @click="handleReject(deposit)" 
            class="reject-btn"
            :disabled="processingId === deposit.id"
          >
            ❌ Reject
          </button>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApproveModal" class="modal-overlay" @click="closeModals">
      <div class="modal-content" @click.stop>
        <h3>Approve Deposit</h3>
        <p>Confirm deposit approval for <strong>{{ selectedDeposit?.user?.username }}</strong></p>
        
        <div class="modal-info">
          <div class="info-item">
            <span>Amount:</span>
            <strong>₱{{ formatMoney(selectedDeposit?.amount) }}</strong>
          </div>
          <div class="info-item">
            <span>Reference:</span>
            <strong>{{ selectedDeposit?.reference_number }}</strong>
          </div>
        </div>

        <div class="form-group">
          <label>Actual Amount Received (optional)</label>
          <input 
            v-model="approveForm.actual_amount" 
            type="number" 
            step="0.01"
            :placeholder="selectedDeposit?.amount"
          />
          <small>Leave empty if amount matches exactly</small>
        </div>

        <div class="form-group">
          <label>Admin Notes (optional)</label>
          <textarea 
            v-model="approveForm.notes" 
            placeholder="Any notes about this deposit..."
            rows="3"
          ></textarea>
        </div>

        <div class="modal-actions">
          <button @click="confirmApprove" class="btn-primary" :disabled="processing">
            {{ processing ? 'Processing...' : 'Confirm Approval' }}
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
        <h3>Reject Deposit</h3>
        <p>Provide a reason for rejecting this deposit</p>

        <div class="form-group">
          <label>Rejection Reason *</label>
          <textarea 
            v-model="rejectReason" 
            placeholder="e.g., Invalid reference number, screenshot not clear, etc."
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

    <!-- Screenshot Modal -->
    <div v-if="showScreenshotModal" class="modal-overlay" @click="closeScreenshotModal">
      <div class="screenshot-modal" @click.stop>
        <button @click="closeScreenshotModal" class="close-btn">✕</button>
        <img :src="currentScreenshot" alt="Full screenshot" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { usePaymentStore } from '../../stores/payment';
import { storeToRefs } from 'pinia';

const paymentStore = usePaymentStore();
const { deposits, loading, error } = storeToRefs(paymentStore);

const showApproveModal = ref(false);
const showRejectModal = ref(false);
const showScreenshotModal = ref(false);
const selectedDeposit = ref(null);
const processing = ref(false);
const processingId = ref(null);
const rejectReason = ref('');
const currentScreenshot = ref('');

const approveForm = reactive({
  actual_amount: '',
  notes: '',
});

onMounted(() => {
  refreshDeposits();
});

async function refreshDeposits() {
  await paymentStore.fetchPendingDeposits();
}

function handleApprove(deposit) {
  selectedDeposit.value = deposit;
  showApproveModal.value = true;
  approveForm.actual_amount = '';
  approveForm.notes = '';
}

function handleReject(deposit) {
  selectedDeposit.value = deposit;
  showRejectModal.value = true;
  rejectReason.value = '';
}

async function confirmApprove() {
  if (!selectedDeposit.value) return;
  
  processing.value = true;
  processingId.value = selectedDeposit.value.id;

  const data = {
    actual_amount: approveForm.actual_amount || selectedDeposit.value.amount,
    notes: approveForm.notes || null,
  };

  const result = await paymentStore.approveDeposit(selectedDeposit.value.id, data);
  
  processing.value = false;
  processingId.value = null;

  if (result.success) {
    closeModals();
    // Show success message (you can add a toast notification here)
    alert('Deposit approved successfully!');
  } else {
    alert(`Error: ${result.message}`);
  }
}

async function confirmReject() {
  if (!selectedDeposit.value || !rejectReason.value) return;
  
  processing.value = true;
  processingId.value = selectedDeposit.value.id;

  const result = await paymentStore.rejectDeposit(
    selectedDeposit.value.id,
    rejectReason.value
  );
  
  processing.value = false;
  processingId.value = null;

  if (result.success) {
    closeModals();
    alert('Deposit rejected successfully!');
  } else {
    alert(`Error: ${result.message}`);
  }
}

function closeModals() {
  showApproveModal.value = false;
  showRejectModal.value = false;
  selectedDeposit.value = null;
}

function openScreenshot(path) {
  currentScreenshot.value = getScreenshotUrl(path);
  showScreenshotModal.value = true;
}

function closeScreenshotModal() {
  showScreenshotModal.value = false;
  currentScreenshot.value = '';
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
.deposit-queue {
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

.deposits-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 20px;
}

.deposit-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}

.deposit-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.deposit-header {
  background: #f7fafc;
  padding: 15px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
}

.deposit-id {
  font-weight: 700;
  color: #2d3748;
}

.deposit-time {
  font-size: 13px;
  color: #718096;
}

.deposit-body {
  padding: 20px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #f7fafc;
}

.info-row:last-child {
  border-bottom: none;
}

.label {
  color: #718096;
  font-size: 14px;
}

.value {
  font-weight: 600;
  color: #2d3748;
}

.user-id {
  color: #a0aec0;
  font-weight: 400;
  font-size: 13px;
}

.amount {
  color: #48bb78;
  font-size: 18px;
}

.reference {
  font-family: monospace;
  background: #f7fafc;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 13px;
}

.screenshot-section {
  margin-top: 15px;
  padding-top: 15px;
  border-top: 1px solid #e2e8f0;
}

.screenshot {
  width: 100%;
  max-height: 300px;
  object-fit: contain;
  border-radius: 8px;
  margin-top: 10px;
  cursor: pointer;
  border: 2px solid #e2e8f0;
  transition: border-color 0.2s;
}

.screenshot:hover {
  border-color: #667eea;
}

.deposit-actions {
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

/* Screenshot Modal */
.screenshot-modal {
  background: white;
  border-radius: 12px;
  padding: 20px;
  max-width: 90vw;
  max-height: 90vh;
  position: relative;
  overflow: auto;
}

.screenshot-modal img {
  max-width: 100%;
  height: auto;
  display: block;
}

.close-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 40px;
  height: 40px;
  background: rgba(0, 0, 0, 0.7);
  color: white;
  border: none;
  border-radius: 50%;
  font-size: 20px;
  cursor: pointer;
  z-index: 10;
}

.close-btn:hover {
  background: rgba(0, 0, 0, 0.9);
}
</style>
