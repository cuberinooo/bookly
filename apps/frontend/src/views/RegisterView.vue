<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const name = ref('');
const email = ref('');
const password = ref('');
const passwordTouched = ref(false);
const confirmPassword = ref('');
const role = ref('ROLE_MEMBER');
const acceptedTerms = ref(false);
const loading = ref(false);
const router = useRouter();
const toast = useToast();

const roleOptions = ref([]);

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

const isFormValid = computed(() => {
    return name.value && email.value && isPasswordValid.value && passwordValidation.value.match && acceptedTerms.value;
});

const privacyPolicyDownloadUrl = computed(() => {
  return `${api.defaults.baseURL}/legal-settings/privacy-policy/download`;
});

async function fetchRoles() {
    try {
        const response = await api.get('/register/roles');
        roleOptions.value = response.data;
    } catch (err) {
        console.error('Failed to fetch roles', err);
    }
}

async function register() {
  if (!isFormValid.value) {
      if (password.value !== confirmPassword.value) {
          toast.add({ severity: 'error', summary: 'Error', detail: 'Passwords do not match', life: 5000 });
      } else {
          toast.add({ severity: 'error', summary: 'Error', detail: 'Please meet all password requirements', life: 5000 });
      }
      return;
  }

  loading.value = true;
  try {
    await api.post('/register', {
      name: name.value,
      email: email.value,
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
    <div class="phoenix-card w-full max-w-md">
      <div class="text-center mb-10">
        <h1 class="text-3xl font-extrabold tracking-tight">
          Join the Phoenix
        </h1>
        <p class="text-slate-600 mt-2 font-medium">
          Start your athletic transformation
        </p>
      </div>

      <form
        class="flex flex-col gap-6"
        @submit.prevent="register"
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
            placeholder="athlete@phoenix.com"
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
          <ul
            v-if="passwordTouched && !isPasswordValid"
            class="mt-2 flex flex-col gap-1 text-xs font-bold"
          >
            <li
              v-if="!passwordValidation.minLength"
              class="text-red-500"
            >
              • At least 8 characters
            </li>
            <li
              v-if="!passwordValidation.uppercase"
              class="text-red-500"
            >
              • At least one uppercase
            </li>
            <li
              v-if="!passwordValidation.lowercase"
              class="text-red-500"
            >
              • At least one lowercase
            </li>
            <li
              v-if="!passwordValidation.number"
              class="text-red-500"
            >
              • At least one number
            </li>
            <li
              v-if="!passwordValidation.special"
              class="text-red-500"
            >
              • At least one special character
            </li>
          </ul>
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
          <small
            v-if="confirmPassword && !passwordValidation.match"
            class="text-red-500 mt-1 font-bold"
          >Passwords do not match</small>
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

        <div class="flex items-start gap-3 mt-2">
          <Checkbox
            id="terms"
            v-model="acceptedTerms"
            :binary="true"
            class="mt-1"
          />
          <label
            for="terms"
            class="text-sm text-slate-600 font-medium leading-tight cursor-pointer"
          >
            I agree to the
            <RouterLink
              to="/terms"
              target="_blank"
              class="text-primary font-bold hover:underline"
            >Terms & Conditions (AGB)</RouterLink>
            and have read the
            <a
              :href="privacyPolicyDownloadUrl"
              target="_blank"
              class="text-primary font-bold hover:underline"
            >Privacy Policy</a>.
          </label>
        </div>

        <Button
          type="submit"
          severity="primary"
          label="Create Account"
          :loading="loading"
          :disabled="!isFormValid"
          class="btn-primary w-full py-4 text-lg"
        />
      </form>

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
  </div>
</template>

<style scoped>
:deep(.p-select-label) {
  color: unset;
}
/* No component-level styling needed; everything is global */
</style>
