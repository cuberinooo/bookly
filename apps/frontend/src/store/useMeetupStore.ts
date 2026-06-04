import { defineStore } from 'pinia';
import meetupService, { Meetup, MeetupFilters } from '../services/meetup.service';
import { RsvpStatus } from '../app/enums/RsvpStatus';

export const useMeetupStore = defineStore('meetup', {
  state: () => ({
    meetups: new Map<number, Meetup>(),
    meetupListOrder: [] as number[],
    isLoading: false,
    lastFetched: null as number | null,
    activeFilter: 'active' as 'active' | 'past' | 'joined' | 'cancelled',
    totalUnreadCount: 0,
    lastCommentUpdate: { meetupId: null as number | null, timestamp: 0 }
  }),

  getters: {
    meetupList: (state) => state.meetupListOrder.map(id => state.meetups.get(id)).filter(Boolean) as Meetup[],
    globalUnread: (state) => state.totalUnreadCount,
  },

  actions: {
    async fetchMeetups(filter: 'active' | 'past' | 'joined' | 'cancelled' = this.activeFilter, forceLoading = false) {
      if (forceLoading || this.meetupListOrder.length === 0) {
        this.isLoading = true;
      }
      
      this.activeFilter = filter;
      try {
        const data = await meetupService.getMeetups({ filter });
        this.meetupListOrder = data.map(m => m.id);
        data.forEach(m => this.meetups.set(m.id, m));
        this.lastFetched = Date.now();
        // Update global unread count based on current list (aggregation)
        // or fetch it from dedicated endpoint
        await this.fetchNotificationCounts();
      } catch (err) {
        console.error('Failed to fetch meetups', err);
        throw err;
      } finally {
        this.isLoading = false;
      }
    },

    async fetchNotificationCounts() {
        try {
            const data = await meetupService.getNotificationCounts();
            this.totalUnreadCount = data.totalUnread;
        } catch (err) {
            console.error('Failed to fetch notification counts', err);
        }
    },

    async rsvp(id: number, status: RsvpStatus) {
      await meetupService.rsvp(id, status);
      // Refresh the specific meetup
      const updated = await meetupService.getMeetup(id);
      this.meetups.set(id, updated);
      await this.fetchNotificationCounts();
    },

    async cancelMeetup(id: number) {
      await meetupService.cancelMeetup(id);
      const updated = await meetupService.getMeetup(id);
      this.meetups.set(id, updated);
    },

    async createMeetup(data: any) {
        const newMeetup = await meetupService.createMeetup(data);
        newMeetup.unreadCommentsCount = 0;
        this.meetups.set(newMeetup.id, newMeetup);
        this.meetupListOrder.unshift(newMeetup.id);
        return newMeetup;
    },

    async updateMeetup(id: number, data: any) {
        const updated = await meetupService.updateMeetup(id, data);
        const existing = this.meetups.get(id);
        if (existing) {
            updated.unreadCommentsCount = existing.unreadCommentsCount;
        }
        this.meetups.set(id, updated);
        return updated;
    },

    async markRead(id: number) {
        await meetupService.markCommentsRead(id);
        const meetup = this.meetups.get(id);
        if (meetup && meetup.unreadCommentsCount && meetup.unreadCommentsCount > 0) {
            this.totalUnreadCount -= meetup.unreadCommentsCount;
            meetup.unreadCommentsCount = 0;
            this.meetups.set(id, { ...meetup });
        }
    },

    async applyBatchUpdate(updates: any[]) {
      const toDelete = updates.filter(u => u.action === 'deleted');
      const toFetch = updates.filter(u => u.action !== 'deleted');

      toDelete.forEach(u => {
        this.meetups.delete(u.id);
        this.meetupListOrder = this.meetupListOrder.filter(id => id !== u.id);
      });

      if (toFetch.length > 0) {
        // Record comment updates so dialogs can react
        const commentUpdate = toFetch.find(u => u.entity === 'MeetupComment');
        if (commentUpdate) {
            this.lastCommentUpdate = {
                meetupId: commentUpdate.meetupId,
                timestamp: Date.now()
            };
        }

        // Check if we need to refresh notification counts
        const needsCountRefresh = toFetch.some(u => 
            ['MeetupComment', 'MeetupUserReadState', 'Meetup', 'MeetupRsvp'].includes(u.entity)
        );

        if (needsCountRefresh) {
            await this.fetchNotificationCounts();
        }

        // Refresh specific meetups or the whole list
        const needsListRefresh = toFetch.some(u => ['Meetup', 'MeetupRsvp'].includes(u.entity));
        if (needsListRefresh) {
            await this.fetchMeetups();
        } else {
            for (const u of toFetch) {
                const targetId = u.meetupId || u.id;
                if (targetId && this.meetups.has(targetId)) {
                    const updated = await meetupService.getMeetup(targetId);
                    this.meetups.set(targetId, updated);
                }
            }
        }
      }
    }
  }
});
