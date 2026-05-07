<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { useRouter } from 'vue-router';
import api from '../services/api';

const toast = useToast();
const confirm = useConfirm();
const router = useRouter();
const user = ref({ name: '', email: '', id: null, roles: [] as string[], profilePicture: null });
const loading = ref(false);
const fetching = ref(true);
const uploading = ref(false);
const deleting = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const profilePictureUrl = computed(() => {
  if (user.value.profilePicture && user.value.id) {
    return `${import.meta.env.VITE_API_URL}/user/profile-picture/${user.value.id}?t=${user.value.profilePicture}`;
  }
  return null;
});

async function fetchProfile() {
    try {
        const response = await api.get('/user/me');
        user.value = response.data;
        // Sync back to authStore just in case
        authStore.user = {
            ...authStore.user,
            ...response.data
        } as any;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load profile', life: 5000 });
    } finally {
        fetching.value = false;
    }
}

function confirmDelete() {
    confirm.require({
        message: 'Are you sure you want to delete your account? This action is permanent and all your data (including bookings) will be lost.',
        header: 'Delete Account',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectProps: {
          label: 'Cancel',
          severity: 'secondary',
          text: true
        },
        accept: () => {
            deleteAccount();
        }
    });
}

async function deleteAccount() {
    deleting.value = true;
    try {
        await api.delete('/user/me');
        toast.add({ severity: 'success', summary: 'Deleted', detail: 'Your account has been deleted.', life: 5000 });
        await authStore.logout();
        router.push('/login');
    } catch (e: any) {
        const message = e.response?.data?.error || 'Failed to delete account';
        toast.add({ severity: 'error', summary: 'Error', detail: message, life: 7000 });
    } finally {
        deleting.value = false;
    }
}

function triggerFileUpload() {
  fileInput.value?.click();
}

async function handleFileUpload(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  uploading.value = true;
  try {
    const response = await api.post('/user/profile-picture', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    // Update local state and authStore
    user.value.profilePicture = response.data.profilePicture;
    if (authStore.user) {
      authStore.user.profilePicture = response.data.profilePicture;
    }

    toast.add({ severity: 'success', summary: 'Success', detail: 'Profile picture updated', life: 5000 });
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Upload failed', life: 5000 });
  } finally {
    uploading.value = false;
    if (target) target.value = ''; // Reset input
  }
}

async function updateProfile() {
    loading.value = true;
    try {
        await api.patch('/user/me', {
            name: user.value.name
        });
        authStore.user = {
            ...authStore.user,
            name: user.value.name
        };
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Profile saved successfully', life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Update failed', life: 5000 });
    } finally {
        loading.value = false;
    }
}

onMounted(fetchProfile);
</script>

<template>
  <div class="max-w-4xl mx-auto py-12 px-4">
    <div class="mb-10">
      <h1 class="text-4xl font-extrabold tracking-tight">
        Athlete Profile
      </h1>
      <p class="text-slate-600 mt-2 font-medium">
        Manage your personal information and preferences
      </p>
    </div>

    <div
      v-if="fetching"
      class="flex justify-center py-20"
    >
      <i class="pi pi-spin pi-spinner text-4xl text-amber-400" />
    </div>

    <div
      v-else
      class="grid grid-cols-1 md:grid-cols-3 gap-8"
    >
      <div class="md:col-span-2">
        <div class="phoenix-card">
          <form
            class="flex flex-col gap-6"
            @submit.prevent="updateProfile"
          >
            <div class="flex flex-col">
              <label class="form-label-base">Full Name</label>
              <InputText v-model="user.name" />
            </div>

            <div class="flex flex-col">
              <label class="form-label-base">Email Address</label>
              <InputText
                v-model="user.email"
                type="email"
                disabled
                class="opacity-70 cursor-not-allowed"
              />
              <small class="text-slate-400 mt-1">Email cannot be changed</small>
            </div>

            <div class="pt-4">
              <Button
                severity="primary"
                type="submit"
                label="Save Changes"
                :loading="loading"
                class="btn-primary"
              />
            </div>
          </form>
        </div>

        <div class="phoenix-card mt-8 border-red-100 bg-red-50/30">
          <h3 class="text-red-600 font-bold uppercase tracking-wider text-sm mb-4">Danger Zone</h3>
          <p class="text-slate-600 text-sm mb-6">
            Deleting your account will permanently remove all your data, including your profile picture and bookings. This action cannot be undone.
          </p>
          <Button
            label="Delete My Account"
            severity="danger"
            outlined
            icon="pi pi-trash"
            :loading="deleting"
            @click="confirmDelete"
          />
        </div>
      </div>

      <div class="md:col-span-1">
        <div class="phoenix-card text-center flex flex-col items-center">
          <div class="profile-image-container mb-4">
            <img v-if="profilePictureUrl" :src="profilePictureUrl" alt="Profile" class="profile-image-large" />
            <div v-else class="profile-image-placeholder">
              <i class="pi pi-user text-3xl" />
            </div>
          </div>

          <input
            ref="fileInput"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleFileUpload"
          >

          <Button
            label="Change Picture"
            icon="pi pi-camera"
            severity="secondary"
            text
            size="small"
            class="mb-4"
            :loading="uploading"
            @click="triggerFileUpload"
          />

          <h2 class="text-xl font-bold text-slate-900">
            {{ user.name }}
          </h2>
          <div class="flex flex-wrap gap-2 justify-center mt-3">
            <span
              v-for="role in user.roles.filter(r => r !== 'ROLE_USER')"
              :key="role"
              class="px-3 py-1 bg-amber-100 text-amber-800 text-[10px] font-black rounded-full tracking-widest uppercase"
            >
              {{ role.replace('ROLE_', '') }}
            </span>
          </div>

          <div class="mt-8 w-full pt-6 border-t border-slate-100">
            <div class="flex justify-between text-sm mb-2">
              <span class="text-slate-500 font-bold uppercase tracking-tighter">Status</span>
              <span class="text-slate-900 font-bold uppercase">Active</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.profile-image-container {
  width: 6rem;
  height: 6rem;
  background-color: #f1f5f9;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  border: 2px solid #fbbf24;
  padding: 0.25rem;
  overflow: hidden;
}

.profile-image-large {
  width: 100%;
  height: 100%;
  border-radius: 9999px;
  object-fit: cover;
}

.profile-image-placeholder {
  width: 100%;
  height: 100%;
  background-color: white;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
