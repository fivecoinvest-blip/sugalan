<template>
  <div class="bonuses-page">
    <div class="container">
      <h1 class="page-title">Bonuses & Promotions</h1>

      <!-- Active Bonuses -->
      <div class="active-bonuses-section">
        <h2>🎁 Active Bonuses</h2>
        
        <div v-if="activeBonuses.length === 0" class="empty-state">
          <div class="empty-icon">🎁</div>
          <p>No active bonuses</p>
        </div>

        <div v-else class="bonuses-grid">
          <div v-for="bonus in activeBonuses" :key="bonus.id" class="bonus-card active">
            <div class="bonus-header">
              <div class="bonus-type-badge" :class="bonus.bonus_type">
                {{ formatBonusType(bonus.bonus_type) }}
              </div>
              <div class="bonus-amount">₱{{ formatMoney(bonus.amount) }}</div>
            </div>

            <div class="bonus-progress">
              <div class="progress-info">
                <span>Wagering Progress</span>
                <span class="progress-percent">{{ bonusProgress(bonus) }}%</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill" :style="{ width: bonusProgress(bonus) + '%' }"></div>
              </div>
              <div class="progress-details">
                <span>₱{{ formatMoney(bonus.wagered) }} / ₱{{ formatMoney(bonus.wager_requirement) }}</span>
                <span>Remaining: ₱{{ formatMoney(bonus.wager_requirement - bonus.wagered) }}</span>
              </div>
            </div>

            <div class="bonus-footer">
              <div class="bonus-dates">
                <span class="bonus-date">
                  <span class="date-label">Expires:</span>
                  {{ formatDate(bonus.expires_at) }}
                </span>
              </div>
              <button @click="cancelBonus(bonus.id)" class="btn-cancel">
                Cancel
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Available Promotions -->
      <div class="promotions-section">
        <h2>🎉 Available Promotions</h2>
        
        <div class="promotions-grid">
          <div v-for="promo in promotions" :key="promo.id" class="promo-card">
            <div class="promo-badge">{{ promo.label }}</div>
            <div class="promo-icon">{{ promo.icon }}</div>
            <h3>{{ promo.title }}</h3>
            <p class="promo-description">{{ promo.description }}</p>
            
            <div class="promo-details">
              <div class="detail-item">
                <span class="detail-label">Bonus Amount</span>
                <span class="detail-value">{{ promo.amount }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Wagering</span>
                <span class="detail-value">{{ promo.wagering }}x</span>
              </div>
              <div v-if="promo.min_deposit" class="detail-item">
                <span class="detail-label">Min Deposit</span>
                <span class="detail-value">₱{{ promo.min_deposit }}</span>
              </div>
            </div>

            <button @click="claimPromo(promo)" class="btn btn-primary btn-block">
              Claim Now
            </button>
          </div>
        </div>
      </div>

      <!-- Bonus History -->
      <div class="history-section">
        <h2>📜 Bonus History</h2>
        
        <div v-if="bonusHistory.length === 0" class="empty-state">
          <p>No bonus history</p>
        </div>

        <div v-else class="history-list">
          <div v-for="bonus in bonusHistory" :key="bonus.id" class="history-item">
            <div class="history-main">
              <div class="history-info">
                <span class="history-type">{{ formatBonusType(bonus.bonus_type) }}</span>
                <span class="history-date">{{ formatDateTime(bonus.created_at) }}</span>
              </div>
              <div class="history-amounts">
                <span class="history-amount">₱{{ formatMoney(bonus.amount) }}</span>
                <span class="status-badge" :class="bonus.status">{{ bonus.status }}</span>
              </div>
            </div>
            <div v-if="bonus.status === 'completed'" class="history-details">
              <span>Wagered: ₱{{ formatMoney(bonus.wagered) }}</span>
              <span>Completed: {{ formatDate(bonus.completed_at) }}</span>
            </div>
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
const activeBonuses = ref([]);
const bonusHistory = ref([]);

const promotions = [
  {
    id: 'welcome',
    icon: '🎁',
    label: 'NEW',
    title: 'Welcome Bonus',
    description: 'Get 100% match bonus on your first deposit up to ₱5,000',
    amount: '100% up to ₱5,000',
    wagering: '30',
    min_deposit: '100',
  },
  {
    id: 'reload',
    icon: '🔄',
    label: 'POPULAR',
    title: 'Reload Bonus',
    description: 'Get 50% bonus on every deposit on weekends',
    amount: '50% up to ₱2,000',
    wagering: '25',
    min_deposit: '500',
  },
  {
    id: 'cashback',
    icon: '💰',
    label: 'WEEKLY',
    title: 'Cashback Bonus',
    description: 'Get 10% cashback on your losses every Monday',
    amount: '10% up to ₱1,000',
    wagering: '15',
    min_deposit: null,
  },
  {
    id: 'referral',
    icon: '👥',
    label: 'ONGOING',
    title: 'Referral Bonus',
    description: 'Earn ₱100 for every friend you refer',
    amount: '₱100 per referral',
    wagering: '20',
    min_deposit: null,
  },
];

function formatMoney(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
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

function formatBonusType(type) {
  const types = {
    welcome: 'Welcome Bonus',
    deposit: 'Deposit Bonus',
    reload: 'Reload Bonus',
    cashback: 'Cashback',
    referral: 'Referral Bonus',
    vip: 'VIP Bonus',
  };
  return types[type] || type;
}

function bonusProgress(bonus) {
  return Math.min(Math.round((bonus.wagered / bonus.wager_requirement) * 100), 100);
}

async function claimPromo(promo) {
  if (promo.min_deposit) {
    // Redirect to deposit page
    router.push('/deposit');
  } else {
    try {
      await axios.post('/api/bonuses/claim', {
        bonus_type: promo.id,
      });
      alert('Bonus claimed successfully!');
      fetchActiveBonuses();
    } catch (error) {
      alert(error.response?.data?.message || 'Failed to claim bonus');
    }
  }
}

async function cancelBonus(bonusId) {
  if (!confirm('Are you sure you want to cancel this bonus? Any progress will be lost.')) {
    return;
  }

  try {
    await axios.delete(`/api/bonuses/${bonusId}`);
    fetchActiveBonuses();
  } catch (error) {
    alert(error.response?.data?.message || 'Failed to cancel bonus');
  }
}

async function fetchActiveBonuses() {
  try {
    const response = await axios.get('/api/bonuses/active');
    activeBonuses.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch active bonuses:', error);
  }
}

async function fetchBonusHistory() {
  try {
    const response = await axios.get('/api/bonuses/history', {
      params: { limit: 10 },
    });
    bonusHistory.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch bonus history:', error);
  }
}

onMounted(() => {
  fetchActiveBonuses();
  fetchBonusHistory();
});
</script>

<style scoped>
.bonuses-page {
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

.active-bonuses-section,
.promotions-section,
.history-section {
  margin-bottom: 40px;
}

.active-bonuses-section h2,
.promotions-section h2,
.history-section h2 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 20px;
}

.bonuses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.bonus-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.bonus-card.active {
  border-color: rgba(237, 137, 54, 0.3);
}

.bonus-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.bonus-type-badge {
  background: rgba(102, 126, 234, 0.2);
  padding: 6px 12px;
  border-radius: 12px;
  font-size: 12px;
  color: #667eea;
  font-weight: 600;
  text-transform: uppercase;
}

.bonus-amount {
  font-size: 24px;
  font-weight: 800;
  color: #ed8936;
}

.bonus-progress {
  margin-bottom: 20px;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 14px;
}

.progress-percent {
  color: #667eea;
  font-weight: 700;
}

.progress-bar {
  height: 12px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  transition: width 0.3s;
}

.progress-details {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.bonus-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.bonus-date {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
}

.date-label {
  color: rgba(255, 255, 255, 0.5);
}

.btn-cancel {
  padding: 6px 16px;
  background: rgba(252, 129, 129, 0.2);
  border: 1px solid rgba(252, 129, 129, 0.3);
  border-radius: 8px;
  color: #fc8181;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: rgba(252, 129, 129, 0.3);
}

.promotions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

.promo-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  position: relative;
  transition: all 0.2s;
}

.promo-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2);
}

