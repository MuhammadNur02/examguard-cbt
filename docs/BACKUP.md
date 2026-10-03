# Backup dan Pemulihan Basis Data — ExamGuard CBT

PRD §12 meminta backup basis data terjadwal. Prinsip: otomatis, disimpan di
luar server aplikasi, dienkripsi, dan **diuji pemulihannya** secara berkala.

## Apa yang dibackup

| Item | Alasan |
|---|---|
| Basis data | Akun, ujian, soal, jawaban, log pelanggaran, nilai |
| `.env` (simpan terpisah, aman) | Berisi `APP_KEY` dan kredensial; tanpa `APP_KEY` sesi lama tidak terbaca |
| `storage/app/private/` | Berkas ekspor (mis. dataset evaluasi esai) |

Jangan simpan backup di repositori Git.

## Perintah backup

**MySQL**
```bash
mysqldump --single-transaction --routines -u examguard -p examguard > examguard-$(date +%Y%m%d-%H%M).sql
```
Pulihkan: `mysql -u examguard -p examguard < berkas.sql`

**PostgreSQL**
```bash
pg_dump -Fc -U examguard examguard > examguard-$(date +%Y%m%d-%H%M).dump
```
Pulihkan: `pg_restore -U examguard -d examguard --clean berkas.dump`

**SQLite (pengembangan)**
```bash
sqlite3 database/database.sqlite ".backup 'backup/examguard-$(date +%Y%m%d-%H%M).sqlite'"
```
Tanpa `sqlite3`, salin berkas `database/database.sqlite` saat aplikasi tidak
dipakai (bukan saat ujian berlangsung).

## Jadwal

- Harian (mis. pukul 02.00) dengan retensi 30 hari.
- Tambahan: sesaat sebelum dan sesudah setiap ujian besar.

Contoh cron (Linux):
```cron
0 2 * * * /opt/examguard/scripts/backup.sh >> /var/log/examguard-backup.log 2>&1
```
Di Windows gunakan Task Scheduler untuk menjalankan perintah yang sama.

## Uji pemulihan

Minimal sekali per semester: pulihkan backup ke basis data uji, arahkan
`.env` salinan aplikasi ke basis data itu, jalankan `php artisan migrate:status`
(semua migration berstatus *Ran*), login sebagai dosen, dan buka rekap nilai
salah satu ujian.
