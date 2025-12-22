<template>
  <div class="dashboard">
    <div v-if="loading" class="loading">
      Loading statistics...
    </div>
    
    <div v-else-if="error" class="error">
      {{ error }}
    </div>
    
    <div v-else class="dashboard-content">
      <!-- Overview Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon" style="background: #667eea;">👥</div>
          <div class="stat-content">
            <div class="stat-label">Total Users</div>
            <div class="stat-value">{{ stats?.users.total_users?.toLocaleString() || 0 }}</div>
            <div class="stat-subtext">
              {{ stats?.users.active_users || 0 }} active today
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: #48bb78;">💰</div>
          <div class="stat-content">
            <div class="stat-label">Total Deposits</div>
            <div class="stat-value">₱{{ formatMoney(stats?.financial.total_deposits) }}</div>
            <div class="stat-subtext">
              {{ stats?.financial.total_deposit_count || 0 }} transactions
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: #ed8936;">💸</div>
          <div class="stat-content">
            <div class="stat-label">Total Withdrawals</div>
            <div class="stat-value">₱{{ formatMoney(stats?.financial.total_withdrawals) }}</div>
            <div class="stat-subtext">
              {{ stats?.financial.total_withdrawal_count || 0 }} transactions
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-icon" style="background: #9f7aea;">📈</div>
          <div class="stat-content">
            <div class="stat-label">Net Revenue</div>
            <div class="stat-value">₱{{ formatMoney(stats?.financial.net_revenue) }}</div>
            <div class="stat-subtext">
              GGR: ₱{{ formatMoney(stats?.financial.gross_gaming_revenue) }}
            </div>
          </div>
        </div>
      </div>
      
      <!-- Pending Actions -->
      <div class="section">
        <h2 class="section-title">⚠️ Pending Actions</h2>
        <div class="pending-grid">
          <router-link 
            to="/admin/payments/deposits" 
            class="pending-card"
            :class="{ 'has-items': stats?.pending.pending_deposits > 0 }"
          >
            <div class="pending-count">{{ stats?.pending.pending_deposits || 0 }}</div>
            <div class="pending-label">Pending Deposits</div>
          </router-link>
          
          <router-link 
            to="/admin/payments/withdrawals" 
            class="pending-card"
            :class="{ 'has-items': stats?.pending.pending_withdrawals > 0 }"
          >
            <div class="pending-count">{{ stats?.pending.pending_withdrawals || 0 }}</div>
            <div class="pending-label">Pending Withdrawals</div>
          </router-link>
        </div>
      </div>
      
      <!-- VIP Distribution -->
      <div class="section">
        <h2 class="section-title">👑 VIP Distribution</h2>
        <div class="vip-grid">
          <div 
            v-for="(count, tier) in stats?.users.vip_distribution" 
            :key="tier"
            class="vip-card"
          >
            <div class="vip-tier">{{ tier }}</div>
            <div class="vip-count">{{ count }} users</div>
            <div class="vip-percentage">
              {{ calculatePercentage(count, stats?.users.total_users) }}%
            </div>
          </div>
        </div>
      </div>
      
      <!-- Game Performance -->
      <div class="section">
        <h2 class="section-title">🎮 Game Performance</h2>
        <div class="games-table">
          <table>
            <thead>
              <tr>
                <th>Game</th>
                <th>Total Bets</th>
                <th>Total Wagered</th>
                <th>Total Won</th>
                <th>House Edge</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="game in stats?.games" :key="game.game">
                <td class="game-name">{{ game.game }}</td>
                <td>{{ game.total_bets?.toLocaleString() }}</td>
                <td>₱{{ formatMoney(game.total_wagered) }}</td>
                <td>₱{{ formatMoney(game.total_won) }}</td>
                <td>
                  <span class="house-edge">{{ game.house_edge }}%</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useDashboardStore } from '../stores/dashboard';
import { storeToRefs } from 'pinia';

const dashboardStore = useDashboardStore();
const { stats, loading, error } = storeToRefs(dashboardStore);

onMounted(() => {
  dashboardStore.fetchStats();
});

function formatMoney(value) {
  if (!value) return '0.00';
  return Number(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function calculatePercentage(value, total) {
  if (!total) return 0;
  return ((value / total) * 100).toFixed(1);
}
</script>

<style scoped>
.dashboard {
  max-width: 1400px;
}

.loading, .error {
  text-align: center;
  padding: 60px 20px;
  font-size: 18px;
  color: #718096;
}

.error {
  color: #e53e3e;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 24px;
  display: flex;
  gap: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  flex-shrink: 0;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 13px;
  color: #718096;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 4px;
}

.stat-subtext {
  font-size: 12px;
  color: #a0aec0;
}

.section {
  margin-bottom: 40px;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 20px;
}

.pending-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.pending-card {
  background: white;
  border-radius: 12px;
  padding: 30px;
  text-align: center;
  text-decoration: none;
  border: 2px solid #e2e8f0;
  transition: all 0.2s;
}

.pending-card:hover {
  border-color: #667eea;
  transform: translateY(-2px);
}

.pending-card.has-items {
  border-color: #f56565;
  background: #fff5f5;
}

.pending-count {
  font-size: 36px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 8px;
}

.pending-card.has-items .pending-count {
  color: #e53e3e;
}

.pending-label {
  font-size: 14px;
  color: #718096;
}

.vip-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 15px;
}

.vip-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  text-align: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.vip-tier {
  font-weight: 700;
  font-size: 16px;
  color: #2d3748;
  margin-bottom: 8px;
}

.vip-count {
  font-size: 24px;
  font-weight: 700;
  color: #667eea;
  margin-bottom: 4px;
}

.vip-percentage {
  font-size: 12px;
  color: #a0aec0;
}

.games-table {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: #f7fafc;
}

th {
  padding: 16px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: #4a5568;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

td {
  padding: 16px;
  border-top: 1px solid #e2e8f0;
  color: #2d3748;
}

.game-name {
  font-weight: 600;
  text-transform: capitalize;
}

.house-edge {
  display: inline-block;
  padding: 4px 12px;
  background: #c6f6d5;
  color: #22543d;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
}
</style>
