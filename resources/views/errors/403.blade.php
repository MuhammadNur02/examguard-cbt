<x-error-page title="Akses ditolak" icon="ban">
    {{ $exception->getMessage() ?: 'Anda tidak memiliki akses ke halaman ini.' }}
</x-error-page>
