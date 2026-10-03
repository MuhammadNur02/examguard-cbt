# ExamGuard CBT 🛡️

Sistem ujian daring (*Computer Based Test*) berbasis web yang dirancang untuk
mempersempit celah kecurangan di peramban dan mempercepat koreksi esai.

Proyek ini dikembangkan sebagai luaran penelitian Karya Tulis Ilmiah (KTI) /
Tugas Akhir di Program Studi S1 Pendidikan Informatika, Universitas Ivet Semarang.

Dokumen perencanaan: [PRD.md](PRD.md) · [Task.md](Task.md) · [StyleGuide.md](StyleGuide.md) ·
[docs/ERD.md](docs/ERD.md). Status pengembangan: [PROGRESS.md](PROGRESS.md).

Panduan: [mahasiswa](docs/PANDUAN-MAHASISWA.md) · [dosen dan admin](docs/PANDUAN-DOSEN.md) ·
[keamanan dan produksi](docs/KEAMANAN.md) · [backup](docs/BACKUP.md) ·
[pengujian black-box](docs/PENGUJIAN-BLACKBOX.md) · [evaluasi akurasi esai](docs/EVALUASI-AKURASI-ESAI.md) ·
[SUS/UAT](docs/KUESIONER-SUS-UAT.md) · [pemetaan ke Bab 1–4 KTI](docs/PEMETAAN-KTI.md).

## 📌 Latar Belakang Masalah

Ujian daring dengan platform formulir biasa menghadapi dua masalah nyata:

1. **Integritas ujian.** Mahasiswa mudah berpindah tab untuk menyalin soal ke
   generator AI, mencari jawaban di mesin pencari, atau bertukar jawaban tanpa
   terpantau pengawas.
2. **Beban koreksi esai.** Memeriksa puluhan lembar esai secara manual memakan
   waktu lama sehingga dosen cenderung menghindari soal esai dan umpan balik nilai
   terlambat.

ExamGuard CBT menangani keduanya dari sisi peramban: **mendeteksi dan mencatat**
perilaku mencurigakan selama ujian, lalu memberi **rekomendasi** nilai esai
berbasis kemiripan teks yang tetap divalidasi dosen.

## ✨ Fitur Utama

> Seluruh fitur di bawah sudah diimplementasikan dan diuji (tugas Must, Should,
> dan Could di Task.md). Status setiap task, termasuk uji akurasi esai dan SUS
> yang masih menunggu data nyata, tercatat di [Task.md](Task.md) dan
> [PROGRESS.md](PROGRESS.md).

### Sisi mahasiswa: pemantauan dan proteksi layar ujian
- **Deteksi pindah tab/jendela** lewat Page Visibility API dan event `blur`.
  Setiap kejadian dicatat di server dan memunculkan modal peringatan.
- **Layar penuh wajib.** Ujian berjalan dalam mode layar penuh; keluar dari layar
  penuh (misalnya menekan Esc) dicatat sebagai pelanggaran dan layar penuh
  diminta ulang.
- **Peringatan bertingkat.** Modal "Peringatan Pelanggaran (X/N)"; bila batas N
  terlampaui, lembar ujian dibekukan dan jawaban tersimpan dikirim otomatis.
- **Proteksi salin-tempel.** Klik kanan, seleksi teks, dan pintasan Ctrl+C/V/U,
  Ctrl+Shift+I, F12 dinonaktifkan di halaman ujian.
- **Pengacakan soal dan opsi per mahasiswa** (Fisher-Yates dengan seed tersimpan),
  sehingga mahasiswa yang bersebelahan tidak mendapat urutan yang sama. Acak soal
  dan acak opsi dapat dimatikan per ujian; opsi seperti "Semua benar" dapat dikunci
  posisinya.
- **Watermark nama/NIM** samar di layar ujian, **deteksi perangkat berganti**
  (IP/peramban, dicatat untuk ditinjau), dan penolakan ponsel/tablet.
- **Kode akses** opsional, **kelas peserta**, **pembatasan jaringan kampus**
  (IP/CIDR), dan **pool soal** (N dari M soal per mahasiswa) per ujian.

### Sisi dosen: penilaian dan validasi
- **Nilai pilihan ganda otomatis** dihitung di server.
- **Asisten penilaian esai:** preprocessing (case folding, hapus tanda baca/angka,
  stopword, stemming Sastrawi), pembobotan TF-IDF, dan Cosine Similarity terhadap
  kunci dosen menghasilkan skor rekomendasi (similarity × bobot soal).
- **Koreksi berdampingan (*human-in-the-loop*):** dosen membandingkan jawaban dan
  kunci, lalu menyetujui atau mengubah skor sebelum nilai dipublikasikan.
- **Koreksi cepat:** terima massal rekomendasi di atas ambang similarity untuk
  jawaban yang belum dikonfirmasi, dengan checklist kata kunci wajib.
- **Kemiripan esai antarmahasiswa** ditandai untuk ditinjau (bukan bukti).
- **Live Monitor:** daftar peserta, status, waktu aktivitas terakhir, dan log
  pelanggaran yang diperbarui berkala; dari sana dosen dapat **memaafkan
  pelanggaran, menambah waktu, atau membuka ulang** ujian seorang mahasiswa
  (wajib beralasan, tercatat di log audit).
- **Bank soal:** impor soal dari Excel/CSV dengan laporan baris salah, duplikat
  ujian, dan pratinjau sebagai mahasiswa.
- **Rekap dan laporan:** ekspor Excel, laporan siap cetak/PDF berisi pelanggaran
  per mahasiswa, analisis butir soal, publikasi nilai langsung atau terjadwal,
  serta mengunci ujian seorang mahasiswa dari halaman Kelola.

