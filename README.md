# PBM Management System

Platform manajemen Proses Belajar Mengajar (PBM) untuk perguruan tinggi: dokumen pembelajaran, keselamatan lab/bengkel, kurikulum, dan penjaminan mutu.

Stack: Vue 3 (Composition API) + Vite + Vuetify 3, Laravel 13 (REST API + Sanctum), PostgreSQL.

## Status

| Fase | Isi | Status |
|---|---|---|
| 1. Fondasi | Login, token Sanctum, RBAC, layout responsif, sidebar per peran, dashboard, audit log | Selesai, diuji pada Laravel 13 + PostgreSQL 16 |
| 2. Soal UTS/UAS dan pengesahan | Upload soal, otorisasi tanda tangan KPS, pembubuhan tanda tangan, verifikasi publik | Selesai, 25 feature test + uji alur di browser |
| Berikutnya | Master data akademik, Jadwal PBM, PPM dan CPP, Kurikulum IKI, RPS, Matriks, Repositori Dokumen, SOP, K3L, Monitoring, CPP | Tampil di sidebar sebagai "Segera hadir" |

## Struktur

```
backend/    Aplikasi Laravel (API saja)
  app/Services/Soal/   Alur pengesahan soal: inspeksi berkas, konversi DOCX, pencarian penanda, stempel PDF
frontend/   Aplikasi Vue 3 + Vuetify 3
scripts/    setup-backend.sh
antislop.md Aturan kualitas UI/teks/kode yang dipakai proyek ini
DESIGN.md   Arah desain dari pemilik produk
docs/       Catatan per fase
```

## Menjalankan

Prasyarat:

- PHP 8.3+ dengan ekstensi `pdo_pgsql`, `gd`, `zip`, `mbstring`, Composer
- PostgreSQL 14+
- Node 18+
- Untuk modul soal: LibreOffice (konversi DOCX), Poppler `pdftotext` (mencari penanda), `qpdf` (membaca PDF dari Word/PDF 1.5+)

  ```bash
  sudo apt install libreoffice-writer poppler-utils qpdf
  ```

### Backend

```bash
createdb pbm                       # atau buat database lewat psql
./scripts/setup-backend.sh         # composer install, .env, APP_KEY
cd backend
# isi DB_USERNAME dan DB_PASSWORD di .env
php artisan migrate --seed
php artisan serve                  # http://localhost:8000
```

### Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev                        # http://localhost:5173, /api diteruskan ke localhost:8000
```

### Pengujian backend

Test memakai database PostgreSQL `pbm_test` (lihat `backend/phpunit.xml`).

```bash
createdb pbm_test
cd backend && php artisan test
```

## Akun contoh (hanya lingkungan lokal)

Seeder `SampleDataSeeder` hanya berjalan saat `APP_ENV=local` atau `testing`. Semua akun memakai kata sandi `pbm-dev-2026`.

| Email | Peran |
|---|---|
| admin@pbm.test | Administrator |
| kps@pbm.test | Ketua Program Studi |
| dosen1@pbm.test, dosen2@pbm.test | Dosen (masing-masing mengampu 6 mata kuliah semester aktif) |
| laboran@pbm.test | Laboran / Teknisi |
| mutu@pbm.test | Tim Penjaminan Mutu |
| manajemen@pbm.test | Manajemen Akademik |

Nama, program studi, dan mata kuliah pada seeder adalah **data contoh**, bukan data institusi. Seeder **tidak** membuat otorisasi tanda tangan: KPS harus memberikannya sendiri lewat aplikasi.

## Modul soal UTS/UAS

### Alur dosen

1. Buka **Soal UTS/UAS**, pilih mata kuliah dan jenis ujian (semester aktif terisi otomatis).
2. Pilih berkas PDF atau DOCX, klik **Kirim soal**.
3. Sistem langsung:
   - memeriksa isi berkas (bukan hanya ekstensi),
   - menyimpan berkas asli apa adanya beserta SHA-256-nya,
   - mencari otorisasi KPS yang berlaku untuk program studi dan jenis ujian itu,
   - bila ada: mengonversi DOCX ke PDF, membubuhkan tanda tangan, menyimpan PDF final, dan menampilkan pratinjaunya,
   - bila tidak ada: soal tetap tersimpan dengan status **Menunggu otorisasi KPS** dan alasan yang jelas (belum ada, kedaluwarsa, dicabut, tidak mencakup UAS, akun KPS nonaktif, gambar tanda tangan berubah).

Dosen hanya bisa mengunggah untuk mata kuliah yang ia ampu pada semester itu. Unggahan ulang membuat versi baru; versi lama tetap tersimpan.

### Posisi tanda tangan

- Tulis `[TTD_KPS]` di dokumen pada tempat tanda tangan KPS. Sistem menutup teks penanda itu dan meletakkan gambar tanda tangan, nama, NIP, waktu, dan kode verifikasi di posisinya.
- Tanpa penanda, sistem menambahkan **Lembar Pengesahan Soal** di akhir dokumen. Halaman soal tidak ditimpa.
- Catatan: teks `[TTD_KPS]` tertutup secara visual, tetapi masih ada di lapisan teks PDF (bisa terbaca bila teks PDF disalin).

### Otorisasi KPS

- Hanya akun KPS yang bisa memberi otorisasi, dan hanya untuk dirinya sendiri. Administrator tidak memiliki izin `soal.authorize`, sehingga tidak bisa memberi otorisasi atas nama KPS.
- KPS mengunggah gambar tanda tangan PNG, memilih jenis ujian (UTS/UAS), menentukan tanggal berakhir (maks. 366 hari), dan mencentang pernyataan persetujuan. Teks pernyataan lengkap disimpan bersama otorisasi.
- Gambar ditulis ulang lewat GD (membuang metadata) dan disimpan dengan SHA-256. Bila berkas gambar berubah, pengesahan dihentikan.
- KPS atau administrator dapat mencabut otorisasi dengan alasan. Pencabutan berlaku seketika; baris otorisasi dikunci selama pemrosesan sehingga pencabutan yang bersamaan tidak terlewat.
- Otorisasi baru mengganti otorisasi lama (status "Diganti").

### Verifikasi

Setiap PDF yang disahkan memuat kode verifikasi. Halaman publik `/verifikasi/{kode}` menampilkan mata kuliah, penanda tangan, waktu, dan SHA-256 PDF final, tanpa isi soal.

### Batasan yang perlu diketahui

Tanda tangan yang dibubuhkan adalah **gambar tanda tangan dengan jejak audit**, bukan tanda tangan elektronik tersertifikasi (PSrE/BSrE). Untuk keabsahan hukum penuh, institusi perlu bekerja sama dengan Penyelenggara Sertifikasi Elektronik dan menambahkan penandatanganan PAdES di `ExamSigningService`. Integrasi itu memerlukan kredensial dari institusi dan belum dibuat; sistem tidak pernah mensimulasikannya.

## Deployment (production)

1. Set `APP_ENV=production`, `APP_DEBUG=false`, dan kredensial PostgreSQL di `backend/.env`.
2. Isi `ADMIN_EMAIL` dan `ADMIN_PASSWORD`, lalu `php artisan migrate --force --seed`. Di luar `local`, seeder hanya membuat peran, izin, dan satu akun administrator.
3. `php artisan config:cache && php artisan route:cache`.
4. Pastikan LibreOffice, `pdftotext`, dan `qpdf` terpasang di server, dan pengguna web server dapat menulis ke `backend/storage`. Sesuaikan path di `.env` (`SOFFICE_BINARY`, `PDFTOTEXT_BINARY`, `QPDF_BINARY`) bila tidak ada di `PATH`.
5. Frontend: `npm run build`, sajikan `frontend/dist` lewat web server yang meneruskan `/api` ke Laravel dan mengarahkan rute lain ke `index.html`. Gunakan HTTPS.
6. Atur `upload_max_filesize` dan `post_max_size` PHP minimal 20 MB (atau sesuai `SOAL_MAX_UPLOAD_KB`).

## RBAC

Matriks peran dan izin ada di satu berkas: `backend/database/data/permissions.json`. Seeder membacanya ke tabel, frontend menerima daftar izin dari `GET /api/auth/me`, dan rute API dilindungi `EnsurePermission`. Peran dengan `"*"` mendapat semua izin kecuali yang tercantum di `"except"`. Setelah mengubah berkas itu, jalankan `php artisan db:seed --class=RolePermissionSeeder`.

Peran Manajemen Akademik tidak dirinci pada spesifikasi awal, sehingga saat ini hanya diberi akses lihat.

## Skema database

| Tabel | Fungsi |
|---|---|
| `users` | Akun pengguna. Kolom tambahan: `role_id`, `program_studi_id`, `nip`, `is_active`, `last_login_at`. |
| `roles`, `permissions`, `permission_role` | RBAC. |
| `program_studi` | Program studi (kode, nama). |
| `semesters` | Tahun ajaran dan term (ganjil/genap), satu semester aktif. |
| `mata_kuliah` | Kode, nama, SKS, semester ke-, jenis (teori, praktikum, teori_praktikum). |
| `mata_kuliah_pengampu` | Dosen pengampu per mata kuliah per semester. Menentukan siapa boleh mengunggah soal. |
| `documents` | Metadata berkas: pemilik, kategori (`soal_asli`, `soal_final`, ...), status, SHA-256, versi, soft delete. Berkas ada di storage privat (`storage/app/private`). |
| `signature_authorizations` | Otorisasi KPS: penanda tangan, prodi, jenis ujian (JSONB), path dan SHA-256 gambar, teks persetujuan, masa berlaku, status, data pencabutan. |
| `exam_submissions` | Pengajuan soal: mata kuliah, semester, jenis, versi, dosen, berkas asli dan final, otorisasi yang dipakai, status, alasan, posisi tanda tangan, kode verifikasi. |
| `audit_logs` | Jejak aktivitas: pengguna, aksi, objek, IP, user agent, metadata JSONB. |
| `personal_access_tokens` | Token Sanctum. |

Relasi utama: `exam_submissions` → `mata_kuliah` → `program_studi` ← `signature_authorizations` → `users` (KPS). Setiap pengajuan menunjuk dua baris `documents` (asli dan final) dan, bila disahkan, otorisasi yang dipakai.

## API

| Metode | Rute | Izin |
|---|---|---|
| POST | `/api/auth/login` | publik (5 kegagalan per email+IP per menit) |
| GET | `/api/auth/me`, POST `/api/auth/logout` | login |
| GET | `/api/dashboard` | `dashboard.view` |
| GET | `/api/soal/opsi` | `soal.upload` |
| POST | `/api/soal` | `soal.upload` |
| GET | `/api/soal`, `/api/soal/{id}`, `/api/soal/{id}/berkas/{asli\|final}` | `soal.upload`, `soal.status`, `soal.authorize`, atau `dokumen.manage` (data dibatasi: dosen melihat miliknya, KPS melihat prodinya, admin melihat semua) |
| POST | `/api/soal/{id}/proses` | pemilik, KPS prodi, atau admin |
| GET/POST | `/api/otorisasi-ttd` | lihat: `soal.authorize` atau `signature.manage`; buat: `soal.authorize` |
| POST | `/api/otorisasi-ttd/{id}/cabut` | KPS pemilik atau `signature.manage` |
| GET | `/api/otorisasi-ttd/{id}/gambar` | KPS pemilik atau `signature.manage` |
| GET | `/api/verifikasi/{kode}` | publik |
