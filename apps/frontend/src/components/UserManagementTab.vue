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
const resettingPassword = ref(false);
const sendingWelcome = ref(false);

const getSortedRoles = (roles: string[]) => {
  const roleOrder = ['ROLE_ADMIN', 'ROLE_TRAINER', 'ROLE_MEMBER', 'ROLE_TRIAL'];
  return [...roles]
    .filter((r) => r !== 'ROLE_USER')
    .sort((a, b) => {
      const indexA = roleOrder.indexOf(a);
      const indexB = roleOrder.indexOf(b);
      const posA = indexA === -1 ? 99 : indexA;
      const posB = indexB === -1 ? 99 : indexB;
      return posA - posB;
    });
};

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

async function resetPassword() {
    if (!editingUser.value.id) return;

    confirm.require({
        message: `Are you sure you want to reset the password for ${editingUser.value.name}? A new temporary password will be emailed to them immediately.`,
        header: 'Reset Password',
        icon: 'pi pi-exclamation-lock',
        acceptProps: { severity: 'warn', label: 'Reset Password' },
        rejectProps: {
          label: 'Cancel',
          severity: 'secondary',
          text: true
        },
        accept: async () => {
            resettingPassword.value = true;
            try {
                await api.post(`/admin/users/${editingUser.value.id}/reset-password`);
                toast.add({
                    severity: 'success',
                    summary: 'Password Reset',
                    detail: 'A temporary password has been sent to the athlete.',
                    life: 5000
                });
            } catch (e) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to reset password', life: 5000 });
            } finally {
                resettingPassword.value = false;
            }
        }
    });
}

