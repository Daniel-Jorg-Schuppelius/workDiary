{{--
  Created on   : Sun Oct 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _preview.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Original zum Abgleich der Werte (Detailseite, Werte-Dialog). Bilder direkt,
     PDF und XML-Sichtdarstellung im iframe: die Seiten-CSP sperrt object/embed.
     Weiß hinterlegt, weil die Sichtdarstellung keinen eigenen Hintergrund setzt. --}}
@php($previewUrl = route('finance.incoming-invoices.preview', $document))
@if (str_starts_with($previewMime, 'image/'))
    <div class="overflow-auto bg-base-200 {{ $height }}">
        <img src="{{ $previewUrl }}" alt="{{ __('Original der Rechnung') }}" class="w-full">
    </div>
@else
    {{-- Ohne Seitenleiste und auf Breite gezoomt; Viewer ohne diese Parameter ignorieren sie. --}}
    <iframe src="{{ $previewMime === 'application/pdf' ? $previewUrl . '#view=FitH&navpanes=0' : $previewUrl }}"
            class="block w-full bg-white {{ $height }}"
            title="{{ __('Original der Rechnung') }}"
            loading="lazy"></iframe>
@endif
