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
