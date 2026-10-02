@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}ExamGuard CBT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ivory bg-pola-garis">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        {{ $slot }}
    </main>
</body>
</html>
