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

### D-23 Task 1.5 dikerjakan di Fase 1
- **Keputusan:** Task 1.5 (M) tidak tercantum di "Jalur MVP" Task.md, tetapi
  berprioritas Must di PRD (FR-01.3) dan berada di Fase 1, jadi dikerjakan
  bersama Fase 1. Task 1.6 (S) ditunda sampai jalur MVP selesai.

### D-24 Kata sandi awal dan reset dibuat sistem, ditampilkan sekali
- **Keputusan:** bila admin tidak mengisi kata sandi, sistem membuat 10 karakter
  acak dari alfabet tanpa karakter mirip (i, l, o, 0, 1). Kata sandi tampil satu
  kali. Untuk tambah/reset satu akun memakai flash sesi (terhapus pada permintaan
  berikutnya); untuk impor, halaman hasil dirender langsung sehingga kata sandi
  tidak pernah masuk sesi. Reset kata sandi juga mengakhiri sesi aktif akun itu.
- **Alternatif:** admin mengetik kata sandi sendiri (rawan sandi lemah/seragam).

### D-25 Impor akun: CSV, semua-atau-tidak sama sekali
- **Keputusan:** format CSV (`nim_nidn,nama,peran,kata_sandi`), pemisah koma atau
  titik koma, BOM UTF-8 diterima. Seluruh berkas divalidasi dulu; satu baris salah
  membatalkan impor dan semua galat dilaporkan dengan nomor baris. Maksimal 500
  baris; peran yang boleh diimpor hanya mahasiswa/dosen (admin lewat form).
  Kata sandi impor di-hash bcrypt cost 10 (impor 500 akun cost 12 ≈ 2 menit di
  mesin ini) dan dinaikkan otomatis ke cost 12 saat login pertama — terverifikasi
  manual: hash akun yang sudah login berubah dari `$2y$10$` ke `$2y$12$`.
- **Belum dibuat:** impor langsung berkas `.xlsx`; dari Excel gunakan
  "Simpan sebagai CSV".

### D-26 Batas aksi admin atas akunnya sendiri
- **Keputusan:** admin tidak bisa menonaktifkan atau me-reset sesi akunnya sendiri
  dari daftar akun, sehingga selalu ada minimal satu admin aktif.

## G. Layar Ujian dan Anti-Kecurangan (Fase 3)

### D-27 Memuat ulang / meninggalkan halaman ujian terhitung pindah tab
- **Keputusan:** saat halaman dimuat ulang atau ditinggalkan, peramban memicu
  `visibilitychange` (hidden); kejadian ini dilaporkan (fetch `keepalive`) dan
  terhitung sebagai pelanggaran `pindah_tab`. Hal ini dijelaskan di kartu
  persetujuan integritas ("termasuk memuat ulang atau meninggalkan halaman").
- **Alasan:** menutup celah "pindah ke situs lain di tab yang sama lalu kembali".
  Muat ulang yang tidak disengaja dapat dimaafkan dosen lewat fitur FR-06.5 (Task 4.8, Should — belum dibuat saat keputusan ini dicatat).
- **Alternatif:** mengabaikan kejadian saat `beforeunload` (celah tersebut terbuka).

### D-28 Debounce dua lapis 2 detik, duplikat tidak disimpan
- **Keputusan:** klien hanya mengirim satu laporan per 2 detik; server juga
  menolak menghitung laporan yang tiba < 2 detik setelah pelanggaran terhitung
  terakhir dan **tidak menyimpan baris** untuk laporan duplikat itu.
- **Alasan:** PRD §9.1: `visibilitychange` dan `blur` (dan keluar layar penuh)
  dari satu kejadian dihitung sekali. Nilai dapat diubah lewat
  `EXAM_VIOLATION_DEBOUNCE_MS`.