async function sendJoinUsMail(user: any) {
    sendingWelcome.value = true;
    try {
        await api.post(`/admin/users/${user.id}/send-join-us`);
        user.joinUsMailSent = true;
        toast.add({ severity: 'success', summary: 'Success', detail: 'Join us mail sent successfully', life: 5000 });
    } catch (e: any) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.error || 'Failed to send join us mail', life: 5000 });
    } finally {
        sendingWelcome.value = false;
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
        rejectProps: {
          label: 'Cancel',
          severity: 'secondary',
          text: true
        },
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

    <div class="hidden md:block">
      <DataTable
        :value="users"
        :loading="loading"
        class="athletic-table"
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
                v-for="role in getSortedRoles(data.roles)"
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
              :class="data.isVerified ? 'pi-check-circle text-accent' : 'pi-times-circle text-slate-300'"
            />
          </template>
        </Column>
        <Column header="MAIL">
          <template #body="{ data }">
            <div v-if="(data.roles.includes('ROLE_TRIAL'))">
              <i
                v-tooltip.top="data.joinUsMailSent ? 'Join us Mail Sent' : 'Not Sent'"
                class="pi"
                :class="data.joinUsMailSent ? 'pi-send text-accent' : 'pi-minus text-slate-300'"
              />
            </div>
          </template>
        </Column>
        <Column
          header="ACTIONS"
          class="w-48"
        >
          <template #body="{ data }">
            <div class="flex gap-2">
              <Button
                v-if="(data.roles.includes('ROLE_TRIAL') || data.roles.includes('ROLE_MEMBER')) && !data.joinUsMailSent"
                v-tooltip.top="'Send Welcome Mail'"
                icon="pi pi-envelope"
                variant="text"
                rounded
                :loading="sendingWelcome"
                @click="sendJoinUsMail(data)"
              />
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
    </div>

    <!-- Mobile Card Layout -->
    <div class="md:hidden flex flex-col gap-4">
      <div
        v-if="loading"
        class="flex justify-center py-8"
      >
        <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
      </div>
      <template v-else>
        <div
          v-for="user in users"
          :key="user.id"
          class="phoenix-card p-4 border border-slate-200"
        >
          <div class="flex justify-between items-start mb-4">
            <div>
              <h3 class="font-black text-lg text-slate-900 leading-tight">
                {{ user.name }}
              </h3>
              <p class="text-xs text-slate-500 font-medium mb-2">
                {{ user.email }}
              </p>

              <div class="flex flex-wrap gap-1 mb-2">
                <Tag
                  v-for="role in getSortedRoles(user.roles)"
                  :key="role"
                  :value="role.replace('ROLE_', '')"
                  :severity="role === 'ROLE_ADMIN' ? 'danger' : (role === 'ROLE_TRAINER' ? 'warn' : (role === 'ROLE_TRIAL' ? 'secondary' : 'info'))"
                  class="text-[10px] uppercase font-black"
                />
                <Tag
                  v-if="user.roles.includes('ROLE_TRIAL') && user.joinUsMailSent"
                  value="Welcome Sent"
                  severity="success"
                  class="text-[10px] uppercase font-black"
                  icon="pi pi-send"
                />
              </div>
            </div>
            <div class="flex items-center gap-2">
              <i
                v-tooltip.left="user.isVerified ? 'Verified' : 'Unverified'"
                class="pi"
                :class="user.isVerified ? 'pi-check-circle text-accent' : 'pi-times-circle text-slate-300'"
              />
            </div>
          </div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-100 mt-2">
            <div class="flex items-center gap-3">
              <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Active Status</span>
              <ToggleSwitch
                :model-value="user.isActive"
                @update:model-value="toggleActive(user)"
              />
            </div>
            <div class="flex gap-1">
              <Button
                v-if="user.roles.includes('ROLE_TRIAL') && !user.joinUsMailSent"
                icon="pi pi-envelope"
                severity="secondary"
                variant="text"
                rounded
                :loading="sendingWelcome"
                @click="sendJoinUsMail(user)"
              />
              <Button
                icon="pi pi-pencil"
                severity="secondary"
                variant="text"
                rounded
                @click="editUser(user)"
              />
              <Button
                icon="pi pi-trash"
                severity="danger"
                variant="text"
                rounded
                @click="deleteUser(user)"
              />
            </div>
          </div>
        </div>
      </template>
    </div>

    <Dialog
      v-model:visible="userDialog"
      :header="editingUser.id ? 'Edit Athlete' : 'Onboard New Athlete'"
      :modal="true"
      class="w-full max-w-md"
    >
      <div class="flex flex-col gap-6 py-4">
        <div class="flex flex-col gap-2">
          <label
            for="userName"
            class="text-sm uppercase tracking-wider"
          >Full Name</label>
          <InputText
            id="userName"
            v-model="editingUser.name"
            placeholder="Name"
            :class="{ 'p-invalid': submitted && !editingUser.name }"
          />
          <small
            v-if="submitted && !editingUser.name"
            class="p-error"
          >Name is required.</small>
        </div>
        <div class="flex flex-col gap-2">
          <label
            for="userEmail"
            class="text-sm uppercase tracking-wider"
          >Email Address</label>
          <InputText
            id="userEmail"
            v-model="editingUser.email"
            :disabled="!!editingUser.id"
            placeholder="email@example.com"
            :class="{ 'p-invalid': submitted && (!editingUser.email || !isEmailValid(editingUser.email)) }"
          />
          <small
            v-if="submitted && !editingUser.email"
            class="p-error"
          >Email is required.</small>
          <small
            v-else-if="submitted && !isEmailValid(editingUser.email)"
            class="p-error"
          >Invalid email format.</small>
        </div>
        <div class="flex flex-col gap-2">
          <label
            for="userRoles"
            class="text-sm uppercase tracking-wider"
          >Roles</label>
          <MultiSelect
            v-model="editingUser.roles"
            input-id="userRoles"
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
          <label
            for="userPassword"
            class="text-sm uppercase tracking-wider"
          >Temporary Password</label>
          <InputText
            id="userPassword"
            v-model="editingUser.password"
            placeholder="Temporary password"
          />
          <small class="italic">User will be forced to change this on first login.</small>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-between items-center w-full">
          <div>
            <Button
              v-if="editingUser.id"
              label="Reset Password"
              icon="pi pi-key"
              severity="warn"
              variant="text"
              class="text-xs font-bold"
              :loading="resettingPassword"
              @click="resetPassword"
            />
          </div>
          <div class="flex gap-2">
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
