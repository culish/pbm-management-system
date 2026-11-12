<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
  map: { type: Object, required: true },
})

const entry = computed(() => props.map[props.status] ?? { label: props.status, color: 'default' })
</script>

<template>
  <v-chip :color="entry.color" size="small" variant="tonal" label class="font-weight-medium">
    {{ entry.label }}
  </v-chip>
</template>
