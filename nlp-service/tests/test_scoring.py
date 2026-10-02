"""Task 4.3 / FR-05.3: TF-IDF + Cosine Similarity dengan contoh hitung manual.

Rumus (bawaan scikit-learn TfidfVectorizer, dipakai apa adanya):
    tf(t, d)  = frekuensi mentah term t di dokumen d
    idf(t)    = ln((1 + n) / (1 + df(t))) + 1      (n = jumlah dokumen di korpus)
    w(t, d)   = tf(t, d) * idf(t), lalu vektor tiap dokumen dinormalisasi L2
    cos(A, B) = (A . B) / (||A|| ||B||)

Contoh manual (korpus = kunci + seluruh jawaban, K-2), token sudah dipraproses:
    D0 (kunci) = [a, b, c]   D1 = [a, b]   D2 = [c, d]      n = 3
    df: a = 2, b = 2, c = 2, d = 1
    idf(a) = idf(b) = idf(c) = ln(4/3) + 1 = 1,287682
    idf(d) = ln(4/2) + 1 = 1,693147
    D0 = (1,2877; 1,2877; 1,2877; 0) -> normal L2: (0,57735; 0,57735; 0,57735; 0)
    D1 = (1,2877; 1,2877; 0; 0)      -> (0,70711; 0,70711; 0; 0)
    D2 = (0; 0; 1,2877; 1,6931)      -> (0; 0; 0,60535; 0,79596)
    cos(D0, D1) = 2 x 0,57735 x 0,70711 = 2/sqrt(6) = 0,8165
    cos(D0, D2) = 0,57735 x 0,60535      = 0,3495
"""

import math
import random

import pytest

from app.scoring import cek_kata_kunci, kemiripan_token, skor_esai


def _manual(kunci, jawaban):
    """Implementasi rumus di atas secara independen (tanpa scikit-learn)."""
    korpus = [kunci, *jawaban]
    n = len(korpus)
    kosakata = sorted({t for dok in korpus for t in dok})
    df = {t: sum(1 for dok in korpus if t in dok) for t in kosakata}
    idf = {t: math.log((1 + n) / (1 + df[t])) + 1 for t in kosakata}

    def vektor(dok):
        v = [dok.count(t) * idf[t] for t in kosakata]
        norma = math.sqrt(sum(x * x for x in v))
        return [x / norma for x in v] if norma else v

    a = vektor(kunci)
    return [sum(x * y for x, y in zip(a, vektor(d), strict=True)) for d in jawaban]


def test_contoh_hitung_manual():
    hasil = kemiripan_token(["a", "b", "c"], [["a", "b"], ["c", "d"]])

    assert hasil[0] == pytest.approx(2 / math.sqrt(6), abs=1e-9)
    assert round(hasil[0], 4) == 0.8165
    assert round(hasil[1], 4) == 0.3495
    assert hasil == pytest.approx(_manual(["a", "b", "c"], [["a", "b"], ["c", "d"]]), abs=1e-9)


def test_frekuensi_term_ikut_dihitung():
    kunci = ["data", "data", "kirim"]
    jawaban = [["data", "kirim"], ["data", "data", "kirim"], ["kirim", "kirim"]]

    assert kemiripan_token(kunci, jawaban) == pytest.approx(_manual(kunci, jawaban), abs=1e-9)


def test_cocok_dengan_rumus_manual_pada_data_acak():
    rng = random.Random(2026)
    kata = [f"k{i}" for i in range(12)]
    for _ in range(30):
        kunci = rng.choices(kata, k=rng.randint(1, 8))
        jawaban = [rng.choices(kata, k=rng.randint(0, 8)) for _ in range(rng.randint(1, 6))]
        assert kemiripan_token(kunci, jawaban) == pytest.approx(_manual(kunci, jawaban), abs=1e-9)


def test_rentang_skor_nol_sampai_satu():
    assert kemiripan_token(["a", "b"], [["a", "b"]]) == pytest.approx([1.0])
    assert kemiripan_token(["a", "b"], [["x", "y"]]) == [0.0]
    assert kemiripan_token(["a", "b"], [[]]) == [0.0]
    assert kemiripan_token([], [["a"]]) == [0.0]
    assert kemiripan_token([], [[]]) == [0.0]
    assert kemiripan_token(["a"], []) == []


def test_variasi_idf_hanya_dari_kunci():
    # Korpus hanya kunci (n = 1): semua idf = ln(2/2) + 1 = 1 dan term di luar
    # kunci (d) diabaikan, sehingga D2 tampak lebih mirip: 1/sqrt(3) = 0,5774.
    hasil = kemiripan_token(["a", "b", "c"], [["a", "b"], ["c", "d"]], korpus_idf="kunci")

    assert round(hasil[0], 4) == 0.8165
    assert round(hasil[1], 4) == 0.5774


def test_skor_esai_dari_teks_mentah():
    kunci = (
        "Metode GET mengirim data melalui URL sehingga data terlihat, cocok untuk mengambil "
        "data. Metode POST mengirim data di dalam badan permintaan dan cocok untuk mengubah "
        "data di server."
    )
    baik = (
        "GET mengirim data lewat URL untuk mengambil data, POST mengirim data lewat "
        "badan permintaan untuk mengubah data server."
    )
    lemah = "Keduanya adalah metode di internet."
    kosong = ""

    skor = skor_esai(kunci, [baik, lemah, kosong])

    assert 0.0 <= min(skor) and max(skor) <= 1.0
    assert skor[0] > skor[1] > skor[2] == 0.0
    assert skor_esai(kunci, [kunci])[0] == pytest.approx(1.0)


def test_kata_kunci_dicek_setelah_praproses():
    terpenuhi, tidak = cek_kata_kunci(
        ["URL", "badan permintaan", "mengubah data", "autentikasi", "lewat"],
        "Data dikirim lewat url; POST memakai badan permintaan untuk mengubah data.",
    )

    # "lewat" adalah stopword: dicek sebagai teks biasa.
    assert terpenuhi == ["URL", "badan permintaan", "mengubah data", "lewat"]
    assert tidak == ["autentikasi"]
