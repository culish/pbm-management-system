<?php

namespace Tests\Support;

use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use TCPDF;
use ZipArchive;

trait SoalFixtures
{
    protected ProgramStudi $prodi;
    protected Semester $semester;
    protected MataKuliah $mk;
    protected User $kps;
    protected User $dosen;
    protected User $dosenLain;
    protected User $admin;

    protected function setUpSoal(): void
    {
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);

        $this->prodi = ProgramStudi::create(['kode' => 'TM', 'nama' => 'Teknik Mesin']);
        $this->semester = Semester::create(['tahun_ajaran' => '2026/2027', 'term' => 'ganjil', 'is_active' => true]);
        $this->mk = MataKuliah::create(['program_studi_id' => $this->prodi->id, 'kode' => 'TM301', 'nama' => 'Termodinamika', 'sks' => 3, 'semester_ke' => 3]);

        $this->kps = $this->makeUser('kps', 'kps@test.local', $this->prodi->id);
        $this->dosen = $this->makeUser('dosen', 'dosen@test.local', $this->prodi->id);
        $this->dosenLain = $this->makeUser('dosen', 'dosen2@test.local', $this->prodi->id);
        $this->admin = $this->makeUser('admin', 'admin@test.local', null);

        $this->mk->pengampu()->attach($this->dosen->id, ['semester_id' => $this->semester->id]);
    }

    protected function makeUser(string $role, string $email, ?int $prodiId): User
    {
        return User::create([
            'name' => ucfirst($role).' Uji',
            'email' => $email,
            'password' => 'rahasia-uji',
            'role_id' => Role::where('key', $role)->value('id'),
            'program_studi_id' => $prodiId,
            'is_active' => true,
        ]);
    }

    protected function pdfUpload(bool $withMarker = true, string $name = 'soal.pdf'): UploadedFile
    {
        $pdf = new TCPDF('P', 'pt', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(16, "Soal UTS Termodinamika\n1. Jelaskan hukum pertama termodinamika.\n");
        $pdf->AddPage();
        $pdf->Write(16, "2. Hitung efisiensi siklus Carnot.\n\nMengetahui,\nKetua Program Studi\n\n");
        if ($withMarker) {
            $pdf->Write(16, config('pbm.soal.signature_marker')."\n");
        }

        return UploadedFile::fake()->createWithContent($name, $pdf->Output('', 'S'));
    }

    protected function docxUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Soal UAS Termodinamika</w:t></w:r></w:p><w:p><w:r><w:t>Mengetahui, Ketua Program Studi</w:t></w:r></w:p><w:p><w:r><w:t>[TTD_KPS]</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return UploadedFile::fake()->createWithContent('soal-uas.docx', file_get_contents($path));
    }

    protected function signatureImage(): UploadedFile
    {
        $img = imagecreatetruecolor(400, 140);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagesetthickness($img, 4);
        imageline($img, 20, 110, 380, 30, imagecolorallocate($img, 20, 43, 74));
        ob_start();
        imagepng($img);

        return UploadedFile::fake()->createWithContent('ttd.png', ob_get_clean());
    }

    protected function grantAuthorization(array $overrides = []): array
    {
        return $this->actingAs($this->kps)->post('/api/otorisasi-ttd', array_merge([
            'signer_name' => 'Dr. Siti Rahmawati, M.T.',
            'signer_nip' => '197801012005012001',
            'signer_title' => 'Ketua Program Studi Teknik Mesin',
            'exam_types' => ['uts', 'uas'],
            'valid_until' => now()->addMonths(6)->toDateString(),
            'gambar' => $this->signatureImage(),
            'persetujuan' => '1',
        ], $overrides), ['Accept' => 'application/json'])->assertCreated()->json();
    }

    protected function upload(?UploadedFile $file = null, string $type = 'uts', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->dosen)->post('/api/soal', [
            'mata_kuliah_id' => $this->mk->id,
            'semester_id' => $this->semester->id,
            'exam_type' => $type,
            'berkas' => $file ?? $this->pdfUpload(),
        ], ['Accept' => 'application/json']);
    }
}
