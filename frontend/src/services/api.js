import axios from 'axios'

const TOKEN_KEY = 'pbm.token'

export const tokenStore = {
  get: () => sessionStorage.getItem(TOKEN_KEY),
  set: (value) => sessionStorage.setItem(TOKEN_KEY, value),
  clear: () => sessionStorage.removeItem(TOKEN_KEY),
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: { Accept: 'application/json' },
  timeout: 15000,
})

api.interceptors.request.use((config) => {
  const token = tokenStore.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const isLogin = error.config?.url?.includes('/auth/login')
    if (error.response?.status === 401 && !isLogin) {
      window.dispatchEvent(new Event('pbm:unauthorized'))
    }
    return Promise.reject(error)
  },
)

export function errorMessage(error) {
  if (!error.response) {
    return 'Tidak dapat terhubung ke server. Periksa koneksi Anda lalu coba lagi.'
  }
  const { status, data } = error.response
  if (status === 422 && data?.errors) {
    return Object.values(data.errors).flat()[0]
  }
  if (status === 429) return data?.message || 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.'
  if (status === 403) return data?.message || 'Anda tidak memiliki akses ke fitur ini.'
  if (status >= 500) return 'Terjadi kesalahan pada server. Coba lagi beberapa saat lagi.'
  return data?.message || 'Terjadi kesalahan. Coba lagi.'
}

export default api
