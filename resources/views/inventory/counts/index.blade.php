@extends('layouts.app')
@section('title', __('inventory.count_ui.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.count_ui.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.count_ui.title')">
    <x-slot:actions>
        <x-icon-btn icon="inventory" size="sm" :href="route('inventory.stock')" show-label>{{ __('inventory.stock') }}</x-icon-btn>
    </x-slot:actions>

    @if ($warehouses->isEmpty())
        <x-empty-state framed :title="__('inventory.empty.warehouses')" />
    @else
        <div role="tablist" class="tabs tabs-box self-start">
            @foreach ($warehouses as $wh)
                <a role="tab" href="{{ route('inventory.counts.index', ['warehouse' => $wh->sqid]) }}"
                   class="tab {{ $selected && $selected->id === $wh->id ? 'tab-active' : '' }}">{{ $wh->name }}</a>
            @endforeach
        </div>

        @if (! $selected)
            <x-empty-state framed :title="__('inventory.count_ui.no_selection')" />
        @else
            @if ($canCount)
                <x-card>
                    <div class="flex flex-wrap items-end gap-3">
                        <form method="POST" action="{{ route('inventory.counts.open') }}">
                            @csrf
                            <input type="hidden" name="warehouse" value="{{ $selected->sqid }}">
                            <x-icon-btn icon="play_circle" tone="primary" size="sm" type="submit" show-label>{{ __('inventory.count_ui.open') }} — {{ $selected->name }}</x-icon-btn>
                        </form>

                        {{-- Zyklische Inventur nach ABC-Klasse (E6) --}}
                        <form method="POST" action="{{ route('inventory.counts.cycle') }}" class="flex items-end gap-2">
                            @csrf
                            <input type="hidden" name="warehouse" value="{{ $selected->sqid }}">
                            <div class="fieldset"><label class="fieldset-label">{{ __('inventory.count_ui.cycle') }}</label>
                                <select name="abc_class" class="select select-sm select-bordered">
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                </select></div>
                            <button type="submit" class="btn btn-sm">{{ __('inventory.count_ui.cycle_open') }}</button>
                        </form>
                    </div>
                </x-card>
            @endif

            <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
                <x-table bare scroll="flex" :pinRows="true">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('inventory.count_ui.counted_at') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($counts as $count)
                        <tr>
                            <td>{{ $count->counted_at?->format('d.m.Y H:i') }}</td>
                            <td><span class="badge badge-sm">{{ $count->status->label() }}</span></td>
                            <td class="text-right"><a href="{{ route('inventory.counts.show', $count) }}" class="link">{{ __('Öffnen') }}</a></td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="3"
                                       icon='<span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>'
                                       :title="__('inventory.count_ui.no_counts')" compact />
                    @endforelse
                </x-table>
            </x-card>
        @endif
    @endif
</x-index-page>
@endsection
