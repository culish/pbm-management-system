const AUDIT_ACTIONS = {
  'auth.login': 'Masuk ke sistem',
  'auth.logout': 'Keluar dari sistem',
  'auth.login_failed': 'Percobaan masuk gagal',
  'auth.login_blocked': 'Percobaan masuk pada akun nonaktif',
}

export const auditLabel = (action) => AUDIT_ACTIONS[action] ?? action

export const STATUS_LABELS = {
  draft: 'Draf',
  diajukan: 'Diajukan',
  disahkan: 'Disahkan',
  ditolak: 'Ditolak',
  arsip: 'Arsip',
}

const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })

export const formatDateTime = (iso) => (iso ? dateTime.format(new Date(iso)) : '')
