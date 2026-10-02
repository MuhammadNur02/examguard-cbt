# Task.md — ExamGuard CBT

Rincian tugas pengembangan per fase. Rujukan kebutuhan ada di [PRD.md](PRD.md); tampilan mengikuti [StyleGuide.md](StyleGuide.md).

Prioritas: **M** Must · **S** Should · **C** Could. Tandai `[x]` bila kriteria selesai (DoD) terpenuhi.

> Urutan fase mengikuti ketergantungan teknis: autentikasi dibangun di Fase 1, bukan Fase 4 seperti di peta fitur, karena semua modul lain bergantung padanya.

---

## Fase 1 — Arsitektur dan Skema Basis Data

- [x] **1.1 (M)** Rancang skema database: `users`, `classes`, `class_students`, `exams`, `exam_access`, `questions`, `options`, `exam_attempts`, `student_answers`, `exam_logs`, `exam_results`, `similarity_flags`. *(PRD §11)*
  - DoD: ERD final, migration berjalan bersih, seeder data contoh.
- [x] **1.2 (M)** Inisialisasi repositori, environment (`.env.example`), Laravel + Tailwind, layanan Python NLP (FastAPI), Docker Compose atau skrip setup.
  - DoD: `README` berisi langkah menjalankan; lint dan test kosong berjalan.
- [x] **1.3 (M)** Autentikasi dan peran Admin/Dosen/Mahasiswa. *(FR-01.1)*
  - DoD: login NIM/NIDN/username; middleware peran; rate limiting login (FR-01.4).
- [x] **1.4 (M)** Single session login. *(FR-01.2, K-8)*
  - DoD: login kedua membatalkan sesi pertama dan tercatat.
- [x] **1.5 (M)** Manajemen akun Admin: daftar, tambah, impor CSV, reset password, nonaktifkan. *(FR-01.3)*
- [ ] **1.6 (S)** Manajemen kelas dan keanggotaan mahasiswa. *(FR-02.6)*

## Fase 2 — Antarmuka Dosen dan Bank Soal

- [x] **2.1 (M)** Form manajemen ujian: judul, jadwal, durasi, batas pelanggaran, opsi pengacakan, publish/unpublish. *(FR-02.1, FR-02.3)*
  - DoD: ujian tidak dapat dimulai di luar jadwal.
- [x] **2.2 (M)** Form soal PG berbobot dan esai berkunci patokan + kata kunci. *(FR-02.2)*
  - DoD: validasi satu kunci PG, kunci esai wajib.
- [ ] **2.3 (S)** Parser impor CSV/Excel + template + laporan baris salah. *(FR-02.4)*
  - DoD: baris salah dilaporkan dengan nomor baris sebelum penyimpanan.
- [x] **2.4 (M)** Endpoint API soal teracak per mahasiswa. *(FR-03.1–FR-03.4)*
  - Fisher-Yates dengan PRNG berseed; `shuffle_seed` disimpan di `exam_attempts`; pemetaan urutan ke ID asli disimpan; kunci jawaban tidak pernah dikirim.
  - DoD: dua akun uji mendapat urutan berbeda; reload tidak mengubah urutan; nilai tetap benar; respons API tidak memuat kunci.
- [ ] **2.5 (S)** Opsi pengacakan per ujian (acak soal/opsi on/off, opsi posisi tetap). *(FR-03.5)*
- [ ] **2.6 (S)** Duplikat ujian, pratinjau sebagai mahasiswa, kode akses ujian. *(FR-02.5, FR-02.7, FR-02.8)*
  - DoD: pratinjau tidak membuat attempt atau nilai.
- [ ] **2.7 (C)** Pembatasan IP/CIDR kampus. *(FR-02.9)*
- [ ] **2.8 (C)** Pool soal N dari M. *(FR-03.6)*

## Fase 3 — Antarmuka Mahasiswa dan Engine Anti-Kecurangan

- [x] **3.1 (M)** Layar pengerjaan: navigasi nomor soal (sesuai urutan acak), penanda ragu, timer mundur sinkron server. *(FR-07.1)*
- [x] **3.2 (M)** Persetujuan integritas dan penguncian fullscreen saat Mulai Ujian. *(FR-04.2, FR-04.10)*
  - DoD: Esc memicu pelanggaran dan permintaan fullscreen ulang.
- [x] **3.3 (M)** Blokir klik kanan, seleksi teks, copy/cut/paste, Ctrl+C/V/U, Ctrl+Shift+I, F12. *(FR-04.3)*
- [x] **3.4 (M)** Detektor `visibilitychange` + `blur` dengan debounce, kirim log ke backend. *(FR-04.1, FR-04.4)*
  - DoD: log tercatat < 1 detik; kejadian ganda dihitung sekali.
