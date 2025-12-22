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
      stats.value = response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch statistics';
      console.error('Error fetching stats:', err);
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
