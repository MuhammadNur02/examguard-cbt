"""Task 5.5 / PRD §13.4: perhitungan System Usability Scale."""

import pytest

from sus import baca_respons, main, ringkas, skor_sus


def test_contoh_hitung_manual():
    # Ganjil: (skor - 1), genap: (5 - skor), jumlah x 2,5.
    assert skor_sus([3] * 10) == 50.0  # (5 x 2 + 5 x 2) x 2,5
    assert skor_sus([5, 1] * 5) == 100.0
    assert skor_sus([1, 5] * 5) == 0.0
    assert skor_sus([4, 2] * 5) == 75.0  # (5 x 3 + 5 x 3) x 2,5


@pytest.mark.parametrize("jawaban", [[3] * 9, [3] * 11, [0] + [3] * 9, [6] + [3] * 9])
def test_jawaban_tidak_valid(jawaban):
    with pytest.raises(ValueError):
        skor_sus(jawaban)


def test_ringkasan_per_peran_dan_ambang_68():
    hasil = ringkas([("mahasiswa", 75.0), ("mahasiswa", 60.0), ("dosen", 90.0)])

    assert hasil["mahasiswa"]["n"] == 2
    assert hasil["mahasiswa"]["rata_rata"] == 67.5
    assert hasil["mahasiswa"]["persen_di_atas_68"] == 50.0
    assert hasil["semua"]["n"] == 3
    assert hasil["semua"]["rata_rata"] == 75.0
    assert hasil["semua"]["minimum"] == 60.0 and hasil["semua"]["maksimum"] == 90.0


def test_baca_csv_dan_cli(tmp_path, capsys):
    path = tmp_path / "sus.csv"
    path.write_text(
        "responden;peran;q1;q2;q3;q4;q5;q6;q7;q8;q9;q10\n"
        "R1;mahasiswa;4;2;4;2;4;2;4;2;4;2\n"
        "R2;dosen;5;1;5;1;5;1;5;1;5;1\n",
        encoding="utf-8-sig",
    )

    assert baca_respons(path) == [("R1", "mahasiswa", 75.0), ("R2", "dosen", 100.0)]
    assert main([str(path)]) == 0
    keluaran = capsys.readouterr().out
    assert "| semua | 2 | 87.50 |" in keluaran
    assert "R1: 75.0" in keluaran