### D-29 Toleransi 10 detik dan penutupan terjadwal
- **Keputusan:** jawaban yang tiba ≤ 10 detik setelah batas waktu masih diterima
  (latensi jaringan); setelah itu attempt ditutup "waktu habis". Perintah
  `ujian:tutup-kedaluwarsa` dijadwalkan tiap menit untuk attempt yang
  pesertanya sudah menutup peramban (jalankan `php artisan schedule:work` atau cron).

### D-30 Pintasan yang diblokir
- **Keputusan:** sesuai FR-04.3 (klik kanan, salin/potong/tempel, seleksi teks,
  Ctrl+C/V/U, Ctrl+Shift+I, F12) ditambah Ctrl+X (potong) dan Ctrl+Shift+J/C
  (pintasan DevTools lain yang setara Ctrl+Shift+I). Ctrl+S/P dan Tab tidak
  diblokir (StyleGuide §10). Seleksi teks diizinkan di kotak jawaban esai agar
  mahasiswa bisa menyunting; tempel tetap diblokir di mana pun. Aksi yang
  diblokir tidak dihitung sebagai pelanggaran.

### D-31 Autosave satu-permintaan-sekaligus
- **Keputusan:** klien mengirim perubahan jawaban berdasarkan posisi tampil,
  satu permintaan pada satu waktu (urutan simpan terjaga, tidak ada jawaban lama
  menimpa yang baru), antrean juga disimpan di `localStorage` agar selamat dari
  koneksi putus dan muat ulang, dengan coba ulang bertahap hingga 30 detik.
  Pilihan PG disimpan segera; esai 1,5 detik setelah berhenti mengetik; flush
  berkala tiap 10 detik.

### D-32 Layar penuh lewat tombol
- **Keputusan:** layar pengerjaan menampilkan tombol "Masuk Layar Penuh & Mulai"
  karena peramban hanya mengizinkan `requestFullscreen` dari gestur pengguna.
  Pemantauan aktif setelah layar penuh berhasil. Tombol "Kembali ke Ujian" pada
  modal meminta layar penuh lagi (DoD 3.2).

### D-33 Batas laju endpoint ujian
- **Keputusan:** 240 permintaan/menit per mahasiswa untuk endpoint ujian
  (autosave, heartbeat, pelanggaran, kirim) agar klien yang rusak tidak membanjiri server.

## H. Penilaian dan Laporan (Fase 4)

### D-34 Persyaratan PHP 8.4
- **Keputusan:** `composer.json` kini menuntut `php: ^8.4` dan README diperbarui.
- **Alasan:** `composer.lock` hasil instalasi sudah memakai komponen Symfony 8.1
  yang mensyaratkan PHP ≥ 8.4.1, sehingga klaim "PHP 8.3+" sebelumnya tidak akurat.
- **Alternatif:** menurunkan dependensi ke Symfony 7 dengan `config.platform.php=8.3`
  (perubahan besar, belum diperlukan).

### D-35 Esai kosong otomatis 0, nilai akhir menunggu dosen
- **Keputusan:** saat attempt difinalisasi, PG langsung dinilai; esai kosong
  diberi skor 0 final (tidak ada yang perlu dinilai); esai berisi menunggu skor
  rekomendasi NLP lalu keputusan dosen. Nilai akhir terisi setelah semua esai final.

### D-36 Skor rekomendasi dihitung setelah semua peserta selesai
- **Keputusan:** tombol "Hitung skor rekomendasi" menolak berjalan bila masih ada
  attempt berlangsung (setelah menutup yang kedaluwarsa), karena IDF memakai korpus
  kunci + seluruh jawaban soal itu (K-2). Hitung ulang tidak menimpa skor final dosen.

### D-37 Ekspor Excel dengan OpenSpout, teks selalu sel string
- **Keputusan:** ekspor `.xlsx` memakai `openspout/openspout` ^5.12. Semua teks
  ditulis sebagai `StringCell` karena `Cell::fromValue()` mengubah string berawalan
  `=` menjadi formula (risiko injeksi formula); diuji dengan memeriksa XML lembar kerja.
