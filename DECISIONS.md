# DECISIONS.md — Keputusan dan Asumsi

Catatan keputusan selama pengembangan otonom. Format: **keputusan**, alasan,
alternatif yang dipertimbangkan. Semua dapat diubah; beri tahu bila tidak setuju.

## A. Lingkungan dan Repositori

### D-01 PHP 8.4 NTS resmi di luar PATH sistem
- **Keputusan:** PHP 8.4.22 NTS (zip resmi php.net, SHA256 diverifikasi terhadap
  manifest winget) ditempatkan di `%LOCALAPPDATA%\Programs\PHP\php-8.4.22-nts`
  dengan `php.ini` sendiri. PATH sistem **tidak** diubah.
- **Alasan:** Mesin ini tidak punya PHP. Percobaan pertama (winget `PHP.PHP.8.4`,
  build TS 8.4.25) berhasil terpasang, tetapi **Smart App Control** (mode enforce)
  memblokir `php_mbstring.dll`. PHPUnit dan Pint mewajibkan ekstensi mbstring asli,
  sehingga tes tidak bisa jalan. Kebijakan keamanan itu **tidak** diakali; saya
  mencoba satu alternatif sah, yaitu build resmi NTS (build yang memang dianjurkan
  untuk CLI), dan DLL-nya diizinkan oleh kebijakan. Build TS saya copot kembali
  agar tidak ada dua PHP yang membingungkan.
- **Alternatif:** memakai polyfill mbstring (PHPUnit/Pint tetap menolak berjalan),
  mengubah `vendor/` (rapuh, dilarang), mematikan Smart App Control (melanggar
  batas keamanan), Laragon/XAMPP (instalasi lebih besar, butuh interaksi).

### D-02 Composer dari getcomposer.org
- **Keputusan:** `composer.phar` 2.10.3 diunduh dari getcomposer.org, checksum
  SHA-256 dicocokkan, ditaruh di `%LOCALAPPDATA%\Programs\Composer\composer.phar`.
- **Alasan:** installer Composer-Setup.exe interaktif dan bisa meminta hak admin.
- **Alternatif:** winget `Composer.Composer`.

### D-03 Python 3.13 64-bit untuk layanan NLP
- **Keputusan:** Python 3.13.15 x64 dipasang lewat winget (scope user, tanpa
  mengubah PATH dan tanpa py launcher baru), berdampingan dengan Python 3.14
  32-bit yang sudah ada (tidak disentuh). Virtualenv di `nlp-service/.venv`.
- **Alasan:** scikit-learn tidak menyediakan wheel untuk Python 32-bit Windows.
- **Alternatif:** menulis TF-IDF tanpa scikit-learn (menyimpang dari stack PRD).

### D-04 SQLite untuk pengembangan dan tes
- **Keputusan:** `DB_CONNECTION=sqlite` (berkas `database/database.sqlite`), tes
  memakai SQLite in-memory. Migration ditulis portabel (tanpa tipe khusus MySQL),
  sehingga beralih ke MySQL/PostgreSQL cukup mengubah `.env` (petunjuk ada di
  `.env.example`).
- **Alasan:** MySQL/PostgreSQL tidak terpasang di mesin ini.

### D-05 Branch kerja dimulai dari `main` terbaru
- **Keputusan:** branch lokal `claude/sweet-einstein-gv61ik` dibuat dari
  `origin/main` (11787c1). Branch remote lama (40fa786) adalah leluhur `main`,
  sehingga push pertama berupa fast-forward biasa, bukan force push.
- **Alasan:** agar perubahan README Anda di `main` ikut dan PR nanti bebas konflik.
- **Alternatif:** mulai dari 40fa786 (README Anda di `main` akan berkonflik).

### D-06 Laravel 13 + PHPUnit
- **Keputusan:** Laravel 13.34 (rilis stabil terbaru), PHPUnit 12 (bawaan kerangka),
  Pint sebagai linter PHP.
- **Alternatif:** Pest (tidak perlu untuk cakupan ini).

### D-07 Berkas agen bawaan kerangka tidak disalin
- **Keputusan:** `CLAUDE.md`/`AGENTS.md` bawaan kerangka Laravel (instruksi
  "Laravel Boost" untuk memasang paket tambahan) tidak disalin ke repo.
- **Alasan:** isinya instruksi pihak ketiga yang tidak relevan dengan proyek.

