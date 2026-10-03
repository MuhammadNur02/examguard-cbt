# PROGRESS.md — Kemajuan Pengembangan

Ringkasan per fase: apa yang selesai, apa yang diuji dan hasilnya, serta cara
menjalankan dan memverifikasi. Detail keputusan ada di [DECISIONS.md](DECISIONS.md),
hambatan di [BLOCKERS.md](BLOCKERS.md).

## Lingkungan di mesin ini

| Alat | Versi | Lokasi |
|---|---|---|
| PHP | 8.4.22 NTS | `%LOCALAPPDATA%\Programs\PHP\php-8.4.22-nts\php.exe` (tidak di PATH) |
| Composer | 2.10.3 | `%LOCALAPPDATA%\Programs\Composer\composer.phar` |
| Node / npm | 24.18 / 11.16 | sudah ada sebelumnya |
| Python (NLP) | 3.13.15 x64 | `%LOCALAPPDATA%\Programs\Python\Python313\` (virtualenv: `nlp-service\.venv`) |
| Basis data | SQLite | `database\database.sqlite` |

Agar perintah `php` dan `composer` di bawah berjalan di PowerShell, jalankan
sekali per terminal:

```powershell
$env:Path = "$env:LOCALAPPDATA\Programs\PHP\php-8.4.22-nts;$env:Path"
function composer { php "$env:LOCALAPPDATA\Programs\Composer\composer.phar" @args }
```

## Fase 1 — Arsitektur dan Skema Basis Data

| Task | Status | Bukti |
|---|---|---|
| 1.1 Skema DB | Selesai | `tests/Feature/Database/SchemaTest.php` lulus; `migrate:fresh --seed`, `migrate:rollback`, `migrate` bersih di SQLite; ERD di `docs/ERD.md` |
| 1.2 Inisialisasi | Selesai | Clone bersih: `composer setup` + `db:seed` + `php artisan test` (7/7) + Pint lulus; layanan NLP dari nol: `pytest` (9/9) + ruff lulus |
| 1.3 Autentikasi & peran | Selesai | `LoginTest`, `RoleAuthorizationTest`; dicek di peramban headless (login per peran, pesan galat, logout, 403) |
| 1.4 Single session | Selesai | `SingleSessionTest`; dua profil peramban: perangkat lama dikeluarkan, perangkat baru tetap masuk, keduanya tercatat di `audit_logs` |
| 1.5 Manajemen akun admin | Selesai | `UserManagementTest`, `UserImportTest`, tes unit CSV; impor CSV salah/benar dan login akun hasil impor dicek di peramban |
| 1.6 Kelas (S) | Selesai (tahap Should) | Lihat bagian "Tugas Should" di bawah |

Hasil tes akhir Fase 1: **PHPUnit 86/86 lulus**, Pint lulus, **pytest 9/9
lulus**, ruff lulus, `npm run build` berhasil.

Catatan 1.2: tidak ada Docker di mesin ini, jadi dipakai skrip setup
(`composer setup`, bawaan Laravel 13) dan langkah manual di README. Docker
Compose belum dibuat karena tidak bisa diuji di sini.

### Tinjauan kritis Fase 1
- Dicek: output tak ter-escape (hanya SVG ikon dari repo), SQL mentah (tidak
  ada), rahasia di repo (tidak ada), `.env` tidak ter-track, otorisasi lintas
  peran (diuji untuk semua area dan semua aksi admin), kunci jawaban
  disembunyikan dari serialisasi model.
- Diperbaiki: header keamanan dasar (`X-Frame-Options: DENY`, `nosniff`,
  Referrer-Policy, Permissions-Policy) dan pencegahan injeksi formula pada CSV
  kredensial yang diunduh.

### Catatan untuk produksi (belum dikerjakan, dicatat agar tidak lupa)
- Di balik reverse proxy/load balancer, atur *trusted proxies* agar IP asli
  terbaca; tanpa itu rate limit login dan log IP memakai IP proxy.
- Pakai HTTPS dan `SESSION_SECURE_COOKIE=true`.
- Layanan NLP hanya di `127.0.0.1` atau jaringan privat, dengan token terisi.

## Fase 2 — Antarmuka Dosen dan Bank Soal (jalur MVP)

| Task | Status | Bukti |
|---|---|---|
| 2.1 Kelola ujian | Selesai | `ExamManagementTest` (validasi, kepemilikan 8 aksi × 3 peran, aturan terbit, penguncian setelah dikerjakan); DoD jadwal di `ShuffledQuestionsTest` |
| 2.2 Form soal PG/esai | Selesai | `QuestionManagementTest`; alur tambah soal + galat kunci dicek di peramban |
| 2.4 API soal teracak | Selesai | `FisherYatesTest` (termasuk uji keseragaman), `ShuffledQuestionsTest` (seed deterministik) |
| 2.3, 2.5–2.8 | Belum | Should/Could, dikerjakan setelah jalur MVP |

Catatan: centang "posisi tetap" untuk opsi sudah ada di form dan dihormati
pengacakan (teruji), tetapi Task 2.5 baru akan dicentang saat dikerjakan utuh.

### Tinjauan kritis Fase 2
- Dicek: kunci tidak ada di respons mahasiswa (diuji, termasuk ID dan label asli),
  attempt selalu dicari dari pasangan (ujian, pengguna login), soal memakai
  scoped binding, `status` ujian tidak bisa dikirim lewat form.
- Diperbaiki: semua respons area mahasiswa kini `Cache-Control: no-store` agar
  soal tidak tertinggal di cache komputer lab bersama.
- Belum ada: pembatasan ujian per kelas (FR-02.6, Should). Saat ini semua
  mahasiswa dapat memulai ujian terbit mana pun dalam jadwal.

## Fase 3 — Antarmuka Mahasiswa dan Engine Anti-Kecurangan (jalur MVP)

| Task | Status | Bukti |
|---|---|---|
| 3.1 Layar pengerjaan | Selesai | Peramban headless: navigasi nomor urutan acak, ragu, timer dari `sisa_detik` server; `ExamSessionTest` (heartbeat, waktu tambahan) |
| 3.2 Persetujuan + layar penuh | Selesai | Tombol Mulai nonaktif sampai dicentang (server juga memvalidasi, `ExamPagesTest`); keluar layar penuh → pelanggaran `keluar_fullscreen` + modal, tombol modal masuk layar penuh lagi (diuji di peramban) |
| 3.3 Blokir aksi | Selesai | Peramban: `copy/paste/cut/contextmenu/selectstart`, Ctrl+C/V/U, Ctrl+Shift+I, F12 → `defaultPrevented`; Tab tidak diblokir |
| 3.4 Deteksi + debounce | Selesai | `ViolationTest` (debounce, K-7, presisi ms); peramban: `blur`+`visibilitychange` satu kejadian = 1 pelanggaran; latensi lihat bawah |
| 3.5 Modal bertingkat + auto-submit | Selesai | Peramban: 1/3 → 2/3 → Peringatan Terakhir (3/3) → Ujian Dikunci, input beku; `ViolationTest`: ke-(N+1) mengunci, jawaban tersimpan ikut terkirim |
| 3.6 Autosave + heartbeat | Selesai | Peramban: offline → antrean di localStorage + "Offline, mencoba lagi" → online → tersimpan; muat ulang mempertahankan jawaban; `ExamSessionTest` |
| 3.7 Kirim + riwayat nilai | Selesai | Peramban: modal konfirmasi berisi rekap, jawaban esai terakhir ikut tersimpan, 0 pelanggaran palsu saat keluar layar penuh setelah kirim; `ExamPagesTest` (nilai tersembunyi sebelum publikasi) |
| 3.8 Watermark, perangkat, mobile (S) | Belum | Setelah jalur MVP |

### Pengukuran latensi pencatatan pelanggaran (mesin pengembangan ini)
Selisih `exam_logs.waktu` (server) dan `detail.waktu_klien` (saat event terjadi;
klien dan server satu mesin sehingga jam sama):

| Kondisi server dev | Kejadian → tersimpan di DB | Kejadian → modal tampil |
|---|---|---|
| `php artisan serve` tanpa OPcache | 610–1047 ms (7 dari 8 sampel < 1 s) | – |
| PHP built-in server + OPcache | **194–257 ms** (3 sampel) | 324–383 ms |

Ini bukan uji beban (Task 5.4) dan bukan lingkungan produksi; ukur ulang di
server target. Untuk produksi, OPcache wajib aktif. Untuk pengembangan di
Windows, aktifkan OPcache di `php.ini` (`zend_extension=opcache`,
`opcache.enable_cli=1`) atau jalankan server dari folder `public`:

```powershell
cd public
php -d zend_extension=opcache -d opcache.enable_cli=1 -S 127.0.0.1:8000 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

