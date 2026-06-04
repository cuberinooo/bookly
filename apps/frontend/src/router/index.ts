import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import { useAuthStore } from '../store/useAuthStore';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition;
    } else {
      return { top: 0 };
    }
  },
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: { requiresAuth: true }
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('../views/RegisterView.vue'),
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('../views/VerifyEmailView.vue'),
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('../views/ForgotPasswordView.vue'),
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('../views/ResetPasswordView.vue'),
    },
    {
      path: '/terms',
      name: 'terms',
      component: () => import('../views/TermsView.vue'),
    },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: () => import('../views/DashboardView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('../views/ProfileView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('../views/SettingsView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/users',
      name: 'users',
      component: () => import('../views/UsersView.vue'),
      meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
    },
    {
      path: '/payments',
      name: 'payments',
      component: () => import('../views/PaymentsView.vue'),
      meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
    },
    {
      path: '/meetups',
      name: 'meetups',
      component: () => import('../views/MeetupsView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/statistics',
      name: 'statistics',
      component: () => import('../views/StatisticsView.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/leaderboard',
      name: 'leaderboard',
      component: () => import('../views/LeaderboardView.vue'),
      meta: { requiresAuth: true, roles: ['ROLE_MEMBER', 'ROLE_TRAINER', 'ROLE_ADMIN'] }
    },
    {
      path: '/personal-bests',
      name: 'personal-bests',
      component: () => import('../views/PersonalBestsView.vue'),
      meta: { requiresAuth: true }
    },
  ],
});

router.beforeEach(async (to) => {
  const authStore = useAuthStore();
  
  // 1. Wait for authStore to be initialized
  if (!authStore.initialized) {
    await authStore.init();
  }

  const loggedIn = authStore.isLoggedIn;

  // 2. Handle Authentication logic via return values
  if (to.meta.requiresAuth && !loggedIn) {
    return { name: 'login' };
  }

  // 3. Handle Role-based Authorization logic
  if (to.meta.roles && Array.isArray(to.meta.roles)) {
    const userRoles = authStore.user?.roles || [];
    const hasRequiredRole = to.meta.roles.some(role => userRoles.includes(role));
    
    if (!hasRequiredRole) {
      return { name: 'home' }; // Redirect to home if user lacks required role
    }
  }

  // 4. Redirect logged-in users away from auth pages
  if ((to.name === 'login' || to.name === 'register') && loggedIn) {
    return { name: 'home' };
  }

  // 4. If no conditions are met, navigation proceeds automatically.
  // Returning true (or nothing) allows the transition.
  return true;
});

export default router;
