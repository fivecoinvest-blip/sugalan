<template>
  <div class="withdraw-page">
    <div class="container">
      <div class="page-header">
        <router-link to="/wallet" class="back-btn">← Back to Wallet</router-link>
        <h1 class="page-title">Withdraw Funds</h1>
      </div>

      <div class="withdraw-content">
        <!-- Balance Info -->
        <div class="balance-info">
          <div class="balance-card">
            <span class="balance-label">Available to Withdraw</span>
            <span class="balance-amount">₱{{ formatMoney(availableBalance) }}</span>
          </div>
          <div class="info-text">
            <p>✓ Minimum withdrawal: ₱100</p>
            <p>✓ Maximum withdrawal: ₱100,000 per day</p>
            <p>✓ Processing time: 30 minutes - 24 hours</p>
          </div>
        </div>

        <!-- Withdrawal Form -->
        <div class="withdraw-form-section">
          <form @submit.prevent="submitWithdrawal" class="withdraw-form">
            <div class="form-group">
              <label>Withdrawal Amount (₱)</label>
              <input 
                v-model.number="form.amount"
                type="number"
                :min="100"
                :max="availableBalance"
                step="1"
                placeholder="Enter amount"
                required
              />
              <div class="quick-amounts">
                <button 
                  v-for="amount in quickAmounts" 
                  :key="amount"
                  type="button"
                  @click="form.amount = amount"
                  :disabled="amount > availableBalance"
                  class="quick-amount-btn"
                >
                  {{ amount === availableBalance ? 'All' : `₱${amount}` }}
                </button>
              </div>
              <p class="help-text">Available: ₱{{ formatMoney(availableBalance) }}</p>
            </div>

            <div class="form-group">
              <label>GCash Account Name</label>
              <input 
                v-model="form.gcash_name"
                type="text"
                placeholder="Enter your GCash registered name"
                required
              />
            </div>

            <div class="form-group">
              <label>GCash Mobile Number</label>
              <input 
                v-model="form.gcash_number"
                type="tel"
                placeholder="+639123456789"
                pattern="^\\+639\\d{9}$"
                required
              />
              <p class="help-text">Format: +639XXXXXXXXX</p>
            </div>

            <div class="form-group">
              <label>Confirm GCash Mobile Number</label>
              <input 
                v-model="form.gcash_number_confirm"
                type="tel"
                placeholder="+639123456789"
                pattern="^\\+639\\d{9}$"
                required
              />
            </div>

            <div class="form-group">
              <label>Notes (Optional)</label>
              <textarea 
                v-model="form.notes"
                placeholder="Any additional information..."
                rows="3"
              ></textarea>
            </div>

            <div v-if="error" class="error-message">{{ error }}</div>
            <div v-if="success" class="success-message">{{ success }}</div>

            <div class="terms-check">
              <input 
                v-model="agreedToTerms"
                type="checkbox"
                id="terms"
              />
              <label for="terms">
                I confirm that the GCash account details are correct and belong to me
              </label>
            </div>

            <button 
              type="submit" 
              class="btn btn-primary btn-block"
              :disabled="loading || !agreedToTerms || form.amount > availableBalance"
            >
              {{ loading ? 'Processing...' : 'Request Withdrawal' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Pending Withdrawals -->
      <div class="pending-withdrawals">
        <h2>Withdrawal History</h2>
        <div v-if="withdrawals.length === 0" class="empty-state">
          <div class="empty-icon">💸</div>
          <p>No withdrawal history</p>
        </div>
        <div v-else class="withdrawals-list">
          <div v-for="withdrawal in withdrawals" :key="withdrawal.id" class="withdrawal-item">
            <div class="withdrawal-main">
              <div class="withdrawal-info">
                <span class="withdrawal-amount">₱{{ formatMoney(withdrawal.amount) }}</span>
                <span class="withdrawal-gcash">{{ withdrawal.gcash_number }}</span>
              </div>
              <span class="status-badge" :class="withdrawal.status">
                {{ withdrawal.status }}
              </span>
            </div>
            <div class="withdrawal-meta">
              <span class="withdrawal-date">{{ formatDateTime(withdrawal.created_at) }}</span>
              <span v-if="withdrawal.processed_at" class="withdrawal-processed">
                Processed: {{ formatDateTime(withdrawal.processed_at) }}
              </span>
            </div>
            <div v-if="withdrawal.admin_notes" class="admin-notes">
              <strong>Admin Notes:</strong> {{ withdrawal.admin_notes }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { useWalletStore } from '../stores/wallet';

const router = useRouter();
const walletStore = useWalletStore();

const loading = ref(false);
const error = ref(null);
const success = ref(null);
const agreedToTerms = ref(false);
const withdrawals = ref([]);

const availableBalance = computed(() => walletStore.balance.real_balance || 0);

const quickAmounts = computed(() => {
  const amounts = [500, 1000, 5000, 10000];
  return [...amounts, availableBalance.value];
});

const form = ref({
  amount: null,
  gcash_name: '',
  gcash_number: '',
  gcash_number_confirm: '',
  notes: '',
});

async function submitWithdrawal() {
  error.value = null;
  success.value = null;

  // Validation
  if (form.value.amount < 100) {
    error.value = 'Minimum withdrawal amount is ₱100';
    return;
  }

  if (form.value.amount > availableBalance.value) {
    error.value = 'Insufficient balance';
    return;
  }

  if (form.value.gcash_number !== form.value.gcash_number_confirm) {
    error.value = 'GCash numbers do not match';
    return;
  }

  if (!agreedToTerms.value) {
    error.value = 'Please confirm your GCash account details';
    return;
  }

  loading.value = true;

  try {
    const response = await axios.post('/api/payments/withdraw', {
      amount: form.value.amount,
      gcash_name: form.value.gcash_name,
      gcash_number: form.value.gcash_number,
      notes: form.value.notes,
    });

    success.value = 'Withdrawal request submitted successfully! Please wait for processing.';
    
    // Reset form
    form.value = {
      amount: null,
      gcash_name: '',
      gcash_number: '',
      gcash_number_confirm: '',
      notes: '',
    };
    agreedToTerms.value = false;
    
    // Refresh data
    await walletStore.fetchBalance();
    await fetchWithdrawals();

    // Redirect after 3 seconds
    setTimeout(() => {
      router.push('/wallet');
    }, 3000);
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to submit withdrawal request';
  } finally {
    loading.value = false;
  }
}

async function fetchWithdrawals() {
  try {
    const response = await axios.get('/api/payments/withdrawals', {
      params: { limit: 10 },
    });
    withdrawals.value = response.data.data;
  } catch (err) {
    console.error('Failed to fetch withdrawals:', err);
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
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

onMounted(() => {
  walletStore.fetchBalance();
  fetchWithdrawals();
});
</script>

<style scoped>
.withdraw-page {
  min-height: 100vh;
  padding: 40px 20px;
}

.container {
  max-width: 900px;
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

.withdraw-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
  margin-bottom: 40px;
}

.balance-info {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.balance-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.balance-label {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
}

.balance-amount {
  font-size: 36px;
  font-weight: 800;
  color: #48bb78;
}

.info-text {
  padding-top: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.info-text p {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  margin-bottom: 8px;
}

.withdraw-form-section {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.withdraw-form {
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
  grid-template-columns: repeat(5, 1fr);
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

.quick-amount-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.1);
}

.quick-amount-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.help-text {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
  margin: 0;
}

.terms-check {
  display: flex;
  align-items: center;
  gap: 8px;
}

.terms-check input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.terms-check label {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.8);
  cursor: pointer;
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

.pending-withdrawals {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.pending-withdrawals h2 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 20px;
}

.withdrawals-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.withdrawal-item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
}

.withdrawal-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.withdrawal-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.withdrawal-amount {
  font-size: 20px;
  font-weight: 700;
  color: #ed8936;
}

.withdrawal-gcash {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
  font-family: monospace;
}

.withdrawal-meta {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
  margin-bottom: 8px;
}

.admin-notes {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  padding: 12px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
  margin-top: 8px;
}

.admin-notes strong {
  color: #667eea;
}

.status-badge {
  padding: 6px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
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
  padding: 60px 20px;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.empty-state p {
  color: rgba(255, 255, 255, 0.6);
}

@media (max-width: 768px) {
  .quick-amounts {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>
