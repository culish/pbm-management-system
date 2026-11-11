<script setup>
import { mdiInboxOutline } from '@mdi/js'

defineProps({
  title: { type: String, required: true },
  message: { type: String, default: '' },
  icon: { type: String, default: mdiInboxOutline },
})
</script>

<template>
  <div class="d-flex flex-column align-center text-center py-8 px-4">
    <v-icon :icon="icon" size="40" color="primary" class="mb-3" aria-hidden="true" />
    <p class="text-subtitle-1 font-weight-bold mb-1">{{ title }}</p>
    <p v-if="message" class="text-body-2 text-muted mb-0">{{ message }}</p>
    <slot />
  </div>
</template>
