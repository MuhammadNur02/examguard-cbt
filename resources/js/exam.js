/**
 * Layar ujian ExamGuard CBT.
 *
 * Server adalah sumber kebenaran untuk waktu, jumlah pelanggaran, dan status
 * attempt (PRD §9.1). Skrip ini: menampilkan soal, autosave + heartbeat,
 * MENDETEKSI dan melaporkan pindah tab / keluar layar penuh (aplikasi web tidak
 * dapat memblokir Alt+Tab), serta menonaktifkan klik kanan, salin/tempel, dan
 * seleksi teks di halaman ini (FR-04.3).
 */

const cfg = JSON.parse(document.getElementById('konfigurasi-ujian').textContent);
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const $ = (id) => document.getElementById(id);

const keadaan = {
    soal: [],
    aktif: 0,
    status: 'berlangsung',
    batas: 0,
    pelanggaran: 0,
    batasWaktu: null, // performance.now() saat waktu habis, disinkronkan dari server
    dipantau: false, // pemantauan aktif setelah masuk layar penuh
    beku: false,
    mengakhiri: false,
    terakhirLapor: 0,
    antrean: new Map(), // nomor -> perubahan jawaban yang belum tersimpan
    versi: 0,
    mengirim: false,
    jedaUlang: 2000,
    offline: false,
    tersimpanPada: null,
    antreanPelanggaran: [],
};

class GalatSesi extends Error {}
class GalatStatus extends Error {
    constructor(data) {
        super(data.message || 'Ujian tidak dapat dilanjutkan.');
        this.data = data;
    }
}

async function kirim(url, data = null, { metode = 'POST', keepalive = false } = {}) {
    const respons = await fetch(url, {
        method: metode,
        credentials: 'same-origin',
        keepalive,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: data === null ? undefined : JSON.stringify(data),
    });
    const isi = await respons.json().catch(() => ({}));

    if (respons.status === 401 || respons.status === 419) {
        throw new GalatSesi(isi.message || 'Sesi Anda berakhir. Silakan login kembali.');
    }
    if (respons.status === 409) {
        throw new GalatStatus(isi);
    }
    if (!respons.ok) {
        const galat = new Error(isi.message || `HTTP ${respons.status}`);
        galat.status = respons.status;
        galat.kode = isi.kode;
        throw galat;
    }

    return isi;
}

function el(tag, kelas = '', atribut = {}) {
    const node = document.createElement(tag);
    if (kelas) node.className = kelas;
    for (const [nama, nilai] of Object.entries(atribut)) {
        if (nilai !== null && nilai !== undefined) node.setAttribute(nama, nilai);
    }
    return node;
}

function ikon(nama) {
    return $(`ikon-${nama}`).content.cloneNode(true);
}

/* ---------- Status dari server ---------- */

function terapkanStatus(data) {
    const attempt = data?.attempt;
    if (!attempt) return;

    keadaan.status = attempt.status;
    keadaan.batas = attempt.batas_pelanggaran;
    keadaan.pelanggaran = attempt.jumlah_pelanggaran;
    setelSisa(attempt.sisa_detik);
    tampilkanLencana();

    if (attempt.status !== 'berlangsung') {
        bekukan(attempt.status, attempt.alasan_selesai, data.message);
    }
}

function tampilkanLencana() {
    const lencana = $('lencana-pelanggaran');
    const { pelanggaran: x, batas: n } = keadaan;
    const nada = x === 0 ? 'badge-neutral' : x >= n ? 'badge-danger' : 'badge-warning';
    lencana.className = `badge ${nada}`;
    lencana.textContent = `Pelanggaran ${x}/${n}`;
}

/* ---------- Timer (sinkron server) ---------- */

function setelSisa(detik) {
    keadaan.batasWaktu = performance.now() + detik * 1000;
    perbaruiTimer();
}

function sisaDetik() {
    if (keadaan.batasWaktu === null) return null;
    return Math.max(0, Math.ceil((keadaan.batasWaktu - performance.now()) / 1000));
}

