import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('admin_token') || null);
  const admin = ref(JSON.parse(localStorage.getItem('admin_user') || 'null'));

  const isAuthenticated = computed(() => !!token.value);

  // Configure axios defaults
  if (token.value) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
  }

  async function login(email, password) {
    try {
      const response = await axios.post('/api/admin/auth/login', {
        email,
        password,
      });

      token.value = response.data.access_token;
      admin.value = response.data.admin;

      localStorage.setItem('admin_token', token.value);
      localStorage.setItem('admin_user', JSON.stringify(admin.value));

      axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;

      return { success: true };
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Login failed',
      };
    }
  }

  async function logout() {
    try {
      await axios.post('/api/admin/auth/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      token.value = null;
      admin.value = null;
      localStorage.removeItem('admin_token');
      localStorage.removeItem('admin_user');
      delete axios.defaults.headers.common['Authorization'];
    }
  }

  async function fetchProfile() {
    try {
      const response = await axios.get('/api/admin/auth/profile');
      admin.value = response.data;
      localStorage.setItem('admin_user', JSON.stringify(admin.value));
    } catch (error) {
      console.error('Failed to fetch profile:', error);
    }
  }

  return {
    token,
    admin,
    isAuthenticated,
    login,
    logout,
    fetchProfile,
  };
});
