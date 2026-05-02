<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import {authStore} from "../store/auth";

const toast = useToast();
const confirm = useConfirm();
const users = ref<any[]>([]);
const loading = ref(false);
const userDialog = ref(false);
const editingUser = ref<any>({ name: '', email: '', roles: ['ROLE_MEMBER'], password: '' });
const submitting = ref(false);
const submitted = ref(false);

const isEmailValid = (email: string) => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
};

const roleOptions = [
    { label: 'Member', value: 'ROLE_MEMBER' },
    { label: 'Trainer', value: 'ROLE_TRAINER' },
    { label: 'Admin', value: 'ROLE_ADMIN' },
    { label: 'Trial', value: 'ROLE_TRIAL' }
];

async function fetchUsers() {
    loading.value = true;
    try {
        const response = await api.get('/admin/users');
        users.value = response.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch users', life: 5000 });
    } finally {
        loading.value = false;
    }
}

function openNewUser() {
    editingUser.value = { name: '', email: '', roles: ['ROLE_MEMBER'], password: Math.random().toString(36).slice(-10) + 'A1!' };
    submitted.value = false;
    userDialog.value = true;
}

function editUser(user: any) {
    editingUser.value = {
        id: user.id,
        name: user.name,
        email: user.email,
        roles: [...user.roles].filter(r => r !== 'ROLE_USER') // Filter out internal base role
    };
    submitted.value = false;
    userDialog.value = true;
}

async function saveUser() {
    submitted.value = true;

    if (!editingUser.value.name || !editingUser.value.email || !isEmailValid(editingUser.value.email)) {
        return;
    }

    submitting.value = true;
    try {
        if (editingUser.value.id) {
            await api.patch(`/admin/users/${editingUser.value.id}`, {
                name: editingUser.value.name,
                roles: editingUser.value.roles
            });
            toast.add({ severity: 'success', summary: 'Updated', detail: 'User updated', life: 5000 });

            if (editingUser.value.id === authStore.user?.id) {
                // Refresh current user info to update roles in session
                const response = await api.get('/user/me');
                authStore.user = {
                    ...authStore.user,
                    ...response.data
                } as any;
            }
        } else {
            await api.post('/admin/users', editingUser.value);
            toast.add({ severity: 'success', summary: 'Created', detail: 'User created and email sent', life: 5000 });
        }
        userDialog.value = false;
        fetchUsers();
    } catch (e: any) {
        let detail = e.response?.data?.error || 'Operation failed';
        if (e.response?.status === 409 || detail === 'Email already registered') {
            detail = 'This email address is already in use by another athlete.';
        }
        toast.add({ severity: 'error', summary: 'Validation Error', detail: detail, life: 7000 });
    } finally {
        submitting.value = false;
    }
}

async function toggleActive(user: any) {
    try {
        await api.patch(`/admin/users/${user.id}/toggle-active`);
        user.isActive = !user.isActive;
        toast.add({ severity: 'success', summary: 'Status Updated', detail: `User ${user.isActive ? 'activated' : 'deactivated'}`, life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to toggle status', life: 5000 });
    }
}

async function deleteUser(user: any) {
    confirm.require({
        message: `Are you sure you want to delete ${user.name}?`,
        header: 'Delete Confirmation',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { severity: 'danger', label: 'Delete' },
        accept: async () => {
            try {
                const response = await api.delete(`/admin/users/${user.id}`);
                toast.add({ severity: 'info', summary: 'Result', detail: response.data.status, life: 5000 });
                fetchUsers();
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Deletion failed', life: 5000 });
            }
        }
    });
}

onMounted(fetchUsers);
</script>

<template>
  <div class="user-management mt-6">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold uppercase tracking-tight font-barlow">
        User Directory
      </h2>
      <Button
        label="Create User"
        icon="pi pi-user-plus"
        severity="primary"
        @click="openNewUser"
      />
    </div>

    <DataTable
      :value="users"
      :loading="loading"
      class="athletic-table"
      responsive-layout="stack"
    >
      <Column
        field="name"
        header="ATHLETE NAME"
        sortable
      >
        <template #body="{ data }">
          <div class="font-bold text-slate-900">
            {{ data.name }}
          </div>
          <div class="text-xs text-slate-500">
            {{ data.email }}
          </div>
        </template>
      </Column>
      <Column header="ROLES">
        <template #body="{ data }">
          <div class="flex flex-wrap gap-1">
            <Tag
              v-for="role in data.roles.filter(r => r !== 'ROLE_USER')"
              :key="role"
              :value="role.replace('ROLE_', '')"
              :severity="role === 'ROLE_ADMIN' ? 'danger' : (role === 'ROLE_TRAINER' ? 'warn' : (role === 'ROLE_TRIAL' ? 'secondary' : 'info'))"
            />
          </div>
        </template>
      </Column>
      <Column header="STATUS">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <ToggleSwitch
              :model-value="data.isActive"
              @update:model-value="toggleActive(data)"
            />
          </div>
        </template>
      </Column>
      <Column header="VERIFIED">
        <template #body="{ data }">
          <i
            class="pi"
            :class="data.isVerified ? 'pi-check-circle text-green-500' : 'pi-times-circle text-slate-300'"
          />
        </template>
      </Column>
      <Column
        header="ACTIONS"
        class="w-32"
      >
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button
              icon="pi pi-pencil"
              variant="text"
              rounded
              @click="editUser(data)"
            />
            <Button
              icon="pi pi-trash"
              variant="text"
              severity="danger"
              rounded
              @click="deleteUser(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <Dialog
      v-model:visible="userDialog"
      :header="editingUser.id ? 'Edit Athlete' : 'Onboard New Athlete'"
      :modal="true"
      class="w-full max-w-md"
    >
      <div class="flex flex-col gap-6 py-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm uppercase tracking-wider">Full Name</label>
          <InputText
            v-model="editingUser.name"
            placeholder="Name"
            :class="{ 'p-invalid': submitted && !editingUser.name }"
          />
          <small v-if="submitted && !editingUser.name" class="p-error">Name is required.</small>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm uppercase tracking-wider">Email Address</label>
          <InputText
            v-model="editingUser.email"
            :disabled="!!editingUser.id"
            placeholder="email@example.com"
            :class="{ 'p-invalid': submitted && (!editingUser.email || !isEmailValid(editingUser.email)) }"
          />
          <small v-if="submitted && !editingUser.email" class="p-error">Email is required.</small>
          <small v-else-if="submitted && !isEmailValid(editingUser.email)" class="p-error">Invalid email format.</small>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm uppercase tracking-wider">Roles</label>
          <MultiSelect
            v-model="editingUser.roles"
            :options="roleOptions"
            option-label="label"
            option-value="value"
            placeholder="Select Roles"
            class="w-full"
            display="chip"
          />
        </div>
        <div
          v-if="!editingUser.id"
          class="flex flex-col gap-2"
        >
          <label class="text-sm uppercase tracking-wider">Temporary Password</label>
          <InputText
            v-model="editingUser.password"
            placeholder="Temporary password"
          />
          <small class="italic">User will be forced to change this on first login.</small>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Cancel"
            severity="secondary"
            variant="text"
            @click="userDialog = false"
          />
          <Button
            label="Save User"
            severity="primary"
            :loading="submitting"
            @click="saveUser"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.font-barlow { font-family: 'Barlow Condensed', sans-serif; }
.p-error {
    color: var(--danger-color);
    font-size: 0.75rem;
    margin-top: -0.25rem;
}
</style>
