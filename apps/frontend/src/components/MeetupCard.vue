<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Meetup } from '../services/meetup.service';
import { MeetupStatus } from '../app/enums/MeetupStatus';
import { RsvpStatus } from '../app/enums/RsvpStatus';
import { useTimeStore } from '../store/useTimeStore';
import { useAuthStore } from '../store/useAuthStore';
import { formatDate, formatDateTime } from '../services/date-utils';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Avatar from 'primevue/avatar';
import AvatarGroup from 'primevue/avatargroup';
import Dialog from 'primevue/dialog';

const props = defineProps<{
  meetup: Meetup;
}>();

const emit = defineEmits<{
  (e: 'rsvp', status: RsvpStatus): void;
  (e: 'cancel'): void;
  (e: 'edit'): void;
}>();

const { t } = useI18n();
const timeStore = useTimeStore();
const authStore = useAuthStore();

const rsvpDeadline = computed(() => new Date(props.meetup.rsvpDeadline));
const meetupDate = computed(() => new Date(props.meetup.meetupDate));
const isRsvpLocked = computed(() => timeStore.now > rsvpDeadline.value || props.meetup.status !== MeetupStatus.OPEN);
const isPast = computed(() => timeStore.now > meetupDate.value);

const displayStatus = computed(() => {
  if (props.meetup.status === MeetupStatus.CANCELLED) return t('meetup.status.cancelled');
  if (isPast.value) return t('meetup.status.closed');
  if (timeStore.now > rsvpDeadline.value) return t('meetup.status.locked');
  return t(`meetup.status.${props.meetup.status}`);
});

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
  if (diff <= 0) return t('meetup.status.locked');

  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);

  const pad = (n: number) => n.toString().padStart(2, '0');

  if (days > 0) {
    return `${days}${t('app.dayUnit')} ${pad(hours)}${t('app.hourShort')} ${pad(minutes)}${t('app.minutesShort')} ${pad(seconds)}${t('app.secondsShort')}`;
  }
  return `${pad(hours)}${t('app.hourShort')} ${pad(minutes)}${t('app.minutesShort')} ${pad(seconds)}${t('app.secondsShort')}`;
});

const statusSeverity = computed(() => {
  if (props.meetup.status === MeetupStatus.CANCELLED) return 'danger';
  if (isPast.value) return 'secondary';
  if (timeStore.now > rsvpDeadline.value) return 'secondary';

  switch (props.meetup.status) {
    case MeetupStatus.CONFIRMED: return 'success';
    case MeetupStatus.OPEN: return 'info';
    default: return 'info';
  }
});

const participants = computed(() => {
    return props.meetup.rsvps.filter(r => r.status === RsvpStatus.GOING);
});