### D-08 Lisensi di composer.json
- **Keputusan:** `"license": "proprietary"` menggantikan `MIT` bawaan kerangka.
- **Alasan:** Anda belum memilih lisensi; saya tidak menetapkan lisensi terbuka
  atas nama Anda. Silakan ganti bila ingin lisensi tertentu.

## B. Tampilan

### D-09 Token StyleGuide di Tailwind v4 (`@theme`)
- **Keputusan:** kerangka Laravel 13 memakai Tailwind v4 (konfigurasi di CSS).
  Token StyleGuide §12 dipindahkan ke blok `@theme` di `resources/css/app.css`
  dengan nilai hex yang sama. Palet bawaan Tailwind dikosongkan
  (`--color-*: initial`) sehingga hanya token resmi yang bisa dipakai.
  `stone-200/500/700` dan `ink` didefinisikan eksplisit sesuai tabel §2.1
  (nilai bawaan Tailwind sedikit berbeda).
- **Alternatif:** `tailwind.config.js` gaya v3 lewat `@config` (mode kompatibilitas).

### D-10 Font di-host sendiri
- **Keputusan:** Inter, Playfair Display, JetBrains Mono dari paket npm
  `@fontsource/*` (subset latin), bukan CDN Bunny Fonts bawaan kerangka.
- **Alasan:** ujian tidak boleh bergantung pada layanan luar; privasi peserta.

## C. Layanan NLP

### D-11 Token internal wajib (gagal tertutup)
- **Keputusan:** endpoint penilaian mensyaratkan header `X-Internal-Token`.
  Bila `NLP_SERVICE_TOKEN` kosong, endpoint menolak (503), tidak terbuka.
  `/docs` dan `/openapi.json` dimatikan. Bawaan host `127.0.0.1`.
- **Alasan:** PRD §9.3: layanan hanya untuk Laravel, tidak diekspos publik.

### D-12 `httpx2` untuk TestClient
- **Keputusan:** dependensi pengembangan memakai `httpx2` (dirujuk langsung oleh
  Starlette 1.7; `httpx` menimbulkan peringatan usang).

## D. Skema dan Aturan Bisnis

### D-13 Kolom identitas login `nim_nidn`
- **Keputusan:** satu kolom `users.nim_nidn` (unik) menyimpan NIM (mahasiswa),
  NIDN (dosen), atau username (admin), sesuai PRD §11. Nilai hanya di-*trim*,
  tidak diubah huruf besar/kecilnya.
- **Alternatif:** kolom `username` generik (lebih umum di Laravel, tetapi
  menyimpang dari PRD).

### D-14 Jadwal ujian sinkron
- **Keputusan:** ujian terbuka pada jendela `[mulai, mulai + durasi_menit)`.
  Batas waktu setiap attempt = akhir jendela + `waktu_tambahan` (FR-06.6).
  Mahasiswa yang mulai terlambat mendapat sisa waktu, bukan durasi penuh.
- **Alasan:** PRD hanya punya `mulai` dan `durasi_menit`; "tidak bisa dimulai di
  luar jadwal" paling sederhana dibaca sebagai jendela tersebut, dan semua peserta
  selesai bersamaan sehingga soal tidak bocor ke peserta yang mulai belakangan.
- **Alternatif:** durasi penuh per attempt sejak mulai + batas akhir terpisah
  (butuh kolom tambahan yang tidak ada di PRD).

### D-15 Pemetaan urutan disimpan, klien hanya melihat posisi
- **Keputusan:** saat attempt dibuat, Fisher-Yates (PRNG Mt19937 berseed
  `shuffle_seed`) menghasilkan `urutan_soal` dan `urutan_opsi` yang disimpan di
  attempt. Klien hanya menerima nomor posisi soal/opsi, bukan ID asli; server
  memetakan posisi ke ID dari data tersimpan.
- **Alasan:** urutan stabil saat reload (FR-03.3) walau soal diedit kemudian, dan
  ID berurutan tidak membocorkan urutan asli opsi (pola letak kunci dosen).

### D-16 Publikasi nilai per hasil
- **Keputusan:** `exam_results.dipublikasikan_pada` per attempt (sesuai PRD §11).
  Tombol publikasi mengisi kolom ini untuk semua hasil ujian yang sudah final.

