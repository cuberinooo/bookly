<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Meetup } from '../services/meetup.service';
import api from '../services/api';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import Checkbox from 'primevue/checkbox';
import FileUpload from 'primevue/fileupload';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const props = defineProps<{
  meetup?: Meetup;
  loading?: boolean;
}>();

const emit = defineEmits<{
  (e: 'submit', data: any): void;
  (e: 'cancel'): void;
}>();

const formData = ref({
  title: '',
  description: '',
  meetupDate: null as Date | null,
  location: '',
  imageUrl: '',
  link: '',
  minParticipants: null as number | null,
  maxParticipants: null as number | null,
  rsvpDeadline: null as Date | null,
  sendNotification: false
});

const selectedFile = ref<File | null>(null);
const localPreviewUrl = ref<string | null>(null);
const isUploading = ref(false);

const fileUploadRef = ref();

const triggerUpload = () => {
  if (fileUploadRef.value) {
    fileUploadRef.value.choose();
  }
};

const onFileSelect = (event: any) => {
  const file = event.files[0];
  if (file) {
    selectedFile.value = file;
    localPreviewUrl.value = URL.createObjectURL(file);
    // Clear existing imageUrl if a new file is picked
    formData.value.imageUrl = '';
  }
};

const removeImage = () => {
  selectedFile.value = null;
  localPreviewUrl.value = null;
  formData.value.imageUrl = '';
};

const displayImageUrl = computed(() => {
  return localPreviewUrl.value || formData.value.imageUrl;
});

onMounted(() => {
  if (props.meetup) {
    formData.value = {
      title: props.meetup.title,
      description: props.meetup.description || '',
      meetupDate: new Date(props.meetup.meetupDate),
      location: props.meetup.location,
      imageUrl: props.meetup.imageUrl || '',
      link: props.meetup.link || '',
      minParticipants: props.meetup.minParticipants,
      maxParticipants: props.meetup.maxParticipants,
      rsvpDeadline: new Date(props.meetup.rsvpDeadline),
      sendNotification: false
    };
  }
});

