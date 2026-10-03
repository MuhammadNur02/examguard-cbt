{{-- skrip: pratinjau dosen memakai app.js agar logika ujian (layar penuh, pemantauan, autosave) tidak berjalan. --}}
@props(['title' => null, 'skrip' => 'resources/js/exam.js'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}ExamGuard CBT</title>
    @vite(['resources/css/app.css', $skrip])
</head>
<body class="bg-ivory">
    {{ $slot }}
</body>
</html>
