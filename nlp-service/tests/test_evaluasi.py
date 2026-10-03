"""Task 5.3 / PRD §13.2: alat evaluasi MAE dan Pearson."""

import math

import pytest

from evaluasi import Baris, baca_dataset, evaluasi, mae, main, pearson, skor_sistem


def test_mae_contoh_manual():
    # |1-2| + |2-2| + |3-5| = 3; 3 / 3 = 1
    assert mae([1, 2, 3], [2, 2, 5]) == 1.0


def test_pearson_contoh_manual():
    assert pearson([1, 2, 3], [2, 4, 6]) == pytest.approx(1.0)
    assert pearson([1, 2, 3], [6, 4, 2]) == pytest.approx(-1.0)
    # x = 1,2,3; y = 1,3,2 -> cov = 1, var x = 2, var y = 2 -> r = 0,5
    assert pearson([1, 2, 3], [1, 3, 2]) == pytest.approx(0.5)
    assert pearson([1, 1, 1], [1, 2, 3]) is None
    assert pearson([1], [1]) is None


def _tulis(tmp_path, isi, nama="data.csv"):
    path = tmp_path / nama
    path.write_bytes(isi.encode("utf-8-sig"))
    return path


def test_baca_dataset_koma_titik_koma_dan_apostrof(tmp_path):
    koma = _tulis(
        tmp_path,
        "soal_id,bobot,kunci,jawaban_id,kode_mahasiswa,jawaban,skor_dosen\n"
        '1,10,"Kunci, dengan koma",5,M001,"\'=jawaban aneh",7.5\n'
        "1,10,Kunci,6,M002,Belum dinilai,\n",
    )
    baris, dilewati = baca_dataset(koma)
    assert dilewati == 1
    assert baris == [Baris("1", 10.0, "Kunci, dengan koma", "=jawaban aneh", 7.5)]

    titik_koma = _tulis(
        tmp_path,
        "soal_id;bobot;kunci;jawaban;skor_dosen\r\n2;8;Kunci;Jawaban;6,5\r\n",
        "excel.csv",
    )
    assert baca_dataset(titik_koma)[0] == [Baris("2", 8.0, "Kunci", "Jawaban", 6.5)]


def test_kolom_wajib(tmp_path):
    path = _tulis(tmp_path, "soal_id,jawaban\n1,x\n")
    with pytest.raises(ValueError, match="bobot"):
        baca_dataset(path)


KUNCI = "Middleware menyaring permintaan HTTP sebelum diteruskan ke controller."
DATA = [
    Baris("1", 10.0, KUNCI, "Middleware menyaring permintaan HTTP ke controller.", 9.0),
    Baris("1", 10.0, KUNCI, "Middleware memeriksa permintaan.", 6.0),
    Baris("1", 10.0, KUNCI, "Saya tidak tahu.", 0.0),
    Baris("2", 5.0, "Data dikirim lewat URL.", "Data dikirim melalui URL.", 5.0),
    Baris("2", 5.0, "Data dikirim lewat URL.", "Server menyimpan berkas.", 1.0),
]


def test_evaluasi_empat_varian_dengan_rentang_valid():
    hasil = evaluasi(DATA)

    assert [(h["stemming"], h["korpus_idf"]) for h in hasil] == [
        (True, "kunci_dan_jawaban"),
        (False, "kunci_dan_jawaban"),
        (True, "kunci"),
        (False, "kunci"),
    ]
    for h in hasil:
        assert h["n"] == 5
        assert h["mae"] >= 0 and 0 <= h["mae_normal"] <= 1
        assert -1 <= h["pearson"] <= 1


def test_skor_dosen_sama_dengan_sistem_memberi_mae_nol_dan_r_satu():
    sistem = skor_sistem(DATA, stemming=True, korpus_idf="kunci_dan_jawaban")
    sempurna = [
        Baris(b.soal_id, b.bobot, b.kunci, b.jawaban, s) for b, s in zip(DATA, sistem, strict=True)
    ]

    utama = evaluasi(sempurna)[0]

    assert utama["mae"] == 0.0
    assert utama["pearson"] == pytest.approx(1.0)


def test_cli_menulis_ringkasan_dan_berkas_keluaran(tmp_path, capsys):
    data = _tulis(
        tmp_path,
        "soal_id,bobot,kunci,jawaban,skor_dosen\n"
        + "".join(
            f'{b.soal_id},{b.bobot},"{b.kunci}","{b.jawaban}",{b.skor_dosen}\n' for b in DATA
        ),
    )
    keluar = tmp_path / "hasil.csv"

    assert main([str(data), "--keluar", str(keluar)]) == 0

    teks = capsys.readouterr().out
    assert "| Stemming | Korpus IDF | n | MAE | MAE (0-1) | Pearson r |" in teks
    baris = keluar.read_text(encoding="utf-8-sig").strip().splitlines()
    assert len(baris) == 6
    assert baris[0].startswith("soal_id,bobot,skor_dosen,stem_kunci_dan_jawaban")
    assert not math.isnan(float(baris[1].split(",")[3]))
