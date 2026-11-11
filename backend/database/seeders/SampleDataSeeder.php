<?php

namespace Database\Seeders;

use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk pengembangan lokal. Tidak dijalankan di production.
 * Nama, NIP, dan mata kuliah di bawah adalah contoh, bukan data institusi.
 */
class SampleDataSeeder extends Seeder
{
    private const DEV_PASSWORD = 'pbm-dev-2026';

    public function run(): void
    {
        $prodi = ProgramStudi::updateOrCreate(
            ['kode' => 'TM'],
            ['nama' => 'Teknik Mesin (contoh)'],
        );

        Semester::updateOrCreate(
            ['tahun_ajaran' => '2026/2027', 'term' => 'ganjil'],
            ['mulai' => '2026-09-01', 'selesai' => '2027-01-31', 'is_active' => true],
        );

        $courses = [
            ['TM101', 'Matematika Teknik I', 3, 1, 'teori'],
            ['TM102', 'Gambar Teknik', 3, 1, 'teori_praktikum'],
            ['TM103', 'Fisika Terapan', 2, 1, 'teori'],
            ['TM201', 'Mekanika Teknik', 3, 2, 'teori'],
            ['TM202', 'Proses Manufaktur I', 3, 2, 'teori_praktikum'],
            ['TM203', 'Praktik Kerja Bengkel', 2, 2, 'praktikum'],
            ['TM301', 'Termodinamika', 3, 3, 'teori'],
            ['TM302', 'Elemen Mesin', 3, 3, 'teori'],
            ['TM303', 'Praktikum Pengelasan', 2, 3, 'praktikum'],
            ['TM401', 'Mekanika Fluida', 3, 4, 'teori'],
            ['TM402', 'Praktikum Pengukuran', 2, 4, 'praktikum'],
            ['TM501', 'Perpindahan Panas', 3, 5, 'teori'],
        ];

        foreach ($courses as [$kode, $nama, $sks, $semesterKe, $jenis]) {
            MataKuliah::updateOrCreate(
                ['kode' => $kode],
                ['program_studi_id' => $prodi->id, 'nama' => $nama, 'sks' => $sks, 'semester_ke' => $semesterKe, 'jenis' => $jenis],
            );
        }

        $users = [
            ['admin', 'Admin Contoh', 'admin@pbm.test'],
            ['kps', 'KPS Contoh', 'kps@pbm.test'],
            ['dosen', 'Dosen Contoh Satu', 'dosen1@pbm.test'],
            ['dosen', 'Dosen Contoh Dua', 'dosen2@pbm.test'],
            ['laboran', 'Laboran Contoh', 'laboran@pbm.test'],
            ['mutu', 'Penjaminan Mutu Contoh', 'mutu@pbm.test'],
            ['manajemen', 'Manajemen Contoh', 'manajemen@pbm.test'],
        ];

        foreach ($users as [$role, $name, $email]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => self::DEV_PASSWORD,
                    'role_id' => Role::where('key', $role)->value('id'),
                    'is_active' => true,
                ],
            );
        }
    }
}
