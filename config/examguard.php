<?php

/*
| Pengaturan layar ujian dan pemantauan. Nilai dapat diubah lewat .env.
*/

return [

    // Selang heartbeat dari peramban mahasiswa (detik).
    'heartbeat_detik' => (int) env('EXAM_HEARTBEAT_SECONDS', 15),

    // Selang autosave berkala jawaban yang belum tersimpan (detik).
    'autosave_detik' => (int) env('EXAM_AUTOSAVE_SECONDS', 10),

    // Peserta dianggap offline bila tidak ada heartbeat selama ini (detik).
    'offline_setelah_detik' => (int) env('EXAM_OFFLINE_AFTER_SECONDS', 45),

    // Kejadian pelanggaran dalam jarak ini dihitung satu kali (milidetik).
    'debounce_pelanggaran_ms' => (int) env('EXAM_VIOLATION_DEBOUNCE_MS', 2000),

    // Toleransi jawaban yang tiba sesaat setelah batas waktu (latensi jaringan).
    'toleransi_simpan_detik' => (int) env('EXAM_SAVE_GRACE_SECONDS', 10),

    // Selang polling Live Monitor dosen (detik, PRD: <= 10).
    'monitor_poll_detik' => (int) env('MONITOR_POLL_SECONDS', 5),

];
