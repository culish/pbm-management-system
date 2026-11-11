<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { mdiMenu, mdiLogout, mdiChevronDown } from '@mdi/js'
import { useAuthStore } from '@/stores/auth'
import { navGroups } from '@/config/navigation'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { mobile } = useDisplay()

const drawer = ref(!mobile.value)
const rail = ref(false)
const confirmLogout = ref(false)
const loggingOut = ref(false)

watch(mobile, (isMobile) => {
  drawer.value = !isMobile
  rail.value = false
})

watch(
  () => route.fullPath,
  () => {
    if (mobile.value) drawer.value = false
  },
)

const visibleGroups = computed(() =>
  navGroups
    .map((group) => ({ ...group, items: group.items.filter((item) => auth.can(item.permission)) }))
    .filter((group) => group.items.length > 0),
)

const initials = computed(() =>
  (auth.user?.name ?? '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join(''),
)

const crumbs = computed(() => [{ title: 'Beranda', to: '/' }, ...(route.meta.crumbs ?? [])])

function toggleDrawer() {
  if (mobile.value) drawer.value = !drawer.value
  else rail.value = !rail.value
}

async function doLogout() {
  loggingOut.value = true
  await auth.logout()
  loggingOut.value = false
  confirmLogout.value = false
  router.replace({ name: 'login', query: { sesi: 'keluar' } })
}
</script>

<template>
  <v-navigation-drawer
    v-model="drawer"
    :rail="rail && !mobile"
    :temporary="mobile"
    :permanent="!mobile"
    color="primary"
    class="pbm-sidebar"
    aria-label="Navigasi utama"
  >
    <div class="d-flex align-center px-4" style="height: 64px">
      <span class="text-h6 font-weight-bold text-white">PBM</span>
      <span v-if="!rail || mobile" class="ml-2 text-body-2 text-white">Management System</span>
    </div>
    <v-divider color="white" opacity="0.2" />

    <nav aria-label="Menu modul">
      <template v-for="group in visibleGroups" :key="group.title">
        <p v-if="!rail || mobile" class="group-title px-4 pt-4 pb-1 mb-0">{{ group.title }}</p>
        <v-list density="comfortable" nav bg-color="transparent" class="py-0">
          <template v-for="item in group.items" :key="item.title">
            <v-list-item
              v-if="item.to"
              :to="item.to"
              :prepend-icon="item.icon"
              :title="item.title"
              rounded="lg"
              exact
            />
            <v-list-item
              v-else
              :prepend-icon="item.icon"
              :title="item.title"
              :subtitle="rail && !mobile ? undefined : 'Segera hadir'"
              rounded="lg"
              class="soon-item"
              :ripple="false"
              aria-disabled="true"
            />
          </template>
        </v-list>
      </template>
    </nav>
  </v-navigation-drawer>

  <v-app-bar flat color="surface" border="b">
    <v-btn
      :icon="mdiMenu"
      variant="text"
      size="large"
      :aria-label="mobile ? 'Buka atau tutup menu' : 'Ciutkan atau perluas menu'"
      @click="toggleDrawer"
    />
    <v-app-bar-title class="text-body-1 font-weight-bold">
      {{ route.meta.title }}
    </v-app-bar-title>
    <v-spacer />

    <v-menu location="bottom end">
      <template #activator="{ props }">
        <v-btn v-bind="props" variant="text" class="px-2" style="min-height: 44px" aria-label="Menu pengguna">
          <v-avatar color="primary" size="32" class="mr-2">
            <span class="text-caption font-weight-bold text-white">{{ initials }}</span>
          </v-avatar>
          <span v-if="!mobile" class="text-body-2">{{ auth.user?.name }}</span>
          <v-icon :icon="mdiChevronDown" size="18" class="ml-1" aria-hidden="true" />
        </v-btn>
      </template>
      <v-card min-width="240">
        <div class="pa-4">
          <p class="text-body-2 font-weight-bold mb-0">{{ auth.user?.name }}</p>
          <p class="text-caption text-muted mb-0">{{ auth.user?.email }}</p>
          <v-chip size="small" color="primary" variant="tonal" class="mt-2">
            {{ auth.user?.role?.label ?? 'Tanpa peran' }}
          </v-chip>
        </div>
        <v-divider />
        <v-list density="comfortable">
          <v-list-item :prepend-icon="mdiLogout" title="Keluar" @click="confirmLogout = true" />
        </v-list>
      </v-card>
    </v-menu>
  </v-app-bar>

  <v-main>
    <div class="page-container pa-4 pa-md-6">
      <v-breadcrumbs :items="crumbs" density="compact" class="px-0 pt-0">
        <template #divider>/</template>
      </v-breadcrumbs>
      <router-view />
    </div>
  </v-main>

  <v-dialog v-model="confirmLogout" max-width="400" :persistent="loggingOut">
    <v-card class="pa-2">
      <v-card-title class="text-h6">Keluar dari sistem?</v-card-title>
      <v-card-text>Sesi Anda akan diakhiri dan Anda perlu masuk kembali.</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="loggingOut" @click="confirmLogout = false">Batal</v-btn>
        <v-btn color="primary" variant="flat" :loading="loggingOut" @click="doLogout">Keluar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
