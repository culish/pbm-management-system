<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSignatureAuthorizationRequest;
use App\Models\ExamSubmission;
use App\Models\SignatureAuthorization;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Otorisasi hanya bisa dibuat oleh KPS untuk dirinya sendiri. Administrator dapat
 * melihat dan mencabut, tetapi tidak dapat memberi otorisasi atas nama KPS.
 */
class SignatureAuthorizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = SignatureAuthorization::query()
            ->with(['programStudi:id,nama', 'revoker:id,name'])
            ->unless($user->hasPermission('signature.manage'), fn ($q) => $q->where('signer_id', $user->id))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 10), 5), 50));

        $tz = config('pbm.display_timezone');

        return response()->json([
            'data' => collect($rows->items())->map(fn (SignatureAuthorization $a) => $this->present($a)),
            'meta' => ['total' => $rows->total(), 'page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage()],
            'bisa_memberi' => $user->hasPermission('soal.authorize'),
            'prodi' => $user->programStudi?->nama,
            'pernyataan' => $user->hasPermission('soal.authorize') && $user->programStudi
                ? sprintf(SignatureAuthorization::CONSENT_STATEMENT, '{jenis}', $user->programStudi->nama, '{tanggal}')
                : null,
            'maks_hari' => config('pbm.soal.max_authorization_days'),
            'zona_waktu' => $tz,
        ]);
    }

    public function store(StoreSignatureAuthorizationRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->program_studi_id) {
            throw ValidationException::withMessages([
                'gambar' => 'Akun Anda belum terhubung ke program studi. Minta administrator melengkapi data akun.',
            ]);
        }

        $png = $this->sanitizePng($request->file('gambar')->getRealPath());
        $path = 'signatures/'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $png);

        $validUntil = Carbon::parse($request->input('valid_until'), config('pbm.display_timezone'))->endOfDay();
        $examTypes = array_values(array_intersect(ExamSubmission::EXAM_TYPES, $request->input('exam_types')));

        $statement = sprintf(
            SignatureAuthorization::CONSENT_STATEMENT,
            implode(' dan ', array_map('strtoupper', $examTypes)),
            $user->programStudi->nama,
            $validUntil->translatedFormat('j F Y'),
        );

        $authorization = DB::transaction(function () use ($user, $request, $path, $png, $validUntil, $examTypes, $statement) {
            $previous = SignatureAuthorization::active()->where('signer_id', $user->id)->lockForUpdate()->get();
            foreach ($previous as $old) {
                $old->update(['status' => SignatureAuthorization::STATUS_REPLACED, 'revoked_at' => now(), 'revoked_by' => $user->id, 'revoke_reason' => 'Diganti dengan otorisasi baru']);
                Audit::record('signature.replaced', $old);
            }

            $authorization = SignatureAuthorization::create([
                'signer_id' => $user->id,
                'program_studi_id' => $user->program_studi_id,
                'signer_name' => $request->input('signer_name'),
                'signer_nip' => $request->input('signer_nip') ? preg_replace('/\s+/', '', $request->input('signer_nip')) : null,
                'signer_title' => $request->input('signer_title'),
                'exam_types' => $examTypes,
                'image_path' => $path,
                'image_sha256' => hash('sha256', $png),
                'consent_statement' => $statement,
                'valid_from' => now(),
                'valid_until' => $validUntil,
                'status' => SignatureAuthorization::STATUS_ACTIVE,
                'granted_ip' => $request->ip(),
            ]);

            Audit::record('signature.authorized', $authorization, [
                'exam_types' => $examTypes,
                'valid_until' => $validUntil->toIso8601String(),
                'image_sha256' => $authorization->image_sha256,
            ]);

            return $authorization;
        });

        $waiting = ExamSubmission::where('status', ExamSubmission::STATUS_WAITING)
            ->whereIn('exam_type', $examTypes)
            ->whereHas('mataKuliah', fn ($q) => $q->where('program_studi_id', $user->program_studi_id))
            ->count();

        return response()->json([
            'data' => $this->present($authorization->load('programStudi')),
            'soal_menunggu' => $waiting,
        ], 201);
    }

    public function revoke(Request $request, SignatureAuthorization $otorisasi): JsonResponse
    {
        $user = $request->user();
        $isOwner = $otorisasi->signer_id === $user->id && $user->hasPermission('soal.authorize');

        if (! $isOwner && ! $user->hasPermission('signature.manage')) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke fitur ini.'], 403);
        }

        $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:250']], [
            'alasan.required' => 'Tuliskan alasan pencabutan.',
            'alasan.min' => 'Alasan pencabutan minimal 5 karakter.',
        ]);

        $updated = DB::transaction(function () use ($otorisasi, $user, $data) {
            $locked = SignatureAuthorization::whereKey($otorisasi->id)->lockForUpdate()->first();
            if ($locked->status !== SignatureAuthorization::STATUS_ACTIVE) {
                return false;
            }
            $locked->update([
                'status' => SignatureAuthorization::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $user->id,
                'revoke_reason' => $data['alasan'],
            ]);
            Audit::record('signature.revoked', $locked, ['reason' => $data['alasan']]);

            return true;
        });

        if (! $updated) {
            return response()->json(['message' => 'Otorisasi ini sudah tidak aktif.'], 422);
        }

        return response()->json(['data' => $this->present($otorisasi->refresh()->load('programStudi', 'revoker'))]);
    }

    public function image(Request $request, SignatureAuthorization $otorisasi): Response
    {
        $user = $request->user();
        abort_unless($otorisasi->signer_id === $user->id || $user->hasPermission('signature.manage'), 404);

        return Storage::disk('local')->response($otorisasi->image_path, 'tanda-tangan.png', [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Menulis ulang PNG lewat GD: membuang metadata dan memastikan berkas benar-benar gambar.
     */
    private function sanitizePng(string $path): string
    {
        $image = @imagecreatefrompng($path);
        if (! $image) {
            throw ValidationException::withMessages(['gambar' => 'Gambar tanda tangan tidak dapat dibaca. Simpan ulang sebagai PNG.']);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private function present(SignatureAuthorization $a): array
    {
        return [
            'id' => $a->id,
            'penanda_tangan' => $a->signer_name,
            'nip' => $a->signer_nip,
            'jabatan' => $a->signer_title,
            'prodi' => $a->programStudi?->nama,
            'jenis_ujian' => $a->exam_types,
            'status' => $a->effectiveStatus(),
            'berlaku_mulai' => $a->valid_from?->toIso8601String(),
            'berlaku_sampai' => $a->valid_until?->toIso8601String(),
            'pernyataan' => $a->consent_statement,
            'dicabut_pada' => $a->revoked_at?->toIso8601String(),
            'dicabut_oleh' => $a->revoker?->name,
            'alasan_cabut' => $a->revoke_reason,
            'sha256_gambar' => $a->image_sha256,
            'dibuat' => $a->created_at?->toIso8601String(),
        ];
    }
}
