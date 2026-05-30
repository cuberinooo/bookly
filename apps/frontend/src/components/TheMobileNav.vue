<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { useAuthStore } from '../store/useAuthStore';
import { useMeetupStore } from '../store/useMeetupStore';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import OverlayBadge from 'primevue/overlaybadge';

const authStore = useAuthStore();
const meetupStore = useMeetupStore();
const { t } = useI18n();

const dashboardLabel = computed(() => {
  return authStore.isTrainer && authStore.viewMode === 'trainer' ? t('app.dashboard') : t('app.myBookings');
});
</script>

<template>
  <nav
    v-if="authStore.isLoggedIn"
    class="mobile-nav"
  >
    <RouterLink
      to="/"
      class="mobile-nav-item"
    >
      <i class="pi pi-calendar" />
      <span>{{ t('app.courses') }}</span>
    </RouterLink>
    
    <RouterLink
      to="/dashboard"
      class="mobile-nav-item"
    >
      <i class="pi pi-th-large" />
      <span>{{ dashboardLabel }}</span>
    </RouterLink>
    
    <RouterLink
      to="/meetups"
      class="mobile-nav-item"
    >
      <OverlayBadge
        v-if="meetupStore.globalUnread > 0"
        :value="meetupStore.globalUnread"
        severity="danger"
        size="small"
      >
        <i class="pi pi-users" />
      </OverlayBadge>
      <i
        v-else
        class="pi pi-users"
      />
      <span>{{ t('app.meetups') }}</span>
    </RouterLink>
    
    <RouterLink
      to="/leaderboard"
      class="mobile-nav-item"
    >
      <i class="pi pi-trophy" />
      <span>{{ t('app.rankings') }}</span>
    </RouterLink>
  </nav>
</template>

<style scoped lang="scss">
.mobile-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 64px;
  background: var(--bg-primary-color);
  display: flex;
  justify-content: space-around;
  align-items: center;
  border-top: 2px solid var(--primary-color);
  box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.3);
  z-index: 1000;
  padding: 0 1rem;

  @media (min-width: 769px) {
    display: none;
  }
}

.mobile-nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #f8fafc;
  text-decoration: none;
  font-family: 'Barlow Condensed', sans-serif;
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.7rem;
  letter-spacing: 0.05em;
  transition: all 0.2s;
  flex: 1;
  gap: 0.25rem;

  i {
    font-size: 1.25rem;
  }

  &:hover {
    color: var(--primary-color);
  }

  &.router-link-active {
    color: var(--primary-color);
    
    i {
      transform: scale(1.1);
    }
  }
}
</style>
