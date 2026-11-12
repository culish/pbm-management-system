# Delivery Gate antislop: Fase 2 (soal UTS/UAS)

Mode: during. Arah desain dari `DESIGN.md`. Dial yang dipakai: ENERGY 1, RHYTHM 2, MOTION 1 (aplikasi kerja harian; prioritas keterbacaan, bukan halaman pemasaran).

## Blok 1: Hard Gate

| Aturan | Hasil | Bukti |
|---|---|---|
| R-02 em dash | PASS | `grep '—'` pada `frontend/src`, `backend/app`, README: kosong |
| R-03 tata letak ponsel | PASS | Playwright 375 px: `scrollWidth - innerWidth = 0` di daftar soal dan dialog detail |
| R-17, R-38 statistik tanpa sumber | PASS | Semua angka dashboard dihitung dari database; tidak ada angka statis |
| R-18 testimoni | PASS | Tidak ada |
| R-23 aset tanpa instruksi | PASS | Tidak ada logo/avatar baru; avatar tetap inisial nama |
| R-24 tautan ke halaman yang tidak ada | PASS | Item sidebar tanpa halaman tetap "Segera hadir" dan bukan tautan |
| R-25 kontras | PASS | Warna teks memakai token Fase 1 (#1E293B, #475569, teal #0F766E) yang lolos AA |
| R-26 kontrol mati | PASS | Uji browser menekan setiap tombol: kirim, proses ulang, unduh, beri otorisasi, cabut, verifikasi |
| R-27 state kosong/muat/galat | PASS | Setiap tabel dan dialog punya skeleton, EmptyState, dan ErrorState dengan "Coba lagi" |
| R-32 keyboard | PASS | Komponen Vuetify bawaan; fokus `:focus-visible` dari Fase 1; dialog tertutup dengan Escape |
| R-33 patch lewat skrip | PASS | Tidak ada |
| R-35 dijalankan dan diklik | PASS | 25 feature test PHP; alur Playwright 8 langkah (dosen, KPS, admin, publik, ponsel) tanpa galat konsol |
| R-36 klaim palsu | PASS | README menyebut tanda tangan ini **bukan** tanda tangan tersertifikasi PSrE |
| R-39 kontrol | PASS | Setiap isian punya label terlihat; gambar punya `alt`; ikon dekoratif `aria-hidden` |
| R-40 media | PASS | Gambar tanda tangan diberi `width`/`height`; tidak ada skrip pihak ketiga |

## Blok 2: Purpose-Gate

Tidak ada gradien, glow, glassmorphism, pola latar, panah dekoratif, atau ilustrasi. Satu-satunya pola latar adalah kotak-kotak di belakang pratinjau tanda tangan: fungsinya menunjukkan bahwa PNG benar-benar transparan.

## Blok 3: Liveliness

- Titik fokus: kartu "Unggah soal" di halaman soal; kartu "Otorisasi Anda" di halaman otorisasi.
- Aksen tunggal: teal untuk progres pengesahan di dashboard. Status memakai warna semantik (hijau, kuning, merah).
- Motif identitas: garis waktu riwayat dengan titik navy, dipakai di detail soal sebagai jejak audit.

## Blok 4: Craftsmanship

PASS. Tidak ada CTA generik ("Kirim soal UTS", "Setujui dan aktifkan", "Cabut otorisasi"), tidak ada buzzword, palet tetap navy, biru, teal, dan warna status.
