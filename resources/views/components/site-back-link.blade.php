@props([
    'href',
    'label' => 'Back to previous page',
    'history' => false,
])

<a {{ $attributes->class(['site-back-button']) }} href="{{ $href }}" @if($history) data-site-back @endif aria-label="{{ $label }}">
    <svg class="site-back-button-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M19 12H5m7 7-7-7 7-7" />
    </svg>
    <span class="site-back-button-label">{{ $label }}</span>
</a>
