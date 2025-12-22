<template>
  <div class="vip-page">
    <div class="container">
      <!-- Hero Section -->
      <div class="vip-hero">
        <h1 class="hero-title">VIP Rewards Program</h1>
        <p class="hero-subtitle">Unlock exclusive benefits as you play</p>
      </div>

      <!-- Current Level -->
      <div class="current-level-section">
        <div class="level-badge" :class="currentLevel?.slug">
          <div class="badge-icon">{{ currentLevel?.icon }}</div>
          <div class="badge-content">
            <div class="badge-level">{{ currentLevel?.name }}</div>
            <div class="badge-subtitle">Your Current Tier</div>
          </div>
        </div>

        <div class="level-stats">
          <div class="stat-item">
            <div class="stat-value">{{ progress.total_wagered_formatted }}</div>
            <div class="stat-label">Total Wagered</div>
          </div>
          <div class="stat-item">
            <div class="stat-value">{{ progress.current_level }}</div>
            <div class="stat-label">Current Level</div>
          </div>
          <div class="stat-item">
            <div class="stat-value">{{ progress.progress_percent }}%</div>
            <div class="stat-label">Progress to Next</div>
          </div>
        </div>
      </div>

      <!-- Progress to Next Level -->
      <div v-if="nextLevel" class="progress-section">
        <div class="progress-header">
          <h2>Progress to {{ nextLevel.name }}</h2>
          <div class="progress-amount">
            ₱{{ formatMoney(progress.wagered_in_level) }} / ₱{{ formatMoney(nextLevel.wager_requirement) }}
          </div>
        </div>
        
        <div class="progress-bar-container">
          <div class="progress-bar">
            <div class="progress-fill" :style="{ width: progress.progress_percent + '%' }"></div>
          </div>
          <div class="progress-info">
            <span>{{ progress.progress_percent }}% Complete</span>
            <span>₱{{ formatMoney(progress.remaining_wager) }} remaining</span>
          </div>
        </div>

        <div class="next-benefits">
          <h3>Unlock These Benefits:</h3>
          <div class="benefits-preview">
            <div class="benefit-preview">
              <span class="benefit-icon">🎁</span>
              <span>{{ nextLevel.rakeback_percent }}% Rakeback</span>
            </div>
            <div class="benefit-preview">
              <span class="benefit-icon">💰</span>
              <span>{{ nextLevel.level_up_bonus_formatted }} Level Up Bonus</span>
            </div>
            <div class="benefit-preview">
              <span class="benefit-icon">🎂</span>
              <span>{{ nextLevel.birthday_bonus_formatted }} Birthday Bonus</span>
            </div>
            <div class="benefit-preview">
              <span class="benefit-icon">⚡</span>
              <span>{{ nextLevel.weekly_bonus_formatted }} Weekly Bonus</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Current Benefits -->
      <div class="benefits-section">
        <h2>Your Current Benefits</h2>
        <div class="benefits-grid">
          <div class="benefit-card">
            <div class="benefit-icon">🎁</div>
            <div class="benefit-title">Rakeback</div>
            <div class="benefit-value">{{ currentLevel?.rakeback_percent }}%</div>
            <div class="benefit-description">Get back a percentage of your total wagers</div>
          </div>
          <div class="benefit-card">
            <div class="benefit-icon">💰</div>
            <div class="benefit-title">Level Up Bonus</div>
            <div class="benefit-value">{{ currentLevel?.level_up_bonus_formatted }}</div>
            <div class="benefit-description">Instant bonus when you level up</div>
          </div>
          <div class="benefit-card">
            <div class="benefit-icon">🎂</div>
            <div class="benefit-title">Birthday Bonus</div>
            <div class="benefit-value">{{ currentLevel?.birthday_bonus_formatted }}</div>
            <div class="benefit-description">Special gift on your birthday</div>
          </div>
          <div class="benefit-card">
            <div class="benefit-icon">⚡</div>
            <div class="benefit-title">Weekly Bonus</div>
            <div class="benefit-value">{{ currentLevel?.weekly_bonus_formatted }}</div>
            <div class="benefit-description">Claim every Monday</div>
          </div>
          <div class="benefit-card">
            <div class="benefit-icon">💎</div>
            <div class="benefit-title">Monthly Bonus</div>
            <div class="benefit-value">{{ currentLevel?.monthly_bonus_formatted }}</div>
            <div class="benefit-description">Exclusive monthly reward</div>
          </div>
          <div class="benefit-card">
            <div class="benefit-icon">⚡</div>
            <div class="benefit-title">Faster Withdrawals</div>
            <div class="benefit-value">Priority</div>
            <div class="benefit-description">Get your winnings faster</div>
          </div>
        </div>
      </div>

      <!-- All VIP Levels -->
      <div class="all-levels-section">
        <h2>All VIP Levels</h2>
        <div class="levels-list">
          <div 
            v-for="level in allLevels" 
            :key="level.id"
            class="level-item"
            :class="{ 
              current: level.level === progress.current_level,
              locked: level.level > progress.current_level 
            }"
          >
            <div class="level-header">
              <div class="level-icon-badge" :class="level.slug">
                {{ level.icon }}
              </div>
              <div class="level-info">
                <div class="level-name">{{ level.name }}</div>
                <div class="level-requirement">
                  {{ level.level === 1 ? 'Starting Level' : `Wager ₱${formatMoney(level.wager_requirement)}` }}
                </div>
              </div>
              <div v-if="level.level === progress.current_level" class="current-badge">
                Current
              </div>
              <div v-else-if="level.level < progress.current_level" class="completed-badge">
                ✓ Unlocked
              </div>
              <div v-else class="locked-badge">
                🔒 Locked
              </div>
            </div>

            <div class="level-benefits">
              <div class="level-benefit">
                <span class="benefit-label">Rakeback:</span>
                <span class="benefit-val">{{ level.rakeback_percent }}%</span>
              </div>
              <div class="level-benefit">
                <span class="benefit-label">Level Up Bonus:</span>
                <span class="benefit-val">{{ level.level_up_bonus_formatted }}</span>
              </div>
              <div class="level-benefit">
                <span class="benefit-label">Weekly Bonus:</span>
                <span class="benefit-val">{{ level.weekly_bonus_formatted }}</span>
              </div>
              <div class="level-benefit">
                <span class="benefit-label">Monthly Bonus:</span>
                <span class="benefit-val">{{ level.monthly_bonus_formatted }}</span>
              </div>
              <div class="level-benefit">
                <span class="benefit-label">Birthday Bonus:</span>
                <span class="benefit-val">{{ level.birthday_bonus_formatted }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- VIP Info -->
      <div class="vip-info-section">
        <h2>How It Works</h2>
        <div class="info-cards">
          <div class="info-card">
            <div class="info-icon">🎮</div>
            <h3>Play & Wager</h3>
            <p>Every bet you place counts towards your VIP progress. The more you play, the faster you level up!</p>
          </div>
          <div class="info-card">
            <div class="info-icon">⬆️</div>
            <h3>Level Up</h3>
            <p>Reach the wagering requirement to unlock the next VIP level and enjoy better benefits.</p>
          </div>
          <div class="info-card">
            <div class="info-icon">🎁</div>
            <h3>Enjoy Rewards</h3>
            <p>Higher levels unlock bigger bonuses, better rakeback, and exclusive perks.</p>
          </div>
          <div class="info-card">
            <div class="info-icon">♾️</div>
            <h3>Keep Your Status</h3>
            <p>Once unlocked, your VIP level is permanent. You'll never lose your benefits!</p>
          </div>
        </div>

        <div class="faq-box">
          <h3>Frequently Asked Questions</h3>
          <div class="faq-item">
            <strong>Q: How do I increase my VIP level?</strong>
            <p>A: Simply play games and wager. Every bet contributes to your progress.</p>
          </div>
          <div class="faq-item">
            <strong>Q: Can I lose my VIP level?</strong>
            <p>A: No! Once you reach a VIP level, it's yours forever.</p>
          </div>
          <div class="faq-item">
            <strong>Q: How do I claim my bonuses?</strong>
            <p>A: Weekly and monthly bonuses are automatically credited to your account. Check the Bonuses page to claim them.</p>
          </div>
          <div class="faq-item">
            <strong>Q: What is rakeback?</strong>
            <p>A: Rakeback returns a percentage of your total wagers to you, regardless of wins or losses. It's calculated and paid weekly.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const progress = ref({
  current_level: 1,
  total_wagered: 0,
  total_wagered_formatted: '₱0',
  wagered_in_level: 0,
  remaining_wager: 0,
  progress_percent: 0,
});

