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
        <x-table table-sort="server"
                 :route="route('materials.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'asc'"
                 :sort-params="['q' => $q]"
                 bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort="sku">SKU</x-table.th>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort="unit">{{ __('Einheit') }}</x-table.th>
                    <x-table.th sort="price" align="right">{{ __('EP netto') }}</x-table.th>
                    <x-table.th sort="tax" align="right">USt %</x-table.th>
                    <x-table.th sort="provider">{{ __('Quelle') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
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
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>' :colspan="7" :title="__('Noch keine Materialien')" compact />
            @endforelse
        </x-table>
        @if ($materials->hasPages())
            <div class="border-t border-base-300 px-4 py-3">{{ $materials->links() }}</div>
        @endif
    </x-card>
</x-page-shell>
@endsection
