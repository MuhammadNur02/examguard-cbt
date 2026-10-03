#!/usr/bin/env node
/**
 * Uji beban ExamGuard CBT (Task 5.4, PRD §13.3): N peserta bersamaan.
 *
 * Fase 0 (login): peserta login dan membuka halaman ujian, serentak atau
 *   disebar selama --ramp-login detik (kenyataannya mahasiswa login beberapa
 *   menit sebelum ujian dimulai).
 * Fase 1 (puncak awal): semua peserta serentak memulai attempt dan mengambil soal.
 * Fase 2 (ujian berjalan, --durasi detik): heartbeat tiap --heartbeat detik,
 *   autosave tiap --autosave detik (1-3 jawaban acak), dan satu laporan
 *   pelanggaran per peserta pada waktu acak. Seperti exam.js, tiap peserta
 *   hanya punya satu permintaan berjalan pada satu waktu.
 * Fase 3 (puncak akhir): semua peserta serentak mengirim jawaban.
 *
 * Latensi diukur dari sisi klien (termasuk antre di server). Galat = status
 * HTTP tak terduga, batas waktu 30 detik, atau galat jaringan.
 *
 * --url boleh berisi beberapa alamat dipisah koma (mis. beberapa proses PHP);
 * peserta dibagi bergiliran ke alamat-alamat itu.
 *
 * Tanpa dependensi (Node >= 20). Contoh:
 *   node tools/uji-beban/uji-beban.mjs --url http://127.0.0.1:8002 --ujian 1 --peserta 100 --durasi 120 --ramp-login 60
 */
import { writeFileSync } from 'node:fs';
import { parseArgs } from 'node:util';

const { values: arg } = parseArgs({
    options: {
        url: { type: 'string', default: 'http://127.0.0.1:8002' },
        ujian: { type: 'string', default: '1' },
        peserta: { type: 'string', default: '100' },
        durasi: { type: 'string', default: '120' },
        heartbeat: { type: 'string', default: '15' },
        autosave: { type: 'string', default: '10' },
        'ramp-login': { type: 'string', default: '0' },
        awalan: { type: 'string', default: 'B' },
        sandi: { type: 'string', default: 'password' },
        keluaran: { type: 'string' },
    },
});

const DAFTAR_BASIS = arg.url.split(',').map((u) => u.trim().replace(/\/$/, ''));
const UJIAN = Number(arg.ujian);
const RAMP_MS = Number(arg['ramp-login']) * 1000;
const N = Number(arg.peserta);
const DURASI_MS = Number(arg.durasi) * 1000;
const HEARTBEAT_MS = Number(arg.heartbeat) * 1000;
const AUTOSAVE_MS = Number(arg.autosave) * 1000;
const BATAS_WAKTU_MS = 30_000;

const tunggu = (ms) => new Promise((r) => setTimeout(r, ms));
const acak = (n) => Math.floor(Math.random() * n);

/** @type {Map<string, {ms: number[], ok: number, galat: Record<string, number>}>} */
const statistik = new Map();
let fase = 'awal';

function catat(nama, ms, ok, kode) {
    const kunci = `${fase}:${nama}`;
    const s = statistik.get(kunci) ?? { ms: [], ok: 0, galat: {} };
    s.ms.push(ms);
    if (ok) s.ok++;
    else s.galat[kode] = (s.galat[kode] ?? 0) + 1;
    statistik.set(kunci, s);
}

class Peserta {
    constructor(nim, basis) {
        this.nim = nim;
        this.basis = basis;
        this.cookie = new Map();
        this.csrf = null;
        this.soal = [];
        this.esai = {};
    }

    async minta(nama, metode, path, { json, form, harapkan = [200], html = false } = {}) {
        const headers = {
            Cookie: [...this.cookie].map(([k, v]) => `${k}=${v}`).join('; '),
            Accept: html ? 'text/html' : 'application/json',
        };
        let body;
        if (json !== undefined) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-TOKEN'] = this.csrf;
            body = JSON.stringify(json);
        } else if (form !== undefined) {
            headers['Content-Type'] = 'application/x-www-form-urlencoded';
            body = new URLSearchParams(form).toString();
        }

