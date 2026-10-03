// Konfirmasi sebelum mengirim form berisiko: <form data-confirm="Pesan?">
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

// Tombol "Mulai Ujian" aktif hanya setelah persetujuan integritas dicentang (FR-04.10).
// Server tetap memvalidasi persetujuan; ini hanya kenyamanan antarmuka.
// FR-04.11: layar kecil (ponsel/tablet kecil) atau perangkat sentuh tanpa mouse/trackpad
// tidak dapat memulai. Server juga menolak user-agent seluler.
const layarTidakDidukung = () =>
    Math.max(window.screen.width, window.screen.height) < 1024 ||
    (window.matchMedia('(pointer: coarse)').matches && !window.matchMedia('(any-pointer: fine)').matches);

document.querySelectorAll('[data-persetujuan]').forEach((form) => {
    const centang = form.querySelector('[data-persetujuan-centang]');
    const tombol = form.querySelector('[data-persetujuan-tombol]');
    const tidakDidukung = layarTidakDidukung();
    form.querySelector('[data-layar-kecil]')?.classList.toggle('hidden', !tidakDidukung);
    const sinkron = () => {
        tombol.disabled = tidakDidukung || !centang.checked;
    };
    centang.addEventListener('change', sinkron);
    sinkron();
});

// Unduh isi tabel sebagai CSV: <button data-unduh-tabel="id-tabel" data-nama-berkas="x.csv">
document.addEventListener('click', (event) => {
    const tombol = event.target.closest('[data-unduh-tabel]');
    if (!tombol) {
        return;
    }

    const tabel = document.getElementById(tombol.dataset.unduhTabel);
    // Awalan ' mencegah Excel mengeksekusi isi sel sebagai formula (CSV injection).
    const aman = (teks) => (/^[=+\-@\t\r]/.test(teks) ? `'${teks}` : teks);
    const sel = (teks) => `"${aman(teks).replaceAll('"', '""')}"`;
    const baris = [...tabel.querySelectorAll('tr')].map((tr) =>
        [...tr.querySelectorAll('th, td')].map((td) => sel(td.innerText.trim())).join(','),
    );
    const blob = new Blob(['﻿' + baris.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const tautan = document.createElement('a');
    tautan.href = URL.createObjectURL(blob);
    tautan.download = tombol.dataset.namaBerkas || 'data.csv';
    tautan.click();
    URL.revokeObjectURL(tautan.href);
});

// Laporan cetak (FR-09.3): PDF dibuat lewat dialog cetak peramban ("Simpan sebagai PDF").
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-cetak]')) {
        window.print();
    }
});
