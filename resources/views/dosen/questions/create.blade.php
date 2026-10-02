<x-layouts.app :title="'Tambah Soal '.$question->tipe->label()">
    <p class="mb-4 text-small text-stone-500">{{ $exam->judul }} · {{ $exam->mata_kuliah }}</p>
    <form method="POST" action="{{ route('dosen.questions.store', $exam) }}" class="card max-w-3xl" novalidate>
        @csrf
        @include('dosen.questions._form')
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-primary"><x-icon name="save" class="size-4" />Simpan soal</button>
            <a href="{{ route('dosen.exams.show', $exam) }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
