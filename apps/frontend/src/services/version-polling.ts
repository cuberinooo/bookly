import { ref, onMounted, onUnmounted } from 'vue';

/**
 * Composable to poll for new app versions.
 * @param intervalMs Polling interval in milliseconds (default 30s).
 */
export function useVersionPolling(intervalMs = 30000) {
  const isNewVersionAvailable = ref(false);
  const currentVersion = ref<string | null>(null);
  let intervalId: any = null;

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

  const refreshApp = () => {
    // Force reload from server to get new chunks
    // @ts-ignore - reload(true) is non-standard but supported by some browsers for force-reload
    window.location.reload(true);
  };

  return {
    isNewVersionAvailable,
    refreshApp
  };
}
