# Pengujian Black-box — ExamGuard CBT (Task 5.1)

Skenario diturunkan dari kriteria penerimaan PRD §7 dan daftar PRD §13.1.
Tabel ini dapat langsung dipakai sebagai bahan Bab 4 KTI; kolom *Hasil aktual*
berisi hasil eksekusi pada 3 Oktober 2026.

## Lingkungan dan metode

| Item | Nilai |
|---|---|
| Sistem | Windows 11, PHP 8.4.22 (server bawaan + OPcache), SQLite, layanan NLP FastAPI lokal |
| Peramban | Chromium headless (otomasi Playwright/patchright) |
| Data | `php artisan migrate:fresh --seed` (akun contoh di README) |

**Batasan metode (jujur):** peramban headless tidak dapat menekan Alt+Tab atau
tombol Esc fisik. Pindah tab disimulasikan dengan memicu event `blur` dan
`visibilitychange` (jalur kode yang sama dengan kejadian nyata); keluar layar
penuh dengan `document.exitFullscreen()` (setara Esc); putus koneksi dengan mode
offline peramban. Untuk lampiran KTI, ulangi skenario BB-11, BB-12, dan BB-16
secara manual di Chrome/Edge desktop dengan menekan tombol sungguhan.

Selain uji peramban, setiap skenario juga dijaga tes otomatis (kolom *Bukti*),
yang dijalankan dengan `php artisan test` dan `pytest`.

## Kasus uji

