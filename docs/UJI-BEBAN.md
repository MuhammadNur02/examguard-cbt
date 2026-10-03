# Uji Beban — ExamGuard CBT (Task 5.4, PRD §13.3)

Simulasi 50–100 peserta bersamaan (autosave + heartbeat) serta pencatatan
latensi dan galat. Data mentah tiap putaran ada di
[`docs/hasil-uji-beban/`](hasil-uji-beban/).

> **Penting untuk pembacaan hasil.** Uji dilakukan di **laptop pengembangan**,
> bukan server produksi: klien dan server berjalan di mesin yang sama, server
> web adalah server bawaan PHP (`php -S`, satu permintaan per proses), dan basis
> data SQLite di folder OneDrive. Angka di bawah adalah **batas bawah** kinerja;
> ulangi uji pada lingkungan yang menyerupai produksi (Nginx + PHP-FPM +
> MySQL/PostgreSQL) sebelum ujian sungguhan.

## 1. Lingkungan

| Komponen | Nilai |
|---|---|
| Mesin | Intel Core i3-10110U (2 inti / 4 utas), RAM 7,8 GB, Windows 11 |
| PHP | 8.4.22 NTS + OPcache, server bawaan `php -S` |
| Basis data | SQLite (berkas `database/beban.sqlite`, terpisah dari data pengembangan) |
| Sesi, cache, antrean | driver `database` (sama dengan konfigurasi pengembangan) |
| Klien beban | `tools/uji-beban/uji-beban.mjs` (Node 24, tanpa dependensi) di mesin yang sama |
| Data | `LoadTestSeeder`: N mahasiswa, 1 ujian terbit (20 PG + 2 esai, acak soal dan opsi) |
| `APP_DEBUG` | `false` |

## 2. Skenario

Tiap peserta adalah klien HTTP sungguhan (cookie sesi, token CSRF, alur yang
sama dengan peramban):

1. **Fase 0 — login:** `GET /login` → `POST /login` → `GET` halaman ujian,
   serentak atau disebar selama `--ramp-login` detik.
2. **Fase 1 — mulai serentak:** semua peserta `POST mulai` lalu `GET soal`
   pada saat yang sama (puncak saat ujian dimulai, PRD §15).
3. **Fase 2 — ujian berjalan (120 detik):** heartbeat tiap 15 detik, autosave
   tiap 10 detik (1–3 jawaban acak), dan satu laporan pelanggaran per peserta
   pada waktu acak. Seperti `exam.js`, tiap peserta hanya punya satu permintaan
   berjalan pada satu waktu. Beban yang diharapkan untuk 100 peserta ≈ 16,7
   permintaan/detik.
4. **Fase 3 — kirim serentak:** semua peserta mengirim jawaban bersamaan
   (puncak saat waktu habis).

Latensi diukur di klien dan sudah termasuk waktu antre di server. Galat = status
HTTP tak terduga, batas waktu 30 detik, atau galat jaringan.

## 3. Hasil

### Ringkasan

| Putaran | Peserta | Proses PHP | Login | Siap | Galat | Throughput fase 2 |
|---|---|---|---|---|---|---|
| R1 | 50 | 1 | serentak | 25/50 | 25 (batas waktu 30 s saat login) | 4,4 permintaan/s |
| R2 | 100 | 1 | disebar 90 s | 100/100 | 0 | 6,6 permintaan/s (jenuh) |
| R3 | 100 | 4 + WAL | disebar 90 s | 100/100 | 0 dari 3.400 | 17,5 permintaan/s |
| R4 | 50 | 4 + WAL | disebar 45 s | 50/50 | 0 dari 1.700 | 8,8 permintaan/s |
| R5 | 100 | 4 + WAL | serentak | 98/100 | 2 (HTTP 500, SQLite terkunci) | 17,8 permintaan/s |

"4 + WAL" = empat proses `php -S` (port 8002–8005, peserta dibagi bergiliran,
meniru beberapa worker PHP-FPM) dengan SQLite `journal_mode=wal`,
`busy_timeout=5000`, `synchronous=normal`, transaksi `IMMEDIATE`.

### Latensi fase ujian berjalan (ms)

| Putaran | Endpoint | p50 | p95 | p99 | Maks |
|---|---|---|---|---|---|
| R2 (100, 1 proses) | autosave | 14.984 | 16.763 | 16.940 | 17.028 |
| | heartbeat | 7.381 | 13.827 | 13.924 | 13.924 |
| | pelanggaran | 15.805 | 16.777 | 16.894 | 16.953 |
| R3 (100, 4 proses) | autosave | 275 | 708 | 890 | 1.416 |
| | heartbeat | 250 | 747 | 1.016 | 1.471 |
| | pelanggaran | 282 | 804 | 1.169 | 1.374 |
| R4 (50, 4 proses) | autosave | 145 | 330 | 499 | 705 |
| | heartbeat | 126 | 294 | 415 | 475 |
| | pelanggaran | 132 | 333 | 578 | 578 |

