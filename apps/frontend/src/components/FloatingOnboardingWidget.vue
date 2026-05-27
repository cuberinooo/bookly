<script setup lang="ts">
import { useOnboarding, TASK_METADATA } from '../composables/useOnboarding';
import Button from 'primevue/button';
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const {
    onboardingState,
    currentContextualTask,
    nextPendingTask,
    isComplete,
    completedTasksCount,
    totalTasksCount,
    skipOnboarding,
    filteredMetadata
} = useOnboarding();

const isExpanded = ref(false);

const displayedTask = computed(() => currentContextualTask.value || nextPendingTask.value);
const showWidget = computed(() => !isComplete.value);
const isContextual = computed(() => !!currentContextualTask.value);
const allTasks = computed(() => {
    const tasks = Object.entries(filteredMetadata.value).map(([id, meta]) => ({
        id,
        ...meta,
        isDone: onboardingState.value.includes(id),
        isCurrentRoute: meta.routeName === route.name
    }));

    return tasks.sort((a, b) => {
        // Priority 1: Current contextual task (on this page and not done)
        const aIsContextual = a.isCurrentRoute && !a.isDone;
        const bIsContextual = b.isCurrentRoute && !b.isDone;
        if (aIsContextual && !bIsContextual) return -1;
        if (!aIsContextual && bIsContextual) return 1;

        // Priority 2: Not done tasks
        if (!a.isDone && b.isDone) return -1;
        if (a.isDone && !b.isDone) return 1;

        return 0;
    });
});

function toggleExpand() {
    isExpanded.value = !isExpanded.value;
}

function navigateToTask(task: any) {
    if (task.routeName !== route.name) {
        router.push({ name: task.routeName });
    }
}
</script>

<template>
    <transition name="slide-up">
        <div v-if="showWidget" class="floating-onboarding-container" :class="{ 'is-expanded': isExpanded }">

            <!-- Collapsed Pill -->
            <div
                v-if="!isExpanded"
                class="collapsed-pill shadow-lg border border-primary-100 bg-white cursor-pointer hover:bg-primary-50 transition-all duration-300 flex items-center p-2 rounded-full"
                @click="toggleExpand"
            >
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full text-primary bg-primary flex items-center justify-center shadow-md flex-shrink-0">
                        <i :class="displayedTask.icon" class="text-lg"></i>
                    </div>
                    <div class="flex flex-col pr-2 overflow-hidden">
                        <div class="text-xs font-bold text-gray-900 tracking-tight whitespace-nowrap overflow-hidden text-ellipsis max-w-[12rem]">
                            {{ isContextual ? 'Here: ' + displayedTask.title : 'Next: ' + displayedTask.title }}
                        </div>
                        <div v-if="isContextual" class="text-[9px] text-gray-600 leading-none mt-0.5 whitespace-nowrap overflow-hidden text-ellipsis max-w-[12rem]">
                            {{ displayedTask.description }}
                        </div>
                    </div>
                    <i class="pi pi-chevron-up text-primary-300 mr-2 text-xs"></i>
                </div>
            </div>

            <!-- Expanded Panel -->
            <div
                v-else
                class="expanded-panel shadow-xl border border-primary-100 bg-white rounded-2xl flex flex-col overflow-hidden"
            >
                <!-- Header -->
                <div class="panel-header p-3 flex items-center justify-between bg-primary-50 border-b border-primary-100">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-compass text-primary font-bold"></i>
                        <h3 class="m-0 text-sm font-black text-primary-800 uppercase tracking-widest">Training Guide</h3>
                    </div>
                    <Button
                        icon="pi pi-minus"
                        text
                        rounded
                        severity="secondary"
                        size="small"
                        class="h-8 w-8"
                        @click="toggleExpand"
                    />
                </div>

                <!-- Scrollable Body -->
                <div class="panel-body p-3 overflow-y-auto custom-scrollbar">

                    <!-- All Tasks List -->
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="(task, index) in allTasks"
                            :key="task.id"
                            class="task-item p-3 border rounded-lg transition-all duration-200 cursor-pointer"
                            :class="{
                                'bg-green-50 border-green-200 opacity-70': task.isDone,
                                'bg-gray-50 border-gray-100 hover:border-primary': !task.isDone && !task.isCurrentRoute,
                                'bg-primary-50 border-primary-200': !task.isDone && task.isCurrentRoute
                            }"
                            @click="navigateToTask(task)"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center shadow-sm"
                                    :class="task.isDone ? 'bg-green-500 text-white' : (task.isCurrentRoute ? 'bg-primary text-primary' : 'bg-primary-100 text-primary')"
                                >
                                    <i :class="task.isDone ? 'pi pi-check text-xs' : task.icon + ' text-xs'"></i>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <h5 class="m-0 text-sm font-bold" :class="task.isDone ? 'text-gray-500 line-through' : 'text-gray-900'">
                                                {{ index + 1 }}. {{ task.title }}
                                            </h5>
                                            <span v-if="!task.isDone && task.isCurrentRoute" class="bg-green-100 text-green-700 text-[8px] px-1 rounded font-black uppercase">Active Here</span>
                                        </div>
                                        <span v-if="task.isDone" class="text-[9px] font-black text-green-600 uppercase">Done</span>
                                    </div>
                                    <p class="m-0 text-xs leading-relaxed" :class="task.isDone ? 'text-gray-400' : 'text-gray-600'">
                                        {{ task.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="panel-footer p-3 bg-white border-t border-gray-100 flex items-center justify-between">
                    <div class="flex-grow mr-4">
                        <div class="flex justify-between mb-1">
                            <span class="text-[10px] font-bold text-gray-500 uppercase">{{ completedTasksCount }}/{{ totalTasksCount }} Tasks</span>
                            <span class="text-[10px] font-bold text-primary">{{ Math.round((completedTasksCount / totalTasksCount) * 100) }}%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded overflow-hidden" style="height: 4px">
                            <div
                                class="bg-primary h-full transition-all duration-500"
                                :style="{ width: (completedTasksCount / totalTasksCount) * 100 + '%' }"
                            ></div>
                        </div>
                    </div>
                    <Button
                        label="Skip"
                        size="small"
                        text
                        severity="secondary"
                        class="text-[10px] font-bold uppercase p-2"
                        @click="skipOnboarding"
                    />
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped lang="scss">
.floating-onboarding-container {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    z-index: 100;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.collapsed-pill {
    white-space: nowrap;
    border-radius: 999px;

    @media (hover: hover) {
        &:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
    }
}

.expanded-panel {
    width: 380px;
    height: 300px;
    max-height: calc(100vh - 10rem);
    max-width: calc(100vw - 4rem);
    display: flex;
    flex-direction: column;
}

.panel-body {
    flex-grow: 1;
}

.task-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.custom-scrollbar {
    &::-webkit-scrollbar {
        width: 6px;
    }
    &::-webkit-scrollbar-track {
        background: transparent;
    }
    &::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
        &:hover {
            background: #cbd5e1;
        }
    }
}

@media (max-width: 768px) {
    .floating-onboarding-container {
        bottom: 5.5rem;
        right: 1rem;
        left: 1rem;
    }

    .expanded-panel {
        width: auto;
        max-width: none;
    }
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.slide-up-enter-from {
    opacity: 0;
    transform: translateY(30px) scale(0.95);
}

.slide-up-leave-to {
    opacity: 0;
    transform: translateY(30px) scale(0.95);
}
</style>
