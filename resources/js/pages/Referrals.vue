<template>
  <div class="referrals-page">
    <div class="container">
      <h1 class="page-title">Referral Program</h1>

      <!-- Referral Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">👥</div>
          <div class="stat-content">
            <div class="stat-label">Total Referrals</div>
            <div class="stat-value">{{ stats.total_referrals }}</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">💰</div>
          <div class="stat-content">
            <div class="stat-label">Total Earnings</div>
            <div class="stat-value">₱{{ formatMoney(stats.total_earnings) }}</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">📊</div>
          <div class="stat-content">
            <div class="stat-label">Active Referrals</div>
            <div class="stat-value">{{ stats.active_referrals }}</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🎁</div>
          <div class="stat-content">
            <div class="stat-label">Pending Rewards</div>
            <div class="stat-value">₱{{ formatMoney(stats.pending_rewards) }}</div>
          </div>
        </div>
      </div>

      <!-- Referral Code Section -->
      <div class="referral-code-section">
        <h2>Your Referral Code</h2>
        <div class="code-container">
          <div class="code-box">
            <div class="code-label">Referral Code</div>
            <div class="code-value">{{ referralCode }}</div>
          </div>
          <button @click="copyCode" class="btn-copy">
            {{ copied ? '✓ Copied!' : '📋 Copy' }}
          </button>
        </div>

        <div class="referral-link">
          <div class="link-label">Referral Link</div>
          <div class="link-box">
            <input 
              type="text" 
              :value="referralLink" 
              readonly 
              class="link-input"
              @focus="$event.target.select()"
            >
            <button @click="copyLink" class="btn-copy-link">
              {{ copiedLink ? '✓' : '📋' }}
            </button>
          </div>
        </div>

        <div class="share-buttons">
          <button @click="shareWhatsApp" class="btn-share whatsapp">
            <span>💬</span> WhatsApp
          </button>
          <button @click="shareFacebook" class="btn-share facebook">
            <span>📘</span> Facebook
          </button>
          <button @click="shareTwitter" class="btn-share twitter">
            <span>🐦</span> Twitter
          </button>
          <button @click="shareTelegram" class="btn-share telegram">
            <span>✈️</span> Telegram
          </button>
        </div>
      </div>

      <!-- How it Works -->
      <div class="how-it-works">
        <h2>How It Works</h2>
        <div class="steps-grid">
          <div class="step-card">
            <div class="step-number">1</div>
            <h3>Share Your Code</h3>
            <p>Share your unique referral code with friends and family</p>
          </div>
          <div class="step-card">
            <div class="step-number">2</div>
            <h3>They Sign Up</h3>
            <p>When they register using your code, they become your referral</p>
          </div>
          <div class="step-card">
            <div class="step-number">3</div>
            <h3>They Play</h3>
            <p>When your referrals make their first deposit and play</p>
          </div>
          <div class="step-card">
            <div class="step-number">4</div>
            <h3>Earn Rewards</h3>
            <p>You earn ₱100 bonus for each qualified referral</p>
          </div>
        </div>

        <div class="info-box">
          <div class="info-icon">ℹ️</div>
          <div class="info-content">
            <strong>Bonus Requirements:</strong>
            <ul>
              <li>Your referral must deposit at least ₱500</li>
              <li>They must wager at least ₱1,000</li>
              <li>Bonus is credited within 24 hours after requirements are met</li>
              <li>Bonus has 20x wagering requirement</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Referral Leaderboard -->
      <div class="leaderboard-section">
        <h2>🏆 Top Referrers This Month</h2>
        <div class="leaderboard">
          <div v-for="(leader, index) in leaderboard" :key="index" class="leader-item">
            <div class="leader-rank">
              <span v-if="index === 0" class="medal gold">🥇</span>
              <span v-else-if="index === 1" class="medal silver">🥈</span>
              <span v-else-if="index === 2" class="medal bronze">🥉</span>
              <span v-else class="rank-number">{{ index + 1 }}</span>
            </div>
            <div class="leader-info">
              <div class="leader-username">{{ leader.username }}</div>
              <div class="leader-count">{{ leader.referrals }} referrals</div>
            </div>
            <div class="leader-earnings">₱{{ formatMoney(leader.earnings) }}</div>
          </div>
        </div>
      </div>

      <!-- My Referrals -->
      <div class="my-referrals-section">
        <h2>My Referrals</h2>
        
        <div v-if="referrals.length === 0" class="empty-state">
          <div class="empty-icon">👥</div>
          <p>No referrals yet</p>
          <p class="empty-subtitle">Start sharing your code to earn rewards!</p>
        </div>

        <div v-else class="referrals-list">
          <div v-for="referral in referrals" :key="referral.id" class="referral-item">
            <div class="referral-main">
              <div class="referral-info">
                <div class="referral-username">{{ referral.referred_username }}</div>
                <div class="referral-date">Joined {{ formatDate(referral.created_at) }}</div>
              </div>
              <div class="referral-status">
                <span class="status-badge" :class="referral.status">
                  {{ formatStatus(referral.status) }}
                </span>
                <div v-if="referral.status === 'completed'" class="referral-reward">
                  +₱{{ formatMoney(referral.reward_amount) }}
                </div>
              </div>
            </div>

            <div v-if="referral.status === 'pending'" class="referral-progress">
              <div class="progress-item">
                <span>Deposited:</span>
                <span :class="{ completed: referral.deposited >= 500 }">
                  {{ referral.deposited >= 500 ? '✓' : '○' }} ₱{{ formatMoney(referral.deposited) }} / ₱500
                </span>
              </div>
              <div class="progress-item">
                <span>Wagered:</span>
                <span :class="{ completed: referral.wagered >= 1000 }">
                  {{ referral.wagered >= 1000 ? '✓' : '○' }} ₱{{ formatMoney(referral.wagered) }} / ₱1,000
                </span>
              </div>
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
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const stats = ref({
  total_referrals: 0,
  total_earnings: 0,
  active_referrals: 0,
  pending_rewards: 0,
});
const referrals = ref([]);
const leaderboard = ref([]);
const copied = ref(false);
const copiedLink = ref(false);

