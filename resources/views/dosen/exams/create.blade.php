<x-layouts.app title="Buat Ujian">
    <form method="POST" action="{{ route('dosen.exams.store') }}" class="card max-w-3xl space-y-6" novalidate>
        @csrf
        @include('dosen.exams._form')
        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary"><x-icon name="save" class="size-4" />Simpan sebagai draf</button>
            <a href="{{ route('dosen.dashboard') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
