# Arah desain PBM Management System

Sumber: master prompt dari pemilik produk. Isi di bawah adalah transkripsi, bukan tambahan agen.

## Identitas
Platform manajemen akademik tingkat enterprise untuk perguruan tinggi di Indonesia. Tampilan profesional, bersih, dan terstruktur. Utamakan keterbacaan dan produktivitas, hindari dekorasi berlebihan.

## Warna
| Peran | Nilai dari pemilik produk | Catatan implementasi |
|---|---|---|
| Utama (navy) | #142B4A | Sidebar, tombol utama, judul |
| Sekunder (biru) | #2563EB | Grafik, tautan, indikator |
| Aksen (teal) | #0D9488 | Dipakai sebagai #0F766E untuk teks dan ikon kecil agar lolos kontras WCAG AA (4.5:1). #0D9488 asli hanya 3.7:1 di atas putih. |
| Latar | #F8FAFC | Latar halaman |

## Tipografi
Plus Jakarta Sans, dibundel lokal lewat `@fontsource-variable/plus-jakarta-sans` (tanpa CDN).

## Tata letak
Sidebar kiri yang dapat diciutkan, bilah atas dengan judul halaman dan menu pengguna, breadcrumb, kartu berpojok bulat dengan bayangan tipis. Responsif untuk ponsel, tablet, dan desktop.

## Bahasa
Seluruh label antarmuka berbahasa Indonesia.

## Keputusan terbuka
- Logo: belum ada. Memakai teks "PBM" sebagai penanda sampai logo institusi tersedia.
- Pencarian global dan notifikasi di bilah atas belum dibuat karena belum ada modul yang dapat dicari atau memicu notifikasi.
