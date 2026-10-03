"""Task 4.9 / FR-05.5: kemiripan esai antarmahasiswa pada satu soal.

Cosine TF-IDF antarjawaban (IDF dari seluruh jawaban soal). Pasangan dengan
skor >= ambang ditandai untuk ditinjau dosen; bukan bukti kecurangan.
"""

import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.scoring import pasangan_mirip

client = TestClient(app)
TOKEN = {"X-Internal-Token": "rahasia-uji"}


@pytest.fixture(autouse=True)
def token(monkeypatch):
    monkeypatch.setenv("NLP_SERVICE_TOKEN", "rahasia-uji")


A = ["middleware", "saring", "minta", "http", "masuk", "controller"]
B_SALINAN = ["middleware", "saring", "minta", "http", "masuk", "controller"]
C_BERBEDA = ["route", "petakan", "url", "fungsi", "controller", "aplikasi"]
D_PARAFRASE = ["middleware", "saring", "minta", "http", "teruskan", "controller"]


def test_salinan_ditandai_dan_jawaban_berbeda_tidak():
    hasil = pasangan_mirip([A, B_SALINAN, C_BERBEDA], ambang=0.8)

    assert [(a, b) for a, b, _ in hasil] == [(0, 1)]
    assert hasil[0][2] == 1.0


def test_hasil_urut_skor_menurun_dan_indeks_a_lebih_kecil():
    hasil = pasangan_mirip([A, C_BERBEDA, D_PARAFRASE, B_SALINAN], ambang=0.5)

    skor = [s for _, _, s in hasil]
    assert skor == sorted(skor, reverse=True)
    assert all(a < b for a, b, _ in hasil)
    assert (0, 3, 1.0) in hasil
    assert all(0.0 <= s <= 1.0 and s == round(s, 4) for s in skor)


def test_jawaban_pendek_dan_kosong_diabaikan():
    pendek = ["tcp", "udp"]

    assert pasangan_mirip([pendek, list(pendek), [], []], ambang=0.5) == []
    assert pasangan_mirip([pendek, list(pendek)], ambang=0.5, min_token=2) == [(0, 1, 1.0)]


def test_kurang_dari_dua_jawaban():
    assert pasangan_mirip([], ambang=0.8) == []
    assert pasangan_mirip([A], ambang=0.8) == []


def test_endpoint_kemiripan_memakai_id_jawaban():
    respons = client.post(
        "/kemiripan",
        json={
            "jawaban": [
                {
                    "id": 31,
                    "teks": "Middleware menyaring permintaan HTTP yang masuk sebelum controller.",
                },
                {"id": 32, "teks": "Route memetakan URL ke fungsi controller dalam aplikasi web."},
                {
                    "id": 33,
                    "teks": "Middleware menyaring permintaan HTTP yang masuk sebelum controller!",
                },
                {"id": 34, "teks": ""},
            ],
            "ambang": 0.8,
        },
        headers=TOKEN,
    )

    assert respons.status_code == 200
    assert respons.json()["pasangan"] == [{"a": 31, "b": 33, "skor": 1.0}]


@pytest.mark.parametrize("ubah", [{"ambang": 0.2}, {"ambang": 1.5}, {"min_token": 0}])
def test_endpoint_memvalidasi_masukan(ubah):
    data = {"jawaban": [{"id": 1, "teks": "a"}], **ubah}
    assert client.post("/kemiripan", json=data, headers=TOKEN).status_code == 422


def test_endpoint_wajib_token():
    assert client.post("/kemiripan", json={"jawaban": []}).status_code == 401
