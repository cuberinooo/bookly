import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Composable to poll for new app versions.
 * @param intervalMs Polling interval in milliseconds (default 30s).
 */
export function useVersionPolling(intervalMs = 30000) {
  const isNewVersionAvailable = ref(false);
  const currentVersion = ref<string | null>(null);
  let intervalId: ReturnType<typeof setInterval> | null = null;

  const fetchVersion = async () => {
    try {
      // Fetch version.json with a cache-busting timestamp
      const response = await fetch(`/version.json?t=${Date.now()}`, {
        headers: {
          'Cache-Control': 'no-cache',
          'Pragma': 'no-cache'
        }
      });
      
      if (!response.ok) {
        return null;
      }

      const data = await response.json();
      return data.version;
    } catch (error) {
      // Gracefully handle network errors
      console.warn('[VersionPolling] Failed to check for updates:', error);
      return null;
    }
  };

  const checkVersion = async () => {
    const fetchedVersion = await fetchVersion();
    
    if (!fetchedVersion) return;

    // If we don't have a current version yet, set it (initial load)
    if (!currentVersion.value) {
      currentVersion.value = fetchedVersion;
      return;
    }

    // Compare versions
    if (fetchedVersion !== currentVersion.value) {
      console.info('[VersionPolling] New version detected:', fetchedVersion);
      isNewVersionAvailable.value = true;
      
      // Stop polling once we know an update is available
      if (intervalId) {
        clearInterval(intervalId);
      }
    }
  };

  onMounted(async () => {
    // Perform initial check
    await checkVersion();
    
    // Setup background interval
    intervalId = setInterval(checkVersion, intervalMs);
  });

  onUnmounted(() => {
    if (intervalId) {
      clearInterval(intervalId);
    }
  });

  const refreshApp = async () => {
    console.info('[VersionPolling] Refreshing application to latest version...');

    // 1. Clear Cache API storage (Service Worker caches, etc.)
    if ('caches' in window) {
      try {
        const cacheNames = await caches.keys();
        await Promise.all(cacheNames.map((name) => caches.delete(name)));
        console.debug('[VersionPolling] Cache Storage cleared');
      } catch (error) {
        console.warn('[VersionPolling] Cache clearance failed:', error);
      }
    }

    // 2. Force a hard reload by appending a temporary cache-busting query parameter.
    // This is the most reliable way to bypass stubborn browser or proxy caches 
    // when window.location.reload(true) is not supported.
    const url = new URL(window.location.href);
    url.searchParams.set('refresh', Date.now().toString());
    
    // Use location.replace to avoid cluttering the browser history with the refresh URL
    window.location.replace(url.toString());
  };

  return {
    isNewVersionAvailable,
    refreshApp
  };
}