- **Alternatif:** PhpSpreadsheet (lebih berat, butuh ext-gd/mbstring), CSV (bukan Excel).

### D-38 Publikasi nilai hanya untuk nilai final
- **Keputusan:** tombol "Publikasikan nilai final" mengisi `dipublikasikan_pada`
  untuk hasil yang `nilai_akhir`-nya sudah terisi; sisanya dilaporkan dan tidak
  ikut. Publikasi terjadwal (FR-08.1, Could) belum dibuat.

## I. Keamanan dan Pengujian (Fase 5)

### D-39 CSP ketat hanya saat debug mati
- **Keputusan:** `Content-Security-Policy` (semua sumber `'self'`, tanpa inline,
  `frame-ancestors 'none'`) dipasang bila `APP_DEBUG=false`.
- **Alasan:** halaman galat debug Laravel memakai skrip inline dan server Vite
  dev memakai origin lain. Diuji di peramban dengan debug mati: layar ujian,
  Live Monitor, dan unduhan CSV berjalan tanpa pelanggaran CSP.

### D-40 Disk lokal tidak disajikan lewat rute
- **Keputusan:** `filesystems.disks.local.serve = false`, sehingga rute bawaan
  `GET/PUT storage/{path}` tidak terdaftar.
- **Alasan:** tidak dipakai aplikasi; berkas privat (ekspor dataset esai) tidak
  perlu diakses dari peramban.

### D-41 Task Must di luar "Jalur MVP"
- **Keputusan:** 5.2 dan 5.6 (Must, tidak tercantum di Jalur MVP) dikerjakan
  setelah 5.1/5.3/5.5, sebelum tugas Should — sama seperti 1.5 (D-23).

### D-42 Uji akurasi dan SUS memakai alat, bukan data rekaan
- **Keputusan:** untuk 5.3 dan 5.5 hanya disiapkan alat dan instrumen yang
  teruji. Tidak ada hasil penelitian yang dibuat-buat; contoh dataset diberi
  label "rekaan" dan tidak boleh dikutip sebagai hasil.

## J. Tugas Should

### D-43 Visibilitas ujian per kelas
- **Keputusan:** ujian yang ditetapkan ke ≥ 1 kelas hanya terlihat (daftar,
  halaman, semua endpoint ujian) oleh anggota kelas tersebut; ujian tanpa kelas
  terlihat oleh semua mahasiswa, dan halaman ujian dosen menampilkan peringatan
  tentang hal itu. Aturan dipusatkan di `Exam::terlihatOleh()` dan scope
  `terlihatUntuk()`.
- **Alasan:** kompatibel dengan ujian yang sudah ada dan instalasi tanpa kelas;
  peringatan mencegah dosen lupa memilih kelas tanpa sadar.
- **Alternatif:** wajib memilih kelas sebelum terbit (lebih ketat, tetapi memblokir
  dosen bila admin belum membuat kelas).
- Kelas yang masih dipakai ujian tidak dapat dihapus; impor anggota hanya
  menerima akun mahasiswa yang sudah ada (semua-atau-tidak sama sekali).

### D-44 Impor soal: XLSX + CSV, semua-atau-tidak sama sekali
- **Keputusan:** impor soal menerima `.xlsx` (lembar pertama, dibaca OpenSpout)
  dan `.csv` dengan kolom tetap (`tipe, teks, bobot, opsi_a–opsi_e, kunci,
  opsi_tetap, kunci_esai, kata_kunci`). Seluruh berkas divalidasi dengan aturan
  yang sama dengan form soal; satu baris salah membatalkan seluruh impor dan
  semua galat dilaporkan per nomor baris. Kolom yang tidak dikenal ditolak agar
  salah ketik judul kolom tidak diam-diam diabaikan. Soal ditambahkan setelah
  soal yang ada dan hanya pada ujian draf.
- **Alasan:** sama dengan impor akun (D-25) sehingga perilaku mudah
  ditebak; dosen memperbaiki berkas lalu mengunggah ulang tanpa duplikat setengah
  jadi. Templat Excel paling mudah diisi dosen; CSV tetap ada untuk alat lain.
