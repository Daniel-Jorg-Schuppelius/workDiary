@props([
    'paginator',
    'showLinks' => true,
])

@if ($paginator && method_exists($paginator, 'total') && $paginator->total() > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between']) }}>
        <p class="text-sm text-base-content/70">
            {{ __('Eintrag :from–:to von :total', [
                'from'  => $paginator->firstItem() ?? 0,
                'to'    => $paginator->lastItem() ?? 0,
                'total' => $paginator->total(),
            ]) }}
        </p>

        @if ($showLinks)
            <div>
                {{ $paginator->onEachSide(1)->withQueryString()->links() }}
            </div>
        @endif
    </div>
@endif
