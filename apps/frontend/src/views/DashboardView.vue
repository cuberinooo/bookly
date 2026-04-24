<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import CourseForm from '../components/CourseForm.vue';

const toast = useToast();
const confirm = useConfirm();
const courses = ref<any[]>([]);
const notifications = ref<any[]>([]);
const isTrainer = authStore.isTrainer();

const courseDialog = ref(false);
const participantsDialog = ref(false);
const selectedCourse = ref<any>(null);
const editingCourse = ref<any>(null);
const submitting = ref(false);

async function fetchData() {
    const response = await api.get('/courses');
    if (isTrainer) {
        courses.value = response.data.filter((c: any) => c.trainer?.id === authStore.user?.id);
        fetchNotifications();
    } else {
        courses.value = response.data.filter((c: any) => c.bookings.some((b: any) => b.member?.id === authStore.user?.id));
    }
}

async function fetchNotifications() {
    try {
        const response = await api.get('/notifications');
        notifications.value = response.data;
    } catch (e) {}
}

function openNewCourse() {
    editingCourse.value = {
        title: 'Functional Training',
        capacity: 10,
        startTime: new Date(),
        durationMinutes: 60,
        description: ''
    };
    courseDialog.value = true;
}

function editCourse(course: any) {
    editingCourse.value = course;
    courseDialog.value = true;
}

async function onSaveCourse(formData: any) {
    submitting.value = true;
    try {
        if (editingCourse.value?.id) {
            await api.patch(`/courses/${editingCourse.value.id}`, formData);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Course updated', life: 3000 });
        } else {
            await api.post('/courses', formData);
            toast.add({ severity: 'success', summary: 'Created', detail: 'Course created', life: 3000 });
        }
        courseDialog.value = false;
        fetchData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Operation failed', life: 3000 });
    } finally {
        submitting.value = false;
    }
}

function confirmDeleteCourse(course: any) {
    confirm.require({
        message: `Delete "${course.title}"? This cannot be undone.`,
        header: 'Dangerous Action',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { severity: 'danger' },
        accept: async () => {
            await api.delete(`/courses/${course.id}`);
            toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Course removed', life: 3000 });
            fetchData();
        }
    });
}

async function unbookCourse(courseId: number) {
    confirm.require({
        message: 'Cancel this booking?',
        header: 'Confirmation',
        icon: 'pi pi-calendar-times',
        accept: async () => {
            try {
                await api.delete(`/courses/${courseId}/booking`);
                toast.add({ severity: 'info', summary: 'Cancelled', detail: 'Booking removed', life: 3000 });
                fetchData();
            } catch (e) {}
        }
    });
}

function formatDuration(min: number) {
    if (min < 60) return `${min}min`;
    const hours = Math.floor(min / 60);
    const remaining = min % 60;
    return remaining > 0 ? `${hours}h ${remaining}min` : `${hours} hour${hours > 1 ? 's' : ''}`;
}

onMounted(fetchData);
</script>

