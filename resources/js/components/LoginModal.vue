<template>
  <div class="modal-overlay" @click="$emit('close')">
    <div class="modal-content" @click.stop>
      <button @click="$emit('close')" class="close-btn">✕</button>
      
      <div class="modal-header">
        <h2>Welcome Back</h2>
        <p>Login to continue playing</p>
      </div>

      <div class="auth-tabs">
        <button 
          v-for="tab in tabs" 
          :key="tab.value"
          @click="activeTab = tab.value"
          class="tab-btn"
          :class="{ active: activeTab === tab.value }"
        >
          <span class="tab-icon">{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </div>

      <div class="tab-content">
        <!-- Phone Login -->
        <form v-if="activeTab === 'phone'" @submit.prevent="handlePhoneLogin" class="auth-form">
          <div class="form-group">
            <label>Phone Number</label>
            <input 
              v-model="phoneForm.phone" 
              type="tel" 
              placeholder="+639123456789"
              required
            />
          </div>
          <div class="form-group">
            <label>Password</label>
            <input 
              v-model="phoneForm.password" 
              type="password" 
              placeholder="Enter your password"
              required
            />
          </div>
          
          <div v-if="error" class="error-message">{{ error }}</div>
          
          <button type="submit" class="btn btn-primary btn-block" :disabled="loading">
            {{ loading ? 'Logging in...' : 'Login' }}
          </button>
        </form>

        <!-- MetaMask Login -->
        <div v-else-if="activeTab === 'metamask'" class="auth-form">
          <div class="metamask-info">
            <div class="metamask-icon">🦊</div>
            <p>Connect your MetaMask wallet to login</p>
          </div>
          
          <div v-if="error" class="error-message">{{ error }}</div>
          
          <button @click="handleMetaMaskLogin" class="btn btn-primary btn-block" :disabled="loading">
            {{ loading ? 'Connecting...' : 'Connect MetaMask' }}
          </button>
        </div>

        <!-- Telegram Login -->
        <div v-else-if="activeTab === 'telegram'" class="auth-form">
          <div class="telegram-info">
            <div class="telegram-icon">✈️</div>
            <p>Login with your Telegram account</p>
          </div>
          
          <div v-if="error" class="error-message">{{ error }}</div>
          
          <button @click="handleTelegramLogin" class="btn btn-primary btn-block" :disabled="loading">
            {{ loading ? 'Connecting...' : 'Login with Telegram' }}
          </button>
        </div>
      </div>

      <div class="modal-footer">
        <p>Don't have an account? <button @click="switchToRegister" class="link-btn">Sign up</button></p>
        <p>Or <button @click="handleGuestLogin" class="link-btn">play as guest</button></p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useAuthStore } from '../stores/auth';

const emit = defineEmits(['close']);

const authStore = useAuthStore();
const activeTab = ref('phone');
const loading = ref(false);
const error = ref(null);

const tabs = [
  { value: 'phone', label: 'Phone', icon: '📱' },
  { value: 'metamask', label: 'MetaMask', icon: '🦊' },
  { value: 'telegram', label: 'Telegram', icon: '✈️' },
];

const phoneForm = reactive({
  phone: '',
  password: '',
});

async function handlePhoneLogin() {
  loading.value = true;
  error.value = null;

  const result = await authStore.login(phoneForm, 'phone');
  
  loading.value = false;

  if (!result.success) {
    error.value = result.message;
  }
}

async function handleMetaMaskLogin() {
  if (typeof window.ethereum === 'undefined') {
    error.value = 'MetaMask is not installed. Please install MetaMask extension.';
    return;
  }

  loading.value = true;
  error.value = null;

  try {
    const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
    const address = accounts[0];
    
    const result = await authStore.login({ wallet_address: address }, 'metamask');
    
    if (!result.success) {
      error.value = result.message;
    }
  } catch (err) {
    error.value = err.message || 'Failed to connect MetaMask';
  } finally {
    loading.value = false;
  }
}

async function handleTelegramLogin() {
  error.value = 'Telegram login coming soon!';
}

async function handleGuestLogin() {
  loading.value = true;
  error.value = null;

  const result = await authStore.createGuestAccount();
  
  loading.value = false;

  if (result.success) {
    emit('close');
  } else {
    error.value = result.message;
  }
}

function switchToRegister() {
  authStore.showLoginModal = false;
  authStore.showRegisterModal = true;
}
</script>

<style scoped>
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
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
  border: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
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
  transition: all 0.2s;
}

.close-btn:hover {
  background: rgba(255, 255, 255, 0.2);
}

.modal-header {
  padding: 30px 30px 20px;
  text-align: center;
}

.modal-header h2 {
  font-size: 28px;
  margin-bottom: 8px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.modal-header p {
  color: rgba(255, 255, 255, 0.6);
}

.auth-tabs {
  display: flex;
  gap: 10px;
  padding: 0 30px 20px;
}

.tab-btn {
  flex: 1;
  padding: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.6);
  cursor: pointer;
  transition: all 0.2s;
  font-weight: 600;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
}

.tab-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.8);
}

.tab-btn.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-color: transparent;
  color: white;
}

.tab-icon {
  font-size: 20px;
}

.tab-content {
  padding: 0 30px 30px;
}

.auth-form {
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
  color: rgba(255, 255, 255, 0.9);
  font-size: 14px;
}

.form-group input {
  padding: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  color: white;
  font-size: 14px;
}

.form-group input:focus {
  outline: none;
  border-color: #667eea;
  background: rgba(255, 255, 255, 0.08);
}

.metamask-info, .telegram-info {
  text-align: center;
  padding: 40px 20px;
}

.metamask-icon, .telegram-icon {
  font-size: 64px;
  margin-bottom: 20px;
}

.metamask-info p, .telegram-info p {
  color: rgba(255, 255, 255, 0.8);
  font-size: 16px;
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
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-block {
  width: 100%;
}

.modal-footer {
  padding: 20px 30px 30px;
  text-align: center;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.modal-footer p {
  color: rgba(255, 255, 255, 0.6);
  margin-bottom: 10px;
}

.link-btn {
  background: none;
  border: none;
  color: #667eea;
  cursor: pointer;
  font-weight: 600;
  text-decoration: underline;
}

.link-btn:hover {
  color: #764ba2;
}
</style>
