import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useBonusStore = defineStore('bonus', () => {
  const bonuses = ref([]);
  const userBonuses = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function fetchActiveBonuses() {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get('/api/admin/bonuses/active');
      bonuses.value = response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch bonuses';
      console.error('Error fetching bonuses:', err);
    } finally {
      loading.value = false;
    }
  }

  async function fetchUserBonuses(userId) {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`/api/admin/users/${userId}/bonuses`);
      userBonuses.value = response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch user bonuses';
      console.error('Error fetching user bonuses:', err);
    } finally {
      loading.value = false;
    }
  }

  async function awardBonus(data) {
    try {
      await axios.post('/api/admin/bonuses/award', data);
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to award bonus',
      };
    }
  }

  async function cancelBonus(bonusId) {
    try {
      await axios.post(`/api/admin/bonuses/${bonusId}/cancel`);
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to cancel bonus',
      };
    }
  }

  return {
    bonuses,
    userBonuses,
    loading,
    error,
    fetchActiveBonuses,
    fetchUserBonuses,
    awardBonus,
    cancelBonus,
  };
});
