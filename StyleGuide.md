# StyleGuide — ExamGuard CBT

Panduan visual dan antarmuka. Tema: **Maroon elegan dan putih**, bersih, tenang, dan fokus. Rujukan: [PRD.md](PRD.md), [Task.md](Task.md).

## 1. Prinsip

1. **Tenang dan fokus.** Layar ujian bebas distraksi; warna kuat hanya untuk aksi dan peringatan.
2. **Elegan.** Maroon sebagai aksen berwibawa di atas bidang putih dan netral hangat.
3. **Jelas.** Hierarki kuat, kontras tinggi, label eksplisit.
4. **Konsisten.** Satu set token dipakai di seluruh halaman.

## 2. Warna

### 2.1 Token

| Token | Hex | Penggunaan |
|---|---|---|
| `maroon-900` | `#4A0E1C` | Teks judul di atas putih, header gelap |
| `maroon-800` | `#5E1224` | Hover tombol primer |
| `maroon-700` | `#7A1B2D` | **Primer**: tombol, tautan aktif, sidebar |
| `maroon-600` | `#8E2438` | Tombol primer (alternatif), fokus |
| `maroon-500` | `#A63A4E` | Ikon, aksen sekunder |
| `maroon-200` | `#E7C6CC` | Garis halus beraksen, lencana |
| `maroon-100` | `#F5E6E9` | Latar item terpilih/hover |
| `maroon-50` | `#FBF4F5` | Latar panel lembut |
| `white` | `#FFFFFF` | Latar kartu dan halaman |
| `ivory` | `#FAF8F6` | Latar halaman (netral hangat) |
| `stone-200` | `#E7E2DE` | Garis dan pemisah |
| `stone-500` | `#78716C` | Teks sekunder |
| `stone-700` | `#44403C` | Teks isi |
| `ink` | `#1C1917` | Teks utama |
| `gold-500` | `#B8935A` | Aksen premium (lencana, garis tipis), hemat |
| `gold-100` | `#F4ECDD` | Latar aksen emas lembut |

### 2.2 Warna Status

| Status | Teks/ikon | Latar | Catatan |
|---|---|---|---|
| Sukses | `#1E6B45` | `#E6F4EC` | Selesai, tersimpan |
| Peringatan | `#8A5A00` | `#FFF4DB` | Pelanggaran tingkat 1–2 |
| Bahaya | `#B3261E` | `#FDECEA` | Pelanggaran terakhir, terkunci |
| Info | `#1F4E79` | `#E8F0F8` | Informasi netral |

Karena primer sudah merah, **bahaya** memakai ikon dan label teks selain warna, dan latar bahaya lebih terang agar tidak tertukar dengan tombol primer. Status tidak boleh hanya dibedakan lewat warna.

### 2.3 Kontras (WCAG AA)

| Pasangan | Rasio (dihitung) | Lulus |
|---|---|---|
| `ink` di `white` | 17,5:1 | AA/AAA |
| `stone-700` di `white` | 10,3:1 | AA/AAA |
| `stone-500` di `white` | 4,8:1 | AA (teks biasa) |
| putih di `maroon-700` | 10,4:1 | AA/AAA |
| putih di `maroon-600` | 8,5:1 | AA/AAA |
| `maroon-700` di `white` | 10,4:1 | AA/AAA |
| `#8A5A00` di `#FFF4DB` | 5,4:1 | AA |
| `#B3261E` di `#FDECEA` | 5,7:1 | AA |
| `stone-500` di `ivory` | 4,5:1 | AA (batas, hindari teks < 14 px) |

Rasio dihitung dengan rumus luminansi relatif WCAG 2.x. Verifikasi ulang bila token berubah.

## 3. Tipografi

| Peran | Font | Catatan |
|---|---|---|
| Judul | **Playfair Display** (600/700) | Kesan elegan; dipakai hemat di judul halaman dan kartu utama |
| Isi dan UI | **Inter** (400/500/600) | Terbaca di layar, termasuk angka tabel |
| Angka timer/kode | **JetBrains Mono** (500) | Lebar angka tetap |

Fallback: `Georgia, serif` untuk judul; `system-ui, sans-serif` untuk isi.

| Level | Ukuran / tinggi baris | Berat |
|---|---|---|
| Display | 36 / 44 | 700 |
| H1 | 28 / 36 | 600 |
| H2 | 22 / 30 | 600 |
| H3 | 18 / 26 | 600 |
| Isi | 16 / 26 | 400 |
| Kecil | 14 / 22 | 400–500 |
| Label | 12 / 16 | 600, kapital, letter-spacing 0,04em |

