@extends('layouts.app')
@section('title', __('Materialien'))
@section('nav-title', __('Materialien'))
@section('content')
<x-page-shell>
    <x-filter-bar :action="route('materials.index')" :reset="$q !== '' ? route('materials.index') : null">
        <x-filter-field :label="__('Suche')" for="mat-q" class="flex-1 min-w-60">
            <input id="mat-q" type="search" name="q" value="{{ $q }}" placeholder="{{ __('Suche…') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
        <x-slot:extra>
            @can('create', \App\Models\Material::class)
                <a href="{{ route('materials.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary gap-1">
                    <x-icon name="add" /><span>{{ __('Material') }}</span>
                </a>
            @endcan
        </x-slot:extra>
    </x-filter-bar>

    <x-card padding="p-0">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <?php $p = ['q' => $q]; ?>
                        <th><x-sort-th column="sku" :route="route('materials.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'asc'">SKU</x-sort-th></th>
                        <th><x-sort-th column="name" :route="route('materials.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'asc'" default="name">{{ __('Name') }}</x-sort-th></th>
                        <th><x-sort-th column="unit" :route="route('materials.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Einheit') }}</x-sort-th></th>
                        <th class="text-right"><x-sort-th column="price" :route="route('materials.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('EP netto') }}</x-sort-th></th>
                        <th class="text-right"><x-sort-th column="tax" :route="route('materials.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'asc'">USt %</x-sort-th></th>
                        <th><x-sort-th column="provider" :route="route('materials.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Quelle') }}</x-sort-th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($materials as $m)
                        <tr>
                            <td>{{ $m->sku }}</td>
                            <td>{{ $m->name }}</td>
                            <td>{{ $m->unit }}</td>
                            <td class="text-right">{{ $m->default_unit_price !== null ? number_format((float)$m->default_unit_price, 4, ',', '.') : '—' }}</td>
                            <td class="text-right">{{ $m->tax_rate !== null ? rtrim(rtrim(number_format((float)$m->tax_rate, 2, ',', '.'), '0'), ',') : '—' }}</td>
                            <td>{{ $m->external_provider ?: 'local' }}</td>
                            <td class="text-right">
                                @can('update', $m)
                                    <a href="{{ route('materials.edit', $m) }}" data-entry-modal-trigger class="btn btn-xs">{{ __('Bearbeiten') }}</a>
                                @endcan
                                @can('delete', $m)
                                    <form method="POST" action="{{ route('materials.destroy', $m) }}" class="inline"
                                          data-confirm-dialog
                                          data-confirm-message="{{ __('Löschen?') }}"
                                          data-confirm-icon="delete"
                                          data-confirm-tone="error"
                                          data-confirm-label="{{ __('Löschen') }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-ghost text-error">×</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state :compact="true" :title="__('Noch keine Materialien')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-base-300 px-4 py-3">{{ $materials->links() }}</div>
    </x-card>
</x-page-shell>
@endsection
