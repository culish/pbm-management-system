<script setup>
import { mdiAlertCircleOutline, mdiRefresh } from '@mdi/js'

defineProps({
  message: { type: String, default: 'Data tidak dapat dimuat.' },
})
defineEmits(['retry'])
</script>

<template>
  <div class="d-flex flex-column align-center text-center py-8 px-4" role="alert">
    <v-icon :icon="mdiAlertCircleOutline" size="40" color="error" class="mb-3" aria-hidden="true" />
    <p class="text-subtitle-1 font-weight-bold mb-1">Terjadi masalah</p>
    <p class="text-body-2 text-muted mb-4">{{ message }}</p>
    <v-btn color="primary" variant="flat" :prepend-icon="mdiRefresh" @click="$emit('retry')">
      Coba lagi
    </v-btn>
  </div>
</template>
