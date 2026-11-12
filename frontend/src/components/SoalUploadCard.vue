<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { mdiFileUploadOutline, mdiInformationOutline } from '@mdi/js'
import api, { errorMessage } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { EXAM_TYPES } from '@/utils/labels'

const emit = defineEmits(['submitted'])
const auth = useAuthStore()

const options = ref(null)
const loadingOptions = ref(true)
const optionsError = ref('')

const semesterId = ref(null)
const mataKuliahId = ref(null)
const examType = ref('uts')
const file = ref(null)
const submitting = ref(false)
const errors = ref({})
const formError = ref('')

async function loadOptions(semester = null) {
  loadingOptions.value = true
  optionsError.value = ''
  try {
    const { data } = await api.get('/soal/opsi', { params: semester ? { semester_id: semester } : {} })
    options.value = data
    semesterId.value = data.semester_id
    if (!data.mata_kuliah.some((mk) => mk.id === mataKuliahId.value)) mataKuliahId.value = null
  } catch (e) {
    optionsError.value = errorMessage(e)
  } finally {
    loadingOptions.value = false
  }
}

onMounted(() => loadOptions())

watch(semesterId, (value, old) => {
  if (old !== null && value !== old) loadOptions(value)
})

const selectedCourse = computed(() => options.value?.mata_kuliah.find((mk) => mk.id === mataKuliahId.value))
const examLabel = computed(() => EXAM_TYPES.find((t) => t.value === examType.value)?.title)
watch([mataKuliahId, file, semesterId], ([mk, f, sem], [oldMk, oldF, oldSem]) => {
  if (mk !== oldMk) delete errors.value.mata_kuliah_id
  if (f !== oldF) delete errors.value.berkas
  if (sem !== oldSem) delete errors.value.semester_id
})

const notAuthorized = computed(() => selectedCourse.value && !selectedCourse.value.otorisasi.includes(examType.value))

const courseItems = computed(() =>
  (options.value?.mata_kuliah ?? []).map((mk) => ({ value: mk.id, title: `${mk.kode} · ${mk.nama}` })),
)

function validate() {
  const e = {}
  if (!semesterId.value) e.semester_id = 'Pilih semester.'
  if (!mataKuliahId.value) e.mata_kuliah_id = 'Pilih mata kuliah.'
  if (!file.value) e.berkas = 'Pilih berkas soal (PDF atau DOCX).'
  else if (!/\.(pdf|docx)$/i.test(file.value.name)) e.berkas = 'Berkas soal harus berformat PDF atau DOCX.'
  else if (file.value.size > options.value.max_upload_mb * 1024 * 1024) e.berkas = `Ukuran berkas maksimal ${options.value.max_upload_mb} MB.`
  errors.value = e
  return Object.keys(e).length === 0
}

async function submit() {
  formError.value = ''
  if (!validate()) return
  submitting.value = true
  const body = new FormData()
  body.append('semester_id', semesterId.value)
  body.append('mata_kuliah_id', mataKuliahId.value)
  body.append('exam_type', examType.value)
  body.append('berkas', file.value)
  try {
    const { data } = await api.post('/soal', body, { timeout: 180000 })
    file.value = null
    errors.value = {}
    emit('submitted', data.data)
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = Object.fromEntries(Object.entries(e.response.data.errors).map(([k, v]) => [k, v[0]]))
    } else {
      formError.value = errorMessage(e)
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <v-card class="pa-4 pa-md-5">
    <div class="d-flex align-center ga-2 mb-1">
      <v-icon :icon="mdiFileUploadOutline" color="primary" aria-hidden="true" />
      <h2 class="text-subtitle-1 font-weight-bold mb-0">Unggah soal</h2>
    </div>
    <p class="text-body-2 text-muted mb-4">
      Diunggah sebagai <strong>{{ auth.user?.name }}</strong>. Sistem memeriksa otorisasi KPS lalu menghasilkan PDF yang disahkan.
    </p>

    <v-skeleton-loader v-if="loadingOptions && !options" type="list-item, list-item, list-item" aria-label="Memuat pilihan" />

    <v-alert v-else-if="optionsError" type="error" variant="tonal" density="comfortable" role="alert">
      {{ optionsError }}
      <template #append>
        <v-btn variant="text" size="small" @click="loadOptions()">Coba lagi</v-btn>
      </template>
    </v-alert>

    <v-form v-else novalidate :disabled="submitting" @submit.prevent="submit">
      <v-row dense>
        <v-col cols="12" md="4">
          <v-select
            v-model="semesterId"
            :items="options.semesters"
            item-value="id"
            item-title="label"
            label="Semester dan tahun ajaran"
            :error-messages="errors.semester_id"
            :loading="loadingOptions"
          />
        </v-col>
        <v-col cols="12" md="8">
          <v-autocomplete
            v-model="mataKuliahId"
            :items="courseItems"
            label="Mata kuliah"
            :error-messages="errors.mata_kuliah_id"
            :no-data-text="'Tidak ada mata kuliah yang Anda ampu pada semester ini.'"
            :loading="loadingOptions"
          />
        </v-col>
        <v-col cols="12" md="4">
          <p id="jenis-ujian-label" class="text-body-2 mb-2">Jenis ujian</p>
          <v-btn-toggle
            v-model="examType"
            mandatory
            color="primary"
            variant="outlined"
            divided
            density="comfortable"
            aria-labelledby="jenis-ujian-label"
          >
            <v-btn v-for="t in EXAM_TYPES" :key="t.value" :value="t.value" min-width="88">{{ t.title }}</v-btn>
          </v-btn-toggle>
        </v-col>
        <v-col cols="12" md="8">
          <v-file-input
            v-model="file"
            label="Berkas soal (PDF atau DOCX)"
            accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
            :hint="`Maksimal ${options.max_upload_mb} MB`"
            persistent-hint
            prepend-icon=""
            :prepend-inner-icon="mdiFileUploadOutline"
            :error-messages="errors.berkas"
            show-size
          />
        </v-col>
      </v-row>

      <v-alert
        v-if="!options.mata_kuliah.length"
        type="info"
        variant="tonal"
        density="comfortable"
        class="mt-3"
      >
        Anda belum terdaftar sebagai pengampu mata kuliah pada semester ini. Hubungi administrator program studi.
      </v-alert>

      <v-alert v-if="notAuthorized" type="warning" variant="tonal" density="comfortable" class="mt-3" role="status">
        KPS belum memberi otorisasi tanda tangan untuk soal {{ examLabel }}. Soal tetap disimpan, lalu disahkan setelah otorisasi tersedia.
      </v-alert>

      <v-alert v-if="formError" type="error" variant="tonal" density="comfortable" class="mt-3" role="alert">
        {{ formError }}
      </v-alert>

      <div class="d-flex flex-wrap align-center ga-3 mt-4">
        <v-btn type="submit" color="primary" variant="flat" size="large" :loading="submitting">
          Kirim soal {{ examLabel }}
        </v-btn>
        <p class="text-caption text-muted mb-0" style="max-width: 560px">
          <v-icon :icon="mdiInformationOutline" size="16" class="mr-1" aria-hidden="true" />
          Tulis <code>{{ options.penanda }}</code> di dokumen pada posisi tanda tangan KPS. Tanpa penanda, sistem menambahkan lembar pengesahan di akhir.
        </p>
      </div>
    </v-form>
  </v-card>
</template>
