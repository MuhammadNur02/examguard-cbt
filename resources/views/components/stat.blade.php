@props(['label', 'value', 'icon' => null])
<div class="card flex items-start justify-between gap-4">
    <div>
        <p class="font-display text-display font-bold text-maroon-900">{{ $value }}</p>
        <p class="mt-1 text-small text-stone-500">{{ $label }}</p>
    </div>
    @if ($icon)
        <x-icon :name="$icon" class="size-6 text-maroon-500" />
    @endif
</div>