- [x] **3.5 (M)** Modal peringatan bertingkat dan auto-submit saat batas terlampaui. *(FR-04.5, FR-04.6, K-1)*
  - DoD: hitungan benar; lembar dibekukan; jawaban tersimpan terakhir terkirim.
- [x] **3.6 (M)** Autosave berkala dan heartbeat; auto-submit memakai jawaban tersimpan. *(FR-04.7)*
  - DoD: putus koneksi tidak menghilangkan jawaban lebih lama dari satu interval autosave.
- [x] **3.7 (M)** Tombol Kirim Jawaban + konfirmasi dan halaman riwayat nilai. *(FR-07.2, FR-07.3)*
- [ ] **3.8 (S)** Watermark nama/NIM, deteksi perangkat berganti, penolakan perangkat mobile. *(FR-04.8, FR-04.9, FR-04.11)*

## Fase 4 — Mesin Penilaian dan Dashboard Dosen

- [x] **4.1 (M)** Penilaian PG otomatis di backend. *(FR-05.1)*
- [ ] **4.2 (M)** Preprocessing esai: case folding, tokenizing, stopword, stemming Sastrawi. *(FR-05.2)*
- [ ] **4.3 (M)** TF-IDF dan Cosine Similarity (IDF dari kunci + seluruh jawaban per soal, K-2); skor = similarity × bobot. *(FR-05.3)*
  - DoD: unit test dengan contoh hitung manual; skor 0,0–1,0.
- [ ] **4.4 (M)** Integrasi Laravel ↔ FastAPI (HTTP internal), antrean untuk penilaian massal.
- [ ] **4.5 (M)** Live Monitor: peserta, status, jumlah pelanggaran, peringatan langsung (polling ≤ 10 detik). *(FR-06.1, FR-06.2)*
- [ ] **4.6 (M)** Koreksi esai side-by-side dengan konfirmasi/edit skor. *(FR-06.3)*
- [ ] **4.7 (S)** Koreksi cepat (terima massal di atas ambang) dan checklist kata kunci. *(FR-06.4, FR-05.4)*
- [ ] **4.8 (S)** Kelola pelanggaran (maafkan/reset + audit log) dan tambah waktu/buka ulang attempt. *(FR-06.5, FR-06.6)*
- [ ] **4.9 (S)** Deteksi kemiripan esai antar mahasiswa. *(FR-05.5)*
- [ ] **4.10 (M)** Laporan: rekap nilai kelas, detail jawaban, ekspor Excel. *(FR-09.1, FR-09.2)*
- [ ] **4.11 (C)** Ekspor PDF, analisis butir soal, publikasi nilai terjadwal, kunci mahasiswa dari monitor. *(FR-09.3, FR-09.4, FR-08.1, FR-06.7)*

## Fase 5 — Pengujian, Pengambilan Data KTI, dan Finalisasi

- [ ] **5.1 (M)** Black-box testing: pindah tab, Esc fullscreen, klik kanan, copy-paste, habis waktu, putus koneksi, login ganda, urutan acak berbeda, reload. *(PRD §13.1)*
- [ ] **5.2 (M)** Keamanan teknis: CSRF, validasi input, otorisasi per peran, kunci tidak bocor di respons, rate limiting.
- [ ] **5.3 (M)** Uji akurasi esai: dataset 30–50 jawaban dinilai manual dosen; hitung MAE dan Pearson; bandingkan dengan/tanpa stemming dan variasi korpus IDF. *(PRD §13.2)*
- [ ] **5.4 (S)** Uji beban 50–100 peserta bersamaan dan catat latensi/error. *(PRD §13.3)*
- [ ] **5.5 (M)** Kuesioner SUS/UAT pada mahasiswa dan dosen untuk Bab 4. *(PRD §13.4)*
- [ ] **5.6 (M)** Dokumentasi: README, panduan dosen, panduan mahasiswa, backup database, pemetaan hasil ke Bab 1–4 KTI.

---

## Ringkasan Cakupan Modul

| Modul peta fitur | Task |
|---|---|
| Layar Ujian Mahasiswa | 3.1–3.8 |
| Kelola Ujian | 2.1, 2.5–2.8 |
| Bank Soal | 2.2–2.4 |
| Penilaian Otomatis | 4.1–4.4, 4.7, 4.9 |
| Pantau Ujian | 4.5, 4.8 |
| Laporan Nilai | 4.6, 4.10, 4.11 |
| Akun Pengguna | 1.3–1.6 |

## Jalur MVP (jika waktu terbatas)

1.1–1.4 → 2.1, 2.2, 2.4 → 3.1–3.7 → 4.1–4.6, 4.10 → 5.1, 5.3, 5.5.