function perbaruiTimer() {
    const sisa = sisaDetik();
    if (sisa === null) return;

    const jam = Math.floor(sisa / 3600);
    const menit = Math.floor((sisa % 3600) / 60);
    const detik = sisa % 60;
    const dua = (angka) => String(angka).padStart(2, '0');
    $('timer-teks').textContent = `${jam > 0 ? `${jam}:` : ''}${dua(menit)}:${dua(detik)}`;

    const kritis = sisa <= 300;
    $('timer').classList.toggle('text-status-danger', kritis);
    $('timer').classList.toggle('text-ink', !kritis);
    $('timer-ikon').classList.toggle('hidden', !kritis);
    $('timer-teks').classList.toggle('timer-kedip', sisa <= 60 && sisa > 0 && !keadaan.beku);

    if (sisa === 0 && keadaan.status === 'berlangsung' && !keadaan.beku) {
        waktuHabis();
    }
}

/* ---------- Tampilan soal ---------- */

function terjawab(soal) {
    return soal.tipe === 'pg' ? soal.jawaban !== null : (soal.jawaban ?? '').trim() !== '';
}

function render() {
    const soal = keadaan.soal[keadaan.aktif];
    if (!soal) return;
    $('area-soal').replaceChildren(kartuSoal(soal));
    renderNavigasi();
}

function kartuSoal(soal) {
    const kartu = el('article', 'card', { 'aria-labelledby': 'judul-soal' });

    const kepala = el('div', 'flex flex-wrap items-center justify-between gap-3');
    const judul = el('h2', 'text-h3 font-semibold text-ink', { id: 'judul-soal' });
    judul.textContent = `Soal ${soal.nomor} `;
    const dari = el('span', 'font-normal text-stone-500');
    dari.textContent = `dari ${keadaan.soal.length}`;
    judul.append(dari);

    const kanan = el('div', 'flex items-center gap-4');
    const bobot = el('span', 'badge badge-neutral');
    bobot.textContent = `Bobot ${String(soal.bobot).replace('.', ',')}`;
    const labelRagu = el('label', 'flex cursor-pointer items-center gap-2 text-small font-medium text-ink');
    const ragu = el('input', 'size-4 accent-maroon-700', { type: 'checkbox' });
    ragu.checked = soal.ragu;
    ragu.addEventListener('change', () => {
        soal.ragu = ragu.checked;
        antreJawaban(soal.nomor, { ragu: soal.ragu });
        kirimAntrean();
        renderNavigasi();
    });
    labelRagu.append(ragu, 'Ragu-ragu');
    kanan.append(bobot, labelRagu);
    kepala.append(judul, kanan);

    const teks = el('p', 'mt-4 whitespace-pre-line text-question text-ink');
    teks.textContent = soal.teks;

    kartu.append(kepala, teks, soal.tipe === 'pg' ? pilihanGanda(soal) : esai(soal), tombolPindah());
    return kartu;
}

function pilihanGanda(soal) {
    const kelompok = el('fieldset', 'mt-6 space-y-3');
    const legenda = el('legend', 'sr-only');
    legenda.textContent = 'Pilihan jawaban';
    kelompok.append(legenda);

    soal.opsi.forEach((opsi, indeks) => {
        const label = el(
            'label',
            'group flex cursor-pointer items-start gap-3 rounded-md border border-stone-200 bg-white px-4 py-3 text-question text-ink transition duration-150 ease-out hover:bg-maroon-50 has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-100 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-maroon-500 has-[:focus-visible]:ring-offset-2',
        );
        const input = el('input', 'sr-only', { type: 'radio', name: `opsi-${soal.nomor}`, value: String(indeks) });
        input.checked = soal.jawaban === indeks;
        input.addEventListener('change', () => {
            soal.jawaban = indeks;
            antreJawaban(soal.nomor, { opsi: indeks });
            kirimAntrean();
            renderNavigasi();
        });

        const lingkaran = el('span', 'mt-1 flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-stone-500 group-has-[:checked]:border-maroon-700');
        lingkaran.append(el('span', 'size-2.5 rounded-full bg-maroon-700 opacity-0 group-has-[:checked]:opacity-100'));
        const huruf = el('span', 'font-semibold');
        huruf.textContent = `${opsi.huruf}.`;
        const isi = el('span', 'flex-1');
        isi.textContent = opsi.teks;

        label.append(input, lingkaran, huruf, isi);
        kelompok.append(label);
    });

    if (soal.jawaban !== null) {
        const kosongkan = el('button', 'btn btn-ghost btn-sm', { type: 'button' });
        kosongkan.textContent = 'Kosongkan pilihan';
        kosongkan.addEventListener('click', () => {
            soal.jawaban = null;
            antreJawaban(soal.nomor, { opsi: null });
            kirimAntrean();
            render();
        });
        kelompok.append(kosongkan);
    }

    return kelompok;
}

