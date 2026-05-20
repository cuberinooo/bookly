import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';

export const useLeaderboardStore = defineStore('leaderboard', () => {
    const monthlyStats = ref<any[]>([]);
    const exercises = ref<any[]>([]);
    const records = ref<Record<string, any[]>>({});
    const loading = ref(false);

    const fetchMonthlyStats = async () => {
        try {
            const response = await api.get('/leaderboard/monthly-stats');
            monthlyStats.value = response.data;
        } catch (error) {
            console.error('Failed to fetch monthly stats:', error);
            throw error;
        }
    };

    const fetchExercises = async () => {
        try {
            const response = await api.get('/leaderboard/exercises');
            exercises.value = response.data;
        } catch (error) {
            console.error('Failed to fetch exercises:', error);
            throw error;
        }
    };

    const fetchRecords = async () => {
        try {
            const response = await api.get('/leaderboard/workout-records');
            records.value = response.data;
        } catch (error) {
            console.error('Failed to fetch workout records:', error);
            throw error;
        }
    };

    const submitRecord = async (exerciseName: string, weightValue: number) => {
        try {
            await api.post('/leaderboard/workout-records', {
                exerciseName,
                weightValue,
            });
            await fetchRecords(); // Refresh the board after submission
        } catch (error) {
            console.error('Failed to submit workout record:', error);
            throw error;
        }
    };

    const loadAll = async () => {
        loading.value = true;
        try {
            await Promise.all([
                fetchMonthlyStats(),
                fetchExercises(),
                fetchRecords()
            ]);
        } finally {
            loading.value = false;
        }
    };

    return {
        monthlyStats,
        exercises,
        records,
        loading,
        fetchMonthlyStats,
        fetchExercises,
        fetchRecords,
        submitRecord,
        loadAll
    };
});