Teks soal ujian minimal **17 px** dengan tinggi baris 1,7 agar nyaman dibaca lama.

## 4. Spasi, Radius, Bayangan

- Skala spasi 4 px: 4, 8, 12, 16, 24, 32, 48, 64.
- Radius: `sm` 6, `md` 10, `lg` 16, `full` 999.
- Bayangan:
  - `shadow-card`: `0 1px 2px rgba(74,14,28,.06), 0 4px 16px rgba(74,14,28,.06)`
  - `shadow-pop`: `0 12px 40px rgba(74,14,28,.18)` (modal)
- Lebar konten: dashboard maks 1280 px; layar ujian maks 960 px terpusat.

## 5. Komponen

### Tombol
| Varian | Gaya |
|---|---|
| Primer | Latar `maroon-700`, teks putih, hover `maroon-800`, radius `md`, tinggi 44 |
| Sekunder | Putih, garis `maroon-700`, teks `maroon-700`, hover `maroon-50` |
| Hantu | Tanpa latar, teks `maroon-700`, hover `maroon-100` |
| Bahaya | Latar `#B3261E`, teks putih, selalu disertai ikon/label jelas |
Fokus: cincin 2 px `maroon-500` dengan offset 2 px. Nonaktif: opasitas 50% dan kursor `not-allowed`.

### Kartu
Latar putih, garis `stone-200`, radius `lg`, `shadow-card`, padding 24. Garis atas 3 px `maroon-700` hanya untuk kartu penting.

### Input
Tinggi 44, garis `stone-200`, radius `md`; fokus garis `maroon-600` + cincin `maroon-100`. Label di atas, pesan galat di bawah dengan ikon.

### Tabel
Header `maroon-50` dengan teks label; baris selang-seling `ivory`; hover `maroon-100`; angka rata kanan.

### Lencana Status
Pil `full` dengan latar dan teks sesuai §2.2: **Aktif**, **Selesai**, **Offline**, **Terkunci**, **Dimaafkan**.

### Sidebar Dosen/Admin
Latar `maroon-900`, teks putih, item aktif `maroon-700` dengan garis kiri `gold-500`. Ikon 20 px.

## 6. Layar Ujian Mahasiswa

Tujuan: bebas distraksi, sinyal jelas.

| Elemen | Aturan |
|---|---|
| Bilah atas | Tinggi 56, latar putih, judul ujian kiri, timer kanan (JetBrains Mono, 20 px). Timer ≤ 5 menit berubah `#B3261E` + ikon jam; ≤ 1 menit berkedip lembut (hormati `prefers-reduced-motion`). |
| Penghitung pelanggaran | Lencana "Pelanggaran X/N" di bilah atas; warna netral → peringatan → bahaya sesuai tingkat. |
| Area soal | Kartu putih lebar 960, teks 17 px, jarak antar-opsi 12. |
| Opsi PG | Kartu opsi penuh lebar, radius `md`, garis `stone-200`; terpilih: latar `maroon-100`, garis `maroon-700`, penanda lingkaran terisi. |
| Esai | Textarea min tinggi 200, penghitung kata kecil di bawah. |
| Navigasi nomor | Panel samping/bawah, kotak 40×40: belum dijawab (putih), terjawab (`maroon-700` putih), ragu (`gold-500`), aktif (garis tebal). Nomor mengikuti **urutan acak milik mahasiswa**, bukan nomor asli. |
| Watermark | Teks nama/NIM `stone-500` opasitas 8%, diagonal berulang, `pointer-events: none`. |
| Status simpan | Teks kecil "Tersimpan otomatis · 12:04:31" dengan ikon; "Menyimpan..." atau "Offline, mencoba lagi" bila ada masalah. |
| Latar | `ivory`; tidak ada iklan, animasi mencolok, atau tautan keluar. |

### Modal Peringatan Bertingkat

| Tingkat | Warna | Judul | Aksi |
|---|---|---|---|
| 1 | Peringatan | "Peringatan Pelanggaran (1/N)" | "Kembali ke Ujian" |
| 2 | Peringatan (lebih tegas) | "Peringatan Pelanggaran (2/N)" | "Kembali ke Ujian" |
| 3 (terakhir) | Bahaya | "Peringatan Terakhir (N/N)" | "Saya Mengerti" |
| Batas terlampaui | Bahaya | "Ujian Dikunci" | "Lihat Hasil Pengiriman" |

Isi modal: apa yang terdeteksi (pindah tab / keluar layar penuh), jumlah tersisa, dan konsekuensi. Modal bersuara opsional dan harus bisa dimatikan sistem bila `prefers-reduced-motion`/preferensi pengguna. Fokus dikunci di dalam modal; tombol utama otomatis terfokus.

