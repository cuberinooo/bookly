<script setup lang="ts">
import { computed } from 'vue';
import { Meetup } from '../services/meetup.service';
import { MeetupStatus } from '../app/enums/MeetupStatus';
import { RsvpStatus } from '../app/enums/RsvpStatus';
import { timeStore } from '../store/time';
import { authStore } from '../store/auth';
import { formatDate, formatDateTime } from '../services/date-utils';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Avatar from 'primevue/avatar';
import AvatarGroup from 'primevue/avatargroup';

const props = defineProps<{
  meetup: Meetup;
}>();

const emit = defineEmits<{
  (e: 'rsvp', status: RsvpStatus): void;
  (e: 'cancel'): void;
  (e: 'edit'): void;
}>();

const rsvpDeadline = computed(() => new Date(props.meetup.rsvpDeadline));
const meetupDate = computed(() => new Date(props.meetup.meetupDate));
const isRsvpLocked = computed(() => timeStore.now > rsvpDeadline.value || props.meetup.status !== MeetupStatus.OPEN);
const isOwner = computed(() => authStore.user?.id === props.meetup.creator.id);
const canEdit = computed(() => isOwner.value && timeStore.now < meetupDate.value && props.meetup.status !== MeetupStatus.CANCELLED);

const getAvatarUrl = (user: { id: number, profilePicture: string | null }) => {
  if (user.profilePicture) {
    return `${import.meta.env.VITE_API_URL}/user/profile-picture/${user.id}?t=${user.profilePicture}`;
  }
  return null;
};

const myRsvp = computed(() => {
  return props.meetup.rsvps.find(r => r.user.id === authStore.user?.id)?.status;
});

const countdown = computed(() => {
  const diff = rsvpDeadline.value.getTime() - timeStore.now.getTime();
  if (diff <= 0) return 'LOCKED';

  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);

  const pad = (n: number) => n.toString().padStart(2, '0');

  if (days > 0) {
    return `${days}d ${pad(hours)}h ${pad(minutes)}m ${pad(seconds)}s`;
  }
  return `${pad(hours)}h ${pad(minutes)}m ${pad(seconds)}s`;
});

const statusSeverity = computed(() => {
  switch (props.meetup.status) {
    case MeetupStatus.CONFIRMED: return 'success';
    case MeetupStatus.CANCELLED: return 'danger';
    case MeetupStatus.OPEN: return 'info';
    default: return 'info';
  }
});

const participants = computed(() => {
    return props.meetup.rsvps.filter(r => r.status === RsvpStatus.GOING);
});

const isLocationLink = computed(() => {
  const loc = props.meetup.location;
  return loc.startsWith('http://') || loc.startsWith('https://');
});

const handleRsvp = (status: RsvpStatus) => {
  if (isRsvpLocked.value) return;
  emit('rsvp', status);
};
</script>

