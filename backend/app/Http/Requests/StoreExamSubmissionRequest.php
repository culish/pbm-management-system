<?php

namespace App\Http\Requests;

use App\Models\ExamSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mata_kuliah_id' => ['required', 'integer', 'exists:mata_kuliah,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'exam_type' => ['required', Rule::in(ExamSubmission::EXAM_TYPES)],
            'berkas' => ['required', 'file', 'extensions:pdf,docx', 'max:'.config('pbm.soal.max_upload_kb')],
        ];
    }

    public function attributes(): array
    {
        return [
            'mata_kuliah_id' => 'mata kuliah',
            'semester_id' => 'semester',
            'exam_type' => 'jenis ujian',
            'berkas' => 'berkas soal',
        ];
    }

    public function messages(): array
    {
        return [
            'berkas.extensions' => 'Berkas soal harus berformat PDF atau DOCX.',
            'berkas.max' => 'Ukuran berkas soal maksimal '.(int) (config('pbm.soal.max_upload_kb') / 1024).' MB.',
            'berkas.required' => 'Pilih berkas soal yang akan diunggah.',
            'exam_type.in' => 'Jenis ujian harus UTS atau UAS.',
        ];
    }
}