function esai(soal) {
    const wadah = el('div', 'mt-6');
    const label = el('label', 'form-label', { for: 'jawaban-esai' });
    label.textContent = 'Jawaban Anda';
    const area = el('textarea', 'form-input min-h-[200px] select-text text-question', { id: 'jawaban-esai', maxlength: '20000' });
    area.value = soal.jawaban ?? '';
    const hitung = el('p', 'mt-1.5 text-small text-stone-500', { 'aria-live': 'polite' });
    const perbaruiHitung = () => {
        const kata = area.value.trim() ? area.value.trim().split(/\s+/).length : 0;
        hitung.textContent = `${kata} kata`;
    };
    perbaruiHitung();

    let jeda;
    area.addEventListener('input', () => {
        soal.jawaban = area.value;
        perbaruiHitung();
        antreJawaban(soal.nomor, { teks: area.value });
        clearTimeout(jeda);
        jeda = setTimeout(kirimAntrean, 1500);
        renderNavigasi();
    });

    wadah.append(label, area, hitung);
    return wadah;
}

function tombolPindah() {
    const baris = el('div', 'mt-8 flex justify-between gap-3');
    const sebelum = el('button', 'btn btn-secondary', { type: 'button' });
    sebelum.textContent = '‹ Sebelumnya';
    sebelum.disabled = keadaan.aktif === 0;
    sebelum.addEventListener('click', () => pindahKe(keadaan.aktif - 1));
    const berikut = el('button', 'btn btn-primary', { type: 'button' });
    berikut.textContent = 'Berikutnya ›';
    berikut.disabled = keadaan.aktif === keadaan.soal.length - 1;
    berikut.addEventListener('click', () => pindahKe(keadaan.aktif + 1));
    baris.append(sebelum, berikut);
    return baris;
}

function renderNavigasi() {
    const dasar =
        'size-10 rounded-sm border text-small font-semibold transition duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-maroon-500 focus-visible:ring-offset-2';
    $('navigasi').replaceChildren(
        ...keadaan.soal.map((soal, indeks) => {
            const sudah = terjawab(soal);
            const warna = soal.ragu
                ? 'border-gold-500 bg-gold-500 text-ink'
                : sudah
                  ? 'border-maroon-700 bg-maroon-700 text-white'
                  : 'border-stone-200 bg-white text-ink hover:bg-maroon-50';
            const aktif = indeks === keadaan.aktif ? 'ring-2 ring-maroon-900 ring-offset-2' : '';
            const tombol = el('button', `${dasar} ${warna} ${aktif}`, {
                type: 'button',
                'aria-label': `Soal ${soal.nomor}, ${sudah ? 'terjawab' : 'belum dijawab'}${soal.ragu ? ', ragu-ragu' : ''}`,
                'aria-current': indeks === keadaan.aktif ? 'step' : null,
            });
            tombol.textContent = soal.nomor;
            tombol.disabled = keadaan.beku;
            tombol.addEventListener('click', () => pindahKe(indeks));
            return tombol;
        }),
    );
}

function pindahKe(indeks) {
    if (indeks < 0 || indeks >= keadaan.soal.length) return;
    keadaan.aktif = indeks;
    render();
    $('judul-soal')?.focus?.();
}

/* ---------- Autosave (FR-04.7) ---------- */

function antreJawaban(nomor, perubahan) {
    const lama = keadaan.antrean.get(nomor) ?? { nomor };
    keadaan.antrean.set(nomor, { ...lama, ...perubahan, versi: ++keadaan.versi });
    simpanAntreanLokal();
    tampilkanStatusSimpan('menyimpan');
}

function simpanAntreanLokal() {
    try {
        if (keadaan.antrean.size === 0) {
            localStorage.removeItem(cfg.kunciPenyimpanan);
        } else {
            localStorage.setItem(cfg.kunciPenyimpanan, JSON.stringify([...keadaan.antrean.values()]));
        }
    } catch {
        // Penyimpanan lokal tidak tersedia: antrean tetap di memori.
    }
}

