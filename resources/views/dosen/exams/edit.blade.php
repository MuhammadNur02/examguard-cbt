<x-layouts.app title="Ubah Ujian">
    <form method="POST" action="{{ route('dosen.exams.update', $exam) }}" class="card max-w-3xl space-y-6" novalidate>
        @csrf
        @method('PUT')
        @include('dosen.exams._form')
        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary"><x-icon name="save" class="size-4" />Simpan perubahan</button>
            <a href="{{ route('dosen.exams.show', $exam) }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
