{{--
  Created on   : Sun May 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : filter-bar.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'action'      => null,
    'method'      => 'GET',
    'reset'       => null,
    'submitLabel' => null,
])

{{--
    <x-filter-bar> — einzeilige Filter-/Suchleiste für Index-Seiten.

    Layout-Standard (Corporate Design):
      - Hülle: rounded-box border bg-base-100 shadow-xs (wie x-card).
      - Innen: flex flex-wrap items-center gap-2 — Filter bleiben in einer
        Zeile, solange sie passen, und brechen sonst um. Kein horizontales
        Scrollen: dabei rutschen die Filtern-/Zurücksetzen-Schalter aus dem
        Blickfeld, ohne dass es jemand bemerkt.
      - Filter-Felder im Slot sollen `select-sm` / `input-sm` und `shrink-0`
        tragen (Größen-Standard `sm`, kein `xs`).
      - Reihenfolge: Felder (order-0) → Schalter (`x-filter-toggle`, order-40)
        → Aktionsblock (order-50, `ml-auto shrink-0`, automatisch).

    Slots:
      - default : Filterfelder
      - extra   : zusätzliche Aktionen links der Filter-/Reset-Buttons
--}}

@php
    $methodUpper = strtoupper($method);
    $isGet = $methodUpper === 'GET';
    // Gespeicherte Filter je Liste (Bereich = Routenname); Standardfilter lädt ApplyDefaultFilterPreset.
    $presetScope = $isGet ? request()->route()?->getName() : null;
    $presetUser = auth()->user();
    $presets = $presetScope !== null && $presetUser instanceof \App\Models\Platform\User
        ? $presetUser->filterPresets()->where('scope', $presetScope)->get(['id', 'name', 'query', 'is_default'])
        : collect();
    $currentFilter = \Illuminate\Support\Arr::except(request()->query(), ['page', 'preset']);
    $resetHref = $reset !== null && $presets->contains('is_default', true)
        ? $reset . (str_contains($reset, '?') ? '&' : '?') . 'preset=none'
        : $reset;
@endphp

<form
    method="{{ $isGet ? 'GET' : 'POST' }}"
    @if ($action) action="{{ $action }}" @endif
    {{ $attributes->class(['flex min-h-16 flex-none flex-col justify-center rounded-box border border-base-300 bg-base-100 p-4 shadow-xs']) }}
>
    @if (! $isGet)
        @csrf
        @if (! in_array($methodUpper, ['POST'], true))
            @method($methodUpper)
        @endif
    @endif
    <div class="flex flex-wrap items-center gap-x-2 gap-y-3">
        {{ $slot }}

        <div class="order-50 ml-auto flex shrink-0 items-center gap-2">
            @isset($extra)
                {{ $extra }}
            @endisset
            @if ($presetScope !== null && $presetUser !== null && ($presets->isNotEmpty() || $currentFilter !== []))
                <details class="dropdown dropdown-end">
                    <summary class="btn btn-sm btn-ghost gap-1" aria-label="{{ __('Gespeicherte Filter') }}">
                        <x-icon name="bookmarks" /> <span class="hidden sm:inline">{{ __('Gespeicherte Filter') }}</span>
                    </summary>
                    <ul class="dropdown-content menu z-20 mt-1 w-64 rounded-box border border-base-300 bg-base-100 p-2 shadow">
                        @foreach ($presets as $preset)
                            <li>
                                <a href="{{ url()->current() . '?' . http_build_query((array) $preset->query) }}">
                                    @if ($preset->is_default)<x-icon name="star" class="text-warning" />@endif
                                    {{ $preset->name }}
                                </a>
                            </li>
                        @endforeach
                        @if ($currentFilter !== [])
                            <li>
                                <a href="{{ route('filter-presets.create', ['scope' => $presetScope, 'query' => $currentFilter]) }}" data-entry-modal-trigger>
                                    <x-icon name="bookmark_add" /> {{ __('Aktuellen Filter speichern') }}
                                </a>
                            </li>
                        @endif
                    </ul>
                </details>
            @endif
            <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit"
                        show-label>{{ $submitLabel ?? __('Filtern') }}</x-icon-btn>
            @if ($reset)
                <x-icon-btn icon="restart_alt" size="sm"
                            :href="$resetHref"
                            show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
            @endif
        </div>
    </div>
</form>
