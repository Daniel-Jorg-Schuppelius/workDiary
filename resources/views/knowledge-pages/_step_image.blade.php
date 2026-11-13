{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _step_image.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bild eines Anleitungsschritts (Feature 191, MVP-1668) mit Markierungen als
  Überlagerung (resources/js/guide-editor.js zeichnet sie auf ein Canvas).
  Nummern und Texte stehen zusätzlich als Liste darunter — für Screenreader
  und ohne Skript. Variablen: $image (URL|null), $markers, $alt.
--}}
@php
    $labelled = array_values(array_filter($markers, static fn ($marker): bool => is_array($marker) && in_array($marker['kind'] ?? '', ['number', 'text'], true) && ($marker['label'] ?? '') !== ''));
@endphp
@if ($image)
    <div class="relative inline-block max-w-full overflow-hidden rounded-box border border-base-300" data-guide-image
         data-markers="{{ \CommonToolkit\Helper\Data\JsonHelper::encode(array_values($markers)) }}">
        <img src="{{ $image }}" alt="{{ $alt }}" class="block h-auto max-w-full" loading="lazy" data-guide-picture>
        <canvas class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true" data-guide-canvas></canvas>
    </div>
@else
    <x-empty-state compact icon="hide_image" :title="__('knowledge_pages.guide.no_image')" />
@endif
@if ($labelled !== [])
    <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="{{ __('knowledge_pages.guide.markers') }}">
        @foreach ($labelled as $marker)
            <li><x-icon :name="($marker['kind'] ?? '') === 'number' ? 'counter_1' : 'text_fields'" /> {{ $marker['label'] }}</li>
        @endforeach
    </ul>
@endif
