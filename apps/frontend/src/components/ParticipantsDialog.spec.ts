import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import PrimeVue from 'primevue/config';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import ParticipantsDialog from './ParticipantsDialog.vue';

// Mock i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
    locale: { value: 'en' }
  })
}));

// Mock PrimeVue tools
vi.mock('primevue/useconfirm', () => ({
  useConfirm: () => ({
    require: vi.fn()
  })
}));
vi.mock('primevue/usetoast', () => ({
  useToast: () => ({
    add: vi.fn()
  })
}));

const authStoreState = {
  isTrainer: false,
  isAdmin: false
};

vi.mock('../store/useAuthStore', () => ({
  useAuthStore: () => authStoreState
}));

// Mock api
vi.mock('../services/api', () => ({
  default: {
    get: vi.fn(),
    patch: vi.fn()
  }
}));

describe('ParticipantsDialog.vue - Trial highlight', () => {
  beforeEach(() => {
    const pinia = createPinia();
    setActivePinia(pinia);
    authStoreState.isTrainer = false;
    authStoreState.isAdmin = false;
  });

  const createCourseMock = (bookings: any[]) => ({
    id: 1,
    title: 'Functional Fitness',
    startTime: '2026-06-11T19:00:00',
    endTime: '2026-06-11T20:00:00',
    bookings,
    user: { id: 200, name: 'Coach Smith' }
  });

  it('renders trial badge for trial members when names are not anonymized', () => {
    const course = createCourseMock([
      {
        id: 10,
        isWaitlist: false,
        user: { id: 101, name: 'Alice', roles: ['ROLE_TRIAL'] }
      },
      {
        id: 11,
        isWaitlist: false,
        user: { id: 102, name: 'Bob', roles: ['ROLE_USER'] }
      }
    ]);

    const wrapper = mount(ParticipantsDialog, {
      props: {
        visible: true,
        course,
        embedded: true
      },
      global: {
        plugins: [PrimeVue],
        components: {
          DataTable,
          Column,
          Button
        }
      }
    });

    const text = wrapper.text();
    // Confirmed trial member Alice should have the trial badge text
    expect(text).toContain('participants.trial');
    expect(text).toContain('Alice');
    expect(text).not.toContain('Bob (Trial)');
  });

  it('hides trial badge for trial members when names are anonymized and viewer is a regular member', () => {
    const course = createCourseMock([
      {
        id: 10,
        isWaitlist: false,
        user: { id: 101, name: 'Athlete #101', roles: ['ROLE_TRIAL'] }
      }
    ]);

    authStoreState.isTrainer = false;
    authStoreState.isAdmin = false;

    const wrapper = mount(ParticipantsDialog, {
      props: {
        visible: true,
        course,
        embedded: true
      },
      global: {
        plugins: [PrimeVue],
        components: {
          DataTable,
          Column,
          Button
        }
      }
    });

    const text = wrapper.text();
    expect(text).toContain('Athlete #101');
    // Regular member shouldn't see 'participants.trial' for anonymized Athlete #101
    expect(text).not.toContain('participants.trial');
  });

  it('shows trial badge even when names are anonymized if the viewer is a trainer', () => {
    const course = createCourseMock([
      {
        id: 10,
        isWaitlist: false,
        user: { id: 101, name: 'Athlete #101', roles: ['ROLE_TRIAL'] }
      }
    ]);

    authStoreState.isTrainer = true;

    const wrapper = mount(ParticipantsDialog, {
      props: {
        visible: true,
        course,
        embedded: true
      },
      global: {
        plugins: [PrimeVue],
        components: {
          DataTable,
          Column,
          Button
        }
      }
    });

    const text = wrapper.text();
    expect(text).toContain('Athlete #101');
    // Trainer/admin should see the trial badge
    expect(text).toContain('participants.trial');
  });
});
