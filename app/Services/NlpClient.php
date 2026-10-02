<?php

namespace App\Services;

use App\Exceptions\NlpTidakTersedia;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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
        try {
            $respons = Http::baseUrl((string) config('services.nlp.url'))
                ->timeout((int) config('services.nlp.timeout'))
                ->withHeaders(['X-Internal-Token' => (string) config('services.nlp.token')])
                ->acceptJson()
                ->post('/score', [
                    'kunci' => $kunci,
                    'jawaban' => $jawaban,
                    'kata_kunci' => array_values($kataKunci),
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new NlpTidakTersedia('Layanan NLP tidak dapat dipakai: '.$e->getMessage(), 0, $e);
        }

        $hasil = [];
        foreach ((array) $respons->json('hasil') as $baris) {
            $hasil[(int) $baris['id']] = [
                'similarity' => max(0.0, min(1.0, (float) $baris['similarity'])),
                'kata_kunci_terpenuhi' => array_values((array) ($baris['kata_kunci_terpenuhi'] ?? [])),
            ];
        }

        return $hasil;
    }
}