const referralCode = computed(() => authStore.user?.referral_code || 'XXXXXX');
const referralLink = computed(() => {
  return `${window.location.origin}/register?ref=${referralCode.value}`;
});

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

function formatStatus(status) {
  const statuses = {
    pending: 'Pending',
    completed: 'Completed',
    expired: 'Expired',
  };
  return statuses[status] || status;
}

function copyCode() {
  navigator.clipboard.writeText(referralCode.value);
  copied.value = true;
  setTimeout(() => {
    copied.value = false;
  }, 2000);
}

function copyLink() {
  navigator.clipboard.writeText(referralLink.value);
  copiedLink.value = true;
  setTimeout(() => {
    copiedLink.value = false;
  }, 2000);
}

function shareWhatsApp() {
  const text = `Join this awesome casino using my referral code: ${referralCode.value}\n${referralLink.value}`;
  window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}

function shareFacebook() {
  window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralLink.value)}`, '_blank');
}

function shareTwitter() {
  const text = `Join this awesome casino using my referral code: ${referralCode.value}`;
  window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(referralLink.value)}`, '_blank');
}

function shareTelegram() {
  const text = `Join this awesome casino using my referral code: ${referralCode.value}\n${referralLink.value}`;
  window.open(`https://t.me/share/url?url=${encodeURIComponent(referralLink.value)}&text=${encodeURIComponent(text)}`, '_blank');
}

async function fetchReferralStats() {
  try {
    const response = await axios.get('/api/referrals/stats');
    stats.value = response.data;
  } catch (error) {
    console.error('Failed to fetch referral stats:', error);
  }
}

async function fetchMyReferrals() {
  try {
    const response = await axios.get('/api/referrals/my-referrals');
    referrals.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch referrals:', error);
  }
}

async function fetchLeaderboard() {
  try {
    const response = await axios.get('/api/referrals/leaderboard', {
      params: { limit: 10 },
    });
    leaderboard.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch leaderboard:', error);
  }
}

