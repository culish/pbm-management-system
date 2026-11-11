# PBM Management System

Platform manajemen Proses Belajar Mengajar (PBM) untuk perguruan tinggi: dokumen pembelajaran, keselamatan lab/bengkel, kurikulum, dan penjaminan mutu.

Stack: Vue 3 (Composition API) + Vite + Vuetify 3, Laravel (REST API + Sanctum), PostgreSQL.

## Status

**Phase 1 (fondasi)** selesai. Modul lain dikerjakan bertahap pada fase berikutnya dan tampil di sidebar sebagai "Segera hadir".

| Sudah ada | Belum ada |
|---|---|
| Login, logout, token Sanctum (kedaluwarsa 8 jam), pembatasan percobaan login | Semua modul akademik (jadwal, CPP, RPS, kurikulum, dst.) |
| RBAC berbasis tabel `roles` dan `permissions` | Upload soal UTS/UAS dan tanda tangan otomatis |
| Layout responsif, sidebar yang terfilter menurut peran, breadcrumb | Repositori dokumen (tabel sudah ada, endpoint belum) |
| Dashboard (hitungan dihitung dari database) | Manajemen pengguna, pengaturan sistem |
| Audit log dasar (login, logout, login gagal) | Pencarian global dan notifikasi |

## Struktur

```
backend/    Berkas khusus aplikasi untuk Laravel (model, migrasi, seeder, rute, middleware)
frontend/   Aplikasi Vue 3 + Vuetify 3
scripts/    setup-backend.sh
antislop.md Aturan kualitas UI/teks/kode yang dipakai proyek ini
DESIGN.md   Arah desain dari pemilik produk
```

## Menjalankan

Prasyarat: PHP 8.2+, Composer, PostgreSQL, Node 18+, rsync.

### Backend

```bash
createdb pbm            # atau buat database lewat psql
./scripts/setup-backend.sh
cd backend
# isi DB_USERNAME dan DB_PASSWORD di .env
php artisan migrate --seed
php artisan serve       # http://localhost:8000
```

`setup-backend.sh` membuat kerangka Laravel di folder sementara, memasang Sanctum, lalu menyalin kerangka itu ke `backend/` tanpa menimpa berkas aplikasi yang sudah ada.

### Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev             # http://localhost:5173, proxy /api ke localhost:8000
```

### Tanpa backend (server mock)

Untuk bekerja pada UI tanpa Laravel:

```bash
cd frontend
npm run mock                                      # http://localhost:8001
VITE_PROXY_TARGET=http://localhost:8001 npm run dev
```

## Akun contoh (hanya lingkungan lokal)

Seeder `SampleDataSeeder` hanya berjalan saat `APP_ENV=local` atau `testing`. Semua akun memakai kata sandi `pbm-dev-2026`.

| Email | Peran |
|---|---|
| admin@pbm.test | Administrator |
| kps@pbm.test | Ketua Program Studi |
| dosen1@pbm.test, dosen2@pbm.test | Dosen |
| laboran@pbm.test | Laboran / Teknisi |
| mutu@pbm.test | Tim Penjaminan Mutu |
| manajemen@pbm.test | Manajemen Akademik |

Nama, program studi, dan mata kuliah pada seeder adalah **data contoh**, bukan data institusi. Dashboard hanya menampilkan angka yang dihitung dari database.

## Deployment (production)

1. Set `APP_ENV=production`, `APP_DEBUG=false`, dan kredensial PostgreSQL di `.env`.
2. Isi `ADMIN_EMAIL` dan `ADMIN_PASSWORD`, lalu `php artisan migrate --force --seed`. Di luar `local`, seeder hanya membuat peran, izin, dan satu akun administrator.
3. `php artisan config:cache && php artisan route:cache`.
4. Frontend: `npm run build`, sajikan folder `frontend/dist` lewat web server yang meneruskan `/api` ke Laravel. Gunakan HTTPS.

## RBAC

Matriks peran dan izin ada di satu berkas: `backend/database/data/permissions.json`. Seeder membacanya ke tabel, frontend menerima daftar izin dari `GET /api/auth/me`, dan rute API dilindungi `EnsurePermission`. Mengubah hak akses cukup dengan mengubah berkas itu lalu menjalankan ulang `php artisan db:seed --class=RolePermissionSeeder`.

Peran Manajemen Akademik tidak dirinci pada spesifikasi awal, sehingga saat ini hanya diberi akses lihat. Silakan sesuaikan.

## Skema database

| Tabel | Fungsi |
|---|---|
| `users` | Akun pengguna. Kolom tambahan: `role_id`, `nip`, `is_active`, `last_login_at`. |
| `roles` | Peran (admin, kps, dosen, laboran, mutu, manajemen). |
| `permissions` | Daftar izin per fitur, misalnya `soal.authorize`. |
| `permission_role` | Pivot peran ke izin. |
| `program_studi` | Program studi (kode, nama). |
| `semesters` | Tahun ajaran dan term (ganjil/genap), satu semester aktif. |
| `mata_kuliah` | Mata kuliah: kode, nama, SKS, semester ke-, jenis (teori, praktikum, teori_praktikum). |
| `documents` | Metadata dokumen: pemilik, kategori, status, hash SHA-256, versi, soft delete. Berkas disimpan di storage privat pada fase dokumen. |
| `audit_logs` | Jejak aktivitas: pengguna, aksi, objek, IP, user agent, metadata JSONB. |
| `personal_access_tokens` | Token Sanctum (dibuat oleh `install:api`). |

## Pengujian yang sudah dilakukan pada Phase 1

- Frontend: `vite build` berhasil. Alur login, validasi, dashboard, sidebar, dialog keluar, 404, dan tampilan ponsel diuji dengan Chromium headless terhadap server mock.
- Backend: seluruh berkas PHP lolos `php -l`. **Belum dijalankan terhadap Laravel dan PostgreSQL sungguhan**, karena lingkungan pembuatan tidak dapat menjangkau Packagist. Jalankan langkah instalasi di atas, lalu laporkan galat apa pun yang muncul.
