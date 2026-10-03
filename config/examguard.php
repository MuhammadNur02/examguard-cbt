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

    // Batas permintaan endpoint ujian per mahasiswa per menit (autosave, heartbeat, dll.).
    'batas_permintaan_per_menit' => (int) env('EXAM_RATE_LIMIT_PER_MINUTE', 240),

    // Selang polling Live Monitor dosen (detik, PRD: <= 10).
    'monitor_poll_detik' => (int) env('MONITOR_POLL_SECONDS', 5),

    // Koreksi cepat esai: pilihan ambang similarity dan bawaannya (FR-06.4).
    'ambang_terima_massal' => [0.9, 0.85, 0.8, 0.75, 0.7, 0.6, 0.5],
    'ambang_terima_massal_bawaan' => 0.8,

];
