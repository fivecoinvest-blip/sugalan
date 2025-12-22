<template>
  <div class="admin-layout">
    <aside class="sidebar">
      <div class="sidebar-header">
        <h2>🎰 Sugalan</h2>
        <p>Admin Panel</p>
      </div>
      
      <nav class="sidebar-nav">
        <router-link to="/admin/dashboard" class="nav-item">
          <span class="icon">📊</span>
          <span>Dashboard</span>
        </router-link>
        
        <router-link to="/admin/users" class="nav-item">
          <span class="icon">👥</span>
          <span>Users</span>
        </router-link>
        
        <div class="nav-group">
          <div class="nav-group-title">Payments</div>
          <router-link to="/admin/payments/deposits" class="nav-item">
            <span class="icon">💰</span>
            <span>Deposits</span>
            <span v-if="pendingDeposits > 0" class="badge">{{ pendingDeposits }}</span>
          </router-link>
          <router-link to="/admin/payments/withdrawals" class="nav-item">
            <span class="icon">💸</span>
            <span>Withdrawals</span>
            <span v-if="pendingWithdrawals > 0" class="badge">{{ pendingWithdrawals }}</span>
          </router-link>
          <router-link to="/admin/payments/history" class="nav-item">
            <span class="icon">📜</span>
            <span>History</span>
          </router-link>
        </div>
        
        <router-link to="/admin/bonuses" class="nav-item">
          <span class="icon">🎁</span>
          <span>Bonuses</span>
        </router-link>
        
        <router-link to="/admin/promotions" class="nav-item">
          <span class="icon">🎉</span>
          <span>Promotions</span>
        </router-link>
        
        <router-link to="/admin/games" class="nav-item">
          <span class="icon">🎮</span>
          <span>Games</span>
        </router-link>
        
        <router-link to="/admin/reports" class="nav-item">
          <span class="icon">📈</span>
          <span>Reports</span>
        </router-link>
      </nav>
      
      <div class="sidebar-footer">
        <div class="admin-info">
          <div class="admin-name">{{ admin?.name || 'Admin' }}</div>
          <div class="admin-role">{{ admin?.role || 'Administrator' }}</div>
        </div>
        <button @click="handleLogout" class="logout-btn">
          <span class="icon">🚪</span>
          Logout
        </button>
      </div>
    </aside>
    
    <main class="main-content">
      <div class="topbar">
        <h1 class="page-title">{{ pageTitle }}</h1>
        <div class="topbar-actions">
          <button class="icon-btn" title="Notifications">
            🔔
            <span v-if="notifications > 0" class="notification-badge">{{ notifications }}</span>
          </button>
        </div>
      </div>
      
      <div class="content-area">
        <router-view />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const admin = computed(() => authStore.admin);
const pendingDeposits = ref(0);
const pendingWithdrawals = ref(0);
const notifications = ref(0);

const pageTitle = computed(() => {
  const titles = {
    'admin.dashboard': 'Dashboard',
    'admin.users': 'User Management',
    'admin.payments.deposits': 'Deposit Queue',
    'admin.payments.withdrawals': 'Withdrawal Queue',
    'admin.payments.history': 'Payment History',
    'admin.bonuses': 'Bonus Management',
    'admin.games': 'Game Statistics',
    'admin.reports': 'Reports & Analytics',
  };
  return titles[route.name] || 'Admin Panel';
});

async function handleLogout() {
  if (confirm('Are you sure you want to logout?')) {
    await authStore.logout();
    router.push({ name: 'admin.login' });
  }
}

onMounted(() => {
  // Fetch pending counts
  // TODO: Implement API calls
});
</script>

<style scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
  background-color: #f5f5f5;
}

.sidebar {
  width: 260px;
  background: #1a1d29;
  color: white;
  display: flex;
  flex-direction: column;
  position: fixed;
  height: 100vh;
  overflow-y: auto;
}

.sidebar-header {
  padding: 30px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header h2 {
  font-size: 24px;
  margin-bottom: 5px;
}

.sidebar-header p {
  font-size: 12px;
  color: #a0aec0;
}

.sidebar-nav {
  flex: 1;
  padding: 20px 0;
}

.nav-group {
  margin: 20px 0;
}

.nav-group-title {
  padding: 8px 20px;
  font-size: 11px;
  font-weight: 600;
  color: #a0aec0;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.nav-item {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: #cbd5e0;
  text-decoration: none;
  transition: all 0.2s;
  position: relative;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.05);
  color: white;
}

.nav-item.router-link-active {
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.nav-item .icon {
  font-size: 18px;
  margin-right: 12px;
  width: 24px;
  text-align: center;
}

.nav-item .badge {
  margin-left: auto;
  background: #e53e3e;
  color: white;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 10px;
  font-weight: 600;
}

.sidebar-footer {
  padding: 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.admin-info {
  margin-bottom: 15px;
}

.admin-name {
  font-weight: 600;
  margin-bottom: 3px;
}

.admin-role {
  font-size: 12px;
  color: #a0aec0;
}

.logout-btn {
  width: 100%;
  padding: 10px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 0.2s;
}

.logout-btn:hover {
  background: rgba(255, 255, 255, 0.15);
}

.main-content {
  flex: 1;
  margin-left: 260px;
  display: flex;
  flex-direction: column;
}

.topbar {
  background: white;
  padding: 20px 30px;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 10;
}

.page-title {
  font-size: 24px;
  font-weight: 600;
  color: #2d3748;
}

.topbar-actions {
  display: flex;
  gap: 10px;
}

.icon-btn {
  width: 40px;
  height: 40px;
  border: none;
  background: #f7fafc;
  border-radius: 8px;
  cursor: pointer;
  font-size: 18px;
  position: relative;
  transition: background 0.2s;
}

.icon-btn:hover {
  background: #edf2f7;
}

.notification-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background: #e53e3e;
  color: white;
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 10px;
  font-weight: 600;
}

.content-area {
  flex: 1;
  padding: 30px;
  overflow-y: auto;
}
</style>
