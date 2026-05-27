<script setup lang="ts">
import UserManagementTab from '../components/UserManagementTab.vue';
</script>

<template>
  <div class="users-view max-w-6xl mx-auto py-6 md:py-12 px-2 md:px-4">
    <div class="mb-6 md:mb-10">
      <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">
        Athlete Management
      </h1>
      <p class="text-sm md:text-base text-slate-600 mt-2 font-medium">
        Manage your athletes, roles, and access permissions
      </p>
    </div>

    <div class="mt-4 md:mt-6">
      <UserManagementTab />
    </div>
  </div>
</template>

<style scoped lang="scss">
.users-view {
    h1 { font-family: 'Barlow Condensed', sans-serif; }
}
</style>
