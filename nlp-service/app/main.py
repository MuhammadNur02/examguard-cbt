"""Layanan NLP internal ExamGuard CBT.

Hanya dipanggil oleh Laravel lewat jaringan internal. Jalankan dengan host
127.0.0.1 (bawaan) atau di jaringan privat; jangan diekspos ke publik.
"""

import os
import secrets

from fastapi import FastAPI, Header, HTTPException, status

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


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}
