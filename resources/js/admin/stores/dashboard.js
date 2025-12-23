import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useDashboardStore = defineStore('dashboard', () => {
  const stats = ref(null);
  const loading = ref(false);
  const error = ref(null);

  async function fetchStats() {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get('/api/admin/dashboard/stats');
      
      // Backend returns { success: true, data: { users, financial, pending, games } }
      if (response.data.success) {
        stats.value = response.data.data;
      } else {
        throw new Error(response.data.message || 'Failed to fetch statistics');
      }
    } catch (err) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch statistics';
      console.error('Error fetching stats:', err);
      
      // Set empty stats to prevent undefined errors
      stats.value = {
        users: {
          total_users: 0,
          active_users: 0,
          new_users_today: 0,
          vip_distribution: {}
        },
        financial: {
          total_deposits: 0,
          total_withdrawals: 0,
          net_revenue: 0,
          gross_gaming_revenue: 0,
          total_deposit_count: 0,
          total_withdrawal_count: 0
        },
        pending: {
          pending_deposits: 0,
          pending_withdrawals: 0
        },
        games: []
      };
    } finally {
      loading.value = false;
    }
  }

  return {
    stats,
    loading,
    error,
    fetchStats,
  };
});