function pulihkanAntreanLokal() {
    let tertunda = [];
    try {
        tertunda = JSON.parse(localStorage.getItem(cfg.kunciPenyimpanan) || '[]');
    } catch {
        tertunda = [];
    }

    for (const item of tertunda) {
        const soal = keadaan.soal.find((s) => s.nomor === item.nomor);
        if (!soal) continue;
        if ('opsi' in item) soal.jawaban = item.opsi;
        if ('teks' in item) soal.jawaban = item.teks;
        if ('ragu' in item) soal.ragu = item.ragu;
        const { versi, ...perubahan } = item;
        antreJawaban(item.nomor, perubahan);
    }
}

async function kirimAntrean() {
    if (keadaan.mengirim || keadaan.antrean.size === 0 || keadaan.beku) return;

    keadaan.mengirim = true;
    const paket = [...keadaan.antrean.values()];
    let lanjut = false;

    try {
        const data = await kirim(cfg.url.jawaban, { jawaban: paket.map(({ versi, ...item }) => item) });
        for (const item of paket) {
            if (keadaan.antrean.get(item.nomor)?.versi === item.versi) keadaan.antrean.delete(item.nomor);
        }
        simpanAntreanLokal();
        keadaan.offline = false;
        keadaan.jedaUlang = 2000;
        keadaan.tersimpanPada = new Date(data.tersimpan_pada);
        terapkanStatus(data);
        lanjut = keadaan.antrean.size > 0;
        tampilkanStatusSimpan(lanjut ? 'menyimpan' : 'tersimpan');
    } catch (galat) {
        if (galat instanceof GalatStatus) {
            terapkanStatus(galat.data);
        } else if (galat instanceof GalatSesi) {
            sesiBerakhir(galat.message);
        } else if (galat.status === 422) {
            // Data tidak valid (seharusnya tidak terjadi): buang agar antrean tidak macet.
            paket.forEach((item) => keadaan.antrean.delete(item.nomor));
            simpanAntreanLokal();
            tampilkanStatusSimpan('galat');
        } else {
            // Jawaban tetap di antrean lokal dan dicoba lagi (juga bila jaringan ditolak, FR-02.9).
            keadaan.offline = true;
            tampilkanStatusSimpan(galat.kode === 'jaringan_ditolak' ? 'jaringan' : 'offline');
            setTimeout(kirimAntrean, keadaan.jedaUlang);
            keadaan.jedaUlang = Math.min(keadaan.jedaUlang * 2, 30000);
        }
    } finally {
        keadaan.mengirim = false;
    }

    if (lanjut) kirimAntrean();
}

/** Coba kirim semua jawaban tertunda sebelum attempt difinalkan. */
async function tuntaskanAntrean() {
    for (let percobaan = 0; percobaan < 3 && keadaan.antrean.size > 0; percobaan++) {
        while (keadaan.mengirim) await new Promise((selesai) => setTimeout(selesai, 100));
        await kirimAntrean();
    }
}

/** Format jam HH:MM:SS (StyleGuide §6: "Tersimpan otomatis · 12:04:31"). */
function jam(tanggal) {
    return [tanggal.getHours(), tanggal.getMinutes(), tanggal.getSeconds()].map((n) => String(n).padStart(2, '0')).join(':');
}

function tampilkanStatusSimpan(kondisi) {
    const wadah = $('status-simpan');
    const teks = {
        menyimpan: 'Menyimpan…',
        offline: 'Offline, mencoba lagi',
        jaringan: 'Jaringan tidak diizinkan, sambungkan ke jaringan kampus',
        galat: 'Sebagian jawaban gagal disimpan',
        tersimpan: keadaan.tersimpanPada ? `Tersimpan otomatis · ${jam(keadaan.tersimpanPada)}` : 'Tersimpan otomatis',
    }[kondisi];
    const peringatan = ['offline', 'jaringan', 'galat'].includes(kondisi);
    wadah.replaceChildren(ikon(peringatan ? 'offline' : 'tersimpan'), teks);
    wadah.classList.toggle('text-status-warning', peringatan);
    wadah.classList.toggle('text-stone-500', !peringatan);
}

/* ---------- Heartbeat ---------- */