const handleSubmit = async () => {
  if (formData.value.rsvpDeadline && formData.value.meetupDate && formData.value.rsvpDeadline > formData.value.meetupDate) {
    return;
  }
  isUploading.value = true;
  try {
    let finalImageUrl = formData.value.imageUrl;

    // 1. Handle Upload if a new file was selected
    if (selectedFile.value) {
      const uploadData = new FormData();
      uploadData.append('file', selectedFile.value);

      const response = await api.post('/meetups/upload-image', uploadData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      finalImageUrl = response.data.url;
    }

    // 2. Emit submit with final data
    emit('submit', {
      ...formData.value,
      imageUrl: finalImageUrl
    });
  } catch (e: any) {
    console.error('Upload failed', e);
  } finally {
    isUploading.value = false;
  }
};
</script>

<template>
  <form
    class="meetup-form p-fluid"
    @submit.prevent="handleSubmit"
  >
    <div class="field">
      <label for="title">{{ t('meetup.title') }}</label>
      <InputText
        id="title"
        v-model="formData.title"
        required
        :placeholder="t('meetup.placeholderTitle')"
      />
    </div>

    <div class="field">
      <label for="description">{{ t('meetup.description') }}</label>
      <Textarea
        id="description"
        v-model="formData.description"
        rows="3"
        :placeholder="t('meetup.placeholderDescription')"
      />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="field">
        <label
          for="meetupDate"
        >{{ t('meetup.eventDateTime') }}</label>
        <DatePicker
          id="meetupDate"
          v-model="formData.meetupDate"
          show-time
          hour-format="24"
          required
        />
      </div>
      <div class="field">
        <label
          v-tooltip.top="'Répondez s\'il vous plaît - Please respond by this date to help the organizer plan better.'"
          for="rsvpDeadline"
        >
          RSVP Deadline *
          <i class="pi pi-info-circle text-xs text-slate-400" />
        </label>
        <DatePicker
          id="rsvpDeadline"
          v-model="formData.rsvpDeadline"
          show-time
          hour-format="24"
          :max-date="formData.meetupDate || undefined"
          :class="{ 'p-invalid': formData.rsvpDeadline && formData.meetupDate && formData.rsvpDeadline > formData.meetupDate }"
          required
        />
        <small
          v-if="formData.rsvpDeadline && formData.meetupDate && formData.rsvpDeadline > formData.meetupDate"
          class="p-error"
        >
          Deadline must be before the event.
        </small>
      </div>
    </div>

    <div class="field">
      <label for="location">{{ t('meetup.location') }}</label>
      <InputText
        id="location"
        v-model="formData.location"
        required
        :placeholder="t('meetup.placeholderLocation')"
      />
    </div>

    <div class="field">
      <label for="link">{{ t('meetup.externalLink') }}</label>
      <InputText
        id="link"
        v-model="formData.link"
        :placeholder="t('meetup.placeholderLink')"
      />
    </div>

    <div class="field">
      <label>{{ t('meetup.banner') }}</label>
      <FileUpload
        ref="fileUploadRef"
        mode="basic"
        name="file"
        accept="image/*"
        :max-file-size="5000000"
        class="hidden"
        @select="onFileSelect"
      />

      <div
        v-if="displayImageUrl"
        class="relative group"
      >
        <img
          :src="displayImageUrl"
          class="w-full h-48 object-cover rounded-lg shadow-md border border-slate-200"
        >
        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 rounded-lg">
          <Button
            icon="pi pi-refresh"
            label="Change"
            class="p-button-sm p-button-secondary"
            @click="triggerUpload"
          />
          <Button
            icon="pi pi-trash"
            label="Remove"
            class="p-button-sm p-button-danger"
            @click="removeImage"
          />
        </div>
      </div>
      <div
        v-else
        class="flex flex-col items-center justify-center py-10 border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 hover:bg-slate-100 hover:border-primary transition-all cursor-pointer"
        @click="triggerUpload"
      >
        <i class="pi pi-cloud-upload text-5xl text-slate-400 mb-3" />
        <p class="text-slate-600 font-bold text-lg">
          Drag and drop image here
        </p>
        <p class="text-slate-400 text-sm mt-1">
          or click to browse files
        </p>
        <p class="text-slate-400 text-xs mt-4 uppercase tracking-widest font-semibold">
          Max size: 5MB
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="field">
        <label for="minParticipants">{{ t('meetup.minParticipants') }}</label>
        <InputNumber
          id="minParticipants"
          v-model="formData.minParticipants"
          :min="0"
        />
      </div>
      <div class="field">
        <label for="maxParticipants">{{ t('meetup.maxParticipants') }}</label>
        <InputNumber
          id="maxParticipants"
          v-model="formData.maxParticipants"
          :min="1"
        />
      </div>
    </div>

    <div
      v-if="!meetup"
      class="field flex items-center gap-2 mb-6"
    >
      <Checkbox
        v-model="formData.sendNotification"
        :binary="true"
        input-id="sendNotification"
      />
      <label
        for="sendNotification"
        class="mb-0"
      >{{ t('meetup.notifyMembers') }}</label>
    </div>

    <div class="flex justify-end gap-2 mt-4">
      <Button
        type="button"
        :label="t('meetup.cancel')"
        class="p-button-text"
        :disabled="loading || isUploading"
        @click="emit('cancel')"
      />
      <Button
        type="submit"
        :label="meetup ? t('meetup.update') : t('meetup.create')"
        :loading="loading || isUploading"
        class="p-button-primary"
      />
    </div>
  </form>
</template>

<style scoped lang="scss">
.meetup-form {
  .field {
    margin-bottom: 1.25rem;
  }
}

:deep(.p-button-icon-only) {
  color: var(--bg-primary-color) !important;
}

</style>
