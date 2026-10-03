{{-- Tata letak laporan cetak (FR-09.3): A4, tanpa navigasi; PDF dibuat lewat dialog cetak peramban. --}}
@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}ExamGuard CBT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-ink">
    <main class="laporan-cetak mx-auto max-w-[210mm] px-6 py-8 print:p-0">
        {{ $slot }}
    </main>
</body>
</html>
