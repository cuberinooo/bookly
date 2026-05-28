<script setup lang="ts">
import { ref, onMounted } from 'vue';
import api from '../services/api';
import { useToast } from 'primevue/usetoast';
import MarkdownPreview from './MarkdownPreview.vue';
import { downloadWelcomeAttachment } from '../services/download';

const toast = useToast();
const settings = ref({
    welcomeMailMarkdown: '',
    welcomeMailAttachments: [] as { name: string, path: string }[],
    membershipWelcomeMailMarkdown: '',
    membershipWelcomeMailAttachments: [] as { name: string, path: string }[]
});
const loading = ref(true);
const saving = ref(false);
const uploadingWelcome = ref(false);
const uploadingMembershipWelcome = ref(false);

async function fetchSettings() {
    loading.value = true;
    try {
        const response = await api.get('/admin-settings');
        settings.value = {
            welcomeMailMarkdown: response.data.welcomeMailMarkdown || '',
            welcomeMailAttachments: response.data.welcomeMailAttachments || [],
            membershipWelcomeMailMarkdown: response.data.membershipWelcomeMailMarkdown || '',
            membershipWelcomeMailAttachments: response.data.membershipWelcomeMailAttachments || []
        };
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load email settings', life: 5000 });
    } finally {
        loading.value = false;
    }
}

