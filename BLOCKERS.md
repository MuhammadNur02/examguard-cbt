# BLOCKERS.md — Hambatan dan Hal yang Butuh Keputusan Anda

## B-01 Smart App Control memblokir `php_mbstring.dll` (build PHP 8.4.25 TS)
- **Status:** teratasi dengan alternatif (lihat DECISIONS D-01), tetap perlu Anda ketahui.
- **Yang terjadi:** Windows Smart App Control (mode *enforce*) memblokir
  `ext\php_mbstring.dll` dari build resmi PHP 8.4.25 TS ("An Application Control
  policy has blocked this file"). Ekstensi lain termuat. Anda mungkin melihat
  notifikasi Smart App Control dari kejadian ini.
- **Yang saya lakukan:** tidak mengakali kebijakan. Memakai build resmi PHP 8.4.22
  NTS yang DLL-nya diizinkan kebijakan.
- **Sisa di luar proyek:** winget menyisakan folder
  `%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\`
  yang hanya berisi `php.ini` buatan saya. Tidak saya hapus karena aturan melarang
  menghapus berkas di luar folder proyek; aman dihapus manual.
- **Saran:** bila nanti memperbarui PHP, uji `php -m | findstr mbstring` setelahnya.

## B-02 Perlu keputusan: ganti kata sandi oleh pengguna sendiri
- **Status:** belum dibuat, menunggu keputusan Anda.
- **Konteks:** PRD tidak memuat fitur "ganti kata sandi sendiri". Saat ini kata
  sandi hanya bisa diatur admin (reset menghasilkan kata sandi acak). Mahasiswa
  dan dosen tidak dapat menggantinya sendiri.
- **Saran:** tambahkan halaman "Ganti kata sandi" sederhana (kata sandi lama +
  baru + konfirmasi) bila diinginkan; perkiraan kecil.

## B-03 Folder di OneDrive bisa mendapat atribut Read-only
- **Status:** teratasi sementara, perlu Anda ketahui.
- **Yang terjadi:** OneDrive memberi atribut *ReadOnly* pada folder proyek.
  `is_writable()` PHP di Windows lalu bernilai false untuk `bootstrap/cache`,
  sehingga `composer require/install` gagal pada langkah `package:discover`
  ("bootstrap/cache directory must be present and writable"), padahal menulis
  berkas sebenarnya bisa.
- **Yang saya lakukan:** `attrib -R` pada `bootstrap\cache`, `storage\framework\*`,
  dan `storage\logs` (di dalam proyek).
- **Bila terulang:** jalankan `attrib -R bootstrap\cache` lalu ulangi perintah.
  Saran jangka panjang: simpan proyek di luar folder OneDrive (mis. `C:\dev\`),
  karena `vendor/`, `node_modules/`, dan `.venv/` juga ikut tersinkron.