### Persetujuan Integritas
Kartu tengah dengan ikon perisai, daftar singkat apa yang dipantau (layar penuh, tab, salin-tempel, IP/perangkat), kotak centang persetujuan, lalu tombol "Mulai Ujian" yang aktif setelah dicentang.

## 7. Dashboard Dosen

- **Live Monitor:** tabel peserta dengan lencana status, kolom pelanggaran (angka + ikon), waktu aktivitas terakhir. Baris yang melampaui separuh batas diberi garis kiri kuning; terkunci, garis kiri merah. Polling tidak boleh membuat tabel berkedip.
- **Koreksi Esai Side-by-Side:** dua kolom sama lebar. Kiri: jawaban mahasiswa (kata kunci cocok disorot `gold-100`). Kanan: kunci dosen. Di bawah: kartu skor rekomendasi (Similarity 0,00–1,00, skor = similarity × bobot), kolom input skor, tombol **Setujui** (primer) dan **Simpan Perubahan** (sekunder). Di layar sempit, kolom menumpuk vertikal.
- **Rekap Nilai:** tabel dengan filter kelas/ujian, tombol ekspor Excel/PDF di kanan atas.
- **Kartu ringkasan:** angka besar (Playfair), label kecil di bawah.

## 8. Ikonografi dan Ilustrasi

Ikon garis (stroke 1,75) satu keluarga, misalnya Lucide. Ukuran 16/20/24. Tanpa ilustrasi berwarna ramai; boleh pola garis halus `maroon-100` di halaman login.

## 9. Gerak

Transisi 150–200 ms `ease-out`. Modal: fade + naik 8 px. Hormati `prefers-reduced-motion`: matikan animasi kedip dan geser.

## 10. Aksesibilitas

- Kontras AA minimum (§2.3); status tidak hanya lewat warna.
- Semua kontrol dapat dioperasikan keyboard di area dosen/admin dengan fokus terlihat. Di layar ujian, pintasan yang diblokir hanya yang dilarang pada FR-04.3; Tab dan navigasi dasar tetap berfungsi.
- Modal memakai `role="alertdialog"`, label, dan perangkap fokus.
- Ukuran target sentuh/klik minimal 40 px.
- Teks alternatif untuk ikon bermakna.

## 11. Responsif

| Breakpoint | Perilaku |
|---|---|
| < 768 px | Dashboard dosen ramah baca dan tabel bisa digulir; **layar ujian ditolak** dengan pesan "Gunakan laptop/PC untuk mengerjakan ujian". |
| 768–1279 px | Sidebar bisa dilipat. |
| ≥ 1280 px | Tata letak penuh. |

## 12. Konfigurasi Tailwind

```js
// tailwind.config.js
module.exports = {
  content: ['./resources/**/*.blade.php', './resources/**/*.js'],
  theme: {
    extend: {
      colors: {
        maroon: {
          50: '#FBF4F5', 100: '#F5E6E9', 200: '#E7C6CC', 500: '#A63A4E',
          600: '#8E2438', 700: '#7A1B2D', 800: '#5E1224', 900: '#4A0E1C',
        },
        ivory: '#FAF8F6',
        gold: { 100: '#F4ECDD', 500: '#B8935A' },
        status: {
          success: '#1E6B45', 'success-bg': '#E6F4EC',
          warning: '#8A5A00', 'warning-bg': '#FFF4DB',
          danger: '#B3261E', 'danger-bg': '#FDECEA',
          info: '#1F4E79', 'info-bg': '#E8F0F8',
        },
      },
      fontFamily: {
        display: ['"Playfair Display"', 'Georgia', 'serif'],
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
      },
      borderRadius: { sm: '6px', md: '10px', lg: '16px' },
      boxShadow: {
        card: '0 1px 2px rgba(74,14,28,.06), 0 4px 16px rgba(74,14,28,.06)',
        pop: '0 12px 40px rgba(74,14,28,.18)',
      },
    },
  },
};
```

## 13. Daftar Periksa Sebelum Merilis Halaman

- [ ] Hanya memakai token di atas (tanpa hex acak).
- [ ] Kontras teks AA, status punya ikon/label.
- [ ] Fokus keyboard terlihat; modal menahan fokus.
- [ ] Layar ujian: tanpa elemen pengalih, ada status simpan dan penghitung pelanggaran.
- [ ] Nomor soal mengikuti urutan acak mahasiswa.
- [ ] Tampil benar di 1280 px dan 768 px; layar ujian ditolak di mobile.
