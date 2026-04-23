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
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch profile', life: 3000 });
    }
}

async function updateProfile() {
    loading.value = true;
    try {
        await api.patch('/user/me', { name: name.value });
        authStore.user!.name = name.value; // Update local store
        toast.add({ severity: 'success', summary: 'Success', detail: 'Profile updated', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Update failed', life: 3000 });
    } finally {
        loading.value = false;
    }
}

onMounted(fetchProfile);
</script>

<template>
    <div class="profile-container">
        <h1>Profile Settings</h1>
        <Card>
            <template #content>
                <div class="form-grid">
                    <div class="field">
                        <label for="email">Email (Cannot be changed)</label>
                        <InputText id="email" :value="authStore.user?.email" disabled class="w-full" />
                    </div>
                    <div class="field">
                        <label for="name">Full Name</label>
                        <InputText id="name" v-model="name" class="w-full" />
                    </div>
                    <Button label="Save Changes" @click="updateProfile" :loading="loading" class="mt-4" />
                </div>
            </template>
        </Card>
    </div>
</template>

<style scoped lang="scss">
.profile-container {
    max-width: 600px;
    margin: 0 auto;
}
.form-grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
</style>