const allLevels = ref([]);

const currentLevel = computed(() => {
  return allLevels.value.find(l => l.level === progress.value.current_level);
});

const nextLevel = computed(() => {
  return allLevels.value.find(l => l.level === progress.value.current_level + 1);
});

function formatMoney(amount) {
  return Number(amount || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

async function fetchVipProgress() {
  try {
    const response = await axios.get('/api/vip/progress');
    progress.value = response.data;
  } catch (error) {
    console.error('Failed to fetch VIP progress:', error);
  }
}

async function fetchVipLevels() {
  try {
    const response = await axios.get('/api/vip/levels');
    allLevels.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch VIP levels:', error);
  }
}

onMounted(() => {
  fetchVipProgress();
  fetchVipLevels();
});
</script>

<style scoped>
.vip-page {
  min-height: 100vh;
  padding: 40px 20px;
  background: linear-gradient(180deg, rgba(102, 126, 234, 0.05) 0%, transparent 100%);
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.vip-hero {
  text-align: center;
  margin-bottom: 40px;
}

.hero-title {
  font-size: 48px;
  font-weight: 800;
  margin-bottom: 12px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-subtitle {
  font-size: 18px;
  color: rgba(255, 255, 255, 0.7);
}

.current-level-section {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 32px;
  align-items: center;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 32px;
  margin-bottom: 40px;
}

.level-badge {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 24px 32px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 2px solid;
}

.level-badge.bronze { border-color: #cd7f32; }
.level-badge.silver { border-color: #c0c0c0; }
.level-badge.gold { border-color: #ffd700; }
.level-badge.platinum { border-color: #e5e4e2; }
.level-badge.diamond { border-color: #b9f2ff; }

.badge-icon {
  font-size: 64px;
}

.badge-level {
  font-size: 32px;
  font-weight: 800;
  line-height: 1;
}

.badge-subtitle {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
  margin-top: 4px;
}

.level-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.stat-item {
  text-align: center;
}

.stat-value {
  font-size: 28px;
  font-weight: 800;
  color: #667eea;
  margin-bottom: 4px;
}

.stat-label {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 1px;
}

.progress-section {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 40px;
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.progress-header h2 {
  font-size: 24px;
  font-weight: 700;
}

.progress-amount {
  font-size: 18px;
  font-weight: 700;
  color: #667eea;
}

.progress-bar-container {
  margin-bottom: 24px;
}

.progress-bar {
  height: 16px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  transition: width 0.5s ease;
}

.progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
}

.next-benefits h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 16px;
}

.benefits-preview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.benefit-preview {
  background: rgba(102, 126, 234, 0.1);
  border: 1px solid rgba(102, 126, 234, 0.3);
  border-radius: 10px;
  padding: 12px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 600;
}

.benefit-icon {
  font-size: 20px;
}

.benefits-section {
  margin-bottom: 40px;
}

.benefits-section h2 {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 24px;
}

.benefits-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.benefit-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  text-align: center;
  transition: all 0.2s;
}

.benefit-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2);
}

.benefit-card .benefit-icon {
  font-size: 40px;
  margin-bottom: 12px;
}

.benefit-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 8px;
  color: rgba(255, 255, 255, 0.9);
}

.benefit-value {
  font-size: 24px;
  font-weight: 800;
  color: #667eea;
  margin-bottom: 8px;
}

.benefit-description {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.4;
}

.all-levels-section {
  margin-bottom: 40px;
}

.all-levels-section h2 {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 24px;
}

.levels-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.level-item {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  transition: all 0.2s;
}

.level-item.current {
  border-color: rgba(102, 126, 234, 0.5);
  box-shadow: 0 0 20px rgba(102, 126, 234, 0.2);
}

.level-item.locked {
  opacity: 0.5;
}

.level-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 16px;
}

.level-icon-badge {
  font-size: 48px;
  width: 72px;
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 16px;
  border: 2px solid;
}

.level-icon-badge.bronze { border-color: #cd7f32; background: rgba(205, 127, 50, 0.1); }
.level-icon-badge.silver { border-color: #c0c0c0; background: rgba(192, 192, 192, 0.1); }
.level-icon-badge.gold { border-color: #ffd700; background: rgba(255, 215, 0, 0.1); }
.level-icon-badge.platinum { border-color: #e5e4e2; background: rgba(229, 228, 226, 0.1); }
.level-icon-badge.diamond { border-color: #b9f2ff; background: rgba(185, 242, 255, 0.1); }

.level-info {
  flex: 1;
}

.level-name {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 4px;
}

.level-requirement {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
}

.current-badge {
  padding: 6px 16px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
}

.completed-badge {
  padding: 6px 16px;
  background: rgba(72, 187, 120, 0.2);
  border: 1px solid rgba(72, 187, 120, 0.3);
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
  color: #48bb78;
}

.locked-badge {
  padding: 6px 16px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  font-size: 12px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.5);
}

.level-benefits {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.level-benefit {
  display: flex;
  justify-content: space-between;
  padding: 8px 12px;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
  font-size: 13px;
}

.benefit-label {
  color: rgba(255, 255, 255, 0.6);
}

.benefit-val {
  font-weight: 700;
  color: #667eea;
}

.vip-info-section {
  margin-bottom: 40px;
}

.vip-info-section h2 {
  font-size: 28px;
  font-weight: 700;
  margin-bottom: 24px;
}

.info-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.info-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
  text-align: center;
}

.info-icon {
  font-size: 48px;
  margin-bottom: 16px;
}

.info-card h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
}

.info-card p {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.6;
}

.faq-box {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 32px;
}

.faq-box h3 {
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 20px;
}

.faq-item {
  margin-bottom: 20px;
}

.faq-item:last-child {
  margin-bottom: 0;
}

.faq-item strong {
  display: block;
  margin-bottom: 8px;
  color: #667eea;
}

.faq-item p {
  color: rgba(255, 255, 255, 0.8);
  line-height: 1.6;
}

@media (max-width: 768px) {
  .current-level-section {
    grid-template-columns: 1fr;
  }
  
  .level-stats {
    grid-template-columns: repeat(3, 1fr);
  }
  
  .hero-title {
    font-size: 32px;
  }
}
</style>