- **Alternatif:** pratinjau lalu konfirmasi (perlu menyimpan berkas sementara di
  server), atau menyimpan baris yang valid saja (berisiko soal hilang tanpa sadar).
- `CsvReader` kini memakai `fgetcsv` sehingga sel berkutip berisi baris baru
  (Alt+Enter di Excel) terbaca utuh; nomor baris = nomor rekaman, sama dengan
  nomor baris di Excel.

### D-45 Duplikat, pratinjau, dan kode akses (Task 2.6)
- **Duplikat:** menyalin pengaturan, soal, opsi (termasuk kunci dan posisi
  tetap), kelas, dan daftar IP ke draf baru berjudul "(salinan)". **Kode akses
  tidak disalin** karena kode ujian lama mungkin sudah diketahui mahasiswa.
  Alternatif: menyalin semuanya (lebih sedikit klik, tetapi kode bocor terpakai ulang).
- **Pratinjau:** dirender di server dari payload yang sama dengan layar ujian
  (`AttemptService::soalUntukKlien` atas attempt yang tidak disimpan), dengan
  seed acak yang dapat diulang lewat `?seed=`. Tidak memuat `exam.js`, jadi
  tidak ada permintaan ke endpoint attempt. Alternatif: mode pratinjau di
  `exam.js` (lebih identik secara interaksi, tetapi menambah cabang pada kode
  paling kritis). Pratinjau menampilkan semua soal dalam satu halaman dan
  menyebutkan bahwa layar ujian menampilkannya satu per satu.
- **Kode akses:** disimpan apa adanya (huruf besar) di `exam_access.kode_akses`
  karena dosen perlu melihat dan mengumumkannya; tidak pernah dikirim ke halaman
  mahasiswa (`#[Hidden]` dan tes `assertDontSee`). Dicocokkan dengan
  `hash_equals`, tidak peka huruf/spasi tepi, hanya saat attempt baru dibuat
  (melanjutkan tidak meminta kode lagi). Percobaan salah dibatasi 5 kali per
  5 menit per mahasiswa per ujian. Alternatif: hash kode (tidak bisa
  ditampilkan ke dosen) atau meminta kode di setiap lanjut (mengganggu
  mahasiswa yang peramban-nya tertutup).

### D-46 Watermark, perangkat berganti, dan penolakan ponsel (Task 3.8)
- **Watermark (FR-04.8):** lapisan HTML tetap (`pointer-events-none`,
  `aria-hidden`) berisi "nama · NIM" berulang, miring, opasitas 8%, di atas
  konten tetapi di bawah modal. Alternatif: gambar SVG sebagai latar (lebih
  ringan, tetapi teks di dalam data URI lebih sulit diuji dan diubah).
- **Perangkat berganti (FR-04.9):** setiap permintaan attempt (mulai-lanjut,
  soal, jawaban, heartbeat, pelanggaran) membandingkan IP dan user-agent
  dengan nilai terakhir pada attempt. Perubahan dicatat sekali sebagai
  `perangkat_berganti` (tidak dihitung, PRD K-7) lalu nilai acuan diperbarui,
  sehingga setiap perpindahan tercatat tepat satu kali. Pembaruan bersyarat
  mencegah catatan ganda dari permintaan paralel. Alternatif: menghitungnya
  sebagai pelanggaran (berisiko menghukum ganti Wi-Fi yang wajar).
- **Ponsel (FR-04.11):** server menolak memulai dan melanjutkan dari
  user-agent seluler (termasuk tablet Android/iPad lama); peramban menolak
  layar < 1024 px sisi terpanjang atau perangkat sentuh tanpa mouse/trackpad.
  Perubahan perangkat dicatat dulu sebelum permintaan dari ponsel ditolak.
  Batasan (dapat dikelabui) ditulis jujur di `docs/KEAMANAN.md`.
