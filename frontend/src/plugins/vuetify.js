import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi-svg'
import { id, en } from 'vuetify/locale'

export default createVuetify({
  locale: { locale: 'id', fallback: 'en', messages: { id, en } },
  icons: { defaultSet: 'mdi', aliases, sets: { mdi } },
  theme: {
    defaultTheme: 'pbm',
    themes: {
      pbm: {
        dark: false,
        colors: {
          primary: '#142B4A',
          secondary: '#2563EB',
          accent: '#0F766E',
          background: '#F8FAFC',
          surface: '#FFFFFF',
          error: '#B91C1C',
          warning: '#B45309',
          success: '#15803D',
          info: '#1D4ED8',
        },
      },
    },
  },
  defaults: {
    VBtn: { rounded: 'lg', style: 'text-transform: none; letter-spacing: 0;' },
    VCard: { rounded: 'lg', elevation: 0, border: true },
    VTextField: { variant: 'outlined', density: 'comfortable' },
    VSelect: { variant: 'outlined', density: 'comfortable' },
    VAutocomplete: { variant: 'outlined', density: 'comfortable' },
    VFileInput: { variant: 'outlined', density: 'comfortable' },
    VTextarea: { variant: 'outlined', density: 'comfortable' },
  },
})
