# ERD dan Skema Basis Data — ExamGuard CBT

Skema final hasil Task 1.1, diturunkan dari PRD §11. Sumber kebenaran adalah
berkas migration di `database/migrations/`. Tipe ditulis generik (berjalan di
SQLite, MySQL, dan PostgreSQL).

```mermaid
erDiagram
    users ||--o{ exams : "membuat (dosen_id)"
    users ||--o{ exam_attempts : mengerjakan
    users ||--o{ class_students : anggota
    classes ||--o{ class_students : berisi
    classes ||--o{ exam_classes : ditetapkan
    exams ||--o{ exam_classes : untuk
    exams ||--o| exam_access : membatasi
    exams ||--o{ questions : memiliki
    questions ||--o{ options : memiliki
    exams ||--o{ exam_attempts : memiliki
    exam_attempts ||--o{ student_answers : berisi
    questions ||--o{ student_answers : dijawab
    options |o--o{ student_answers : dipilih
    exam_attempts ||--o{ exam_logs : mencatat
    exam_attempts ||--o| exam_results : menghasilkan
    questions ||--o{ similarity_flags : ditandai
    exam_attempts ||--o{ similarity_flags : "attempt_a / attempt_b"
    users |o--o{ audit_logs : pelaku

    users {
        bigint id PK
        string nim_nidn UK "NIM / NIDN / username admin"
        string nama
        string role "admin | dosen | mahasiswa"
        string password "hash bcrypt"
        boolean aktif
        string session_token "single session"
    }
    classes {
        bigint id PK
        string nama UK
        string keterangan
    }
    class_students {
        bigint class_id PK, FK
        bigint user_id PK, FK
    }
    exams {
        bigint id PK
        bigint dosen_id FK
        string judul
        string mata_kuliah
        datetime mulai
        smallint durasi_menit
        tinyint batas_pelanggaran "N"
        boolean acak_soal
        boolean acak_opsi
        smallint pool_size "null = semua"
        string status "draft | published"
    }
    exam_access {
        bigint id PK
        bigint exam_id FK, UK
        string kode_akses
        text ip_allowlist "CIDR per baris"
    }
    exam_classes {
        bigint exam_id PK, FK
        bigint class_id PK, FK
    }
    questions {
        bigint id PK
        bigint exam_id FK
        smallint urutan "nomor asli"
        string tipe "pg | esai"
        text teks
        decimal bobot
        text kunci_esai
        json keywords
    }
    options {
        bigint id PK
        bigint question_id FK
        string label "A-E"
        text teks
        boolean is_correct
        boolean posisi_tetap
    }
    exam_attempts {
        bigint id PK
        bigint exam_id FK
        bigint user_id FK
        int shuffle_seed
        json urutan_soal "question_id sesuai urutan tampil"
        json urutan_opsi "question_id -> option_id[]"
        datetime mulai
        datetime selesai
        string status "berlangsung | selesai | terkunci"
        string alasan_selesai
        smallint jumlah_pelanggaran
        smallint waktu_tambahan "menit"
        string ip
        text user_agent
        datetime terakhir_aktif "heartbeat"
    }
    student_answers {
        bigint id PK
        bigint attempt_id FK
        bigint question_id FK
        bigint option_id FK
        text teks_jawaban
        boolean ragu
        datetime disimpan_pada
        decimal similarity "esai 0-1"
        decimal skor_sistem
        decimal skor_final
        bigint dinilai_oleh FK
        datetime dinilai_pada
    }
    exam_logs {
        bigint id PK
        bigint attempt_id FK
        string jenis
        datetime waktu "milidetik"
        json detail
        boolean dihitung
        boolean dimaafkan
        bigint dimaafkan_oleh FK
        datetime dimaafkan_pada
        text alasan
    }
    exam_results {
        bigint id PK
        bigint attempt_id FK, UK
        decimal skor_pg
        decimal skor_esai_sistem
        decimal skor_esai_final
        decimal skor_maksimal
        decimal nilai_akhir "0-100"
        datetime dipublikasikan_pada
    }
    similarity_flags {
        bigint id PK
        bigint question_id FK
        bigint attempt_a FK
        bigint attempt_b FK
        decimal skor
    }
    audit_logs {
        bigint id PK
        bigint user_id FK
        string aksi
        string subjek_tipe
        bigint subjek_id
        json detail
        string ip
        timestamp created_at
    }
```

Tabel bawaan Laravel yang tetap dipakai: `sessions` (sesi berbasis basis data),
`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` (antrean penilaian
esai massal).

## Penyesuaian terhadap PRD §11

| Tabel | Penyesuaian | Alasan |
|---|---|---|
| `users` | `aktif` | FR-01.3: admin dapat menonaktifkan akun tanpa menghapus data. |
| `users` | tanpa `email` dan tabel `password_reset_tokens` | Login memakai NIM/NIDN/username; reset password dilakukan admin. |
| `exam_classes` | tabel baru | FR-02.6: menetapkan ujian ke kelas. |
| `questions` | `urutan` | Nomor asli dari dosen, dasar sebelum diacak. |
| `exam_attempts` | `urutan_opsi` | PRD §9.2: pemetaan urutan acak opsi ke `option_id` asli disimpan di attempt. |
| `exam_attempts` | `jumlah_pelanggaran`, `alasan_selesai`, `terakhir_aktif` | Penghitung pelanggaran sisi server (PRD §9.1), alasan auto-submit, dan status offline di monitor. |
| `student_answers` | `ragu`, `similarity`, `skor_sistem`, `skor_final`, `dinilai_oleh`, `dinilai_pada` | Penanda ragu (FR-07.1) bertahan saat reload; skor per jawaban untuk koreksi esai side-by-side (FR-06.3). |
| `exam_logs` | `dihitung`, `dimaafkan_pada` | Membedakan insiden yang hanya dicatat (mis. perangkat berganti) dari pelanggaran; waktu pemaafan untuk audit. |
| `exam_results` | `skor_maksimal` | Penyebut nilai akhir skala 0–100. |
| `audit_logs` | tabel baru | Jejak "siapa, kapan, alasan" untuk sesi ganda (FR-01.2), pemaafan pelanggaran (FR-06.5), tambah waktu (FR-06.6), dan perubahan akun. |

## Aturan integritas

- Satu mahasiswa hanya punya satu attempt per ujian (`unique(exam_id, user_id)`).
- Satu jawaban per soal per attempt (`unique(attempt_id, question_id)`).
- Ujian yang sudah punya attempt tidak dapat dihapus (`restrictOnDelete`), sehingga
  jawaban dan log tidak hilang. Akun pengguna dinonaktifkan, bukan dihapus.
- Kunci jawaban (`options.is_correct`, `questions.kunci_esai`, `questions.keywords`)
  disembunyikan dari serialisasi model sebagai lapisan pertahanan tambahan; respons
  untuk mahasiswa juga dibangun eksplisit tanpa field tersebut.

## Rumus nilai

- PG: `skor_sistem = bobot` bila opsi benar, selain itu `0`; `skor_final = skor_sistem`.
- Esai: `skor_sistem = similarity × bobot` (rekomendasi); `skor_final` = keputusan dosen.
- `nilai_akhir = (skor_pg + skor_esai_final) / skor_maksimal × 100`, dibulatkan dua
  desimal, terisi setelah semua esai pada attempt dikonfirmasi dosen.
