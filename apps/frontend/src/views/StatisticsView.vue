<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';

const stats = ref<any>(null);
const loading = ref(true);

const lineData = ref<any>(null);
const lineOptions = ref<any>(null);
const barData = ref<any>(null);
const barOptions = ref<any>(null);

async function fetchStats() {
  try {
    const response = await api.get('/trainer/statistics');
    stats.value = response.data;
    setupCharts();
  } catch (error) {
    console.error('Failed to fetch statistics', error);
  } finally {
    loading.value = false;
  }
}

function setupCharts() {
  const documentStyle = getComputedStyle(document.documentElement);
  const textColor = documentStyle.getPropertyValue('--p-text-color') || '#4b5563';
  const textColorSecondary = documentStyle.getPropertyValue('--p-text-muted-color') || '#9ca3af';
  const surfaceBorder = documentStyle.getPropertyValue('--p-content-border-color') || '#e5e7eb';
  const primaryColor = documentStyle.getPropertyValue('--p-primary-color') || '#3b82f6';

  // Line Chart: Monthly Stats
  lineData.value = {
    labels: stats.value.monthlyStats.map((s: any) => s.month),
    datasets: [
      {
        label: 'Courses Coached',
        data: stats.value.monthlyStats.map((s: any) => s.count),
        fill: false,
        borderColor: primaryColor,
        backgroundColor: primaryColor,
        tension: 0.4
      }
    ]
  };

  lineOptions.value = {
    maintainAspectRatio: false,
    aspectRatio: 0.6,
    plugins: {
      legend: {
        labels: {
          color: textColor
        }
      }
    },
    scales: {
      x: {
        ticks: {
          color: textColorSecondary
        },
        grid: {
          color: surfaceBorder
        }
      },
      y: {
        ticks: {
          color: textColorSecondary,
          stepSize: 1
        },
        grid: {
          color: surfaceBorder
        }
      }
    }
  };

  // Bar Chart: Popular Time Slots
  barData.value = {
    labels: stats.value.popularTimeSlots.map((s: any) => s.hour),
    datasets: [
      {
        label: 'Frequency',
        backgroundColor: primaryColor,
        borderColor: primaryColor,
        data: stats.value.popularTimeSlots.map((s: any) => s.count),
        borderRadius: 4
      }
    ]
  };

  barOptions.value = {
    maintainAspectRatio: false,
    aspectRatio: 0.8,
    plugins: {
      legend: {
        display: false
      }
    },
    scales: {
      x: {
        ticks: {
          color: textColorSecondary
        },
        grid: {
          color: 'transparent'
        }
      },
      y: {
        ticks: {
          color: textColorSecondary,
          stepSize: 1
        },
        grid: {
          color: surfaceBorder
        }
      }
    }
  };
}

onMounted(() => {
  fetchStats();
});
</script>

