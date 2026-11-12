<script setup>
import { computed, onMounted, ref } from 'vue'
import { mdiBookOpenVariantOutline, mdiAccountTieOutline, mdiFileDocumentOutline, mdiCalendarRange, mdiHistory } from '@mdi/js'
import api, { errorMessage } from '@/services/api'
import { STATUS_LABELS, SOAL_STATUS, auditLabel, formatDateTime } from '@/utils/labels'
import { useAuthStore } from '@/stores/auth'
import StatCard from '@/components/StatCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'

const auth = useAuthStore()
const canSeeSoal = computed(() => auth.can(['soal.upload', 'soal.status', 'soal.authorize', 'dokumen.manage']))

const data = ref(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/dashboard')
    data.value = response.data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)

const chartSeries = computed(() => [
  { name: 'Mata kuliah', data: (data.value?.mata_kuliah_per_semester ?? []).map((row) => row.total) },
])

const chartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit', foreColor: '#334155' },
  colors: ['#2563EB'],
  plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
  dataLabels: { enabled: false },
  xaxis: { categories: (data.value?.mata_kuliah_per_semester ?? []).map((row) => `Smt ${row.semester_ke}`) },
  yaxis: { labels: { formatter: (v) => Math.round(v) }, forceNiceScale: true, min: 0 },
  grid: { borderColor: '#E2E8F0' },
  tooltip: { y: { formatter: (v) => `${v} mata kuliah` } },
}))

const soal = computed(() => data.value?.soal_semester_aktif)
const soalRows = computed(() =>
  ['uts', 'uas'].map((jenis) => {
    const counts = soal.value?.per_jenis?.[jenis] ?? {}
    const disahkan = counts.disahkan ?? 0
    const total = soal.value?.mata_kuliah_aktif ?? 0
    return { jenis, counts, disahkan, total, persen: total ? Math.round((disahkan / total) * 100) : 0 }
  }),
)

const statusTotal = computed(() => (data.value?.dokumen_per_status ?? []).reduce((sum, row) => sum + row.total, 0))
</script>

