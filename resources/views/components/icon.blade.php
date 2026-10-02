@props(['name', 'label' => null])
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
    stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
    {{ $attributes->merge(['class' => 'size-5 shrink-0']) }}
    @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>{!! \App\Support\Icons::inner($name) !!}</svg>
