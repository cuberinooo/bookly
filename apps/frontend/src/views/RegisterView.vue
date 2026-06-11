<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import { downloadPrivacyPolicy, downloadPlatformPrivacyPolicy } from "../services/download";
import LegalDialog from "../components/LegalDialog.vue";
import PlatformLegalDialog from "../components/PlatformLegalDialog.vue";

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

const isModeLocked = computed(() => !!route.query.companyName);

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

// Company Legal Setup details (for Create mode)
const legalNoticeRepresentative = ref('');
const legalNoticeRepresentativeTouched = ref(false);
const legalNoticeStreet = ref('');
const legalNoticeStreetTouched = ref(false);
const legalNoticeHouseNumber = ref('');
const legalNoticeHouseNumberTouched = ref(false);
const legalNoticeZipCode = ref('');
const legalNoticeZipCodeTouched = ref(false);
const legalNoticeCity = ref('');
const legalNoticeCityTouched = ref(false);

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
const showPlatformLegal = ref(false);
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
    return !!(name.value && email.value && isEmailValid.value && gender.value && isPasswordValid.value && passwordValidation.value.match);
});

const isStep2Valid = computed(() => {
    if (registerMode.value === 'create') {
        return !!(companyName.value &&
                  legalNoticeRepresentative.value &&
                  legalNoticeStreet.value &&
                  legalNoticeHouseNumber.value &&
                  legalNoticeZipCode.value &&
                  legalNoticeCity.value);
    } else {
        return !!companyName.value;
    }
});

const isFormValid = computed(() => {
    if (!isStep1Valid.value) return false;
    if (!isStep2Valid.value) return false;
    if (registerMode.value === 'join') {
        return companyLegal.value.found && acceptedTerms.value;
    }
    return !companyLegal.value.found;
});

function goToStep2() {
  nameTouched.value = true;
  emailTouched.value = true;
  genderTouched.value = true;
  passwordTouched.value = true;
  confirmPasswordTouched.value = true;

  if (!isStep1Valid.value) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('auth.fillFieldsError'), life: 3000 });
    return;
  }

  step.value = 2;
  window.scrollTo(0, 0);
}

const cooldownSeconds = ref(0);
let cooldownInterval: any = null;

const clickCount = ref(0);

function startCooldown(seconds: number = 5) {
  cooldownSeconds.value = seconds;
  if (cooldownInterval) clearInterval(cooldownInterval);
  cooldownInterval = setInterval(() => {
    cooldownSeconds.value--;
    if (cooldownSeconds.value <= 0) {
      clearInterval(cooldownInterval);
      cooldownInterval = null;
    }
  }, 1000);
}