<template>
  <div>
    <v-row v-if="loading" aria-busy="true" aria-label="Memuat dashboard">
      <v-col v-for="n in 4" :key="n" cols="12" sm="6" lg="3">
        <v-skeleton-loader type="article" />
      </v-col>
      <v-col cols="12" md="7"><v-skeleton-loader type="image" /></v-col>
      <v-col cols="12" md="5"><v-skeleton-loader type="list-item-two-line, list-item-two-line, list-item-two-line" /></v-col>
    </v-row>

    <v-card v-else-if="error">
      <ErrorState :message="error" @retry="load" />
    </v-card>

    <template v-else>
      <v-row>
        <v-col cols="12" sm="6" lg="3">
          <StatCard label="Total mata kuliah" :value="data.stats.mata_kuliah" :icon="mdiBookOpenVariantOutline" />
        </v-col>
        <v-col cols="12" sm="6" lg="3">
          <StatCard label="Dosen aktif" :value="data.stats.dosen" :icon="mdiAccountTieOutline" />
        </v-col>
        <v-col cols="12" sm="6" lg="3">
          <StatCard label="Dokumen pembelajaran" :value="data.stats.dokumen" :icon="mdiFileDocumentOutline" />
        </v-col>
        <v-col cols="12" sm="6" lg="3">
          <StatCard label="Semester aktif" :value="data.stats.semester_aktif" :icon="mdiCalendarRange" />
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" md="7">
          <v-card class="pa-4 h-100">
            <h2 class="text-subtitle-1 font-weight-bold mb-2">Sebaran mata kuliah per semester</h2>
            <EmptyState
              v-if="!data.mata_kuliah_per_semester.length"
              title="Belum ada mata kuliah"
              message="Data akan tampil setelah mata kuliah ditambahkan pada data master."
            />
            <apexchart
              v-else
              type="bar"
              height="280"
              :options="chartOptions"
              :series="chartSeries"
              aria-label="Grafik batang jumlah mata kuliah per semester"
            />
          </v-card>
        </v-col>

        <v-col cols="12" md="5">
          <v-card class="pa-4 h-100">
            <h2 class="text-subtitle-1 font-weight-bold mb-3">Status dokumen</h2>
            <EmptyState v-if="statusTotal === 0" title="Belum ada dokumen" message="Dokumen yang diunggah akan dihitung di sini." />
            <ul v-else class="pa-0" style="list-style: none">
              <li v-for="row in data.dokumen_per_status" :key="row.status" class="mb-3">
                <div class="d-flex justify-space-between text-body-2 mb-1">
                  <span>{{ STATUS_LABELS[row.status] }}</span>
                  <span class="font-weight-bold">{{ row.total }}</span>
                </div>
                <v-progress-linear
                  :model-value="(row.total / statusTotal) * 100"
                  color="secondary"
                  height="8"
                  rounded
                  :aria-label="`${STATUS_LABELS[row.status]}: ${row.total} dokumen`"
                />
              </li>
            </ul>
          </v-card>
        </v-col>
      </v-row>

      <v-row v-if="soal">
        <v-col cols="12">
          <v-card class="pa-4">
            <div class="d-flex flex-wrap align-center justify-space-between ga-2 mb-3">
              <h2 class="text-subtitle-1 font-weight-bold mb-0">Soal UTS/UAS semester aktif</h2>
              <v-btn v-if="canSeeSoal" :to="{ name: 'soal' }" variant="text" color="secondary" size="small">Buka daftar soal</v-btn>
            </div>
            <EmptyState
              v-if="!soal.mata_kuliah_aktif"
              title="Belum ada mata kuliah berpengampu"
              message="Status pengumpulan soal dihitung dari mata kuliah yang sudah memiliki dosen pengampu pada semester aktif."
            />
            <v-row v-else dense>
              <v-col v-for="row in soalRows" :key="row.jenis" cols="12" md="6">
                <div class="d-flex justify-space-between text-body-2 mb-1">
                  <span class="font-weight-bold">{{ row.jenis.toUpperCase() }}</span>
                  <span>{{ row.disahkan }} dari {{ row.total }} mata kuliah disahkan</span>
                </div>
                <v-progress-linear
                  :model-value="row.persen"
                  color="accent"
                  height="10"
                  rounded
                  :aria-label="`Soal ${row.jenis.toUpperCase()} disahkan: ${row.disahkan} dari ${row.total}`"
                />
                <div class="d-flex flex-wrap ga-2 mt-2">
                  <v-chip
                    v-for="key in ['menunggu_otorisasi', 'gagal_diproses']"
                    :key="key"
                    :color="SOAL_STATUS[key].color"
                    size="small"
                    variant="tonal"
                    label
                  >
                    {{ SOAL_STATUS[key].label }}: {{ row.counts[key] ?? 0 }}
                  </v-chip>
                  <v-chip size="small" variant="tonal" label>
                    Belum diunggah: {{ Math.max(0, row.total - Object.values(row.counts).reduce((a, b) => a + b, 0)) }}
                  </v-chip>
                </div>
              </v-col>
            </v-row>
          </v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12">
          <v-card class="pa-4">
            <h2 class="text-subtitle-1 font-weight-bold mb-2">Aktivitas terbaru</h2>
            <EmptyState
              v-if="!data.aktivitas_terbaru.length"
              :icon="mdiHistory"
              title="Belum ada aktivitas"
              message="Aktivitas pengguna akan tercatat otomatis."
            />
            <v-list v-else lines="two" class="py-0">
              <v-list-item
                v-for="item in data.aktivitas_terbaru"
                :key="item.id"
                :title="auditLabel(item.aksi)"
                :subtitle="`${item.pengguna ?? 'Tanpa pengguna'} · ${formatDateTime(item.waktu)}`"
              />
            </v-list>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </div>
</template>
