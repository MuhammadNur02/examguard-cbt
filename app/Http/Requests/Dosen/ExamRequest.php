<?php

namespace App\Http\Requests\Dosen;

use App\Support\JaringanIp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'pool_size' => ['nullable', 'integer', 'min:1', 'max:999'],
            'kelas' => ['nullable', 'array'],
            'kelas.*' => ['integer', 'exists:classes,id'],
            'kode_akses' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{4,20}$/'],
            'ip_allowlist' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * Setiap baris daftar IP harus alamat IP atau CIDR yang valid; nomor baris
     * mengikuti baris di kotak isian (baris kosong ikut dihitung).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $galat = [];
            foreach (preg_split('/\r\n|\r|\n/', (string) $this->input('ip_allowlist')) as $i => $baris) {
                foreach (JaringanIp::pecah($baris) as $alamat) {
                    if (! JaringanIp::valid($alamat)) {
                        $galat[] = 'Baris '.($i + 1)." bukan alamat IP atau CIDR yang valid: {$alamat}.";
                    }
                }
            }
            if ($galat !== []) {
                $validator->errors()->add('ip_allowlist', implode(' ', $galat));
            } elseif (count($this->daftarIp()) > JaringanIp::MAKS_BARIS) {
                $validator->errors()->add('ip_allowlist', 'Daftar IP maksimal '.JaringanIp::MAKS_BARIS.' baris.');
            }
        }];
    }

    /** Daftar IP/CIDR yang dinormalkan (satu per baris); null bila semua jaringan diizinkan (FR-02.9). */
    public function daftarIpTeks(): ?string
    {
        return $this->daftarIp() === [] ? null : implode("\n", $this->daftarIp());
    }

    /** @return list<string> */
    private function daftarIp(): array
    {
        return JaringanIp::pecah((string) $this->input('ip_allowlist'));
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
            'ip_allowlist' => 'daftar IP',
            'pool_size' => 'jumlah soal per mahasiswa',
        ];
    }

    /** @return array<string, mixed> */
    public function dataUjian(): array
    {
        return [
            ...$this->safe()->only(['judul', 'mata_kuliah', 'mulai', 'durasi_menit', 'batas_pelanggaran']),
            'acak_soal' => $this->boolean('acak_soal'),
            'acak_opsi' => $this->boolean('acak_opsi'),
            'pool_size' => filled($this->input('pool_size')) ? (int) $this->input('pool_size') : null,
        ];
    }
}
