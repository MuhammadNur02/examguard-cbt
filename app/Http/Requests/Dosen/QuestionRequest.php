<?php

namespace App\Http\Requests\Dosen;

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi soal (FR-02.2): PG berbobot dengan tepat satu kunci di antara opsi
 * A–E yang terisi berurutan; esai wajib punya kunci patokan.
 */
class QuestionRequest extends FormRequest
{
    public const LABEL = ['A', 'B', 'C', 'D', 'E'];

    public const MAKS_KATA_KUNCI = 20;

    public function authorize(): bool
    {
        return true;
    }

    /** Tipe soal: dari soal yang diubah (tidak bisa diganti) atau dari input saat membuat. */
    public function tipe(): ?QuestionType
    {
        $question = $this->route('question');

        return $question instanceof Question
            ? $question->tipe
            : QuestionType::tryFrom((string) $this->input('tipe'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'teks' => ['required', 'string', 'max:5000'],
            'bobot' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'urutan' => ['required', 'integer', 'min:1', 'max:999'],
        ];

        if (! $this->route('question')) {
            $rules['tipe'] = ['required', Rule::enum(QuestionType::class)];
        }

        if ($this->tipe() === QuestionType::Pg) {
            return $rules + [
                'opsi' => ['required', 'array'],
                'opsi.A.teks' => ['required', 'string', 'max:1000'],
                'opsi.B.teks' => ['required', 'string', 'max:1000'],
                'opsi.C.teks' => ['nullable', 'string', 'max:1000'],
                'opsi.D.teks' => ['nullable', 'string', 'max:1000'],
                'opsi.E.teks' => ['nullable', 'string', 'max:1000'],
                'opsi.*.tetap' => ['nullable', 'boolean'],
                'kunci' => ['required', Rule::in(self::LABEL)],
            ];
        }

        return $rules + [
            'kunci_esai' => ['required', 'string', 'max:5000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'teks' => 'teks soal',
            'urutan' => 'nomor urut',
            'kunci' => 'kunci jawaban',
            'kunci_esai' => 'kunci jawaban esai',
            'keywords' => 'kata kunci',
            'opsi.A.teks' => 'opsi A',
            'opsi.B.teks' => 'opsi B',
            'opsi.C.teks' => 'opsi C',
            'opsi.D.teks' => 'opsi D',
            'opsi.E.teks' => 'opsi E',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->tipe() === QuestionType::Pg) {
                $terisi = $this->labelTerisi();
                if ($terisi !== array_slice(self::LABEL, 0, count($terisi))) {
                    $validator->errors()->add('opsi', 'Opsi harus diisi berurutan mulai dari A tanpa ada yang terlewat.');
                }
                if (! in_array($this->input('kunci'), $terisi, true)) {
                    $validator->errors()->add('kunci', 'Kunci jawaban harus salah satu opsi yang terisi.');
                }
            } elseif (count($this->kataKunci()) > self::MAKS_KATA_KUNCI) {
                $validator->errors()->add('keywords', 'Kata kunci maksimal '.self::MAKS_KATA_KUNCI.' butir.');
            }
        }];
    }

    /** @return list<string> */
    public function labelTerisi(): array
    {
        return array_values(array_filter(self::LABEL, fn (string $label) => filled($this->input("opsi.{$label}.teks"))));
    }

    /** @return list<string> */
    public function kataKunci(): array
    {
        return self::pecahKataKunci((string) $this->input('keywords'));
    }

    /** Kata kunci dipisah koma atau baris baru, tanpa duplikat (dipakai juga oleh impor soal). @return list<string> */
    public static function pecahKataKunci(string $teks): array
    {
        $bagian = preg_split('/[,\r\n]+/', $teks);

        return array_values(array_unique(array_filter(array_map('trim', $bagian), fn ($k) => $k !== '')));
    }

    /** @return array<string, mixed> */
    public function dataSoal(): array
    {
        $tipe = $this->tipe();
        $pg = $tipe === QuestionType::Pg;

        return [
            'tipe' => $tipe,
            'teks' => $this->input('teks'),
            'bobot' => (float) $this->input('bobot'),
            'urutan' => (int) $this->input('urutan'),
            'kunci_esai' => $pg ? null : $this->input('kunci_esai'),
            'keywords' => $pg ? null : ($this->kataKunci() ?: null),
        ];
    }
}
