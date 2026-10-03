# Pemetaan Hasil Proyek ke Bab 1–4 KTI

Mengikuti PRD §16. Kolom *Sumber* menunjuk berkas di repositori.

## Bab 1 — Pendahuluan

| Bagian | Sumber |
|---|---|
| Latar belakang masalah | PRD §2, README "Latar Belakang Masalah" |
| Rumusan/tujuan | PRD §3 (tiga tujuan, metrik keberhasilan) |
| Ruang lingkup dan batasan | PRD §4, termasuk batasan jujur: web tidak dapat memblokir Alt+Tab, hanya mendeteksi dan mencatat |

## Bab 2 — Landasan Teori

| Konsep | Rumus/implementasi | Sumber kode |
|---|---|---|
| Fisher-Yates (Durstenfeld) | i = n−1..1: j acak seragam di [0, i], tukar elemen i dan j; PRNG Mt19937 berseed `shuffle_seed` | `app/Support/FisherYates.php` |
| Praproses teks Indonesia | case folding → hapus tanda baca/angka → token → stopword Sastrawi → stemming Sastrawi | `nlp-service/app/preprocessing.py` |
| TF-IDF | tf mentah; idf = ln((1+n)/(1+df)) + 1; normalisasi L2 (bawaan scikit-learn) | `nlp-service/app/scoring.py` |
| Cosine Similarity | (A·B) / (‖A‖‖B‖), rentang 0–1 | `nlp-service/app/scoring.py` |
| Page Visibility API, `blur`, Fullscreen API | `visibilitychange`, `blur`, `fullscreenchange`, `requestFullscreen` | `resources/js/exam.js` |
| System Usability Scale | (Σ(ganjil−1) + Σ(5−genap)) × 2,5 | `nlp-service/sus.py` |

Contoh hitung manual TF-IDF/Cosine siap kutip: docstring
`nlp-service/tests/test_scoring.py` (hasil 0,8165 dan 0,3495).

## Bab 3 — Metode / Perancangan

| Bagian | Sumber |
|---|---|
| Arsitektur (Laravel ↔ FastAPI internal, DB, antrean) | PRD §10, `README.md`, `nlp-service/README.md` |
| Model data (ERD) | `docs/ERD.md` (diagram Mermaid + penyesuaian terhadap PRD) |
| Alur pengguna | PRD §8; panduan `docs/PANDUAN-DOSEN.md`, `docs/PANDUAN-MAHASISWA.md` |
| Algoritma pengacakan dan pemetaan posisi | `app/Services/AttemptService.php` (`susunUrutan`, `soalUntukKlien`, `petakanPosisi`) |
| Mesin anti-kecurangan (server = sumber kebenaran, debounce, K-1) | `app/Services/ViolationService.php`, `resources/js/exam.js`, DECISIONS D-27..D-33 |
| Penilaian (PG, esai, nilai akhir) | `app/Services/ScoringService.php`, `app/Jobs/ScoreEssayQuestion.php`, DECISIONS D-17, D-35 |
| Keputusan desain dan asumsi | `DECISIONS.md` (termasuk K-1..K-8) |

## Bab 4 — Hasil dan Pembahasan

| Pengujian | Sumber | Status |
|---|---|---|
| Black-box (PRD §13.1) | `docs/PENGUJIAN-BLACKBOX.md` (25 skenario) | Dijalankan otomatis; ulangi manual untuk lampiran |
| Keamanan teknis | `docs/KEAMANAN.md`, `tests/Feature/Security/RouteSecurityTest.php` | Selesai |
| Latensi pencatatan pelanggaran | `PROGRESS.md` (194–257 ms di mesin pengembangan dengan OPcache) | Ukur ulang di server target |
| Akurasi esai (MAE, Pearson; variasi stemming dan korpus IDF) | `docs/EVALUASI-AKURASI-ESAI.md`, `nlp-service/evaluasi.py` | Menunggu data nyata |
| Uji beban 50–100 peserta | `docs/UJI-BEBAN.md`, `tools/uji-beban/uji-beban.mjs` | Dijalankan di laptop pengembangan (100 peserta, 0 galat dengan 4 proses PHP); ulangi di server target |
| SUS/UAT | `docs/KUESIONER-SUS-UAT.md`, `nlp-service/sus.py` | Menunggu responden |

Bahan pembahasan keterbatasan: `nlp-service/README.md` (negasi hilang,
kesalahan stemmer, parafrase), PRD §4 dan §15, `docs/KEAMANAN.md`.
Jumlah tes otomatis saat ini tercatat di `PROGRESS.md`.
