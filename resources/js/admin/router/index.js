import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Layouts
import AdminLayout from '../layouts/AdminLayout.vue';
import AuthLayout from '../layouts/AuthLayout.vue';

// Auth Pages
import Login from '../pages/auth/Login.vue';

// Dashboard Pages
import Dashboard from '../pages/Dashboard.vue';
import Users from '../pages/users/Index.vue';
import DepositQueue from '../pages/payments/DepositQueue.vue';
import WithdrawalQueue from '../pages/payments/WithdrawalQueue.vue';
import PaymentHistory from '../pages/payments/History.vue';
import BonusManagement from '../pages/bonuses/Index.vue';
import Campaigns from '../pages/promotions/Campaigns.vue';
import GameStats from '../pages/games/Stats.vue';
import Reports from '../pages/reports/Index.vue';

// Slot Management Pages
import SlotProviders from '../../pages/admin/slots/Providers.vue';
import SlotGames from '../../pages/admin/slots/Games.vue';
import SlotStatistics from '../../pages/admin/slots/Statistics.vue';

const routes = [
  {
    path: '/admin',
    redirect: '/admin/dashboard',
  },
  {
    path: '/admin/login',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'admin.login',
        component: Login,
        meta: { requiresGuest: true },
      },
    ],
  },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'admin.dashboard',
        component: Dashboard,
      },
      {
        path: 'users',
        name: 'admin.users',
        component: Users,
      },
      {
        path: 'payments/deposits',
        name: 'admin.payments.deposits',
        component: DepositQueue,
      },
      {
        path: 'payments/withdrawals',
        name: 'admin.payments.withdrawals',
        component: WithdrawalQueue,
      },
      {
        path: 'payments/history',
        name: 'admin.payments.history',
        component: PaymentHistory,
      },
      {
        path: 'bonuses',
        name: 'admin.bonuses',
        component: BonusManagement,
      },
      {
        path: 'promotions',
        name: 'admin.promotions',
        component: Campaigns,
      },
      {
        path: 'games',
        name: 'admin.games',
        component: GameStats,
      },
      {
        path: 'slots/providers',
        name: 'admin.slots.providers',
        component: SlotProviders,
      },
      {
        path: 'slots/games',
        name: 'admin.slots.games',
        component: SlotGames,
      },
      {
        path: 'slots/statistics',
        name: 'admin.slots.statistics',
        component: SlotStatistics,
      },
      {
        path: 'reports',
        name: 'admin.reports',
        component: Reports,
      },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Navigation Guards
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore();
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'admin.login' });
  } else if (to.meta.requiresGuest && authStore.isAuthenticated) {
    next({ name: 'admin.dashboard' });
  } else {
    next();
  }
});

export default router;
