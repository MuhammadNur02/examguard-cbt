import pytest
from fastapi import HTTPException
from fastapi.testclient import TestClient

from app.main import app, require_internal_token

client = TestClient(app)


def test_health_tanpa_token():
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def test_dokumentasi_publik_dimatikan():
    assert client.get("/docs").status_code == 404
    assert client.get("/openapi.json").status_code == 404


def test_token_belum_dikonfigurasi_gagal_tertutup(monkeypatch):
    monkeypatch.delenv("NLP_SERVICE_TOKEN", raising=False)
    with pytest.raises(HTTPException) as err:
        require_internal_token("apa-saja")
    assert err.value.status_code == 503


@pytest.mark.parametrize("given", [None, "", "salah", "rahasia-uji-x", "ráhasia"])
def test_token_salah_ditolak(monkeypatch, given):
    monkeypatch.setenv("NLP_SERVICE_TOKEN", "rahasia-uji")
    with pytest.raises(HTTPException) as err:
        require_internal_token(given)
    assert err.value.status_code == 401


def test_token_benar_diterima(monkeypatch):
    monkeypatch.setenv("NLP_SERVICE_TOKEN", "rahasia-uji")
    assert require_internal_token("rahasia-uji") is None
