@props(['name', 'class' => 'size-5'])

<svg
    {{ $attributes->merge([
        'class' => $class,
        'fill' => 'none',
        'stroke' => 'currentColor',
        'stroke-width' => '1.8',
        'stroke-linecap' => 'round',
        'stroke-linejoin' => 'round',
        'aria-hidden' => 'true',
        'focusable' => 'false',
    ]) }}
>
    <use href="#nf-{{ $name }}"></use>
</svg>
