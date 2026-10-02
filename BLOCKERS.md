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
