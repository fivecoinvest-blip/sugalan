<template>
  <div class="wallet-page">
    <div class="container">
      <h1 class="page-title">Wallet</h1>

      <!-- Balance Cards -->
      <div class="balance-cards">
        <div class="balance-card real">
          <div class="card-icon">💰</div>
          <div class="card-content">
            <span class="card-label">Real Balance</span>
            <span class="card-amount">₱{{ formatMoney(balance.real_balance) }}</span>
          </div>
        </div>

        <div class="balance-card bonus">
          <div class="card-icon">🎁</div>
          <div class="card-content">
            <span class="card-label">Bonus Balance</span>
            <span class="card-amount">₱{{ formatMoney(balance.bonus_balance) }}</span>
          </div>
        </div>

        <div class="balance-card locked">
          <div class="card-icon">🔒</div>
          <div class="card-content">
            <span class="card-label">Locked Balance</span>
            <span class="card-amount">₱{{ formatMoney(balance.locked_balance) }}</span>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <router-link to="/deposit" class="action-btn deposit">
          <span class="action-icon">💳</span>
          <span>Deposit</span>
        </router-link>
        <router-link to="/withdraw" class="action-btn withdraw">
          <span class="action-icon">💸</span>
          <span>Withdraw</span>
        </router-link>
      </div>

      <!-- Transactions -->
      <div class="transactions-section">
        <div class="section-header">
          <h2>Transaction History</h2>
          <div class="filters">
            <select v-model="selectedType" class="filter-select">
              <option value="all">All Types</option>
              <option value="deposit">Deposits</option>
              <option value="withdrawal">Withdrawals</option>
              <option value="bet">Bets</option>
              <option value="win">Wins</option>
              <option value="bonus">Bonuses</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading transactions...</p>
        </div>

        <div v-else-if="filteredTransactions.length === 0" class="empty-state">
          <div class="empty-icon">📊</div>
          <p>No transactions yet</p>
        </div>

        <div v-else class="transactions-list">
          <div 
            v-for="transaction in filteredTransactions" 
            :key="transaction.id"
            class="transaction-item"
          >
            <div class="transaction-icon" :class="transaction.type">
              {{ getTransactionIcon(transaction.type) }}
            </div>
            <div class="transaction-content">
              <div class="transaction-main">
                <span class="transaction-type">{{ getTransactionLabel(transaction.type) }}</span>
                <span class="transaction-amount" :class="getAmountClass(transaction)">
                  {{ getAmountSign(transaction) }}₱{{ formatMoney(Math.abs(transaction.amount)) }}
                </span>
              </div>
              <div class="transaction-details">
                <span class="transaction-date">{{ formatDateTime(transaction.created_at) }}</span>
                <span class="transaction-balance">Balance: ₱{{ formatMoney(transaction.balance_after) }}</span>
              </div>
              <div v-if="transaction.description" class="transaction-description">
                {{ transaction.description }}
              </div>
            </div>
            <div class="transaction-status">
              <span class="status-badge" :class="transaction.status">
                {{ transaction.status }}
              </span>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="pagination">
          <button 
            @click="currentPage--" 
            :disabled="currentPage === 1"
            class="page-btn"
          >
            ← Previous
          </button>
          <span class="page-info">Page {{ currentPage }} of {{ totalPages }}</span>
          <button 
            @click="currentPage++" 
            :disabled="currentPage === totalPages"
            class="page-btn"
          >
            Next →
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useWalletStore } from '../stores/wallet';

const walletStore = useWalletStore();
const balance = computed(() => walletStore.balance);
const transactions = computed(() => walletStore.transactions);
const loading = ref(false);

const selectedType = ref('all');
const currentPage = ref(1);
const perPage = 20;

const filteredTransactions = computed(() => {
  let filtered = transactions.value;

  if (selectedType.value !== 'all') {
    filtered = filtered.filter(t => t.type === selectedType.value);
  }

  return filtered;
});

const totalPages = computed(() => {
  return Math.ceil(filteredTransactions.value.length / perPage);
});

const transactionIcons = {
  deposit: '💳',
  withdrawal: '💸',
  bet: '🎲',
  win: '🏆',
  bonus: '🎁',
  refund: '↩️',
  referral: '👥',
};

