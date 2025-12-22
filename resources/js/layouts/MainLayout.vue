<template>
  <div class="main-layout">
    <!-- Header -->
    <header class="header">
      <div class="container">
        <div class="header-content">
          <!-- Logo -->
          <router-link to="/" class="logo">
            <span class="logo-icon">🎰</span>
            <span class="logo-text">Sugalan</span>
          </router-link>

          <!-- Navigation -->
          <nav class="nav">
            <router-link to="/" class="nav-link">Home</router-link>
            <router-link to="/games" class="nav-link">Games</router-link>
            <router-link v-if="isAuthenticated" to="/dashboard" class="nav-link">Dashboard</router-link>
            <router-link v-if="isAuthenticated" to="/vip" class="nav-link">
              VIP <span class="vip-badge">{{ user?.vip_level?.name || 'Bronze' }}</span>
            </router-link>
          </nav>

          <!-- User Actions -->
          <div class="header-actions">
            <template v-if="isAuthenticated">
              <!-- Wallet Balance -->
              <div class="wallet-display">
                <div class="balance-item">
                  <span class="balance-label">Real:</span>
                  <span class="balance-value">₱{{ formatMoney(walletBalance.real_balance) }}</span>
                </div>
                <div class="balance-item bonus">
                  <span class="balance-label">Bonus:</span>
                  <span class="balance-value">₱{{ formatMoney(walletBalance.bonus_balance) }}</span>
                </div>
              </div>

              <!-- Deposit Button -->
              <router-link to="/deposit" class="btn btn-primary">
                💰 Deposit
              </router-link>

              <!-- User Menu -->
              <div class="user-menu" @click="toggleUserMenu">
                <div class="user-avatar">
                  {{ user?.username?.[0]?.toUpperCase() || 'U' }}
                </div>
                <span class="user-name">{{ user?.username }}</span>
                
                <div v-if="showUserMenu" class="dropdown-menu">
                  <router-link to="/profile" class="dropdown-item">👤 Profile</router-link>
                  <router-link to="/wallet" class="dropdown-item">💳 Wallet</router-link>
                  <router-link to="/bonuses" class="dropdown-item">🎁 Bonuses</router-link>
                  <router-link to="/promotions" class="dropdown-item">🎉 Promotions</router-link>
                  <router-link to="/referrals" class="dropdown-item">👥 Referrals</router-link>
                  <router-link to="/history" class="dropdown-item">📊 Bet History</router-link>
                  <div class="dropdown-divider"></div>
                  <button @click="handleLogout" class="dropdown-item logout">🚪 Logout</button>
                </div>
              </div>
            </template>

            <template v-else>
              <button @click="authStore.showLoginModal = true" class="btn btn-secondary">
                Login
              </button>
              <button @click="authStore.showRegisterModal = true" class="btn btn-primary">
                Sign Up
              </button>
              <button @click="handleGuestLogin" class="btn btn-ghost">
                👤 Play as Guest
              </button>
            </template>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
      <router-view />
    </main>

    <!-- Footer -->
    <footer class="footer">
      <div class="container">
        <div class="footer-content">
          <div class="footer-section">
            <h3>Sugalan Casino</h3>
            <p>Provably fair online casino with VIP rewards</p>
          </div>
          <div class="footer-section">
            <h4>Quick Links</h4>
            <a href="#">About Us</a>
            <a href="#">Terms of Service</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Responsible Gaming</a>
          </div>
          <div class="footer-section">
            <h4>Games</h4>
            <router-link to="/play/dice">Dice</router-link>
            <router-link to="/play/crash">Crash</router-link>
            <router-link to="/play/mines">Mines</router-link>
            <router-link to="/play/plinko">Plinko</router-link>
          </div>
          <div class="footer-section">
            <h4>Support</h4>
            <a href="#">Help Center</a>
            <a href="#">Contact Us</a>
            <a href="#">FAQ</a>
          </div>
        </div>
        <div class="footer-bottom">
          <p>&copy; 2025 Sugalan Casino. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <!-- Login Modal -->
    <LoginModal v-if="authStore.showLoginModal" @close="authStore.showLoginModal = false" />
    
    <!-- Register Modal -->
    <RegisterModal v-if="authStore.showRegisterModal" @close="authStore.showRegisterModal = false" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useWalletStore } from '../stores/wallet';
import LoginModal from '../components/LoginModal.vue';
import RegisterModal from '../components/RegisterModal.vue';

