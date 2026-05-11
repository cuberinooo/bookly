import api from './api';
import { MeetupStatus } from '../app/enums/MeetupStatus';
import { RsvpStatus } from '../app/enums/RsvpStatus';

export interface Meetup {
  id: number;
  title: string;
  description: string | null;
  meetupDate: string;
  location: string;
  imageUrl: string | null;
  link: string | null;
  minParticipants: number | null;
  maxParticipants: number | null;
  rsvpDeadline: string;
  status: MeetupStatus;
  createdAt: string;
  creator: {
    id: number;
    name: string;
    profilePicture: string | null;
  };
  rsvps: MeetupRsvp[];
  goingCount: number;
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
  }
};

export default meetupService;
