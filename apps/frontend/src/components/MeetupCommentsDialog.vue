<script setup lang="ts">
import { ref, onMounted, nextTick, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../services/api';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Avatar from 'primevue/avatar';
import { useAuthStore } from '../store/useAuthStore';
import { useMeetupStore } from '../store/useMeetupStore';
import { formatDateTime } from '../services/date-utils';

const props = defineProps<{
  meetupId: number;
  meetupTitle: string;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'comment-added'): void;
}>();

const { t } = useI18n();
const authStore = useAuthStore();
const meetupStore = useMeetupStore();
const comments = ref<any[]>([]);
const newComment = ref('');
const loading = ref(false);
const submitting = ref(false);
const scrollContainer = ref<HTMLElement | null>(null);

const fetchComments = async () => {
  loading.value = true;
  try {
    const response = await api.get(`/meetups/${props.meetupId}/comments`);
    comments.value = response.data;
    scrollToBottom();
  } catch (e) {
    console.error('Failed to fetch comments', e);
  } finally {
    loading.value = false;
  }
};

const postComment = async () => {
  if (!newComment.value.trim() || submitting.value) return;

  submitting.value = true;
  try {
    const response = await api.post(`/meetups/${props.meetupId}/comments`, {
      content: newComment.value
    });
    comments.value.push(response.data);
    newComment.value = '';
    emit('comment-added');
    scrollToBottom();
  } catch (e) {
    console.error('Failed to post comment', e);
  } finally {
    submitting.value = false;
  }
};

const markAsRead = async () => {
  try {
    await api.post(`/meetups/${props.meetupId}/comments/mark-read`);
    // Clear local state in store too
    meetupStore.markRead(props.meetupId);
  } catch (e) {
    console.error('Failed to mark as read', e);
  }
};

const scrollToBottom = () => {
  nextTick(() => {
    if (scrollContainer.value) {
      scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
  });
};

const getAvatarUrl = (user: { id: number, profilePicture: string | null }) => {
  if (user.profilePicture) {
    return `${import.meta.env.VITE_API_URL}/user/profile-picture/${user.id}?t=${user.profilePicture}`;
  }
  return null;
};

// Listen for real-time comment updates from Mercure
watch(() => meetupStore.lastCommentUpdate, (update) => {
    if (update.meetupId === props.meetupId) {
        fetchComments();
        markAsRead();
    }
}, { deep: true });

onMounted(() => {
  fetchComments();
});
</script>

<template>
  <div class="comments-container flex flex-col h-[500px]">
    <div
      ref="scrollContainer"
      class="flex-1 overflow-y-auto p-4 space-y-4"
    >
      <div
        v-if="loading"
        class="flex justify-center py-10"
      >
        <i class="pi pi-spin pi-spinner text-3xl text-slate-300" />
      </div>

      <div
        v-else-if="comments.length === 0"
        class="flex flex-col items-center justify-center py-10 text-slate-400"
      >
        <i class="pi pi-comments text-5xl mb-3 opacity-20" />
        <p>{{ t('meetup.noCommentsYet') }}</p>
      </div>

      <div
        v-for="comment in comments"
        :key="comment.id"
        class="flex gap-3"
        :class="comment.author.id === authStore.user?.id ? 'flex-row-reverse' : 'flex-row'"
      >
        <Avatar
          :image="getAvatarUrl(comment.author) || undefined"
          :label="(!comment.author?.profilePicture && comment.author?.name) ? comment.author.name.charAt(0) : undefined"
          :icon="(!comment.author?.profilePicture && !comment.author?.name) ? 'pi pi-user' : undefined"
          shape="circle"
          size="normal"
          class="flex-shrink-0 border border-slate-200 shadow-sm"
        />
        <div
          class="flex flex-col max-w-[85%]"
          :class="comment.author.id === authStore.user?.id ? 'items-end' : 'items-start'"
        >
          <div
            class="flex items-center gap-2 mb-1 px-1"
            :class="comment.author.id === authStore.user?.id ? 'flex-row-reverse' : 'flex-row'"
          >
            <span class="text-xs font-black uppercase tracking-wider text-slate-500">{{ comment.author.name }}</span>
            <span class="text-[10px] text-slate-400 font-mono">{{ formatDateTime(comment.createdAt) }}</span>
          </div>
          <div
            class="p-3 rounded-2xl text-sm shadow-sm border"
            :class="[
              comment.author.id === authStore.user?.id 
                ? 'bg-primary text-white border-primary rounded-tr-none' 
                : 'bg-white text-slate-800 border-slate-100 rounded-tl-none'
            ]"
          >
            {{ comment.content }}
          </div>
        </div>
      </div>
    </div>

    <div class="p-4 border-t border-slate-100 bg-slate-50 rounded-b-xl">
      <form
        class="flex gap-2"
        @submit.prevent="postComment"
      >
        <InputText
          v-model="newComment"
          :placeholder="t('meetup.writeComment')"
          class="flex-1"
          :disabled="submitting"
        />
        <Button
          type="submit"
          severity="secondary"
          icon="pi pi-send"
          :loading="submitting"
          :disabled="!newComment.trim()"
        />
      </form>
    </div>
  </div>
</template>

<style scoped lang="scss">
.comments-container {
  max-height: 70vh;
}

::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: #e2e8f0;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: #cbd5e1;
}
</style>