### Latensi puncak (ms)

| Putaran | Mulai (p95) | Ambil soal (p95) | Kirim serentak (p95) | Login (p95) |
|---|---|---|---|---|
| R2 (100, 1 proses) | 15.513 | 16.447 | 17.924 | 5.577 (disebar) |
| R3 (100, 4 proses) | 4.919 | 5.047 | 9.753 | 871 (disebar) |
| R4 (50, 4 proses) | 2.746 | 2.925 | 4.585 | 839 (disebar) |
| R5 (100, 4 proses) | 4.329 | 4.767 | 8.361 | 27.111 (serentak) |

## 4. Analisis

1. **Satu proses PHP tidak cukup untuk 100 peserta.** Server bawaan PHP
   melayani satu permintaan pada satu waktu (±7 permintaan/detik di mesin ini),
   sedangkan 100 peserta membutuhkan ±16,7 permintaan/detik. Permintaan
   mengantre sehingga latensi naik ke belasan detik (R2). Tidak ada data yang
   hilang (0 galat), tetapi target pencatatan pelanggaran < 1 detik tidak
   terpenuhi. Pada R1, 50 login serentak (bcrypt) dalam satu proses melewati
   batas waktu 30 detik untuk separuh peserta.
2. **Dengan 4 proses, 100 peserta berjalan stabil.** Seluruh beban fase 2
   terlayani (17,5 permintaan/detik, 0 galat dari 3.400 permintaan).
   Pencatatan pelanggaran: p95 0,80 detik (memenuhi < 1 detik), p99 1,17 detik
   (sedikit di atas). Untuk 50 peserta (R4) p99 pelanggaran 0,58 detik.
3. **Puncak awal dan akhir paling berat.** Mulai + ambil soal serentak untuk 100
   peserta selesai dalam ±5 detik; kirim serentak hingga ±10 detik (penilaian
   PG dan rekap dalam satu transaksi; SQLite hanya mengizinkan satu penulis
   pada satu waktu). Tidak ada permintaan yang gagal; layar ujian tidak
   memasang batas waktu permintaan, jadi mahasiswa hanya menunggu lebih lama.
4. **Login serentak 100 peserta (R5)** butuh hingga ±28 detik karena bcrypt
   (cost 12) adalah pekerjaan CPU, dan 2 permintaan pertama gagal dengan HTTP
   500 karena "database is locked" (SQLite) di detik pertama. Dalam praktik
   mahasiswa login beberapa menit sebelum ujian dimulai (R3), dan produksi
   memakai MySQL/PostgreSQL yang tidak memakai kunci tingkat berkas.

## 5. Kesimpulan dan rekomendasi

- Aplikasi **sanggup melayani 100 peserta bersamaan tanpa galat** pada fase
  ujian berjalan bila server memakai beberapa proses/worker PHP. Dengan satu
  proses (server bawaan PHP), kapasitas ±40 peserta (±7 permintaan/detik).
- Untuk produksi: Nginx/Apache + **PHP-FPM ≥ 4 worker**, **MySQL/PostgreSQL**,
  OPcache aktif, sesi/cache sebaiknya Redis atau basis data server (bukan
  SQLite), dan minta mahasiswa login 5–10 menit sebelum ujian dimulai.
- Uji ini perlu **diulang di server produksi** (dengan jaringan kampus
  sungguhan) untuk angka yang dipakai sebagai klaim di KTI. Angka di dokumen ini
  sah sebagai hasil di lingkungan pengembangan yang dijelaskan di §1.

## 6. Cara mengulang

Basis data uji terpisah agar data pengembangan tidak tersentuh. Contoh Git Bash
(PowerShell: atur `$env:DB_DATABASE` dsb.):

```bash
export DB_DATABASE="$PWD/database/beban.sqlite"   # WAJIB: basis data uji, bukan database.sqlite
touch "$DB_DATABASE"
LOAD_TEST_USERS=100 php artisan migrate:fresh --seeder=LoadTestSeeder --force

# Server (ulangi untuk port 8003-8005 bila memakai beberapa proses)
cd public
DB_BUSY_TIMEOUT=5000 DB_JOURNAL_MODE=wal DB_SYNCHRONOUS=normal DB_TRANSACTION_MODE=IMMEDIATE APP_DEBUG=false \
  php -d zend_extension=opcache -d opcache.enable_cli=1 -S 127.0.0.1:8002 \
  ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
cd ..

node tools/uji-beban/uji-beban.mjs --url http://127.0.0.1:8002 --ujian 1 \
  --peserta 100 --durasi 120 --ramp-login 90 --keluaran hasil.json
```

Untuk server produksi cukup jalankan seeder di basis data uji dan arahkan
`--url` ke alamat server. Jangan menjalankan `LoadTestSeeder` di basis data
produksi (seeder menolak bila `APP_ENV=production`).

Catatan: berkas R1 dibuat versi awal skrip (fase login dan mulai masih
digabung sebagai "awal").