async function denyut() {
    if (keadaan.beku) return;
    try {
        terapkanStatus(await kirim(cfg.url.heartbeat));
        if (keadaan.offline && keadaan.antrean.size === 0) {
            keadaan.offline = false;
            tampilkanStatusSimpan('tersimpan');
        }
        kirimPelanggaranTertunda();
        kirimAntrean();
    } catch (galat) {
        if (galat instanceof GalatStatus) terapkanStatus(galat.data);
        else if (galat instanceof GalatSesi) sesiBerakhir(galat.message);
        else {
            keadaan.offline = true;
            tampilkanStatusSimpan(galat.kode === 'jaringan_ditolak' ? 'jaringan' : 'offline');
        }
    }
}

/* ---------- Deteksi pelanggaran (FR-04.1, FR-04.2) ---------- */

function laporPelanggaran(jenis, pemicu) {
    if (!keadaan.dipantau || keadaan.beku) return;

    // visibilitychange + blur + keluar layar penuh dari satu aksi = satu kejadian.
    const sekarang = Date.now();
    if (sekarang - keadaan.terakhirLapor < cfg.debounceMs) return;
    keadaan.terakhirLapor = sekarang;

    kirimLaporan({ jenis, pemicu, waktu_klien: new Date().toISOString() });
}

function kirimLaporan(laporan) {
    kirim(cfg.url.pelanggaran, laporan, { keepalive: true })
        .then((data) => {
            terapkanStatus(data);
            if (data.dihitung && keadaan.status === 'berlangsung') tampilkanPeringatan(laporan.jenis);
        })
        .catch((galat) => {
            if (galat instanceof GalatStatus) {
                terapkanStatus(galat.data);
            } else if (galat instanceof GalatSesi) {
                sesiBerakhir(galat.message);
            } else {
                // Offline: laporan disimpan dan dikirim saat koneksi pulih.
                keadaan.antreanPelanggaran.push(laporan);
                bukaModal({
                    nada: 'peringatan',
                    judul: 'Pelanggaran Terdeteksi',
                    isi: 'Sistem mendeteksi Anda meninggalkan halaman ujian. Koneksi sedang terputus; kejadian ini akan dilaporkan saat koneksi pulih.',
                    tombol: [{ teks: 'Kembali ke Ujian', utama: true, aksi: kembaliKeUjian }],
                });
            }
        });
}

function kirimPelanggaranTertunda() {
    const tertunda = keadaan.antreanPelanggaran.splice(0);
    tertunda.forEach(kirimLaporan);
}

function tampilkanPeringatan(jenis) {
    const { pelanggaran: x, batas: n } = keadaan;
    const apa = jenis === 'keluar_fullscreen' ? 'keluar dari mode layar penuh' : 'berpindah tab/jendela atau meninggalkan halaman ujian';

    if (x >= n) {
        bukaModal({
            nada: 'bahaya',
            judul: `Peringatan Terakhir (${x}/${n})`,
            isi: `Sistem mendeteksi Anda ${apa}.\nIni peringatan terakhir. Satu pelanggaran lagi akan mengunci ujian dan mengirim jawaban yang sudah tersimpan secara otomatis.`,
            tombol: [{ teks: 'Saya Mengerti', utama: true, aksi: kembaliKeUjian }],
        });
        return;
    }

    const tegas = x >= 2 ? '\nHarap tetap di halaman ujian sampai selesai.' : '';
    bukaModal({
        nada: 'peringatan',
        judul: `Peringatan Pelanggaran (${x}/${n})`,
        isi: `Sistem mendeteksi Anda ${apa}. Kejadian ini dicatat untuk ditinjau dosen.\nSisa toleransi: ${n - x} kali; pelanggaran ke-${n + 1} mengunci ujian secara otomatis.${tegas}`,
        tombol: [{ teks: 'Kembali ke Ujian', utama: true, aksi: kembaliKeUjian }],
    });
}

function kembaliKeUjian() {
    tutupModal();
    if (!document.fullscreenElement && !keadaan.beku) {
        document.documentElement.requestFullscreen().catch(() => {});
    }
}

/* ---------- Modal (alertdialog, fokus terkunci) ---------- */

let fokusSebelumModal = null;

