<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';
import api from '../services/api';

const toast = useToast();
const user = ref({ name: '', email: '', id: null });
const loading = ref(false);
const fetching = ref(true);

async function fetchProfile() {
    try {
        const response = await api.get('/user/me');
        user.value = response.data;
        // Sync back to authStore just in case
        authStore.user = {
            ...authStore.user,
            name: response.data.name,
            email: response.data.email
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load profile', life: 5000 });
    } finally {
        fetching.value = false;
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
      </div>

      <div class="md:col-span-1">
        <div class="phoenix-card text-center flex flex-col items-center">
          <div class="w-24 h-24 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4 border-2 border-amber-400 p-1">
            <div class="w-full h-full bg-white rounded-full flex items-center justify-center">
              <i class="pi pi-user text-3xl" />
            </div>
          </div>
          <h2 class="text-xl font-bold text-slate-900">
            {{ user.name }}
          </h2>
          <span class="inline-block mt-2 px-3 py-1 bg-amber-100 text-amber-800 text-xs font-black rounded-full tracking-widest uppercase">
            {{ authStore.isTrainer() ? 'Trainer' : 'Member' }}
          </span>

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
</style>
