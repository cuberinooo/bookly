<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '../services/api';

const name = ref('');
const email = ref('');
const password = ref('');
const role = ref('ROLE_MEMBER');
const error = ref('');
const router = useRouter();

async function register() {
  try {
    await api.post('/register', {
      name: name.value,
      email: email.value,
      password: password.value,
      role: role.value,
    });
    router.push({ name: 'login' });
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Registration failed';
  }
}
</script>

<template>
  <div class="register-container">
    <h2>Register</h2>
    <form @submit.prevent="register">
      <div class="form-group">
        <label>Full Name</label>
        <input v-model="name" type="text" required />
      </div>
      <div class="form-group">
        <label>Email</label>
        <input v-model="email" type="email" required />
      </div>
      <div class="form-group">
        <label>Password</label>
        <input v-model="password" type="password" required />
      </div>
      <div class="form-group">
        <label>Role</label>
        <select v-model="role">
          <option value="ROLE_MEMBER">Member</option>
          <option value="ROLE_TRAINER">Trainer</option>
        </select>
      </div>
      <button type="submit">Register</button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>
    <p>Already have an account? <RouterLink to="/login">Login here</RouterLink></p>
  </div>
</template>

<style scoped lang="scss">
.register-container {
  max-width: 400px;
  margin: 2rem auto;
  padding: 1rem;
  border: 1px solid #ccc;
  border-radius: 8px;
}
.form-group {
  margin-bottom: 1rem;
  label {
    display: block;
    margin-bottom: 0.5rem;
  }
  input, select {
    width: 100%;
    padding: 0.5rem;
    box-sizing: border-box;
  }
}
button {
  width: 100%;
  padding: 0.75rem;
  background-color: #42b983;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.error {
  color: red;
  margin-top: 1rem;
}
</style>