function bukaModal({ nada, judul, isi, tombol }) {
    const kotak = $('modal-kotak');
    const garis = { peringatan: 'border-t-status-warning', bahaya: 'border-t-status-danger', info: 'border-t-status-info', sukses: 'border-t-status-success' }[nada];
    kotak.className = `modal-masuk w-full max-w-md rounded-lg border-t-4 bg-white p-6 shadow-pop ${garis}`;
    $('modal-ikon').replaceChildren(ikon(nada));
    $('modal-judul').textContent = judul;
    $('modal-isi').textContent = isi;

    const tombolNode = tombol.map(({ teks, utama, aksi }) => {
        const node = el('button', `btn ${utama ? (nada === 'bahaya' ? 'btn-danger' : 'btn-primary') : 'btn-secondary'}`, { type: 'button' });
        node.textContent = teks;
        node.addEventListener('click', aksi);
        return node;
    });
    $('modal-aksi').replaceChildren(...tombolNode);

    if (!fokusSebelumModal) fokusSebelumModal = document.activeElement;
    const modal = $('modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    tombolNode.find((_, i) => tombol[i].utama)?.focus();
}

function tutupModal() {
    const modal = $('modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    fokusSebelumModal?.focus?.();
    fokusSebelumModal = null;
}

$('modal').addEventListener('keydown', (event) => {
    if (event.key !== 'Tab') return;
    const fokusable = [...$('modal').querySelectorAll('button')];
    const pertama = fokusable[0];
    const terakhir = fokusable[fokusable.length - 1];
    if (event.shiftKey && document.activeElement === pertama) {
        event.preventDefault();
        terakhir.focus();
    } else if (!event.shiftKey && document.activeElement === terakhir) {
        event.preventDefault();
        pertama.focus();
    }
});

/* ---------- Penyelesaian ---------- */

function keluarLayarPenuh() {
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
}

function bekukan(status, alasan, pesan) {
    if (keadaan.beku) return;
    keadaan.beku = true;
    keadaan.dipantau = false;

    document.querySelectorAll('#area-soal input, #area-soal textarea, #area-soal button, #navigasi button, #tombol-kirim').forEach((node) => {
        node.disabled = true;
    });
    try {
        localStorage.removeItem(cfg.kunciPenyimpanan);
    } catch {
        // abaikan
    }

    const dikunci = status === 'terkunci';
    const judul = dikunci ? 'Ujian Dikunci' : alasan === 'waktu_habis' ? 'Waktu Habis' : 'Jawaban Terkirim';
    const isiBawaan = dikunci
        ? 'Ujian Anda dikunci. Jawaban yang sudah tersimpan telah dikirim secara otomatis.'
        : alasan === 'waktu_habis'
          ? 'Waktu ujian habis. Jawaban yang sudah tersimpan telah dikirim.'
          : 'Jawaban Anda telah dikirim.';

    bukaModal({
        nada: dikunci ? 'bahaya' : 'info',
        judul,
        isi: pesan || isiBawaan,
        tombol: [
            {
                teks: 'Lihat Hasil Pengiriman',
                utama: true,
                aksi: () => {
                    keluarLayarPenuh();
                    window.location.href = cfg.url.selesai;
                },
            },
        ],
    });
}

async function waktuHabis() {
    if (keadaan.mengakhiri) return;
    keadaan.mengakhiri = true;
    keadaan.dipantau = false;
    await tuntaskanAntrean();
    try {
        terapkanStatus(await kirim(cfg.url.kirim));
    } catch (galat) {
        if (galat instanceof GalatStatus) terapkanStatus(galat.data);
        else bekukan('selesai', 'waktu_habis');
    }
}

function konfirmasiKirim() {
    const total = keadaan.soal.length;
    const dijawab = keadaan.soal.filter(terjawab).length;
    const ragu = keadaan.soal.filter((s) => s.ragu).length;
    bukaModal({
        nada: 'info',
        judul: 'Kirim Jawaban?',
        isi: `Terjawab ${dijawab} dari ${total} soal${ragu ? `, ${ragu} ditandai ragu-ragu` : ''}${total - dijawab ? `, ${total - dijawab} belum dijawab` : ''}.\nSetelah dikirim, jawaban tidak dapat diubah.`,
        tombol: [
            { teks: 'Batal', utama: false, aksi: tutupModal },
            { teks: 'Kirim Sekarang', utama: true, aksi: kirimJawabanAkhir },
        ],
    });
}

async function kirimJawabanAkhir() {
    tutupModal();
    keadaan.dipantau = false;
    $('tombol-kirim').disabled = true;
    await tuntaskanAntrean();
    if (keadaan.antrean.size > 0) {
        keadaan.dipantau = true;
        $('tombol-kirim').disabled = false;
        bukaModal({
            nada: 'peringatan',
            judul: 'Belum Tersimpan',
            isi: 'Sebagian jawaban belum tersimpan karena koneksi bermasalah. Periksa koneksi lalu coba kirim lagi.',
            tombol: [{ teks: 'Kembali ke Ujian', utama: true, aksi: kembaliKeUjian }],
        });
        return;
    }
    try {
        await kirim(cfg.url.kirim);
        keadaan.beku = true;
        keluarLayarPenuh();
        window.location.href = cfg.url.selesai;
    } catch (galat) {
        if (galat instanceof GalatStatus) terapkanStatus(galat.data);
        else if (galat instanceof GalatSesi) sesiBerakhir(galat.message);
        else {
            keadaan.dipantau = true;
            $('tombol-kirim').disabled = false;
            tampilkanStatusSimpan('offline');
        }
    }
}

function sesiBerakhir(pesan) {
    if (keadaan.beku) return;
    keadaan.beku = true;
    keadaan.dipantau = false;
    bukaModal({
        nada: 'bahaya',
        judul: 'Sesi Berakhir',
        isi: `${pesan}\nJawaban yang sudah tersimpan aman. Login kembali untuk melanjutkan.`,
        tombol: [
            {
                teks: 'Login Kembali',
                utama: true,
                aksi: () => {
                    keluarLayarPenuh();
                    window.location.href = '/login';
                },
            },
        ],
    });
}

/* ---------- Proteksi halaman (FR-04.3) ---------- */

document.addEventListener('contextmenu', (event) => event.preventDefault());
for (const jenis of ['copy', 'cut', 'paste', 'drop', 'dragstart']) {
    document.addEventListener(jenis, (event) => event.preventDefault());
}
document.addEventListener('selectstart', (event) => {
    // Seleksi di kotak jawaban esai tetap boleh agar mahasiswa bisa menyunting.
    if (!(event.target instanceof HTMLTextAreaElement)) event.preventDefault();
});
document.addEventListener('keydown', (event) => {
    const tombol = event.key.toLowerCase();
    const ctrl = event.ctrlKey || event.metaKey;
    const dilarang =
        event.key === 'F12' ||
        (ctrl && !event.shiftKey && ['c', 'v', 'x', 'u'].includes(tombol)) ||
        (ctrl && event.shiftKey && ['i', 'j', 'c'].includes(tombol));
    if (dilarang) event.preventDefault();
});

document.addEventListener('visibilitychange', () => {
    if (document.hidden) laporPelanggaran('pindah_tab', 'visibilitychange');
});
window.addEventListener('blur', () => laporPelanggaran('pindah_tab', 'blur'));
document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement) laporPelanggaran('keluar_fullscreen', 'fullscreenchange');
});