async function updateSettings() {
    saving.value = true;
    try {
        await api.patch('/admin-settings', {
            welcomeMailMarkdown: settings.value.welcomeMailMarkdown,
            membershipWelcomeMailMarkdown: settings.value.membershipWelcomeMailMarkdown
        });
        toast.add({ severity: 'success', summary: 'Updated', detail: 'Email templates saved', life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update settings', life: 5000 });
    } finally {
        saving.value = false;
    }
}

async function onUpload(event: any, type: 'welcome' | 'membership-welcome') {
    const file = event.files[0];
    if (!file) return;

    if (type === 'welcome') uploadingWelcome.value = true;
    else uploadingMembershipWelcome.value = true;

    const formData = new FormData();
    formData.append('file', file);

    const endpoint = type === 'welcome' ? '/admin-settings/welcome-attachment' : '/admin-settings/membership-welcome-attachment';

    try {
        const response = await api.post(endpoint, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        if (type === 'welcome') {
            settings.value.welcomeMailAttachments.push(response.data);
        } else {
            settings.value.membershipWelcomeMailAttachments.push(response.data);
        }
        toast.add({ severity: 'success', summary: 'Uploaded', detail: 'Attachment added successfully', life: 5000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to upload attachment', life: 5000 });
    } finally {
        if (type === 'welcome') uploadingWelcome.value = false;
        else uploadingMembershipWelcome.value = false;
    }
}

async function deleteAttachment(path: string, type: 'welcome' | 'membership-welcome') {
    const endpoint = type === 'welcome' ? '/admin-settings/welcome-attachment' : '/admin-settings/membership-welcome-attachment';
    try {
        await api.delete(endpoint, {
            params: { path }
        });
        if (type === 'welcome') {
            settings.value.welcomeMailAttachments = settings.value.welcomeMailAttachments.filter(a => a.path !== path);
        } else {
            settings.value.membershipWelcomeMailAttachments = settings.value.membershipWelcomeMailAttachments.filter(a => a.path !== path);
        }
        toast.add({ severity: 'info', summary: 'Deleted', detail: 'Attachment removed', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete attachment', life: 5000 });
    }
}

onMounted(fetchSettings);
</script>

<template>
  <div class="welcome-mail-settings">
    <div
      v-if="loading"
      class="flex justify-center py-8"
    >
      <i class="pi pi-spin pi-spinner text-3xl text-amber-400" />
    </div>

    <div
      v-else
      class="settings-grid flex flex-col gap-12"
    >
      <div class="p-4 bg-blue-50 border-l-4 border-blue-500 text-blue-900 text-sm">
        <p class="font-bold mb-1">
          Dynamic Placeholders
        </p>
        <p>Use <code>{user_name}</code> for the athlete's name and <code>{company_name}</code> for your company name in any template.</p>
      </div>

      <!-- Welcome Mail Section -->
      <section class="flex flex-col gap-6">
        <div class="settings-card phoenix-card">
          <div class="flex flex-col gap-1 mb-6">
            <h3 class="settings-title mb-0 border-b-0 pb-0">
              Welcome Mail (New Users)
            </h3>
            <p class="text-sm text-slate-500">
              This email is sent to newly registered users as a welcome message.
            </p>
          </div>

          <div class="field">
            <label
              class="secondary-text"
              for="welcomeMarkdown"
            >Email Body (Markdown)</label>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Textarea
                id="welcomeMarkdown"
                v-model="settings.welcomeMailMarkdown"
                rows="15"
                placeholder="Dear {user_name},&#10;&#10;Welcome to {company_name}!..."
                class="w-full font-mono text-sm"
              />
              <MarkdownPreview
                :content="settings.welcomeMailMarkdown"
                title="Welcome Email Preview"
                placeholder="Your welcome email content will appear here..."
                css-style="background-color: white !important"
              />
            </div>
          </div>

          <div class="mt-8">
            <h4 class="font-bold uppercase text-xs text-slate-400 mb-4 tracking-widest">
              Welcome Mail Attachments
            </h4>
            <div class="flex flex-col gap-4 mb-6">
              <div
                v-for="att in settings.welcomeMailAttachments"
                :key="att.path"
                class="p-4 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between"
              >
                <div class="flex items-center gap-3">
                  <i class="pi pi-file text-slate-400 text-xl" />
                  <div>
                    <span class="font-bold text-slate-700">{{ att.name }}</span>
                    <p class="text-xs text-slate-500">
                      {{ att.path }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Button
                    icon="pi pi-download"
                    severity="secondary"
                    variant="text"
                    rounded
                    @click="downloadWelcomeAttachment(att.path, att.name)"
                  />
                  <Button
                    icon="pi pi-trash"
                    severity="danger"
                    variant="text"
                    rounded
                    @click="deleteAttachment(att.path, 'welcome')"
                  />
                </div>
              </div>

              <div
                v-if="settings.welcomeMailAttachments.length === 0"
                class="text-center py-6 border-2 border-dashed border-slate-200 rounded-xl"
              >
                <p class="text-slate-400 italic">
                  No attachments for Welcome Mail.
                </p>
              </div>
            </div>

            <FileUpload
              mode="basic"
              name="file"
              :auto="true"
              custom-upload
              choose-label="Upload Welcome Attachment"
              :disabled="uploadingWelcome"
              class="w-full"
              @uploader="onUpload($event, 'welcome')"
            />
          </div>
        </div>
      </section>

      <!-- Membership Welcome Mail Section -->
      <section class="flex flex-col gap-6">
        <div class="settings-card phoenix-card">
          <div class="flex flex-col gap-1 mb-6">
            <h3 class="settings-title mb-0 border-b-0 pb-0">
              Membership Welcome Mail (Subscribers)
            </h3>
            <p class="text-sm text-slate-500">
              This email is automatically sent when a user successfully subscribes to a paid membership. Use this to provide essential onboarding information, studio rules, or access details.
            </p>
          </div>

          <div class="field">
            <label
              class="secondary-text"
              for="membershipWelcomeMarkdown"
            >Email Body (Markdown)</label>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <Textarea
                id="membershipWelcomeMarkdown"
                v-model="settings.membershipWelcomeMailMarkdown"
                rows="15"
                placeholder="Dear {user_name},&#10;&#10;Welcome to {company_name}! We are thrilled to have you as a full member..."
                class="w-full font-mono text-sm"
              />
              <MarkdownPreview
                :content="settings.membershipWelcomeMailMarkdown"
                title="Membership Welcome Email Preview"
                placeholder="Your membership welcome email content will appear here..."
                css-style="background-color: white !important"
              />
            </div>
          </div>

          <div class="mt-8">
            <h4 class="font-bold uppercase text-xs text-slate-400 mb-4 tracking-widest">
              Membership Welcome Mail Attachments
            </h4>
            <div class="flex flex-col gap-4 mb-6">
              <div
                v-for="att in settings.membershipWelcomeMailAttachments"
                :key="att.path"
                class="p-4 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between"
              >
                <div class="flex items-center gap-3">
                  <i class="pi pi-file text-slate-400 text-xl" />
                  <div>
                    <span class="font-bold text-slate-700">{{ att.name }}</span>
                    <p class="text-xs text-slate-500">
                      {{ att.path }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Button
                    icon="pi pi-download"
                    severity="secondary"
                    variant="text"
                    rounded
                    @click="downloadWelcomeAttachment(att.path, att.name)"
                  />
                  <Button
                    icon="pi pi-trash"
                    severity="danger"
                    variant="text"
                    rounded
                    @click="deleteAttachment(att.path, 'membership-welcome')"
                  />
                </div>
              </div>

              <div
                v-if="settings.membershipWelcomeMailAttachments.length === 0"
                class="text-center py-6 border-2 border-dashed border-slate-200 rounded-xl"
              >
                <p class="text-slate-400 italic">
                  No attachments for Membership Welcome Mail.
                </p>
              </div>
            </div>

            <FileUpload
              mode="basic"
              name="file"
              :auto="true"
              custom-upload
              choose-label="Upload Membership Welcome Attachment"
              :disabled="uploadingMembershipWelcome"
              class="w-full"
              @uploader="onUpload($event, 'membership-welcome')"
            />
          </div>
        </div>
      </section>

      <div class="fixed-save-bar sticky bottom-6 z-10 flex justify-center">
        <Button
          severity="primary"
          label="Save All Templates"
          icon="pi pi-save"
          size="large"
          class="shadow-xl px-10 rounded-full"
          :loading="saving"
          @click="updateSettings"
        />
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

    @media (max-width: 640px) {
      padding: 1.5rem;
    }
}

.settings-title {
    @apply text-xl font-black uppercase tracking-tighter text-slate-900 mb-8 pb-4 border-b border-slate-100;
    font-family: 'Barlow Condensed', sans-serif;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;

    label {
        @apply font-bold uppercase text-xs text-slate-400;
        font-family: 'Barlow Condensed', sans-serif;
    }
}

.sticky {
  @apply backdrop-blur-sm bg-white/30 p-4 rounded-full;
}
</style>
