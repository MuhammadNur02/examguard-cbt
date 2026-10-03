"""Layanan NLP internal ExamGuard CBT.

Hanya dipanggil oleh Laravel lewat jaringan internal. Jalankan dengan host
127.0.0.1 (bawaan) atau di jaringan privat; jangan diekspos ke publik.
"""

import os
import secrets

from fastapi import Depends, FastAPI, Header, HTTPException, status
from pydantic import BaseModel, Field

from app.preprocessing import praproses
from app.scoring import KorpusIdf, cek_kata_kunci, pasangan_mirip, skor_esai

app = FastAPI(
    title="ExamGuard NLP",
    version="0.1.0",
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
)


def require_internal_token(x_internal_token: str | None = Header(default=None)) -> None:
    """Tolak permintaan tanpa token internal yang cocok (gagal tertutup)."""
    expected = os.environ.get("NLP_SERVICE_TOKEN", "").strip()
    if not expected:
        raise HTTPException(
            status.HTTP_503_SERVICE_UNAVAILABLE, "NLP_SERVICE_TOKEN belum dikonfigurasi."
        )
    given = (x_internal_token or "").encode()
    if not secrets.compare_digest(given, expected.encode()):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Token internal tidak valid.")


class Jawaban(BaseModel):
    id: int
    teks: str = Field(default="", max_length=20000)


class PermintaanSkor(BaseModel):
    kunci: str = Field(min_length=1, max_length=5000)
    jawaban: list[Jawaban] = Field(max_length=2000)
    kata_kunci: list[str] = Field(default_factory=list, max_length=20)
    stemming: bool = True
    korpus_idf: KorpusIdf = "kunci_dan_jawaban"


class PermintaanKemiripan(BaseModel):
    jawaban: list[Jawaban] = Field(max_length=2000)
    ambang: float = Field(default=0.8, ge=0.5, le=1.0)
    min_token: int = Field(default=5, ge=1, le=100)
    stemming: bool = True


class PermintaanPraproses(BaseModel):
    teks: str = Field(max_length=20000)
    stemming: bool = True


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/score", dependencies=[Depends(require_internal_token)])
def score(permintaan: PermintaanSkor) -> dict:
    """Similarity TF-IDF + Cosine tiap jawaban terhadap kunci, plus checklist kata kunci."""
    similarity = skor_esai(
        permintaan.kunci,
        [j.teks for j in permintaan.jawaban],
        stemming=permintaan.stemming,
        korpus_idf=permintaan.korpus_idf,
    )

    hasil = []
    for jawaban, nilai in zip(permintaan.jawaban, similarity, strict=True):
        terpenuhi, tidak = cek_kata_kunci(permintaan.kata_kunci, jawaban.teks, permintaan.stemming)
        hasil.append(
            {
                "id": jawaban.id,
                "similarity": round(nilai, 4),
                "kata_kunci_terpenuhi": terpenuhi,
                "kata_kunci_tidak_terpenuhi": tidak,
            }
        )

    return {
        "hasil": hasil,
        "metode": {"stemming": permintaan.stemming, "korpus_idf": permintaan.korpus_idf},
    }


@app.post("/kemiripan", dependencies=[Depends(require_internal_token)])
def kemiripan(permintaan: PermintaanKemiripan) -> dict:
    """Pasangan jawaban antarmahasiswa yang mirip (FR-05.5), untuk ditinjau dosen."""
    token = [praproses(j.teks, permintaan.stemming) for j in permintaan.jawaban]
    pasangan = pasangan_mirip(token, permintaan.ambang, permintaan.min_token)
    return {
        "pasangan": [
            {"a": permintaan.jawaban[a].id, "b": permintaan.jawaban[b].id, "skor": skor}
            for a, b, skor in pasangan
        ]
    }


@app.post("/preprocess", dependencies=[Depends(require_internal_token)])
def preprocess(permintaan: PermintaanPraproses) -> dict:
    """Token hasil praproses, untuk transparansi dan dokumentasi."""
    return {"token": praproses(permintaan.teks, permintaan.stemming)}
