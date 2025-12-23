import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add Authorization header interceptor
const token = localStorage.getItem('token');
if (token) {
  window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

// Add request interceptor to always include token from localStorage
window.axios.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Add response interceptor to handle 401 errors
window.axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Token expired or invalid, clear auth and redirect to home
      localStorage.removeItem('token');
      delete window.axios.defaults.headers.common['Authorization'];
      
      // Only redirect if not already on auth pages
      if (!window.location.pathname.includes('/login') && !window.location.pathname.includes('/register')) {
        window.location.href = '/';
      }
    }
    return Promise.reject(error);
  }
);