onMounted(() => {
  fetchReferralStats();
  fetchMyReferrals();
  fetchLeaderboard();
});
</script>

<style scoped>
.referrals-page {
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

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.stat-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  display: flex;
  gap: 16px;
  align-items: center;
}

.stat-icon {
  font-size: 36px;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 4px;
}

.stat-value {
  font-size: 24px;
  font-weight: 800;
  color: #667eea;
}

.referral-code-section {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 40px;
}

.referral-code-section h2 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 24px;
}

.code-container {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
}

.code-box {
  flex: 1;
  background: rgba(102, 126, 234, 0.1);
  border: 2px solid #667eea;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}

.code-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.code-value {
  font-size: 32px;
  font-weight: 800;
  color: #667eea;
  font-family: monospace;
  letter-spacing: 4px;
}

.btn-copy {
  padding: 20px 32px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 12px;
  color: white;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.btn-copy:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.referral-link {
  margin-bottom: 24px;
}

.link-label {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 8px;
}

.link-box {
  display: flex;
  gap: 12px;
}

.link-input {
  flex: 1;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 12px 16px;
  color: white;
  font-family: monospace;
  font-size: 14px;
}

.btn-copy-link {
  padding: 12px 20px;
  background: rgba(102, 126, 234, 0.2);
  border: 1px solid rgba(102, 126, 234, 0.3);
  border-radius: 8px;
  color: #667eea;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-copy-link:hover {
  background: rgba(102, 126, 234, 0.3);
}

.share-buttons {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
}

.btn-share {
  padding: 12px 20px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-share:hover {
  transform: translateY(-2px);
}

.btn-share.whatsapp {
  background: rgba(37, 211, 102, 0.2);
  border-color: rgba(37, 211, 102, 0.3);
}

.btn-share.facebook {
  background: rgba(24, 119, 242, 0.2);
  border-color: rgba(24, 119, 242, 0.3);
}

.btn-share.twitter {
  background: rgba(29, 161, 242, 0.2);
  border-color: rgba(29, 161, 242, 0.3);
}

.btn-share.telegram {
  background: rgba(0, 136, 204, 0.2);
  border-color: rgba(0, 136, 204, 0.3);
}

.how-it-works,
.leaderboard-section,
.my-referrals-section {
  margin-bottom: 40px;
}

.how-it-works h2,
.leaderboard-section h2,
.my-referrals-section h2 {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 24px;
}

.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

.step-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 24px;
  text-align: center;
}

.step-number {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 800;
  margin: 0 auto 16px;
}

.step-card h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
}

.step-card p {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.5;
}

.info-box {
  background: rgba(102, 126, 234, 0.1);
  border: 1px solid rgba(102, 126, 234, 0.3);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  gap: 16px;
}

.info-icon {
  font-size: 24px;
}

.info-content {
  flex: 1;
  font-size: 14px;
  line-height: 1.6;
}

.info-content ul {
  margin-top: 8px;
  padding-left: 20px;
}

.info-content li {
  margin-bottom: 4px;
  color: rgba(255, 255, 255, 0.8);
}

.leaderboard {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.leader-item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.leader-rank {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.rank-number {
  font-weight: 700;
  color: rgba(255, 255, 255, 0.6);
}

.leader-info {
  flex: 1;
}

.leader-username {
  font-weight: 600;
  font-size: 16px;
}

.leader-count {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
}

.leader-earnings {
  font-size: 18px;
  font-weight: 700;
  color: #48bb78;
}

.referrals-list {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.referral-item {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
}

.referral-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.referral-username {
  font-weight: 600;
  font-size: 15px;
}

.referral-date {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.6);
}

.referral-status {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-badge {
  padding: 4px 10px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-badge.pending {
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

.referral-reward {
  font-weight: 700;
  color: #48bb78;
}

.referral-progress {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.progress-item {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
}

.progress-item .completed {
  color: #48bb78;
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

.empty-subtitle {
  font-size: 14px;
  margin-top: 8px;
}

@media (max-width: 768px) {
  .code-container {
    flex-direction: column;
  }
  
  .steps-grid {
    grid-template-columns: 1fr;
  }
}
</style>
