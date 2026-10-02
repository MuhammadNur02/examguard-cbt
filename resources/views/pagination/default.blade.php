@if ($paginator->hasPages())
    <nav aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-between gap-4 border-t border-stone-200 px-6 py-4 text-small">
        <p class="text-stone-500">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm cursor-not-allowed opacity-50" aria-disabled="true">
                    <x-icon name="chevron-left" class="size-4" />Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-secondary btn-sm">
                    <x-icon name="chevron-left" class="size-4" />Sebelumnya
                </a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-secondary btn-sm">
                    Berikutnya<x-icon name="chevron-right" class="size-4" />
                </a>
            @else
                <span class="btn btn-secondary btn-sm cursor-not-allowed opacity-50" aria-disabled="true">
                    Berikutnya<x-icon name="chevron-right" class="size-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
