{{--
  Created on   : Thu May 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _header.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
        <div>{{ __('Erstellt am') }} {{ $generatedAt->fdatetime() }}</div>
        @if (! empty($extraMeta))
            <div>{{ $extraMeta }}</div>
        @endif
    </div>
</div>
