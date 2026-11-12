import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/masuk',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guestOnly: true, title: 'Masuk' },
  },
  {
    path: '/',
    component: () => import('@/layouts/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: { title: 'Dashboard PBM', permission: 'dashboard.view', crumbs: [{ title: 'Dashboard PBM' }] },
      },
      {
        path: 'soal',
        name: 'soal',
        component: () => import('@/views/SoalView.vue'),
        meta: {
          title: 'Soal UTS/UAS',
          permission: ['soal.upload', 'soal.status', 'soal.authorize', 'dokumen.manage'],
          crumbs: [{ title: 'Ujian dan Dokumen' }, { title: 'Soal UTS/UAS' }],
        },
      },
      {
        path: 'otorisasi-ttd',
        name: 'otorisasi-ttd',
        component: () => import('@/views/OtorisasiTtdView.vue'),
        meta: {
          title: 'Otorisasi Tanda Tangan',
          permission: ['soal.authorize', 'signature.manage'],
          crumbs: [{ title: 'Ujian dan Dokumen' }, { title: 'Otorisasi Tanda Tangan' }],
        },
      },
      {
        path: 'akses-ditolak',
        name: 'forbidden',
        component: () => import('@/views/ForbiddenView.vue'),
        meta: { title: 'Akses ditolak', crumbs: [{ title: 'Akses ditolak' }] },
      },
    ],
  },
  {
    path: '/verifikasi/:kode?',
    name: 'verifikasi',
    component: () => import('@/views/VerifikasiView.vue'),
    meta: { title: 'Verifikasi Dokumen' },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { title: 'Halaman tidak ditemukan' },
  },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.guestOnly && auth.isAuthenticated) return { name: 'dashboard' }

  if (to.matched.some((r) => r.meta.requiresAuth)) {
    if (!auth.isAuthenticated) {
      return { name: 'login', query: { lanjut: to.fullPath } }
    }
    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        auth.clear()
        return { name: 'login', query: { sesi: 'berakhir' } }
      }
    }
    if (to.meta.permission && !auth.can(to.meta.permission)) {
      return { name: 'forbidden' }
    }
  }
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} | PBM Management System` : 'PBM Management System'
})

export default router
