<?php

namespace App\Http\Requests;

use App\Models\ExamSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSignatureAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxDays = config('pbm.soal.max_authorization_days');

        return [
            'signer_name' => ['required', 'string', 'max:150'],
            'signer_nip' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ]+$/'],
            'signer_title' => ['required', 'string', 'max:200'],
            'exam_types' => ['required', 'array', 'min:1'],
            'exam_types.*' => ['required', 'distinct', Rule::in(ExamSubmission::EXAM_TYPES)],
            'valid_until' => ['required', 'date', 'after:today', 'before_or_equal:'.now()->addDays($maxDays)->toDateString()],
            'gambar' => ['required', 'file', 'mimes:png', 'max:1024', 'dimensions:min_width=200,min_height=60,max_width=3000,max_height=1500'],
            'persetujuan' => ['accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'signer_name' => 'nama penanda tangan',
            'signer_nip' => 'NIP',
            'signer_title' => 'jabatan',
            'exam_types' => 'jenis ujian',
            'valid_until' => 'tanggal berakhir',
            'gambar' => 'gambar tanda tangan',
        ];
    }

    public function messages(): array
    {
        return [
            'persetujuan.accepted' => 'Centang pernyataan persetujuan untuk memberi otorisasi.',
            'gambar.mimes' => 'Gambar tanda tangan harus berformat PNG.',
            'gambar.dimensions' => 'Ukuran gambar tanda tangan minimal 200 x 60 piksel dan maksimal 3000 x 1500 piksel.',
            'signer_nip.regex' => 'NIP hanya boleh berisi angka.',
            'valid_until.before_or_equal' => 'Masa berlaku otorisasi paling lama '.config('pbm.soal.max_authorization_days').' hari.',
            'valid_until.after' => 'Tanggal berakhir harus setelah hari ini.',
        ];
    }
}
