<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useSettingsStore } from '../store/useSettingsStore';
import { useRouter, useRoute } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import {downloadPrivacyPolicy} from "../services/download";
import LegalDialog from "../components/LegalDialog.vue";

const { t } = useI18n();
const step = ref(1);
const name = ref('');
const nameTouched = ref(false);
const email = ref('');
const emailTouched = ref(false);
const gender = ref(null);
const genderTouched = ref(false);
const genderOptions = computed(() => [
    { label: t('auth.genderMale'), value: 'male' },
    { label: t('auth.genderFemale'), value: 'female' },
    { label: t('auth.genderOther'), value: 'other' }
]);
const companyName = ref('');
const companyNameTouched = ref(false);
const registerMode = ref<'create' | 'join'>('create');
const registerModeOptions = computed(() => [
    { label: t('auth.modeCreate'), value: 'create' },
    { label: t('auth.modeJoin'), value: 'join' }
]);
const password = ref('');
const passwordTouched = ref(false);
const confirmPassword = ref('');
const confirmPasswordTouched = ref(false);
const acceptedTerms = ref(false);
const loading = ref(false);
const isRegistered = ref(false);
const router = useRouter();
const route = useRoute();
const toast = useToast();
const settingsStore = useSettingsStore();

const isEmailValid = computed(() => {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
});

onMounted(() => {
    if (route.query.companyName) {
        companyName.value = route.query.companyName as string;
        registerMode.value = 'join';
    }
});

const companyLegal = ref({ found: false, companyName: '', termsAndConditionsMarkdown: '', legalNoticeMarkdown: '', privacyPolicyPdfPath: '' });
const showTermsModal = ref(false);
const dialogType = ref<'terms' | 'legal'>('terms');

const legalSettings = computed(() => companyLegal.value);

const passwordValidation = computed(() => {
    return {
        minLength: password.value.length >= 8,
        uppercase: /[A-Z]/.test(password.value),
        lowercase: /[a-z]/.test(password.value),
        number: /[0-9]/.test(password.value),
        special: /[^A-Za-z0-9]/.test(password.value),
        match: password.value === confirmPassword.value && password.value !== ''
    };
});

const isPasswordValid = computed(() => {
    const v = passwordValidation.value;
    return v.minLength && v.uppercase && v.lowercase && v.number && v.special;
});

const isStep1Valid = computed(() => {
    return name.value && email.value && companyName.value && gender.value && isPasswordValid.value && passwordValidation.value.match;
});

const isFormValid = computed(() => {
    if (!isStep1Valid.value) return false;
    if (registerMode.value === 'join') {
        return companyLegal.value.found && acceptedTerms.value;
    }
    return !companyLegal.value.found;
});

async function goToStep2() {
  nameTouched.value = true;
  emailTouched.value = true;
  companyNameTouched.value = true;
  genderTouched.value = true;
  passwordTouched.value = true;
  confirmPasswordTouched.value = true;

  if (!isStep1Valid.value) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('auth.fillFieldsError'), life: 3000 });
    return;
  }

  loading.value = true;
  try {
    const response = await api.get(`/register/company-legal?name=${encodeURIComponent(companyName.value)}`);
    companyLegal.value = response.data;

    if (registerMode.value === 'create') {
      if (companyLegal.value.found) {
        toast.add({
          severity: 'error',
          summary: t('app.error'),
          detail: t('auth.companyAlreadyExists', { company: companyName.value }),
          life: 5000
        });
        return;
      }
    } else {
      if (!companyLegal.value.found) {
        toast.add({
          severity: 'error',
          summary: t('app.error'),
          detail: t('auth.companyNotFoundForJoin', { company: companyName.value }),
          life: 5000
        });
        return;
      }
      // Fetch terms and conditions only if company exists
      const termsResponse = await api.get(`/register/terms-and-conditions?name=${encodeURIComponent(companyName.value)}`);
      companyLegal.value.termsAndConditionsMarkdown = termsResponse.data.termsAndConditionsMarkdown;
    }

    step.value = 2;
    window.scrollTo(0, 0);
  } catch (err) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('auth.fetchCompanyError'), life: 3000 });
  } finally {
    loading.value = false;
  }
}

function onClickShowTerms() {
  dialogType.value = 'terms';
  showTermsModal.value = true;
}

