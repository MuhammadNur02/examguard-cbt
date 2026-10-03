# Instrumen SUS dan UAT (Task 5.5, PRD §13.4)

> Status: instrumen dan kalkulator siap; **pengambilan data belum dilakukan**
> (butuh responden mahasiswa dan dosen).

## 1. Etika dan persetujuan

- Jelaskan tujuan penelitian, bahwa partisipasi sukarela, dan bahwa jawaban
  tidak memengaruhi nilai mata kuliah.
- Kumpulkan respons tanpa NIM/nama (pakai kode responden R01, R02, ...).
- Lakukan setelah responden mencoba sistem (sesi ujian percobaan untuk
  mahasiswa; membuat ujian, memantau, dan mengoreksi untuk dosen).

## 2. System Usability Scale (10 butir, skala 1–5)

Skala: 1 = sangat tidak setuju, 2 = tidak setuju, 3 = netral, 4 = setuju,
5 = sangat setuju. Rumusan butir mengikuti adaptasi SUS berbahasa Indonesia
yang umum dipakai; **cocokkan dengan sumber adaptasi yang Anda rujuk**
(mis. Sharfina & Santoso, 2016) sebelum menyebarkan kuesioner.

| No | Pernyataan |
|---|---|
| 1 | Saya berpikir akan menggunakan sistem ini lagi. |
| 2 | Saya merasa sistem ini rumit untuk digunakan. |
| 3 | Saya merasa sistem ini mudah digunakan. |
| 4 | Saya membutuhkan bantuan dari orang lain atau teknisi untuk menggunakan sistem ini. |
| 5 | Saya merasa fitur-fitur sistem ini berjalan dengan semestinya. |
| 6 | Saya merasa ada banyak hal yang tidak konsisten pada sistem ini. |
| 7 | Saya merasa orang lain akan cepat memahami cara menggunakan sistem ini. |
| 8 | Saya merasa sistem ini membingungkan. |
| 9 | Saya merasa tidak ada hambatan dalam menggunakan sistem ini. |
| 10 | Saya perlu membiasakan diri terlebih dahulu sebelum menggunakan sistem ini. |

### Perhitungan

- Butir ganjil: skor − 1. Butir genap: 5 − skor.
- Skor SUS responden = jumlah kontribusi × 2,5 (rentang 0–100).
- Target PRD: rata-rata ≥ 68.

Rekam respons ke CSV (`responden;peran;q1;...;q10`), lalu:

```bash
cd nlp-service
.venv\Scripts\python sus.py respons-sus.csv
```

Keluaran berupa tabel per peran dan keseluruhan (n, rata-rata, simpangan baku,
minimum, maksimum, persentase ≥ 68) serta skor per responden.

## 3. Skenario UAT

Tandai **Berhasil / Gagal** dan catat komentar. Skenario mengikuti alur PRD §8.

### Mahasiswa

| ID | Skenario | Berhasil? | Catatan |
|---|---|---|---|
| UAT-M1 | Login dengan NIM dan kata sandi | | |
| UAT-M2 | Menemukan ujian yang sedang dibuka di beranda | | |
| UAT-M3 | Membaca persetujuan integritas lalu memulai ujian dalam layar penuh | | |
| UAT-M4 | Menjawab soal pilihan ganda dan esai, memakai navigasi nomor | | |
| UAT-M5 | Menandai soal ragu-ragu lalu kembali ke soal itu | | |
| UAT-M6 | Melihat status "Tersimpan otomatis" setelah menjawab | | |
| UAT-M7 | Memahami modal peringatan saat tidak sengaja pindah tab | | |
| UAT-M8 | Mengirim jawaban melalui modal konfirmasi | | |
| UAT-M9 | Melihat nilai di Riwayat Nilai setelah dipublikasikan | | |

### Dosen

| ID | Skenario | Berhasil? | Catatan |
|---|---|---|---|
| UAT-D1 | Membuat ujian baru dengan jadwal, durasi, dan batas pelanggaran | | |
| UAT-D2 | Menambah soal pilihan ganda (dengan kunci) dan esai (kunci + kata kunci) | | |
| UAT-D3 | Menerbitkan ujian dan memastikan mahasiswa melihatnya | | |
| UAT-D4 | Memantau peserta dan pelanggaran di Live Monitor | | |
| UAT-D5 | Menghitung skor rekomendasi esai | | |
| UAT-D6 | Mengoreksi esai berdampingan: setujui atau ubah skor | | |
| UAT-D7 | Membuka rekap nilai dan detail jawaban mahasiswa | | |
| UAT-D8 | Mengekspor rekap ke Excel dan membukanya | | |
| UAT-D9 | Mempublikasikan nilai final | | |

### Admin

| ID | Skenario | Berhasil? | Catatan |
|---|---|---|---|
| UAT-A1 | Menambah akun dosen/mahasiswa | | |
| UAT-A2 | Mengimpor daftar mahasiswa dari CSV (termasuk memperbaiki baris salah) | | |
| UAT-A3 | Mengatur ulang kata sandi dan menonaktifkan akun | | |

## 4. Pelaporan yang disarankan (Bab 4)

- Tabel skor SUS per peran dan keseluruhan, dibandingkan dengan ambang 68.
- Persentase keberhasilan tiap skenario UAT dan ringkasan komentar.
- Keterbatasan: jumlah responden, lingkungan uji (lab/rumah), perangkat.
