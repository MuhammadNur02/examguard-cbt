/**
 * Live Monitor dosen (FR-06.1, FR-06.2): polling berkala, tabel diperbarui per
 * sel agar tidak berkedip (StyleGuide §7), pelanggaran baru muncul tanpa muat ulang.
 */

const cfg = JSON.parse(document.getElementById('konfigurasi-monitor').textContent);
const $ = (id) => document.getElementById(id);
let sejak = 0;

const STATUS = {
    aktif: { label: 'Aktif', kelas: 'badge badge-info' },
    offline: { label: 'Offline', kelas: 'badge badge-warning' },
    selesai: { label: 'Selesai', kelas: 'badge badge-success' },
    terkunci: { label: 'Terkunci', kelas: 'badge badge-danger' },
};

function setTeks(node, teks) {
    if (node.textContent !== teks) node.textContent = teks;
}

function waktuSisa(detik) {
    if (!detik) return '–';
    const jam = Math.floor(detik / 3600);
    const menit = Math.floor((detik % 3600) / 60);
    const dua = (n) => String(n).padStart(2, '0');
    return `${jam > 0 ? `${jam}:` : ''}${dua(menit)}:${dua(detik % 60)}`;
}

function barisBaru(id) {
    const tr = document.createElement('tr');
    tr.dataset.id = id;
    tr.innerHTML = '<td class="font-mono text-ink"></td><td class="text-ink"></td><td></td><td class="num"></td><td class="num"></td><td></td><td class="num font-mono"></td>';
    return tr;
}

function perbaruiBaris(tr, p) {
    const [nim, nama, status, pelanggaran, terjawab, aktivitas, sisa] = tr.children;
    setTeks(nim, p.nim);
    setTeks(nama, p.nama);

    const gaya = STATUS[p.status];
    if (status.dataset.status !== p.status) {
        status.dataset.status = p.status;
        const lencana = document.createElement('span');
        lencana.className = gaya.kelas;
        lencana.append($(`ikon-${p.status}`).content.cloneNode(true), gaya.label);
        status.replaceChildren(lencana);
    }

    setTeks(pelanggaran, `${p.pelanggaran}/${p.batas}`);
    setTeks(terjawab, `${p.terjawab}/${p.jumlah_soal}`);
    setTeks(aktivitas, p.status === 'selesai' || p.status === 'terkunci' ? `Selesai ${p.selesai ?? ''}` : (p.terakhir_aktif ?? '–'));
    setTeks(sisa, p.status === 'aktif' || p.status === 'offline' ? waktuSisa(p.sisa_detik) : '–');

    // Garis kiri: kuning bila pelanggaran melewati separuh batas, merah bila terkunci.
    const garis = p.status === 'terkunci' ? 'border-l-4 border-l-status-danger' : p.pelanggaran * 2 > p.batas ? 'border-l-4 border-l-status-warning' : '';
    if (nim.dataset.garis !== garis) {
        nim.dataset.garis = garis;
        nim.className = `font-mono text-ink ${garis}`;
    }
}

function perbaruiTabel(peserta) {
    const tbody = $('monitor-peserta');
    const ada = new Map([...tbody.querySelectorAll('tr[data-id]')].map((tr) => [tr.dataset.id, tr]));
    $('monitor-kosong')?.classList.toggle('hidden', peserta.length > 0);

    peserta.forEach((p, indeks) => {
        const id = String(p.id);
        const tr = ada.get(id) ?? barisBaru(id);
        ada.delete(id);
        perbaruiBaris(tr, p);
        // Pindahkan hanya bila posisinya berubah, agar tidak berkedip.
        const posisi = tbody.querySelectorAll('tr[data-id]')[indeks];
        if (posisi !== tr) tbody.insertBefore(tr, posisi ?? null);
    });

    ada.forEach((tr) => tr.remove());
}

function tambahPelanggaran(daftar) {
    if (!daftar.length) return;
    $('pelanggaran-kosong').classList.add('hidden');
    const ul = $('monitor-pelanggaran');

    for (const log of daftar) {
        sejak = Math.max(sejak, log.id);
        const li = document.createElement('li');
        li.className = 'flex items-start gap-2 rounded-md bg-status-warning-bg px-3 py-2 text-small text-status-warning';
        const teks = document.createElement('span');
        teks.textContent = `${log.waktu} · ${log.nim} ${log.nama} · ${log.jenis_label}${log.dihitung ? '' : ' (dicatat, tidak dihitung)'}`;
        li.append($('ikon-peringatan').content.cloneNode(true), teks);
        ul.prepend(li);
    }

    while (ul.children.length > 50) ul.lastElementChild.remove();
}

async function muat() {
    try {
        const respons = await fetch(`${cfg.url}?sejak=${sejak}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (respons.status === 401 || respons.status === 419) {
            setTeks($('monitor-status'), 'Sesi berakhir. Muat ulang halaman untuk login kembali.');
            return;
        }
        const data = await respons.json();
        for (const [kunci, nilai] of Object.entries(data.ringkasan)) setTeks($(`ringkasan-${kunci}`), String(nilai));
        perbaruiTabel(data.peserta);
        tambahPelanggaran(data.pelanggaran_baru);
        setTeks($('monitor-waktu'), `pukul ${data.waktu_server}`);
        setTeks($('monitor-status'), `Diperbarui tiap ${cfg.pollDetik} detik`);
    } catch {
        setTeks($('monitor-status'), 'Koneksi terputus, mencoba lagi…');
    }
    setTimeout(muat, cfg.pollDetik * 1000);
}

muat();
