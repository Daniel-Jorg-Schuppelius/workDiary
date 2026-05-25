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
      - Innen: flex flex-nowrap items-center gap-2 overflow-x-auto — Filter
        bleiben in einer Zeile und scrollen horizontal wenn nötig.
      - Filter-Felder im Slot sollen `select-sm` / `input-sm` und `shrink-0`
        tragen (Größen-Standard `sm`, kein `xs`).
      - Aktionsblock rechts via `ml-auto shrink-0` (automatisch).

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
    <div class="flex flex-nowrap items-center gap-2 overflow-x-auto">
        {{ $slot }}

        <div class="ml-auto flex shrink-0 items-center gap-2">
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
