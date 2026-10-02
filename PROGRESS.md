# PROGRESS.md — Kemajuan Pengembangan

Ringkasan per fase: apa yang selesai, apa yang diuji dan hasilnya, serta cara
menjalankan dan memverifikasi. Detail keputusan ada di [DECISIONS.md](DECISIONS.md),
hambatan di [BLOCKERS.md](BLOCKERS.md).

## Lingkungan di mesin ini

| Alat | Versi | Lokasi |
|---|---|---|
| PHP | 8.4.22 NTS | `%LOCALAPPDATA%\Programs\PHP\php-8.4.22-nts\php.exe` (tidak di PATH) |
| Composer | 2.10.3 | `%LOCALAPPDATA%\Programs\Composer\composer.phar` |
| Node / npm | 24.18 / 11.16 | sudah ada sebelumnya |
| Python (NLP) | 3.13.15 x64 | `%LOCALAPPDATA%\Programs\Python\Python313\` (virtualenv: `nlp-service\.venv`) |
| Basis data | SQLite | `database\database.sqlite` |

Agar perintah `php` dan `composer` di bawah berjalan di PowerShell, jalankan
sekali per terminal:

```powershell
$env:Path = "$env:LOCALAPPDATA\Programs\PHP\php-8.4.22-nts;$env:Path"
function composer { php "$env:LOCALAPPDATA\Programs\Composer\composer.phar" @args }
```

## Fase 1 — Arsitektur dan Skema Basis Data

| Task | Status | Bukti |
|---|---|---|
| 1.1 Skema DB | Selesai | `tests/Feature/Database/SchemaTest.php` lulus; `migrate:fresh --seed`, `migrate:rollback`, `migrate` bersih di SQLite; ERD di `docs/ERD.md` |
| 1.2 Inisialisasi | Selesai | Clone bersih: `composer setup` + `db:seed` + `php artisan test` (7/7) + Pint lulus; layanan NLP dari nol: `pytest` (9/9) + ruff lulus |
| 1.3 Autentikasi & peran | Selesai | `LoginTest`, `RoleAuthorizationTest`; dicek di peramban headless (login per peran, pesan galat, logout, 403) |
| 1.4 Single session | Selesai | `SingleSessionTest`; dua profil peramban: perangkat lama dikeluarkan, perangkat baru tetap masuk, keduanya tercatat di `audit_logs` |
| 1.5 Manajemen akun admin | Selesai | `UserManagementTest`, `UserImportTest`, tes unit CSV; impor CSV salah/benar dan login akun hasil impor dicek di peramban |
| 1.6 Kelas (S) | Ditunda | Dikerjakan setelah jalur MVP (aturan: MVP dulu, lalu Should) |

Hasil tes akhir Fase 1: **PHPUnit 86/86 lulus**, Pint lulus, **pytest 9/9
lulus**, ruff lulus, `npm run build` berhasil.

Catatan 1.2: tidak ada Docker di mesin ini, jadi dipakai skrip setup
(`composer setup`, bawaan Laravel 13) dan langkah manual di README. Docker
Compose belum dibuat karena tidak bisa diuji di sini.

### Tinjauan kritis Fase 1
- Dicek: output tak ter-escape (hanya SVG ikon dari repo), SQL mentah (tidak
  ada), rahasia di repo (tidak ada), `.env` tidak ter-track, otorisasi lintas
  peran (diuji untuk semua area dan semua aksi admin), kunci jawaban
  disembunyikan dari serialisasi model.
- Diperbaiki: header keamanan dasar (`X-Frame-Options: DENY`, `nosniff`,
  Referrer-Policy, Permissions-Policy) dan pencegahan injeksi formula pada CSV
  kredensial yang diunduh.

### Catatan untuk produksi (belum dikerjakan, dicatat agar tidak lupa)
- Di balik reverse proxy/load balancer, atur *trusted proxies* agar IP asli
  terbaca; tanpa itu rate limit login dan log IP memakai IP proxy.
- Pakai HTTPS dan `SESSION_SECURE_COOKIE=true`.
- Layanan NLP hanya di `127.0.0.1` atau jaringan privat, dengan token terisi.

## Fase 2 — Antarmuka Dosen dan Bank Soal (jalur MVP)

| Task | Status | Bukti |
|---|---|---|
| 2.1 Kelola ujian | Selesai | `ExamManagementTest` (validasi, kepemilikan 8 aksi × 3 peran, aturan terbit, penguncian setelah dikerjakan); DoD jadwal di `ShuffledQuestionsTest` |
| 2.2 Form soal PG/esai | Selesai | `QuestionManagementTest`; alur tambah soal + galat kunci dicek di peramban |
| 2.4 API soal teracak | Selesai | `FisherYatesTest` (termasuk uji keseragaman), `ShuffledQuestionsTest` (seed deterministik) |
| 2.3, 2.5–2.8 | Belum | Should/Could, dikerjakan setelah jalur MVP |

Catatan: centang "posisi tetap" untuk opsi sudah ada di form dan dihormati
pengacakan (teruji), tetapi Task 2.5 baru akan dicentang saat dikerjakan utuh.

### Tinjauan kritis Fase 2
- Dicek: kunci tidak ada di respons mahasiswa (diuji, termasuk ID dan label asli),
  attempt selalu dicari dari pasangan (ujian, pengguna login), soal memakai
  scoped binding, `status` ujian tidak bisa dikirim lewat form.
- Diperbaiki: semua respons area mahasiswa kini `Cache-Control: no-store` agar
  soal tidak tertinggal di cache komputer lab bersama.
- Belum ada: pembatasan ujian per kelas (FR-02.6, Should). Saat ini semua
  mahasiswa dapat memulai ujian terbit mana pun dalam jadwal.

## Cara menjalankan (ringkas)

```powershell
php artisan dev                  # web + queue + Vite, http://localhost:8000
php artisan db:seed              # data contoh (akun di README)
cd nlp-service; .venv\Scripts\uvicorn app.main:app --host 127.0.0.1 --port 8001 --env-file .env
```

## Perintah verifikasi

```powershell
php artisan test
vendor\bin\pint --test
cd nlp-service; .venv\Scripts\python -m pytest; .venv\Scripts\ruff check .
```
