import api from './api';

export interface TrainingCategory {
    id: number;
    name: string;
    colorHex: string;
    description?: string;
}

export interface CycleAssignment {
    id?: number;
    weekNumber: number;
    dayOfWeek: number;
    category: TrainingCategory;
    categoryId?: number; // Used for writing
}

export interface TrainingCycle {
    id: number;
    name: string;
    startDate: string;
    durationWeeks: number;
    isActive: boolean;
    assignments: CycleAssignment[];
}

export const trainingCycleService = {
    async getCategories() {
        const response = await api.get<TrainingCategory[]>('/training-cycles/categories');
        return response.data;
    },

    async createCategory(data: { name: string; colorHex: string; description?: string }) {
        const response = await api.post<{ id: number }>('/training-cycles/categories', data);
        return response.data;
    },

    async updateCategory(id: number, data: { name?: string; colorHex?: string; description?: string }) {
        await api.patch(`/training-cycles/categories/${id}`, data);
    },

    async deleteCategory(id: number) {
        await api.delete(`/training-cycles/categories/${id}`);
    },

    async getCycles() {
        const response = await api.get<TrainingCycle[]>('/training-cycles');
        return response.data;
    },

    async saveCycle(data: any) {
        const response = await api.post<{ id: number }>('/training-cycles', data);
        return response.data;
    },

    async toggleStatus(isActive: boolean) {
        await api.patch('/training-cycles/status', { isActive });
    },

    async deleteCycle(id: number) {
        await api.delete(`/training-cycles/${id}`);
    }
};
