<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import api, { errorMessage } from '@/services/api'
import ErrorState from '@/components/ErrorState.vue'

/**
 * Berkas privat diambil lewat API (dengan token), lalu ditampilkan dari blob URL.
 * Tautan langsung ke /api tidak dipakai karena tidak membawa token.
 */
const props = defineProps({
  url: { type: String, default: '' },
  kind: { type: String, default: 'pdf' },
  title: { type: String, required: true },
  height: { type: String, default: '70vh' },
})

const objectUrl = ref('')
const loading = ref(false)
const error = ref('')

async function load() {
  release()
  if (!props.url) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(props.url, { responseType: 'blob', timeout: 60000 })
    objectUrl.value = URL.createObjectURL(data)
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Berkas tidak ditemukan atau Anda tidak memiliki akses.' : errorMessage(e)
  } finally {
    loading.value = false
  }
}

function release() {
  if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
  objectUrl.value = ''
}

watch(() => props.url, load, { immediate: true })
onBeforeUnmount(release)
</script>

<template>
  <div class="file-preview" :style="{ minHeight: kind === 'pdf' ? height : 'auto' }">
    <v-skeleton-loader v-if="loading" type="image" :height="kind === 'pdf' ? height : '120'" aria-label="Memuat pratinjau" />
    <ErrorState v-else-if="error" :message="error" @retry="load" />
    <iframe v-else-if="objectUrl && kind === 'pdf'" :src="objectUrl" :title="title" :style="{ height }" />
    <img v-else-if="objectUrl && kind === 'image'" :src="objectUrl" :alt="title" width="320" height="112" class="signature-img" />
  </div>
</template>

<style scoped>
.file-preview iframe {
  width: 100%;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f1f5f9;
}

.signature-img {
  max-width: 100%;
  height: auto;
  object-fit: contain;
  background: repeating-conic-gradient(#f1f5f9 0% 25%, #ffffff 0% 50%) 50% / 16px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}
</style>
