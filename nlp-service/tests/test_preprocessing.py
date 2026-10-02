"""Task 4.2 / FR-05.2: case folding, hapus tanda baca/angka/stopword, stemming."""

import pytest

from app.preprocessing import praproses


def test_contoh_prd_menghubungkan_menjadi_hubung():
    assert praproses("menghubungkan") == ["hubung"]


def test_case_folding():
    assert praproses("SERVER Server server") == ["server", "server", "server"]


def test_tanda_baca_dan_angka_dihapus():
    assert praproses("Metode GET, POST (2 metode)!!! 404?") == ["metode", "get", "post", "metode"]


def test_stopword_dihapus_sebelum_stemming():
    # "yang", "dan", "oleh" adalah stopword Sastrawi; "dikirim" -> "kirim", "diterima" -> "terima".
    assert praproses("Data yang dikirim dan diterima oleh server") == [
        "data",
        "kirim",
        "terima",
        "server",
    ]


def test_tanpa_stemming_untuk_variasi_evaluasi():
    assert praproses("Data yang dikirim dan diterima", stemming=False) == [
        "data",
        "dikirim",
        "diterima",
    ]


@pytest.mark.parametrize("teks", ["", "   ", "123 456", "yang dan di ke", "!!! ???"])
def test_teks_tanpa_kata_bermakna_menjadi_kosong(teks):
    assert praproses(teks) == []


def test_spasi_tab_dan_baris_baru():
    assert praproses("  mengambil\t\tdata\n\nserver  ") == ["ambil", "data", "server"]


def test_negasi_hilang_sebagai_keterbatasan_bag_of_words():
    # "tidak" termasuk stopword Sastrawi: makna negasi hilang (dicatat sebagai keterbatasan).
    assert praproses("data tidak terkirim") == praproses("data terkirim")