Tes otomatis juga memastikan waktu proses server satu laporan < 1 detik.

### Tinjauan kritis Fase 3 (lihat juga DECISIONS D-27..D-33)
- Waktu, penghitung pelanggaran, dan status hanya ditentukan server; klien yang
  dimanipulasi paling jauh bisa *tidak melapor* (batasan jujur PRD §4), tetapi
  tidak bisa mengurangi hitungan atau memperpanjang waktu.
- Attempt selalu dicari dari (ujian, pengguna login); endpoint ujian diberi
  rate limit dan `Cache-Control: no-store`.
- Layar pengerjaan tidak menyisipkan soal/kunci di HTML; soal diambil lewat API
  tanpa kunci dan dirender dengan `textContent`.

## Fase 4 — Mesin Penilaian dan Dashboard Dosen (jalur MVP)

| Task | Status | Bukti |
|---|---|---|
| 4.1 Nilai PG | Selesai | `MultipleChoiceScoringTest`: data uji hitungan manual (skor PG 6, maks 19,5, nilai 30,77), jawaban dipilih lewat API pada urutan acak |
| 4.2 Preprocessing | Selesai | `nlp-service/tests/test_preprocessing.py` ("menghubungkan" → "hubung") |
| 4.3 TF-IDF + Cosine | Selesai | `test_scoring.py`: contoh hitung manual (0,8165 dan 0,3495) + rumus independen pada 30 data acak; skor = similarity × bobot di `EssayScoringIntegrationTest` |
| 4.4 Integrasi + antrean | Selesai | `EssayScoringIntegrationTest` (Http::fake) + uji ke FastAPI nyata: similarity 0,6432 × 10 = 6,43 |
| 4.5 Live Monitor | Selesai | `MonitorTest`; dua sesi peramban: peserta dan pelanggaran baru muncul tanpa muat ulang |
| 4.6 Koreksi berdampingan | Selesai | `EssayCorrectionTest` (12 tes); alur Setujui di peramban |
| 4.10 Rekap + Excel | Selesai | `ReportTest`: berkas .xlsx dibaca balik, tanpa sel formula, publikasi ujung-ke-ujung. Belum dibuka langsung di aplikasi Excel |
| 4.7–4.9, 4.11 | Belum | Should/Could |

