# Keamanan Teknis — ExamGuard CBT (Task 5.2)

Ringkasan kontrol keamanan, bukti pengujiannya, dan daftar periksa sebelum
produksi. Bahan untuk Bab 4 KTI (PRD §12 Keamanan).

## Kontrol dan bukti

| Kontrol | Implementasi | Bukti tes |
|---|---|---|
| Autentikasi | Login NIM/NIDN/username, hash bcrypt, regenerasi sesi saat login, logout POST dengan invalidasi sesi | `LoginTest` |
| Rate limiting login | 5 gagal berturut-turut per identitas + IP → penundaan 60 detik | `LoginTest` |
| Single session | Token sesi per login; sesi lama dikeluarkan dan dicatat | `SingleSessionTest` |
| Otorisasi per peran | Middleware `role:` untuk area admin/dosen/mahasiswa | `RouteSecurityTest` (semua rute × semua peran), `RoleAuthorizationTest` |
| Kepemilikan ujian | `ExamPolicy@kelola`: hanya dosen pembuat | `RouteSecurityTest` (semua rute `{exam}` × dosen lain) |
| IDOR mahasiswa | Attempt dicari dari pasangan (ujian, pengguna login); *scoped binding* soal/jawaban/attempt | `ShuffledQuestionsTest`, `EssayCorrectionTest`, `ReportTest` |
| CSRF | Token CSRF + pemeriksaan `Sec-Fetch-Site` (Laravel 13) | `RouteSecurityTest`: 23 rute pengubah data menolak tanpa token (419) |
| Validasi input | FormRequest/validasi di setiap aksi tulis; nomor/opsi jawaban divalidasi terhadap susunan attempt | Tes fitur per modul |
| Kunci jawaban tidak bocor | Payload soal dibangun eksplisit tanpa kunci, ID asli, atau label asli; model menyembunyikan kolom kunci | `ShuffledQuestionsTest`, `SchemaTest`, `ExamPagesTest` |
| Waktu & pelanggaran di server | Timer, penghitung pelanggaran, auto-submit ditentukan server | `ExamSessionTest`, `ViolationTest` |
| Rate limiting ujian | 240 permintaan/menit per mahasiswa (dapat diatur) | `RouteSecurityTest` |
| XSS | Blade meng-escape keluaran; teks soal/jawaban di JS memakai `textContent`; penyorotan kata kunci meng-escape dulu | `HighlightTest`, `EssayCorrectionTest` |
| Injeksi formula | Ekspor Excel memakai sel string; CSV diberi awalan `'` | `ReportTest`, `ExportEssayDatasetTest` |
| Header keamanan | `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, CSP ketat saat `APP_DEBUG=false` | `SecurityHeadersTest`, `RouteSecurityTest`; CSP diuji di peramban (0 pelanggaran) |
| Cache | Area mahasiswa `Cache-Control: no-store` | `ShuffledQuestionsTest` |
| Layanan NLP internal | Bind `127.0.0.1`, token `X-Internal-Token` wajib (gagal tertutup), dokumentasi API dimatikan | `nlp-service/tests` |
| Jejak audit | Sesi ganda, aksi akun, terbit/tarik ujian, publikasi nilai, perubahan skor esai | Tes fitur terkait |
| Permukaan serangan | Rute `storage/{path}` bawaan dimatikan (`serve => false`) | `php artisan route:list` |

## Batasan yang tetap ada

- Proteksi sisi klien (blokir klik kanan, deteksi pindah tab) dapat dilewati
  pengguna mahir; aplikasi web tidak dapat memblokir Alt+Tab. Log pelanggaran
  adalah penanda untuk ditinjau dosen, bukan bukti mutlak.
- Penolakan ponsel/tablet memakai user-agent (server) dan ukuran layar/jenis
  penunjuk (peramban). Keduanya dapat dikelabui, mis. "mode desktop" di
  peramban ponsel; tujuannya mencegah ketidaksengajaan, bukan pengamanan.
- Deteksi perangkat berganti membandingkan IP dan user-agent antarpermintaan.
  Dua orang yang memakai satu akun di jaringan dan peramban yang sama tidak
  terdeteksi dengan cara ini (sesi tunggal per akun tetap berlaku), dan IP
  dapat berubah wajar saat ganti jaringan.
- Watermark nama/NIM hanya membantu menelusuri tangkapan layar; tidak mencegah
  pemotretan layar dengan ponsel.
- Kode akses (FR-02.8) melindungi saat memulai saja; pembatasan IP/jaringan
  kampus (FR-02.9, Could) belum ada.

## Daftar periksa produksi

- [ ] `APP_ENV=production`, `APP_DEBUG=false` (CSP aktif), `APP_KEY` baru.
- [ ] HTTPS dan `SESSION_SECURE_COOKIE=true`.
- [ ] Di balik reverse proxy/load balancer: atur *trusted proxies* agar IP asli
      terbaca (rate limit login dan log IP).
- [ ] OPcache aktif (latensi pencatatan pelanggaran).
- [ ] MySQL/PostgreSQL dengan pengguna DB berhak minimum; backup terjadwal
      (lihat `docs/BACKUP.md`).
- [ ] Queue worker (`php artisan queue:work`) dan penjadwal (`php artisan
      schedule:run` tiap menit via cron) berjalan sebagai layanan.
- [ ] Layanan NLP hanya di `127.0.0.1`/jaringan privat, `NLP_SERVICE_TOKEN`
      acak panjang dan sama di kedua `.env`.
- [ ] Jangan jalankan `db:seed` (data contoh) di produksi; buat admin pertama
      dengan `php artisan examguard:buat-admin <username> "<Nama>"`.
