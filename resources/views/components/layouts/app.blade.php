@props(['title' => null])
@php
    $user = auth()->user();
    $menu = match ($user->role) {
        \App\Enums\Role::Admin => [
            ['route' => 'admin.dashboard', 'aktif' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard'],
            ['route' => 'admin.users.index', 'aktif' => 'admin.users.*', 'label' => 'Akun Pengguna', 'icon' => 'users'],
            ['route' => 'admin.classes.index', 'aktif' => 'admin.classes.*', 'label' => 'Kelas', 'icon' => 'school'],
        ],
        \App\Enums\Role::Dosen => [
            ['route' => 'dosen.dashboard', 'aktif' => ['dosen.dashboard', 'dosen.exams.*', 'dosen.questions.*'], 'label' => 'Ujian Saya', 'icon' => 'clipboard-list'],
        ],
        \App\Enums\Role::Mahasiswa => [
            ['route' => 'mahasiswa.dashboard', 'aktif' => ['mahasiswa.dashboard', 'mahasiswa.exams.*'], 'label' => 'Ujian', 'icon' => 'clipboard-list'],
            ['route' => 'mahasiswa.grades', 'aktif' => 'mahasiswa.grades', 'label' => 'Riwayat Nilai', 'icon' => 'chart-column'],
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}ExamGuard CBT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ivory">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-maroon-700">Lewati ke konten</a>
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 flex-col bg-maroon-900 text-white lg:flex">
            <div class="flex items-center gap-3 px-6 py-6">
                <x-icon name="shield-check" class="size-7 text-gold-500" />
                <span class="font-display text-h3 font-semibold text-white">ExamGuard CBT</span>
            </div>
            <nav aria-label="Menu utama" class="flex-1 space-y-1 px-3">
                @foreach ($menu as $item)
                    @php $aktif = request()->routeIs($item['aktif']); @endphp
                    <a href="{{ route($item['route']) }}"
                        @class([
                            'flex items-center gap-3 rounded-md border-l-4 px-3 py-2.5 text-small font-medium transition duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold-500',
                            'border-gold-500 bg-maroon-700 text-white' => $aktif,
                            'border-transparent text-maroon-100 hover:bg-maroon-800 hover:text-white' => ! $aktif,
                        ])
                        @if ($aktif) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <p class="px-6 py-4 text-label text-maroon-200">Prodi Pendidikan Informatika · Universitas Ivet</p>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between gap-4 border-b border-stone-200 bg-white px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <details class="relative lg:hidden">
                        <summary class="btn btn-ghost btn-sm list-none" aria-label="Buka menu">
                            <x-icon name="menu" />
                        </summary>
                        <nav aria-label="Menu utama" class="absolute left-0 z-20 mt-2 w-56 rounded-md border border-stone-200 bg-white p-2 shadow-pop">
                            @foreach ($menu as $item)
                                <a href="{{ route($item['route']) }}" class="flex items-center gap-2 rounded-sm px-3 py-2 text-small text-stone-700 hover:bg-maroon-100">
                                    <x-icon :name="$item['icon']" class="size-4" />{{ $item['label'] }}
                                </a>
                            @endforeach
                        </nav>
                    </details>
                    <h1 class="font-display text-h2 font-semibold text-maroon-900">{{ $title ?? 'ExamGuard CBT' }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-small font-semibold text-ink">{{ $user->nama }}</p>
                        <p class="text-label text-stone-500">{{ $user->role->label() }} · {{ $user->nim_nidn }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <x-icon name="log-out" class="size-4" />
                            Keluar
                        </button>
                    </form>
                </div>
            </header>

            <main id="konten" class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">
                <x-flash />
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
