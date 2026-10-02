<x-layouts.app title="Tambah Akun">
    <form method="POST" action="{{ route('admin.users.store') }}" class="card max-w-xl space-y-5" novalidate>
        @csrf

        <div>
            <label for="nim_nidn" class="form-label">NIM / NIDN / Username</label>
            <input id="nim_nidn" name="nim_nidn" type="text" value="{{ old('nim_nidn') }}" class="form-input font-mono"
                required maxlength="30" autocomplete="off"
                @error('nim_nidn') aria-invalid="true" aria-describedby="nim_nidn-error" @enderror>
            @error('nim_nidn')
                <p id="nim_nidn-error" class="form-error"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="nama" class="form-label">Nama lengkap</label>
            <input id="nama" name="nama" type="text" value="{{ old('nama') }}" class="form-input" required maxlength="255"
                @error('nama') aria-invalid="true" aria-describedby="nama-error" @enderror>
            @error('nama')
                <p id="nama-error" class="form-error"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="role" class="form-label">Peran</label>
            <select id="role" name="role" class="form-input" required
                @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>
                @foreach (\App\Enums\Role::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', 'mahasiswa') === $role->value)>{{ $role->label() }} ({{ $role->identitas() }})</option>
                @endforeach
            </select>
            @error('role')
                <p id="role-error" class="form-error"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="form-label">Kata sandi awal (opsional)</label>
            <input id="password" name="password" type="password" class="form-input" minlength="8" autocomplete="new-password"
                aria-describedby="password-help @error('password') password-error @enderror"
                @error('password') aria-invalid="true" @enderror>
            <p id="password-help" class="mt-1.5 text-small text-stone-500">Kosongkan agar sistem membuat kata sandi acak yang ditampilkan sekali.</p>
            @error('password')
                <p id="password-error" class="form-error"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary"><x-icon name="save" class="size-4" />Simpan</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
