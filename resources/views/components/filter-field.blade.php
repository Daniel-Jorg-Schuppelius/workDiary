{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : filter-field.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'label'     => null,
    'for'       => null,
    'class'     => '',
    'showLabel' => false,   // Label über dem Feld (Form-Bodies).
    'inline'    => false,   // Label links neben dem Feld (Filter-Bars mit
                            // Eingabefeldern: „60" allein sagt niemandem, dass
                            // hier Mindestminuten stehen).
])

{{-- Ohne sichtbares Label bleibt das Feld für Screenreader unbeschriftet —
     Selects tragen ihre Bedeutung in der „Alle …"-Option, Eingabefelder nicht.
     Daher immer ein Label rendern, im Zweifel sr-only. --}}
@if ($inline)
    <div class="flex shrink-0 items-center gap-2 {{ $class }}">
        @if ($label)
            <label @if ($for) for="{{ $for }}" @endif class="whitespace-nowrap text-sm text-base-content/75">{{ $label }}</label>
        @endif
        {{ $slot }}
    </div>
@else
    <div class="flex flex-col gap-1 {{ $class }}">
        @if ($label)
            <label @if ($for) for="{{ $for }}" @endif
                   @class([
                       'text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/60' => $showLabel,
                       'sr-only' => ! $showLabel,
                   ])>
                {{ $label }}
            </label>
        @endif
        {{ $slot }}
    </div>
@endif
