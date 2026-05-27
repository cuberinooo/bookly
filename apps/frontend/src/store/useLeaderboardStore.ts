import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '../services/api';

export const useLeaderboardStore = defineStore('leaderboard', () => {
    const monthlyStats = ref<any[]>([]);
    const exercises = ref<any[]>([]);
    const records = ref<Record<string, any[]>>({});
    const myRecords = ref<any[]>([]);
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

    const fetchMyRecords = async () => {
        try {
            const response = await api.get('/leaderboard/my-records');
            myRecords.value = response.data;
        } catch (error) {
            console.error('Failed to fetch my records:', error);
            throw error;
        }
    };

    const submitRecord = async (exerciseName: string, weightValue: number) => {
        try {
            await api.post('/leaderboard/workout-records', {
                exerciseName,
                weightValue,
            });
            await Promise.all([
                fetchRecords(),
                fetchMyRecords()
            ]);
        } catch (error) {
            console.error('Failed to submit workout record:', error);
            throw error;
        }
    };

    const deleteRecord = async (id: number) => {
        try {
            await api.delete(`/leaderboard/workout-records/${id}`);
            await Promise.all([
                fetchRecords(),
                fetchMyRecords()
            ]);
        } catch (error) {
            console.error('Failed to delete workout record:', error);
            throw error;
        }
    };

    const loadAll = async () => {
        loading.value = true;
        try {
            await Promise.all([
                fetchMonthlyStats(),
                fetchExercises(),
                fetchRecords(),
                fetchMyRecords()
            ]);
        } finally {
            loading.value = false;
        }
    };

    return {
        monthlyStats,
        exercises,
        records,
        myRecords,
        loading,
        fetchMonthlyStats,
        fetchExercises,
        fetchRecords,
        fetchMyRecords,
        submitRecord,
        deleteRecord,
        loadAll
    };
});
