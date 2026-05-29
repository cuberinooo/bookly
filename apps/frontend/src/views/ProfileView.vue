<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useAuthStore } from '../store/useAuthStore';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useOnboarding, ONBOARDING_TASKS } from '../composables/useOnboarding';
import OnboardingChecklist from '../components/OnboardingChecklist.vue';
import InactiveAccountAlert from '../components/InactiveAccountAlert.vue';
import api from '../services/api';

const { t } = useI18n();
const toast = useToast();
const confirm = useConfirm();
const router = useRouter();
const authStore = useAuthStore();
const { markTaskComplete } = useOnboarding();
const user = ref({
    name: '',
    email: '',
    id: null,
    roles: [] as string[],
    profilePicture: null,
    phoneNumber: '',
    emergencyContactName: '',
    emergencyContactPhone: '',
    gender: null,
    isPublic: false
});
const hasConsentedToEmergency = ref(false);
const loading = ref(false);
const fetching = ref(true);
const uploading = ref(false);
const deleting = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const genderOptions = computed(() => [
    { label: t('auth.genderMale'), value: 'male' },
    { label: t('auth.genderFemale'), value: 'female' },
    { label: t('auth.genderOther'), value: 'other' }
]);

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
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.loadFailed'), life: 5000 });
    } finally {
        fetching.value = false;
    }
}

