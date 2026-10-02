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

(sedang dikerjakan)
