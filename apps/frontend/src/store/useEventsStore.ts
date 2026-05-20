import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface AppEvent {
  entity: string;
  action: string;
  id: number | null;
}

export const useEventsStore = defineStore('events', () => {
  const lastEvent = ref<AppEvent | null>(null);

  function emit(entity: string, action: string, id: number | null = null) {
    lastEvent.value = { entity, action, id };
  }

  return {
    lastEvent,
    emit
  };
});
