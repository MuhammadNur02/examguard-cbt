// Konfirmasi sebelum mengirim form berisiko: <form data-confirm="Pesan?">
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
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
