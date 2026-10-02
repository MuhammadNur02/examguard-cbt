<x-layouts.app :title="'Ubah Soal '.$question->tipe->label()">
    <p class="mb-4 text-small text-stone-500">{{ $exam->judul }} · {{ $exam->mata_kuliah }}</p>
    <form method="POST" action="{{ route('dosen.questions.update', [$exam, $question]) }}" class="card max-w-3xl" novalidate>
        @csrf
        @method('PUT')
        @include('dosen.questions._form')
        <div class="mt-6 flex gap-3">
            <button type="submit" class="btn btn-primary"><x-icon name="save" class="size-4" />Simpan perubahan</button>
            <a href="{{ route('dosen.exams.show', $exam) }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</x-layouts.app>