const transactionLabels = {
  deposit: 'Deposit',
  withdrawal: 'Withdrawal',
  bet: 'Bet Placed',
  win: 'Win',
  bonus: 'Bonus',
  refund: 'Refund',
  referral: 'Referral Earnings',
};

function formatMoney(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDateTime(timestamp) {
  return new Date(timestamp).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getTransactionIcon(type) {
  return transactionIcons[type] || '💰';
}

function getTransactionLabel(type) {
  return transactionLabels[type] || type;
}

function getAmountClass(transaction) {
  if (transaction.amount > 0) return 'positive';
  if (transaction.amount < 0) return 'negative';
  return '';
}

function getAmountSign(transaction) {
  if (transaction.amount > 0) return '+';
  if (transaction.amount < 0) return '';
  return '';
}

async function fetchTransactions() {
  loading.value = true;
  await walletStore.fetchTransactions({
    page: currentPage.value,
    per_page: perPage,
  });
  loading.value = false;
}

watch(currentPage, () => {
  fetchTransactions();
});

onMounted(() => {
  walletStore.fetchBalance();
  fetchTransactions();
});
</script>

<style scoped>
.wallet-page {
  min-height: 100vh;
  padding: 40px 20px;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.page-title {
  font-size: 36px;
  font-weight: 800;
  margin-bottom: 30px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.balance-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.balance-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.balance-card.real {
  border-color: rgba(72, 187, 120, 0.3);
}

.balance-card.bonus {
  border-color: rgba(237, 137, 54, 0.3);
}

.balance-card.locked {
  border-color: rgba(113, 128, 150, 0.3);
}

.card-icon {
  font-size: 48px;
}

.card-content {
  display: flex;
  flex-direction: column;
}

.card-label {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 4px;
}

.card-amount {
  font-size: 28px;
  font-weight: 700;
}

.balance-card.real .card-amount {
  color: #48bb78;
}

.balance-card.bonus .card-amount {
  color: #ed8936;
}

.balance-card.locked .card-amount {
  color: #718096;
}

.quick-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 40px;
}

.action-btn {
  padding: 20px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  text-decoration: none;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: white;
  font-weight: 600;
  font-size: 16px;
  transition: all 0.2s;
}

.action-btn:hover {
  transform: translateY(-2px);
  background: rgba(255, 255, 255, 0.08);
}

.action-btn.deposit {
  border-color: rgba(72, 187, 120, 0.3);
}

.action-btn.withdraw {
  border-color: rgba(237, 137, 54, 0.3);
}

.action-icon {
  font-size: 24px;
}

.transactions-section {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.section-header h2 {
  font-size: 24px;
  font-weight: 700;
}

.filter-select {
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  cursor: pointer;
}

.filter-select:focus {
  outline: none;
  border-color: #667eea;
}

.loading-state, .empty-state {
  text-align: center;
  padding: 60px 20px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid rgba(255, 255, 255, 0.1);
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.empty-state p, .loading-state p {
  color: rgba(255, 255, 255, 0.6);
}

.transactions-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.transaction-item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.transaction-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  background: rgba(255, 255, 255, 0.05);
}

.transaction-content {
  flex: 1;
}

.transaction-main {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.transaction-type {
  font-weight: 600;
  font-size: 16px;
}

.transaction-amount {
  font-weight: 700;
  font-size: 18px;
}

.transaction-amount.positive {
  color: #48bb78;
}

.transaction-amount.negative {
  color: #fc8181;
}

.transaction-details {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 4px;
}

.transaction-description {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
}

.transaction-status {
  display: flex;
  align-items: center;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.completed {
  background: rgba(72, 187, 120, 0.2);
  color: #48bb78;
}

.status-badge.pending {
  background: rgba(237, 137, 54, 0.2);
  color: #ed8936;
}

.status-badge.failed {
  background: rgba(252, 129, 129, 0.2);
  color: #fc8181;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.page-btn {
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  cursor: pointer;
  transition: all 0.2s;
}

.page-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.08);
}

.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.page-info {
  color: rgba(255, 255, 255, 0.7);
}

@media (max-width: 768px) {
  .quick-actions {
    grid-template-columns: 1fr;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
}
</style>