<template>
  <Card
    class="meetup-card"
    :class="{ 'is-locked': isRsvpLocked }"
  >
    <template
      v-if="meetup.imageUrl"
      #header
    >
      <img
        :src="meetup.imageUrl"
        alt="Meetup Banner"
        class="meetup-banner"
      >
    </template>

    <template #title>
      <div class="flex justify-between items-start">
        <h3 class="m-0 text-xl">
          {{ meetup.title }}
        </h3>
        <Tag
          :value="meetup.status"
          :severity="statusSeverity"
        />
      </div>
    </template>

    <template #subtitle>
      <div class="flex flex-col gap-1 mt-2">
        <div class="flex items-center gap-2 text-sm">
          <i class="pi pi-calendar" />
          <span>{{ formatDateTime(meetup.meetupDate) }}</span>
        </div>
        <div class="flex items-center gap-2 text-sm max-w-full overflow-hidden">
          <i class="pi pi-map-marker flex-shrink-0" />
          <a
            v-if="isLocationLink"
            v-tooltip.top="meetup.location"
            :href="meetup.location"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary hover:underline font-medium truncate"
          >
            {{ meetup.location }}
          </a>
          <span
            v-else
            v-tooltip.top="meetup.location.length > 40 ? meetup.location : null"
            class="truncate"
          >
            {{ meetup.location }}
          </span>
        </div>
      </div>
    </template>

    <template #content>
      <div
        v-if="meetup.link"
        class="mb-3"
      >
        <Button
          as="a"
          :href="meetup.link"
          target="_blank"
          rel="noopener noreferrer"
          icon="pi pi-external-link"
          label="More Info"
          class="p-button-link p-0 font-bold"
        />
      </div>

      <p class="text-sm line-clamp-3 mb-4">
        {{ meetup.description }}
      </p>

      <div class="flex items-center justify-between mb-4 bg-slate-50 p-3 rounded-lg border border-slate-100">
        <div class="flex flex-col">
          <span
            v-tooltip.top="'Répondez s\'il vous plaît - Please respond by this date to help the organizer plan better.'"
            class="text-xs uppercase font-bold text-slate-400 cursor-help"
          >
            RSVP Deadline
            <i class="pi pi-info-circle text-[10px]" />
          </span>
          <span
            class="font-mono text-lg font-bold"
            :class="isRsvpLocked ? 'text-slate-400' : 'text-primary'"
          >
            {{ countdown }}
          </span>
        </div>
        <div class="flex flex-col items-end">
          <span class="text-xs uppercase font-bold text-slate-400">Participants</span>
          <span class="font-bold">{{ meetup.goingCount }} / {{ meetup.maxParticipants || '∞' }}</span>
        </div>
      </div>

      <div class="flex items-center gap-2 mb-4">
        <AvatarGroup v-if="participants.length > 0">
          <Avatar
            v-for="p in participants.slice(0, 5)"
            :key="p.id"
            :image="getAvatarUrl(p.user) || undefined"
            :label="!p.user.profilePicture ? p.user.name.charAt(0) : undefined"
            shape="circle"
            size="normal"
          />
          <Avatar
            v-if="participants.length > 5"
            :label="`+${participants.length - 5}`"
            shape="circle"
            size="normal"
          />
        </AvatarGroup>
        <span
          v-else
          class="text-xs text-slate-400 italic"
        >No participants yet</span>
      </div>

      <div class="flex items-center gap-2 text-xs text-slate-500">
        <Avatar
          :image="getAvatarUrl(meetup.creator) || undefined"
          :label="!meetup.creator.profilePicture ? meetup.creator.name.charAt(0) : undefined"
          shape="circle"
          size="small"
        />
        <span>Organized by <strong>{{ meetup.creator.name }}</strong></span>
      </div>
    </template>

    <template #footer>
      <div class="flex gap-2 justify-end">
        <template v-if="canEdit">
          <Button
            icon="pi pi-pencil"
            label="Edit"
            class="p-button-secondary p-button-sm"
            @click="emit('edit')"
          />
          <Button
            v-if="meetup.status === MeetupStatus.OPEN"
            icon="pi pi-times"
            label="Cancel"
            class="p-button-danger p-button-sm"
            @click="emit('cancel')"
          />
        </template>

        <template v-if="!isRsvpLocked">
          <Button
            v-if="myRsvp !== RsvpStatus.GOING"
            icon="pi pi-check"
            label="Going"
            class="p-button-primary p-button-sm"
            @click="handleRsvp(RsvpStatus.GOING)"
          />
          <Button
            v-else
            icon="pi pi-times"
            label="Not Going"
            class="p-button-secondary p-button-sm"
            @click="handleRsvp(RsvpStatus.NOT_GOING)"
          />
        </template>
        <template v-else>
          <Tag
            v-if="myRsvp === RsvpStatus.GOING"
            value="You're going!"
            severity="success"
          />
          <Tag
            v-else-if="myRsvp === RsvpStatus.NOT_GOING"
            value="You're not going"
            severity="secondary"
          />
          <Tag
            v-else
            value="Registration Closed"
            severity="secondary"
          />
        </template>
      </div>
    </template>
  </Card>
</template>

<style scoped lang="scss">
.meetup-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  height: 100%;
  display: flex;
  flex-direction: column;

  &:hover {
    transform: translateY(-4px);
  }

  &.is-locked {
    opacity: 0.9;
    .meetup-banner {
      filter: grayscale(0.5);
    }
  }
}

.meetup-banner {
  width: 100%;
  height: 160px;
  object-fit: cover;
}

.text-primary {
    color: var(--primary-color);
}
</style>
