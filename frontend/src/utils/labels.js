const AUDIT_ACTIONS = {
  'auth.login': 'Masuk ke sistem',
  'auth.logout': 'Keluar dari sistem',
  'auth.login_failed': 'Percobaan masuk gagal',
  'auth.login_blocked': 'Percobaan masuk pada akun nonaktif',
  'soal.submitted': 'Soal diunggah',
  'soal.signed': 'Soal disahkan dengan tanda tangan KPS',
  'soal.signing_blocked': 'Pengesahan ditahan: otorisasi KPS tidak tersedia',
  'soal.signing_failed': 'Dokumen gagal diproses',
  'soal.reprocess_requested': 'Proses ulang diminta',
  'soal.file_accessed': 'Berkas soal dibuka',
  'signature.authorized': 'KPS memberi otorisasi tanda tangan',
  'signature.revoked': 'Otorisasi tanda tangan dicabut',
  'signature.replaced': 'Otorisasi tanda tangan diganti',
}

export const auditLabel = (action) => AUDIT_ACTIONS[action] ?? action

export const STATUS_LABELS = {
  draft: 'Draf',
  diajukan: 'Diajukan',
  disahkan: 'Disahkan',
  ditolak: 'Ditolak',
  arsip: 'Arsip',
}

export const SOAL_STATUS = {
  disahkan: { label: 'Disahkan', color: 'success' },
  menunggu_otorisasi: { label: 'Menunggu otorisasi KPS', color: 'warning' },
  gagal_diproses: { label: 'Gagal diproses', color: 'error' },
}

export const OTORISASI_STATUS = {
  aktif: { label: 'Aktif', color: 'success' },
  kedaluwarsa: { label: 'Kedaluwarsa', color: 'warning' },
  belum_berlaku: { label: 'Belum berlaku', color: 'info' },
  dicabut: { label: 'Dicabut', color: 'error' },
  diganti: { label: 'Diganti', color: 'default' },
}

export const EXAM_TYPES = [
  { value: 'uts', title: 'UTS' },
  { value: 'uas', title: 'UAS' },
]

export const PENEMPATAN = {
  penanda: 'Di posisi penanda [TTD_KPS]',
  lembar_pengesahan: 'Lembar pengesahan di akhir dokumen',
}

const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
const dateOnly = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' })

export const formatDateTime = (iso) => (iso ? dateTime.format(new Date(iso)) : '')
export const formatDate = (iso) => (iso ? dateOnly.format(new Date(iso)) : '')

export function formatBytes(bytes) {
  if (!bytes) return '0 KB'
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}
