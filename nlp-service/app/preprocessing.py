"""Preprocessing teks esai berbahasa Indonesia (PRD §9.3 langkah 1, FR-05.2).

Urutan: case folding -> hapus tanda baca dan angka -> tokenisasi ->
hapus stopword (daftar Sastrawi) -> stemming Sastrawi.
"""

import re
from functools import lru_cache

from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory

_BUKAN_HURUF = re.compile(r"[^a-z\s]+")


@lru_cache(maxsize=1)
def _stemmer():
    # create_stemmer() sudah memakai cache internal untuk kata yang berulang.
    return StemmerFactory().create_stemmer()


@lru_cache(maxsize=1)
def stopwords() -> frozenset[str]:
    return frozenset(StopWordRemoverFactory().get_stop_words())


def praproses(teks: str, stemming: bool = True) -> list[str]:
    """Ubah teks menjadi daftar token bersih.

    >>> praproses("menghubungkan")
    ['hubung']
    """
    teks = teks.lower()  # 1. case folding
    teks = _BUKAN_HURUF.sub(" ", teks)  # 2. hapus tanda baca dan angka
    token = teks.split()  # 3. tokenisasi
    token = [t for t in token if t not in stopwords()]  # 4. hapus stopword
    if stemming:
        stem = _stemmer().stem
        token = [stem(t) for t in token]  # 5. stemming Sastrawi
    return [t for t in token if t]
