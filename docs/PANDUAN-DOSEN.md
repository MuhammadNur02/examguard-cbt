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
- **Kelas peserta** (opsional): hanya anggota kelas terpilih yang melihat ujian.
  Bila tidak ada kelas dipilih, ujian terlihat oleh semua mahasiswa.
- **Kode akses** (opsional, 4–20 huruf/angka/tanda hubung): mahasiswa harus
  memasukkan kode ini untuk memulai. Umumkan kode di ruang ujian saat ujian
  dimulai. Kode tidak membedakan huruf besar/kecil; 5 kali salah membuat
  mahasiswa itu menunggu 5 menit. Mahasiswa yang melanjutkan ujiannya (mis.
  setelah peramban tertutup) tidak diminta kode lagi.
- **Soal per mahasiswa** (opsional, pool): isi N agar tiap mahasiswa mendapat N
  soal acak dari seluruh soal ujian. Subset tiap mahasiswa tersimpan di
  attempt-nya; nilai akhir (persen) dihitung dari bobot soal yang ia terima.
  Bila soal esai ikut di-pool, jumlah esai antarmahasiswa bisa berbeda;
  gunakan bobot yang setara bila ingin perbandingan yang adil. Ujian tidak dapat
  diterbitkan bila N melebihi jumlah soal.
- **Batasi jaringan** (opsional): satu alamat IP atau CIDR per baris, mis.
  `10.20.0.0/16` untuk Wi-Fi kampus. Mahasiswa dari jaringan lain tidak dapat
  memulai, dan bila pindah jaringan di tengah ujian, jawabannya tidak tersimpan
  sampai ia kembali ke jaringan yang diizinkan (jawaban tetap tersimpan
  sementara di peramban). Tanyakan rentang IP jaringan kampus ke pengelola
  jaringan; di balik proxy, *trusted proxies* harus diatur (lihat `docs/KEAMANAN.md`).

**Duplikat** (di halaman ujian) membuat salinan draf berisi semua soal, opsi,
pengaturan, dan kelas. Kode akses tidak ikut disalin. Periksa judul dan jadwal
salinan sebelum menerbitkan.

### 2. Menambah soal

Di halaman ujian: **Pilihan ganda** atau **Esai**.

- **PG:** isi opsi A–E berurutan (minimal A dan B), pilih tepat satu kunci,
  bobot. Centang **Posisi tetap** untuk opsi seperti "Semua benar" agar tidak
  ikut diacak.
- **Esai:** teks soal, **kunci jawaban patokan** (wajib, acuan skor
  rekomendasi), **kata kunci wajib** (opsional, dipisah koma; tampil sebagai
  checklist saat koreksi), bobot.

**Impor soal dari Excel/CSV:** di halaman ujian klik **Impor**, unduh
**templat Excel** (atau CSV), isi satu baris per soal, lalu unggah. Kolom:
`tipe` (pg/esai), `teks`, `bobot` (kosong = PG 1, esai 10), `opsi_a`–`opsi_e`,
`kunci` (huruf A–E), `opsi_tetap` (huruf opsi yang tidak diacak, mis. `E`),
`kunci_esai`, `kata_kunci` (dipisah koma). Seluruh berkas diperiksa lebih dulu:
bila ada satu baris salah, tidak ada soal yang disimpan dan setiap kesalahan
ditampilkan dengan nomor barisnya. Soal hasil impor ditambahkan setelah soal
yang sudah ada.

**Pratinjau** (di halaman ujian) membuka tab berisi tampilan persis seperti
yang dilihat satu mahasiswa, termasuk pengacakan; **Acak ulang** menampilkan
urutan mahasiswa lain. Pratinjau tidak membuat attempt, jawaban, atau nilai,
dan pemantauan pelanggaran tidak aktif di sana.

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
- **Perangkat berganti** (alamat IP atau peramban mahasiswa berubah di tengah
  ujian) muncul sebagai insiden "dicatat, tidak dihitung". Detail IP dan
  peramban lama → baru ada di **Rekap Nilai → Detail**. IP dapat berubah wajar
  (mis. ganti Wi-Fi), jadi tinjau bersama konteksnya.
- Layar ujian mahasiswa memuat watermark samar nama/NIM sehingga tangkapan
  layar soal yang beredar dapat ditelusuri.

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
   Daftar **kata kunci wajib** menandai kata kunci yang terpenuhi (✓) dan yang
   tidak ditemukan (✗); checklist ini tidak mengubah rumus skor (PRD K-3).
4. **Koreksi cepat** (opsional): pilih ambang similarity (bawaan 0,80) lalu
   **Terima massal**. Hanya jawaban yang **belum dikonfirmasi** dengan similarity
   ≥ ambang yang skornya disetujui; bila dicentang, semua kata kunci wajib juga
   harus terpenuhi. Pilihan ambang menampilkan jumlah jawaban yang akan
   diterima. Jawaban lain tetap dikoreksi satu per satu, dan skor yang sudah
   Anda tetapkan tidak pernah ditimpa.

**Kemiripan antarmahasiswa:** saat skor rekomendasi dihitung, sistem juga
membandingkan jawaban antarmahasiswa pada soal yang sama. Pasangan dengan
kemiripan ≥ 0,80 tampil di halaman koreksi soal (bagian **Kemiripan
antarmahasiswa**, lencana **Pasangan mirip** di daftar soal, dan lencana
**Mirip dengan …** pada jawaban). Jawaban sangat pendek (< 5 kata bermakna)
tidak dibandingkan. Ini penanda untuk ditinjau, bukan bukti kecurangan: dua
jawaban yang sama-sama mendekati kunci juga bisa mirip.

Nilai akhir selalu keputusan Anda; setiap perubahan skor tercatat di audit.
Esai yang tidak dijawab otomatis bernilai 0.

### 5a. Kelola peserta: maafkan pelanggaran, tambah waktu, buka ulang

Di **Live Monitor** klik **Kelola** pada baris mahasiswa (atau buka
**Rekap Nilai → Detail**). Setiap tindakan wajib diberi alasan dan tercatat di
log audit (siapa, kapan, alasan).

- **Maafkan** satu pelanggaran di tabel log, atau **Maafkan semua pelanggaran**
  (reset ke 0). Hitungan di layar mahasiswa menyesuaikan pada heartbeat
  berikutnya (≤ 15 detik). Memaafkan tidak membuka ujian yang sudah terkunci.
- **Tambah waktu** (1–180 menit) untuk mahasiswa yang masih mengerjakan; timer
  mahasiswa menyesuaikan pada heartbeat berikutnya.
- **Buka ulang** ujian yang sudah terkirim/terkunci: mahasiswa mendapat
  sedikitnya N menit sejak dibuka ulang, juga bila jadwal sudah berakhir.
  Ditolak bila pelanggarannya masih melebihi batas (maafkan dulu) atau nilainya
  sudah dipublikasikan. Bila mahasiswa mengubah jawaban esai, skor esai lama
  jawaban itu dihapus dan perlu dihitung/dikoreksi ulang.

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
