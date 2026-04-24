<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { authStore } from '../store/auth';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const name = ref('');
const loading = ref(false);

async function fetchProfile() {
    try {
        const response = await api.get('/user/me');
        name.value = response.data.name;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load profile', life: 3000 });
    }
}

async function updateProfile() {
    loading.value = true;
    try {
        await api.patch('/user/me', { name: name.value });
        if (authStore.user) authStore.user.name = name.value;
        toast.add({ severity: 'success', summary: 'Profile Updated', detail: 'Your changes have been saved', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Update Failed', detail: 'Please try again later', life: 3000 });
    } finally {
        loading.value = false;
    }
}

onMounted(fetchProfile);
</script>

<template>
    <div class="profile-page">
        <div class="header-section mb-8">
            <h1>Account Settings</h1>
            <p class="text-muted">Manage your personal information</p>
        </div>

        <div class="max-w-2xl mx-auto">
            <Card class="p-4 shadow-1">
                <template #content>
                    <div class="flex flex-col gap-3">
                        <div class="field">
                            <label for="email">Account Email</label>
                            <InputText id="email" :value="authStore.user?.email" disabled class="bg-slate-50 cursor-not-allowed opacity-70" />
                            <small class="text-muted mt-2 block">Email cannot be changed for security reasons.</small>
                        </div>

                        <div class="field">
                            <label for="name">Display Name</label>
                            <InputText id="name" v-model="name" placeholder="Enter your full name" />
                        </div>

                        <div class="border-t-1 border-slate-100">
                            <Button label="Save All Changes" icon="pi pi-check" @click="updateProfile" :loading="loading" severity="primary" size="large" />
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>

<style scoped lang="scss">
.profile-page {
    padding: 3rem 1rem;
}
.header-section {
    text-align: center;
    h1 { font-size: 3rem; margin-bottom: 0.5rem; }
}
.bg-slate-50 { background-color: #f8fafc !important; }
.mx-auto { margin-left: auto; margin-right: auto; }
</style>