const isDescriptionExpanded = ref(false);
const showParticipantsDialog = ref(false);

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
        :alt="t('meetup.bannerAlt')"
        class="meetup-banner"
      >
    </template>

    <template #title>
      <div class="flex justify-between items-start">
        <h3 class="m-0 text-xl">
          {{ meetup.title }}
        </h3>
        <Tag
          :value="displayStatus"
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
        <div
          v-memo="[meetup.location]"
          class="flex items-center gap-2 text-sm max-w-full overflow-hidden"
        >
          <i class="pi pi-map-marker flex-shrink-0" />
          <a
            v-if="isLocationLink"
            v-tooltip.top="meetup.location"
            :href="meetup.location"
            target="_blank"
            rel="noopener noreferrer"
            tabindex="0"
            class="text-primary hover:underline font-medium truncate focus:outline-none focus:ring-1 focus:ring-primary rounded-sm"
          >
            {{ meetup.location }}
          </a>
          <span
            v-else
            v-tooltip.top="meetup.location.length > 40 ? meetup.location : null"
            :tabindex="meetup.location.length > 40 ? 0 : undefined"
            class="truncate focus:outline-none focus:bg-slate-100 rounded-sm px-1 -mx-1 cursor-default"
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
          :label="t('meetup.moreInfo')"
          class="p-button-link p-0 font-bold"
        />
      </div>

      <p :class="['text-sm mb-1 whitespace-pre-wrap break-words', !isDescriptionExpanded && 'line-clamp-3']">
        {{ meetup.description }}
      </p>
      <div
        v-if="meetup.description && meetup.description.length > 120"
        class="mb-4"
      >
        <button
          class="text-xs font-bold text-primary hover:underline focus:outline-none"
          @click="isDescriptionExpanded = !isDescriptionExpanded"
        >
          {{ isDescriptionExpanded ? t('app.showLess') : t('app.showMore') }}
        </button>
      </div>

      <div class="flex items-center justify-between mb-4 bg-slate-50 p-3 rounded-lg border border-slate-100">
        <div class="flex flex-col">
          <span
            v-memo="[]"
            v-tooltip.top="t('meetup.rsvpDeadlineTooltip')"
            tabindex="0"
            class="text-xs uppercase font-bold text-slate-400 cursor-help flex items-center gap-1 focus:outline-none focus:text-slate-600 transition-colors"
          >
            {{ t('meetup.rsvpDeadline') }}
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
          <span class="text-xs uppercase font-bold text-slate-400">{{ t('meetup.participants') }}</span>
          <span class="font-bold">{{ meetup.goingCount }} / {{ meetup.maxParticipants || '∞' }}</span>
        </div>
      </div>

      <div class="flex flex-col gap-2 mb-4">
        <div
          v-if="participants.length > 0"
          v-tooltip.top="t('meetup.viewParticipants')"
          class="flex items-center gap-2 cursor-pointer group/avatars transition-all hover:translate-x-1"
          @click="showParticipantsDialog = true"
        >
          <AvatarGroup>
            <Avatar
              v-for="p in participants.slice(0, 5)"
              :key="p.id"
              :image="getAvatarUrl(p.user) || undefined"
              :label="!p.user.profilePicture ? p.user.name.charAt(0) : undefined"
              shape="circle"
              size="normal"
              class="border-2 border-white group-hover/avatars:border-primary transition-colors"
            />
            <Avatar
              v-if="participants.length > 5"
              :label="`+${participants.length - 5}`"
              shape="circle"
              size="normal"
              class="border-2 border-white group-hover/avatars:border-primary transition-colors"
            />
          </AvatarGroup>
          <i class="pi pi-chevron-right text-[10px] text-slate-300 group-hover/avatars:text-primary group-hover/avatars:translate-x-1 transition-all" />
        </div>
        <span
          v-else
          class="text-xs text-slate-400 italic mb-2"
        >{{ t('meetup.noParticipants') }}</span>
      </div>

      <Dialog
        v-model:visible="showParticipantsDialog"
        :header="t('meetup.participants')"
        :modal="true"
        class="w-full max-w-sm"
        dismissable-mask
      >
        <div class="flex flex-col gap-3 py-2">
          <div
            v-for="p in participants"
            :key="p.id"
            class="flex items-center gap-3 p-1 rounded-lg transition-colors border border-transparent hover:border-slate-100"
          >
            <Avatar
              :image="getAvatarUrl(p.user) || undefined"
              :label="!p.user.profilePicture ? p.user.name.charAt(0) : undefined"
              shape="circle"
              size="normal"
              class="border border-slate-200"
            />
            <div class="flex flex-col">
              <span class="font-bold text-white">{{ p.user.name }}</span>
              <span
                v-if="p.user.id === meetup.creator.id"
                class="text-[10px] uppercase font-black text-primary tracking-widest"
              >{{ t('meetup.organizer') }}</span>
            </div>
          </div>
        </div>
        <template #footer>
          <Button
            :label="t('app.close')"
            severity="secondary"
            variant="text"
            class="w-full"
            @click="showParticipantsDialog = false"
          />
        </template>
      </Dialog>

      <div class="flex items-center gap-2 text-xs text-slate-500">
        <Avatar
          :image="getAvatarUrl(meetup.creator) || undefined"
          :label="!meetup.creator.profilePicture ? meetup.creator.name.charAt(0) : undefined"
          shape="circle"
          size="small"
        />
        <span>{{ t('meetup.organizedBy') }} <strong>{{ meetup.creator.name }}</strong></span>
      </div>
    </template>

    <template #footer>
      <div class="flex gap-2 justify-end">
        <template v-if="canEdit">
          <Button
            v-if="meetup.status === MeetupStatus.OPEN"
            icon="pi pi-times"
            :label="t('meetup.cancel')"
            class="p-button-danger p-button-sm"
            @click="emit('cancel')"
          />
          <Button
            icon="pi pi-pencil"
            :label="t('meetup.edit')"
            class="p-button-secondary p-button-sm"
            @click="emit('edit')"
          />
        </template>

        <template v-if="!isRsvpLocked">
          <Button
            v-if="myRsvp !== RsvpStatus.GOING"
            icon="pi pi-check"
            :label="t('meetup.going')"
            class="p-button-primary p-button-sm"
            @click="handleRsvp(RsvpStatus.GOING)"
          />
          <Button
            v-else
            icon="pi pi-times"
            :label="t('meetup.notGoing')"
            class="p-button-secondary p-button-sm"
            @click="handleRsvp(RsvpStatus.NOT_GOING)"
          />
        </template>
        <template v-else>
          <Tag
            v-if="myRsvp === RsvpStatus.GOING"
            :value="t('meetup.youAreGoing')"
            severity="secondary"
          />
          <Tag
            v-else-if="myRsvp === RsvpStatus.NOT_GOING"
            :value="t('meetup.youAreNotGoing')"
            severity="secondary"
          />
          <Tag
            v-else
            :value="t('meetup.registrationClosed')"
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

  :deep(.p-card-body) {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
  }

  :deep(.p-card-content) {
    flex: 1;
    padding: 0;
  }
}

.meetup-banner {
  width: 100%;
  height: 160px;
  object-fit: cover;
}
</style>
