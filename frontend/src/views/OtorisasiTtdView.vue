<script setup>
import { computed, ref, watch } from 'vue'
import { mdiDrawPen, mdiShieldOffOutline } from '@mdi/js'
import api, { errorMessage } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { EXAM_TYPES, OTORISASI_STATUS, formatDate, formatDateTime } from '@/utils/labels'
import FilePreview from '@/components/FilePreview.vue'
import StatusChip from '@/components/StatusChip.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'

const auth = useAuthStore()

const rows = ref([])
const meta = ref({ total: 0 })
const info = ref(null)
const loading = ref(true)
const error = ref('')
const page = ref(1)
const perPage = ref(10)

const formOpen = ref(false)
const form = ref({})
const formErrors = ref({})
const formError = ref('')
const saving = ref(false)
const imagePreview = ref('')
const notice = ref(null)

const revokeTarget = ref(null)
const revokeReason = ref('')
const revokeError = ref('')
const revoking = ref(false)

const headers = computed(() => [
  { title: 'Penanda tangan', key: 'penanda_tangan', sortable: false },
  ...(auth.can('signature.manage') ? [{ title: 'Program studi', key: 'prodi', sortable: false }] : []),
  { title: 'Jenis ujian', key: 'jenis_ujian', sortable: false },
  { title: 'Berlaku', key: 'berlaku', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: '', key: 'aksi', sortable: false, align: 'end' },
])

const active = computed(() => (info.value?.bisa_memberi ? rows.value.find((r) => r.status === 'aktif') : null))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/otorisasi-ttd', { params: { page: page.value, per_page: perPage.value } })
    rows.value = data.data
    meta.value = data.meta
    info.value = data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

function onOptions({ page: p, itemsPerPage }) {
  page.value = p
  perPage.value = itemsPerPage
  load()
}

function defaultUntil() {
  const d = new Date()
  d.setMonth(d.getMonth() + 6)
  return d.toISOString().slice(0, 10)
}

const maxDate = computed(() => {
  const d = new Date()
  d.setDate(d.getDate() + (info.value?.maks_hari ?? 366))
  return d.toISOString().slice(0, 10)
})

function openForm() {
  form.value = {
    signer_name: auth.user?.name ?? '',
    signer_nip: auth.user?.nip ?? '',
    signer_title: info.value?.prodi ? `Ketua Program Studi ${info.value.prodi}` : 'Ketua Program Studi',
    exam_types: ['uts', 'uas'],
    valid_until: defaultUntil(),
    gambar: null,
    persetujuan: false,
  }
  formErrors.value = {}
  formError.value = ''
  setImagePreview(null)
  formOpen.value = true
}

// Pesan galat sebuah isian hilang begitu isian itu diubah.
watch(
  () => ({ ...form.value, exam_types: [...(form.value.exam_types ?? [])] }),
  (now, before) => {
    for (const key of Object.keys(now)) {
      if (before && JSON.stringify(now[key]) !== JSON.stringify(before[key])) delete formErrors.value[key]
    }
  },
)

function setImagePreview(file) {
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
  imagePreview.value = file ? URL.createObjectURL(file) : ''
}

const statement = computed(() => {
  if (!info.value?.pernyataan) return ''
  const types = form.value.exam_types?.length ? form.value.exam_types.map((t) => t.toUpperCase()).join(' dan ') : '...'
  return info.value.pernyataan
    .replace('{jenis}', types)
    .replace('{tanggal}', form.value.valid_until ? formatDate(`${form.value.valid_until}T00:00:00`) : '...')
})

function validate() {
  const f = form.value
  const e = {}
  if (!f.signer_name?.trim()) e.signer_name = 'Nama penanda tangan wajib diisi.'
  if (f.signer_nip && !/^[0-9 ]+$/.test(f.signer_nip)) e.signer_nip = 'NIP hanya boleh berisi angka.'
  if (!f.signer_title?.trim()) e.signer_title = 'Jabatan wajib diisi.'
  if (!f.exam_types?.length) e.exam_types = 'Pilih minimal satu jenis ujian.'
  if (!f.valid_until) e.valid_until = 'Tanggal berakhir wajib diisi.'
  if (!f.gambar) e.gambar = 'Pilih gambar tanda tangan (PNG).'
  else if (f.gambar.type !== 'image/png') e.gambar = 'Gambar tanda tangan harus berformat PNG.'
  if (!f.persetujuan) e.persetujuan = 'Centang pernyataan persetujuan untuk memberi otorisasi.'
  formErrors.value = e
  return Object.keys(e).length === 0
}