        const mulai = performance.now();
        try {
            const res = await fetch(this.basis + path, { method: metode, headers, body, redirect: 'manual', signal: AbortSignal.timeout(BATAS_WAKTU_MS) });
            const teks = await res.text();
            for (const c of res.headers.getSetCookie()) {
                const pasangan = c.split(';')[0];
                const i = pasangan.indexOf('=');
                this.cookie.set(pasangan.slice(0, i), pasangan.slice(i + 1));
            }
            const ok = harapkan.includes(res.status);
            catat(nama, performance.now() - mulai, ok, String(res.status));
            return { ok, status: res.status, teks };
        } catch (e) {
            catat(nama, performance.now() - mulai, false, e.name === 'TimeoutError' ? 'batas-waktu' : e.name);
            return { ok: false, status: 0, teks: '' };
        }
    }

    async masuk() {
        const halaman = await this.minta('GET /login', 'GET', '/login', { html: true });
        const token = halaman.teks.match(/name="_token" value="([^"]+)"/)?.[1];
        if (!token) return false;
        const login = await this.minta('POST /login', 'POST', '/login', { form: { _token: token, nim_nidn: this.nim, password: arg.sandi }, harapkan: [302] });
        if (!login.ok) return false;

        const info = await this.minta('GET halaman ujian', 'GET', `/mahasiswa/ujian/${UJIAN}`, { html: true });
        this.csrf = info.teks.match(/<meta name="csrf-token" content="([^"]+)"/)?.[1] ?? null;

        return this.csrf !== null;
    }

    async mulai() {
        const mulai = await this.minta('POST mulai', 'POST', `/mahasiswa/ujian/${UJIAN}/mulai`, { json: { setuju: '1' }, harapkan: [200, 201] });
        if (!mulai.ok) return false;
        const soal = await this.minta('GET soal', 'GET', `/mahasiswa/ujian/${UJIAN}/soal`);
        if (!soal.ok) return false;
        this.soal = JSON.parse(soal.teks).soal;

        return true;
    }

    jawabanAcak() {
        return Array.from({ length: 1 + acak(3) }, () => {
            const s = this.soal[acak(this.soal.length)];
            if (s.tipe === 'pg') return { nomor: s.nomor, opsi: acak(s.opsi.length) };
            this.esai[s.nomor] = `${this.esai[s.nomor] ?? 'Middleware menyaring permintaan'} HTTP sebelum controller`.slice(0, 2000);
            return { nomor: s.nomor, teks: this.esai[s.nomor] };
        });
    }

    async ujianBerjalan(selesaiPada) {
        let berikutHeartbeat = Date.now() + acak(HEARTBEAT_MS);
        let berikutAutosave = Date.now() + acak(AUTOSAVE_MS);
        let pelanggaran = Date.now() + DURASI_MS * (0.2 + Math.random() * 0.6);

        while (Date.now() < selesaiPada) {
            const sekarang = Date.now();
            if (sekarang >= pelanggaran) {
                pelanggaran = Infinity;
                await this.minta('POST pelanggaran', 'POST', `/mahasiswa/ujian/${UJIAN}/pelanggaran`, {
                    json: { jenis: 'pindah_tab', pemicu: 'visibilitychange', waktu_klien: new Date().toISOString() },
                });
            } else if (sekarang >= berikutAutosave) {
                berikutAutosave += AUTOSAVE_MS;
                await this.minta('POST jawaban (autosave)', 'POST', `/mahasiswa/ujian/${UJIAN}/jawaban`, { json: { jawaban: this.jawabanAcak() } });
            } else if (sekarang >= berikutHeartbeat) {
                berikutHeartbeat += HEARTBEAT_MS;
                await this.minta('POST heartbeat', 'POST', `/mahasiswa/ujian/${UJIAN}/heartbeat`, { json: {} });
            } else {
                await tunggu(Math.min(berikutHeartbeat, berikutAutosave, pelanggaran, selesaiPada) - sekarang);
            }
        }
    }

    kirim() {
        return this.minta('POST kirim', 'POST', `/mahasiswa/ujian/${UJIAN}/kirim`, { json: {} });
    }
}

