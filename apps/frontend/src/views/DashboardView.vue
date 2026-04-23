<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

const toast = useToast();
const confirm = useConfirm();
const courses = ref<any[]>([]);
const notifications = ref<any[]>([]);
const isTrainer = authStore.isTrainer();

const courseDialog = ref(false);
const participantsDialog = ref(false);
const selectedCourse = ref<any>(null);

const courseForm = ref({
    id: null as number | null,
    title: '',
    description: '',
    capacity: 10,
    startTime: null as any,
    durationMinutes: 60
});

async function fetchData() {
    const response = await api.get('/courses');
    if (isTrainer) {
        courses.value = response.data.filter((c: any) => c.trainer.email === authStore.user?.email);
        fetchNotifications();
    } else {
        courses.value = response.data.filter((c: any) => c.bookings.some((b: any) => b.member.email === authStore.user?.email));
    }
}

async function fetchNotifications() {
    try {
        const response = await api.get('/notifications');
        notifications.value = response.data;
    } catch (e) {}
}

function openNewCourse() {
    courseForm.value = { id: null, title: '', description: '', capacity: 10, startTime: null, durationMinutes: 60 };
    courseDialog.value = true;
}

function editCourse(course: any) {
    courseForm.value = {
        id: course.id,
        title: course.title,
        description: course.description,
        capacity: course.capacity,
        startTime: new Date(course.startTime),
        durationMinutes: course.durationMinutes || 60
    };
    courseDialog.value = true;
}

async function saveCourse() {
    try {
        const payload = {
            ...courseForm.value,
            startTime: courseForm.value.startTime.toISOString()
        };
        
        if (courseForm.value.id) {
            await api.patch(`/courses/${courseForm.value.id}`, payload);
            toast.add({ severity: 'success', summary: 'Updated', detail: 'Course updated', life: 3000 });
        } else {
            await api.post('/courses', payload);
            toast.add({ severity: 'success', summary: 'Created', detail: 'Course created', life: 3000 });
        }
        courseDialog.value = false;
        fetchData();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Operation failed', life: 3000 });
    }
}

function confirmDeleteCourse(course: any) {
    confirm.require({
        message: 'Are you sure you want to delete this course?',
        header: 'Confirm Deletion',
        icon: 'pi pi-exclamation-triangle',
        accept: async () => {
            await api.delete(`/courses/${course.id}`);
            toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Course removed', life: 3000 });
            fetchData();
        }
    });
}

function showParticipants(course: any) {
    selectedCourse.value = course;
    participantsDialog.value = true;
}

async function removeParticipant(bookingId: number) {
    confirm.require({
        message: 'Are you sure you want to remove this participant?',
        header: 'Confirm Removal',
        icon: 'pi pi-user-minus',
        accept: async () => {
            try {
                await api.delete(`/courses/${selectedCourse.value.id}/bookings/${bookingId}`);
                toast.add({ severity: 'info', summary: 'Removed', detail: 'Participant removed', life: 3000 });
                fetchData().then(() => {
                    selectedCourse.value = courses.value.find(c => c.id === selectedCourse.value.id);
                });
            } catch (e) {}
        }
    });
}

async function markAsRead(id: number) {
    await api.patch(`/notifications/${id}/read`);
    fetchNotifications();
}

onMounted(fetchData);
</script>

