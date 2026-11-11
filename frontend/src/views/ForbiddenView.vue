<script setup>
import { mdiLockOutline } from '@mdi/js'
import EmptyState from '@/components/EmptyState.vue'
</script>

<template>
  <v-card class="pa-6">
    <EmptyState
      :icon="mdiLockOutline"
      title="Anda tidak memiliki akses"
      message="Peran akun Anda tidak mencakup halaman ini. Hubungi administrator jika Anda memerlukannya."
    >
      <v-btn color="primary" variant="flat" class="mt-4" to="/">Kembali ke dashboard</v-btn>
    </EmptyState>
  </v-card>
</template>

