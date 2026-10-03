<?php

namespace App\Services;

use App\Exceptions\NlpTidakTersedia;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien HTTP internal ke layanan NLP FastAPI (PRD §9.3). Layanan tidak
 * diekspos publik; setiap permintaan membawa token internal.
 */
class NlpClient
{
    /**
     * Similarity tiap jawaban terhadap kunci. Seluruh jawaban satu soal dikirim
     * sekaligus karena IDF dihitung dari kunci + semua jawaban (K-2).
     *
     * @param  list<array{id: int, teks: string}>  $jawaban
     * @param  list<string>  $kataKunci
     * @return array<int, array{similarity: float, kata_kunci_terpenuhi: list<string>}> per id jawaban
     *
     * @throws NlpTidakTersedia
     */
    public function skor(string $kunci, array $jawaban, array $kataKunci = []): array
    {
        $respons = $this->kirim('/score', [
            'kunci' => $kunci,
            'jawaban' => $jawaban,
            'kata_kunci' => array_values($kataKunci),
        ]);

        $hasil = [];
        foreach ((array) $respons->json('hasil') as $baris) {
            $hasil[(int) $baris['id']] = [
                'similarity' => max(0.0, min(1.0, (float) $baris['similarity'])),
                'kata_kunci_terpenuhi' => array_values((array) ($baris['kata_kunci_terpenuhi'] ?? [])),
            ];
        }

        return $hasil;
    }

    /**
     * Pasangan jawaban antarmahasiswa yang mirip (FR-05.5), urut skor menurun.
     *
     * @param  list<array{id: int, teks: string}>  $jawaban
     * @return list<array{a: int, b: int, skor: float}> a dan b adalah id jawaban
     *
     * @throws NlpTidakTersedia
     */
    public function kemiripan(array $jawaban, float $ambang, int $minToken): array
    {
        $respons = $this->kirim('/kemiripan', ['jawaban' => $jawaban, 'ambang' => $ambang, 'min_token' => $minToken]);

        return array_map(fn (array $p) => [
            'a' => (int) $p['a'],
            'b' => (int) $p['b'],
            'skor' => max(0.0, min(1.0, (float) $p['skor'])),
        ], array_values((array) $respons->json('pasangan', [])));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws NlpTidakTersedia
     */
    private function kirim(string $path, array $data): Response
    {
        try {
            return Http::baseUrl((string) config('services.nlp.url'))
                ->timeout((int) config('services.nlp.timeout'))
                ->withHeaders(['X-Internal-Token' => (string) config('services.nlp.token')])
                ->acceptJson()
                ->post($path, $data)
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new NlpTidakTersedia('Layanan NLP tidak dapat dipakai: '.$e->getMessage(), 0, $e);
        }
    }
}
