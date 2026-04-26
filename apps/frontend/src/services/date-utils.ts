export const formatDatePickerDate = 'dd.mm.yy'; // PrimeVue uses 'yy' for 4-digit year in some versions, but 4.x/v4 uses 'dd.mm.yyyy' or compatible

export function formatDate(date: Date | string | null | undefined): string {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

export function formatDateTime(date: Date | string | null | undefined): string {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

export function formatTime(date: Date | string | null | undefined): string {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleTimeString('de-DE', {
        hour: '2-digit',
        minute: '2-digit'
    });
}
