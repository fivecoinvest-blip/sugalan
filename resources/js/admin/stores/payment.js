import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const usePaymentStore = defineStore('payment', () => {
  const deposits = ref([]);
  const withdrawals = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function fetchPendingDeposits() {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get('/api/admin/payments/deposits/pending');
      deposits.value = response.data.data || response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch deposits';
      console.error('Error fetching deposits:', err);
    } finally {
      loading.value = false;
    }
  }

  async function fetchPendingWithdrawals() {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get('/api/admin/payments/withdrawals/pending');
      withdrawals.value = response.data.data || response.data;
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch withdrawals';
      console.error('Error fetching withdrawals:', err);
    } finally {
      loading.value = false;
    }
  }

  async function approveDeposit(depositId, data) {
    try {
      await axios.post(`/api/admin/payments/deposits/${depositId}/approve`, data);
      // Remove from local state
      deposits.value = deposits.value.filter(d => d.id !== depositId);
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to approve deposit',
      };
    }
  }

  async function rejectDeposit(depositId, reason) {
    try {
      await axios.post(`/api/admin/payments/deposits/${depositId}/reject`, { reason });
      deposits.value = deposits.value.filter(d => d.id !== depositId);
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to reject deposit',
      };
    }
  }

  async function approveWithdrawal(withdrawalId, data) {
    try {
      await axios.post(`/api/admin/payments/withdrawals/${withdrawalId}/approve`, data);
      withdrawals.value = withdrawals.value.filter(w => w.id !== withdrawalId);
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to approve withdrawal',
      };
    }
  }

  async function rejectWithdrawal(withdrawalId, reason) {
    try {
      await axios.post(`/api/admin/payments/withdrawals/${withdrawalId}/reject`, { reason });
      withdrawals.value = withdrawals.value.filter(w => w.id !== withdrawalId);
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to reject withdrawal',
      };
    }
  }

  return {
    deposits,
    withdrawals,
    loading,
    error,
    fetchPendingDeposits,
    fetchPendingWithdrawals,
    approveDeposit,
    rejectDeposit,
    approveWithdrawal,
    rejectWithdrawal,
  };
});
