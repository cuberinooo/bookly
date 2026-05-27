<script setup lang="ts">
import { useOnboarding } from '../composables/useOnboarding';
import Card from 'primevue/card';
import ProgressBar from 'primevue/progressbar';
import Button from 'primevue/button';
import { useRouter } from 'vue-router';
import { computed } from 'vue';

const router = useRouter();
const {
    onboardingState,
    isComplete,
    completionPercentage,
    skipOnboarding,
    filteredMetadata
} = useOnboarding();

const props = defineProps({
    alwaysShow: {
        type: Boolean,
        default: false
    }
});

const tasks = computed(() => Object.entries(filteredMetadata.value).map(([id, meta]) => ({
    id,
    ...meta
})));

const visible = computed(() => props.alwaysShow || !isComplete.value);

function isTaskDone(taskId: string) {
    return onboardingState.value.includes(taskId);
}

function navigate(route: string) {
    router.push({ name: route });
}
</script>

<template>
    <Card v-if="visible" class="mb-4 shadow-md rounded-xl overflow-hidden border-t-4 border-primary">
        <template #title>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xl font-bold text-primary">Onboarding Progress</span>
                <span class="text-sm font-medium text-gray-500">{{ completionPercentage }}% Complete</span>
            </div>
        </template>
        <template #subtitle>
            <p class="m-0 text-gray-600">Complete your setup to unlock the full potential of your training journey.</p>
        </template>
        <template #content>
            <div v-if="isComplete" class="text-center py-10">
                <div class="w-16 h-16 rounded-full bg-green-100 text-green-600 flex items-center justify-center mx-auto mb-4">
                    <i class="pi pi-check-circle text-3xl"></i>
                </div>
                <h3 class="text-gray-900 font-bold mb-2">You're all set!</h3>
                <p class="text-gray-600 m-0">You've completed all onboarding tasks. Welcome to the community!</p>
            </div>
            <div v-else>
                <ProgressBar :value="completionPercentage" :showValue="false" class="mb-6 h-4" />

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="task in tasks" :key="task.id" class="h-full">
                        <div
                            class="task-item p-4 rounded-lg transition-all duration-200 cursor-pointer flex items-start gap-3 border h-full"
                            :class="isTaskDone(task.id) ? 'bg-green-50 border-green-200 opacity-70' : 'bg-gray-50 border-gray-100 hover:border-primary hover:-translate-y-0.5 hover:shadow-sm'"
                            @click="navigate(task.routeName)"
                        >
                            <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center"
                                 :class="isTaskDone(task.id) ? 'bg-green-500 text-white' : 'bg-primary-100 text-primary'">
                                <i :class="isTaskDone(task.id) ? 'pi pi-check' : task.icon" class="text-xl"></i>
                            </div>
                            <div class="flex-grow">
                                <h4 class="m-0 text-gray-900 font-bold mb-1" :class="{'line-through text-gray-500': isTaskDone(task.id)}">{{ task.title }}</h4>
                                <p class="m-0 text-sm text-gray-600 mb-2" :class="{'line-through': isTaskDone(task.id)}">{{ task.description }}</p>
                                <span v-if="isTaskDone(task.id)" class="text-xs text-green-600 font-bold uppercase tracking-wider">Completed</span>
                                <span v-else class="text-xs text-primary font-bold uppercase tracking-wider flex items-center">
                                    Start Task <i class="pi pi-arrow-right ml-1 text-[10px]"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <template #footer>
            <div v-if="!isComplete" class="flex justify-end pt-2">
                <Button label="Skip Onboarding" icon="pi pi-times" text severity="secondary" @click="skipOnboarding" />
            </div>
        </template>
    </Card>
</template>

<style scoped>
.task-item:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}
</style>