| ID | FR | Skenario | Hasil yang diharapkan | Hasil aktual | Status | Bukti |
|---|---|---|---|---|---|---|
| BB-01 | FR-01.1 | Login dengan kata sandi salah | Ditolak, pesan "NIM/NIDN/username atau kata sandi salah." | Sesuai, NIM tetap terisi, field ditandai `aria-invalid` | Lulus | Peramban; `LoginTest` |
| BB-02 | FR-01.1 | Login admin, dosen, mahasiswa | Diarahkan ke dashboard sesuai peran | Sesuai (/admin, /dosen, /mahasiswa) | Lulus | Peramban; `LoginTest` |
| BB-03 | FR-01.4 | 5 kali salah lalu login benar | Percobaan ke-6 ditunda 60 detik | Sesuai; IP lain tidak ikut terkunci | Lulus | `LoginTest` |
| BB-04 | FR-01.2 | Login akun yang sama di perangkat kedua | Sesi pertama berakhir dan tercatat | Perangkat A dikeluarkan dengan pesan; B tetap masuk; `audit_logs` mencatat `sesi_diganti` dan `sesi_lama_ditolak` | Lulus | Peramban dua profil; `SingleSessionTest` |
| BB-05 | FR-02.3 | Mahasiswa membuka ujian berstatus draf | Tidak terlihat (404) | Sesuai | Lulus | `ExamPagesTest`, `ShuffledQuestionsTest` |
| BB-06 | FR-02.1 | Mulai ujian sebelum/sesudah jadwal | Ditolak dengan pesan | Sesuai, termasuk batas detik tepat | Lulus | `ShuffledQuestionsTest`, `ExamPagesTest` |
| BB-07 | FR-04.10 | Tombol Mulai Ujian sebelum persetujuan dicentang | Nonaktif; server juga menolak | Sesuai | Lulus | Peramban; `ExamPagesTest` |
| BB-08 | FR-03.1, 03.2, 03.5 | Dua mahasiswa membuka ujian yang sama | Urutan soal dan opsi berbeda; opsi "posisi tetap" tidak berpindah | Urutan soal dan opsi berbeda; "Semua jawaban salah" tetap di posisi E pada keduanya | Lulus | Peramban; `ShuffledQuestionsTest` |
| BB-09 | FR-03.3, FR-04.7 | Muat ulang halaman ujian | Urutan dan jawaban tetap | Urutan identik sebelum/sesudah muat ulang; jawaban terpilih tetap | Lulus | Peramban; `ShuffledQuestionsTest` |
| BB-10 | FR-03.4 | Periksa respons API soal | Tidak memuat kunci, ID asli, atau label asli | Sesuai | Lulus | `ShuffledQuestionsTest` |
| BB-11 | FR-04.1, 04.4 | Pindah tab/jendela | Log tercatat < 1 detik dan modal peringatan muncul | Modal "Peringatan Pelanggaran (1/3)"; `blur`+`visibilitychange` satu kejadian dihitung sekali; log kejadian→DB 194–257 ms (dengan OPcache) | Lulus* | Peramban; `ViolationTest` |
| BB-12 | FR-04.2 | Keluar layar penuh (Esc) | Pelanggaran tercatat dan layar penuh diminta ulang | Modal muncul; tombol "Kembali ke Ujian" masuk layar penuh lagi | Lulus* | Peramban |
| BB-13 | FR-04.3 | Klik kanan, salin, potong, tempel, seleksi, Ctrl+C/V/U, Ctrl+Shift+I, F12 | Tidak berefek | Semua dicegah (`defaultPrevented`); Tab tetap berfungsi | Lulus | Peramban |
| BB-14 | FR-04.5 | Pelanggaran ke-1, ke-2, ke-3 (N = 3) | Judul modal "Peringatan Pelanggaran (X/N)" lalu "Peringatan Terakhir (3/3)" | Sesuai, hitungan dari server | Lulus | Peramban; `ViolationTest` |
| BB-15 | FR-04.6, K-1 | Pelanggaran ke-4 (N + 1) | Lembar dibekukan, jawaban tersimpan terkirim | Modal "Ujian Dikunci", input nonaktif, status `terkunci`, jawaban tersimpan ikut dinilai | Lulus | Peramban; `ViolationTest` |
| BB-16 | FR-04.7 | Koneksi terputus saat menjawab | Jawaban tidak hilang | Status "Offline, mencoba lagi", antrean di localStorage, tersimpan setelah koneksi pulih | Lulus* | Peramban |
| BB-17 | FR-04.6, FR-07.1 | Waktu ujian habis (ujian 1 menit) | Kirim otomatis, lembar beku | Timer merah saat ≤ 5 menit; pada 00:00 modal "Waktu Habis", input nonaktif; status `selesai/waktu_habis`; jawaban terakhir dinilai | Lulus | Peramban; `ExamSessionTest` |
| BB-18 | FR-07.2 | Kirim Jawaban | Konfirmasi lalu attempt final | Modal konfirmasi berisi rekap; jawaban esai terakhir tersimpan; 0 pelanggaran palsu saat keluar layar penuh | Lulus | Peramban; `ExamSessionTest` |
| BB-19 | FR-07.3 | Lihat nilai sebelum/sesudah publikasi | Tersembunyi sebelum publikasi | "Belum dipublikasikan" → nilai tampil setelah dosen mempublikasikan | Lulus | Peramban; `ReportTest`, `ExamPagesTest` |
| BB-20 | FR-06.1, 06.2 | Live Monitor saat peserta melanggar | Status dan pelanggaran baru tampil ≤ 10 detik tanpa muat ulang | Muncul pada polling berikutnya (5 detik) | Lulus | Peramban dua sesi; `MonitorTest` |
| BB-21 | FR-06.3 | Koreksi esai: setujui/ubah skor | Skor tersimpan, nilai akhir terhitung | Sesuai; perubahan tercatat di audit | Lulus | Peramban; `EssayCorrectionTest` |
| BB-22 | FR-09.2 | Ekspor Excel rekap | Berkas .xlsx sesuai tampilan | Berkas `rekap-nilai-<ujian>-<waktu>.xlsx` terunduh; isi dibaca balik sama dengan tabel | Lulus** | Peramban; `ReportTest` |
| BB-23 | FR-01.3 | Impor akun CSV dengan baris salah | Baris salah dilaporkan dengan nomor baris, tidak ada yang tersimpan | Sesuai; CSV Excel (titik koma + BOM) diterima | Lulus | Peramban; `UserImportTest` |
| BB-24 | FR-05.1 | Nilai PG | Sama dengan hitungan manual | Skor 6 dari maks 19,5 (nilai 30,77) sesuai hitungan manual | Lulus | `MultipleChoiceScoringTest` |
| BB-25 | FR-05.2, 05.3 | Skor rekomendasi esai | Similarity 0–1, skor = similarity × bobot | 0,6432 × 10 = 6,43 dari layanan NLP nyata | Lulus | `EssayScoringIntegrationTest`, `test_scoring.py` |

\* Kejadian disimulasikan lewat event (lihat *Batasan metode*).
\** Berkas belum dibuka langsung di aplikasi Microsoft Excel; validitas diuji
dengan membaca balik memakai pembaca XLSX OpenSpout.

## Cara mengulang secara manual

1. `php artisan migrate:fresh --seed`, jalankan `php artisan dev` (atau server +
   `php artisan queue:work`), dan layanan NLP.
2. Login sebagai mahasiswa `2301001`/`password` di Chrome desktop, buka UTS
   Pemrograman Web, centang persetujuan, mulai.
3. Lakukan BB-11 sampai BB-18 dengan aksi sungguhan (Alt+Tab, Esc, cabut kabel
   jaringan/matikan Wi-Fi, klik kanan, Ctrl+C), catat hasilnya di kolom
   *Hasil aktual*.
4. Login sebagai dosen `0601018801` di peramban lain untuk BB-20 sampai BB-22.
