<template>
  <div class="promotions-page">
    <div class="container">
      <h1 class="page-title">🎉 Promotions & Rewards</h1>

      <!-- Daily Reward Section -->
      <div class="daily-reward-section">
        <div class="daily-reward-card" :class="{ 'can-claim': dailyReward.can_claim }">
          <div class="daily-reward-header">
            <div class="reward-icon">🎁</div>
            <div class="reward-info">
              <h3>Daily Check-in Reward</h3>
              <p>Login daily to claim your rewards and build your streak!</p>
            </div>
          </div>

          <div class="streak-progress">
            <div class="streak-days">
              <div 
                v-for="day in 7" 
                :key="day"
                class="day-item"
                :class="{
                  'completed': day <= dailyReward.current_streak,
                  'current': day === dailyReward.current_streak + 1 && dailyReward.can_claim,
                  'today': day === (dailyReward.current_streak % 7) + 1
                }"
              >
                <div class="day-number">Day {{ day }}</div>
                <div class="day-reward">₱{{ getDayReward(day) }}</div>
                <div class="day-check" v-if="day <= dailyReward.current_streak">✓</div>
              </div>
            </div>
          </div>

          <div class="reward-stats">
            <div class="stat-item">
              <span class="stat-label">Current Streak</span>
              <span class="stat-value">{{ dailyReward.current_streak }} days</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Today's Reward</span>
              <span class="stat-value">₱{{ dailyReward.reward_amount }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-label">Next Reward</span>
              <span class="stat-value" v-if="!dailyReward.can_claim">Tomorrow</span>
              <span class="stat-value claim-ready" v-else>Ready!</span>
            </div>
          </div>

          <button 
            @click="claimDailyReward" 
            class="btn btn-primary btn-block btn-claim"
            :disabled="!dailyReward.can_claim || claimingDaily"
          >
            <span v-if="claimingDaily">Claiming...</span>
            <span v-else-if="dailyReward.can_claim">Claim Today's Reward</span>
            <span v-else>Already Claimed Today</span>
          </button>
        </div>
      </div>

      <!-- Available Campaigns -->
      <div class="campaigns-section">
        <h2>🎁 Active Campaigns</h2>
        
        <div v-if="loading" class="loading-state">
          <div class="spinner"></div>
          <p>Loading campaigns...</p>
        </div>

        <div v-else-if="campaigns.length === 0" class="empty-state">
          <div class="empty-icon">🎉</div>
          <p>No active campaigns available</p>
        </div>

        <div v-else class="campaigns-grid">
          <div 
            v-for="campaign in campaigns" 
            :key="campaign.id" 
            class="campaign-card"
            :class="campaign.type"
          >
            <div class="campaign-badge" v-if="campaign.is_featured">⭐ FEATURED</div>
            <div class="campaign-type-badge" :class="campaign.type">
              {{ formatCampaignType(campaign.type) }}
            </div>

            <h3 class="campaign-title">{{ campaign.title }}</h3>
            <p class="campaign-description">{{ campaign.description }}</p>

            <div class="campaign-details">
              <div class="detail-row" v-if="campaign.type === 'bonus'">
                <span class="detail-label">Bonus Amount</span>
                <span class="detail-value">₱{{ formatMoney(campaign.value) }}</span>
              </div>
              <div class="detail-row" v-if="campaign.type === 'reload'">
                <span class="detail-label">Bonus</span>
                <span class="detail-value">{{ campaign.percentage }}% up to ₱{{ formatMoney(campaign.max_bonus) }}</span>
              </div>
              <div class="detail-row" v-if="campaign.type === 'cashback'">
                <span class="detail-label">Cashback</span>
                <span class="detail-value">{{ campaign.percentage }}% up to ₱{{ formatMoney(campaign.max_bonus) }}</span>
              </div>
              <div class="detail-row" v-if="campaign.type === 'free_spins'">
                <span class="detail-label">Free Spins</span>
                <span class="detail-value">{{ campaign.value }} spins</span>
              </div>
              
              <div class="detail-row">
                <span class="detail-label">Wagering</span>
                <span class="detail-value">{{ campaign.wagering_multiplier }}x</span>
              </div>

              <div class="detail-row" v-if="campaign.min_deposit">
                <span class="detail-label">Min Deposit</span>
                <span class="detail-value">₱{{ formatMoney(campaign.min_deposit) }}</span>
              </div>

              <div class="detail-row" v-if="campaign.min_vip_level">
                <span class="detail-label">Required</span>
                <span class="detail-value">{{ formatVipLevel(campaign.min_vip_level) }}+ VIP</span>
              </div>
            </div>

            <div class="campaign-code" v-if="campaign.code">
              <span class="code-label">Code:</span>
              <span class="code-value">{{ campaign.code }}</span>
              <button @click="copyCode(campaign.code)" class="btn-copy">📋</button>
            </div>

            <div class="campaign-footer">
              <div class="campaign-meta">
                <span class="expires">Expires: {{ formatDate(campaign.end_date) }}</span>
                <span class="claims" v-if="campaign.remaining_claims !== null">
                  {{ campaign.remaining_claims }} left
                </span>
              </div>
              <button 
                @click="showClaimModal(campaign)" 
                class="btn btn-primary"
                :disabled="!campaign.can_claim"
              >
                {{ campaign.can_claim ? 'Claim Now' : 'Claimed' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Claimed Campaigns History -->
      <div class="claimed-section">
        <h2>📜 My Claimed Campaigns</h2>
        
        <div v-if="claimedCampaigns.length === 0" class="empty-state">
          <p>No claimed campaigns yet</p>
        </div>

        <div v-else class="claimed-list">
          <div v-for="claim in claimedCampaigns" :key="claim.id" class="claimed-item">
            <div class="claimed-main">
              <div class="claimed-info">
                <h4>{{ claim.campaign.title }}</h4>
                <span class="claimed-date">{{ formatDateTime(claim.created_at) }}</span>
              </div>
              <div class="claimed-amount">
                <span class="amount">₱{{ formatMoney(claim.bonus_amount) }}</span>
                <span class="status-badge" :class="claim.bonus.status">
                  {{ claim.bonus.status }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Claim Modal -->
    <div v-if="showModal" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Claim Campaign</h3>
          <button @click="closeModal" class="btn-close">×</button>
        </div>

        <div class="modal-body">
          <div class="campaign-preview">
            <h4>{{ selectedCampaign.title }}</h4>
            <p>{{ selectedCampaign.description }}</p>
          </div>

          <div v-if="selectedCampaign.type === 'reload' || selectedCampaign.min_deposit" class="deposit-input">
            <label>Deposit Amount (optional)</label>
            <input 
              v-model.number="depositAmount" 
              type="number" 
              class="form-control"
              :placeholder="`Min: ₱${selectedCampaign.min_deposit || 0}`"
            />
            <small v-if="selectedCampaign.type === 'reload'" class="help-text">
              Your bonus: {{ selectedCampaign.percentage }}% of deposit (max ₱{{ formatMoney(selectedCampaign.max_bonus) }})
            </small>
          </div>

          <div class="terms-section">
            <h5>Terms & Conditions</h5>
            <div class="terms-content">{{ selectedCampaign.terms }}</div>
          </div>
        </div>

        <div class="modal-footer">
          <button @click="closeModal" class="btn btn-secondary">Cancel</button>
          <button @click="confirmClaim" class="btn btn-primary" :disabled="claiming">
            {{ claiming ? 'Claiming...' : 'Confirm Claim' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const loading = ref(true);
const campaigns = ref([]);
const claimedCampaigns = ref([]);
const dailyReward = ref({
  can_claim: false,
  current_streak: 0,
  reward_amount: 0,
  weekly_progress: []
});

const showModal = ref(false);
const selectedCampaign = ref(null);
const depositAmount = ref(null);
const claiming = ref(false);
const claimingDaily = ref(false);

const vipLevels = ['Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'];

onMounted(() => {
  loadCampaigns();
  loadClaimedCampaigns();
  loadDailyRewardStatus();
});

const loadCampaigns = async () => {
  try {
    loading.value = true;
    const response = await axios.get('/api/promotions/campaigns');
    campaigns.value = response.data.data;
  } catch (error) {
    console.error('Failed to load campaigns:', error);
    alert('Failed to load campaigns');
  } finally {
    loading.value = false;
  }
};

const loadClaimedCampaigns = async () => {
  try {
    const response = await axios.get('/api/promotions/campaigns/claimed');
    claimedCampaigns.value = response.data.data;
  } catch (error) {
    console.error('Failed to load claimed campaigns:', error);
  }
};

const loadDailyRewardStatus = async () => {
  try {
    const response = await axios.get('/api/promotions/daily-reward/status');
    dailyReward.value = response.data.data;
  } catch (error) {
    console.error('Failed to load daily reward status:', error);
  }
};

const claimDailyReward = async () => {
  if (!dailyReward.value.can_claim || claimingDaily.value) return;

  try {
    claimingDaily.value = true;
    const response = await axios.post('/api/promotions/daily-reward/claim');
    
    alert(`✅ Daily reward claimed! You received ₱${response.data.data.reward_amount}`);
    
    // Refresh status
    await loadDailyRewardStatus();
    
    // Refresh wallet balance
    if (authStore.refreshWallet) {
      await authStore.refreshWallet();
    }
  } catch (error) {
    console.error('Failed to claim daily reward:', error);
    alert(error.response?.data?.message || 'Failed to claim daily reward');
  } finally {
    claimingDaily.value = false;
  }
};

const showClaimModal = (campaign) => {
  selectedCampaign.value = campaign;
  depositAmount.value = campaign.min_deposit || null;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  selectedCampaign.value = null;
  depositAmount.value = null;
};

const confirmClaim = async () => {
  if (claiming.value) return;

  try {
    claiming.value = true;
    
    const payload = {
      campaign_id: selectedCampaign.value.id
    };

    if (depositAmount.value && depositAmount.value > 0) {
      payload.deposit_amount = depositAmount.value;
    }

    const response = await axios.post('/api/promotions/campaigns/claim', payload);
    
    alert(`✅ Campaign claimed successfully! You received ₱${response.data.data.bonus_amount}`);
    
    closeModal();
    
    // Refresh data
    await Promise.all([
      loadCampaigns(),
      loadClaimedCampaigns()
    ]);
    
    // Refresh wallet
    if (authStore.refreshWallet) {
      await authStore.refreshWallet();
    }
  } catch (error) {
    console.error('Failed to claim campaign:', error);
    alert(error.response?.data?.message || 'Failed to claim campaign');
  } finally {
    claiming.value = false;
  }
};

const copyCode = (code) => {
  navigator.clipboard.writeText(code);
  alert('Code copied to clipboard!');
};

const getDayReward = (day) => {
  const baseRewards = [10, 15, 20, 25, 30, 40, 100];
  const userVipLevel = authStore.user?.vip_level || 1;
  const vipMultipliers = [1, 1.2, 1.5, 2, 3];
  
  const baseReward = baseRewards[day - 1] || 10;
  const multiplier = vipMultipliers[userVipLevel - 1] || 1;
  
  return Math.floor(baseReward * multiplier);
};

const formatCampaignType = (type) => {
  const types = {
    bonus: 'Bonus',
    reload: 'Reload Bonus',
    cashback: 'Cashback',
    free_spins: 'Free Spins',
    tournament: 'Tournament'
  };
  return types[type] || type;
};

const formatVipLevel = (level) => {
  return vipLevels[level - 1] || 'Bronze';
};

const formatMoney = (amount) => {
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount);
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-PH', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  });
};

const formatDateTime = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleString('en-PH', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>

<style scoped>
.promotions-page {
  padding: 2rem 0;
  min-height: 100vh;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1rem;
}

.page-title {
  font-size: 2.5rem;
  font-weight: bold;
  text-align: center;
  margin-bottom: 2rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

/* Daily Reward Section */
.daily-reward-section {
  margin-bottom: 3rem;
}

.daily-reward-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 1rem;
  padding: 2rem;
  color: white;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.daily-reward-card.can-claim {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.02); }
}

.daily-reward-header {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.reward-icon {
  font-size: 4rem;
}

.reward-info h3 {
  font-size: 1.5rem;
  margin-bottom: 0.5rem;
}

.reward-info p {
  opacity: 0.9;
}

.streak-progress {
  margin-bottom: 2rem;
}

.streak-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.5rem;
}

.day-item {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 0.5rem;
  padding: 1rem 0.5rem;
  text-align: center;
  transition: all 0.3s;
  position: relative;
}

.day-item.completed {
  background: rgba(255, 255, 255, 0.3);
}

.day-item.current {
  background: rgba(255, 255, 255, 0.5);
  animation: glow 1.5s infinite;
}

@keyframes glow {
  0%, 100% { box-shadow: 0 0 10px rgba(255, 255, 255, 0.5); }
  50% { box-shadow: 0 0 20px rgba(255, 255, 255, 0.8); }
}

.day-number {
  font-size: 0.75rem;
  opacity: 0.8;
  margin-bottom: 0.25rem;
}

.day-reward {
  font-weight: bold;
  font-size: 1rem;
}

.day-check {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  background: #10b981;
  border-radius: 50%;
  width: 1.25rem;
  height: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
}

.reward-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.stat-item {
  text-align: center;
}

.stat-label {
  display: block;
  font-size: 0.875rem;
  opacity: 0.8;
  margin-bottom: 0.25rem;
}

.stat-value {
  display: block;
  font-size: 1.25rem;
  font-weight: bold;
}

.stat-value.claim-ready {
  color: #10b981;
  animation: blink 1s infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.btn-claim {
  font-size: 1.125rem;
  padding: 1rem;
  background: white;
  color: #667eea;
  font-weight: bold;
  transition: all 0.3s;
}

.btn-claim:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.btn-claim:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Campaigns Section */
.campaigns-section {
  margin-bottom: 3rem;
}

.campaigns-section h2 {
  font-size: 1.75rem;
  margin-bottom: 1.5rem;
}

.loading-state, .empty-state {
  text-align: center;
  padding: 3rem;
  color: #666;
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

.campaigns-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
}

.campaign-card {
  background: white;
  border-radius: 1rem;
  padding: 1.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s, box-shadow 0.3s;
  position: relative;
}

.campaign-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
}

.campaign-badge {
  position: absolute;
  top: -10px;
  right: 10px;
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 1rem;
  font-size: 0.75rem;
  font-weight: bold;
}

.campaign-type-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: bold;
  margin-bottom: 1rem;
}

.campaign-type-badge.bonus {
  background: #dbeafe;
  color: #1e40af;
}

.campaign-type-badge.reload {
  background: #dcfce7;
  color: #166534;
}

.campaign-type-badge.cashback {
  background: #fef3c7;
  color: #92400e;
}

.campaign-type-badge.free_spins {
  background: #fce7f3;
  color: #9f1239;
}

.campaign-title {
  font-size: 1.25rem;
  font-weight: bold;
  margin-bottom: 0.5rem;
}

.campaign-description {
  color: #666;
  margin-bottom: 1rem;
  line-height: 1.5;
}

.campaign-details {
  background: #f9fafb;
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.detail-row:last-child {
  margin-bottom: 0;
}

.detail-label {
  color: #666;
  font-size: 0.875rem;
}

.detail-value {
  font-weight: 600;
  color: #111;
}

.campaign-code {
  background: #f3f4f6;
  border: 2px dashed #d1d5db;
  border-radius: 0.5rem;
  padding: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.code-label {
  color: #666;
  font-size: 0.875rem;
}

.code-value {
  font-family: 'Courier New', monospace;
  font-weight: bold;
  color: #667eea;
  font-size: 1.125rem;
}

.btn-copy {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.25rem;
  transition: transform 0.2s;
}

.btn-copy:hover {
  transform: scale(1.2);
}

.campaign-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.campaign-meta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.75rem;
  color: #666;
}

/* Claimed Section */
.claimed-section {
  margin-bottom: 3rem;
}

.claimed-section h2 {
  font-size: 1.75rem;
  margin-bottom: 1.5rem;
}

.claimed-list {
  background: white;
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.claimed-item {
  padding: 1.5rem;
  border-bottom: 1px solid #f3f4f6;
}

.claimed-item:last-child {
  border-bottom: none;
}

.claimed-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.claimed-info h4 {
  font-size: 1.125rem;
  margin-bottom: 0.25rem;
}

.claimed-date {
  font-size: 0.875rem;
  color: #666;
}

.claimed-amount {
  text-align: right;
}

.amount {
  display: block;
  font-size: 1.25rem;
  font-weight: bold;
  color: #10b981;
  margin-bottom: 0.25rem;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 0.5rem;
  font-size: 0.75rem;
  font-weight: bold;
}

.status-badge.active {
  background: #dbeafe;
  color: #1e40af;
}

.status-badge.completed {
  background: #dcfce7;
  color: #166534;
}

.status-badge.expired {
  background: #fee2e2;
  color: #991b1b;
}

/* Modal */
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
  border-radius: 1rem;
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
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

.campaign-preview {
  margin-bottom: 1.5rem;
}

.campaign-preview h4 {
  font-size: 1.25rem;
  margin-bottom: 0.5rem;
}

.campaign-preview p {
  color: #666;
}

.deposit-input {
  margin-bottom: 1.5rem;
}

.deposit-input label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  font-size: 1rem;
}

.help-text {
  display: block;
  margin-top: 0.5rem;
  color: #666;
  font-size: 0.875rem;
}

.terms-section {
  margin-bottom: 1rem;
}

.terms-section h5 {
  font-size: 1rem;
  margin-bottom: 0.5rem;
  font-weight: 600;
}

.terms-content {
  background: #f9fafb;
  padding: 1rem;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  color: #666;
  line-height: 1.6;
  max-height: 150px;
  overflow-y: auto;
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
  border-radius: 0.5rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  border: none;
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

.btn-block {
  width: 100%;
  display: block;
}

@media (max-width: 768px) {
  .streak-days {
    grid-template-columns: repeat(4, 1fr);
  }
  
  .reward-stats {
    grid-template-columns: 1fr;
  }
  
  .campaigns-grid {
    grid-template-columns: 1fr;
  }
}
</style>
