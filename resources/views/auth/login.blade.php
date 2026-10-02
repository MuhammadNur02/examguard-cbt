<x-layouts.guest title="Masuk">
    <div class="card card-important w-full max-w-md">
        <div class="mb-8 flex items-center gap-3">
            <span class="flex size-12 items-center justify-center rounded-md bg-maroon-700 text-white">
                <x-icon name="shield-check" class="size-7" />
            </span>
            <div>
                <h1 class="font-display text-h1 font-semibold text-maroon-900">ExamGuard CBT</h1>
                <p class="text-small text-stone-500">Prodi Pendidikan Informatika · Universitas Ivet</p>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-info mb-6" role="status">
                <x-icon name="info" class="mt-0.5 size-5" />
                <p>{{ session('status') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5" novalidate>
            @csrf

            <div>
                <label for="nim_nidn" class="form-label">NIM / NIDN / Username</label>
                <input id="nim_nidn" name="nim_nidn" type="text" value="{{ old('nim_nidn') }}"
                    class="form-input" required autofocus autocomplete="username" maxlength="30"
                    @error('nim_nidn') aria-invalid="true" aria-describedby="nim_nidn-error" @enderror>
                @error('nim_nidn')
                    <p id="nim_nidn-error" class="form-error"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="form-label">Kata sandi</label>
                <input id="password" name="password" type="password" class="form-input" required
                    autocomplete="current-password"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <p id="password-error" class="form-error"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary w-full">
                <x-icon name="log-in" />
                Masuk
            </button>
        </form>

        <p class="mt-6 text-small text-stone-500">
            Lupa kata sandi? Hubungi admin prodi untuk mengatur ulang.
        </p>
    </div>
</x-layouts.guest>
