import api from './api';
import { MeetupStatus } from '../app/enums/MeetupStatus';
import { RsvpStatus } from '../app/enums/RsvpStatus';

export interface Meetup {
  id: number;
  title: string;
  description: string | null;
  meetupDate: string | null;
  location: string;
  imageUrl: string | null;
  link: string | null;
  minParticipants: number | null;
  maxParticipants: number | null;
  rsvpDeadline: string | null;
  status: MeetupStatus;
  createdAt: string;
  creator: {
    id: number;
    name: string;
    profilePicture: string | null;
  };
  rsvps: MeetupRsvp[];
  goingCount: number;
  unreadCommentsCount?: number;
}

export interface MeetupComment {
  id: number;
  content: string;
  createdAt: string;
  author: {
    id: number;
    name: string;
    profilePicture: string | null;
  };
}

export interface MeetupRsvp {
  id: number;
  user: {
    id: number;
    name: string;
    profilePicture: string | null;
  };
  status: RsvpStatus;
  createdAt: string;
}

export interface MeetupFilters {
  filter?: 'active' | 'past' | 'joined' | 'cancelled';
}

const meetupService = {
  async getMeetups(filters: MeetupFilters = {}) {
    const response = await api.get<Meetup[]>('/meetups', { params: filters });
    return response.data;
  },

  async getMeetup(id: number) {
    const response = await api.get<Meetup>(`/meetups/${id}`);
    return response.data;
  },

  async createMeetup(data: Partial<Meetup> & { sendNotification?: boolean }) {
    const response = await api.post<Meetup>('/meetups', data);
    return response.data;
  },

  async updateMeetup(id: number, data: Partial<Meetup>) {
    const response = await api.put<Meetup>(`/meetups/${id}`, data);
    return response.data;
  },

  async rsvp(id: number, status: RsvpStatus) {
    const response = await api.post<MeetupRsvp>(`/meetups/${id}/rsvp`, { status });
    return response.data;
  },

  async cancelMeetup(id: number) {
    const response = await api.post(`/meetups/${id}/cancel`);
    return response.data;
  },

  async getComments(meetupId: number) {
    const response = await api.get<MeetupComment[]>(`/meetups/${meetupId}/comments`);
    return response.data;
  },

  async postComment(meetupId: number, content: string) {
    const response = await api.post<MeetupComment>(`/meetups/${meetupId}/comments`, { content });
    return response.data;
  },

  async markCommentsRead(meetupId: number) {
    await api.post(`/meetups/${meetupId}/comments/mark-read`);
  },

  async getNotificationCounts() {
    const response = await api.get<{ totalUnread: number }>('/meetups/notifications');
    return response.data;
  }
};

export default meetupService;
