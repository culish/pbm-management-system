<script setup>
import { computed, onMounted, ref } from 'vue'
import { mdiBookOpenVariantOutline, mdiAccountTieOutline, mdiFileDocumentOutline, mdiCalendarRange, mdiHistory } from '@mdi/js'
import api, { errorMessage } from '@/services/api'
import { STATUS_LABELS, auditLabel, formatDateTime } from '@/utils/labels'
import StatCard from '@/components/StatCard.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'

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
