<script setup>
import { computed, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'
import { mdiClose, mdiDownload, mdiRefresh } from '@mdi/js'
import api, { errorMessage } from '@/services/api'
import { SOAL_STATUS, PENEMPATAN, auditLabel, formatBytes, formatDateTime } from '@/utils/labels'
import FilePreview from '@/components/FilePreview.vue'
import ErrorState from '@/components/ErrorState.vue'
import StatusChip from '@/components/StatusChip.vue'

const props = defineProps({ soalId: { type: Number, default: null } })
const emit = defineEmits(['close', 'changed', 'open'])
const { mobile } = useDisplay()

const detail = ref(null)
const loading = ref(false)
const error = ref('')
const reprocessing = ref(false)
const actionMessage = ref(null)
const downloading = ref('')

const open = computed({
  get: () => props.soalId !== null,
  set: (v) => {
    if (!v) emit('close')
  },
})

async function load() {
  if (!props.soalId) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/soal/${props.soalId}`)
    detail.value = data.data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

watch(
  () => props.soalId,
  (id) => {
    detail.value = null
    actionMessage.value = null
    if (id) load()
  },
  { immediate: true },
)

const preview = computed(() => {
  const d = detail.value
  if (!d) return null
  if (d.berkas_final) return { url: `/soal/${d.id}/berkas/final`, title: 'Pratinjau PDF yang disahkan' }
  if (d.berkas_asli.mime === 'application/pdf') return { url: `/soal/${d.id}/berkas/asli`, title: 'Pratinjau berkas asli' }
  return null
})

async function reprocess() {
  reprocessing.value = true
  actionMessage.value = null
  try {
    const { data } = await api.post(`/soal/${props.soalId}/proses`, null, { timeout: 180000 })
    actionMessage.value = data.data.status === 'disahkan'
      ? { type: 'success', text: 'Soal berhasil disahkan.' }
      : { type: 'warning', text: data.data.alasan }
    await load()
    emit('changed')
  } catch (e) {
    actionMessage.value = { type: 'error', text: errorMessage(e) }
  } finally {
    reprocessing.value = false
  }
}

async function download(jenis) {
  downloading.value = jenis
  try {
    const { data } = await api.get(`/soal/${props.soalId}/berkas/${jenis}`, { params: { unduh: 1 }, responseType: 'blob', timeout: 60000 })
    const name = jenis === 'final' ? detail.value.berkas_final.nama : detail.value.berkas_asli.nama
    const link = document.createElement('a')
    link.href = URL.createObjectURL(data)
    link.download = name
    link.click()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (e) {
    actionMessage.value = { type: 'error', text: errorMessage(e) }
  } finally {
    downloading.value = ''
  }
}
</script>

<template>
  <v-dialog v-model="open" :fullscreen="mobile" max-width="1200" scrollable>
    <v-card>
      <div class="d-flex align-center px-4 py-3 border-b">
        <div class="min-width-0 flex-grow-1">
          <h2 class="text-subtitle-1 font-weight-bold mb-0 text-truncate">
            {{ detail ? `${detail.mata_kuliah.kode} ${detail.mata_kuliah.nama}` : 'Detail soal' }}
          </h2>
          <p v-if="detail" class="text-caption text-muted mb-0">
            Soal {{ detail.jenis_ujian.toUpperCase() }} · versi {{ detail.versi }} · {{ detail.semester }}
          </p>
        </div>
        <v-btn :icon="mdiClose" variant="text" aria-label="Tutup detail" @click="open = false" />
      </div>

      <v-card-text class="pa-4">
        <v-skeleton-loader v-if="loading && !detail" type="article, image" aria-label="Memuat detail" />
        <ErrorState v-else-if="error" :message="error" @retry="load" />

        <v-row v-else-if="detail">
          <v-col cols="12" md="5" order="2" order-md="1">
            <div class="d-flex align-center ga-2 mb-3">
              <StatusChip :status="detail.status" :map="SOAL_STATUS" />
            </div>

            <v-alert
              v-if="detail.alasan"
              :type="detail.status === 'gagal_diproses' ? 'error' : 'warning'"
              variant="tonal"
              density="comfortable"
              class="mb-3"
            >
              {{ detail.alasan }}
            </v-alert>
            <v-alert v-if="actionMessage" :type="actionMessage.type" variant="tonal" density="comfortable" class="mb-3" role="status">
              {{ actionMessage.text }}
            </v-alert>

            <div class="d-flex flex-wrap ga-2 mb-4">
              <v-btn
                v-if="detail.bisa_diproses_ulang"
                color="primary"
                variant="flat"
                :prepend-icon="mdiRefresh"
                :loading="reprocessing"
                @click="reprocess"
              >
                Proses ulang
              </v-btn>
              <v-btn
                v-if="detail.berkas_final"
                color="primary"
                variant="flat"
                :prepend-icon="mdiDownload"
                :loading="downloading === 'final'"
                @click="download('final')"
              >
                Unduh PDF disahkan
              </v-btn>
              <v-btn variant="outlined" :prepend-icon="mdiDownload" :loading="downloading === 'asli'" @click="download('asli')">
                Unduh berkas asli
              </v-btn>
            </div>

            <dl class="detail-list text-body-2">
              <dt>Dosen pengunggah</dt>
              <dd>{{ detail.dosen }}</dd>
              <dt>Berkas asli</dt>
              <dd>{{ detail.berkas_asli.nama }} ({{ formatBytes(detail.berkas_asli.ukuran) }})</dd>
              <dt>SHA-256 berkas asli</dt>
              <dd class="hash">{{ detail.berkas_asli.sha256 }}</dd>
              <template v-if="detail.berkas_final">
                <dt>SHA-256 PDF disahkan</dt>
                <dd class="hash">{{ detail.berkas_final.sha256 }}</dd>
                <dt>Disahkan oleh</dt>
                <dd>{{ detail.otorisasi?.penanda_tangan }}, {{ formatDateTime(detail.disahkan_pada) }}</dd>
                <dt>Posisi tanda tangan</dt>
                <dd>{{ PENEMPATAN[detail.penempatan] }}</dd>
                <dt>Kode verifikasi</dt>
                <dd>
                  <router-link :to="{ name: 'verifikasi', params: { kode: detail.kode_verifikasi } }" target="_blank">
                    {{ detail.kode_verifikasi }}
                  </router-link>
                </dd>
              </template>
            </dl>

            <h3 class="text-body-1 font-weight-bold mt-5 mb-2">Riwayat</h3>
            <ol class="timeline">
              <li v-for="(h, i) in detail.riwayat" :key="i">
                <p class="text-body-2 font-weight-medium mb-0">{{ auditLabel(h.aksi) }}</p>
                <p class="text-caption text-muted mb-0">{{ h.pengguna ?? 'Sistem' }} · {{ formatDateTime(h.waktu) }}</p>
                <p v-if="h.keterangan" class="text-caption mb-0">{{ h.keterangan }}</p>
              </li>
            </ol>

            <template v-if="detail.versi_lain.length > 1">
              <h3 class="text-body-1 font-weight-bold mt-5 mb-2">Versi soal ini</h3>
              <v-list density="compact" class="py-0 border rounded-lg">
                <v-list-item
                  v-for="v in detail.versi_lain"
                  :key="v.id"
                  :active="v.id === detail.id"
                  :title="`Versi ${v.versi} · ${SOAL_STATUS[v.status]?.label ?? v.status}`"
                  :subtitle="`${v.pengunggah} · ${formatDateTime(v.dibuat)}`"
                  @click="emit('open', v.id)"
                />
              </v-list>
            </template>
          </v-col>

          <v-col cols="12" md="7" order="1" order-md="2">
            <FilePreview v-if="preview" :url="preview.url" :title="preview.title" :height="mobile ? '60vh' : '72vh'" />
            <div v-else class="no-preview d-flex align-center justify-center text-center pa-6">
              <p class="text-body-2 text-muted mb-0">
                Berkas asli berformat DOCX dan belum ada PDF yang disahkan. Pratinjau tersedia setelah soal diproses.
              </p>
            </div>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.detail-list {
  display: grid;
  grid-template-columns: minmax(120px, 38%) 1fr;
  gap: 6px 12px;
  margin: 0;
}
.detail-list dt {
  color: var(--pbm-muted);
}
.detail-list dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.hash {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.75rem;
}
.timeline {
  list-style: none;
  padding: 0 0 0 16px;
  margin: 0;
  border-left: 2px solid #cbd5e1;
}
.timeline li {
  position: relative;
  padding-bottom: 12px;
}
.timeline li::before {
  content: '';
  position: absolute;
  left: -22px;
  top: 6px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--pbm-navy);
}
.no-preview {
  min-height: 240px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
}
</style>
