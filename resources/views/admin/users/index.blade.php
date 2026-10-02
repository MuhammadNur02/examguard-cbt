<x-layouts.app title="Akun Pengguna">
    @if (session('kredensial'))
        <x-kredensial :data="session('kredensial')" />
    @endif

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-wrap items-end gap-3" role="search">
            <div>
                <label for="peran" class="form-label">Peran</label>
                <select id="peran" name="peran" class="form-input w-40">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\Role::cases() as $role)
                        <option value="{{ $role->value }}" @selected($filter['peran'] === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-input w-36">
                    <option value="">Semua</option>
                    <option value="aktif" @selected($filter['status'] === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($filter['status'] === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div>
                <label for="q" class="form-label">Cari NIM/NIDN atau nama</label>
                <input id="q" name="q" type="search" value="{{ $filter['q'] }}" class="form-input w-64" maxlength="100">
            </div>
            <button type="submit" class="btn btn-secondary"><x-icon name="search" class="size-4" />Terapkan</button>
        </form>
        <div class="flex gap-2">
            <a href="{{ route('admin.users.import') }}" class="btn btn-secondary"><x-icon name="upload" class="size-4" />Impor CSV</a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="user-plus" class="size-4" />Tambah Akun</a>
        </div>
    </div>

    <section class="card overflow-hidden p-0" aria-label="Daftar akun">
        @if ($users->isEmpty())
            <p class="p-6 text-stone-500">Tidak ada akun yang cocok dengan filter.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-eg">
                    <thead>
                        <tr>
                            <th scope="col">NIM / NIDN / Username</th>
                            <th scope="col">Nama</th>
                            <th scope="col">Peran</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td class="font-mono text-ink">{{ $user->nim_nidn }}</td>
                                <td class="text-ink">{{ $user->nama }}</td>
                                <td><span class="badge badge-info">{{ $user->role->label() }}</span></td>
                                <td>
                                    @if ($user->aktif)
                                        <span class="badge badge-success"><x-icon name="circle-check" class="size-3.5" />Aktif</span>
                                    @else
                                        <span class="badge badge-neutral"><x-icon name="ban" class="size-3.5" />Nonaktif</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}"
                                            data-confirm="Atur ulang kata sandi {{ $user->nim_nidn }}? Sesi aktifnya juga akan berakhir.">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="key-round" class="size-4" />Reset sandi</button>
                                        </form>
                                        @unless ($user->is(auth()->user()))
                                            <form method="POST" action="{{ route('admin.users.reset-session', $user) }}"
                                                data-confirm="Akhiri sesi {{ $user->nim_nidn }}? Pengguna harus login ulang.">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="log-out" class="size-4" />Reset sesi</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.users.status', $user) }}"
                                                data-confirm="{{ $user->aktif ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $user->nim_nidn }}?">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="aktif" value="{{ $user->aktif ? 0 : 1 }}">
                                                <button type="submit" class="btn btn-ghost btn-sm">
                                                    @if ($user->aktif)
                                                        <x-icon name="user-x" class="size-4" />Nonaktifkan
                                                    @else
                                                        <x-icon name="user-check" class="size-4" />Aktifkan
                                                    @endif
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links() }}
        @endif
    </section>
</x-layouts.app>
