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
                await api.delete(`/courses/${courseId}/book`);
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
        <Button v-if="isTrainer" label="Create New Course" icon="pi pi-plus" @click="openNewCourse" severity="primary" size="large" />
    </div>

    <div v-if="isTrainer" class="trainer-layout">
        <div class="main-content">
            <section class="section-card">
                <h2>Managed Courses</h2>
                <DataTable :value="courses" responsiveLayout="stack" breakpoint="960px" class="mt-4">
                    <Column field="title" header="Course"></Column>
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
                                <Button icon="pi pi-users" variant="text" @click="selectedCourse = slotProps.data; participantsDialog = true" v-tooltip="'Participants'" />
                                <Button icon="pi pi-pencil" variant="text" severity="warn" @click="editCourse(slotProps.data)" />
                                <Button icon="pi pi-trash" variant="text" severity="danger" @click="confirmDeleteCourse(slotProps.data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </section>
        </div>

        <aside class="notifications-panel">
            <h2>Live Feed</h2>
            <div class="notif-list shadow-1">
                <div v-for="notif in notifications" :key="notif.id" :class="['notif-item', { unread: !notif.isRead }]">
                    <p>{{ notif.message }}</p>
                    <div class="flex justify-content-between align-items-center mt-2">
                        <small>{{ new Date(notif.createdAt).toLocaleTimeString() }}</small>
                        <Button v-if="!notif.isRead" icon="pi pi-check" variant="text" size="small" @click="api.patch(`/notifications/${notif.id}/read`).then(fetchNotifications)" />
                    </div>
                </div>
                <div v-if="notifications.length === 0" class="text-center p-6 text-muted">
                    <i class="pi pi-inbox block mb-2 text-2xl"></i>
                    No alerts
                </div>
            </div>
        </aside>
    </div>

    <div v-else class="member-layout">
        <section>
            <h2>My Scheduled Bookings</h2>
            <div v-if="courses.length === 0" class="empty-state shadow-1 mt-4">
                <i class="pi pi-calendar-plus" style="font-size: 3rem"></i>
                <p class="text-xl">Ready to train? Your schedule is empty.</p>
                <Button label="View Course Schedule" icon="pi pi-calendar" @click="$router.push('/')" class="mt-4" />
            </div>
            <div v-else class="bookings-grid mt-4">
                <Card v-for="course in courses" :key="course.id" class="booking-card">
                    <template #title>
                        <div class="flex justify-content-between align-items-start">
                            <span>{{ course.title }}</span>
                            <span class="duration-tag">{{ formatDuration(course.durationMinutes) }}</span>
                        </div>
                    </template>
                    <template #content>
                        <div class="flex flex-column gap-3 py-2">
                            <div class="flex align-items-center gap-3">
                                <i class="pi pi-user p-2 bg-slate-100 border-circle text-primary"></i>
                                <div>
                                    <small class="block text-muted">TRAINER</small>
                                    <span class="font-bold">{{ course.trainer.name }}</span>
                                </div>
                            </div>
                            <div class="flex align-items-center gap-3">
                                <i class="pi pi-clock p-2 bg-slate-100 border-circle text-primary"></i>
                                <div>
                                    <small class="block text-muted">TIME & DATE</small>
                                    <span class="font-bold">{{ new Date(course.startTime).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template #footer>
                        <Button label="CANCEL BOOKING" severity="danger" variant="text" icon="pi pi-times" class="w-full" @click="unbookCourse(course.id)" />
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
        <DataTable :value="selectedCourse?.bookings" class="mt-4 border-1 border-slate-200">
            <Column header="Member">
                <template #body="slotProps">
                    <div class="flex flex-column">
                        <span class="font-bold">{{ slotProps.data.member.name }}</span>
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
    max-width: 1200px;
    margin: 0 auto;
    padding: 3rem 1rem;
}

.header-with-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 3rem;
    h1 { margin: 0; font-size: 3rem; }
    p { margin: 0; }
}

.section-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.trainer-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 3rem;
}

@media (max-width: 1100px) {
    .trainer-layout { grid-template-columns: 1fr; }
}

.notif-list {
    background: white;
    border: 1px solid var(--border-color);
    max-height: 600px;
    overflow-y: auto;
    border-radius: 12px;
}

.notif-item {
    padding: 1.5rem;
    border-bottom: 1px solid var(--bg-color);
    transition: background 0.2s;
    &:hover { background-color: var(--bg-color); }
    &.unread {
        background-color: rgba(249, 115, 22, 0.03);
        border-left: 4px solid var(--accent-color);
    }
    p { margin: 0; font-weight: 500; }
    small { color: var(--text-muted); font-weight: 600; }
}

.slot-badge {
    padding: 0.25rem 0.75rem;
    background: var(--bg-color);
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 700;
    &.full { background: #fee2e2; color: #ef4444; }
}

.duration-tag {
    background: var(--bg-color);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 800;
    color: var(--accent-color);
}

.booking-card {
    transition: transform 0.2s, box-shadow 0.2s;
    &:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
}

.bookings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 2rem;
}

.empty-state {
    text-align: center;
    padding: 6rem 2rem;
    background: white;
    border-radius: 16px;
    border: 2px dashed var(--border-color);
    i { color: #cbd5e1; margin-bottom: 2rem; }
}

.bg-slate-100 { background-color: var(--bg-color); }
</style>