Menjalankan penilaian esai: layanan NLP (`uvicorn ...`) dan queue worker
(`php artisan queue:work` atau `php artisan dev`) harus berjalan, dengan
`NLP_SERVICE_TOKEN` yang sama di `.env` Laravel dan `nlp-service/.env`.

### Tinjauan kritis Fase 4
- Bug ditemukan dan diperbaiki: (1) `refresh()` setelah finalisasi membuang
  atribut `withCount` di monitor; (2) `firstWhere('skor_final', null)`
  membandingkan longgar sehingga skor 0 dianggap belum dikonfirmasi. Keduanya
  punya tes regresi.
- Injeksi formula pada ekspor Excel ditutup (OpenSpout mengubah teks berawalan
  `=` menjadi formula bila memakai `Cell::fromValue`).
- Ditambahkan jejak audit setiap perubahan skor esai final (dari → ke,
  termasuk penanda bila nilai sudah dipublikasikan).

## Fase 5 — Pengujian, Data KTI, Finalisasi

| Task | Status | Bukti / catatan |
|---|---|---|
| 5.1 Black-box | Selesai | `docs/PENGUJIAN-BLACKBOX.md`: 25 skenario lulus (Chromium headless + tes otomatis); pindah tab/Esc disimulasikan lewat event — ulangi manual untuk lampiran |
| 5.2 Keamanan teknis | Selesai | `RouteSecurityTest` (semua rute × peran, CSRF 31 rute pengubah data, batas laju), CSP diuji di peramban; `docs/KEAMANAN.md` |
| 5.3 Akurasi esai | **Alat siap, data belum ada** | `ujian:ekspor-esai` + `nlp-service/evaluasi.py` (teruji); butuh 30–50 jawaban yang dinilai dosen (BLOCKERS B-04) |
| 5.4 Uji beban (S) | Selesai (lingkungan pengembangan) | `docs/UJI-BEBAN.md`: 100 peserta, 4 proses PHP + SQLite WAL: 0 galat dari 3.400 permintaan, pelanggaran p95 0,80 s / p99 1,17 s; 1 proses jenuh di ±7 permintaan/s; perlu diulang di server target |
| 5.5 SUS/UAT | **Instrumen siap, responden belum ada** | `docs/KUESIONER-SUS-UAT.md` + `nlp-service/sus.py` (teruji) (BLOCKERS B-05) |
| 5.6 Dokumentasi | Selesai | README, panduan dosen/admin/mahasiswa, backup, pemetaan KTI |

