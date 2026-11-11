import { defineStore } from 'pinia'
import api, { tokenStore } from '@/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: tokenStore.get(),
    user: null,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    permissions: (state) => state.user?.permissions ?? [],
  },
  actions: {
    can(permission) {
      return this.permissions.includes(permission)
    },
    async login(email, password) {
      const { data } = await api.post('/auth/login', { email, password })
      tokenStore.set(data.token)
      this.token = data.token
      this.user = data.user
    },
    async fetchMe() {
      const { data } = await api.get('/auth/me')
      this.user = data.user
    },
    async logout() {
      try {
        await api.post('/auth/logout')
      } catch {
        // Token mungkin sudah tidak berlaku; sesi lokal tetap dibersihkan.
      }
      this.clear()
    },
    clear() {
      tokenStore.clear()
      this.token = null
      this.user = null
    },
  },
})
