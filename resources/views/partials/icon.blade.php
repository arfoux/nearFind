@php
    $name = $name ?? null;
    $class = $class ?? 'size-5';
    $strokeWidth = $strokeWidth ?? '1.8';
@endphp
<svg class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <use href="#nf-{{ $name }}"></use>
</svg>
