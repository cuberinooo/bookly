<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { useAuthStore } from '../store/useAuthStore';
import { useSettingsStore } from '../store/useSettingsStore';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const toast = useToast();
const confirm = useConfirm();
const authStore = useAuthStore();
const settingsStore = useSettingsStore();
const users = ref<any[]>([]);
const searchQuery = ref('');
const selectedRoles = ref<string[]>([]);
const loading = ref(false);

const filteredUsers = computed(() => {
    let result = users.value;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(u =>
            u.name.toLowerCase().includes(query) ||
            u.email.toLowerCase().includes(query)
        );
    }

    if (selectedRoles.value && selectedRoles.value.length > 0) {
        result = result.filter(u =>
            u.roles.some(role => selectedRoles.value.includes(role))
        );
    }

    return result;
});

const userDialog = ref(false);
const editingUser = ref<any>({ name: '', email: '', roles: ['ROLE_MEMBER'], password: '' });
const submitting = ref(false);
const submitted = ref(false);
const resettingPassword = ref(false);
const sendingMembershipWelcome = ref(false);
const emailTakenError = ref(false);

const getSortedRoles = (roles: string[]) => {
  const roleOrder = ['ROLE_ADMIN', 'ROLE_TRAINER', 'ROLE_MEMBER', 'ROLE_TRIAL'];
  return [...roles]
    .filter((r) => r !== 'ROLE_USER' && r !== 'ROLE_MONITOR')
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

const roleOptions = computed(() => [
    { label: t('admin.users.roleMember'), value: 'ROLE_MEMBER' },
    { label: t('admin.users.roleTrainer'), value: 'ROLE_TRAINER' },
    { label: t('admin.users.roleAdmin'), value: 'ROLE_ADMIN' },
    { label: t('admin.users.roleTrial'), value: 'ROLE_TRIAL' }
]);

async function fetchUsers() {
    loading.value = true;
    try {
        const response = await api.get('/admin/users');
        users.value = response.data;
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('admin.users.fetchFailed'), life: 5000 });
    } finally {
        loading.value = false;
    }
}

function openNewUser() {
    editingUser.value = { name: '', email: '', roles: ['ROLE_MEMBER'], password: Math.random().toString(36).slice(-10) + 'A1!' };
    submitted.value = false;
    emailTakenError.value = false;
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
    emailTakenError.value = false;
    userDialog.value = true;
}

async function saveUser() {
    submitted.value = true;

    if (!editingUser.value.name || !editingUser.value.email || !isEmailValid(editingUser.value.email)) {
        return;
    }

    submitting.value = true;
    try {
        emailTakenError.value = false;
        if (editingUser.value.id) {
            await api.patch(`/admin/users/${editingUser.value.id}`, {
                name: editingUser.value.name,
                roles: editingUser.value.roles
            });
            toast.add({ severity: 'success', summary: t('app.updated'), detail: t('admin.users.statusUpdated'), life: 5000 });

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
            toast.add({ severity: 'success', summary: t('app.created'), detail: t('admin.users.statusUpdated'), life: 5000 });
        }
        userDialog.value = false;
        fetchUsers();
    } catch (e: any) {
        let detail = e.response?.data?.error || t('app.error');
        if (e.response?.status === 409 || detail === 'Email already registered') {
            detail = t('auth.emailAlreadyRegistered');
            emailTakenError.value = true;
        }
        toast.add({ severity: 'error', summary: t('app.error'), detail: detail, life: 7000 });
    } finally {
        submitting.value = false;
    }
}

async function resetPassword() {
    if (!editingUser.value.id) return;

    confirm.require({
        message: t('admin.users.resetPasswordConfirm', { name: editingUser.value.name }),
        header: t('admin.users.resetPassword'),
        icon: 'pi pi-exclamation-lock',
        acceptProps: { severity: 'warn', label: t('admin.users.resetPassword') },
        rejectProps: {
          label: t('app.cancel'),
          severity: 'secondary',
          text: true
        },
        accept: async () => {
            resettingPassword.value = true;
            try {
                await api.post(`/admin/users/${editingUser.value.id}/reset-password`);
                toast.add({
                    severity: 'success',
                    summary: t('admin.users.passwordResetSuccess'),
                    detail: t('admin.users.passwordResetDetail'),
                    life: 5000
                });
            } catch (e) {
                toast.add({ severity: 'error', summary: t('app.error'), detail: t('app.error'), life: 5000 });
            } finally {
                resettingPassword.value = false;
            }
        }
    });
}

