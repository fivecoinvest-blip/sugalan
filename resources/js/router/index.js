import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Layouts
import MainLayout from '../layouts/MainLayout.vue';
import GameLayout from '../layouts/GameLayout.vue';

// Pages
import Home from '../pages/Home.vue';
import Games from '../pages/Games.vue';
import Dashboard from '../pages/Dashboard.vue';
import Wallet from '../pages/Wallet.vue';
import Deposit from '../pages/Deposit.vue';
import Withdraw from '../pages/Withdraw.vue';
import Profile from '../pages/Profile.vue';
import BetHistory from '../pages/BetHistory.vue';
import Bonuses from '../pages/Bonuses.vue';
import Promotions from '../pages/Promotions.vue';
import Referrals from '../pages/Referrals.vue';
import VIP from '../pages/VIP.vue';
import Verify from '../pages/Verify.vue';

// Game Pages
import DiceGame from '../pages/games/Dice.vue';
import HiLoGame from '../pages/games/HiLo.vue';
import MinesGame from '../pages/games/Mines.vue';
import PlinkoGame from '../pages/games/Plinko.vue';
import KenoGame from '../pages/games/Keno.vue';
import WheelGame from '../pages/games/Wheel.vue';
import CrashGame from '../pages/games/Crash.vue';
import PumpGame from '../pages/games/Pump.vue';

const routes = [
  {
    path: '/',
    component: MainLayout,
    children: [
      {
        path: '',
        name: 'home',
        component: Home,
      },
      {
        path: 'games',
        name: 'games',
        component: Games,
      },
      {
        path: 'dashboard',
        name: 'dashboard',
        component: Dashboard,
        meta: { requiresAuth: true },
      },
      {
        path: 'wallet',
        name: 'wallet',
        component: Wallet,
        meta: { requiresAuth: true },
      },
      {
        path: 'deposit',
        name: 'deposit',
        component: Deposit,
        meta: { requiresAuth: true },
      },
      {
        path: 'withdraw',
        name: 'withdraw',
        component: Withdraw,
        meta: { requiresAuth: true },
      },
      {
        path: 'profile',
        name: 'profile',
        component: Profile,
        meta: { requiresAuth: true },
      },
      {
        path: 'history',
        name: 'bet-history',
        component: BetHistory,
        meta: { requiresAuth: true },
      },
      {
        path: 'bonuses',
        name: 'bonuses',
        component: Bonuses,
        meta: { requiresAuth: true },
      },
      {
        path: 'promotions',
        name: 'promotions',
        component: Promotions,
        meta: { requiresAuth: true },
      },
      {
        path: 'referrals',
        name: 'referrals',
        component: Referrals,
        meta: { requiresAuth: true },
      },
      {
        path: 'vip',
        name: 'vip',
        component: VIP,
        meta: { requiresAuth: true },
      },
      {
        path: 'verify',
        name: 'verify',
        component: Verify,
        // Public page - no auth required
      },
    ],
  },
  {
    path: '/play',
    component: GameLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dice',
        name: 'game-dice',
        component: DiceGame,
      },
      {
        path: 'hilo',
        name: 'game-hilo',
        component: HiLoGame,
      },
      {
        path: 'mines',
        name: 'game-mines',
        component: MinesGame,
      },
      {
        path: 'plinko',
        name: 'game-plinko',
        component: PlinkoGame,
      },
      {
        path: 'keno',
        name: 'game-keno',
        component: KenoGame,
      },
      {
        path: 'wheel',
        name: 'game-wheel',
        component: WheelGame,
      },
      {
        path: 'crash',
        name: 'game-crash',
        component: CrashGame,
      },
      {
        path: 'pump',
        name: 'game-pump',
        component: PumpGame,
      },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Navigation guard
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore();
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    // Show login modal instead of redirecting
    authStore.showLoginModal = true;
    next('/');
  } else {
    next();
  }
});

export default router;
