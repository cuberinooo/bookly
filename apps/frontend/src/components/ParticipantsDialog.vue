<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    visible: boolean;
    course: any;
}>();

const emit = defineEmits(['update:visible', 'remove-participant']);

const confirmedParticipants = computed(() => {
    return props.course?.bookings.filter((b: any) => !b.isWaitlist) || [];
});

const waitlistParticipants = computed(() => {
    return props.course?.bookings.filter((b: any) => b.isWaitlist).sort((a: any, b: any) => new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime()) || [];
});

function isAnonymized(name: string) {
    return name?.startsWith('Athlete #');
}

function close() {
    emit('update:visible', false);
}
</script>

<template>
    <Dialog :visible="visible" @update:visible="close" :header="'Squad List: ' + course?.title" :modal="true" class="w-full max-w-xl squad-dialog">
        <div class="dialog-content-wrapper p-4">
            <div v-if="confirmedParticipants.length > 0" class="participant-section">
                <h3 class="section-title"><i class="pi pi-check-circle text-green-500 mr-2"></i>Confirmed Athletes</h3>
                <DataTable :value="confirmedParticipants" class="participants-table">
                    <Column header="Athlete">
                        <template #body="slotProps">
                            <div class="flex flex-col">
                                <span :class="['font-bold', isAnonymized(slotProps.data.member.name) ? 'text-slate-400' : 'text-slate-900']">
                                    {{ slotProps.data.member.name }}
                                </span>
                                <small v-if="slotProps.data.member.email">{{ slotProps.data.member.email }}</small>
                            </div>
                        </template>
                    </Column>
                    <Column header="Actions" class="text-right" v-if="$attrs['onRemoveParticipant']">
                        <template #body="slotProps">
                            <Button icon="pi pi-user-minus" severity="danger" variant="text" @click="$emit('remove-participant', slotProps.data.id)" v-tooltip="'Remove Member'" class="action-btn delete-btn" />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <div v-if="waitlistParticipants.length > 0" class="participant-section mt-8">
                <h3 class="section-title"><i class="pi pi-clock text-amber-500 mr-2"></i>Waitlist (Chronological)</h3>
                <DataTable :value="waitlistParticipants" class="participants-table waitlist">
                    <Column header="Athlete">
                        <template #body="slotProps">
                            <div class="flex flex-col">
                                <span :class="['font-bold', isAnonymized(slotProps.data.member.name) ? 'text-slate-400' : 'text-slate-900']">
                                    {{ slotProps.data.member.name }}
                                </span>
                                <small v-if="slotProps.data.member.email" class="text-slate-500">{{ slotProps.data.member.email }}</small>
                            </div>
                        </template>
                    </Column>
                    <Column header="Queue Pos">
                        <template #body="slotProps">
                            <span class="waitlist-badge">#{{ waitlistParticipants.indexOf(slotProps.data) + 1 }}</span>
                        </template>
                    </Column>
                    <Column header="Actions" class="text-right" v-if="$attrs['onRemoveParticipant']">
                        <template #body="slotProps">
                            <Button icon="pi pi-user-minus" severity="danger" variant="text" @click="$emit('remove-participant', slotProps.data.id)" v-tooltip="'Remove Member'" class="action-btn delete-btn" />
                        </template>
                    </Column>
                </DataTable>
            </div>

            <div v-if="confirmedParticipants.length === 0 && waitlistParticipants.length === 0" class="empty-squad">
                <i class="pi pi-users text-4xl mb-4 opacity-20"></i>
                <p>No athletes have joined this squad yet.</p>
            </div>
        </div>
    </Dialog>
</template>

<style lang="scss" scoped>
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
</style>
