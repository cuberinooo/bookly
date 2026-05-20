import { useCourseStore } from '../store/useCourseStore';
import { useMeetupStore } from '../store/useMeetupStore';

export interface EntityUpdate {
  entity: string;
  action: 'created' | 'updated' | 'deleted';
  id: number;
  companyId: number;
}

const pendingUpdates: EntityUpdate[] = [];
let flushTimer: ReturnType<typeof setTimeout> | null = null;

export function enqueueUpdate(update: EntityUpdate): void {
  pendingUpdates.push(update);
  
  if (flushTimer) clearTimeout(flushTimer);
  flushTimer = setTimeout(flushQueue, 300);
}

function flushQueue(): void {
  if (pendingUpdates.length === 0) return;

  const grouped: Record<string, EntityUpdate[]> = {};
  pendingUpdates.forEach(u => {
    if (!grouped[u.entity]) grouped[u.entity] = [];
    grouped[u.entity].push(u);
  });

  for (const [entityType, updates] of Object.entries(grouped)) {
    // Deduplicate: same entity ID -> latest action wins
    const dedupedMap = new Map<number, EntityUpdate>();
    updates.forEach(u => dedupedMap.set(u.id, u));
    const deduped = Array.from(dedupedMap.values());

    switch (entityType) {
      case 'Course':
      case 'CourseSeries':
      case 'Booking':
        useCourseStore().applyBatchUpdate(deduped);
        break;
      case 'Meetup':
      case 'MeetupRsvp':
        useMeetupStore().applyBatchUpdate(deduped);
        break;
    }
  }

  pendingUpdates.length = 0;
  flushTimer = null;
}
