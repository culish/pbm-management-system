import { createApp } from 'vue'
import { createPinia } from 'pinia'
import VueApexCharts from 'vue3-apexcharts'
import '@fontsource-variable/plus-jakarta-sans'
import 'vuetify/styles'
import './assets/main.css'
import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia).use(router).use(vuetify).use(VueApexCharts)

window.addEventListener('pbm:unauthorized', () => {
  useAuthStore().clear()
  if (router.currentRoute.value.name !== 'login') {
    router.replace({ name: 'login', query: { sesi: 'berakhir' } })
  }
})

app.mount('#app')
