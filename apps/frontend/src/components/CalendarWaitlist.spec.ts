import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import PrimeVue from 'primevue/config';
import WeeklyCalendar from './WeeklyCalendar.vue';
import MobileCalendar from './MobileCalendar.vue';
import CourseDetails from './CourseDetails.vue';

// Mock i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, params?: any) => {
      if (key === 'course.spotsLeft' && params) {
        return `SPOTS LEFT: ${params.count}`;
      }
      return key;
    },
    locale: { value: 'en' }
  })
}));

// Mock the auth store or other globals
vi.mock('../store/useAuthStore', () => ({
  useAuthStore: () => ({
    isLoggedIn: true,
    isTrainer: false,
    viewMode: 'member',
    isTrial: false,
    user: { id: 1, name: 'Test User' }
  })
}));

// Mock api
vi.mock('../services/api', () => ({
  default: {
    post: vi.fn(),
    delete: vi.fn()
  }
}));

// Mock primevue toast
vi.mock('primevue/usetoast', () => ({
  useToast: () => ({
    add: vi.fn()
  })
}));

const mockCourses = [
  {
    id: 1,
    startTime: '2026-06-11T19:00:00',
    endTime: '2026-06-11T20:00:00',
    durationMinutes: 60,
    title: 'Full Course with Waitlist',
    capacity: 2,
    bookings: [
      { id: 10, isWaitlist: false, user: { id: 101, name: 'Alice' } },
      { id: 11, isWaitlist: false, user: { id: 102, name: 'Bob' } },
      { id: 12, isWaitlist: true, user: { id: 103, name: 'Charlie' } },
      { id: 13, isWaitlist: true, user: { id: 104, name: 'Dave' } }
    ],
    user: { id: 200, name: 'Coach Smith' },
    status: 'active',
    cycleCategory: null
  },
  {
    id: 2,
    startTime: '2026-06-11T19:00:00',
    endTime: '2026-06-11T20:00:00',
    durationMinutes: 60,
    title: 'Full Course no Waitlist',
    capacity: 2,
    bookings: [
      { id: 10, isWaitlist: false, user: { id: 101, name: 'Alice' } },
      { id: 11, isWaitlist: false, user: { id: 102, name: 'Bob' } }
    ],
    user: { id: 200, name: 'Coach Smith' },
    status: 'active',
    cycleCategory: null
  },
  {
    id: 3,
    startTime: '2026-06-11T19:00:00',
    endTime: '2026-06-11T20:00:00',
    durationMinutes: 60,
    title: 'Not Full Course',
    capacity: 2,
    bookings: [
      { id: 10, isWaitlist: false, user: { id: 101, name: 'Alice' } }
    ],
    user: { id: 200, name: 'Coach Smith' },
    status: 'active',
    cycleCategory: null
  }
];

describe('Waitlist display in course views', () => {
  beforeEach(() => {
    const pinia = createPinia();
    setActivePinia(pinia);
  });

  describe('WeeklyCalendar.vue', () => {
    it('shows waitlist count when course is full, and doesn\'t when not full or empty waitlist', () => {
      const wrapper = mount(WeeklyCalendar, {
        props: {
          courses: mockCourses,
          baseDate: new Date('2026-06-11T12:00:00'),
          userId: 1
        },
        global: {
          plugins: [PrimeVue]
        }
      });

      const text = wrapper.text();
      // Full course with 2 waitlist users should display waitlist count (+2)
      expect(text).toContain('calendar.full (+2)');

      // Full course with no waitlist should just show full
      expect(text).toContain('calendar.full');
      expect(text).not.toContain('calendar.full (+0)');

      // Not full course should show spots count
      expect(text).toContain('1 / 2');
    });
  });

  describe('MobileCalendar.vue', () => {
    it('shows waitlist count when course is full, and doesn\'t when not full or empty waitlist', () => {
      const wrapper = mount(MobileCalendar, {
        props: {
          courses: mockCourses,
          baseDate: new Date('2026-06-11T12:00:00'),
          userId: 1
        },
        global: {
          plugins: [PrimeVue]
        }
      });

      const text = wrapper.text();
      // Should show (+2) for the full course with waitlist
      expect(text).toContain('CALENDAR.FULL (+2)');

      // Should show CALENDAR.FULL for the course without waitlist
      expect(text).toContain('CALENDAR.FULL');

      // Not full course should show spots count
      expect(text).toContain('1 / 2');
    });
  });

  describe('CourseDetails.vue', () => {
    it('shows waitlist count in spotsLeft and registeredCount computed fields', () => {
      const course = mockCourses[0]; // Full Course with Waitlist
      const wrapper = mount(CourseDetails, {
        props: {
          course,
          settings: {},
          isPastCourse: false,
          isOutsideBookingWindow: false,
          isTrialRestricted: false,
          bookingWindowMessage: '',
          isMemberMode: true
        },
        global: {
          plugins: [PrimeVue],
          stubs: {
            Textarea: true,
            InputText: {
              template: '<input :value="modelValue" />',
              props: ['modelValue']
            },
            Button: true
          }
        }
      });

      const inputs = wrapper.findAll('input');
      const inputValues = inputs.map(i => i.element.value);

      // Spots Left should show waitlist active (+2)
      expect(inputValues).toContain('COURSE.WAITLISTACTIVE (+2)');

      // Registered Count should show 2 / 2 (+2)
      expect(inputValues).toContain('2 / 2 (+2)');
    });

    it('shows waitlist active (no +X) when course is full but has 0 waitlisted', () => {
      const course = mockCourses[1]; // Full Course no Waitlist
      const wrapper = mount(CourseDetails, {
        props: {
          course,
          settings: {},
          isPastCourse: false,
          isOutsideBookingWindow: false,
          isTrialRestricted: false,
          bookingWindowMessage: '',
          isMemberMode: true
        },
        global: {
          plugins: [PrimeVue],
          stubs: {
            Textarea: true,
            InputText: {
              template: '<input :value="modelValue" />',
              props: ['modelValue']
            },
            Button: true
          }
        }
      });

      const inputs = wrapper.findAll('input');
      const inputValues = inputs.map(i => i.element.value);

      // Spots Left should show waitlist active (no +0)
      expect(inputValues).toContain('COURSE.WAITLISTACTIVE');
      expect(inputValues).not.toContain('COURSE.WAITLISTACTIVE (+0)');

      // Registered Count should show 2 / 2 (no +0)
      expect(inputValues).toContain('2 / 2');
    });
  });
});