<template>
  <div class="dashboard-container">
    <div class="header-with-action">
        <div>
            <h1>Dashboard</h1>
            <p class="text-muted">Manage your athletic journey</p>
        </div>
        <Button v-if="isTrainer" label="New Course" icon="pi pi-plus" @click="openNewCourse" severity="primary" size="large" />
    </div>

    <div v-if="isTrainer" class="trainer-layout">
        <div class="main-content">
            <section class="section-card">
                <h2>Managed Courses</h2>
                <DataTable :value="courses" responsiveLayout="stack" breakpoint="960px" class="mt-4 custom-table">
                    <Column field="title" header="Course">
                        <template #body="slotProps">
                            <span class="course-title-cell">{{ slotProps.data.title }}</span>
                        </template>
                    </Column>
                    <Column header="Schedule">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="font-bold text-sm">{{ new Date(slotProps.data.startTime).toLocaleDateString() }}</span>
                                <span class="text-muted text-xs">{{ new Date(slotProps.data.startTime).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Duration">
                        <template #body="slotProps">
                            {{ formatDuration(slotProps.data.durationMinutes) }}
                        </template>
                    </Column>
                    <Column header="Slots">
                        <template #body="slotProps">
                            <div class="flex align-items-center gap-2">
                                <span :class="['slot-badge', { full: slotProps.data.bookings.length >= slotProps.data.capacity }]">
                                    {{ slotProps.data.bookings.length }} / {{ slotProps.data.capacity }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Actions" class="text-right">
                        <template #body="slotProps">
                            <div class="flex justify-content-end gap-2">
                                <Button icon="pi pi-users" variant="text" @click="selectedCourse = slotProps.data; participantsDialog = true" v-tooltip="'Participants'" class="action-btn" />
                                <Button icon="pi pi-pencil" variant="text" @click="editCourse(slotProps.data)" class="action-btn" />
                                <Button icon="pi pi-trash" variant="text" severity="danger" @click="confirmDeleteCourse(slotProps.data)" class="action-btn delete-btn" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </section>
        </div>

        <aside class="notifications-panel">
            <div class="panel-header">
                <h2>Live Feed</h2>
            </div>
            <div class="notif-list">
                <div v-for="notif in notifications" :key="notif.id" :class="['notif-item', { unread: !notif.isRead }]">
                    <p>{{ notif.message }}</p>
                    <div class="flex justify-content-between align-items-center mt-3">
                        <small>{{ new Date(notif.createdAt).toLocaleTimeString() }}</small>
                        <Button v-if="!notif.isRead" icon="pi pi-check" variant="text" size="small" @click="api.patch(`/notifications/${notif.id}/read`).then(fetchNotifications)" class="mark-read-btn" />
                    </div>
                </div>
                <div v-if="notifications.length === 0" class="empty-notifs">
                    <i class="pi pi-bell-slash"></i>
                    <p>No new alerts</p>
                </div>
            </div>
        </aside>
    </div>

    <div v-else class="member-layout">
        <section>
            <div class="pb-4">
                <h2>My Scheduled Bookings</h2>
                <Button class="pt-4" severity="primary" label="View Schedule" icon="pi pi-calendar" @click="$router.push('/')" variant="text" />
            </div>

            <div v-if="courses.length === 0" class="empty-state mt-4">
                <i class="pi pi-calendar-plus"></i>
                <p>Ready to train? Your schedule is empty.</p>
                <Button severity="primary" label="Explore Courses" icon="pi pi-search" @click="$router.push('/')" size="large" class="mt-4" />
            </div>

            <div v-else class="bookings-grid">
                <Card v-for="course in courses" :key="course.id" class="booking-card">
                    <template #title>
                        <div class="flex justify-content-between align-items-start">
                            <span>{{ course.title }}</span>
                            <span class="duration-tag">{{ formatDuration(course.durationMinutes) }}</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="flex flex-column gap-4 py-3">
                            <div class="info-row">
                                <i class="pi pi-user"></i>
                                <div>
                                    <label>TRAINER</label>
                                    <span>{{ course.trainer.name }}</span>
                                </div>
                            </div>
                            <div class="info-row">
                                <i class="pi pi-clock"></i>
                                <div>
                                    <label>TIME & DATE</label>
                                    <span>{{ new Date(course.startTime).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template #footer>
                        <Button label="CANCEL BOOKING" severity="danger" variant="text" icon="pi pi-times" class="w-full cancel-btn" @click="unbookCourse(course.id)" />
                    </template>
                </Card>
            </div>
        </section>
    </div>

    <!-- Course Form Dialog -->
    <Dialog v-model:visible="courseDialog" :header="editingCourse?.id ? 'Modify Course' : 'Create New Course'" :modal="true" class="w-full max-w-lg">
        <CourseForm
            :course="editingCourse"
            :loading="submitting"
            @save="onSaveCourse"
            @cancel="courseDialog = false"
        />
    </Dialog>

    <!-- Participants Dialog -->
    <Dialog v-model:visible="participantsDialog" :header="'Participants: ' + selectedCourse?.title" :modal="true" class="w-full max-w-xl">
        <DataTable :value="selectedCourse?.bookings" class="mt-4 custom-table">
            <Column header="Member">
                <template #body="slotProps">
                    <div class="flex flex-column">
                        <span class="font-bold text-header">{{ slotProps.data.member.name }}</span>
                        <small class="text-muted">{{ slotProps.data.member.email }}</small>
                    </div>
                </template>
            </Column>
            <Column header="Joined On">
                <template #body="slotProps">
                    {{ new Date(slotProps.data.createdAt).toLocaleDateString() }}
                </template>
            </Column>
            <Column header="Actions" class="text-right">
                <template #body="slotProps">
                    <Button icon="pi pi-user-minus" severity="danger" variant="text" @click="removeParticipant(slotProps.data.id)" v-tooltip="'Remove Member'" />
                </template>
            </Column>
        </DataTable>
    </Dialog>
  </div>
</template>

<style scoped lang="scss">
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 0;
}

.header-with-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4rem;
    h1 { margin: 0; font-size: 3.5rem; letter-spacing: -0.02em; }
    p { font-size: 1.1rem; font-weight: 500; }
}

.section-card {
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-md);
}

.trainer-layout {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 3rem;
}

@media (max-width: 1200px) {
    .trainer-layout { grid-template-columns: 1fr; }
}

.notif-list {
    background: #f8fafc;
    border: 1px solid var(--border-color);
    max-height: 700px;
    overflow-y: auto;
    border-radius: 16px;
}

.notif-item {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    background: white;
    transition: all 0.2s;

    &:last-child { border-bottom: none; }

    &.unread {
        background-color: #fffbeb;
        border-left: 6px solid var(--primary-color);
    }

    p { margin: 0; color: var(--text-header); font-weight: 600; line-height: 1.4; }
    small { color: var(--text-muted); font-family: 'Barlow Condensed', sans-serif; font-weight: 700; text-transform: uppercase; }
}

.mark-read-btn {
    color: var(--primary-color) !important;
    &:hover { background: #fef3c7 !important; }
}

.course-title-cell {
    font-family: 'Barlow Condensed', sans-serif;
    font-weight: 800;
    font-size: 1.1rem;
    color: var(--text-header);
    text-transform: uppercase;
}

.slot-badge {
    padding: 0.4rem 1rem;
    background: #f1f5f9;
    border-radius: 4px;
    font-size: 0.85rem;
    font-weight: 800;
    font-family: 'Barlow Condensed', sans-serif;
    color: #475569;

    &.full { background: #fee2e2; color: #ef4444; }
}

.duration-tag {
    background: #1e293b;
    color: var(--primary-color);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 900;
    font-family: 'Barlow Condensed', sans-serif;
    letter-spacing: 0.05em;
}

.booking-card {
    border-top: 4px solid var(--primary-color) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

    &:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }
}

.info-row {
    display: flex;
    align-items: center;
    gap: 1.25rem;

    i {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        border-radius: 10px;
        color: var(--primary-color);
        font-size: 1.1rem;
    }

    label {
        display: block;
        font-size: 0.65rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 2px;
    }

    span { font-weight: 700; color: var(--text-header); }
}

.bookings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 2.5rem;
}

.empty-state {
    text-align: center;
    padding: 8rem 2rem;
    background: #f8fafc;
    border-radius: 20px;
    border: 2px dashed #cbd5e1;

    i { color: #cbd5e1; font-size: 4rem; margin-bottom: 1.5rem; }
    p { font-size: 1.5rem; font-weight: 700; color: #64748b; }
}

.action-btn {
    color: #64748b !important;
    &:hover { color: var(--primary-color) !important; background: #fffbeb !important; }
}

.delete-btn {
    &:hover { color: #ef4444 !important; background: #fef2f2 !important; }
}

.cancel-btn {
    font-weight: 800 !important;
    letter-spacing: 0.1em !important;
    margin-top: 1rem;
    &:hover { background: #fef2f2 !important; }
}

.empty-notifs {
    padding: 4rem 2rem;
    text-align: center;
    color: #94a3b8;
    i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.5; }
    p { font-family: 'Barlow Condensed', sans-serif; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; }
}

.custom-table {
    :deep(.p-datatable-thead > tr > th) {
        background: #f8fafc;
        color: #475569;
        font-family: 'Barlow Condensed', sans-serif;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.05em;
        padding: 1.25rem 1rem;
    }

    :deep(.p-datatable-tbody > tr) {
        transition: background 0.2s;
        &:hover { background: #fdfdfd; }
    }
}
</style>
