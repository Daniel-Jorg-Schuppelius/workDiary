@props([
    'paginator',
    'framed' => true,
])

{{--
    <x-pagination> — kanonischer Pagination-Block (Vorbild: Legacy-Bereich).

    Zeigt links eine Info-Zeile "Seite X / Y (N Einträge)" und rechts die
    Zurück/Weiter-Navigation im DaisyUI-Stil (vendor.pagination.daisyui-simple).
    Query-Parameter (Filter/Suche) bleiben beim Blättern erhalten.

    Props:
      - paginator : LengthAwarePaginator (Pflicht)
      - framed    : true (Default) → eigener gerahmter Block (für direkte page-shell-Nutzung)
                    false → nur obere Trennlinie (für Pagination INNERHALB einer <x-card>)
--}}

@if ($paginator && method_exists($paginator, 'total') && $paginator->total() > 0)
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
