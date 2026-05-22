import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useCourseStore } from '../store/useCourseStore';

export function useCourseDeletion() {
    const confirm = useConfirm();
    const toast = useToast();
    const courseStore = useCourseStore();

    const confirmDeleteCourse = (course: any, onSuccess?: () => void) => {
        const isSeries = !!course.seriesId;

        confirm.require({
            message: isSeries
                ? `Do you want to delete the entire series "${course.title}"? This cannot be undone.`
                : `Delete "${course.title}"? This cannot be undone.`,
            header: isSeries ? 'Series Detected' : 'Dangerous Action',
            icon: 'pi pi-exclamation-triangle',
            acceptProps: {
                label: isSeries ? 'Delete Entire Series' : 'Delete',
                severity: 'danger'
            },
            rejectProps: {
              label: isSeries ? 'Delete Only This' : 'Cancel',
              severity: isSeries ? 'warn' : 'primary',
            },
            accept: async () => {
                try {
                    await courseStore.deleteCourse(course.id, isSeries);
                    toast.add({ severity: 'warn', summary: 'Deleted', detail: isSeries ? 'Series removed' : 'Course removed', life: 5000 });
                    if (onSuccess) onSuccess();
                } catch (e) {
                    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
                }
            },
            reject: async () => {
                if (isSeries) {
                    try {
                        await courseStore.deleteCourse(course.id, false);
                        toast.add({ severity: 'warn', summary: 'Deleted', detail: 'Single instance removed', life: 5000 });
                        if (onSuccess) onSuccess();
                    } catch (e) {
                        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete', life: 5000 });
                    }
                }
            }
        });
    };

    return {
        confirmDeleteCourse
    };
}
