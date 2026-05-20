import { enqueueUpdate } from './mercure-queue';

class MercureService {
  private eventSource: EventSource | null = null;
  private getTopics(): string[] {
    const apiBase = import.meta.env.VITE_API_URL.replace(/\/$/, '');
    return [
      `${apiBase}/booking`,
      `${apiBase}/course`,
      `${apiBase}/courseseries`,
      `${apiBase}/meetup`,
      `${apiBase}/meetuprsvp`,
    ];
  }

  init() {
    if (this.eventSource) {
      return;
    }

    const url = new URL(import.meta.env.VITE_MERCURE_URL);
    this.getTopics().forEach(topic => url.searchParams.append('topic', topic));

    this.eventSource = new EventSource(url.toString());

    this.eventSource.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data);
        console.log('Mercure update received:', data);
        
        if (data.batch) {
          data.updates.forEach((u: any) => enqueueUpdate(u));
        } else {
          enqueueUpdate(data);
        }
      } catch (e) {
        console.error('Failed to parse Mercure event', e);
      }
    };

    this.eventSource.onerror = (error) => {
      console.error('Mercure connection error', error);
      this.eventSource?.close();
      this.eventSource = null;
      // Retry connection after 5 seconds
      setTimeout(() => this.init(), 5000);
    };
  }

  stop() {
    this.eventSource?.close();
    this.eventSource = null;
  }
}

export default new MercureService();