async function goToStep3() {
  if (cooldownSeconds.value > 0) return;

  companyNameTouched.value = true;
  if (registerMode.value === 'create') {
    legalNoticeRepresentativeTouched.value = true;
    legalNoticeStreetTouched.value = true;
    legalNoticeHouseNumberTouched.value = true;
    legalNoticeZipCodeTouched.value = true;
    legalNoticeCityTouched.value = true;
  }

  if (!isStep2Valid.value) {
    toast.add({ severity: 'error', summary: t('app.error'), detail: t('auth.fillFieldsError'), life: 3000 });
    return;
  }

  clickCount.value++;
  if (clickCount.value >= 3) {
    startCooldown(5);
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

    step.value = 3;
    window.scrollTo(0, 0);
  } catch (err: any) {
    let errorMsg = t('auth.fetchCompanyError');
    if (err.response?.status === 429) {
      errorMsg = err.response?.data?.error || t('auth.fetchCompanyError');
    }
    toast.add({ severity: 'error', summary: t('app.error'), detail: errorMsg, life: 3000 });
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
    const payload = {
      name: name.value,
      email: email.value,
      gender: gender.value,
      companyName: companyName.value,
      password: password.value,
      registerMode: registerMode.value
    };

    if (registerMode.value === 'create') {
      Object.assign(payload, {
        legalNoticeRepresentative: legalNoticeRepresentative.value,
        legalNoticeStreet: legalNoticeStreet.value,
        legalNoticeHouseNumber: legalNoticeHouseNumber.value,
        legalNoticeZipCode: legalNoticeZipCode.value,
        legalNoticeCity: legalNoticeCity.value
      });
    }

    await api.post('/register', payload);
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
  <div class="min-h-[85vh] flex flex-col items-center justify-center bg-slate-50/50 px-4 py-12 gap-6">
    <div class="phoenix-card w-full max-w-2xl bg-white border border-slate-100 shadow-xl rounded-3xl p-8 md:p-10">
      <div v-if="!isRegistered">
        <!-- Step Wizard Header -->
        <div class="mb-10">
          <div class="flex items-center justify-between relative max-w-md mx-auto">
            <!-- Connecting Line -->
            <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-[3px] bg-slate-100 -z-10 rounded-full">
              <div
                class="h-full bg-amber-400 transition-all duration-300 rounded-full"
                :style="{ width: step === 1 ? '0%' : step === 2 ? '50%' : '100%' }"
              />
            </div>

            <!-- Step 1 -->
            <div class="flex flex-col items-center gap-2">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300 cursor-pointer"
                :class="step >= 1 ? 'bg-amber-400 text-slate-950 shadow-md ring-4 ring-amber-100/50' : 'bg-slate-100 text-slate-400'"
                @click="step > 1 && (step = 1)"
              >
                <span
                  v-if="step > 1"
                  class="pi pi-check text-xs font-bold"
                />
                <span v-else>1</span>
              </div>
              <span class="text-[10px] uppercase font-bold tracking-wider text-slate-600">
                {{ t('auth.stepAccount') }}
              </span>
            </div>

            <!-- Step 2 -->
            <div class="flex flex-col items-center gap-2">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300 cursor-pointer"
                :class="step >= 2 ? 'bg-amber-400 text-slate-950 shadow-md ring-4 ring-amber-100/50' : 'bg-slate-100 text-slate-400'"
                @click="step > 2 && (step = 2)"
              >
                <span
                  v-if="step > 2"
                  class="pi pi-check text-xs font-bold"
                />
                <span v-else>2</span>
              </div>
              <span class="text-[10px] uppercase font-bold tracking-wider text-slate-600">
                {{ registerMode === 'create' ? t('auth.stepCompany') : t('auth.stepJoinCompany') }}
              </span>
            </div>

            <!-- Step 3 -->
            <div class="flex flex-col items-center gap-2">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300"
                :class="step >= 3 ? 'bg-amber-400 text-slate-950 shadow-md ring-4 ring-amber-100/50' : 'bg-slate-100 text-slate-400'"
              >
                <span>3</span>
              </div>
              <span class="text-[10px] uppercase font-bold tracking-wider text-slate-600">
                {{ t('auth.stepConfirm') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Section Title & Subtitle based on Step & Mode -->
        <div class="text-center mb-8">
          <span class="text-xs uppercase font-extrabold tracking-widest text-amber-500 bg-amber-50 border border-amber-100 rounded-full px-3 py-1">
            {{ t('auth.stepTracking', { current: step, total: 3 }) }}
          </span>
          <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 mt-3">
            <template v-if="step === 1">
              {{ t('auth.startTransformation') }}
            </template>
            <template v-else-if="step === 2">
              {{ registerMode === 'create' ? t('auth.registerNewCompanyTitle') : t('auth.joinExistingCompanyTitle') }}
            </template>
            <template v-else-if="step === 3">
              {{ registerMode === 'create' ? t('auth.confirmDetails') : t('auth.legalAgreement') }}
            </template>
          </h1>
          <p class="text-slate-500 mt-2 font-medium">
            <template v-if="step === 1">
              {{ t('auth.fillFieldsError') }}
            </template>
            <template v-else-if="step === 2">
              {{ registerMode === 'create' ? t('auth.newCompanyWelcomeTitle') : t('auth.joinCompanyWelcomeTitle') }}
            </template>
            <template v-else-if="step === 3">
              {{ registerMode === 'create' ? t('auth.newCompanyWelcomeText', { company: companyName }) : t('auth.reviewTerms') }}
            </template>
          </p>
        </div>

        <!-- STEP 1: Account Details + Mode Choice -->
        <div v-if="step === 1">
          <form
            class="flex flex-col gap-6"
            @submit.prevent="goToStep2"
          >
            <!-- Mode Selection Cards (No SelectButton dropdown) -->
            <div class="flex flex-col gap-2">
              <label class="form-label-base">{{ t('auth.registerModeLabel') }}</label>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Option A: Create -->
                <div
                  class="flex flex-col gap-3 p-5 rounded-2xl border-2 select-none text-left transition-all duration-200"
                  :class="[
                    registerMode === 'create'
                      ? 'border-amber-400 bg-amber-50/20 shadow-sm ring-2 ring-amber-100'
                      : 'border-slate-200 hover:border-slate-350 hover:bg-slate-50/50',
                    isModeLocked ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                  ]"
                  @click="!isModeLocked && (registerMode = 'create')"
                >
                  <div class="flex items-center justify-between">
                    <div
                      class="w-12 h-12 rounded-xl flex items-center justify-center transition-all duration-200"
                      :class="registerMode === 'create' ? 'bg-amber-400 text-slate-950' : 'bg-slate-100 text-slate-500'"
                    >
                      <i class="pi pi-building text-xl" />
                    </div>
                    <div
                      v-if="registerMode === 'create'"
                      class="w-5 h-5 rounded-full bg-amber-400 text-slate-950 flex items-center justify-center"
                    >
                      <i class="pi pi-check text-[10px] font-bold" />
                    </div>
                  </div>
                  <div>
                    <h3 class="text-sm font-bold uppercase tracking-tight text-slate-900 mb-1">
                      {{ t('auth.modeCreate') }}
                    </h3>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium normal-case">
                      {{ t('auth.modeCreateDesc') }}
                    </p>
                  </div>
                </div>

                <!-- Option B: Join -->
                <div
                  class="flex flex-col gap-3 p-5 rounded-2xl border-2 select-none text-left transition-all duration-200"
                  :class="[
                    registerMode === 'join'
                      ? 'border-amber-400 bg-amber-50/20 shadow-sm ring-2 ring-amber-100'
                      : 'border-slate-200 hover:border-slate-350 hover:bg-slate-50/50',
                    isModeLocked ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                  ]"
                  @click="!isModeLocked && (registerMode = 'join')"
                >
                  <div class="flex items-center justify-between">
                    <div
                      class="w-12 h-12 rounded-xl flex items-center justify-center transition-all duration-200"
                      :class="registerMode === 'join' ? 'bg-amber-400 text-slate-950' : 'bg-slate-100 text-slate-500'"
                    >
                      <i class="pi pi-users text-xl" />
                    </div>
                    <div
                      v-if="registerMode === 'join'"
                      class="w-5 h-5 rounded-full bg-amber-400 text-slate-950 flex items-center justify-center"
                    >
                      <i class="pi pi-check text-[10px] font-bold" />
                    </div>
                  </div>
                  <div>
                    <h3 class="text-sm font-bold uppercase tracking-tight text-slate-900 mb-1">
                      {{ t('auth.modeJoin') }}
                    </h3>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium normal-case">
                      {{ t('auth.modeJoinDesc') }}
                    </p>
                  </div>
                </div>
              </div>
              <small
                v-if="isModeLocked"
                class="text-slate-400 text-xs mt-1 flex items-center gap-1.5"
              >
                <i class="pi pi-lock text-[10px]" />
                {{ t('auth.modeLockedByLink') }}
              </small>
            </div>

            <!-- Full Name -->
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
              >
                {{ t('auth.nameRequired') }}
              </small>
            </div>

            <!-- Email -->
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
                placeholder="athlete@example.com"
                :class="{ 'p-invalid': emailTouched && (!email || !isEmailValid) }"
                @blur="emailTouched = true"
              />
              <small
                v-if="emailTouched && !email"
                class="text-red-500 text-xs mt-1"
              >
                {{ t('auth.emailRequired') }}
              </small>
              <small
                v-else-if="emailTouched && !isEmailValid"
                class="text-red-500 text-xs mt-1"
              >
                {{ t('auth.invalidEmail') }}
              </small>
            </div>

            <!-- Gender -->
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
              >
                {{ t('auth.genderRequired') }}
              </small>
            </div>

            <!-- Password -->
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
                  <p class="mt-2 font-bold text-xs uppercase tracking-wider text-slate-700">
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
              >
                {{ t('auth.passwordRequirements') }}
              </small>
            </div>

            <!-- Confirm Password -->
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
              >
                {{ t('auth.passwordsMatchRequired') }}
              </small>
            </div>

            <Button
              type="submit"
              severity="primary"
              :label="t('auth.continue')"
              class="btn-primary w-full py-4 text-lg mt-2"
            />
          </form>
        </div>

        <!-- STEP 2: Company Details / Selection -->
        <div v-else-if="step === 2">
          <form
            class="flex flex-col gap-6"
            @submit.prevent="goToStep3"
          >
            <!-- CREATE MODE: Complete Legal & Address configuration -->
            <div
              v-if="registerMode === 'create'"
              class="flex flex-col gap-6"
            >
              <!-- Company Name -->
              <div class="flex flex-col">
                <label
                  for="companyName"
                  class="form-label-base"
                >{{ t('auth.companyNameCreate') }}</label>
                <InputText
                  id="companyName"
                  v-model="companyName"
                  required
                  placeholder="CrossFit Hamburg"
                  :class="{ 'p-invalid': companyNameTouched && !companyName }"
                  @blur="companyNameTouched = true"
                />
                <small
                  v-if="companyNameTouched && !companyName"
                  class="text-red-500 text-xs mt-1"
                >
                  {{ t('auth.companyRequired') }}
                </small>
              </div>

              <!-- Representative (Legal Representative) -->
              <div class="flex flex-col">
                <label
                  for="rep"
                  class="form-label-base"
                >{{ t('settings.representative') }}</label>
                <InputText
                  id="rep"
                  v-model="legalNoticeRepresentative"
                  required
                  placeholder="Max Mustermann"
                  :class="{ 'p-invalid': legalNoticeRepresentativeTouched && !legalNoticeRepresentative }"
                  @blur="legalNoticeRepresentativeTouched = true"
                />
                <small
                  v-if="legalNoticeRepresentativeTouched && !legalNoticeRepresentative"
                  class="text-red-500 text-xs mt-1"
                >
                  {{ t('auth.repRequired') }}
                </small>
              </div>

              <!-- Address: Street and House Number -->
              <div class="grid grid-cols-4 gap-4">
                <div class="flex flex-col col-span-3">
                  <label
                    for="street"
                    class="form-label-base"
                  >{{ t('settings.street') }}</label>
                  <InputText
                    id="street"
                    v-model="legalNoticeStreet"
                    required
                    placeholder="Heideweg"
                    :class="{ 'p-invalid': legalNoticeStreetTouched && !legalNoticeStreet }"
                    @blur="legalNoticeStreetTouched = true"
                  />
                  <small
                    v-if="legalNoticeStreetTouched && !legalNoticeStreet"
                    class="text-red-500 text-xs mt-1"
                  >
                    {{ t('auth.streetRequired') }}
                  </small>
                </div>
                <div class="flex flex-col col-span-1">
                  <label
                    for="houseNum"
                    class="form-label-base"
                  >{{ t('settings.number') }}</label>
                  <InputText
                    id="houseNum"
                    v-model="legalNoticeHouseNumber"
                    required
                    placeholder="13"
                    :class="{ 'p-invalid': legalNoticeHouseNumberTouched && !legalNoticeHouseNumber }"
                    @blur="legalNoticeHouseNumberTouched = true"
                  />
                  <small
                    v-if="legalNoticeHouseNumberTouched && !legalNoticeHouseNumber"
                    class="text-red-500 text-xs mt-1"
                  >
                    {{ t('auth.houseNumRequired') }}
                  </small>
                </div>
              </div>

              <!-- Address: ZIP Code and City -->
              <div class="grid grid-cols-4 gap-4">
                <div class="flex flex-col col-span-1">
                  <label
                    for="zipCode"
                    class="form-label-base"
                  >{{ t('settings.zipCode') }}</label>
                  <InputText
                    id="zipCode"
                    v-model="legalNoticeZipCode"
                    required
                    placeholder="33659"
                    :class="{ 'p-invalid': legalNoticeZipCodeTouched && !legalNoticeZipCode }"
                    @blur="legalNoticeZipCodeTouched = true"
                  />
                  <small
                    v-if="legalNoticeZipCodeTouched && !legalNoticeZipCode"
                    class="text-red-500 text-xs mt-1"
                  >
                    {{ t('auth.zipRequired') }}
                  </small>
                </div>
                <div class="flex flex-col col-span-3">
                  <label
                    for="city"
                    class="form-label-base"
                  >{{ t('settings.city') }}</label>
                  <InputText
                    id="city"
                    v-model="legalNoticeCity"
                    required
                    placeholder="Bielefeld"
                    :class="{ 'p-invalid': legalNoticeCityTouched && !legalNoticeCity }"
                    @blur="legalNoticeCityTouched = true"
                  />
                  <small
                    v-if="legalNoticeCityTouched && !legalNoticeCity"
                    class="text-red-500 text-xs mt-1"
                  >
                    {{ t('auth.cityRequired') }}
                  </small>
                </div>
              </div>
            </div>

            <!-- JOIN MODE: Select Company with detailed info card -->
            <div
              v-else
              class="flex flex-col gap-6"
            >
              <div class="flex flex-col">
                <label
                  for="companyName"
                  class="form-label-base"
                >{{ t('auth.companyNameJoin') }}</label>
                <InputText
                  id="companyName"
                  v-model="companyName"
                  required
                  placeholder="Enter existing studio name"
                  :class="{ 'p-invalid': companyNameTouched && !companyName }"
                  :disabled="isModeLocked"
                  @blur="companyNameTouched = true"
                />
                <small
                  v-if="companyNameTouched && !companyName"
                  class="text-red-500 text-xs mt-1"
                >
                  {{ t('auth.companyRequired') }}
                </small>
              </div>

              <!-- Visual Info Card explaining Join Flow -->
              <div class="p-6 bg-slate-50 border border-slate-100 rounded-2xl flex flex-col gap-3 text-left">
                <div class="flex items-center gap-2.5 text-amber-500">
                  <i class="pi pi-info-circle text-lg" />
                  <h4 class="text-sm font-bold uppercase tracking-wider text-slate-800">
                    {{ t('auth.joinWarningTitle') }}
                  </h4>
                </div>
                <p class="text-xs text-slate-500 leading-relaxed font-medium">
                  {{ t('auth.joinWarningText') }}
                </p>
              </div>
            </div>

            <!-- Actions buttons -->
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
                type="submit"
                severity="primary"
                :label="cooldownSeconds > 0 ? t('auth.continue') + ' (' + cooldownSeconds + 's)' : t('auth.continue')"
                :loading="loading"
                :disabled="cooldownSeconds > 0"
                class="flex-1 btn-primary py-4 text-lg"
              />
            </div>
          </form>
        </div>

        <!-- STEP 3: Legal & Finalize Confirmation -->
        <div v-else-if="step === 3">
          <div class="space-y-6">
            <!-- CREATE MODE: Optional legal fields & workspace recap -->
            <div
              v-if="registerMode === 'create'"
              class="flex flex-col gap-6"
            >
              <!-- Recap of what will be created -->
              <div class="p-6 bg-slate-50 rounded-2xl border border-slate-100 shadow-sm text-left">
                <div class="flex items-center gap-3 text-primary mb-4">
                  <i class="pi pi-building text-2xl text-amber-500" />
                  <h3 class="text-md font-bold uppercase tracking-tight text-slate-800">
                    {{ t('auth.newCompanyWelcomeTitle') }}
                  </h3>
                </div>
                <ul class="text-xs text-slate-600 space-y-2 font-medium">
                  <li class="flex justify-between border-b border-slate-200/50 pb-2">
                    <span class="text-slate-400">{{ t('auth.gymName') }}:</span>
                    <span class="font-bold text-slate-900">{{ companyName }}</span>
                  </li>
                  <li class="flex justify-between border-b border-slate-200/50 pb-2">
                    <span class="text-slate-400">{{ t('auth.representative') }}:</span>
                    <span class="font-bold text-slate-900">{{ legalNoticeRepresentative }}</span>
                  </li>
                  <li class="flex justify-between border-b border-slate-200/50 pb-2">
                    <span class="text-slate-400">{{ t('auth.address') }}:</span>
                    <span class="font-bold text-slate-900 text-right">
                      {{ legalNoticeStreet }} {{ legalNoticeHouseNumber }}<br>
                      {{ legalNoticeZipCode }} {{ legalNoticeCity }}
                    </span>
                  </li>
                </ul>
              </div>
            </div>

            <!-- JOIN MODE: Existing Company terms acceptance -->
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

            <!-- Actions buttons -->
            <div class="flex gap-4">
              <Button
                type="button"
                severity="secondary"
                :label="t('app.back')"
                icon="pi pi-arrow-left"
                class="flex-1 py-4 text-lg"
                outlined
                @click="step = 2"
              />
              <Button
                type="button"
                severity="primary"
                :label="registerMode === 'create' ? t('auth.acceptAndCreateNewCompany') : t('auth.acceptAndJoin')"
                :loading="loading"
                :disabled="registerMode === 'join' ? !acceptedTerms : false"
                class="flex-1 btn-primary py-4 text-lg"
                @click="register"
              />
            </div>
          </div>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center">
          <p class="font-medium text-slate-600">
            {{ t('auth.alreadyAthlete') }}
            <RouterLink
              to="/login"
              class="text-amber-500 hover:text-amber-600 font-bold underline-offset-4 hover:underline transition-all"
            >
              {{ t('auth.signInHere') }}
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

    <div class="flex flex-col items-center gap-1.5 text-xs text-slate-500 text-center mt-4 pb-4">
      <div class="flex items-center gap-4">
        <a
          href="javascript:void(0)"
          class="hover:primary-text transition font-bold"
          @click="showPlatformLegal = true"
        >
          {{ t('platformLegal.legalNotice') }}
        </a>
        <span class="text-slate-700">•</span>
        <a
          href="javascript:void(0)"
          class="hover:primary-text transition font-bold"
          @click="downloadPlatformPrivacyPolicy()"
        >
          {{ t('platformLegal.privacyPolicy') }}
        </a>
        <span class="text-slate-700">•</span>
        <a
          href="mailto:kubilay.anil@codingcube.de"
          class="hover:primary-text transition font-bold"
        >
          {{ t('platformLegal.support') }}
        </a>
      </div>
    </div>

    <PlatformLegalDialog v-model:visible="showPlatformLegal" />

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
