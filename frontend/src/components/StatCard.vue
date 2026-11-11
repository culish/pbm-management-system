<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: null },
  icon: { type: String, required: true },
  hint: { type: String, default: '' },
})
</script>

<template>
  <v-card class="pa-4 h-100">
    <div class="d-flex align-start justify-space-between ga-3">
      <div class="min-width-0">
        <p class="text-body-2 text-muted mb-1">{{ label }}</p>
        <p class="text-h5 font-weight-bold mb-0" style="overflow-wrap: anywhere">
          {{ value ?? 'Belum diatur' }}
        </p>
        <p v-if="hint" class="text-caption text-muted mt-1 mb-0">{{ hint }}</p>
      </div>
      <v-avatar color="primary" variant="tonal" size="44" rounded="lg">
        <v-icon :icon="icon" aria-hidden="true" />
      </v-avatar>
    </div>
  </v-card>
</template>