### D-17 Rumus nilai akhir
- **Keputusan:** `nilai_akhir = (skor_pg + skor_esai_final) / skor_maksimal × 100`
  (dua desimal), terisi setelah semua esai pada attempt dikonfirmasi dosen.
  `skor_maksimal` = jumlah bobot soal pada attempt.

### D-18 Akun dinonaktifkan, bukan dihapus
- **Keputusan:** tidak ada hapus akun; admin menonaktifkan (`aktif = false`).
  Relasi ke ujian/attempt memakai `restrictOnDelete` agar data ujian tidak hilang.

### D-19 Mode ketat Eloquent di luar produksi
- **Keputusan:** `Model::shouldBeStrict()` aktif kecuali produksi, untuk menangkap
  N+1 dan atribut yang dibuang diam-diam saat tes.

### D-20 Kata sandi data contoh
- **Keputusan:** `DemoSeeder` memakai `SEED_PASSWORD` dari `.env` (bawaan
  `password`) dan menolak berjalan bila `APP_ENV=production`.

## E. Keputusan Terbuka PRD §14

| ID | Sikap yang dipakai |
|---|---|
| K-1 | Batas = N (`exams.batas_pelanggaran`, bawaan 3). Pelanggaran 1..N memunculkan modal "Peringatan Pelanggaran (X/N)" (ke-N berjudul "Peringatan Terakhir (N/N)"); pelanggaran ke-(N+1) memicu auto-submit dan status `terkunci`. |
| K-2 | IDF dihitung dari korpus per soal: kunci dosen + seluruh jawaban mahasiswa pada soal itu, setelah ujian selesai. |
| K-3 | Kata kunci wajib ditampilkan sebagai checklist ke dosen (terpenuhi/tidak) **tanpa penalti** agar rumus inti tetap murni; penalti dapat ditambah kemudian. |
| K-4 | Live Monitor memakai polling 5 detik (≤ 10 detik sesuai PRD). |
| K-5 | Admin: akun dan kelas. Dosen: ujian dan nilai, **hanya ujian miliknya sendiri**. Peran dipisah ketat (admin tidak otomatis bisa mengelola ujian). |
| K-6 | Autentikasi dibangun di Fase 1. |
| K-7 | Pindah tab dan keluar fullscreen masuk penghitung yang sama, dengan `jenis` berbeda di `exam_logs`. |
| K-8 | Login kedua memutus sesi lama (sesi lama ditolak pada permintaan berikutnya) dan dicatat di `audit_logs`. |

## F. Autentikasi

### D-21 Rate limit login per identitas + IP
- **Keputusan:** 5 percobaan gagal berturut-turut untuk kombinasi NIM/NIDN +
  IP memicu penundaan 60 detik (FR-01.4); login berhasil mereset hitungan.
  Tidak ada batas global per IP.
- **Alasan:** batas per akun saja memungkinkan orang lain sengaja mengunci akun
  temannya menjelang ujian; batas global per IP berisiko memblokir satu lab yang
  keluar lewat satu IP NAT.
- **Alternatif:** batas per akun (rawan penguncian sengaja), batas per IP.

### D-22 Single session dengan token di sesi
- **Keputusan:** setiap login membuat token acak 64 karakter yang disimpan di
  `users.session_token` dan di sesi. Middleware `EnsureSingleSession` (grup `web`)
  mengeluarkan sesi yang tokennya tidak lagi cocok, juga akun yang dinonaktifkan.
  Dicatat di `audit_logs`: `sesi_diganti` (saat login baru menggantikan token
  lama) dan `sesi_lama_ditolak` (saat sesi lama mencoba dipakai; berisi
  user-agent dan path). Permintaan JSON dari sesi lama mendapat 401
  `{"kode": "sesi_berakhir"}` agar halaman ujian bisa menampilkan pesan.
- **Alasan:** bekerja untuk semua driver sesi (database, file, redis).
- **Belum dibuat:** mode "tolak login kedua" (FR-01.2 menyebut "sesuai
  pengaturan"). K-8 memilih memutus sesi lama; mode tolak butuh pelacakan sesi
  aktif dan dapat ditambah bila diperlukan.
- **Catatan:** `sesi_diganti` juga tercatat bila sesi sebelumnya sudah kedaluwarsa
  tanpa logout (token lama masih tersimpan). Logout normal menghapus token.
