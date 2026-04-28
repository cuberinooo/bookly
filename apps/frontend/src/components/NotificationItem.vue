<script setup lang="ts">
import { computed } from 'vue';
import { NotificationType } from '../app/enums/NotificationType';

const props = defineProps<{
    notification: {
        id: number;
        message: string;
        type: string;
        createdAt: string;
        isRead: boolean;
    }
}>();

const emit = defineEmits(['read']);

const typeClass = computed(() => {
    switch (props.notification.type) {
        case NotificationType.SUCCESS: return 'notif-success';
        case NotificationType.DANGER: return 'notif-danger';
        case NotificationType.WARNING: return 'notif-danger'; // Map warning to red as requested
        case NotificationType.INFO:
        default: return 'notif-info';
    }
});

const iconClass = computed(() => {
    switch (props.notification.type) {
        case NotificationType.SUCCESS: return 'pi pi-check-circle';
        case NotificationType.DANGER: return 'pi pi-exclamation-circle';
        case NotificationType.WARNING: return 'pi pi-exclamation-triangle';
        case NotificationType.INFO:
        default: return 'pi pi-info-circle';
    }
});

function formatTime(dateStr: string) {
    return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
  <div :class="['notif-item', typeClass, { unread: !notification.isRead }]">
    <div class="notif-icon">
        <i :class="iconClass"></i>
    </div>
    <div class="notif-content">
      <p>{{ notification.message }}</p>
      <div class="notif-footer">
        <small>{{ formatTime(notification.createdAt) }}</small>
        <Button
          v-if="!notification.isRead"
          icon="pi pi-check"
          variant="text"
          size="small"
          class="mark-read-btn"
          @click="emit('read', notification.id)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.notif-item {
    display: flex;
    gap: 1rem;
    padding: 1.25rem;
    border-bottom: 1px solid var(--border-color);
    background: white;
    transition: all 0.2s;
    border-left: 4px solid transparent;

    &:last-child { border-bottom: none; }

    &.unread {
        background-color: #f8fafc;
    }

    &.notif-success {
        border-left-color: var(--success-color);
        .notif-icon i { color: var(--success-color); }
        &.unread { background-color: var(--success-bg); }
    }

    &.notif-danger {
        border-left-color: var(--danger-color);
        .notif-icon i { color: var(--danger-color); }
        &.unread { background-color: var(--danger-bg); }
    }

    &.notif-info {
        border-left-color: #3b82f6;
        .notif-icon i { color: #3b82f6; }
        &.unread { background-color: #eff6ff; }
    }
}

.notif-icon {
    font-size: 1.25rem;
    padding-top: 0.1rem;
}

.notif-content {
    flex: 1;
    
    p { 
        margin: 0; 
        color: var(--text-header); 
        font-weight: 600; 
        line-height: 1.4; 
        font-size: 0.95rem;
    }
}

.notif-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;

    small { 
        color: var(--text-muted); 
        font-family: 'Barlow Condensed', sans-serif; 
        font-weight: 700; 
        text-transform: uppercase; 
        font-size: 0.75rem;
    }
}

.mark-read-btn {
    padding: 0 !important;
    width: 24px !important;
    height: 24px !important;
    color: var(--text-muted) !important;
    
    &:hover { 
        color: var(--primary-color) !important;
        background: transparent !important;
    }
}
</style>
