import { defineStore } from 'pinia';
import meetupService, { Meetup, MeetupFilters } from '../services/meetup.service';
import { RsvpStatus } from '../app/enums/RsvpStatus';

export const useMeetupStore = defineStore('meetup', {
  state: () => ({
    meetups: new Map<number, Meetup>(),
    meetupListOrder: [] as number[],
    loading: false,
    lastFetched: null as number | null,
    activeFilter: 'active' as 'active' | 'past' | 'joined' | 'cancelled'
  }),

  getters: {
    meetupList: (state) => state.meetupListOrder.map(id => state.meetups.get(id)).filter(Boolean) as Meetup[],
  },

  actions: {
    async fetchMeetups(filter: 'active' | 'past' | 'joined' | 'cancelled' = this.activeFilter) {
      this.loading = true;
      this.activeFilter = filter;
      try {
        const data = await meetupService.getMeetups({ filter });
        this.meetupListOrder = data.map(m => m.id);
        data.forEach(m => this.meetups.set(m.id, m));
        this.lastFetched = Date.now();
      } catch (err) {
        console.error('Failed to fetch meetups', err);
        throw err;
      } finally {
        this.loading = false;
      }
    },

    async rsvp(id: number, status: RsvpStatus) {
      await meetupService.rsvp(id, status);
      // Refresh the specific meetup
      const updated = await meetupService.getMeetup(id);
      this.meetups.set(id, updated);
    },

    async cancelMeetup(id: number) {
      await meetupService.cancelMeetup(id);
      const updated = await meetupService.getMeetup(id);
      this.meetups.set(id, updated);
    },

    async createMeetup(data: any) {
        const newMeetup = await meetupService.createMeetup(data);
        this.meetups.set(newMeetup.id, newMeetup);
        this.meetupListOrder.unshift(newMeetup.id);
        return newMeetup;
    },

    async updateMeetup(id: number, data: any) {
        const updated = await meetupService.updateMeetup(id, data);
        this.meetups.set(id, updated);
        return updated;
    },

    async applyBatchUpdate(updates: any[]) {
      const toDelete = updates.filter(u => u.action === 'deleted');
      const toFetch = updates.filter(u => u.action !== 'deleted');

      toDelete.forEach(u => {
        this.meetups.delete(u.id);
        this.meetupListOrder = this.meetupListOrder.filter(id => id !== u.id);
      });

      if (toFetch.length > 0) {
        await this.fetchMeetups();
      }
    }
  }
});
