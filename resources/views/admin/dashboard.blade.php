<x-layouts.app title="Dashboard Admin">
    <section aria-labelledby="ringkasan-akun">
        <h2 id="ringkasan-akun" class="sr-only">Ringkasan akun</h2>
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Mahasiswa aktif" :value="$jumlahMahasiswa" icon="graduation-cap" />
            <x-stat label="Dosen aktif" :value="$jumlahDosen" icon="users" />
            <x-stat label="Kelas" :value="$jumlahKelas" icon="school" />
            <x-stat label="Akun nonaktif" :value="$jumlahNonaktif" icon="user-x" />
        </div>
    </section>
</x-layouts.app>
