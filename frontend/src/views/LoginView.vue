<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { mdiEyeOutline, mdiEyeOffOutline } from '@mdi/js'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/services/api'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')
const touched = ref(false)

const notice = computed(() => {
  if (route.query.sesi === 'berakhir') return 'Sesi Anda telah berakhir. Silakan masuk kembali.'
  if (route.query.sesi === 'keluar') return 'Anda telah keluar dari sistem.'
  return ''
})

const emailError = computed(() => {
  if (!touched.value) return ''
  if (!email.value) return 'Email wajib diisi.'
  if (!/^\S+@\S+\.\S+$/.test(email.value)) return 'Format email tidak valid.'
  return ''
})
const passwordError = computed(() => (touched.value && !password.value ? 'Kata sandi wajib diisi.' : ''))

function safeRedirect() {
  const target = route.query.lanjut
  return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : '/'
}

async function submit() {
  touched.value = true
  error.value = ''
  if (emailError.value || passwordError.value) return
  loading.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    router.replace(safeRedirect())
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-main class="d-flex align-center justify-center pa-4" style="min-height: 100vh">
    <v-card width="100%" max-width="420" class="pa-6">
      <h1 class="text-h5 font-weight-bold mb-1">PBM Management System</h1>
      <p class="text-body-2 text-muted mb-6">Masuk dengan akun institusi Anda.</p>

      <v-alert v-if="notice" type="info" variant="tonal" density="comfortable" class="mb-4" role="status">
        {{ notice }}
      </v-alert>
      <v-alert v-if="error" type="error" variant="tonal" density="comfortable" class="mb-4" role="alert">
        {{ error }}
      </v-alert>

      <v-form novalidate @submit.prevent="submit">
        <v-text-field
          v-model="email"
          label="Email"
          type="email"
          autocomplete="username"
          :error-messages="emailError"
          :disabled="loading"
          class="mb-1"
        />
        <v-text-field
          v-model="password"
          label="Kata sandi"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="current-password"
          :error-messages="passwordError"
          :disabled="loading"
          :append-inner-icon="showPassword ? mdiEyeOffOutline : mdiEyeOutline"
          @click:append-inner="showPassword = !showPassword"
        />
        <v-btn type="submit" color="primary" variant="flat" size="large" block :loading="loading" class="mt-2">
          Masuk
        </v-btn>
      </v-form>
    </v-card>
  </v-main>
</template>