## Tugas Should (setelah jalur MVP)

| Task | Status | Bukti / catatan |
|---|---|---|
| 1.6 Kelas | Selesai | `ClassManagementTest`, `ClassVisibilityTest` (daftar, halaman, dan semua endpoint ujian mengikuti kelas); D-43 |
| 2.3 Impor soal | Selesai | `QuestionImportTest` (CSV/XLSX valid, 8 jenis galat bernomor baris tanpa ada yang tersimpan, templat lolos validasi sendiri), `SpreadsheetReaderTest`, `CsvReaderTest` (sel multibaris); diuji di peramban; D-44 |
| 2.5 Opsi pengacakan | Selesai | `ShuffleSettingsTest`: kombinasi acak soal/opsi terpisah dan opsi terkunci (tengah dan akhir) tidak berpindah atas 200 seed; diverifikasi dengan mutasi |
| 2.6 Duplikat, pratinjau, kode akses | Selesai | `ExamDuplicatePreviewTest`, `AccessCodeTest` (kode salah/kosong ditolak tanpa attempt, tidak bocor ke HTML, batas percobaan); alur penuh diuji di peramban; D-45 |

| 3.8 Watermark, perangkat berganti, ponsel | Selesai | `DeviceIntegrityTest`, `PerangkatTest`; di peramban: watermark terlihat pada tangkapan layar dan klik tetap tembus, ponsel (UA + layar) dan layar kecil ber-UA desktop ditolak; D-46 |

| 4.7 Koreksi cepat + checklist kata kunci | Selesai | `EssayBulkAcceptTest` (hanya ≥ ambang dan belum dikonfirmasi; mutasi terdeteksi); checklist sudah ada sejak Fase 4 (`EssayCorrectionTest`); diuji dengan skor NLP sungguhan; D-47 |
| 4.8 Maafkan/reset, tambah waktu, buka ulang | Selesai | `AttemptManagementTest` (9 skenario termasuk scoped binding dan esai yang diubah setelah buka ulang); di peramban: lencana mahasiswa 2/3 → 1/3 dan timer +10 menit lewat heartbeat; D-48 |
| 4.9 Kemiripan esai antarmahasiswa | Selesai | `EssaySimilarityFlagTest`, `nlp-service/tests/test_kemiripan.py`; layanan NLP sungguhan menandai salinan 2301001 ↔ 2301003 (1,00 dan 0,84) dan tidak menandai pasangan lain; D-49 |

### Tinjauan kritis Fase 4 (Should)
- Semua tindakan dosen baru (terima massal, maafkan/reset, tambah waktu, buka
  ulang) berada di grup `can:kelola` dengan binding bertingkat; tes kepemilikan
  otomatis (`RouteSecurityTest`) mencakup 36 rute pengubah data.
- Attempt yang dibuka ulang tidak dapat ikut dipublikasikan sebelum dikirim
  lagi (`ReportService`: final hanya bila tidak berlangsung); buka ulang ditolak
  bila nilainya sudah dipublikasikan.
- Ditemukan saat pengerjaan: teks esai yang berubah setelah buka ulang akan
  mempertahankan skor lama; kini dikosongkan (tes regresi).
- Tanda kemiripan suatu attempt yang dibuka ulang baru diperbarui pada
  penghitungan skor rekomendasi berikutnya (dicatat, bukan bug kritis).

### Tinjauan kritis Fase 2 (Should)
- Tidak ada jalur baru yang mengirim kunci PG, kunci esai, atau kode akses ke
  mahasiswa (pratinjau hanya untuk dosen pemilik; payload sama dengan layar ujian).
- Ditemukan dan diperbaiki saat tinjauan: `CsvReader` membaca per baris fisik
  sehingga sel berkutip multibaris (Alt+Enter di Excel) memecah satu soal;
  `lockForUpdate()` + `max()` ditolak PostgreSQL (dihapus); kode akses yang
  tersimpan huruf kecil di luar form tidak akan pernah cocok (kedua sisi kini
  dinormalkan, ada tes).

## Cara menjalankan (ringkas)

```powershell
php artisan dev                  # web + queue + Vite, http://localhost:8000
php artisan db:seed              # data contoh (akun di README)
cd nlp-service; .venv\Scripts\uvicorn app.main:app --host 127.0.0.1 --port 8001 --env-file .env
```

## Perintah verifikasi

```powershell
php artisan test
vendor\bin\pint --test
cd nlp-service; .venv\Scripts\python -m pytest; .venv\Scripts\ruff check .
```
