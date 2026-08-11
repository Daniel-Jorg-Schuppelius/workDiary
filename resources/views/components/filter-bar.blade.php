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
            <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit"
                        show-label>{{ $submitLabel ?? __('Filtern') }}</x-icon-btn>
            @if ($reset)
                <x-icon-btn icon="restart_alt" size="sm"
                            :href="$reset"
                            show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
            @endif
        </div>
    </div>
</form>
