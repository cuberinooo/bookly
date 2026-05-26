import { defineStore } from 'pinia';
import api from '../services/api';

export interface Course {
  id: string | number;
  title: string;
  description: string | null;
  startTime: string;
  endTime: string;
  durationMinutes: number;
  capacity: number;
  allowTrial: boolean;
  status: string;
  seriesId: string | null;
  user: {
    id: number;
    name: string;
    profilePicture: string | null;
  };
  bookings: any[];
  bookingCount: number;
  cycleCategory?: any;
}

export interface CourseFilters {
  all?: boolean;
  startDate?: string;
  endDate?: string;
  trainerId?: number;
  memberId?: number;
  futureOnly?: boolean;
  page?: number;
  limit?: number;
}

export const useCourseStore = defineStore('course', {
  state: () => ({
    courses: new Map<string | number, Course>(),
    courseListOrder: [] as (string | number)[],
    cycleInfo: null as any,
    isLoading: false,
    lastFetched: null as number | null,
    filters: {} as CourseFilters,
    loadedRange: { start: null as string | null, end: null as string | null },
    pagination: {
        page: 1,
        limit: 10,
        totalItems: 0,
        totalPages: 0
    }
  }),

  getters: {
    courseList: (state) => state.courseListOrder
        .map(id => state.courses.get(id))
        .filter((c): c is Course => !!c && c.status !== 'deleted'),
    getCourseById: (state) => (id: string | number) => state.courses.get(id),
  },

  actions: {
    async fetchCourses(filters: CourseFilters = this.filters, forceLoading = false) {
      if (forceLoading || this.courseListOrder.length === 0) {
        this.isLoading = true;
      }
      
      this.filters = { ...filters };
      try {
        const response = await api.get('/courses', { params: filters });
        const data = response.data.data as Course[];
        this.cycleInfo = response.data.cycle;
        
        if (response.data.meta) {
            this.pagination = {
                page: response.data.meta.page,
                limit: response.data.meta.limit,
                totalItems: response.data.meta.totalItems,
                totalPages: response.data.meta.totalPages
            };
        }

        // Populate new data
        this.courseListOrder = data.map(c => c.id);
        data.forEach(c => this.courses.set(c.id, c));
        
        this.lastFetched = Date.now();
        if (filters.startDate) this.loadedRange.start = filters.startDate;
        if (filters.endDate) this.loadedRange.end = filters.endDate;
      } catch (err) {
        console.error('Failed to fetch courses', err);
        throw err;
      } finally {
        this.isLoading = false;
      }
    },

    async fetchCourse(id: string | number) {
      this.isLoading = true;
      try {
        const response = await api.get(`/courses/${id}`);
        const course = response.data as Course;
        this.courses.set(course.id, course);
        return course;
      } catch (err) {
        console.error(`Failed to fetch course ${id}`, err);
        throw err;
      } finally {
        this.isLoading = false;
      }
    },

    async createCourse(data: any) {
      try {
        const response = await api.post('/courses', data);
        await this.fetchCourses();
        return response.data;
      } catch (err) {
        console.error('Failed to create course', err);
        throw err;
      }
    },

    async updateCourse(id: string | number, data: any, transferAll = false) {
      try {
        const url = transferAll ? `/courses/${id}?transferAll=true` : `/courses/${id}`;
        const response = await api.patch(url, data);
        await this.fetchCourses();
        return response.data;
      } catch (err) {
        console.error(`Failed to update course ${id}`, err);
        throw err;
      }
    },

    async deleteCourse(id: string | number, deleteAll = false) {
      try {
        const url = deleteAll ? `/courses/${id}?deleteAll=true` : `/courses/${id}`;
        await api.delete(url);
        await this.fetchCourses();
      } catch (err) {
        console.error(`Failed to delete course ${id}`, err);
        throw err;
      }
    },

    async postponeCourse(id: string | number) {
      try {
        await api.post(`/courses/${id}/postpone`);
        await this.fetchCourses();
      } catch (err) {
        console.error(`Failed to postpone course ${id}`, err);
        throw err;
      }
    },

    async bookCourse(id: string | number) {
        try {
            await api.post(`/courses/${id}/book`);
            await this.fetchCourses();
        } catch (err) {
            console.error(`Failed to book course ${id}`, err);
            throw err;
        }
    },

    async unbookCourse(id: string | number) {
        try {
            await api.delete(`/courses/${id}/book`);
            await this.fetchCourses();
        } catch (err) {
            console.error(`Failed to unbook course ${id}`, err);
            throw err;
        }
    },

    async applyBatchUpdate(updates: any[]) {
      const toDelete = updates.filter(u => u.action === 'deleted');
      const toFetch = updates.filter(u => u.action !== 'deleted');

      toDelete.forEach(u => {
        this.courses.delete(u.id);
        this.courseListOrder = this.courseListOrder.filter(id => id !== u.id);
      });

      if (toFetch.length > 0) {
        // For simplicity and to ensure correct list order/cycle info, 
        // we re-fetch the current range if anything changed.
        // This is still only ONE call instead of N.
        await this.fetchCourses();
      }
    }
  }
});
