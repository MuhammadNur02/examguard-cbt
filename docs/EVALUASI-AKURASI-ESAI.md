# Protokol Uji Akurasi Skor Esai (Task 5.3, PRD §13.2)

Tujuan: mengukur seberapa dekat skor rekomendasi sistem (TF-IDF + Cosine
Similarity × bobot) dengan skor dosen, dengan metrik **MAE** dan **korelasi
Pearson**, serta membandingkan varian **dengan/tanpa stemming** dan **korpus IDF
kunci + jawaban vs kunci saja**.

> Status: alat siap dan teruji, **data belum ada**. Hasil untuk Bab 4 baru bisa
> dihitung setelah dosen menilai 30–50 jawaban esai nyata.

## Langkah

1. **Kumpulkan jawaban.** Jalankan ujian berisi soal esai (minimal 2–3 soal
   dengan kunci jawaban) sampai terkumpul 30–50 jawaban esai yang terisi.
2. **Ekspor dataset anonim.**
   ```bash
   php artisan ujian:ekspor-esai <id-ujian>
   ```
   Hasil: `storage/app/private/evaluasi/esai-ujian-<id>.csv` dengan kolom
   `soal_id, bobot, kunci, jawaban_id, kode_mahasiswa, jawaban, skor_dosen`.
   NIM/nama tidak diekspor; skor sistem juga tidak diekspor.
3. **Dosen menilai secara buta.** Dosen mengisi kolom `skor_dosen` (0 sampai
   bobot) di Excel **sebelum melihat skor rekomendasi sistem**. Simpan sebagai
   CSV (pemisah koma atau titik koma, desimal titik atau koma diterima).
   - Penting: bila dosen menilai di halaman Koreksi Esai yang menampilkan
     rekomendasi, skor dosen dapat "tertarik" ke angka sistem (bias jangkar)
     sehingga korelasi tampak lebih tinggi daripada sebenarnya. Opsi
     `--sertakan-skor-final` hanya untuk keperluan lain, bukan untuk uji ini.
   - Bila memungkinkan, dua penilai menilai sebagian data yang sama untuk
     melihat kesepakatan antarpenilai sebagai pembanding.
4. **Hitung metrik.**
   ```bash
   cd nlp-service
   .venv\Scripts\python evaluasi.py ..\storage\app\private\evaluasi\esai-ujian-<id>.csv --keluar hasil-evaluasi.csv
   ```
   Keluaran: tabel Markdown (siap tempel ke Bab 4) dan `hasil-evaluasi.csv`
   berisi skor sistem per jawaban untuk tiap varian.

## Definisi metrik

- `skor_sistem = similarity × bobot` (dua desimal).
- **MAE** = rata-rata |skor_sistem − skor_dosen| dalam satuan skor.
- **MAE (0–1)** = MAE setelah setiap skor dibagi bobot soalnya; dipakai untuk
  membandingkan antarsoal berbobot berbeda.
- **Pearson r** dihitung pada skor ternormalisasi (dibagi bobot). Target awal
  PRD: r ≥ 0,7. Bila salah satu deret konstan, r "tidak terdefinisi".
- **Korpus IDF:** `kunci_dan_jawaban` = kunci + seluruh jawaban soal itu di
  dataset (K-2, dipakai sistem); `kunci` = hanya kunci (IDF semua term = 1 dan
  kata di luar kunci diabaikan).

## Contoh format (data rekaan)

`nlp-service/contoh/dataset-rekaan.csv` berisi 8 baris **rekaan** untuk mencoba
alat. Angka yang dihasilkan dari berkas ini tidak bermakna dan **tidak boleh**
dipakai sebagai hasil penelitian.

## Pelaporan yang disarankan (Bab 4)

| Varian | n | MAE | MAE (0–1) | Pearson r |
|---|---|---|---|---|
| Stemming, IDF kunci+jawaban (sistem) | | | | |
| Tanpa stemming, IDF kunci+jawaban | | | | |
| Stemming, IDF kunci saja | | | | |
| Tanpa stemming, IDF kunci saja | | | | |

Bahas juga keterbatasan yang diketahui (lihat `nlp-service/README.md`):
negasi hilang karena "tidak" adalah stopword, kesalahan stemmer (mis.
"penyaring" → "nyaring"), serta parafrase/sinonim yang tidak dikenali.