async function sendMembershipWelcomeMail(user: any) {
    sendingMembershipWelcome.value = true;
    try {
        await api.post(`/admin/users/${user.id}/send-membership-welcome`);
        user.membershipWelcomeMailSent = true;
        toast.add({ severity: 'success', summary: t('app.success'), detail: t('admin.users.membershipWelcomeMailSuccess'), life: 5000 });
    } catch (e: any) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: e.response?.data?.error || t('app.error'), life: 5000 });
    } finally {
        sendingMembershipWelcome.value = false;
    }
}
async function toggleActive(user: any) {
    try {
        await api.patch(`/admin/users/${user.id}/toggle-active`);
        user.isActive = !user.isActive;
        toast.add({ severity: 'success', summary: t('admin.users.statusUpdated'), detail: user.isActive ? t('admin.users.userActivated') : t('admin.users.userDeactivated'), life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('app.error'), life: 5000 });
    }
}

async function deleteUser(user: any) {
    confirm.require({
        message: t('admin.users.deleteConfirm', { name: user.name }),
        header: t('pb.deleteConfirmHeader'),
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { severity: 'danger', label: t('app.delete') },
        rejectProps: {
          label: t('app.cancel'),
          severity: 'secondary',
          text: true
        },
        accept: async () => {
            try {
                await api.delete(`/admin/users/${user.id}`);
                toast.add({ severity: 'info', summary: t('app.success'), detail: t('admin.users.deleteSuccess'), life: 5000 });
                fetchUsers();
            } catch (e) {
                toast.add({ severity: 'error', summary: t('app.error'), detail: t('app.error'), life: 5000 });
            }
        }
    });
}

onMounted(() => {
    fetchUsers();
    settingsStore.fetchSettings();
});
</script>

