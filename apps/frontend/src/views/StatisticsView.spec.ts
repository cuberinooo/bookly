import { describe, it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import StatisticsView from './StatisticsView.vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Card from 'primevue/card';
import api from '../services/api';

// Mock the api module
vi.mock('../services/api', () => {
  return {
    default: {
      get: vi.fn().mockResolvedValue({
        data: {
          totalCourses: 5,
          monthlyStats: [
            { month: '2026-06', count: 5 }
          ],
          averageFillRate: 85.0,
          uniqueMembers: 12,
          popularTimeSlots: [
            { hour: '18:00', count: 3, attempts: 15 }
          ],
          popularCourseTypes: [
            { title: 'CrossFit', count: 3, attempts: 15 }
          ],
          popularDaysOfWeek: [
            { day: 'Monday', count: 3, attempts: 15 }
          ]
        }
      })
    }
  };
});

// Mock i18n
vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string) => key,
    locale: { value: 'en' }
  })
}));

describe('StatisticsView.vue', () => {
  it('renders stats successfully and loads statistics', async () => {
    const pinia = createPinia();
    const wrapper = mount(StatisticsView, {
      global: {
        plugins: [pinia, PrimeVue],
        components: {
          Card
        },
        stubs: {
          Chart: true,
          Skeleton: true,
          DatePicker: true
        }
      }
    });

    // Wait for the mounted hook to trigger API call and render
    await flushPromises();

    // Verify statistics data is rendered
    expect(wrapper.text()).toContain('statistics.title');
    expect(wrapper.text()).toContain('5'); // totalCourses
    expect(wrapper.text()).toContain('12'); // uniqueMembers
  });

  it('updates statistics when DatePicker changes', async () => {
    const pinia = createPinia();
    const wrapper = mount(StatisticsView, {
      global: {
        plugins: [pinia, PrimeVue],
        components: {
          Card
        },
        stubs: {
          Chart: true,
          Skeleton: true,
          DatePicker: true
        }
      }
    });

    await flushPromises();

    const apiGetMock = vi.mocked(api.get);
    expect(apiGetMock).toHaveBeenCalledWith('/trainer/statistics', expect.objectContaining({
      params: { startDate: '2026-06-01' }
    }));

    apiGetMock.mockClear();

    // Find the DatePicker and emit modelValue update
    const datePicker = wrapper.findComponent({ name: 'DatePicker' });
    const newDate = new Date(2026, 6, 15); // July 15, 2026
    await datePicker.vm.$emit('update:modelValue', newDate);
    await flushPromises();

    expect(apiGetMock).toHaveBeenCalledWith('/trainer/statistics', expect.objectContaining({
      params: { startDate: '2026-07-15' }
    }));
  });
});
