<template>
  <div class="deposit-page">
    <div class="container">
      <div class="page-header">
        <router-link to="/wallet" class="back-btn">← Back to Wallet</router-link>
        <h1 class="page-title">Deposit Funds</h1>
      </div>

      <div class="deposit-content">
        <!-- Left: Instructions -->
        <div class="instructions-section">
          <h2>📱 How to Deposit via GCash</h2>
          
          <div class="steps">
            <div class="step">
              <div class="step-number">1</div>
              <div class="step-content">
                <h3>Send to GCash Account</h3>
                <p>Transfer your desired amount to one of our official GCash accounts listed on the right.</p>
              </div>
            </div>

            <div class="step">
              <div class="step-number">2</div>
              <div class="step-content">
                <h3>Take Screenshot</h3>
                <p>Capture a clear screenshot of your successful GCash transaction showing the reference number and amount.</p>
              </div>
            </div>

            <div class="step">
              <div class="step-number">3</div>
              <div class="step-content">
                <h3>Submit Proof</h3>
                <p>Fill out the form with your transaction details and upload the screenshot.</p>
              </div>
            </div>

            <div class="step">
              <div class="step-number">4</div>
              <div class="step-content">
                <h3>Wait for Approval</h3>
                <p>Our team will verify and credit your account within 5-15 minutes.</p>
              </div>
            </div>
          </div>

          <div class="info-box">
            <div class="info-icon">ℹ️</div>
            <div class="info-content">
              <strong>Important Notes:</strong>
              <ul>
                <li>Minimum deposit: ₱100</li>
                <li>Maximum deposit: ₱50,000 per transaction</li>
                <li>Processing time: 5-15 minutes during business hours</li>
                <li>Keep your screenshot until funds are credited</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Right: Deposit Form -->
        <div class="deposit-form-section">
          <div class="gcash-accounts">
            <h3>Available GCash Accounts</h3>
            <div 
              v-for="account in gcashAccounts" 
              :key="account.id"
              @click="selectedAccount = account"
              class="gcash-account-card"
              :class="{ selected: selectedAccount?.id === account.id }"
            >
              <div class="account-info">
                <div class="account-name">{{ account.account_name }}</div>
                <div class="account-number">{{ account.account_number }}</div>
              </div>
              <div v-if="selectedAccount?.id === account.id" class="selected-badge">✓</div>
            </div>
          </div>

          <form @submit.prevent="submitDeposit" class="deposit-form">
            <div class="form-group">
              <label>Amount (₱)</label>
              <input 
                v-model.number="form.amount"
                type="number"
                min="100"
                max="50000"
                step="1"
                placeholder="Enter amount"
                required
              />
              <div class="quick-amounts">
                <button 
                  v-for="amount in [100, 500, 1000, 5000]" 
                  :key="amount"
                  type="button"
                  @click="form.amount = amount"
                  class="quick-amount-btn"
                >
                  ₱{{ amount }}
                </button>
              </div>
            </div>

            <div class="form-group">
              <label>GCash Reference Number</label>
              <input 
                v-model="form.reference_number"
                type="text"
                placeholder="Enter reference number from receipt"
                required
              />
            </div>

            <div class="form-group">
              <label>Upload Screenshot</label>
              <div class="file-upload">
                <input 
                  ref="fileInput"
                  type="file"
                  accept="image/*"
                  @change="handleFileUpload"
                  hidden
                />
                <button 
                  type="button" 
                  @click="$refs.fileInput.click()"
                  class="upload-btn"
                >
                  {{ form.screenshot ? '✓ File Selected' : '📷 Choose File' }}
                </button>
                <span v-if="form.screenshot" class="file-name">
                  {{ form.screenshot.name }}
                </span>
              </div>
              <p class="help-text">Maximum file size: 5MB (JPG, PNG)</p>
            </div>

            <div class="form-group">
              <label>Additional Notes (Optional)</label>
              <textarea 
                v-model="form.notes"
                placeholder="Any additional information..."
                rows="3"
              ></textarea>
            </div>

            <div v-if="error" class="error-message">{{ error }}</div>
            <div v-if="success" class="success-message">
              {{ success }}
            </div>

            <button 
              type="submit" 
              class="btn btn-primary btn-block"
              :disabled="loading || !selectedAccount || !form.screenshot"
            >
              {{ loading ? 'Submitting...' : 'Submit Deposit Request' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Recent Deposits -->
      <div class="recent-deposits">
        <h2>Recent Deposit Requests</h2>
        <div v-if="recentDeposits.length === 0" class="empty-state">
          <p>No recent deposits</p>
        </div>
        <div v-else class="deposits-list">
          <div v-for="deposit in recentDeposits" :key="deposit.id" class="deposit-item">
            <div class="deposit-info">
              <span class="deposit-amount">₱{{ formatMoney(deposit.amount) }}</span>
              <span class="deposit-date">{{ formatDateTime(deposit.created_at) }}</span>
            </div>
            <div class="deposit-reference">Ref: {{ deposit.reference_number }}</div>
            <span class="status-badge" :class="deposit.status">
              {{ deposit.status }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const router = useRouter();
const fileInput = ref(null);
const loading = ref(false);
const error = ref(null);
const success = ref(null);

const gcashAccounts = ref([]);
const selectedAccount = ref(null);
const recentDeposits = ref([]);

const form = ref({
  amount: null,
  reference_number: '',
  screenshot: null,
  notes: '',
});

function handleFileUpload(event) {
  const file = event.target.files[0];
  if (file) {
    if (file.size > 5 * 1024 * 1024) {
      error.value = 'File size must be less than 5MB';
      return;
    }
    form.value.screenshot = file;
    error.value = null;
  }
}

async function submitDeposit() {
  if (!selectedAccount.value) {
    error.value = 'Please select a GCash account';
    return;
  }

  if (!form.value.screenshot) {
    error.value = 'Please upload a screenshot';
    return;
  }

  loading.value = true;
  error.value = null;
  success.value = null;

  try {
    const formData = new FormData();
    formData.append('amount', form.value.amount);
    formData.append('payment_method_id', selectedAccount.value.id);
    formData.append('reference_number', form.value.reference_number);
    formData.append('proof_of_payment', form.value.screenshot);
    if (form.value.notes) {
      formData.append('notes', form.value.notes);
    }

    const response = await axios.post('/api/payments/deposit', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    success.value = 'Deposit request submitted successfully! Please wait for approval.';
    
    // Reset form
    form.value = {
      amount: null,
      reference_number: '',
      screenshot: null,
      notes: '',
    };
    selectedAccount.value = null;
    
    // Refresh recent deposits
    fetchRecentDeposits();

    // Redirect after 3 seconds
    setTimeout(() => {
      router.push('/wallet');
    }, 3000);
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to submit deposit request';
  } finally {
    loading.value = false;
  }
}

async function fetchGcashAccounts() {
  try {
    const response = await axios.get('/api/payments/methods');
    gcashAccounts.value = response.data.data.filter(m => m.type === 'gcash' && m.is_active);
  } catch (err) {
    console.error('Failed to fetch GCash accounts:', err);
  }
}

async function fetchRecentDeposits() {
  try {
    const response = await axios.get('/api/payments/deposits', {
      params: { limit: 5 },
    });
    recentDeposits.value = response.data.data;
  } catch (err) {
    console.error('Failed to fetch recent deposits:', err);
  }
}

function formatMoney(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDateTime(timestamp) {
  return new Date(timestamp).toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

onMounted(() => {
  fetchGcashAccounts();
  fetchRecentDeposits();
});
</script>

<style scoped>
.deposit-page {
  min-height: 100vh;
  padding: 40px 20px;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 30px;
}

.back-btn {
  display: inline-block;
  color: rgba(255, 255, 255, 0.7);
  text-decoration: none;
  margin-bottom: 12px;
  transition: color 0.2s;
}

.back-btn:hover {
  color: white;
}

.page-title {
  font-size: 36px;
  font-weight: 800;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.deposit-content {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
  margin-bottom: 40px;
}

.instructions-section, .deposit-form-section {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.instructions-section h2 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 24px;
}

.steps {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 24px;
}

.step {
  display: flex;
  gap: 16px;
}

.step-number {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  flex-shrink: 0;
}

.step-content h3 {
  font-size: 16px;
  margin-bottom: 4px;
}

.step-content p {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.5;
}

.info-box {
  background: rgba(102, 126, 234, 0.1);
  border: 1px solid rgba(102, 126, 234, 0.3);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  gap: 12px;
}

.info-icon {
  font-size: 24px;
}

.info-content strong {
  display: block;
  margin-bottom: 8px;
}

.info-content ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.info-content li {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 4px;
  padding-left: 16px;
  position: relative;
}

.info-content li:before {
  content: '•';
  position: absolute;
  left: 0;
}

.gcash-accounts {
  margin-bottom: 24px;
}

.gcash-accounts h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 12px;
}

.gcash-account-card {
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.gcash-account-card:hover {
  background: rgba(255, 255, 255, 0.08);
}

.gcash-account-card.selected {
  border-color: #667eea;
  background: rgba(102, 126, 234, 0.1);
}

.account-name {
  font-weight: 600;
  margin-bottom: 4px;
}

.account-number {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  font-family: monospace;
}

.selected-badge {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #667eea;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
}

.deposit-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  font-weight: 600;
  font-size: 14px;
}

.form-group input,
.form-group textarea {
  padding: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  font-size: 14px;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #667eea;
}

.quick-amounts {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
  margin-top: 8px;
}

.quick-amount-btn {
  padding: 8px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  color: white;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 12px;
}

.quick-amount-btn:hover {
  background: rgba(255, 255, 255, 0.1);
}

.file-upload {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.upload-btn {
  padding: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 2px dashed rgba(255, 255, 255, 0.2);
  border-radius: 8px;
  color: white;
  cursor: pointer;
  transition: all 0.2s;
  font-weight: 600;
}

.upload-btn:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: #667eea;
}

.file-name {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.help-text {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
  margin: 0;
}

.error-message {
  background: rgba(252, 129, 129, 0.1);
  border: 1px solid #fc8181;
  color: #fc8181;
  padding: 12px;
  border-radius: 8px;
  font-size: 14px;
}

.success-message {
  background: rgba(72, 187, 120, 0.1);
  border: 1px solid #48bb78;
  color: #48bb78;
  padding: 12px;
  border-radius: 8px;
  font-size: 14px;
}

.btn {
  padding: 14px 24px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  font-size: 16px;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-block {
  width: 100%;
}

.recent-deposits {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.recent-deposits h2 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 20px;
}

.deposits-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.deposit-item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 12px;
  align-items: center;
}

.deposit-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.deposit-amount {
  font-size: 18px;
  font-weight: 700;
  color: #48bb78;
}

.deposit-date {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.deposit-reference {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
  font-family: monospace;
  grid-column: 1;
}

.status-badge {
  padding: 6px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  grid-row: 1 / 3;
}

.status-badge.pending {
  background: rgba(237, 137, 54, 0.2);
  color: #ed8936;
}

.status-badge.approved {
  background: rgba(72, 187, 120, 0.2);
  color: #48bb78;
}

.status-badge.rejected {
  background: rgba(252, 129, 129, 0.2);
  color: #fc8181;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: rgba(255, 255, 255, 0.6);
}

@media (max-width: 1024px) {
  .deposit-content {
    grid-template-columns: 1fr;
  }
}
</style>
