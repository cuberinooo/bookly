<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { settingsStore } from '../store/settings';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import {downloadPrivacyPolicy} from "../services/download";

const step = ref(1);
const name = ref('');
const email = ref('');
const companyName = ref('');
const password = ref('');
const passwordTouched = ref(false);
const confirmPassword = ref('');
const role = ref('ROLE_MEMBER');
const acceptedTerms = ref(false);
const loading = ref(false);
const router = useRouter();
const toast = useToast();

const roleOptions = ref([]);
const companyLegal = ref({ found: false, companyName: '', termsAndConditionsMarkdown: '', legalNoticeMarkdown: '', privacyPolicyPdfPath: '' });
const showTermsModal = ref(false);

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
    return name.value && email.value && companyName.value && isPasswordValid.value && passwordValidation.value.match;
});

const isFormValid = computed(() => {
    return isStep1Valid.value && acceptedTerms.value;
});

const renderedTerms = computed(() => {
  const md = companyLegal.value?.termsAndConditionsMarkdown || '# Terms & Conditions\n\nPlease agree to our general terms of service to continue.';
  return DOMPurify.sanitize(marked.parse(md) as string);
});

async function fetchRoles() {
    try {
        const response = await api.get('/register/roles');
        roleOptions.value = response.data;
    } catch (err) {
        console.error('Failed to fetch roles', err);
    }
}

async function goToStep2() {
  if (!isStep1Valid.value) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Please fill in all fields correctly.', life: 3000 });
    return;
  }

  loading.value = true;
  try {
    const response = await api.get(`/register/company-legal?name=${encodeURIComponent(companyName.value)}`);
    companyLegal.value = response.data;
    step.value = 2;
    window.scrollTo(0, 0);
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch company information.', life: 3000 });
  } finally {
    loading.value = false;
  }
}

async function register() {
  if (!isFormValid.value) {
      toast.add({ severity: 'error', summary: 'Error', detail: 'Please agree to the terms and conditions.', life: 5000 });
      return;
  }

  loading.value = true;
  try {
    await api.post('/register', {
      name: name.value,
      email: email.value,
      companyName: companyName.value,
      password: password.value,
      role: role.value,
    });
    toast.add({ severity: 'success', summary: 'Check your email', detail: 'Account created! Please verify your email before logging in.', life: 5000 });
    router.push({ name: 'login' });
  } catch (err: any) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.response?.data?.error || 'Registration failed', life: 5000 });
  } finally {
    loading.value = false;
  }
}

onMounted(fetchRoles);
</script>

