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
python -m venv .venv    # Windows dengan beberapa versi Python: py -3.13 -m venv .venv
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

## Metode

1. **Praproses** (`app/preprocessing.py`): case folding → hapus tanda baca dan
   angka → tokenisasi → hapus stopword (daftar Sastrawi) → stemming Sastrawi.
   Contoh: `menghubungkan` → `hubung`.
2. **TF-IDF** (`app/scoring.py`, rumus bawaan scikit-learn):
   `tf` = frekuensi mentah; `idf = ln((1 + n) / (1 + df)) + 1`; bobot = tf × idf,
   lalu tiap vektor dinormalisasi L2. Korpus IDF per soal = kunci + seluruh
   jawaban mahasiswa pada soal itu (PRD K-2).
3. **Cosine Similarity** antara vektor kunci dan jawaban, rentang 0,0–1,0.
   Laravel menghitung skor rekomendasi = similarity × bobot soal.

Contoh hitung manual ada di docstring `tests/test_scoring.py`.

### Keterbatasan yang diketahui (untuk pembahasan)
- Negasi hilang: "tidak" termasuk stopword Sastrawi, sehingga "data tidak
  terkirim" dan "data terkirim" menghasilkan token yang sama.
- Stemmer dapat keliru, misalnya `penyaring` → `nyaring` (bukan `saring`).
- Pendekatan *bag-of-words*: urutan kata dan sinonim/parafrase tidak dikenali,
  sehingga skor hanya rekomendasi dan dosen tetap memutuskan nilai akhir.

## API internal

Semua endpoint selain `/health` mensyaratkan header `X-Internal-Token`.

`POST /score`
```json
{
  "kunci": "teks kunci dosen",
  "jawaban": [{"id": 1, "teks": "jawaban mahasiswa"}],
  "kata_kunci": ["opsional"],
  "stemming": true,
  "korpus_idf": "kunci_dan_jawaban"
}
```
Respons: `hasil[]` berisi `id`, `similarity` (4 desimal),
`kata_kunci_terpenuhi`, `kata_kunci_tidak_terpenuhi`; serta `metode`.
`korpus_idf: "kunci"` dan `stemming: false` disediakan untuk variasi evaluasi.

`POST /preprocess` dengan `{"teks": "...", "stemming": true}` mengembalikan
`{"token": [...]}` untuk transparansi.

## Tes dan lint

```bash
pytest
ruff check .
ruff format --check .
```

## Evaluasi akurasi (Task 5.3)

`evaluasi.py` menghitung MAE dan korelasi Pearson skor sistem terhadap skor
dosen untuk empat varian (stemming ya/tidak × korpus IDF kunci+jawaban/kunci).
Protokol lengkap: [docs/EVALUASI-AKURASI-ESAI.md](../docs/EVALUASI-AKURASI-ESAI.md).

```bash
python evaluasi.py contoh/dataset-rekaan.csv   # data rekaan, hanya untuk mencoba alat
```