function persentil(urut, p) {
    return urut.length ? urut[Math.min(urut.length - 1, Math.ceil((p / 100) * urut.length) - 1)] : 0;
}

function ringkas() {
    return [...statistik].map(([kunci, s]) => {
        const urut = [...s.ms].sort((a, b) => a - b);
        const bulat = (x) => Math.round(x);
        return {
            endpoint: kunci,
            jumlah: urut.length,
            galat: urut.length - s.ok,
            rincian_galat: s.galat,
            p50_ms: bulat(persentil(urut, 50)),
            p95_ms: bulat(persentil(urut, 95)),
            p99_ms: bulat(persentil(urut, 99)),
            maks_ms: bulat(urut.at(-1) ?? 0),
            rata_ms: bulat(urut.reduce((a, b) => a + b, 0) / (urut.length || 1)),
        };
    });
}

const peserta = Array.from({ length: N }, (_, i) => new Peserta(`${arg.awalan}${String(i + 1).padStart(4, '0')}`, DAFTAR_BASIS[i % DAFTAR_BASIS.length]));
console.log(`Uji beban: ${N} peserta, ujian #${UJIAN}, ${DAFTAR_BASIS.join(', ')}, login disebar ${RAMP_MS / 1000} s, fase berjalan ${DURASI_MS / 1000} s`);

fase = 'login';
let t = performance.now();
const masuk = await Promise.all(peserta.map(async (p, i) => {
    await tunggu((RAMP_MS * i) / N);
    return p.masuk();
}));
const durasiLogin = (performance.now() - t) / 1000;
console.log(`Fase 0 (login) selesai dalam ${durasiLogin.toFixed(1)} s: ${masuk.filter(Boolean).length}/${N} berhasil.`);

fase = 'awal';
t = performance.now();
const siap = await Promise.all(peserta.map((p, i) => (masuk[i] ? p.mulai() : false)));
const aktif = peserta.filter((_, i) => siap[i]);
const durasiAwal = (performance.now() - t) / 1000;
console.log(`Fase 1 (mulai serentak) selesai dalam ${durasiAwal.toFixed(1)} s: ${aktif.length}/${N} peserta siap.`);

fase = 'berjalan';
t = performance.now();
await Promise.all(aktif.map((p) => p.ujianBerjalan(Date.now() + DURASI_MS)));
const durasiBerjalan = (performance.now() - t) / 1000;

fase = 'akhir';
t = performance.now();
await Promise.all(aktif.map((p) => p.kirim()));
const durasiAkhir = (performance.now() - t) / 1000;

const hasil = ringkas();
const permintaanBerjalan = hasil.filter((h) => h.endpoint.startsWith('berjalan:')).reduce((a, h) => a + h.jumlah, 0);
const laporan = {
    waktu: new Date().toISOString(),
    parameter: { url: DAFTAR_BASIS, ujian: UJIAN, peserta: N, ramp_login_detik: RAMP_MS / 1000, durasi_detik: DURASI_MS / 1000, heartbeat_detik: HEARTBEAT_MS / 1000, autosave_detik: AUTOSAVE_MS / 1000 },
    peserta_siap: aktif.length,
    durasi_fase_detik: { login: +durasiLogin.toFixed(1), awal: +durasiAwal.toFixed(1), berjalan: +durasiBerjalan.toFixed(1), akhir: +durasiAkhir.toFixed(1) },
    throughput_berjalan_rps: +(permintaanBerjalan / durasiBerjalan).toFixed(2),
    total_permintaan: hasil.reduce((a, h) => a + h.jumlah, 0),
    total_galat: hasil.reduce((a, h) => a + h.galat, 0),
    endpoint: hasil,
};

console.table(hasil.map(({ rincian_galat, ...h }) => h));
console.log(`Throughput fase berjalan: ${laporan.throughput_berjalan_rps} permintaan/detik; total galat: ${laporan.total_galat}`);
if (arg.keluaran) writeFileSync(arg.keluaran, JSON.stringify(laporan, null, 2));
process.exitCode = laporan.total_galat > 0 || aktif.length < N ? 1 : 0;
