<template>
  <div class="bonuses-page">
    <!-- Award Bonus Section -->
    <div class="award-section">
      <div class="section-header">
        <h2>🎁 Award Bonus</h2>
        <p>Award promotional bonuses to users</p>
      </div>

      <div class="award-form">
        <div class="form-row">
          <div class="form-group">
            <label>User ID *</label>
            <input 
              v-model="bonusForm.user_id" 
              type="number" 
              placeholder="Enter user ID"
              required
            />
          </div>

          <div class="form-group">
            <label>Bonus Amount *</label>
            <input 
              v-model="bonusForm.amount" 
              type="number" 
              step="0.01"
              min="1"
              placeholder="0.00"
              required
            />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Wagering Requirement (x) *</label>
            <input 
              v-model="bonusForm.wagering_requirement" 
              type="number" 
              step="0.1"
              min="0"
              placeholder="e.g., 10 (means 10x wagering)"
              required
            />
          </div>

          <div class="form-group">
            <label>Valid Until *</label>
            <input 
              v-model="bonusForm.valid_until" 
              type="datetime-local"
              required
            />
          </div>
        </div>

        <div class="form-group">
          <label>Description *</label>
          <textarea 
            v-model="bonusForm.description" 
            rows="3"
            placeholder="e.g., Welcome bonus, Loyalty reward, Special promotion..."
            required
          ></textarea>
        </div>

        <button 
          @click="handleAwardBonus" 
          class="award-btn"
          :disabled="processing || !isBonusFormValid"
        >
          {{ processing ? 'Awarding...' : '🎁 Award Bonus' }}
        </button>
      </div>
    </div>

    <!-- Active Bonuses Section -->
    <div class="bonuses-section">
      <div class="section-header">
        <h2>📊 Active Bonuses</h2>
        <button @click="refreshBonuses" class="refresh-btn" :disabled="loading">
          {{ loading ? '⟳' : '🔄' }} Refresh
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading && bonuses.length === 0" class="loading-state">
        <div class="spinner"></div>
        <p>Loading bonuses...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="error-state">
        <p>{{ error }}</p>
        <button @click="refreshBonuses" class="retry-btn">Try Again</button>
      </div>

      <!-- Empty State -->
      <div v-else-if="bonuses.length === 0" class="empty-state">
        <div class="empty-icon">🎁</div>
        <p>No active bonuses at the moment</p>
        <small>Awarded bonuses will appear here</small>
      </div>

      <!-- Bonuses Grid -->
      <div v-else class="bonuses-grid">
        <div v-for="bonus in bonuses" :key="bonus.id" class="bonus-card">
          <div class="bonus-header">
            <div class="bonus-id">#{{ bonus.id }}</div>
            <span class="bonus-status" :class="`status-${bonus.status}`">
              {{ bonus.status }}
            </span>
          </div>

          <div class="bonus-user">
            <span class="user-icon">👤</span>
            <div>
              <div class="user-name">{{ bonus.user?.username }}</div>
              <div class="user-id">ID: {{ bonus.user_id }}</div>
            </div>
          </div>

          <div class="bonus-details">
            <div class="detail-item">
              <span class="label">Amount:</span>
              <span class="value amount">₱{{ formatMoney(bonus.amount) }}</span>
            </div>
            
            <div class="detail-item">
              <span class="label">Wagering:</span>
              <span class="value">{{ bonus.wagering_requirement }}x</span>
            </div>
            
            <div class="detail-item">
              <span class="label">Progress:</span>
              <span class="value">
                ₱{{ formatMoney(bonus.wagered_amount) }} / ₱{{ formatMoney(bonus.required_wager) }}
              </span>
            </div>

            <div class="progress-bar">
              <div 
                class="progress-fill" 
                :style="{ width: calculateProgress(bonus) + '%' }"
              ></div>
            </div>
            <div class="progress-text">{{ calculateProgress(bonus) }}% completed</div>
          </div>

          <div class="bonus-info">
            <div class="info-item">
              <span class="info-label">📝 Description:</span>
              <p class="description">{{ bonus.description }}</p>
            </div>
            
            <div class="info-item">
              <span class="info-label">📅 Expires:</span>
              <p class="date">{{ formatDate(bonus.valid_until) }}</p>
            </div>
            
            <div class="info-item">
              <span class="info-label">🎯 Type:</span>
              <p class="type">{{ formatBonusType(bonus.type) }}</p>
            </div>
          </div>

          <div class="bonus-actions">
            <button 
              @click="viewBonusHistory(bonus)" 
              class="action-btn view-btn"
            >
              📊 View History
            </button>
            <button 
              @click="confirmCancelBonus(bonus)" 
              class="action-btn cancel-btn"
              :disabled="bonus.status !== 'active'"
            >
              ❌ Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- User Bonus History Modal -->
    <div v-if="showHistoryModal" class="modal-overlay" @click="closeHistoryModal">
      <div class="modal-content large" @click.stop>
        <div class="modal-header">
          <h3>📊 Bonus History</h3>
          <button @click="closeHistoryModal" class="close-btn">✕</button>
        </div>

        <div class="history-content">
          <div class="history-summary">
            <div class="summary-card">
              <div class="summary-label">User</div>
              <div class="summary-value">{{ selectedBonus?.user?.username }}</div>
            </div>
            <div class="summary-card">
              <div class="summary-label">Bonus ID</div>
              <div class="summary-value">#{{ selectedBonus?.id }}</div>
            </div>
            <div class="summary-card">
              <div class="summary-label">Amount</div>
              <div class="summary-value">₱{{ formatMoney(selectedBonus?.amount) }}</div>
            </div>
            <div class="summary-card">
              <div class="summary-label">Status</div>
              <div class="summary-value">
                <span class="bonus-status" :class="`status-${selectedBonus?.status}`">
                  {{ selectedBonus?.status }}
                </span>
              </div>
            </div>
          </div>

          <div class="wagering-details">
            <h4>Wagering Progress</h4>
            <div class="wager-stats">
              <div class="stat-item">
                <span class="stat-label">Required Wager:</span>
                <span class="stat-value">₱{{ formatMoney(selectedBonus?.required_wager) }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Current Wagered:</span>
                <span class="stat-value">₱{{ formatMoney(selectedBonus?.wagered_amount) }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Remaining:</span>
                <span class="stat-value">₱{{ formatMoney(selectedBonus?.remaining_wager) }}</span>
              </div>
            </div>
            
            <div class="large-progress-bar">
              <div 
                class="progress-fill" 
                :style="{ width: calculateProgress(selectedBonus) + '%' }"
              ></div>
            </div>
            <div class="progress-text-large">
              {{ calculateProgress(selectedBonus) }}% completed
            </div>
          </div>

          <div class="bonus-timeline">
            <h4>Timeline</h4>
            <div class="timeline-item">
              <span class="timeline-icon">🎁</span>
              <div>
                <div class="timeline-label">Awarded</div>
                <div class="timeline-date">{{ formatDate(selectedBonus?.created_at) }}</div>
              </div>
            </div>
            
            <div v-if="selectedBonus?.claimed_at" class="timeline-item">
              <span class="timeline-icon">✅</span>
              <div>
                <div class="timeline-label">Claimed</div>
                <div class="timeline-date">{{ formatDate(selectedBonus?.claimed_at) }}</div>
              </div>
            </div>
            
            <div v-if="selectedBonus?.expired_at" class="timeline-item">
              <span class="timeline-icon">⏰</span>
              <div>
                <div class="timeline-label">Expired</div>
                <div class="timeline-date">{{ formatDate(selectedBonus?.expired_at) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div v-if="showCancelModal" class="modal-overlay" @click="closeCancelModal">
      <div class="modal-content" @click.stop>
        <h3>⚠️ Cancel Bonus</h3>
        <p>Are you sure you want to cancel this bonus?</p>
        
        <div class="cancel-info">
          <div class="info-row">
            <span>User:</span>
            <strong>{{ selectedBonus?.user?.username }}</strong>
          </div>
          <div class="info-row">
            <span>Amount:</span>
            <strong>₱{{ formatMoney(selectedBonus?.amount) }}</strong>
          </div>
          <div class="info-row">
            <span>Progress:</span>
            <strong>{{ calculateProgress(selectedBonus) }}%</strong>
          </div>
        </div>

        <p class="warning-text">
          ⚠️ This action cannot be undone. The bonus will be removed from the user's account.
        </p>

        <div class="modal-actions">
          <button 
            @click="handleCancelBonus" 
            class="btn-danger"
            :disabled="processing"
          >
            {{ processing ? 'Canceling...' : 'Yes, Cancel Bonus' }}
          </button>
          <button @click="closeCancelModal" class="btn-secondary" :disabled="processing">
            No, Keep It
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useBonusStore } from '../../stores/bonus';
import { storeToRefs } from 'pinia';

const bonusStore = useBonusStore();
const { bonuses, loading, error } = storeToRefs(bonusStore);

const processing = ref(false);
const showHistoryModal = ref(false);
const showCancelModal = ref(false);
const selectedBonus = ref(null);

const bonusForm = reactive({
  user_id: '',
  amount: '',
  wagering_requirement: '10',
  valid_until: '',
  description: '',
});

const isBonusFormValid = computed(() => {
  return bonusForm.user_id > 0 &&
         bonusForm.amount > 0 &&
         bonusForm.wagering_requirement >= 0 &&
         bonusForm.valid_until &&
         bonusForm.description.trim().length > 0;
});

onMounted(() => {
  refreshBonuses();
  setDefaultValidUntil();
});

function setDefaultValidUntil() {
  const date = new Date();
  date.setDate(date.getDate() + 7); // Default: 7 days from now
  bonusForm.valid_until = date.toISOString().slice(0, 16);
}

async function refreshBonuses() {
  await bonusStore.fetchActiveBonuses();
}

async function handleAwardBonus() {
  if (!isBonusFormValid.value) return;
  
  processing.value = true;

  const data = {
    user_id: parseInt(bonusForm.user_id),
    amount: parseFloat(bonusForm.amount),
    type: 'promotional',
    wagering_requirement: parseFloat(bonusForm.wagering_requirement),
    valid_until: bonusForm.valid_until,
    description: bonusForm.description,
  };

  const result = await bonusStore.awardBonus(data);
  
  processing.value = false;

  if (result.success) {
    alert('✅ Bonus awarded successfully!');
    resetForm();
    refreshBonuses();
  } else {
    alert(`❌ Error: ${result.message}`);
  }
}

function resetForm() {
  bonusForm.user_id = '';
  bonusForm.amount = '';
  bonusForm.wagering_requirement = '10';
  bonusForm.description = '';
  setDefaultValidUntil();
}

function viewBonusHistory(bonus) {
  selectedBonus.value = bonus;
  showHistoryModal.value = true;
}

function closeHistoryModal() {
  showHistoryModal.value = false;
  selectedBonus.value = null;
}

function confirmCancelBonus(bonus) {
  selectedBonus.value = bonus;
  showCancelModal.value = true;
}

function closeCancelModal() {
  showCancelModal.value = false;
  selectedBonus.value = null;
}

async function handleCancelBonus() {
  processing.value = true;

  const result = await bonusStore.cancelBonus(selectedBonus.value.id);
  
  processing.value = false;

  if (result.success) {
    closeCancelModal();
    alert('✅ Bonus cancelled successfully!');
    refreshBonuses();
  } else {
    alert(`❌ Error: ${result.message}`);
  }
}

function calculateProgress(bonus) {
  if (!bonus || !bonus.required_wager) return 0;
  const progress = (bonus.wagered_amount / bonus.required_wager) * 100;
  return Math.min(Math.round(progress), 100);
}

function formatMoney(value) {
  if (!value) return '0.00';
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatBonusType(type) {
  const types = {
    deposit: 'Deposit Match',
    promotional: 'Promotional',
    referral: 'Referral',
    cashback: 'Cashback',
    vip: 'VIP Reward',
  };
  return types[type] || type;
}
</script>

<style scoped>
.bonuses-page {
  max-width: 1400px;
}

.award-section, .bonuses-section {
  background: white;
  border-radius: 12px;
  padding: 30px;
  margin-bottom: 30px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  padding-bottom: 15px;
  border-bottom: 2px solid #e2e8f0;
}

.section-header h2 {
  font-size: 22px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.section-header p {
  color: #718096;
  font-size: 14px;
  margin: 5px 0 0;
}

.refresh-btn {
  padding: 10px 20px;
  background: #f7fafc;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.refresh-btn:hover:not(:disabled) {
  background: #edf2f7;
  border-color: #cbd5e0;
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.award-form {
  max-width: 800px;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  margin-bottom: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 8px;
  font-size: 14px;
}

.form-group input, .form-group textarea {
  padding: 12px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input:focus, .form-group textarea:focus {
  outline: none;
  border-color: #667eea;
}

.award-btn {
  margin-top: 10px;
  padding: 14px 32px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 16px;
  cursor: pointer;
  transition: transform 0.2s;
}

.award-btn:hover:not(:disabled) {
  transform: translateY(-2px);
}

.award-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.loading-state, .error-state, .empty-state {
  text-align: center;
  padding: 60px 20px;
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

.empty-state .empty-icon {
  font-size: 64px;
  margin-bottom: 15px;
}

.empty-state p {
  font-size: 18px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 5px;
}

.empty-state small {
  color: #718096;
}

.bonuses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 20px;
}

.bonus-card {
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 20px;
  transition: all 0.2s;
}

.bonus-card:hover {
  border-color: #cbd5e0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.bonus-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.bonus-id {
  font-weight: 700;
  color: #a0aec0;
  font-size: 14px;
}

.bonus-status {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  text-transform: capitalize;
}

.status-active { background: #c6f6d5; color: #22543d; }
.status-claimed { background: #bee3f8; color: #2c5282; }
.status-expired { background: #fed7d7; color: #742a2a; }
.status-cancelled { background: #feebc8; color: #7c2d12; }

.bonus-user {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f7fafc;
  border-radius: 8px;
  margin-bottom: 15px;
}

.user-icon {
  font-size: 24px;
}

.user-name {
  font-weight: 600;
  color: #2d3748;
}

.user-id {
  font-size: 12px;
  color: #718096;
}

.bonus-details {
  margin-bottom: 15px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}

.detail-item .label {
  color: #718096;
  font-size: 14px;
}

.detail-item .value {
  font-weight: 600;
  color: #2d3748;
  font-size: 14px;
}

.detail-item .value.amount {
  color: #48bb78;
  font-size: 16px;
}

.progress-bar {
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
  margin: 10px 0 5px;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #48bb78, #38a169);
  transition: width 0.3s;
}

.progress-text {
  font-size: 12px;
  color: #718096;
  text-align: center;
}

.bonus-info {
  padding: 15px 0;
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  margin-bottom: 15px;
}

.info-item {
  margin-bottom: 12px;
}

.info-item:last-child {
  margin-bottom: 0;
}

.info-label {
  display: block;
  font-size: 12px;
  color: #718096;
  margin-bottom: 4px;
}

.description, .date, .type {
  font-size: 14px;
  color: #2d3748;
  margin: 0;
}

.bonus-actions {
  display: flex;
  gap: 10px;
}

.action-btn {
  flex: 1;
  padding: 10px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.2s;
}

.view-btn {
  background: #bee3f8;
  color: #2c5282;
}

.view-btn:hover {
  background: #90cdf4;
}

.cancel-btn {
  background: #fed7d7;
  color: #742a2a;
}

.cancel-btn:hover:not(:disabled) {
  background: #feb2b2;
}

.cancel-btn:disabled {
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

.modal-content.large {
  max-width: 700px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.modal-header h3 {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin: 0;
}

.close-btn {
  width: 32px;
  height: 32px;
  border: none;
  background: #f7fafc;
  border-radius: 50%;
  cursor: pointer;
  font-size: 18px;
  transition: background 0.2s;
}

.close-btn:hover {
  background: #e2e8f0;
}

.modal-content > h3 {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 15px;
}

.modal-content > p {
  color: #718096;
  margin-bottom: 20px;
}

.history-content {
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.history-summary {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px;
}

.summary-card {
  background: #f7fafc;
  padding: 15px;
  border-radius: 8px;
}

.summary-label {
  font-size: 12px;
  color: #718096;
  margin-bottom: 5px;
}

.summary-value {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
}

.wagering-details h4, .bonus-timeline h4 {
  font-size: 16px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 15px;
}

.wager-stats {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 15px;
}

.stat-item {
  display: flex;
  justify-content: space-between;
}

.stat-label {
  color: #718096;
}

.stat-value {
  font-weight: 600;
  color: #2d3748;
}

.large-progress-bar {
  height: 12px;
  background: #e2e8f0;
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 8px;
}

.progress-text-large {
  font-size: 14px;
  font-weight: 600;
  color: #48bb78;
  text-align: center;
}

.bonus-timeline {
  padding-top: 15px;
  border-top: 2px solid #e2e8f0;
}

.timeline-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f7fafc;
  border-radius: 8px;
  margin-bottom: 10px;
}

.timeline-icon {
  font-size: 24px;
}

.timeline-label {
  font-weight: 600;
  color: #2d3748;
}

.timeline-date {
  font-size: 13px;
  color: #718096;
}

.cancel-info {
  background: #f7fafc;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 15px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #e2e8f0;
}

.info-row:last-child {
  border-bottom: none;
}

.warning-text {
  background: #fef5e7;
  border-left: 4px solid #f6ad55;
  padding: 12px;
  border-radius: 4px;
  color: #7c2d12;
  font-size: 14px;
}

.modal-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
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

.btn-danger:disabled, .btn-secondary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
