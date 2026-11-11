import {
  mdiViewDashboardOutline,
  mdiCalendarClock,
  mdiTargetAccount,
  mdiBookEducationOutline,
  mdiFileDocumentEditOutline,
  mdiTableLarge,
  mdiFileSign,
  mdiFolderOutline,
  mdiFlaskOutline,
  mdiShieldCheckOutline,
  mdiHumanHandsup,
  mdiAlertOctagonOutline,
  mdiChartLine,
  mdiCalendarMonthOutline,
  mdiSchoolOutline,
} from '@mdi/js'

/**
 * `to` hanya diisi untuk halaman yang sudah ada. Item tanpa `to` tampil sebagai
 * "Segera hadir" dan bukan tautan (modul dibangun bertahap sesuai urutan fase).
 */
export const navGroups = [
  {
    title: 'Utama',
    items: [
      { title: 'Dashboard PBM', icon: mdiViewDashboardOutline, to: '/', permission: 'dashboard.view' },
    ],
  },
  {
    title: 'Akademik',
    items: [
      { title: 'Jadwal PBM', icon: mdiCalendarClock, permission: 'jadwal.view' },
      { title: 'PPM dan CPP', icon: mdiTargetAccount, permission: 'ppm-cpp.view' },
      { title: 'Kurikulum IKI', icon: mdiBookEducationOutline, permission: 'kurikulum.view' },
      { title: 'RPS dan Rubrik', icon: mdiFileDocumentEditOutline, permission: 'rps.view' },
      { title: 'Matriks CPP-IK-MK-CPMK', icon: mdiTableLarge, permission: 'matriks.view' },
      { title: 'Kalender Akademik', icon: mdiCalendarMonthOutline, permission: 'kalender.view' },
      { title: 'Persiapan PMB', icon: mdiSchoolOutline, permission: 'pmb.view' },
    ],
  },
  {
    title: 'Ujian dan Dokumen',
    items: [
      { title: 'Upload Soal UTS/UAS', icon: mdiFileSign, permission: 'soal.upload' },
      { title: 'Status Soal UTS/UAS', icon: mdiFileSign, permission: 'soal.status' },
      { title: 'Repositori Dokumen', icon: mdiFolderOutline, permission: 'dokumen.view' },
    ],
  },
  {
    title: 'Laboratorium dan K3L',
    items: [
      { title: 'Mata Kuliah Lab/Bengkel', icon: mdiFlaskOutline, permission: 'mk-lab.view' },
      { title: 'SOP Laboratorium/Bengkel', icon: mdiFlaskOutline, permission: 'sop-lab.view' },
      { title: 'SOP K3L', icon: mdiShieldCheckOutline, permission: 'sop-k3l.view' },
      { title: 'Safety Induction', icon: mdiHumanHandsup, permission: 'induksi.view' },
      { title: 'Laporan Insiden', icon: mdiAlertOctagonOutline, permission: 'insiden.view' },
    ],
  },
  {
    title: 'Evaluasi',
    items: [
      { title: 'Monitoring Pembelajaran', icon: mdiChartLine, permission: 'monitoring.view' },
      { title: 'Ketercapaian CPP', icon: mdiChartLine, permission: 'cpp-ukur.view' },
    ],
  },
]