function confirmDelete() {
    confirm.require({
        message: t('profile.deleteAccountConfirmMessage'),
        header: t('profile.deleteAccountConfirmHeader'),
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectProps: {
          label: t('app.cancel'),
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
        toast.add({ severity: 'success', summary: t('app.success'), detail: t('profile.accountDeleted'), life: 5000 });
        await authStore.logout();
        router.push('/login');
    } catch (e: any) {
        const message = e.response?.data?.error || t('profile.deleteFailed');
        toast.add({ severity: 'error', summary: t('app.error'), detail: message, life: 7000 });
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

    markTaskComplete(ONBOARDING_TASKS.PROFILE_UPDATE);
    toast.add({ severity: 'success', summary: t('app.success'), detail: t('profile.avatarUpdated'), life: 5000 });  } catch (e) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.uploadFailed'), life: 5000 });
  } finally {
    uploading.value = false;
    if (target) target.value = ''; // Reset input
  }
}

async function updateProfile() {
    if ((user.value.emergencyContactName || user.value.emergencyContactPhone) && !hasConsentedToEmergency.value) {
        toast.add({ severity: 'warn', summary: t('profile.consentRequired'), detail: t('profile.consentRequiredNote'), life: 5000 });
        return;
    }

    loading.value = true;
    try {
        await api.patch('/user/me', {
            name: user.value.name,
            phoneNumber: user.value.phoneNumber,
            gender: user.value.gender,
            isPublic: user.value.isPublic,
            emergencyContactName: user.value.emergencyContactName,
            emergencyContactPhone: user.value.emergencyContactPhone
        });
        if (authStore.user) {
            authStore.user = {
                ...authStore.user,
                name: user.value.name,
                isPublic: user.value.isPublic
            };
        }
        markTaskComplete(ONBOARDING_TASKS.PROFILE_UPDATE);
        toast.add({ severity: 'success', summary: t('app.success'), detail: t('profile.updateSuccess'), life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: t('app.error'), detail: t('profile.updateError'), life: 5000 });
    } finally {
        loading.value = false;
    }
}

onMounted(fetchProfile);
</script>

<template>
  <div class="max-w-4xl mx-auto py-12 px-2 md:px-4">
    <div class="mb-10">
      <h1 class="text-4xl font-extrabold tracking-tight">
        {{ t('profile.title') }}
      </h1>
      <p class="text-slate-600 mt-2 font-medium">
        {{ t('profile.subtitle') }}
      </p>
    </div>

    <InactiveAccountAlert class="mb-8" />

    <Tabs
      value="0"
      class="mb-8"
    >
      <TabList class="mb-6">
        <Tab
          value="0"
          class="flex items-center gap-2"
        >
          <i class="pi pi-user" />
          <span>{{ t('profile.myAccount') }}</span>
        </Tab>
        <Tab
          value="1"
          class="flex items-center gap-2"
        >
          <i class="pi pi-list-check" />
          <span>{{ t('profile.onboardingGuide') }}</span>
        </Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="0">
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
            <div class="md:col-span-1">
              <div class="phoenix-card text-center flex flex-col items-center">
                <div class="profile-image-container mb-4">
                  <img
                    v-if="profilePictureUrl"
                    :src="profilePictureUrl"
                    alt="Profile"
                    class="profile-image-large"
                  >
                  <div
                    v-else
                    class="profile-image-placeholder"
                  >
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
                  :label="t('profile.changePicture')"
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
              </div>
            </div>

            <div class="md:col-span-2">
              <div class="phoenix-card">
                <form
                  class="flex flex-col gap-6"
                  @submit.prevent="updateProfile"
                >
                  <div class="flex flex-col">
                    <label
                      for="profileName"
                      class="form-label-base"
                    >{{ t('auth.fullName') }}</label>
                    <InputText
                      id="profileName"
                      v-model="user.name"
                    />
                  </div>

                  <div class="flex flex-col">
                    <label
                      for="profileEmail"
                      class="form-label-base"
                    >{{ t('auth.email') }}</label>
                    <InputText
                      id="profileEmail"
                      v-model="user.email"
                      type="email"
                      disabled
                      class="opacity-70 cursor-not-allowed"
                    />
                    <small class="text-slate-400 mt-1">{{ t('profile.emailNote') }}</small>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="flex flex-col">
                      <label
                        for="phoneNumber"
                        class="form-label-base"
                      >{{ t('profile.phoneNumber') }}</label>
                      <InputText
                        id="phoneNumber"
                        v-model="user.phoneNumber"
                        placeholder="+49 123 456789"
                      />
                    </div>
                    <div class="flex flex-col">
                      <label class="form-label-base">{{ t('auth.gender') }}</label>
                      <Select
                        v-model="user.gender"
                        :options="genderOptions"
                        option-label="label"
                        option-value="value"
                        :placeholder="t('auth.selectGender')"
                      />
                    </div>
                  </div>

                  <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-xl border border-slate-200">
                    <div class="flex-1">
                      <h4 class="font-bold text-slate-900">
                        {{ t('profile.publicProfile') }}
                      </h4>
                      <p class="text-sm text-slate-600">
                        {{ t('profile.publicProfileNote') }}
                      </p>
                    </div>
                    <ToggleSwitch v-model="user.isPublic" />
                  </div>

                  <div class="border-t border-slate-100 pt-6 mt-2">
                    <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                      <i class="pi pi-shield text-amber-500" />
                      {{ t('profile.safetyEmergencyInfo') }}
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                      <div class="flex flex-col">
                        <label
                          for="emergencyName"
                          class="form-label-base"
                        >{{ t('profile.emergencyContactName') }}</label>
                        <InputText
                          id="emergencyName"
                          v-model="user.emergencyContactName"
                          :placeholder="t('profile.placeholderEmergencyName')"
                        />
                      </div>
                      <div class="flex flex-col">
                        <label
                          for="emergencyPhone"
                          class="form-label-base"
                        >{{ t('profile.emergencyContactPhone') }}</label>
                        <InputText
                          id="emergencyPhone"
                          v-model="user.emergencyContactPhone"
                          placeholder="+49 123 456789"
                        />
                      </div>
                    </div>

                    <div class="flex items-start gap-3 mt-6 p-4 bg-slate-50 rounded-lg border border-slate-100">
                      <Checkbox
                        v-model="hasConsentedToEmergency"
                        :binary="true"
                        input-id="consent"
                      />
                      <label
                        for="consent"
                        class="text-xs text-slate-600 font-medium leading-relaxed"
                      >
                        {{ t('profile.emergencyConsent', { company: authStore.user?.company?.name || 'this app' }) }}
                      </label>
                    </div>
                  </div>

                  <div class="pt-4">
                    <Button
                      severity="primary"
                      type="submit"
                      :label="t('app.save')"
                      :loading="loading"
                      class="btn-primary"
                    />
                  </div>
                </form>
              </div>

              <div class="phoenix-card mt-8 border-red-100 bg-red-50/30">
                <h3 class="text-red-600 font-bold uppercase tracking-wider text-sm mb-4">
                  {{ t('profile.dangerZone') }}
                </h3>
                <p class="text-slate-600 text-sm mb-6">
                  {{ t('profile.deleteAccountWarning') }}
                </p>
                <Button
                  :label="t('profile.deleteAccount')"
                  severity="danger"
                  outlined
                  icon="pi pi-trash"
                  :loading="deleting"
                  @click="confirmDelete"
                />
              </div>
            </div>
          </div>
        </TabPanel>
        <TabPanel value="1">
          <OnboardingChecklist :always-show="true" />
        </TabPanel>
      </TabPanels>
    </Tabs>
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
