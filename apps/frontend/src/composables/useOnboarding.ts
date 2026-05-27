import { ref, computed, watch } from 'vue';
import { useAuthStore } from '../store/useAuthStore';
import { useRouter, useRoute } from 'vue-router';
import api from '../services/api';

export const ONBOARDING_TASKS = {
  PROFILE_UPDATE: 'profile_update',
  FIRST_BOOKING: 'first_booking',
  EXPLORE_MEETUPS: 'explore_meetups',
  PERSONAL_BESTS: 'personal_bests',
  LEADERBOARD: 'leaderboard',
} as const;

export type OnboardingTaskId = typeof ONBOARDING_TASKS[keyof typeof ONBOARDING_TASKS];

export const TASK_METADATA: Record<OnboardingTaskId, { title: string; description: string; routeName: string; icon: string }> = {
  [ONBOARDING_TASKS.PROFILE_UPDATE]: {
    title: 'Complete Profile',
    description: 'Update your name, gender, and emergency contact info to stay safe.',
    routeName: 'profile',
    icon: 'pi pi-user',
  },
  [ONBOARDING_TASKS.FIRST_BOOKING]: {
    title: 'Book Your First Course',
    description: 'Find a session in the calendar and reserve your spot with a single click.',
    routeName: 'home',
    icon: 'pi pi-calendar-plus',
  },
  [ONBOARDING_TASKS.EXPLORE_MEETUPS]: {
    title: 'Explore Community Meetups',
    description: 'Connect with other athletes by joining or creating social meetups.',
    routeName: 'meetups',
    icon: 'pi pi-users',
  },
  [ONBOARDING_TASKS.PERSONAL_BESTS]: {
    title: 'Log Personal Bests',
    description: 'Track your strength and performance progress by logging your max weights.',
    routeName: 'personal-bests',
    icon: 'pi pi-chart-line',
  },
  [ONBOARDING_TASKS.LEADERBOARD]: {
    title: 'Check the Rankings',
    description: 'See how you stack up against others on the leaderboard (and make your profile public!).',
    routeName: 'leaderboard',
    icon: 'pi pi-trophy',
  },
};

export function useOnboarding() {
  const authStore = useAuthStore();
  const router = useRouter();
  const route = useRoute();

  const onboardingState = computed(() => authStore.user?.onboardingState || []);
  const isSkipped = computed(() => onboardingState.value.includes('skipped'));

  const filteredMetadata = computed(() => {
    const metadata = { ...TASK_METADATA };
    if (authStore.isTrial) {
      delete metadata[ONBOARDING_TASKS.LEADERBOARD];
    }
    return metadata;
  });

  const isComplete = computed(() => {
    if (isSkipped.value) return true;
    const requiredTasks = Object.keys(filteredMetadata.value);
    return requiredTasks.every(task => onboardingState.value.includes(task));
  });

  const completionPercentage = computed(() => {
    if (isSkipped.value) return 100;
    const requiredTasks = Object.keys(filteredMetadata.value);
    const completedCount = requiredTasks.filter(task => onboardingState.value.includes(task)).length;
    return Math.round((completedCount / requiredTasks.length) * 100);
  });

  const totalTasksCount = computed(() => Object.keys(filteredMetadata.value).length);
  const completedTasksCount = computed(() => {
    const requiredTasks = Object.keys(filteredMetadata.value);
    return requiredTasks.filter(task => onboardingState.value.includes(task)).length;
  });

  const currentContextualTask = computed(() => {
    if (isSkipped.value) return null;
    
    // Find a task that matches the current route name AND is not yet complete
    const entry = Object.entries(filteredMetadata.value).find(([taskId, meta]) => {
      return meta.routeName === route.name && !onboardingState.value.includes(taskId);
    });

    return entry ? { id: entry[0] as OnboardingTaskId, ...entry[1] } : null;
  });

  const nextPendingTask = computed(() => {
    if (isSkipped.value) return null;
    const entry = Object.entries(filteredMetadata.value).find(([taskId, _]) => {
      return !onboardingState.value.includes(taskId);
    });
    return entry ? { id: entry[0] as OnboardingTaskId, ...entry[1] } : null;
  });

  async function markTaskComplete(taskId: string) {
    if (onboardingState.value.includes(taskId) || isSkipped.value) return;

    try {
      const response = await api.patch('/user/me/onboarding', { taskId });
      if (authStore.user) {
        authStore.user.onboardingState = response.data.onboardingState;
      }
    } catch (error) {
      console.error('Failed to update onboarding state', error);
    }
  }

  async function skipOnboarding() {
    try {
      const response = await api.patch('/user/me/onboarding', { skip: true });
      if (authStore.user) {
        authStore.user.onboardingState = response.data.onboardingState;
      }
    } catch (error) {
      console.error('Failed to skip onboarding', error);
    }
  }

  // Tracking logic for route-based tasks
  function initRouteTracking() {
    watch(
      () => route.name,
      (newName) => {
        if (newName === 'meetups') {
          markTaskComplete(ONBOARDING_TASKS.EXPLORE_MEETUPS);
        } else if (newName === 'personal-bests') {
          markTaskComplete(ONBOARDING_TASKS.PERSONAL_BESTS);
        } else if (newName === 'leaderboard') {
          markTaskComplete(ONBOARDING_TASKS.LEADERBOARD);
        }
      },
      { immediate: true }
    );
  }

  return {
    onboardingState,
    isSkipped,
    isComplete,
    completionPercentage,
    totalTasksCount,
    completedTasksCount,
    currentContextualTask,
    nextPendingTask,
    markTaskComplete,
    skipOnboarding,
    initRouteTracking,
    filteredMetadata,
  };
}
