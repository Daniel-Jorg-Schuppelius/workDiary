@extends('layouts.app')
@section('title', __('Materialien'))
@section('content')
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Materialien') }}</h1>
        <div class="flex gap-2">
            <form method="GET" class="flex gap-2">
                <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Suche…') }}" class="input input-sm input-bordered">
                <button class="btn btn-sm">{{ __('Suchen') }}</button>
            </form>
            @can('create', \App\Models\Material::class)
                <a href="{{ route('materials.create') }}" class="btn btn-sm btn-primary">+ {{ __('Material') }}</a>
            @endcan
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
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
                                    <a href="{{ route('materials.edit', $m) }}" class="btn btn-xs">{{ __('Bearbeiten') }}</a>
                                @endcan
                                @can('delete', $m)
                                    <form method="POST" action="{{ route('materials.destroy', $m) }}" class="inline" onsubmit="return confirm('{{ __('Löschen?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-ghost text-error">×</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-3 text-sm text-base-content/60">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-base-300 px-4 py-3">{{ $materials->links() }}</div>
    </div>
</div>
@endsection
