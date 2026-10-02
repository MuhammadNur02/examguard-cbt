"""TF-IDF dan Cosine Similarity untuk skor rekomendasi esai (PRD §9.3, FR-05.3).

Rumus mengikuti bawaan scikit-learn TfidfVectorizer:
    tf  = frekuensi mentah term dalam dokumen
    idf = ln((1 + n) / (1 + df)) + 1
    bobot = tf x idf, lalu tiap vektor dinormalisasi L2
    similarity = cosine(kunci, jawaban), rentang 0,0-1,0 (bobot tidak pernah negatif)

Korpus IDF (K-2): bawaan "kunci_dan_jawaban" = kunci dosen + seluruh jawaban
mahasiswa pada soal yang sama. Variasi "kunci" (korpus hanya kunci) disediakan
untuk evaluasi akurasi (Task 5.3).
"""

from typing import Literal

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from app.preprocessing import praproses

KorpusIdf = Literal["kunci_dan_jawaban", "kunci"]


def _token_apa_adanya(token: list[str]) -> list[str]:
    return token


def kemiripan_token(
    kunci: list[str],
    jawaban: list[list[str]],
    korpus_idf: KorpusIdf = "kunci_dan_jawaban",
) -> list[float]:
    """Cosine similarity TF-IDF antara token kunci dan token tiap jawaban."""
    if not jawaban:
        return []

    korpus = [kunci, *jawaban] if korpus_idf == "kunci_dan_jawaban" else [kunci]
    vektorizer = TfidfVectorizer(analyzer=_token_apa_adanya)
    try:
        vektorizer.fit(korpus)
    except ValueError:
        # Kosakata kosong (semua dokumen kosong setelah praproses).
        return [0.0] * len(jawaban)

    skor = cosine_similarity(vektorizer.transform([kunci]), vektorizer.transform(jawaban))[0]
    return [min(1.0, max(0.0, float(s))) for s in skor]


def skor_esai(
    kunci: str,
    jawaban: list[str],
    stemming: bool = True,
    korpus_idf: KorpusIdf = "kunci_dan_jawaban",
) -> list[float]:
    """Similarity tiap jawaban terhadap kunci, dari teks mentah."""
    return kemiripan_token(
        praproses(kunci, stemming),
        [praproses(teks, stemming) for teks in jawaban],
        korpus_idf,
    )


def cek_kata_kunci(
    kata_kunci: list[str], teks: str, stemming: bool = True
) -> tuple[list[str], list[str]]:
    """Checklist kata kunci (K-3): terpenuhi bila semua token kata kunci ada di jawaban.

    Kata kunci yang seluruhnya stopword dicek sebagai teks biasa (tanpa praproses).
    """
    token_jawaban = set(praproses(teks, stemming))
    teks_kecil = teks.lower()
    terpenuhi: list[str] = []
    tidak: list[str] = []
    for kata in kata_kunci:
        token = praproses(kata, stemming)
        cocok = all(t in token_jawaban for t in token) if token else kata.lower() in teks_kecil
        (terpenuhi if cocok else tidak).append(kata)
    return terpenuhi, tidak
