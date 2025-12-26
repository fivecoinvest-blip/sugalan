import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useWalletStore = defineStore('wallet', () => {
  const balance = ref({
    real_balance: 0,
    bonus_balance: 0,
    locked_balance: 0,
  });
  const transactions = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function fetchBalance() {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get('/api/wallet/balance');
      // API returns { success: true, data: { real_balance, bonus_balance, locked_balance } }
      balance.value = response.data.data || response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch balance';
      console.error('Error fetching balance:', err);
    } finally {
      loading.value = false;
    }
  }

  async function fetchTransactions(params = {}) {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get('/api/wallet/transactions', { params });
      transactions.value = response.data.data || response.data;
      return { success: true, data: response.data };
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch transactions';
      return { success: false, message: error.value };
    } finally {
      loading.value = false;
    }
  }

  function updateBalance(newBalance) {
    balance.value = { ...balance.value, ...newBalance };
  }

  return {
    balance,
    transactions,
    loading,
    error,
    fetchBalance,
    fetchTransactions,
    updateBalance,
  };
});