async function register() {
  if (!isFormValid.value) {
      toast.add({ severity: 'error', summary: t('app.error'), detail: t('auth.agreeTermsError'), life: 5000 });
      return;
  }

  loading.value = true;
  try {
    await api.post('/register', {
      name: name.value,
      email: email.value,
      gender: gender.value,
      companyName: companyName.value,
      password: password.value
    });
    isRegistered.value = true;
    window.scrollTo(0, 0);
  } catch (err: any) {
    let detail = err.response?.data?.error || t('auth.registrationFailed');
    if (err.response?.status === 409 || detail === 'Email already registered') {
        detail = t('auth.emailAlreadyRegistered');
    }
    toast.add({ severity: 'error', summary: t('app.error'), detail: detail, life: 7000 });
  } finally {
    loading.value = false;
  }
}

</script>

<template>
  <div class="min-h-[80vh] flex items-center justify-center bg-white px-4 py-12">
    <div class="phoenix-card w-full max-w-2xl">
      <div v-if="!isRegistered">
        <div class="text-center mb-10">
          <h1 class="text-3xl font-extrabold tracking-tight">
            <template v-if="step === 1">
              {{ registerMode === 'create' ? t('auth.registerNewCompanyTitle') : t('auth.joinExistingCompanyTitle') }}
            </template>
            <template v-else>
              {{ registerMode === 'create' ? t('auth.createNewCompany') : t('auth.legalAgreement') }}
            </template>
          </h1>
          <p class="text-slate-600 mt-2 font-medium">
            <template v-if="step === 1">
              {{ t('auth.startTransformation') }}
            </template>
            <template v-else>
              {{ registerMode === 'create' ? t('auth.confirmDetails') : t('auth.reviewTerms') }}
            </template>
          </p>
        </div>

        <div v-if="step === 1">
          <form
            class="flex flex-col gap-6"
            @submit.prevent="goToStep2"
          >
            <!-- Mode Selector -->
            <div class="flex flex-col gap-2">
              <label class="form-label-base">{{ t('auth.registerModeLabel') }}</label>
              <SelectButton
                v-model="registerMode"
                :options="registerModeOptions"
                option-label="label"
                option-value="value"
                :disabled="!!route.query.companyName"
                class="w-full select-button-full"
              />
              <small
                v-if="!!route.query.companyName"
                class="text-slate-400 text-xs mt-1"
              >
                {{ t('auth.modeLockedByLink') }}
              </small>
            </div>

            <div class="flex flex-col">
              <label
                for="name"
                class="form-label-base"
              >{{ t('auth.fullName') }}</label>
              <InputText
                id="name"
                v-model="name"
                required
                placeholder="Coach Carter"
                :class="{ 'p-invalid': nameTouched && !name }"
                @blur="nameTouched = true"
              />
              <small
                v-if="nameTouched && !name"
                class="text-red-500 text-xs mt-1"
              >{{ t('auth.nameRequired') }}</small>
            </div>

            <div class="flex flex-col">
              <label
                for="email"
                class="form-label-base"
              >{{ t('auth.email') }}</label>
              <InputText
                id="email"
                v-model="email"
                type="email"
                required
                :placeholder="'athlete@' + settingsStore.companyName.toLowerCase().replace(/[^a-z0-9]/g, '-') + '.com'"
                :class="{ 'p-invalid': emailTouched && (!email || !isEmailValid) }"
                @blur="emailTouched = true"
              />
              <small
                v-if="emailTouched && !email"
                class="text-red-500 text-xs mt-1"
              >{{ t('auth.emailRequired') }}</small>
              <small
                v-else-if="emailTouched && !isEmailValid"
                class="text-red-500 text-xs mt-1"
              >{{ t('auth.invalidEmail') }}</small>
            </div>

            <div class="flex flex-col">
              <label class="form-label-base">{{ t('auth.gender') }}</label>
              <Select
                v-model="gender"
                :options="genderOptions"
                option-label="label"
                option-value="value"
                :placeholder="t('auth.selectGender')"
                :class="{ 'p-invalid': genderTouched && !gender }"
                @blur="genderTouched = true"
              />
              <small
                v-if="genderTouched && !gender"
                class="text-red-500 text-xs mt-1"
              >{{ t('auth.genderRequired') }}</small>
            </div>

            <div class="flex flex-col">
              <label
                for="companyName"
                class="form-label-base"
              >
                {{ registerMode === 'create' ? t('auth.companyNameCreate') : t('auth.companyNameJoin') }}
              </label>
              <InputText
                id="companyName"
                v-model="companyName"
                required
                :placeholder="registerMode === 'create' ? 'CrossFit Hamburg' : 'Enter existing studio name'"
                :class="{ 'p-invalid': companyNameTouched && !companyName }"
                :disabled="!!route.query.companyName"
                @blur="companyNameTouched = true"
              />
              <small
                v-if="companyNameTouched && !companyName"
                class="text-red-500 text-xs mt-1"
              >{{ t('auth.companyRequired') }}</small>
            </div>
            <div class="flex flex-col">
              <label
                for="password"
                class="form-label-base"
              >{{ t('auth.password') }}</label>
              <Password
                v-model="password"
                input-id="password"
                toggle-mask
                required
                placeholder="••••••••"
                class="w-full"
                :input-class="{ 'w-full': true, 'p-invalid': passwordTouched && !isPasswordValid }"
                @blur="passwordTouched = true"
              >
                <template #footer>
                  <Divider />
                  <p class="mt-2 font-bold text-xs uppercase tracking-wider">
                    {{ t('app.requirements') }}
                  </p>
                  <ul class="pl-2 ml-2 mt-2 list-disc flex flex-col gap-1 text-xs">
                    <li :class="passwordValidation.minLength ? 'text-green-600' : 'text-slate-400'">
                      {{ t('app.atLeast8Chars') }}
                    </li>
                    <li :class="passwordValidation.uppercase ? 'text-green-600' : 'text-slate-400'">
                      {{ t('app.atLeastOneUppercase') }}
                    </li>
                    <li :class="passwordValidation.lowercase ? 'text-green-600' : 'text-slate-400'">
                      {{ t('app.atLeastOneLowercase') }}
                    </li>
                    <li :class="passwordValidation.number ? 'text-green-600' : 'text-slate-400'">
                      {{ t('app.atLeastOneNumber') }}
                    </li>
                    <li :class="passwordValidation.special ? 'text-green-600' : 'text-slate-400'">
                      {{ t('app.atLeastOneSpecial') }}
                    </li>
                  </ul>
                </template>
              </Password>
              <small
                v-if="passwordTouched && !isPasswordValid"
                class="text-red-500 text-xs mt-1"
              >{{ t('auth.passwordRequirements') }}</small>
            </div>

            <div class="flex flex-col">
              <label
                for="confirmPassword"
                class="form-label-base"
              >{{ t('auth.confirmPassword') }}</label>
              <InputText
                id="confirmPassword"
                v-model="confirmPassword"
                type="password"
                required
                placeholder="••••••••"
                :class="{ 'p-invalid': confirmPasswordTouched && !passwordValidation.match }"
                @blur="confirmPasswordTouched = true"
              />
              <small
                v-if="confirmPasswordTouched && !passwordValidation.match"
                class="text-red-500 text-xs mt-1"
              >{{ t('auth.passwordsMatchRequired') }}</small>
            </div>

            <Button
              type="submit"
              severity="primary"
              :label="t('auth.continue')"
              :loading="loading"
              class="btn-primary w-full py-4 text-lg"
            />
          </form>
        </div>

        <div v-else-if="step === 2">
          <div class="space-y-8">
            <!-- New Company Info Card (Create Mode) -->
            <div
              v-if="registerMode === 'create'"
              class="flex flex-col gap-4 p-6 bg-slate-50 rounded-2xl border border-slate-100 shadow-sm text-left"
            >
              <div class="flex items-center gap-3 text-primary">
                <i class="pi pi-building text-2xl text-amber-500" />
                <h3 class="text-lg font-black uppercase tracking-tight text-slate-900">
                  {{ t('auth.newCompanyWelcomeTitle') }}
                </h3>
              </div>
              <p class="text-sm text-slate-600 leading-relaxed">
                {{ t('auth.newCompanyWelcomeText', { company: companyName }) }}
              </p>
              <div class="mt-2 text-xs text-slate-600 bg-amber-50 border border-amber-100 rounded-xl p-4 flex gap-3 text-left">
                <i class="pi pi-info-circle text-amber-500 text-lg flex-shrink-0" />
                <span>{{ t('auth.newCompanyAdminHint') }}</span>
              </div>
            </div>

            <!-- Existing Company Terms Agreement (Join Mode) -->
            <div
              v-else
              class="flex flex-col gap-4 p-6 bg-slate-50 rounded-2xl border border-slate-100 shadow-sm text-left"
            >
              <div class="flex items-center gap-3 text-primary">
                <i class="pi pi-users text-2xl text-amber-500" />
                <h3 class="text-lg font-black uppercase tracking-tight text-slate-900">
                  {{ t('auth.joinCompanyWelcomeTitle') }}
                </h3>
              </div>
              <p class="text-sm text-slate-600 leading-relaxed">
                {{ t('auth.joinCompanyWelcomeText', { company: companyName }) }}
              </p>

              <div class="mt-4 p-4 bg-white rounded-xl border border-slate-150">
                <div class="flex items-start gap-4">
                  <Checkbox
                    v-model="acceptedTerms"
                    input-id="terms"
                    :binary="true"
                    class="mt-1"
                  />
                  <label
                    for="terms"
                    class="text-sm text-slate-700 font-medium leading-relaxed cursor-pointer"
                  >
                    {{ t('auth.iAgreeTo') }}
                    <a
                      href="javascript:void(0)"
                      class="font-bold text-amber-500 hover:underline"
                      @click="onClickShowTerms"
                    >
                      {{ t('auth.termsAndConditionsLink') }}
                    </a>
                    {{ t('auth.of') }} {{ companyLegal.companyName }}
                    {{ t('auth.andIHaveRead') }}
                    <a
                      href="javascript:void(0)"
                      class="text-amber-500 font-bold hover:underline"
                      @click="downloadPrivacyPolicy(companyLegal.companyName)"
                    >
                      {{ t('auth.privacyPolicyLink') }}
                    </a>
                  </label>
                </div>
              </div>
            </div>

            <div class="flex gap-4">
              <Button
                type="button"
                severity="secondary"
                :label="t('app.back')"
                icon="pi pi-arrow-left"
                class="flex-1 py-4 text-lg"
                outlined
                @click="step = 1"
              />
              <Button
                type="button"
                severity="primary"
                :label="registerMode === 'create' ? t('auth.acceptAndCreateNewCompany') : t('auth.acceptAndJoin')"
                :loading="loading"
                :disabled="registerMode === 'join' ? !acceptedTerms : false"
                class="flex-2 btn-primary py-4 text-lg"
                @click="register"
              />
            </div>
          </div>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-50 text-center">
          <p class="font-medium text-slate-600">
            {{ t('auth.alreadyAthlete') }}
            <RouterLink
              to="/login"
              class="text-accent hover:brightness-90 font-bold underline-offset-4 hover:underline transition-all"
            >
              {{ t('auth.registerHere') }}
            </RouterLink>
          </p>
        </div>
      </div>

      <div
        v-else
        class="text-center py-8"
      >
        <div class="mb-6 flex justify-center">
          <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center">
            <i class="pi pi-check text-4xl text-green-600" />
          </div>
        </div>
        <h2 class="text-3xl font-extrabold text-slate-900 mb-4">
          {{ t('auth.checkEmail') }}
        </h2>
        <p class="text-lg text-slate-600 mb-8 leading-relaxed">
          {{ t('auth.accountCreated', { email: email }) }}<br>
          {{ t('auth.verifyEmailInstruction') }}
        </p>
        <div class="flex flex-col gap-4">
          <Button
            :label="t('auth.goToLogin')"
            severity="primary"
            class="w-full py-4 text-lg"
            @click="router.push('/login')"
          />
        </div>
      </div>
    </div>

    <LegalDialog
      v-model:visible="showTermsModal"
      :type="dialogType"
      :data="legalSettings"
      :company-name="companyLegal.companyName || companyName"
    />
  </div>
</template>

<style scoped>
:deep(.p-select-label) {
  color: unset;
}

.markdown-content :deep(h1) {
  font-size: 1.5rem;
  font-weight: 800;
  margin-bottom: 1rem;
  color: #0f172a;
  border-bottom: 2px solid #f1f5f9;
  padding-bottom: 0.5rem;
}

.markdown-content :deep(h2) {
  font-size: 1.25rem;
  font-weight: 700;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  color: #1e293b;
}

.markdown-content :deep(p) {
  margin-bottom: 1rem;
  line-height: 1.7;
  color: #334155;
}

.markdown-content :deep(ul) {
  list-style-type: disc;
  padding-left: 1.5rem;
  margin-bottom: 1rem;
}

.markdown-content :deep(li) {
  margin-bottom: 0.5rem;
  color: #334155;
}

.markdown-content :deep(strong) {
  color: #0f172a;
}
</style>
