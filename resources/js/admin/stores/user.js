import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useUserStore = defineStore('user', () => {
  const users = ref([]);
  const selectedUser = ref(null);
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
  });
  const loading = ref(false);
  const error = ref(null);

  async function fetchUsers(filters = {}) {
    loading.value = true;
    error.value = null;

    try {
      const params = {
        page: filters.page || 1,
        per_page: filters.per_page || 20,
        search: filters.search || null,
        vip_level: filters.vip_level || null,
        status: filters.status || null,
        sort_by: filters.sort_by || 'created_at',
        sort_order: filters.sort_order || 'desc',
      };

      const response = await axios.get('/api/admin/users', { params });
      users.value = response.data.data;
      pagination.value = {
        current_page: response.data.current_page,
        last_page: response.data.last_page,
        per_page: response.data.per_page,
        total: response.data.total,
      };
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch users';
      console.error('Error fetching users:', err);
    } finally {
      loading.value = false;
    }
  }

  async function fetchUserDetails(userId) {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.get(`/api/admin/users/${userId}`);
      selectedUser.value = response.data;
      return { success: true, user: response.data };
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch user details';
      return { success: false, message: error.value };
    } finally {
      loading.value = false;
    }
  }

  async function updateUserStatus(userId, status) {
    try {
      await axios.patch(`/api/admin/users/${userId}/status`, { status });
      // Update local state
      const user = users.value.find(u => u.id === userId);
      if (user) user.status = status;
      if (selectedUser.value?.id === userId) {
        selectedUser.value.status = status;
      }
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to update status',
      };
    }
  }

  async function updateVipLevel(userId, vipLevelId) {
    try {
      await axios.patch(`/api/admin/users/${userId}/vip`, { vip_level_id: vipLevelId });
      // Update local state
      const user = users.value.find(u => u.id === userId);
      if (user) user.vip_level_id = vipLevelId;
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to update VIP level',
      };
    }
  }

  async function adjustBalance(userId, data) {
    try {
      await axios.post(`/api/admin/users/${userId}/balance/adjust`, data);
      return { success: true };
    } catch (err) {
      return {
        success: false,
        message: err.response?.data?.message || 'Failed to adjust balance',
      };
    }
  }

  return {
    users,
    selectedUser,
    pagination,
    loading,
    error,
    fetchUsers,
    fetchUserDetails,
    updateUserStatus,
    updateVipLevel,
    adjustBalance,
  };
});
