"""Endpoint internal /score dan /preprocess (dipanggil Laravel, Task 4.4)."""

import pytest
from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)
TOKEN = {"X-Internal-Token": "rahasia-uji"}


@pytest.fixture(autouse=True)
def token(monkeypatch):
    monkeypatch.setenv("NLP_SERVICE_TOKEN", "rahasia-uji")


def _permintaan(**ubah):
    data = {
        "kunci": "Middleware menyaring permintaan HTTP sebelum diteruskan ke controller.",
        "jawaban": [
            {"id": 11, "teks": "Middleware menyaring permintaan HTTP sebelum ke controller."},
            {"id": 12, "teks": "Saya tidak tahu."},
            {"id": 13, "teks": ""},
        ],
        "kata_kunci": ["permintaan", "controller"],
    }
    data.update(ubah)
    return data


def test_score_mengembalikan_similarity_per_jawaban_sesuai_urutan():
    respons = client.post("/score", json=_permintaan(), headers=TOKEN)

    assert respons.status_code == 200
    hasil = respons.json()["hasil"]
    assert [h["id"] for h in hasil] == [11, 12, 13]
    assert hasil[0]["similarity"] > hasil[1]["similarity"]
    assert hasil[2]["similarity"] == 0.0
    assert all(0.0 <= h["similarity"] <= 1.0 for h in hasil)
    assert all(h["similarity"] == round(h["similarity"], 4) for h in hasil)
    assert hasil[0]["kata_kunci_terpenuhi"] == ["permintaan", "controller"]
    assert hasil[1]["kata_kunci_tidak_terpenuhi"] == ["permintaan", "controller"]
    assert respons.json()["metode"] == {"stemming": True, "korpus_idf": "kunci_dan_jawaban"}


def test_score_mendukung_variasi_evaluasi():
    respons = client.post(
        "/score", json=_permintaan(stemming=False, korpus_idf="kunci"), headers=TOKEN
    )

    assert respons.status_code == 200
    assert respons.json()["metode"] == {"stemming": False, "korpus_idf": "kunci"}


@pytest.mark.parametrize(
    "ubah",
    [
        {"kunci": ""},
        {"korpus_idf": "semua"},
        {"jawaban": [{"id": "bukan-angka", "teks": "x"}]},
        {"jawaban": [{"id": 1, "teks": "x" * 20001}]},
        {"kata_kunci": [f"k{i}" for i in range(21)]},
    ],
)
def test_score_memvalidasi_masukan(ubah):
    assert client.post("/score", json=_permintaan(**ubah), headers=TOKEN).status_code == 422


def test_score_tanpa_token_ditolak():
    salah = {"X-Internal-Token": "salah"}
    assert client.post("/score", json=_permintaan()).status_code == 401
    assert client.post("/score", json=_permintaan(), headers=salah).status_code == 401


def test_preprocess_untuk_transparansi():
    respons = client.post("/preprocess", json={"teks": "Menghubungkan data"}, headers=TOKEN)

    assert respons.status_code == 200
    assert respons.json() == {"token": ["hubung", "data"]}
    assert client.post("/preprocess", json={"teks": "x"}).status_code == 401
