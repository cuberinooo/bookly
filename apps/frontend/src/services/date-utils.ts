export const formatDatePickerDate = 'dd.mm.yy';

export function formatDate(date: Date | string | null | undefined, locale = 'de-DE'): string {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString(locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

export function formatDateWithDay(date: Date | string | null | undefined, short = false, locale = 'de-DE'): string {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString(locale, {
        weekday: short ? 'short' : 'long',
        day: '2-digit',
        month: '2-digit'
    });
}

export function formatDateTime(date: Date | string | null | undefined, locale = 'de-DE'): string {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleString(locale, {
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

export function formatTime(date: Date | string | null | undefined, locale = 'de-DE'): string {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleTimeString(locale, {
        hour: '2-digit',
        minute: '2-digit'
    });
}