<template>
  <div class="min-h-[80vh] flex items-center justify-center bg-white px-4 py-12">
    <div class="phoenix-card w-full max-w-2xl">
      <div class="text-center mb-10">
        <h1 class="text-3xl font-extrabold tracking-tight">
          {{ step === 1 ? 'Join Bookly' : 'Legal Agreement' }}
        </h1>
        <p class="text-slate-600 mt-2 font-medium">
          {{ step === 1 ? 'Start your athletic transformation' : 'Please review and accept the following terms' }}
        </p>
      </div>

      <div v-if="step === 1">
        <form
          class="flex flex-col gap-6"
          @submit.prevent="goToStep2"
        >
          <div class="flex flex-col">
            <label
              for="name"
              class="form-label-base"
            >Full Name</label>
            <InputText
              id="name"
              v-model="name"
              required
              placeholder="Coach Carter"
            />
          </div>

          <div class="flex flex-col">
            <label
              for="email"
              class="form-label-base"
            >Email Address</label>
            <InputText
              id="email"
              v-model="email"
              type="email"
              required
              :placeholder="'athlete@' + settingsStore.companyName.toLowerCase().replace(/[^a-z0-9]/g, '-') + '.com'"
            />
          </div>

          <div class="flex flex-col">
            <label
              for="companyName"
              class="form-label-base"
            >Company Name</label>
            <InputText
              id="companyName"
              v-model="companyName"
              required
              placeholder="Phoenix Athletics"
            />
          </div>

          <div class="flex flex-col">
            <label
              for="password"
              class="form-label-base"
            >Password</label>
            <Password
              id="password"
              v-model="password"
              toggle-mask
              required
              placeholder="••••••••"
              class="w-full"
              input-class="w-full"
              :class="{ 'p-invalid': passwordTouched && !isPasswordValid }"
              @blur="passwordTouched = true"
            >
              <template #footer>
                <Divider />
                <p class="mt-2 font-bold text-xs uppercase tracking-wider">
                  Requirements
                </p>
                <ul class="pl-2 ml-2 mt-2 list-disc flex flex-col gap-1 text-xs">
                  <li :class="passwordValidation.minLength ? 'text-green-600' : 'text-slate-400'">
                    At least 8 characters
                  </li>
                  <li :class="passwordValidation.uppercase ? 'text-green-600' : 'text-slate-400'">
                    At least one uppercase
                  </li>
                  <li :class="passwordValidation.lowercase ? 'text-green-600' : 'text-slate-400'">
                    At least one lowercase
                  </li>
                  <li :class="passwordValidation.number ? 'text-green-600' : 'text-slate-400'">
                    At least one number
                  </li>
                  <li :class="passwordValidation.special ? 'text-green-600' : 'text-slate-400'">
                    At least one special character
                  </li>
                </ul>
              </template>
            </Password>
          </div>

          <div class="flex flex-col">
            <label
              for="confirmPassword"
              class="form-label-base"
            >Confirm Password</label>
            <InputText
              id="confirmPassword"
              v-model="confirmPassword"
              type="password"
              required
              placeholder="••••••••"
              :class="{ 'p-invalid': confirmPassword && !passwordValidation.match }"
            />
          </div>

          <div class="flex flex-col">
            <label
              for="role"
              class="form-label-base"
            >Account Type</label>
            <Select
              id="role"
              v-model="role"
              :options="roleOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>

          <Button
            type="submit"
            severity="primary"
            label="Continue"
            :loading="loading"
            :disabled="!isStep1Valid"
            class="btn-primary w-full py-4 text-lg"
          />
        </form>
      </div>

      <div v-else-if="step === 2">
        <div class="space-y-8">
          <div class="flex items-start gap-4 p-5 bg-primary/5 rounded-2xl border border-primary/10">
            <Checkbox
              id="terms"
              v-model="acceptedTerms"
              :binary="true"
              class="mt-1"
            />
            <label
              for="terms"
              class="text-sm text-slate-700 font-medium leading-relaxed cursor-pointer"
            >
              I agree to the
              <span class="font-bold text-primary">Terms & Conditions (AGB)</span>
              of {{ companyLegal.found ? companyLegal.companyName : companyName }}
              and I have read the
              <a href="javascript:void(0)"
                 @click="downloadPrivacyPolicy()"
                 class="text-primary font-bold hover:underline">
                Privacy Policy (Datenschutz)
              </a>
            </label>
          </div>

          <div class="flex gap-4">
            <Button
              type="button"
              severity="secondary"
              label="Back"
              icon="pi pi-arrow-left"
              @click="step = 1"
              class="flex-1 py-4 text-lg"
              outlined
            />
            <Button
              type="button"
              severity="primary"
              label="Accept & Create Account"
              :loading="loading"
              :disabled="!acceptedTerms"
              @click="register"
              class="flex-2 btn-primary py-4 text-lg"
            />
          </div>
        </div>
      </div>

      <div class="mt-8 pt-6 border-t border-slate-50 text-center">
        <p class="font-medium text-slate-600">
          Already an athlete?
          <RouterLink
            to="/login"
            class="text-accent hover:brightness-90 font-bold underline-offset-4 hover:underline transition-all"
          >
            Login here
          </RouterLink>
        </p>
      </div>
    </div>

    <Dialog v-model:visible="showTermsModal" :header="'Terms & Conditions - ' + (companyLegal.found ? companyLegal.companyName : companyName)" :modal="true" class="w-full max-w-3xl" :breakpoints="{'960px': '75vw', '640px': '90vw'}">
      <div class="prose prose-slate max-w-none p-2 markdown-content overflow-y-auto max-h-[60vh]" v-html="renderedTerms"></div>
      <template #footer>
        <div class="flex justify-between items-center w-full">
          <p class="text-xs text-slate-500 italic">Please read carefully before accepting.</p>
          <Button label="I Understand" icon="pi pi-check" @click="showTermsModal = false" class="btn-primary" autofocus />
        </div>
      </template>
    </Dialog>
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
