<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { settingsStore } from '../store/settings';
import { authStore } from '../store/auth';
import api from '../services/api';

const props = defineProps<{
    visible: boolean;
    course: any;
}>();

const emit = defineEmits(['update:visible', 'remove-participant']);

const profileHashes = ref<Record<number, string>>({});

const confirmedParticipants = computed(() => {
    return props.course?.bookings.filter((b: any) => !b.isWaitlist) || [];
});

const waitlistParticipants = computed(() => {
    return props.course?.bookings.filter((b: any) => b.isWaitlist).sort((a: any, b: any) => new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime()) || [];
});

watch(() => props.visible, (isVisible) => {
    if (isVisible && props.course?.bookings) {
        fetchProfilePictures();
    }
});

async function fetchProfilePictures() {
    const userIds = props.course.bookings.map((b: any) => b.user.id);
    if (userIds.length === 0) return;

    try {
        const response = await api.get('/user/profile-pictures', {
            params: { ids: userIds.join(',') }
        });
        profileHashes.value = response.data;
    } catch (e) {
        console.error('Failed to fetch profile pictures', e);
    }
}

function isAnonymized(name: string) {
    return name?.startsWith('Athlete #');
}

function getProfilePictureUrl(user: any) {
    if (isAnonymized(user.name)) {
        return null;
    }

    const hash = profileHashes.value[user.id];
    if (hash) {
        return `${import.meta.env.VITE_API_URL}/user/profile-picture/${user.id}?t=${hash}`;
    }
    return null;
}

function close() {
    emit('update:visible', false);
}
</script>
<template>
  <Dialog
    :visible="visible"
    :header="'Squad List: ' + course?.title"
    :modal="true"
    class="w-full max-w-xl squad-dialog"
    @update:visible="close"
  >
    <div class="dialog-content-wrapper p-4">
      <div
        v-if="confirmedParticipants.length > 0"
        class="participant-section"
      >
        <h3 class="section-title">
          <i class="pi pi-check-circle text-accent mr-2" />Confirmed Athletes
        </h3>
        <DataTable
          :value="confirmedParticipants"
          class="participants-table"
        >
          <Column header="Athlete">
            <template #body="slotProps">
              <div class="flex items-center gap-3">
                <div class="participant-avatar">
                  <img
                    v-if="getProfilePictureUrl(slotProps.data.user)"
                    :src="getProfilePictureUrl(slotProps.data.user)"
                    alt="Profile"
                    class="avatar-img"
                  />
                  <div v-else class="avatar-fallback">
                    <i class="pi pi-user text-slate-400" />
                  </div>
                </div>
                <div class="flex flex-col">
                  <span :class="['font-bold', isAnonymized(slotProps.data.user.name) ? 'text-slate-400' : 'text-slate-900']">
                    {{ slotProps.data.user.name }}
                  </span>
                  <small v-if="slotProps.data.user.email">{{ slotProps.data.user.email }}</small>
                </div>
              </div>
            </template>
          </Column>
          <Column
            v-if="$attrs['onRemoveParticipant']"
            header="Actions"
            class="text-right"
          >
            <template #body="slotProps">
              <Button
                v-tooltip="'Remove Member'"
                icon="pi pi-user-minus"
                severity="danger"
                variant="text"
                class="action-btn delete-btn"
                @click="$emit('remove-participant', slotProps.data.id)"
              />
            </template>
          </Column>
        </DataTable>
      </div>

      <div
        v-if="waitlistParticipants.length > 0"
        class="participant-section mt-8"
      >
        <h3 class="section-title">
          <i class="pi pi-clock text-amber-500 mr-2" />Waitlist (Chronological)
        </h3>
        <DataTable
          :value="waitlistParticipants"
          class="participants-table waitlist"
        >
          <Column header="Athlete">
            <template #body="slotProps">
              <div class="flex items-center gap-3">
                <div class="participant-avatar">
                  <img
                    v-if="getProfilePictureUrl(slotProps.data.user)"
                    :src="getProfilePictureUrl(slotProps.data.user)"
                    alt="Profile"
                    class="avatar-img"
                  />
                  <div v-else class="avatar-fallback">
                    <i class="pi pi-user text-slate-400" />
                  </div>
                </div>
                <div class="flex flex-col">
                  <span :class="['font-bold', isAnonymized(slotProps.data.user.name) ? 'text-slate-400' : 'text-slate-900']">
                    {{ slotProps.data.user.name }}
                  </span>
                  <small
                    v-if="slotProps.data.user.email"
                  >{{ slotProps.data.user.email }}</small>
                </div>
              </div>
            </template>
          </Column>
          <Column header="Queue Pos">
            <template #body="slotProps">
              <span class="waitlist-badge">#{{ waitlistParticipants.indexOf(slotProps.data) + 1 }}</span>
            </template>
          </Column>
          <Column
            v-if="$attrs['onRemoveParticipant']"
            header="Actions"
            class="text-right"
          >
            <template #body="slotProps">
              <Button
                v-tooltip="'Remove Member'"
                icon="pi pi-user-minus"
                severity="danger"
                variant="text"
                class="action-btn delete-btn"
                @click="$emit('remove-participant', slotProps.data.id)"
              />
            </template>
          </Column>
        </DataTable>
      </div>

      <div
        v-if="confirmedParticipants.length === 0 && waitlistParticipants.length === 0"
        class="empty-squad"
      >
        <i class="pi pi-users text-4xl mb-4 opacity-20" />
        <p>No athletes have joined this squad yet.</p>
      </div>
    </div>
  </Dialog>
</template>

<style lang="scss" scoped>
.participant-avatar {
    @apply w-8 h-8 rounded-full overflow-hidden flex-shrink-0 border border-slate-200 transition-transform duration-200 ease-out;
    
    &:hover {
        @apply scale-125 z-10 shadow-lg border-amber-400;
    }
    
    .avatar-img {
        @apply w-full h-full object-cover;
    }
    
    .avatar-fallback {
        @apply w-full h-full bg-slate-50 flex items-center justify-center;
        i { @apply text-base; }
    }
}

.section-title {
    @apply flex items-center text-sm font-black uppercase tracking-tighter text-slate-700 mb-4;
    font-family: 'Barlow Condensed', sans-serif;
}

.participants-table {
    @apply border border-slate-200 rounded-lg overflow-hidden;

    :deep(.p-datatable-thead > tr > th) {
        @apply bg-slate-50 text-slate-600 font-bold text-xs uppercase tracking-widest p-4;
    }
}

.waitlist-badge {
    @apply px-2 py-1 bg-amber-100 text-amber-700 rounded text-xs font-black;
    font-family: 'Barlow Condensed', sans-serif;
}

.empty-squad {
    @apply py-12 text-center text-slate-400 flex flex-col items-center;
    p { @apply font-bold uppercase text-sm tracking-tight; font-family: 'Barlow Condensed', sans-serif; }
}

.action-btn {
    @apply text-slate-500 transition-colors duration-200;
    &:hover { @apply text-amber-500 bg-amber-50; }
    &.delete-btn:hover { @apply text-red-500 bg-red-50; }
}

h3 {
  color: var(--primary-color) !important;
}
</style>
