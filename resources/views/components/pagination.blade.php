@props([
    'paginator',
    'framed' => true,
    'standing' => false,
])

{{--
    <x-pagination> — kanonischer Pagination-Block (App-Standard).

    Zeigt links eine Info-Zeile „Seite X / Y (N Einträge)" und rechts die
    Zurück/Weiter-Navigation im DaisyUI-Stil. Query-Parameter (Filter/Suche)
    bleiben beim Blättern erhalten. JEDE Seite, die paginiert, nutzt diese
    Komponente — keine rohen `$paginator->links()`-Aufrufe in den Views.

    Modi:
      - standing : true  → das EMPFOHLENE Standardverhalten für Index-/Listen-
                           seiten. Hebt den Block per @push('page-footer') aus dem
                           <main> heraus; das Layout rendert ihn als eigenes,
                           stehendes Panel UNTER dem main (volle Content-Breite,
                           scrollt nicht mit, Gegenstück zum Content-Header).
                           Sind keine Einträge da, wird NICHTS gepusht → der
                           Inhalt füllt wie gehabt.
        Wichtig: pro Seitenaufruf darf nur EIN <x-pagination standing> aktiv sein
        (Tabs sind app-weit serverseitig → immer nur ein Paginator pro Aufruf).

      - standing : false → Inline-Variante INNERHALB einer Karte / eines eigenen
                           Scroll-Containers. `framed` steuert die Optik:
                             framed=true  → eigener gerahmter Block
                             framed=false → nur obere Trennlinie

    Props:
      - paginator : LengthAwarePaginator (Pflicht)
      - standing  : false (Default) | true (stehendes Footer-Panel)
      - framed    : true (Default) — nur im Inline-Modus relevant
--}}

@php
    $hasEntries = $paginator && method_exists($paginator, 'total') && $paginator->total() > 0;
@endphp

@if ($hasEntries)
    @if ($standing)
        @push('page-footer')
            <div class="shrink-0 mt-(--sidebar-gap) max-md:px-1">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-(--panel-radius) border border-base-300 bg-base-100 px-4 py-2.5 shadow-xs">
                    <div class="text-xs text-base-content/60">
                        {{ __('Seite') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
                        ({{ $paginator->total() }} {{ __('Einträge') }})
                    </div>
                    @if ($paginator->hasPages())
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            {{ $paginator->withQueryString()->links('vendor.pagination.daisyui') }}
                        </div>
                    @endif
                </div>
            </div>
        @endpush
    @else
        <div class="flex-none">
            <div class="mb-1 text-xs text-base-content/60">
                {{ __('Seite') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
                ({{ $paginator->total() }} {{ __('Einträge') }})
            </div>
            @if ($paginator->hasPages())
                <div {{ $attributes->class([
                    'rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs' => $framed,
                    'border-t border-base-300 px-4 py-3' => ! $framed,
                ]) }}>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        {{ $paginator->withQueryString()->links('vendor.pagination.daisyui') }}
                    </div>
                </div>
            @endif
        </div>
    @endif
@endif