.promo-badge {
  position: absolute;
  top: 16px;
  right: 16px;
  background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  color: #1a1a2e;
}

.promo-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.promo-card h3 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
}

.promo-description {
  color: rgba(255, 255, 255, 0.7);
  font-size: 14px;
  line-height: 1.5;
  margin-bottom: 16px;
}

.promo-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
  padding: 16px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.detail-label {
  color: rgba(255, 255, 255, 0.6);
}

.detail-value {
  font-weight: 600;
  color: #667eea;
}

.btn {
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  font-size: 14px;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-block {
  width: 100%;
}

.history-list {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.history-item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
}

.history-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.history-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.history-type {
  font-weight: 600;
  font-size: 15px;
}

.history-date {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.history-amounts {
  display: flex;
  align-items: center;
  gap: 12px;
}

.history-amount {
  font-size: 18px;
  font-weight: 700;
  color: #ed8936;
}

.status-badge {
  padding: 4px 10px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.active {
  background: rgba(237, 137, 54, 0.2);
  color: #ed8936;
}

.status-badge.completed {
  background: rgba(72, 187, 120, 0.2);
  color: #48bb78;
}

.status-badge.expired {
  background: rgba(113, 128, 150, 0.2);
  color: #718096;
}

.status-badge.cancelled {
  background: rgba(252, 129, 129, 0.2);
  color: #fc8181;
}

.history-details {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
  padding-top: 8px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
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
  .bonuses-grid,
  .promotions-grid {
    grid-template-columns: 1fr;
  }
}
</style>
