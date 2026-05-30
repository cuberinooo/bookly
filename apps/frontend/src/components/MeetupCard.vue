<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Meetup } from '../services/meetup.service';
import { MeetupStatus } from '../app/enums/MeetupStatus';
import { RsvpStatus } from '../app/enums/RsvpStatus';
import { useTimeStore } from '../store/useTimeStore';
import { useAuthStore } from '../store/useAuthStore';
import { formatDateTime } from '../services/date-utils';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Tag from 'primevue/tag';
import Avatar from 'primevue/avatar';
import AvatarGroup from 'primevue/avatargroup';
import Dialog from 'primevue/dialog';
import MeetupCommentsDialog from './MeetupCommentsDialog.vue';
import { useMeetupStore } from '../store/useMeetupStore';
import OverlayBadge from 'primevue/overlaybadge';
import Menu from 'primevue/menu';

const props = defineProps<{
  meetup: Meetup;
}>();

const emit = defineEmits<{
  (e: 'rsvp', status: RsvpStatus): void;
  (e: 'cancel'): void;
  (e: 'edit'): void;
  (e: 'comment-added'): void;
}>();

const { t } = useI18n();
const timeStore = useTimeStore();
const authStore = useAuthStore();
const meetupStore = useMeetupStore();

const menu = ref();
const menuItems = computed(() => {
    const items = [];
    if (canEdit.value) {
        items.push({
            label: t('meetup.edit'),
            icon: 'pi pi-pencil',
            command: () => emit('edit')
        });
        if (props.meetup.status === MeetupStatus.OPEN) {
            items.push({
                label: t('meetup.cancel'),
                icon: 'pi pi-times',
                class: 'text-red-500',
                command: () => emit('cancel')
            });
        }
    }
    return items;
});

const localUnreadCount = ref(props.meetup.unreadCommentsCount || 0);
const rsvpDeadline = computed(() => props.meetup.rsvpDeadline ? new Date(props.meetup.rsvpDeadline) : null);
const meetupDate = computed(() => props.meetup.meetupDate ? new Date(props.meetup.meetupDate) : null);
const isRsvpLocked = computed(() => {
    if (props.meetup.status !== MeetupStatus.OPEN) return true;
    if (!rsvpDeadline.value) return false;
    return timeStore.now > rsvpDeadline.value;
});
const isPast = computed(() => meetupDate.value && timeStore.now > meetupDate.value);

const displayStatus = computed(() => {
  if (props.meetup.status === MeetupStatus.CANCELLED) return t('meetup.status.cancelled');
  if (isPast.value) return t('meetup.status.closed');
  if (rsvpDeadline.value && timeStore.now > rsvpDeadline.value) return t('meetup.status.locked');
  if (!meetupDate.value) return t('meetup.status.planning');
  return t(`meetup.status.${props.meetup.status}`);
});

const isOwner = computed(() => authStore.user?.id === props.meetup.creator.id);
const canEdit = computed(() => {
    if (props.meetup.status === MeetupStatus.CANCELLED) return false;
    if (isPast.value) return false;
    return isOwner.value;
});

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
  if (!rsvpDeadline.value) return null;
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
  if (rsvpDeadline.value && timeStore.now > rsvpDeadline.value) return 'secondary';
  if (!meetupDate.value) return 'warn';

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
const showCommentsDialog = ref(false);

const isLocationLink = computed(() => {
  const loc = props.meetup.location;
  return loc.startsWith('http://') || loc.startsWith('https://');
});

const handleRsvp = (status: RsvpStatus) => {
  if (isRsvpLocked.value) return;
  emit('rsvp', status);
};

const openComments = () => {
    showCommentsDialog.value = true;
    localUnreadCount.value = 0;
    meetupStore.markRead(props.meetup.id);
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
          <span v-if="meetupDate">{{ formatDateTime(meetup.meetupDate) }}</span>
          <span
            v-else
            class="text-amber-600 font-bold italic"
          >{{ t('meetup.status.planning') }}</span>
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

      <div
        v-if="meetupDate"
        class="flex items-center justify-between mb-4 bg-slate-50 p-3 rounded-lg border border-slate-100"
      >
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
      <div
        v-else
        class="bg-amber-50 border border-amber-100 p-3 rounded-lg mb-4 flex flex-col items-center justify-center text-center"
      >
        <span class="text-xs uppercase font-black text-amber-600 tracking-widest mb-1">{{ t('meetup.planningPhaseTitle') }}</span>
        <span class="text-sm font-bold text-amber-800">{{ t('meetup.datesTbd') }}</span>
        <span class="text-[10px] text-amber-600 mt-1">{{ t('meetup.useCommentsToDiscuss') }}</span>
      </div>

      <div class="flex flex-col gap-2 mb-4">
        <div
          v-if="participants.length > 0"
          v-memo="[participants.length, showParticipantsDialog]"
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

      <Dialog
        v-model:visible="showCommentsDialog"
        :header="`${t('meetup.comments')}: ${meetup.title}`"
        :modal="true"
        class="w-full max-w-lg"
        dismissable-mask
      >
        <MeetupCommentsDialog
          :meetup-id="meetup.id"
          :meetup-title="meetup.title"
          @comment-added="emit('comment-added')"
        />
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
      <div class="flex gap-2 justify-between items-center">
        <Button
          severity="secondary"
          variant="text"
          class="p-button-sm"
          @click="openComments"
        >
          <OverlayBadge
            v-if="localUnreadCount > 0"
            :value="localUnreadCount"
            severity="danger"
            size="small"
          >
            <i class="pi pi-comments mr-2 text-lg" />
          </OverlayBadge>
          <i
            v-else
            class="pi pi-comments mr-2 text-lg"
          />
          <span class="font-bold">{{ t('meetup.comments') }}</span>
        </Button>

        <div class="flex items-center gap-2">
          <template v-if="!isRsvpLocked">
            <Button
              v-if="myRsvp !== RsvpStatus.GOING"
              icon="pi pi-check"
              :label="t('meetup.going')"
              class="p-button-primary p-button-sm shadow-sm"
              @click="handleRsvp(RsvpStatus.GOING)"
            />
            <Button
              v-else
              icon="pi pi-times"
              :label="t('meetup.notGoing')"
              class="p-button-secondary p-button-sm shadow-sm"
              @click="handleRsvp(RsvpStatus.NOT_GOING)"
            />
          </template>
          <template v-else-if="rsvpDeadline">
            <Tag
              v-if="myRsvp === RsvpStatus.GOING"
              :value="t('meetup.youAreGoing')"
              severity="secondary"
              rounded
            />
            <Tag
              v-else-if="myRsvp === RsvpStatus.NOT_GOING"
              :value="t('meetup.youAreNotGoing')"
              severity="secondary"
              rounded
            />
            <Tag
              v-else
              :value="t('meetup.registrationClosed')"
              severity="secondary"
              rounded
            />
          </template>

          <template v-if="canEdit">
            <Button
              v-tooltip.top="t('app.actions')"
              type="button"
              icon="pi pi-ellipsis-v"
              severity="secondary"
              variant="text"
              class="p-button-sm"
              @click="menu.toggle($event)"
            />
            <Menu
              ref="menu"
              :model="menuItems"
              :popup="true"
              class="shadow-xl border-slate-100"
            />
          </template>
        </div>
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
