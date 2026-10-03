# Panduan Dosen dan Admin — ExamGuard CBT

## Bagian A — Dosen

Login dengan **NIDN**. Menu **Ujian Saya** menampilkan semua ujian Anda.
Anda hanya dapat melihat dan mengelola ujian buatan Anda sendiri.

### 1. Membuat ujian

**Buat Ujian** → isi judul, mata kuliah, waktu mulai (WIB), durasi, batas
pelanggaran N, dan opsi pengacakan (soal, opsi PG). Ujian tersimpan sebagai
**draf** dan belum terlihat mahasiswa.

- Jadwal berlaku serentak: ujian dapat dimulai dari waktu mulai sampai
  waktu mulai + durasi; peserta yang terlambat mendapat sisa waktu.
- Batas N: peringatan 1..N ditampilkan, pelanggaran ke-(N+1) mengunci ujian.

### 2. Menambah soal

Di halaman ujian: **Pilihan ganda** atau **Esai**.

- **PG:** isi opsi A–E berurutan (minimal A dan B), pilih tepat satu kunci,
  bobot. Centang **Posisi tetap** untuk opsi seperti "Semua benar" agar tidak
  ikut diacak.
- **Esai:** teks soal, **kunci jawaban patokan** (wajib, acuan skor
  rekomendasi), **kata kunci wajib** (opsional, dipisah koma; tampil sebagai
  checklist saat koreksi), bobot.

Soal hanya dapat diubah selama ujian berstatus draf.

### 3. Menerbitkan

**Terbitkan** ditolak bila belum ada soal, ada PG tanpa tepat satu kunci, atau
esai tanpa kunci. Ujian terbit dapat **ditarik ke draf** selama belum ada
mahasiswa yang memulai. Setelah ada yang memulai, ujian terkunci dari perubahan.

### 4. Live Monitor

Saat ujian berjalan, buka **Live Monitor** (diperbarui tiap 5 detik):

- Status: **Aktif**, **Offline** (tidak ada sinyal > 45 detik), **Selesai**,
  **Terkunci**.
- Kolom pelanggaran X/N; baris bergaris kuning bila melewati separuh batas,
  merah bila terkunci.
- **Pelanggaran terbaru** muncul tanpa memuat ulang halaman.

Ingat: aplikasi web hanya mendeteksi dan mencatat; log adalah penanda untuk
ditinjau, bukan bukti mutlak kecurangan.

### 5. Koreksi esai

1. Setelah semua peserta selesai, buka **Koreksi Esai** → **Hitung skor
   rekomendasi**. Layanan NLP dan queue worker harus berjalan. Skor
   rekomendasi = similarity (TF-IDF + Cosine) × bobot.
2. Klik **Koreksi** pada soal. Jawaban mahasiswa (kata kunci disorot) tampil
   berdampingan dengan kunci Anda, beserta similarity dan skor rekomendasi.
3. **Setujui** untuk memakai rekomendasi, atau isi skor lalu **Simpan
   Perubahan**. Halaman berpindah ke jawaban berikutnya yang belum dikonfirmasi.

Nilai akhir selalu keputusan Anda; setiap perubahan skor tercatat di audit.
Esai yang tidak dijawab otomatis bernilai 0.

### 6. Rekap dan publikasi

**Rekap Nilai** menampilkan skor PG, skor esai final, nilai akhir (0–100),
dan pelanggaran per mahasiswa; **Detail** memuat jawaban dan log pelanggaran.
**Ekspor Excel** mengunduh rekap `.xlsx`. **Publikasikan nilai final**
membuat nilai yang sudah final terlihat oleh mahasiswa; nilai yang masih
menunggu koreksi tidak ikut.

## Bagian B — Admin

Login dengan username admin. Menu **Akun Pengguna**:

- **Tambah Akun:** NIM/NIDN/username, nama, peran. Kosongkan kata sandi agar
  sistem membuat kata sandi acak yang **ditampilkan sekali**.
- **Impor CSV:** unduh templat, isi `nim_nidn,nama,peran,kata_sandi` (CSV dari
  Excel dengan titik koma diterima). Bila ada baris salah, tidak ada yang
  disimpan dan nomor barisnya dilaporkan. Unduh CSV kredensial setelah impor.
- **Reset sandi** (sesi aktif ikut berakhir), **Reset sesi**, **Nonaktifkan /
  Aktifkan**. Akun tidak dihapus agar riwayat ujian tetap utuh.

Admin pertama di server produksi dibuat dari terminal:

```bash
php artisan examguard:buat-admin <username> "<Nama Admin>"
```
