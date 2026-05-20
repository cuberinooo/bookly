<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useAuthStore } from '../store/useAuthStore';
import api from '../services/api';

import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';

const props = defineProps<{
    visible: boolean;
    course: any;
}>();

const emit = defineEmits(['update:visible', 'remove-participant']);

const confirm = useConfirm();
const toast = useToast();
const authStore = useAuthStore();
const profileHashes = ref<Record<number, string>>({});
const emergencyInfo = ref<any>(null);
const showEmergencyDialog = ref(false);
const loadingEmergency = ref(false);

const isTrainerOrAdmin = computed(() => authStore.isTrainer || authStore.isAdmin);

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

function confirmEmergencyAccess(user: any) {
    confirm.require({
        message: 'GDPR WARNING: You are about to access sensitive emergency contact info. This action IS LOGGED for compliance. Use this ONLY if a medical emergency has occurred during this session.',
        header: 'Sensitive Data Access',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Access & Log Action',
        acceptClass: 'p-button-danger',
        rejectProps: {
          label: 'Cancel',
          severity: 'secondary',
        },
        accept: () => {
            fetchEmergencyInfo(user);
        }
    });
}

async function fetchEmergencyInfo(user: any) {
    loadingEmergency.value = true;
    try {
        const response = await api.get(`/user/${user.id}/emergency-contact`);
        emergencyInfo.value = {
            ...response.data,
            userName: user.name
        };
        showEmergencyDialog.value = true;
    } catch (e: any) {
        const msg = e.response?.data?.error || 'Failed to fetch info';
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 5000 });
    } finally {
        loadingEmergency.value = false;
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
                <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 border border-slate-200 transition-transform duration-200 ease-out hover:scale-125 hover:z-10 hover:shadow-lg hover:border-amber-400 cursor-pointer group relative">
                  <img
                    v-if="getProfilePictureUrl(slotProps.data.user)"
                    :src="getProfilePictureUrl(slotProps.data.user)"
                    alt="Profile"
                    class="w-full h-full object-cover"
                  >
                  <div
                    v-else
                    class="w-full h-full bg-slate-50 flex items-center justify-center"
                  >
                    <i class="pi pi-user text-slate-400 text-base" />
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
            header="Actions"
            class="text-right"
          >
            <template #body="slotProps">
              <div class="flex justify-end gap-2">
                <Button
                  v-if="isTrainerOrAdmin && !isAnonymized(slotProps.data.user.name)"
                  v-tooltip="'Emergency Info'"
                  icon="pi pi-shield"
                  severity="warn"
                  variant="text"
                  class="action-btn"
                  @click="confirmEmergencyAccess(slotProps.data.user)"
                />
                <Button
                  v-if="$attrs['onRemoveParticipant']"
                  v-tooltip="'Remove Member'"
                  icon="pi pi-user-minus"
                  severity="danger"
                  variant="text"
                  class="action-btn delete-btn"
                  @click="$emit('remove-participant', slotProps.data.id)"
                />
              </div>
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
                <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 border border-slate-200 transition-transform duration-200 ease-out hover:scale-125 hover:z-10 hover:shadow-lg hover:border-amber-400 cursor-pointer group relative">
                  <img
                    v-if="getProfilePictureUrl(slotProps.data.user)"
                    :src="getProfilePictureUrl(slotProps.data.user)"
                    alt="Profile"
                    class="w-full h-full object-cover"
                  >
                  <div
                    v-else
                    class="w-full h-full bg-slate-50 flex items-center justify-center"
                  >
                    <i class="pi pi-user text-slate-400 text-base" />
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
            header="Actions"
            class="text-right"
          >
            <template #body="slotProps">
              <div class="flex justify-end gap-2">
                <Button
                  v-if="isTrainerOrAdmin && !isAnonymized(slotProps.data.user.name)"
                  v-tooltip="'Emergency Info'"
                  icon="pi pi-shield"
                  severity="warn"
                  variant="text"
                  class="action-btn"
                  @click="confirmEmergencyAccess(slotProps.data.user)"
                />
                <Button
                  v-if="$attrs['onRemoveParticipant']"
                  v-tooltip="'Remove Member'"
                  icon="pi pi-user-minus"
                  severity="danger"
                  variant="text"
                  class="action-btn delete-btn"
                  @click="$emit('remove-participant', slotProps.data.id)"
                />
              </div>
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

    <Dialog
      v-model:visible="showEmergencyDialog"
      header="EMERGENCY CONTACT INFO"
      :modal="true"
      class="w-full max-w-sm"
    >
      <div
        v-if="emergencyInfo"
        class="flex flex-col gap-6 py-4"
      >
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Athlete</label>
          <span class="text-xl font-bold">{{ emergencyInfo.userName }}</span>
        </div>
        <div
          v-if="emergencyInfo.phoneNumber"
          class="flex flex-col gap-1"
        >
          <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Phone</label>
          <a
            :href="'tel:' + emergencyInfo.phoneNumber"
            class="text-xl font-black text-slate-900 flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100"
          >
            <i class="pi pi-phone text-amber-500" />
            {{ emergencyInfo.phoneNumber }}
          </a>
        </div>

        <Divider />

        <div class="flex flex-col gap-4">
          <h4 class="text-sm font-black !text-red-500 uppercase tracking-tighter">
            Primary Contact
          </h4>
          <div
            v-if="emergencyInfo.emergencyContactName"
            class="flex flex-col gap-1"
          >
            <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Name</label>
            <span class="font-bold">{{ emergencyInfo.emergencyContactName }}</span>
          </div>
          <div
            v-if="emergencyInfo.emergencyContactPhone"
            class="flex flex-col gap-1"
          >
            <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Phone</label>
            <a
              :href="'tel:' + emergencyInfo.emergencyContactPhone"
              class="text-xl font-black text-slate-900 flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-100"
            >
              <i class="pi pi-phone text-amber-500" />
              {{ emergencyInfo.emergencyContactPhone }}
            </a>
          </div>
          <div
            v-if="!emergencyInfo.emergencyContactName && !emergencyInfo.emergencyContactPhone"
            class="text-slate-400 italic text-sm"
          >
            No emergency contact provided by athlete.
          </div>
        </div>
      </div>
      <template #footer>
        <Button
          label="Close"
          severity="primary"
          class="w-full"
          @click="showEmergencyDialog = false"
        />
      </template>
    </Dialog>
  </Dialog>
</template>

<style lang="scss" scoped>
.section-title {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    line-height: 1.25rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: -0.05em;
    color: #334155;
    margin-bottom: 1rem;
    font-family: 'Barlow Condensed', sans-serif;
}

.participants-table {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    overflow: hidden;

    :deep(.p-datatable-thead > tr > th) {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 0.75rem;
        line-height: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        padding: 1rem;
    }
}

.waitlist-badge {
    padding: 0.25rem 0.5rem;
    background-color: #fef3c7;
    color: #b45309;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    line-height: 1rem;
    font-weight: 900;
    font-family: 'Barlow Condensed', sans-serif;
}

.empty-squad {
    padding-top: 3rem;
    padding-bottom: 3rem;
    text-align: center;
    color: #94a3b8;
    display: flex;
    flex-direction: column;
    align-items: center;

    p {
      font-weight: 700;
      text-transform: uppercase;
      font-size: 0.875rem;
      line-height: 1.25rem;
      letter-spacing: -0.025em;
      font-family: 'Barlow Condensed', sans-serif;
    }
}

.action-btn {
    color: #64748b;
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 200ms;

    &:hover {
      color: #f59e0b;
      background-color: #fffbeb;
    }

    &.delete-btn:hover {
      color: #ef4444;
      background-color: #fef2f2;
    }
}

h3 {
  color: var(--primary-color) !important;
}
</style>