const authStore = useAuthStore();
const walletStore = useWalletStore();

const showUserMenu = ref(false);

const isAuthenticated = computed(() => authStore.isAuthenticated);
const user = computed(() => authStore.user);
const walletBalance = computed(() => walletStore.balance);

onMounted(() => {
  if (isAuthenticated.value) {
    walletStore.fetchBalance();
  }
});

watch(isAuthenticated, (newVal) => {
  if (newVal) {
    walletStore.fetchBalance();
  }
});

function toggleUserMenu() {
  showUserMenu.value = !showUserMenu.value;
}

async function handleLogout() {
  await authStore.logout();
  showUserMenu.value = false;
}

async function handleGuestLogin() {
  const result = await authStore.createGuestAccount();
  if (result.success) {
    alert('Welcome! You can play as guest. Create an account to keep your winnings!');
  }
}

function formatMoney(value) {
  if (!value) return '0.00';
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

// Close menu when clicking outside
if (typeof window !== 'undefined') {
  window.addEventListener('click', (e) => {
    if (!e.target.closest('.user-menu')) {
      showUserMenu.value = false;
    }
  });
}
</script>

<style scoped>
.main-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  color: white;
}

.header {
  background: rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  position: sticky;
  top: 0;
  z-index: 100;
}

.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0 20px;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 15px 0;
  gap: 30px;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  color: white;
  font-size: 24px;
  font-weight: 700;
}

.logo-icon {
  font-size: 32px;
}

.logo-text {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.nav {
  display: flex;
  gap: 20px;
  flex: 1;
  justify-content: center;
}

.nav-link {
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  padding: 8px 16px;
  border-radius: 8px;
  transition: all 0.2s;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
}

.nav-link:hover, .nav-link.router-link-active {
  color: white;
  background: rgba(255, 255, 255, 0.1);
}

.vip-badge {
  background: linear-gradient(135deg, #ffd700, #ffed4e);
  color: #7c2d12;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 700;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 15px;
}

.wallet-display {
  display: flex;
  gap: 15px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.balance-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.balance-label {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
}

.balance-value {
  font-weight: 700;
  color: #48bb78;
  font-size: 14px;
}

.balance-item.bonus .balance-value {
  color: #f6ad55;
}

.btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
  text-decoration: none;
  display: inline-block;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-secondary:hover {
  background: rgba(255, 255, 255, 0.15);
}

.btn-ghost {
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-ghost:hover {
  color: white;
  background: rgba(255, 255, 255, 0.05);
}

.user-menu {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  cursor: pointer;
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.2s;
}

.user-menu:hover {
  background: rgba(255, 255, 255, 0.1);
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.user-name {
  font-weight: 600;
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  background: rgba(30, 30, 50, 0.98);
  backdrop-filter: blur(10px);
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  min-width: 200px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
  overflow: hidden;
}

.dropdown-item {
  display: block;
  padding: 12px 20px;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.2s;
  cursor: pointer;
  background: none;
  border: none;
  width: 100%;
  text-align: left;
  font-size: 14px;
}

.dropdown-item:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.dropdown-item.logout {
  color: #fc8181;
}

.dropdown-divider {
  height: 1px;
  background: rgba(255, 255, 255, 0.1);
  margin: 8px 0;
}

.main-content {
  flex: 1;
  padding: 40px 0;
}

.footer {
  background: rgba(0, 0, 0, 0.3);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding: 40px 0 20px;
  margin-top: 60px;
}

.footer-content {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 40px;
  margin-bottom: 30px;
}

.footer-section h3 {
  margin-bottom: 15px;
  font-size: 20px;
}

.footer-section h4 {
  margin-bottom: 15px;
  font-size: 16px;
  color: rgba(255, 255, 255, 0.9);
}

.footer-section p {
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.6;
}

.footer-section a {
  display: block;
  color: rgba(255, 255, 255, 0.6);
  text-decoration: none;
  margin-bottom: 10px;
  transition: color 0.2s;
}

.footer-section a:hover {
  color: white;
}

.footer-bottom {
  text-align: center;
  padding-top: 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.6);
}

@media (max-width: 768px) {
  .header-content {
    flex-wrap: wrap;
  }
  
  .nav {
    order: 3;
    width: 100%;
    justify-content: flex-start;
    overflow-x: auto;
  }
  
  .wallet-display {
    display: none;
  }
}
</style>
