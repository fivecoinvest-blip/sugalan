import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const token = ref(localStorage.getItem('token') || null);
  const loading = ref(false);
  const error = ref(null);
  const showLoginModal = ref(false);
  const showRegisterModal = ref(false);

  const isAuthenticated = computed(() => !!token.value && !!user.value);
  const isGuest = computed(() => user.value?.auth_method === 'guest');

  // Set auth token in axios headers
  if (token.value) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
  }

  async function login(credentials, method = 'phone') {
    loading.value = true;
    error.value = null;

    try {
      const endpoint = method === 'phone' ? '/api/auth/login' : `/api/auth/login/${method}`;
      const response = await axios.post(endpoint, credentials);
      
      // Backend returns { success: true, data: { access_token, user, ... } }
      token.value = response.data.data.access_token;
      user.value = response.data.data.user;
      
      localStorage.setItem('token', token.value);
      axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
      
      showLoginModal.value = false;
      
      return { success: true };
    } catch (err) {
      error.value = err.response?.data?.message || 'Login failed';
      return { success: false, message: error.value };
    } finally {
      loading.value = false;
    }
  }

  async function register(data, method = 'phone') {
    loading.value = true;
    error.value = null;

    try {
      const endpoint = method === 'phone' ? '/api/auth/register' : `/api/auth/register/${method}`;
      const response = await axios.post(endpoint, data);
      
      // Backend returns { success: true, data: { access_token, user, ... } }
      token.value = response.data.data.access_token;
      user.value = response.data.data.user;
      
      localStorage.setItem('token', token.value);
      axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
      
      showRegisterModal.value = false;
      return { success: true };
    } catch (err) {
      error.value = err.response?.data?.message || 'Registration failed';
      return { success: false, message: error.value };
    } finally {
      loading.value = false;
    }
  }

  async function createGuestAccount() {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post('/api/auth/guest');
      
      // Backend returns { success: true, data: { access_token, user, ... } }
      token.value = response.data.data.access_token;
      user.value = response.data.data.user;
      
      localStorage.setItem('token', token.value);
      axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
      
      return { success: true };
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create guest account';
      return { success: false, message: error.value };
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    try {
      await axios.post('/api/auth/logout');
    } catch (err) {
      console.error('Logout error:', err);
    } finally {
      token.value = null;
      user.value = null;
      localStorage.removeItem('token');
      delete axios.defaults.headers.common['Authorization'];
    }
  }

  async function checkAuth() {
    if (!token.value) return;

    try {
      const response = await axios.get('/api/auth/me');
      user.value = response.data.data;
    } catch (err) {
      // Token is invalid, clear auth
      token.value = null;
      user.value = null;
      localStorage.removeItem('token');
      delete axios.defaults.headers.common['Authorization'];
    }
  }

  async function upgradeGuest(data) {
    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post('/api/auth/guest/upgrade', data);
      user.value = response.data.user;
      return { success: true };
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to upgrade account';
      return { success: false, message: error.value };
    } finally {
      loading.value = false;
    }
  }

  return {
    user,
    token,
    loading,
    error,
    showLoginModal,
    showRegisterModal,
    isAuthenticated,
    isGuest,
    login,
    register,
    createGuestAccount,
    logout,
    checkAuth,
    upgradeGuest,
  };
});
