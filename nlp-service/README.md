# Layanan NLP ExamGuard CBT

Microservice FastAPI untuk penilaian esai (preprocessing Sastrawi, TF-IDF,
Cosine Similarity). Layanan ini **internal**: hanya dipanggil Laravel lewat
HTTP di jaringan internal dan tidak boleh diekspos ke publik.

## Prasyarat

- Python 3.11 atau lebih baru, **64-bit** (scikit-learn tidak menyediakan
  wheel untuk Python 32-bit di Windows).

## Menyiapkan

```bash
cd nlp-service
python -m venv .venv
# Windows: .venv\Scripts\activate    Linux/macOS: source .venv/bin/activate
pip install -r requirements-dev.txt
cp .env.example .env    # lalu isi NLP_SERVICE_TOKEN (sama dengan .env Laravel)
```

## Menjalankan

```bash
uvicorn app.main:app --host 127.0.0.1 --port 8001 --env-file .env
```

Host `127.0.0.1` membuat layanan hanya bisa diakses dari mesin yang sama.
Bila Laravel dan layanan ini berada di mesin berbeda, gunakan alamat jaringan
privat dan batasi dengan firewall.

## Keamanan

- Setiap endpoint penilaian mensyaratkan header `X-Internal-Token` yang sama
  dengan `NLP_SERVICE_TOKEN`. Bila token belum diisi, endpoint menolak semua
  permintaan (503), bukan membiarkannya terbuka.
- `/health` tidak memerlukan token dan tidak memuat data apa pun.
- Halaman dokumentasi otomatis FastAPI (`/docs`, `/openapi.json`) dimatikan.

## Tes dan lint

```bash
pytest
ruff check .
ruff format --check .
```
