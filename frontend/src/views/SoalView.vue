<script setup>
import { computed, ref, watch } from 'vue'
import { mdiMagnify, mdiFileSign } from '@mdi/js'
import api, { errorMessage } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { EXAM_TYPES, SOAL_STATUS, formatDateTime } from '@/utils/labels'
import SoalUploadCard from '@/components/SoalUploadCard.vue'
import SoalDetailDialog from '@/components/SoalDetailDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'

const auth = useAuthStore()
const canUpload = computed(() => auth.can('soal.upload'))

const rows = ref([])
const total = ref(0)
const summary = ref({})
const loading = ref(true)
const error = ref('')
const page = ref(1)
const perPage = ref(10)
const filters = ref({ q: '', status: null, exam_type: null })
const selectedId = ref(null)
const lastResult = ref(null)

const headers = [
  { title: 'Mata kuliah', key: 'mata_kuliah', sortable: false },
  { title: 'Ujian', key: 'jenis_ujian', sortable: false, width: 80 },
  { title: 'Versi', key: 'versi', sortable: false, width: 70 },
  { title: 'Dosen', key: 'dosen', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Diunggah', key: 'dibuat', sortable: false },
  { title: '', key: 'aksi', sortable: false, align: 'end', width: 90 },
]

const statusItems = Object.entries(SOAL_STATUS).map(([value, s]) => ({ value, title: s.label }))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = { page: page.value, per_page: perPage.value }
    for (const [k, v] of Object.entries(filters.value)) if (v) params[k] = v
    const { data } = await api.get('/soal', { params })
    rows.value = data.data
    total.value = data.meta.total
    summary.value = data.ringkasan
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

let searchTimer
watch(() => filters.value.q, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})
watch(() => [filters.value.status, filters.value.exam_type], () => {
  page.value = 1
  load()
})

function onOptions({ page: p, itemsPerPage }) {
  page.value = p
  perPage.value = itemsPerPage
  load()
}

function onSubmitted(result) {
  lastResult.value = result
  page.value = 1
  load()
  if (result.status === 'disahkan') selectedId.value = result.id
}

const hasFilters = computed(() => Boolean(filters.value.q || filters.value.status || filters.value.exam_type))
</script>

<template>
  <div>
    <h1 class="text-h5 font-weight-bold mb-1">Soal UTS/UAS</h1>
    <p class="text-body-2 text-muted mb-5">
      Soal yang diunggah dosen disahkan dengan tanda tangan KPS hanya bila KPS sudah memberi otorisasi yang masih berlaku.
    </p>

    <SoalUploadCard v-if="canUpload" class="mb-4" @submitted="onSubmitted" />

    <v-alert
      v-if="lastResult"
      :type="lastResult.status === 'disahkan' ? 'success' : lastResult.status === 'menunggu_otorisasi' ? 'warning' : 'error'"
      variant="tonal"
      closable
      class="mb-4"
      role="status"
      @click:close="lastResult = null"
    >
      <p class="font-weight-bold mb-1">
        <template v-if="lastResult.status === 'disahkan'">Soal {{ lastResult.mata_kuliah.nama }} sudah disahkan.</template>
        <template v-else-if="lastResult.status === 'menunggu_otorisasi'">Soal disimpan, tetapi belum disahkan.</template>
        <template v-else>Soal disimpan, tetapi dokumennya gagal diproses.</template>
      </p>
      <p v-if="lastResult.alasan" class="mb-2">{{ lastResult.alasan }}</p>
      <v-btn size="small" variant="outlined" @click="selectedId = lastResult.id">Lihat detail</v-btn>
    </v-alert>

    <v-card>
      <div class="pa-4 pb-2">
        <div class="d-flex flex-wrap align-center ga-2 mb-3">
          <h2 class="text-subtitle-1 font-weight-bold mb-0 mr-2">Daftar soal</h2>
          <v-chip
            v-for="(s, key) in SOAL_STATUS"
            :key="key"
            :color="s.color"
            size="small"
            variant="tonal"
            label
          >
            {{ s.label }}: {{ summary[key] ?? 0 }}
          </v-chip>
        </div>
        <v-row dense>
          <v-col cols="12" md="5">
            <v-text-field
              v-model="filters.q"
              label="Cari mata kuliah atau kode"
              :prepend-inner-icon="mdiMagnify"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="6" md="4">
            <v-select v-model="filters.status" :items="statusItems" label="Status" clearable hide-details />
          </v-col>
          <v-col cols="6" md="3">
            <v-select v-model="filters.exam_type" :items="EXAM_TYPES" label="Jenis ujian" clearable hide-details />
          </v-col>
        </v-row>
      </div>

      <ErrorState v-if="error" :message="error" @retry="load" />

      <v-data-table-server
        v-else
        :headers="headers"
        :items="rows"
        :items-length="total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50]"
        items-per-page-text="Baris per halaman"
        loading-text="Memuat soal..."
        class="soal-table"
        hover
        @update:options="onOptions"
      >
        <template #item.mata_kuliah="{ item }">
          <span class="font-weight-medium">{{ item.mata_kuliah.kode }}</span>
          <span class="d-block text-body-2">{{ item.mata_kuliah.nama }}</span>
        </template>
        <template #item.jenis_ujian="{ item }">{{ item.jenis_ujian.toUpperCase() }}</template>
        <template #item.status="{ item }">
          <StatusChip :status="item.status" :map="SOAL_STATUS" />
        </template>
        <template #item.dibuat="{ item }">{{ formatDateTime(item.dibuat) }}</template>
        <template #item.aksi="{ item }">
          <v-btn size="small" variant="text" color="secondary" :aria-label="`Lihat detail soal ${item.mata_kuliah.nama} versi ${item.versi}`" @click="selectedId = item.id">
            Detail
          </v-btn>
        </template>
        <template #no-data>
          <EmptyState
            :icon="mdiFileSign"
            :title="hasFilters ? 'Tidak ada soal yang cocok' : 'Belum ada soal'"
            :message="hasFilters ? 'Ubah kata kunci atau filter.' : canUpload ? 'Soal yang Anda unggah akan tampil di sini.' : 'Soal yang diunggah dosen program studi akan tampil di sini.'"
          />
        </template>
      </v-data-table-server>
    </v-card>

    <SoalDetailDialog :soal-id="selectedId" @close="selectedId = null" @changed="load" @open="(id) => (selectedId = id)" />
  </div>
</template>

<style scoped>
.soal-table :deep(td) {
  padding-top: 8px !important;
  padding-bottom: 8px !important;
}
</style>
