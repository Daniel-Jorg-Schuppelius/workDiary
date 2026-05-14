@php
    /**
     * Reusable header for print views.
     * Inputs: $title (string), $subtitle (string|null), $org (string|null), $generatedAt (Carbon|null)
     */
    $generatedAt = $generatedAt ?? now();
@endphp
<div class="header">
    <div>
        <div class="title">{{ $title }}</div>
        @if (! empty($subtitle))
            <div class="subtitle">{{ $subtitle }}</div>
        @endif
    </div>
    <div class="meta">
        @if (! empty($org))
            <div><strong>{{ $org }}</strong></div>
        @endif
        <div>{{ __('Erstellt am') }} {{ $generatedAt->format('d.m.Y H:i') }}</div>
        @if (! empty($extraMeta))
            <div>{{ $extraMeta }}</div>
        @endif
    </div>
</div>
