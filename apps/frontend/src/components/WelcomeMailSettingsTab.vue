<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const settings = ref({
    welcomeMailMarkdown: '',
    welcomeMailAttachments: [] as { name: string, path: string }[]
});
const loading = ref(true);
const saving = ref(false);
const uploading = ref(false);

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/admin-settings');
        settings.value = {
            welcomeMailMarkdown: response.data.welcomeMailMarkdown || '',
            welcomeMailAttachments: response.data.welcomeMailAttachments || []
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load welcome mail settings', life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    saving.value = true;
    try {
        await api.patch('/admin-settings', {
            welcomeMailMarkdown: settings.value.welcomeMailMarkdown
        });
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Welcome mail template saved', life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update settings', life: 5000 });
    } finally {
        saving.value = false;
    }
}

async function onUpload(event: any) {
    const file = event.files[0];
    if (!file) return;

    uploading.value = true;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await api.post('/admin-settings/welcome-attachment', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        settings.value.welcomeMailAttachments.push(response.data);
        toast.add({ severity: 'success', summary: 'Uploaded', detail: 'Attachment added successfully', life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to upload attachment', life: 5000 });
    } finally {
        uploading.value = false;
    }
}

async function deleteAttachment(path: string) {
    try {
        await api.delete('/admin-settings/welcome-attachment', {
            params: { path }
        });
        settings.value.welcomeMailAttachments = settings.value.welcomeMailAttachments.filter(a => a.path !== path);
        toast.add({ severity: 'info', summary: 'Deleted', detail: 'Attachment removed', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete attachment', life: 5000 });
    }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="welcome-mail-settings">
    <div v-if="loading" class="flex justify-center py-8">
      <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
    </div>

    <div v-else class="settings-grid flex flex-col gap-8">
      <div class="settings-card phoenix-card">
        <h3 class="settings-title">Welcome Email Template</h3>
        
        <div class="p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-900 text-sm mb-6">
          <p class="font-bold mb-1">Dynamic Placeholders</p>
          <p>Use <code>{user_name}</code> for the athlete's name and <code>{company_name}</code> for your company name.</p>
        </div>

        <div class="field">
          <label class="secondary-text" for="welcomeMarkdown">Email Body (Markdown)</label>
          <Textarea
            id="welcomeMarkdown"
            v-model="settings.welcomeMailMarkdown"
            rows="20"
            placeholder="Dear {user_name},&#10;&#10;Welcome to {company_name}!..."
            class="w-full font-mono text-sm"
          />
        </div>

        <div class="mt-6 flex justify-end">
          <Button severity="primary" label="Save Template" icon="pi pi-save" :loading="saving" @click="updateSettings" />
        </div>
      </div>

      <div class="settings-card phoenix-card">
        <h3 class="settings-title">Attachments (Contracts, Onboarding Docs)</h3>
        <p class="text-sm text-slate-600 mb-6">
          These files will be automatically attached to the welcome email sent to new members.
        </p>

        <div class="flex flex-col gap-4 mb-6">
          <div 
            v-for="att in settings.welcomeMailAttachments" 
            :key="att.path"
            class="p-4 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between"
          >
            <div class="flex items-center gap-3">
              <i class="pi pi-file text-slate-400 text-xl"></i>
              <div>
                <span class="font-bold text-slate-700">{{ att.name }}</span>
                <p class="text-xs text-slate-500">{{ att.path }}</p>
              </div>
            </div>
            <Button 
                icon="pi pi-trash" 
                severity="danger" 
                variant="text" 
                rounded
                @click="deleteAttachment(att.path)" 
            />
          </div>

          <div v-if="settings.welcomeMailAttachments.length === 0" class="text-center py-6 border-2 border-dashed border-slate-200 rounded-xl">
            <p class="text-slate-400 italic">No attachments configured.</p>
          </div>
        </div>

        <FileUpload
          mode="basic"
          name="file"
          :auto="true"
          custom-upload
          @uploader="onUpload"
          choose-label="Upload New Attachment"
          :disabled="uploading"
          class="w-full"
        />
        <div v-if="uploading" class="text-center mt-2">
          <i class="pi pi-spin pi-spinner mr-2"></i> Uploading...
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.settings-card {
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.settings-title {
    @apply text-lg font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100;
    font-family: 'Barlow Condensed', sans-serif;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;

    label {
        @apply font-bold uppercase text-xs;
        font-family: 'Barlow Condensed', sans-serif;
    }
}
</style>
