<?php

namespace App\Http\Requests\Dosen;

use Illuminate\Foundation\Http\FormRequest;

class ExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'mata_kuliah' => ['required', 'string', 'max:255'],
            'mulai' => ['required', 'date'],
            'durasi_menit' => ['required', 'integer', 'min:1', 'max:600'],
            'batas_pelanggaran' => ['required', 'integer', 'min:1', 'max:20'],
            'acak_soal' => ['boolean'],
            'acak_opsi' => ['boolean'],
            'kelas' => ['nullable', 'array'],
            'kelas.*' => ['integer', 'exists:classes,id'],
            'kode_akses' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{4,20}$/'],
        ];
    }

    /** @return list<int> */
    public function kelasDipilih(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('kelas', []))));
    }

    /** Kode akses dinormalkan ke huruf besar; null bila ujian tidak memakai kode (FR-02.8). */
    public function kodeAkses(): ?string
    {
        return filled($this->input('kode_akses')) ? strtoupper(trim((string) $this->input('kode_akses'))) : null;
    }

    public function messages(): array
    {
        return ['kode_akses.regex' => 'Kode akses 4–20 karakter: huruf, angka, atau tanda hubung.'];
    }

    public function attributes(): array
    {
        return [
            'mata_kuliah' => 'mata kuliah',
            'mulai' => 'waktu mulai',
            'durasi_menit' => 'durasi',
            'batas_pelanggaran' => 'batas pelanggaran',
            'kode_akses' => 'kode akses',
        ];
    }

    /** @return array<string, mixed> */
    public function dataUjian(): array
    {
        return [
            ...$this->safe()->only(['judul', 'mata_kuliah', 'mulai', 'durasi_menit', 'batas_pelanggaran']),
            'acak_soal' => $this->boolean('acak_soal'),
            'acak_opsi' => $this->boolean('acak_opsi'),
        ];
    }
}
