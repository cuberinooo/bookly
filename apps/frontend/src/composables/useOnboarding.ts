import { ref, computed, watch } from 'vue';
import { useAuthStore } from '../store/useAuthStore';
import { useCourseStore } from '../store/useCourseStore';
import { useRouter, useRoute } from 'vue-router';
import api from '../services/api';

export const ONBOARDING_TASKS = {
  // Athlete tasks
  PROFILE_UPDATE: 'profile_update',
  FIRST_BOOKING: 'first_booking',
  EXPLORE_MEETUPS: 'explore_meetups',
  PERSONAL_BESTS: 'personal_bests',
  LEADERBOARD: 'leaderboard',
  // Admin tasks
  ADMIN_SETTINGS: 'admin_settings',
  ADMIN_PAYMENTS: 'admin_payments',
  ADMIN_USERS: 'admin_users',
  ADMIN_COURSES: 'admin_courses'
} as const;

export type OnboardingTaskId = typeof ONBOARDING_TASKS[keyof typeof ONBOARDING_TASKS];

export const TASK_METADATA: Record<OnboardingTaskId, { titleKey: string; descriptionKey: string; routeName: string; icon: string }> = {
  [ONBOARDING_TASKS.PROFILE_UPDATE]: {
    titleKey: 'onboarding.steps.profileUpdate.title',
    descriptionKey: 'onboarding.steps.profileUpdate.description',
    routeName: 'profile',
    icon: 'pi pi-user',
  },
  [ONBOARDING_TASKS.FIRST_BOOKING]: {
    titleKey: 'onboarding.steps.firstBooking.title',
    descriptionKey: 'onboarding.steps.firstBooking.description',
    routeName: 'home',
    icon: 'pi pi-calendar-plus',
  },
  [ONBOARDING_TASKS.EXPLORE_MEETUPS]: {
    titleKey: 'onboarding.steps.exploreMeetups.title',
    descriptionKey: 'onboarding.steps.exploreMeetups.description',
    routeName: 'meetups',
    icon: 'pi pi-users',
  },
  [ONBOARDING_TASKS.PERSONAL_BESTS]: {
    titleKey: 'onboarding.steps.personalBests.title',
    descriptionKey: 'onboarding.steps.personalBests.description',
    routeName: 'personal-bests',
    icon: 'pi pi-chart-line',
  },
  [ONBOARDING_TASKS.LEADERBOARD]: {
    titleKey: 'onboarding.steps.leaderboard.title',
    descriptionKey: 'onboarding.steps.leaderboard.description',
    routeName: 'leaderboard',
    icon: 'pi pi-trophy',
  },
  [ONBOARDING_TASKS.ADMIN_SETTINGS]: {
    titleKey: 'onboarding.steps.adminSettings.title',
    descriptionKey: 'onboarding.steps.adminSettings.description',
    routeName: 'settings',
    icon: 'pi pi-cog',
  },
  [ONBOARDING_TASKS.ADMIN_PAYMENTS]: {
    titleKey: 'onboarding.steps.adminPayments.title',
    descriptionKey: 'onboarding.steps.adminPayments.description',
    routeName: 'payments',
    icon: 'pi pi-credit-card',
  },
  [ONBOARDING_TASKS.ADMIN_USERS]: {
    titleKey: 'onboarding.steps.adminUsers.title',
    descriptionKey: 'onboarding.steps.adminUsers.description',
    routeName: 'users',
    icon: 'pi pi-users',
  },
  [ONBOARDING_TASKS.ADMIN_COURSES]: {
    titleKey: 'onboarding.steps.adminCourses.title',
    descriptionKey: 'onboarding.steps.adminCourses.description',
    routeName: 'home',
    icon: 'pi pi-calendar-plus',
  },
};

export function useOnboarding() {
  const authStore = useAuthStore();
  const router = useRouter();
  const route = useRoute();

  const onboardingState = computed(() => authStore.user?.onboardingState || []);
  const isSkipped = computed(() => onboardingState.value.includes('skipped'));

  const filteredMetadata = computed(() => {
    if (authStore.isAdmin) {
      return {
        [ONBOARDING_TASKS.ADMIN_SETTINGS]: TASK_METADATA[ONBOARDING_TASKS.ADMIN_SETTINGS],
        [ONBOARDING_TASKS.ADMIN_PAYMENTS]: TASK_METADATA[ONBOARDING_TASKS.ADMIN_PAYMENTS],
        [ONBOARDING_TASKS.ADMIN_USERS]: TASK_METADATA[ONBOARDING_TASKS.ADMIN_USERS],
        [ONBOARDING_TASKS.ADMIN_COURSES]: TASK_METADATA[ONBOARDING_TASKS.ADMIN_COURSES],
      };
    }

    const metadata = {
      [ONBOARDING_TASKS.PROFILE_UPDATE]: TASK_METADATA[ONBOARDING_TASKS.PROFILE_UPDATE],
      [ONBOARDING_TASKS.FIRST_BOOKING]: TASK_METADATA[ONBOARDING_TASKS.FIRST_BOOKING],
      [ONBOARDING_TASKS.EXPLORE_MEETUPS]: TASK_METADATA[ONBOARDING_TASKS.EXPLORE_MEETUPS],
      [ONBOARDING_TASKS.PERSONAL_BESTS]: TASK_METADATA[ONBOARDING_TASKS.PERSONAL_BESTS],
      [ONBOARDING_TASKS.LEADERBOARD]: TASK_METADATA[ONBOARDING_TASKS.LEADERBOARD],
    };
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
    const courseStore = useCourseStore();

    watch(
      () => route.name,
      (newName) => {
        if (authStore.isAdmin) {
          if (newName === 'settings') {
            markTaskComplete(ONBOARDING_TASKS.ADMIN_SETTINGS);
          } else if (newName === 'payments') {
            markTaskComplete(ONBOARDING_TASKS.ADMIN_PAYMENTS);
          } else if (newName === 'users') {
            markTaskComplete(ONBOARDING_TASKS.ADMIN_USERS);
          }
        } else {
          if (newName === 'meetups') {
            markTaskComplete(ONBOARDING_TASKS.EXPLORE_MEETUPS);
          } else if (newName === 'personal-bests') {
            markTaskComplete(ONBOARDING_TASKS.PERSONAL_BESTS);
          } else if (newName === 'leaderboard') {
            markTaskComplete(ONBOARDING_TASKS.LEADERBOARD);
          }
        }
      },
      { immediate: true }
    );

    if (authStore.isAdmin) {
      watch(
        () => courseStore.courseList.length,
        (newLength) => {
          if (newLength > 0) {
            markTaskComplete(ONBOARDING_TASKS.ADMIN_COURSES);
          }
        },
        { immediate: true }
      );
    }
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
