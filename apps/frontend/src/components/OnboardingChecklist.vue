<script setup lang="ts">
import { useOnboarding, ONBOARDING_TASKS, TASK_METADATA } from '../composables/useOnboarding';
import Card from 'primevue/card';
import ProgressBar from 'primevue/progressbar';
import Button from 'primevue/button';
import { useRouter } from 'vue-router';
import { computed } from 'vue';

const router = useRouter();
const { onboardingState, isComplete, completionPercentage, skipOnboarding } = useOnboarding();

const props = defineProps({
    alwaysShow: {
        type: Boolean,
        default: false
    }
});

const tasks = Object.entries(TASK_METADATA).map(([id, meta]) => ({
    id,
    ...meta
}));

const visible = computed(() => props.alwaysShow || !isComplete.value);

function isTaskDone(taskId: string) {
    return onboardingState.value.includes(taskId);
}

function navigate(route: string) {
    router.push({ name: route });
}
</script>

<template>
    <Card v-if="visible" class="onboarding-card mb-4 shadow-2 border-round-xl overflow-hidden">
        <template #title>
            <div class="flex align-items-center justify-content-between mb-2">
                <span class="text-xl font-bold text-primary">Onboarding Progress</span>
                <span class="text-sm font-medium text-500">{{ completionPercentage }}% Complete</span>
            </div>
        </template>
        <template #subtitle>
            <p class="m-0 text-600">Complete your setup to unlock the full potential of your training journey.</p>
        </template>
        <template #content>
            <div v-if="isComplete" class="text-center py-5">
                <div class="w-4rem h-4rem border-round-full bg-green-100 text-green-600 flex align-items-center justify-content-center mx-auto mb-4">
                    <i class="pi pi-check-circle text-3xl"></i>
                </div>
                <h3 class="text-900 font-bold mb-2">You're all set!</h3>
                <p class="text-600 m-0">You've completed all onboarding tasks. Welcome to the community!</p>
            </div>
            <div v-else>
                <ProgressBar :value="completionPercentage" :showValue="false" class="mb-5 h-1rem" />

                <div class="grid">
                    <div v-for="task in tasks" :key="task.id" class="col-12 lg:col-6 xl:col-4">
                        <div
                            class="task-item p-4 border-round-lg transition-all transition-duration-200 cursor-pointer flex align-items-start gap-3"
                            :class="isTaskDone(task.id) ? 'bg-green-50 border-1 border-green-200 opacity-70' : 'bg-gray-50 border-1 hover:border-primary'"
                            @click="navigate(task.routeName)"
                        >
                            <div class="flex-shrink-0 w-3rem h-3rem border-round-full flex align-items-center justify-content-center"
                                 :class="isTaskDone(task.id) ? 'bg-green-500 text-white' : 'bg-primary-100 text-primary'">
                                <i :class="isTaskDone(task.id) ? 'pi pi-check' : task.icon" class="text-xl"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h4 class="m-0 text-900 mb-1" :class="{'line-through text-600': isTaskDone(task.id)}">{{ task.title }}</h4>
                                <p class="m-0 text-sm text-600 mb-2" :class="{'line-through': isTaskDone(task.id)}">{{ task.description }}</p>
                                <span v-if="isTaskDone(task.id)" class="text-xs text-green-600 font-bold uppercase tracking-wider">Completed</span>
                                <span v-else class="text-xs text-primary font-bold uppercase tracking-wider">Start Task <i class="pi pi-arrow-right ml-1 text-[10px]"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <template #footer>
            <div v-if="!isComplete" class="flex justify-content-end">
                <Button label="Skip Onboarding" icon="pi pi-times" text severity="secondary" @click="skipOnboarding" />
            </div>
        </template>
    </Card>
</template>

<style scoped>
.onboarding-card {
    border-top: 4px solid var(--primary-color);
}
.task-item {
    height: 100%;
}
.task-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}
</style>
