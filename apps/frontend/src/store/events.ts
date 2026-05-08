import { reactive } from 'vue';

export const eventsStore = reactive({
  lastEvent: null as { entity: string, action: string, id: number | null } | null,
  
  emit(entity: string, action: string, id: number | null = null) {
    this.lastEvent = { entity, action, id };
  }
});
