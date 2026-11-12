<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { mdiCheckDecagramOutline, mdiMagnify } from '@mdi/js'
import api, { errorMessage } from '@/services/api'
import { formatDateTime } from '@/utils/labels'

const route = useRoute()
const router = useRouter()

const kode = ref(route.params.kode ?? '')
const result = ref(null)
const loading = ref(false)
const error = ref('')

async function check(value) {
  result.value = null
  error.value = ''
  if (!value) return
  if (!/^[A-Za-z0-9]{6,16}$/.test(value)) {
    error.value = 'Kode verifikasi terdiri dari 6 sampai 16 huruf atau angka.'
    return
  }
  loading.value = true
  try {
    const { data } = await api.get(`/verifikasi/${value}`)
    result.value = data.data
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Kode verifikasi tidak ditemukan. Periksa kembali kode yang tercetak pada dokumen.' : errorMessage(e)
  } finally {
    loading.value = false
  }
}

function submit() {
  const value = kode.value.trim().toUpperCase()
  router.replace({ name: 'verifikasi', params: { kode: value } })
}

watch(() => route.params.kode, (value) => {
  kode.value = value ?? ''
  check(value)
}, { immediate: true })
</script>

<template>
  <v-main class="d-flex justify-center pa-4" style="min-height: 100vh">
    <div style="width: 100%; max-width: 560px" class="pt-md-12">
      <h1 class="text-h5 font-weight-bold mb-1">Verifikasi dokumen soal</h1>
      <p class="text-body-2 text-muted mb-5">
        Masukkan kode verifikasi yang tercetak di bawah tanda tangan KPS untuk memeriksa keaslian soal.
      </p>

      <v-form novalidate class="d-flex ga-2 mb-4" @submit.prevent="submit">
        <v-text-field
          v-model="kode"
          label="Kode verifikasi"
          autocomplete="off"
          hide-details="auto"
          :disabled="loading"
          style="text-transform: uppercase"
        />
        <v-btn type="submit" color="primary" variant="flat" size="x-large" :loading="loading" :prepend-icon="mdiMagnify">Periksa</v-btn>
      </v-form>

      <v-alert v-if="error" type="error" variant="tonal" role="alert">{{ error }}</v-alert>

      <v-card v-if="result" class="pa-5">
        <div class="d-flex align-center ga-2 mb-3">
          <v-icon :icon="mdiCheckDecagramOutline" color="success" aria-hidden="true" />
          <h2 class="text-subtitle-1 font-weight-bold mb-0">Dokumen terdaftar dan disahkan</h2>
        </div>
        <dl class="verify-list text-body-2">
          <dt>Dokumen</dt><dd>{{ result.dokumen }}</dd>
          <dt>Mata kuliah</dt><dd>{{ result.mata_kuliah }}</dd>
          <dt>Program studi</dt><dd>{{ result.prodi }}</dd>
          <dt>Semester</dt><dd>{{ result.semester }}</dd>
          <dt>Disahkan oleh</dt><dd>{{ result.penanda_tangan }}<br />{{ result.jabatan }}</dd>
          <dt>Waktu pengesahan</dt><dd>{{ formatDateTime(result.disahkan_pada) }}</dd>
          <dt>SHA-256 PDF</dt><dd class="hash">{{ result.sha256 }}</dd>
        </dl>
        <v-alert v-if="result.otorisasi_dicabut_pada" type="warning" variant="tonal" density="comfortable" class="mt-4">
          Otorisasi tanda tangan ini dicabut pada {{ formatDateTime(result.otorisasi_dicabut_pada) }}, setelah dokumen disahkan.
        </v-alert>
        <p class="text-caption text-muted mt-4 mb-0">
          Bandingkan SHA-256 di atas dengan hash berkas yang Anda terima. Bila berbeda, berkas sudah diubah setelah disahkan.
        </p>
      </v-card>

      <p class="text-caption mt-6">
        <router-link :to="{ name: 'login' }">Masuk ke PBM Management System</router-link>
      </p>
    </div>
  </v-main>
</template>

<style scoped>
.verify-list {
  display: grid;
  grid-template-columns: minmax(110px, 34%) 1fr;
  gap: 8px 12px;
  margin: 0;
}
.verify-list dt {
  color: var(--pbm-muted);
}
.verify-list dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.hash {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.75rem;
}
</style>