async function save() {
  formError.value = ''
  if (!validate()) return
  saving.value = true
  const body = new FormData()
  for (const key of ['signer_name', 'signer_nip', 'signer_title', 'valid_until']) body.append(key, form.value[key] ?? '')
  form.value.exam_types.forEach((t) => body.append('exam_types[]', t))
  body.append('gambar', form.value.gambar)
  body.append('persetujuan', '1')
  try {
    const { data } = await api.post('/otorisasi-ttd', body)
    formOpen.value = false
    setImagePreview(null)
    notice.value = {
      type: 'success',
      text: data.soal_menunggu
        ? `Otorisasi aktif. Ada ${data.soal_menunggu} soal yang menunggu; dosen atau Anda dapat menekan "Proses ulang" pada soal tersebut.`
        : 'Otorisasi aktif. Soal yang diunggah setelah ini akan disahkan otomatis.',
    }
    page.value = 1
    load()
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) {
      formErrors.value = Object.fromEntries(Object.entries(e.response.data.errors).map(([k, v]) => [k.split('.')[0], v[0]]))
    } else {
      formError.value = errorMessage(e)
    }
  } finally {
    saving.value = false
  }
}

function askRevoke(row) {
  revokeTarget.value = row
  revokeReason.value = ''
  revokeError.value = ''
}

async function revoke() {
  if (revokeReason.value.trim().length < 5) {
    revokeError.value = 'Alasan pencabutan minimal 5 karakter.'
    return
  }
  revoking.value = true
  try {
    await api.post(`/otorisasi-ttd/${revokeTarget.value.id}/cabut`, { alasan: revokeReason.value.trim() })
    notice.value = { type: 'info', text: 'Otorisasi dicabut. Soal baru tidak akan disahkan sampai ada otorisasi baru.' }
    revokeTarget.value = null
    load()
  } catch (e) {
    revokeError.value = errorMessage(e)
  } finally {
    revoking.value = false
  }
}

const canRevoke = (row) => row.status === 'aktif' || row.status === 'belum_berlaku'
</script>