<template>
  <div class="user-management mt-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
      <h2 class="text-2xl font-bold uppercase tracking-tight font-barlow flex items-center gap-2">
        {{ $t('admin.users.title') }}
        <Tag
          :value="filteredUsers.length"
          severity="secondary"
          rounded
          class="font-mono"
        />
      </h2>
      <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
        <div class="relative w-full sm:w-64">
          <InputText
            v-model="searchQuery"
            :placeholder="$t('admin.users.searchPlaceholder')"
            class="w-full"
          />
        </div>
        <div class="w-full sm:w-48 flex gap-2">
          <MultiSelect
            v-model="selectedRoles"
            :options="roleOptions"
            option-label="label"
            option-value="value"
            :placeholder="$t('admin.users.filterRoles')"
            class="flex-1"
            :max-selected-labels="1"
          />
          <Button
            v-if="searchQuery || selectedRoles.length > 0"
            v-tooltip.top="$t('admin.users.clearFilters')"
            icon="pi pi-filter-slash"
            severity="secondary"
            variant="text"
            class="flex-shrink-0"
            @click="searchQuery = ''; selectedRoles = []"
          />
        </div>
        <Button
          :label="$t('admin.users.createUser')"
          icon="pi pi-user-plus"
          severity="primary"
          @click="openNewUser"
        />
      </div>
    </div>

    <!-- Welcome Email Info Message -->
    <Message
      severity="primary"
      icon="pi pi-envelope"
      class="mb-6"
    >
      <div class="flex flex-col sm:flex-row sm:items-center gap-2">
        <span class="font-bold text-slate-800">{{ $t('admin.users.welcomeMailInfoTitle') }}:</span>
        <span class="text-slate-600">
          {{ settingsStore.paymentEnabled ? $t('admin.users.welcomeMailInfoPaymentActive') : $t('admin.users.welcomeMailInfoPaymentInactive') }}
        </span>
      </div>
    </Message>

    <div class="hidden md:block">
      <DataTable
        :value="filteredUsers"
        :loading="loading"
        scrollable
        scroll-height="calc(100vh - 380px)"
        class="athletic-table"
      >
        <Column
          field="name"
          :header="$t('admin.users.athleteName')"
          sortable
        >
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <div class="font-bold text-slate-900">
                {{ data.name }}
              </div>
              <Tag
                v-if="data.stripeCustomerId"
                v-tooltip.top="'Active Subscription'"
                severity="success"
                rounded
                class="px-2 py-0.5 text-[10px] uppercase font-black"
              >
                <div class="flex items-center gap-1">
                  <i class="pi pi-credit-card text-[10px]" />
                  <span>Paid</span>
                </div>
              </Tag>
            </div>
            <div class="text-xs text-slate-500">
              {{ data.email }}
            </div>
          </template>
        </Column>
        <Column :header="$t('admin.users.roles')">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1">
              <Tag
                v-for="role in getSortedRoles(data.roles)"
                :key="role"
                :value="role === 'ROLE_ADMIN' ? t('admin.users.roleAdmin') : (role === 'ROLE_TRAINER' ? t('admin.users.roleTrainer') : (role === 'ROLE_TRIAL' ? t('admin.users.roleTrial') : t('admin.users.roleMember')))"
                :severity="role === 'ROLE_ADMIN' ? 'danger' : (role === 'ROLE_TRAINER' ? 'warn' : (role === 'ROLE_TRIAL' ? 'secondary' : 'info'))"
              />
            </div>
          </template>
        </Column>
        <Column :header="$t('admin.users.status')">
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <ToggleSwitch
                :model-value="data.isActive"
                @update:model-value="toggleActive(data)"
              />
            </div>
          </template>
        </Column>
        <Column :header="$t('admin.users.verified')">
          <template #body="{ data }">
            <i
              class="pi"
              :class="data.isVerified ? 'pi-check-circle text-accent' : 'pi-times-circle text-slate-300'"
            />
          </template>
        </Column>
        <Column
          v-if="!settingsStore.paymentEnabled"
          :header="$t('admin.users.mail')"
        >
          <template #body="{ data }">
            <div v-if="(data.roles.includes('ROLE_TRIAL'))">
              <i
                v-tooltip.top="data.membershipWelcomeMailSent ? $t('admin.users.membershipWelcomeMailSuccess') : $t('admin.users.mail')"
                class="pi"
                :class="data.membershipWelcomeMailSent ? 'pi-send text-accent' : 'pi-minus text-slate-300'"
              />
            </div>
          </template>
        </Column>
        <Column
          :header="$t('admin.users.actions')"
          class="w-48"
        >
          <template #body="{ data }">
            <div class="flex gap-2">
              <Button
                v-if="!settingsStore.paymentEnabled && data.roles.includes('ROLE_TRIAL') && !data.membershipWelcomeMailSent"
                v-tooltip.top="$t('admin.users.sendMembershipWelcome')"
                icon="pi pi-envelope"
                variant="text"
                rounded
                :loading="sendingMembershipWelcome"
                @click="sendMembershipWelcomeMail(data)"
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
    <div
      class="md:hidden user-list-wrapper overflow-y-auto pr-2 -mr-2 mt-4"
      style="max-height: calc(100vh - 380px);"
    >
      <div class="flex flex-col gap-4">
        <div
          v-if="loading"
          class="flex justify-center py-8"
        >
          <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
        </div>
        <template v-else>
          <div
            v-if="filteredUsers.length === 0"
            class="text-center py-12 bg-slate-50 rounded-lg border-2 border-dashed border-slate-200"
          >
            <i class="pi pi-users text-4xl text-slate-300 mb-3" />
            <p class="text-slate-500 font-medium">
              {{ $t('admin.users.noUsersFound') }}
            </p>
          </div>
          <div
            v-for="user in filteredUsers"
            :key="user.id"
            class="phoenix-card p-4 border border-slate-200"
          >
            <div class="flex justify-between items-start mb-4">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <h3 class="font-black text-lg text-slate-900 leading-tight">
                    {{ user.name }}
                  </h3>
                  <Tag
                    v-if="user.stripeCustomerId"
                    severity="success"
                    rounded
                    class="px-2 py-0.5 text-[9px] uppercase font-black"
                  >
                    Paid
                  </Tag>
                </div>
                <p class="text-xs text-slate-500 font-medium mb-2">
                  {{ user.email }}
                </p>

                <div class="flex flex-wrap gap-1 mb-2">
                  <Tag
                    v-for="role in getSortedRoles(user.roles)"
                    :key="role"
                    :value="role === 'ROLE_ADMIN' ? t('admin.users.roleAdmin') : (role === 'ROLE_TRAINER' ? t('admin.users.roleTrainer') : (role === 'ROLE_TRIAL' ? t('admin.users.roleTrial') : t('admin.users.roleMember')))"
                    :severity="role === 'ROLE_ADMIN' ? 'danger' : (role === 'ROLE_TRAINER' ? 'warn' : (role === 'ROLE_TRIAL' ? 'secondary' : 'info'))"
                    class="text-[10px] uppercase font-black"
                  />
                  <Tag
                    v-if="user.roles.includes('ROLE_TRIAL') && user.membershipWelcomeMailSent"
                    :value="$t('admin.users.membershipWelcomeMailSuccess')"
                    severity="success"
                    class="text-[10px] uppercase font-black"
                    icon="pi pi-send"
                  />
                </div>
              </div>
              <div class="flex items-center gap-2">
                <i
                  v-tooltip.left="user.isVerified ? $t('admin.users.verified') : $t('admin.users.verified')"
                  class="pi"
                  :class="user.isVerified ? 'pi-check-circle text-accent' : 'pi-times-circle text-slate-300'"
                />
              </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 mt-2">
              <div class="flex items-center gap-3">
                <span class="text-[10px] font-black uppercase text-slate-400 tracking-widest">{{ $t('admin.users.activeStatus') }}</span>
                <ToggleSwitch
                  :model-value="user.isActive"
                  @update:model-value="toggleActive(user)"
                />
              </div>
              <div class="flex gap-1">
                <Button
                  v-if="!settingsStore.paymentEnabled && user.roles.includes('ROLE_TRIAL') && !user.membershipWelcomeMailSent"
                  v-tooltip.top="$t('admin.users.sendMembershipWelcome')"
                  icon="pi pi-envelope"
                  severity="secondary"
                  variant="text"
                  rounded
                  :loading="sendingMembershipWelcome"
                  @click="sendMembershipWelcomeMail(user)"
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
    </div>

    <Dialog
      v-model:visible="userDialog"
      :header="editingUser.id ? $t('admin.users.editAthlete') : $t('admin.users.onboardAthlete')"
      :modal="true"
      class="w-full max-w-md"
    >
      <div class="flex flex-col gap-6 py-4">
        <div class="flex flex-col gap-2">
          <label
            for="userName"
            class="text-sm uppercase tracking-wider"
          >{{ $t('admin.users.fullName') }}</label>
          <InputText
            id="userName"
            v-model="editingUser.name"
            :placeholder="$t('admin.users.fullName')"
            :class="{ 'p-invalid': submitted && !editingUser.name }"
          />
          <small
            v-if="submitted && !editingUser.name"
            class="p-error"
          >{{ $t('admin.users.nameRequired') }}</small>
        </div>
        <div class="flex flex-col gap-2">
          <label
            for="userEmail"
            class="text-sm uppercase tracking-wider"
          >{{ $t('auth.email') }}</label>
          <InputText
            id="userEmail"
            v-model="editingUser.email"
            :disabled="!!editingUser.id"
            placeholder="email@example.com"
            :class="{ 'p-invalid': (submitted && (!editingUser.email || !isEmailValid(editingUser.email))) || emailTakenError }"
            @input="emailTakenError = false"
          />
          <small
            v-if="submitted && !editingUser.email"
            class="p-error"
          >{{ $t('admin.users.emailRequired') }}</small>
          <small
            v-else-if="submitted && !isEmailValid(editingUser.email)"
            class="p-error"
          >{{ $t('admin.users.invalidEmail') }}</small>
          <small
            v-else-if="emailTakenError"
            class="p-error"
          >{{ $t('auth.emailAlreadyRegistered') }}</small>
        </div>
        <div class="flex flex-col gap-2">
          <label
            for="userRoles"
            class="text-sm uppercase tracking-wider"
          >{{ $t('admin.users.roles') }}</label>
          <MultiSelect
            v-model="editingUser.roles"
            input-id="userRoles"
            :options="roleOptions"
            option-label="label"
            option-value="value"
            :placeholder="$t('admin.users.selectRoles')"
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
          >{{ $t('admin.users.tempPassword') }}</label>
          <InputText
            id="userPassword"
            v-model="editingUser.password"
            placeholder="Temporary password"
          />
          <small class="italic">{{ $t('admin.users.tempPasswordNote') }}</small>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-between items-center w-full">
          <div>
            <Button
              v-if="editingUser.id"
              :label="$t('admin.users.resetPassword')"
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
              :label="$t('app.cancel')"
              severity="secondary"
              variant="text"
              @click="userDialog = false"
            />
            <Button
              :label="$t('admin.users.saveUser')"
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
