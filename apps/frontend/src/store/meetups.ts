import { reactive } from 'vue';
import meetupService, { Meetup, MeetupFilters } from '../services/meetup.service';
import { RsvpStatus } from '../app/enums/RsvpStatus';

export const meetupStore = reactive({
  meetups: [] as Meetup[],
  loading: false,
  error: null as string | null,

  async fetchMeetups(filters: MeetupFilters = {}) {
    this.loading = true;
    this.error = null;
    try {
      this.meetups = await meetupService.getMeetups(filters);
    } catch (e: any) {
      this.error = e.response?.data?.error || 'Failed to fetch meetups';
    } finally {
      this.loading = false;
    }
  },

  async createMeetup(data: any) {
    try {
      const newMeetup = await meetupService.createMeetup(data);
      this.meetups.unshift(newMeetup);
      return newMeetup;
    } catch (e: any) {
      throw e.response?.data?.error || 'Failed to create meetup';
    }
  },

  async updateMeetup(id: number, data: any) {
    try {
      const updated = await meetupService.updateMeetup(id, data);
      const index = this.meetups.findIndex(m => m.id === id);
      if (index !== -1) {
        this.meetups[index] = updated;
      }
      return updated;
    } catch (e: any) {
      throw e.response?.data?.error || 'Failed to update meetup';
    }
  },

  async rsvp(id: number, status: RsvpStatus) {
    try {
      await meetupService.rsvp(id, status);
      // Refresh the specific meetup in the list
      const index = this.meetups.findIndex(m => m.id === id);
      if (index !== -1) {
        const updated = await meetupService.getMeetup(id);
        this.meetups[index] = updated;
      }
    } catch (e: any) {
      throw e.response?.data?.error || 'Failed to RSVP';
    }
  },

  async cancelMeetup(id: number) {
    try {
      await meetupService.cancelMeetup(id);
      const index = this.meetups.findIndex(m => m.id === id);
      if (index !== -1) {
        const updated = await meetupService.getMeetup(id);
        this.meetups[index] = updated;
      }
    } catch (e: any) {
      throw e.response?.data?.error || 'Failed to cancel meetup';
    }
  }
});