## ⚠️ Batasan yang Perlu Diketahui

- Aplikasi web **tidak dapat memblokir** Alt+Tab, tombol Windows, atau pintasan
  sistem operasi lainnya. Sistem hanya **mendeteksi dan mencatat** kejadiannya.
- Proteksi sisi klien dapat dilewati pengguna mahir. Karena itu hitungan
  pelanggaran, timer, dan auto-submit divalidasi di server, dan log pelanggaran
  diperlakukan sebagai **penanda untuk ditinjau dosen**, bukan bukti mutlak.
- Skor esai adalah **rekomendasi**; nilai akhir tetap keputusan dosen.
- Mengerjakan ujian hanya didukung di Chrome/Edge desktop; perangkat mobile
  diarahkan untuk memakai laptop/PC.

## 🛠️ Teknologi

| Lapisan | Teknologi |
|---|---|
| Web | Laravel 13 (PHP 8.4+), Blade, Tailwind CSS v4, JavaScript vanilla |
| Basis data | SQLite (pengembangan/tes), MySQL atau PostgreSQL (produksi) |
| Layanan NLP | Python 3.11+ 64-bit, FastAPI, Sastrawi, scikit-learn |
| Algoritma | Fisher-Yates Shuffle, TF-IDF, Cosine Similarity (Vector Space Model) |
| API peramban | Fullscreen API, Page Visibility API |

## 🚀 Menjalankan di Lokal

### Prasyarat
- PHP 8.4 atau lebih baru (dependensi terkunci memakai Symfony 8.1) dengan ekstensi `mbstring`, `openssl`, `pdo_sqlite`,
  `fileinfo`, `curl`, `zip` (dan `pdo_mysql`/`pdo_pgsql` bila memakai MySQL/PostgreSQL)
- Composer 2
- Node.js 20+ dan npm
- Python 3.11+ **64-bit** (untuk layanan NLP)

### 1. Aplikasi web (Laravel)

```bash
composer setup          # composer install, salin .env, key:generate, migrate, npm install, npm run build
php artisan db:seed     # data contoh (opsional, khusus pengembangan)
php artisan dev         # server http://localhost:8000 + queue worker + Vite
```

Bila `composer setup` tidak tersedia, jalankan manual:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate      # SQLite: berkas database/database.sqlite dibuat otomatis
npm install
npm run build
```

### 2. Layanan NLP (penilaian esai)

```bash
cd nlp-service
python -m venv .venv    # Windows dengan beberapa versi Python: py -3.13 -m venv .venv
# Windows: .venv\Scripts\activate    Linux/macOS: source .venv/bin/activate
pip install -r requirements-dev.txt
cp .env.example .env    # isi NLP_SERVICE_TOKEN
uvicorn app.main:app --host 127.0.0.1 --port 8001 --env-file .env
```

Isi `NLP_SERVICE_TOKEN` dengan nilai acak yang **sama** di `.env` Laravel dan
`nlp-service/.env`. Contoh membuat token: `php -r "echo bin2hex(random_bytes(32));"`.
Detail di [nlp-service/README.md](nlp-service/README.md).

### 3. Proses latar yang dibutuhkan

| Proses | Perintah | Fungsi |
|---|---|---|
| Queue worker | `php artisan queue:work` (sudah termasuk di `php artisan dev`) | Menghitung skor rekomendasi esai |
| Penjadwal | `php artisan schedule:work` (produksi: cron `schedule:run` tiap menit) | Mengirim otomatis attempt yang waktunya habis saat peramban peserta tertutup |
| Layanan NLP | `uvicorn ...` (langkah 2) | TF-IDF + Cosine Similarity |

Di Windows, aktifkan OPcache (`zend_extension=opcache`, `opcache.enable_cli=1`
di `php.ini`) agar server pengembangan cukup cepat; tanpa OPcache latensi
pencatatan pelanggaran di mesin uji bisa mendekati 1 detik.

### Produksi

Lihat daftar periksa di [docs/KEAMANAN.md](docs/KEAMANAN.md). Buat admin pertama
tanpa data contoh: `php artisan examguard:buat-admin <username> "<Nama>"`.

### Akun contoh (setelah `php artisan db:seed`)

Kata sandi semua akun: nilai `SEED_PASSWORD` di `.env` (bawaan `password`).
**Hanya untuk pengembangan**; seeder menolak berjalan bila `APP_ENV=production`.

| Peran | Login (NIM/NIDN/username) |
|---|---|
| Admin | `admin` |
| Dosen | `0601018801`, `0612038502` |
| Mahasiswa | `2301001` s.d. `2301006` |

### Beralih ke MySQL atau PostgreSQL

Ubah `DB_CONNECTION` di `.env` menjadi `mysql` atau `pgsql`, isi `DB_HOST`,
`DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, lalu `php artisan migrate`.

## ✅ Tes dan Lint

```bash
php artisan test               # PHPUnit
vendor/bin/pint --test         # lint PHP

cd nlp-service
pytest                         # tes layanan NLP
ruff check . && ruff format --check .
```

## 📁 Struktur Singkat

| Folder | Isi |
|---|---|
| `app/` | Model, controller, layanan (pengacakan, penilaian) |
| `database/` | Migration, factory, seeder |
| `resources/` | Tampilan Blade, CSS (token StyleGuide), JavaScript |
| `tests/` | Tes PHPUnit (unit dan fitur) |
| `nlp-service/` | Microservice FastAPI untuk penilaian esai |
| `docs/` | ERD dan dokumentasi pendukung |
