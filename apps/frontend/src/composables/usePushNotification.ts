import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../services/api';

function urlBase64ToUint8Array(base64String: string) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

export function usePushNotification() {
    const { t } = useI18n();
    const isSupported = ref('serviceWorker' in navigator && 'PushManager' in window);
    const isSubscribed = ref(false);
    const permission = ref<NotificationPermission>(typeof Notification !== 'undefined' ? Notification.permission : 'default');
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Environment Detection
    const isIOS = computed(() => {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) && !(window as any).MSStream;
    });

    const isStandalone = computed(() => {
        return window.matchMedia('(display-mode: standalone)').matches || (navigator as any).standalone === true;
    });

    const showAddToHomeScreenPrompt = computed(() => {
        return isIOS.value && !isStandalone.value;
    });

    const checkSubscription = async () => {
        if (!isSupported.value) return;
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            isSubscribed.value = !!subscription;
            if (typeof Notification !== 'undefined') {
                permission.value = Notification.permission;
            }
        } catch (err: any) {
            console.error('Error checking push subscription:', err);
        }
    };

    const subscribe = async () => {
        if (!isSupported.value) {
            error.value = t('pushNotifications.errNotSupported');
            return;
        }

        if (showAddToHomeScreenPrompt.value) {
            error.value = t('pushNotifications.errAddToHomeScreen');
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            // 1. Request Notification Permission
            const perm = await Notification.requestPermission();
            permission.value = perm;
            if (perm !== 'granted') {
                throw new Error(t('pushNotifications.errPermissionDenied'));
            }

            // 2. Get Service Worker Registration
            const registration = await navigator.serviceWorker.ready;

            // 3. Subscribe to Push Manager
            const publicVapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY;
            if (!publicVapidKey) {
                throw new Error(t('pushNotifications.errVapidKeyMissing'));
            }

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
            });

            // 4. Send subscription details to backend
            const payload = JSON.parse(JSON.stringify(subscription));
            await api.post('/push-subscriptions', payload);

            isSubscribed.value = true;
        } catch (err: any) {
            console.error('Failed to subscribe user to Web Push:', err);
            error.value = err.message || t('pushNotifications.errSubscriptionFailed');
        } finally {
            loading.value = false;
        }
    };

    const unsubscribe = async () => {
        if (!isSupported.value) return;

        loading.value = true;
        error.value = null;

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (subscription) {
                // 1. Notify backend of unsubscribe
                await api.post('/push-subscriptions/unsubscribe', {
                    endpoint: subscription.endpoint
                });

                // 2. Unsubscribe locally
                await subscription.unsubscribe();
            }

            isSubscribed.value = false;
        } catch (err: any) {
            console.error('Failed to unsubscribe:', err);
            error.value = err.message || t('pushNotifications.errUnsubscriptionFailed');
        } finally {
            loading.value = false;
        }
    };

    return {
        isSupported,
        isSubscribed,
        permission,
        loading,
        error,
        isIOS,
        isStandalone,
        showAddToHomeScreenPrompt,
        checkSubscription,
        subscribe,
        unsubscribe
    };
}
