<template>
  <div class="profile-page">
    <div class="container">
      <h1 class="page-title">Profile & Settings</h1>

      <div class="profile-grid">
        <!-- Account Information -->
        <div class="profile-section">
          <div class="section-header">
            <h2>👤 Account Information</h2>
          </div>
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">Username</span>
              <span class="info-value">{{ user?.username }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">Auth Method</span>
              <span class="info-value">
                <span class="auth-badge" :class="user?.auth_method">
                  {{ getAuthMethodLabel(user?.auth_method) }}
                </span>
              </span>
            </div>
            <div class="info-item">
              <span class="info-label">Member Since</span>
              <span class="info-value">{{ formatDate(user?.created_at) }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">VIP Level</span>
              <span class="info-value">
                <span class="vip-badge">{{ user?.vip_level?.name || 'Bronze' }}</span>
              </span>
            </div>
            <div v-if="user?.phone" class="info-item">
              <span class="info-label">Phone</span>
              <span class="info-value">{{ user?.phone }}</span>
            </div>
            <div v-if="user?.wallet_address" class="info-item">
              <span class="info-label">Wallet Address</span>
              <span class="info-value monospace">{{ shortenAddress(user?.wallet_address) }}</span>
            </div>
          </div>

          <!-- Upgrade Guest Account -->
          <div v-if="isGuest" class="upgrade-section">
            <div class="upgrade-notice">
              <div class="notice-icon">⚠️</div>
              <div class="notice-content">
                <h3>Guest Account</h3>
                <p>Upgrade your account to unlock full features and secure your balance</p>
              </div>
            </div>
            <button @click="showUpgradeModal = true" class="btn btn-primary">
              Upgrade Account
            </button>
          </div>
        </div>

        <!-- Security Settings -->
        <div class="profile-section">
          <div class="section-header">
            <h2>🔒 Security</h2>
          </div>
          
          <div v-if="user?.auth_method === 'phone'" class="security-options">
            <button @click="showPasswordModal = true" class="security-btn">
              <span class="btn-icon">🔑</span>
              <div class="btn-content">
                <span class="btn-title">Change Password</span>
                <span class="btn-desc">Update your account password</span>
              </div>
            </button>
          </div>

          <div class="security-info">
            <div class="info-box">
              <div class="info-icon">ℹ️</div>
              <div>
                <p><strong>Keep your account secure:</strong></p>
                <ul>
                  <li>Never share your password or wallet private keys</li>
                  <li>Enable two-factor authentication when available</li>
                  <li>Log out after each session on shared devices</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Preferences -->
        <div class="profile-section">
          <div class="section-header">
            <h2>⚙️ Preferences</h2>
          </div>
          
          <form @submit.prevent="savePreferences" class="preferences-form">
            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="preferences.notifications_enabled" />
                <span>Enable Notifications</span>
              </label>
              <p class="help-text">Receive updates about bonuses, wins, and promotions</p>
            </div>

            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="preferences.sound_effects" />
                <span>Sound Effects</span>
              </label>
              <p class="help-text">Play sounds during games</p>
            </div>

            <div class="form-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="preferences.animations" />
                <span>Animations</span>
              </label>
              <p class="help-text">Enable game animations (disable for better performance)</p>
            </div>

            <div v-if="success" class="success-message">{{ success }}</div>

            <button type="submit" class="btn btn-primary" :disabled="loading">
              {{ loading ? 'Saving...' : 'Save Preferences' }}
            </button>
          </form>
        </div>

        <!-- Danger Zone -->
        <div class="profile-section danger-zone">
          <div class="section-header">
            <h2>⚠️ Danger Zone</h2>
          </div>
          
          <div class="danger-actions">
            <button @click="confirmLogout" class="danger-btn">
              <span class="btn-icon">🚪</span>
              <div class="btn-content">
                <span class="btn-title">Logout</span>
                <span class="btn-desc">Sign out of your account</span>
              </div>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Change Password Modal -->
    <div v-if="showPasswordModal" class="modal-overlay" @click="showPasswordModal = false">
      <div class="modal-content" @click.stop>
        <button @click="showPasswordModal = false" class="close-btn">✕</button>
        <h2>Change Password</h2>
        
        <form @submit.prevent="changePassword" class="modal-form">
          <div class="form-group">
            <label>Current Password</label>
            <input 
              v-model="passwordForm.current_password"
              type="password"
              required
            />
          </div>
          <div class="form-group">
            <label>New Password</label>
            <input 
              v-model="passwordForm.new_password"
              type="password"
              minlength="8"
              required
            />
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <input 
              v-model="passwordForm.new_password_confirmation"
              type="password"
              required
            />
          </div>

          <div v-if="passwordError" class="error-message">{{ passwordError }}</div>

          <button type="submit" class="btn btn-primary btn-block" :disabled="passwordLoading">
            {{ passwordLoading ? 'Updating...' : 'Update Password' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useRouter } from 'vue-router';
import axios from 'axios';

const authStore = useAuthStore();
const router = useRouter();

const user = computed(() => authStore.user);
const isGuest = computed(() => authStore.isGuest);

const loading = ref(false);
const success = ref(null);
const showPasswordModal = ref(false);
const showUpgradeModal = ref(false);
const passwordLoading = ref(false);
const passwordError = ref(null);

const preferences = ref({
  notifications_enabled: true,
  sound_effects: true,
  animations: true,
});

const passwordForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const authMethodLabels = {
  phone: '📱 Phone',
  metamask: '🦊 MetaMask',
  telegram: '✈️ Telegram',
  guest: '👤 Guest',
};

function getAuthMethodLabel(method) {
  return authMethodLabels[method] || method;
}

function formatDate(date) {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

function shortenAddress(address) {
  if (!address) return '';
  return `${address.slice(0, 6)}...${address.slice(-4)}`;
}

async function savePreferences() {
  loading.value = true;
  success.value = null;

  try {
    await axios.put('/api/user/preferences', preferences.value);
    success.value = 'Preferences saved successfully';
    setTimeout(() => {
      success.value = null;
    }, 3000);
  } catch (error) {
    console.error('Failed to save preferences:', error);
  } finally {
    loading.value = false;
  }
}

async function changePassword() {
  if (passwordForm.value.new_password !== passwordForm.value.new_password_confirmation) {
    passwordError.value = 'Passwords do not match';
    return;
  }

  passwordLoading.value = true;
  passwordError.value = null;

  try {
    await axios.put('/api/user/password', {
      current_password: passwordForm.value.current_password,
      new_password: passwordForm.value.new_password,
      new_password_confirmation: passwordForm.value.new_password_confirmation,
    });

    showPasswordModal.value = false;
    success.value = 'Password changed successfully';
    
    // Reset form
    passwordForm.value = {
      current_password: '',
      new_password: '',
      new_password_confirmation: '',
    };
  } catch (error) {
    passwordError.value = error.response?.data?.message || 'Failed to change password';
  } finally {
    passwordLoading.value = false;
  }
}

function confirmLogout() {
  if (confirm('Are you sure you want to logout?')) {
    authStore.logout();
    router.push('/');
  }
}

async function fetchPreferences() {
  try {
    const response = await axios.get('/api/user/preferences');
    preferences.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch preferences:', error);
  }
}

onMounted(() => {
  fetchPreferences();
});
</script>

<style scoped>
.profile-page {
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

.profile-grid {
  display: grid;
  gap: 24px;
}

.profile-section {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  padding: 24px;
}

.section-header {
  margin-bottom: 20px;
}

.section-header h2 {
  font-size: 20px;
  font-weight: 700;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.info-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.5);
  text-transform: uppercase;
}

.info-value {
  font-size: 16px;
  font-weight: 600;
  color: white;
}

.info-value.monospace {
  font-family: monospace;
}

.auth-badge {
  background: rgba(102, 126, 234, 0.2);
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  color: #667eea;
  display: inline-block;
}

.vip-badge {
  background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  color: #1a1a2e;
  font-weight: 700;
  display: inline-block;
}

.upgrade-section {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.upgrade-notice {
  display: flex;
  gap: 16px;
  background: rgba(237, 137, 54, 0.1);
  border: 1px solid rgba(237, 137, 54, 0.3);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
}

.notice-icon {
  font-size: 32px;
}

.notice-content h3 {
  font-size: 16px;
  margin-bottom: 4px;
}

.notice-content p {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
}

.security-options {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.security-btn, .danger-btn {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 16px;
  cursor: pointer;
  transition: all 0.2s;
  color: white;
  text-align: left;
  width: 100%;
}

.security-btn:hover {
  background: rgba(255, 255, 255, 0.05);
}

.danger-btn {
  border-color: rgba(252, 129, 129, 0.3);
}

.danger-btn:hover {
  background: rgba(252, 129, 129, 0.1);
}

.btn-icon {
  font-size: 32px;
}

.btn-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.btn-title {
  font-size: 16px;
  font-weight: 600;
}

.btn-desc {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
}

.security-info {
  margin-top: 20px;
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

.info-box ul {
  list-style: none;
  padding: 0;
  margin: 8px 0 0 0;
}

.info-box li {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.8);
  margin-bottom: 4px;
  padding-left: 16px;
  position: relative;
}

.info-box li:before {
  content: '•';
  position: absolute;
  left: 0;
}

.preferences-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  font-weight: 600;
}

.checkbox-label input[type="checkbox"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.help-text {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  margin: 0;
  padding-left: 32px;
}

.danger-zone {
  border-color: rgba(252, 129, 129, 0.3);
}

.success-message {
  background: rgba(72, 187, 120, 0.1);
  border: 1px solid #48bb78;
  color: #48bb78;
  padding: 12px;
  border-radius: 8px;
  font-size: 14px;
}

.error-message {
  background: rgba(252, 129, 129, 0.1);
  border: 1px solid #fc8181;
  color: #fc8181;
  padding: 12px;
  border-radius: 8px;
  font-size: 14px;
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
  padding: 20px;
}

.modal-content {
  background: linear-gradient(135deg, #1e1e32 0%, #2a2a40 100%);
  border-radius: 16px;
  max-width: 500px;
  width: 100%;
  padding: 30px;
  position: relative;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.close-btn {
  position: absolute;
  top: 15px;
  right: 15px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  cursor: pointer;
  font-size: 18px;
}

.modal-content h2 {
  margin-bottom: 20px;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.modal-form .form-group label {
  font-weight: 600;
  font-size: 14px;
}

.modal-form .form-group input {
  padding: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  font-size: 14px;
}

.modal-form .form-group input:focus {
  outline: none;
  border-color: #667eea;
}
</style>