<template>
  <div class="dashboard-container">
    <div class="header-with-action">
        <h1>Dashboard</h1>
        <Button v-if="isTrainer" label="New Course" icon="pi pi-plus" @click="openNewCourse" />
    </div>
    
    <div v-if="isTrainer" class="trainer-layout">
        <div class="main-content">
            <section class="courses-section">
                <h2>My Managed Courses</h2>
                <DataTable :value="courses" responsiveLayout="stack" breakpoint="960px" class="shadow-1">
                    <Column field="title" header="Course Title"></Column>
                    <Column header="Time">
                        <template #body="slotProps">
                            {{ new Date(slotProps.data.startTime).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) }}
                        </template>
                    </Column>
                    <Column header="Capacity">
                        <template #body="slotProps">
                            <span :class="{'text-red-500 font-bold': slotProps.data.bookings.length >= slotProps.data.capacity}">
                                {{ slotProps.data.bookings.length }} / {{ slotProps.data.capacity }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Actions">
                        <template #body="slotProps">
                            <div class="flex gap-2">
                                <Button icon="pi pi-users" class="p-button-text p-button-info" @click="showParticipants(slotProps.data)" v-tooltip="'Participants'" />
                                <Button icon="pi pi-pencil" class="p-button-text p-button-warning" @click="editCourse(slotProps.data)" />
                                <Button icon="pi pi-trash" class="p-button-text p-button-danger" @click="confirmDeleteCourse(slotProps.data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </section>
        </div>

        <aside class="notifications-panel">
            <h2>Activity</h2>
            <div class="notif-list shadow-1 rounded">
                <div v-for="notif in notifications" :key="notif.id" :class="['notif-item', { unread: !notif.isRead }]">
                    <div class="notif-content">
                        <p>{{ notif.message }}</p>
                        <small>{{ new Date(notif.createdAt).toLocaleTimeString() }}</small>
                    </div>
                    <Button v-if="!notif.isRead" icon="pi pi-check" class="p-button-text p-button-sm" @click="markAsRead(notif.id)" />
                </div>
                <div v-if="notifications.length === 0" class="text-center p-4 text-gray-500">No recent activity</div>
            </div>
        </aside>
    </div>
    
    <div v-else class="member-layout">
        <section class="bookings-section">
            <h2>My Bookings</h2>
            <div v-if="courses.length === 0" class="empty-state shadow-1">
                <i class="pi pi-calendar-minus" style="font-size: 3rem"></i>
                <p class="text-xl">You haven't booked any courses yet.</p>
                <Button label="Browse Schedule" icon="pi pi-calendar" @click="$router.push('/')" class="mt-4" />
            </div>
            <div v-else class="bookings-grid">
                <Card v-for="course in courses" :key="course.id" class="booking-card">
                    <template #title>{{ course.title }}</template>
                    <template #content>
                        <div class="flex flex-column gap-3">
                            <div class="flex align-items-center gap-2">
                                <i class="pi pi-user text-primary"></i>
                                <span>{{ course.trainer.name }}</span>
                            </div>
                            <div class="flex align-items-center gap-2">
                                <i class="pi pi-clock text-primary"></i>
                                <span>{{ new Date(course.startTime).toLocaleString() }}</span>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </section>
    </div>

    <!-- Course Form Dialog -->
    <Dialog v-model:visible="courseDialog" :header="courseForm.id ? 'Edit Course' : 'Create Course'" :modal="true" class="w-full max-w-lg">
        <div class="p-4">
            <div class="field">
                <label for="title">Title</label>
                <InputText id="title" v-model="courseForm.title" required />
            </div>
            <div class="field">
                <label for="description">Description</label>
                <Textarea id="description" v-model="courseForm.description" rows="3" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="field">
                    <label for="capacity">Capacity</label>
                    <InputNumber id="capacity" v-model="courseForm.capacity" showButtons :min="1" />
                </div>
                <div class="field">
                    <label for="duration">Duration (Min)</label>
                    <InputNumber id="duration" v-model="courseForm.durationMinutes" showButtons :min="15" :step="15" />
                </div>
            </div>
            <div class="field">
                <label for="startTime">Start Time</label>
                <DatePicker id="startTime" v-model="courseForm.startTime" showTime hourFormat="24" :stepMinute="15" fluid />
            </div>
        </div>
        <template #footer>
            <div class="flex justify-content-end gap-2 p-2">
                <Button label="Cancel" icon="pi pi-times" variant="text" @click="courseDialog = false" />
                <Button label="Save Course" icon="pi pi-check" @click="saveCourse" />
            </div>
        </template>
    </Dialog>

    <!-- Participants Dialog -->
    <Dialog v-model:visible="participantsDialog" :header="'Participants: ' + selectedCourse?.title" :modal="true" class="w-full max-w-xl">
        <DataTable :value="selectedCourse?.bookings" class="mt-4">
            <Column header="Member Name">
                <template #body="slotProps">
                    {{ slotProps.data.member.name }}
                </template>
            </Column>
            <Column header="Email">
                <template #body="slotProps">
                    {{ slotProps.data.member.email }}
                </template>
            </Column>
            <Column header="Actions" class="text-right">
                <template #body="slotProps">
                    <Button icon="pi pi-user-minus" severity="danger" variant="text" @click="removeParticipant(slotProps.data.id)" />
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
    padding: 2rem 1rem;
}

.header-with-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2.5rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 1rem;
    h1 { margin: 0; font-size: 2.5rem; }
}

.trainer-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 3rem;
}

@media (max-width: 1024px) {
    .trainer-layout {
        grid-template-columns: 1fr;
    }
}

.notif-list {
    background: white;
    border: 1px solid #e2e8f0;
    max-height: 600px;
    overflow-y: auto;
    border-radius: 8px;
}

.notif-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    &.unread {
        background-color: #f0f9ff;
        border-left: 4px solid var(--accent-color);
    }
    p { margin: 0; font-size: 0.95rem; line-height: 1.4; }
    small { color: #64748b; margin-top: 0.25rem; display: block; }
}

.empty-state {
    text-align: center;
    padding: 5rem 2rem;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    i { color: #cbd5e1; margin-bottom: 1.5rem; }
    p { color: #64748b; }
}

.bookings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}

.booking-card {
    border-left: 4px solid var(--accent-color);
    transition: transform 0.2s ease;
    &:hover { transform: translateY(-3px); }
}
</style>
