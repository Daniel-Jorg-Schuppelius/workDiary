{{-- Wiederverwendbarer Pagination-Block für Duties-Tabs --}}
<div class="flex-none">
    <p class="mb-1 text-xs text-base-content/60">
        {{ __('Seite') }} {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }} · {{ $paginator->total() }} {{ __('Einträge') }}
    </p>
    @if ($paginator->hasPages())
        <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
            {{ $paginator->links('vendor.pagination.daisyui-simple') }}
        </div>
    @endif
</div>