/* ---------- Mulai ---------- */

$('tombol-kirim').addEventListener('click', konfirmasiKirim);

$('tombol-mulai').addEventListener('click', async () => {
    try {
        await document.documentElement.requestFullscreen();
    } catch {
        const galat = $('mulai-galat');
        galat.textContent = 'Peramban menolak mode layar penuh. Gunakan Chrome atau Edge versi desktop, lalu coba lagi.';
        galat.classList.remove('hidden');
        return;
    }
    $('layar-mulai').remove();
    keadaan.dipantau = true;
    $('judul-soal')?.focus?.();
});

async function muat() {
    try {
        const data = await kirim(cfg.url.soal, null, { metode: 'GET' });
        keadaan.soal = data.soal;
        terapkanStatus(data);
        pulihkanAntreanLokal();
        render();
        tampilkanStatusSimpan(keadaan.antrean.size ? 'menyimpan' : 'tersimpan');
        $('tombol-mulai').disabled = false;
        kirimAntrean();
    } catch (galat) {
        if (galat instanceof GalatStatus) {
            $('layar-mulai')?.remove();
            terapkanStatus(galat.data);
        } else if (galat instanceof GalatSesi) {
            $('layar-mulai')?.remove();
            sesiBerakhir(galat.message);
        } else {
            const pesan = $('mulai-galat');
            pesan.textContent = 'Soal gagal dimuat. Periksa koneksi lalu muat ulang halaman.';
            pesan.classList.remove('hidden');
        }
    }
}

setInterval(perbaruiTimer, 1000);
setInterval(denyut, cfg.heartbeatDetik * 1000);
setInterval(kirimAntrean, cfg.autosaveDetik * 1000);
muat();