<template>
  <div>
    <h1 class="text-h5 font-weight-bold mb-1">Otorisasi Tanda Tangan</h1>
    <p class="text-body-2 text-muted mb-5">
      Tanda tangan KPS hanya dibubuhkan pada soal bila ada otorisasi aktif dari KPS sendiri. Administrator dapat mencabut, tetapi tidak dapat memberi otorisasi atas nama KPS.
    </p>

    <v-alert v-if="notice" :type="notice.type" variant="tonal" closable class="mb-4" role="status" @click:close="notice = null">
      {{ notice.text }}
    </v-alert>

    <v-card v-if="info?.bisa_memberi" class="pa-4 pa-md-5 mb-4">
      <template v-if="active">
        <div class="d-flex flex-wrap align-start ga-4">
          <FilePreview :url="`/otorisasi-ttd/${active.id}/gambar`" kind="image" title="Gambar tanda tangan yang diotorisasi" />
          <div class="flex-grow-1" style="min-width: 240px">
            <div class="d-flex align-center ga-2 mb-2">
              <h2 class="text-subtitle-1 font-weight-bold mb-0">Otorisasi Anda</h2>
              <StatusChip :status="active.status" :map="OTORISASI_STATUS" />
            </div>
            <p class="text-body-2 mb-1"><strong>{{ active.penanda_tangan }}</strong><span v-if="active.nip">, NIP {{ active.nip }}</span></p>
            <p class="text-body-2 mb-1">{{ active.jabatan }}</p>
            <p class="text-body-2 mb-3">
              Soal {{ active.jenis_ujian.map((t) => t.toUpperCase()).join(' dan ') }}, berlaku sampai {{ formatDate(active.berlaku_sampai) }}
            </p>
            <p class="text-caption text-muted mb-4">{{ active.pernyataan }}</p>
            <div class="d-flex flex-wrap ga-2">
              <v-btn color="primary" variant="flat" @click="openForm">Perbarui otorisasi</v-btn>
              <v-btn color="error" variant="outlined" :prepend-icon="mdiShieldOffOutline" @click="askRevoke(active)">Cabut otorisasi</v-btn>
            </div>
          </div>
        </div>
      </template>
      <EmptyState
        v-else-if="!loading"
        :icon="mdiDrawPen"
        title="Anda belum memberi otorisasi"
        :message="info.prodi ? `Soal UTS/UAS program studi ${info.prodi} akan menunggu sampai Anda memberi otorisasi.` : 'Akun Anda belum terhubung ke program studi. Minta administrator melengkapi data akun.'"
      >
        <v-btn v-if="info.prodi" color="primary" variant="flat" class="mt-4" @click="openForm">Beri otorisasi</v-btn>
      </EmptyState>
    </v-card>

    <v-card>
      <h2 class="text-subtitle-1 font-weight-bold pa-4 pb-2 mb-0">
        {{ auth.can('signature.manage') ? 'Semua otorisasi' : 'Riwayat otorisasi Anda' }}
      </h2>
      <ErrorState v-if="error" :message="error" @retry="load" />
      <v-data-table-server
        v-else
        :headers="headers"
        :items="rows"
        :items-length="meta.total"
        :loading="loading"
        :page="page"
        :items-per-page="perPage"
        :items-per-page-options="[10, 25, 50]"
        items-per-page-text="Baris per halaman"
        loading-text="Memuat otorisasi..."
        @update:options="onOptions"
      >
        <template #item.penanda_tangan="{ item }">
          <span class="font-weight-medium d-block">{{ item.penanda_tangan }}</span>
          <span class="text-caption text-muted">{{ item.jabatan }}</span>
        </template>
        <template #item.jenis_ujian="{ item }">{{ item.jenis_ujian.map((t) => t.toUpperCase()).join(', ') }}</template>
        <template #item.berlaku="{ item }">
          {{ formatDate(item.berlaku_mulai) }} s.d. {{ formatDate(item.berlaku_sampai) }}
        </template>
        <template #item.status="{ item }">
          <StatusChip :status="item.status" :map="OTORISASI_STATUS" />
          <p v-if="item.dicabut_pada && item.status === 'dicabut'" class="text-caption text-muted mb-0 mt-1">
            {{ item.dicabut_oleh }}, {{ formatDateTime(item.dicabut_pada) }}: {{ item.alasan_cabut }}
          </p>
        </template>
        <template #item.aksi="{ item }">
          <v-btn v-if="canRevoke(item)" size="small" variant="text" color="error" @click="askRevoke(item)">Cabut</v-btn>
        </template>
        <template #no-data>
          <EmptyState title="Belum ada otorisasi" message="Otorisasi yang diberikan KPS akan tercatat di sini." />
        </template>
      </v-data-table-server>
    </v-card>

    <v-dialog v-model="formOpen" max-width="640" scrollable :persistent="saving">
      <v-card>
        <v-card-title class="text-h6 pt-4 px-5">Beri otorisasi tanda tangan</v-card-title>
        <v-card-text class="px-5">
          <v-form novalidate :disabled="saving" @submit.prevent="save">
            <v-text-field v-model="form.signer_name" label="Nama yang dicetak (dengan gelar)" :error-messages="formErrors.signer_name" />
            <v-text-field v-model="form.signer_nip" label="NIP (opsional)" inputmode="numeric" :error-messages="formErrors.signer_nip" />
            <v-text-field v-model="form.signer_title" label="Jabatan" :error-messages="formErrors.signer_title" />

            <fieldset class="mb-3 border-0 pa-0">
              <legend class="text-body-2 mb-1">Berlaku untuk soal</legend>
              <div class="d-flex ga-4">
                <v-checkbox
                  v-for="t in EXAM_TYPES"
                  :key="t.value"
                  v-model="form.exam_types"
                  :value="t.value"
                  :label="t.title"
                  hide-details
                  density="compact"
                />
              </div>
              <p v-if="formErrors.exam_types" class="text-caption text-error mb-0">{{ formErrors.exam_types }}</p>
            </fieldset>

            <v-text-field
              v-model="form.valid_until"
              type="date"
              label="Berlaku sampai"
              :min="new Date().toISOString().slice(0, 10)"
              :max="maxDate"
              :hint="`Paling lama ${info?.maks_hari} hari dari hari ini`"
              persistent-hint
              :error-messages="formErrors.valid_until"
              class="mb-4"
            />

            <v-file-input
              v-model="form.gambar"
              label="Gambar tanda tangan (PNG, latar transparan)"
              accept="image/png"
              prepend-icon=""
              :prepend-inner-icon="mdiDrawPen"
              hint="Minimal 200 x 60 piksel, maksimal 1 MB"
              persistent-hint
              :error-messages="formErrors.gambar"
              class="mb-2"
              @update:model-value="setImagePreview"
            />
            <img v-if="imagePreview" :src="imagePreview" alt="Pratinjau gambar tanda tangan" width="320" height="112" class="sig-preview mt-3" />

            <v-checkbox v-model="form.persetujuan" :error-messages="formErrors.persetujuan" class="mt-3" density="compact">
              <template #label>
                <span class="text-body-2">{{ statement }}</span>
              </template>
            </v-checkbox>

            <v-alert v-if="formError" type="error" variant="tonal" density="comfortable" class="mt-2" role="alert">{{ formError }}</v-alert>
            <p v-if="active" class="text-caption text-muted mt-2 mb-0">Otorisasi aktif saat ini akan diganti dengan yang baru.</p>
          </v-form>
        </v-card-text>
        <v-card-actions class="px-5 pb-4">
          <v-spacer />
          <v-btn variant="text" :disabled="saving" @click="formOpen = false">Batal</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="save">Setujui dan aktifkan</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog :model-value="revokeTarget !== null" max-width="460" :persistent="revoking" @update:model-value="(v) => !v && (revokeTarget = null)">
      <v-card v-if="revokeTarget" class="pa-2">
        <v-card-title class="text-h6">Cabut otorisasi?</v-card-title>
        <v-card-text>
          <p class="mb-4">
            Otorisasi {{ revokeTarget.penanda_tangan }} berhenti berlaku sekarang. Soal yang sudah disahkan tetap sah; soal baru akan menunggu otorisasi baru.
          </p>
          <v-textarea v-model="revokeReason" label="Alasan pencabutan" rows="2" auto-grow :error-messages="revokeError" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="revoking" @click="revokeTarget = null">Batal</v-btn>
          <v-btn color="error" variant="flat" :loading="revoking" @click="revoke">Cabut otorisasi</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.sig-preview {
  max-width: 100%;
  height: auto;
  object-fit: contain;
  background: repeating-conic-gradient(#f1f5f9 0% 25%, #ffffff 0% 50%) 50% / 16px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}
</style>