<template>
  <div class="statistics-page">
    <div class="flex items-center gap-4 mb-8">
      <h1 class="page-title">
        Trainer Statistics
      </h1>
    </div>

    <div
      v-if="loading"
      class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8"
    >
      <Skeleton
        v-for="i in 3"
        :key="i"
        height="160px"
        class="rounded-xl"
      />
    </div>

    <div
      v-else-if="stats"
      class="stats-container"
    >
      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <Card class="stat-card">
          <template #title>
            <div class="flex items-center gap-2 text-slate-400 text-xs uppercase font-bold tracking-widest mb-2">
              <i class="pi pi-calendar" />
              <span>Total Courses</span>
            </div>
          </template>
          <template #content>
            <div class="text-5xl font-black text-slate-800">
              {{ stats.totalCourses }}
            </div>
            <p class="text-slate-400 text-xs mt-3">
              All-time coached sessions
            </p>
          </template>
        </Card>

        <Card class="stat-card">
          <template #title>
            <div class="flex items-center gap-2 text-slate-400 text-xs uppercase font-bold tracking-widest mb-2">
              <i class="pi pi-users" />
              <span>Unique Members</span>
            </div>
          </template>
          <template #content>
            <div class="text-5xl font-black text-slate-800">
              {{ stats.uniqueMembers }}
            </div>
            <p class="text-slate-400 text-xs mt-3">
              Different students reached
            </p>
          </template>
        </Card>

        <Card class="stat-card">
          <template #title>
            <div class="flex items-center gap-2 text-slate-400 text-xs uppercase font-bold tracking-widest mb-2">
              <i class="pi pi-chart-line" />
              <span>Avg. Fill Rate</span>
            </div>
          </template>
          <template #content>
            <div
              class="text-5xl font-black"
              :class="stats.averageFillRate > 80 ? 'text-green-500' : 'text-amber-500'"
            >
              {{ stats.averageFillRate }}%
            </div>
            <p class="text-slate-400 text-xs mt-3">
              Class capacity utilization
            </p>
          </template>
        </Card>
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
        <Card class="chart-card overflow-hidden">
          <template #title>
            <div class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-4">
              Course Volume (12 Months)
            </div>
          </template>
          <template #content>
            <Chart
              type="line"
              :data="lineData"
              :options="lineOptions"
              class="h-[350px] w-full"
            />
          </template>
        </Card>

        <Card class="chart-card overflow-hidden">
          <template #title>
            <div class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-4">
              Popular Time Slots
            </div>
          </template>
          <template #content>
            <Chart
              type="bar"
              :data="barData"
              :options="barOptions"
              class="h-[350px] w-full"
            />
          </template>
        </Card>
      </div>

      <div class="grid grid-cols-1 gap-8">
        <Card class="chart-card">
          <template #title>
            <div class="text-sm font-bold uppercase tracking-wider text-slate-600 mb-6">
              Most Popular Course Types
            </div>
          </template>
          <template #content>
            <div class="flex flex-col gap-6">
              <div
                v-for="(course, index) in stats.popularCourseTypes"
                :key="index"
                class="popular-item"
              >
                <div class="flex justify-between items-center mb-2">
                  <span class="font-bold text-slate-800">{{ course.title }}</span>
                  <span class="text-xs font-bold bg-slate-100 text-slate-600 px-3 py-1 rounded-full">{{ course.count }} sessions</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-3">
                  <div 
                    class="bg-primary-gradient rounded-full h-3 transition-all duration-1000 ease-out" 
                    :style="{ width: (course.count / stats.popularCourseTypes[0].count * 100) + '%' }"
                  />
                </div>
              </div>
              <div
                v-if="stats.popularCourseTypes.length === 0"
                class="text-center py-10 text-slate-400 italic"
              >
                No course data available yet.
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.statistics-page {
  padding: 2.5rem 1.5rem;
  max-width: 1300px;
  margin: 0 auto;

  @media (max-width: 768px) {
    padding: 1.5rem 1rem;
  }
}

.page-title {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 3rem;
  font-weight: 900;
  color: #0f172a;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  margin: 0;
  position: relative;
  line-height: 1;

  &::after {
    content: '';
    display: block;
    width: 60px;
    height: 8px;
    background: var(--p-primary-color);
    margin-top: 0.5rem;
  }
}

.stat-card {
  border: 1px solid rgba(226, 232, 240, 0.8);
  border-radius: 1.25rem;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.05);
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;

  &:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.1);
    border-color: var(--p-primary-color);
  }

  :deep(.p-card-body) {
    padding: 2rem;
  }
}

.chart-card {
  border: 1px solid rgba(226, 232, 240, 0.8);
  border-radius: 1.5rem;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.03);

  :deep(.p-card-body) {
    padding: 2rem;
  }
}

.bg-primary-gradient {
  background: linear-gradient(90deg, var(--p-primary-color), #60a5fa);
}

.popular-item {
  position: relative;
}

:deep(.p-card-title) {
    margin: 0;
}
</style>
