@extends('layouts.app')
@section('title', __('inventory.serial.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('inventory.serial.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('inventory.serial.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="qr_code_scanner" size="sm" :href="route('serials.verify')" show-label>{{ __('inventory.serial.action.verify') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('serials.index')">
        <x-filter-field :label="__('Suche')" for="ser-q" class="flex-1 min-w-60">
            <input id="ser-q" type="text" name="q" value="{{ $search }}" placeholder="{{ __('inventory.serial.verify.placeholder') }}"
                   class="input input-sm input-bordered">
        </x-filter-field>
        <x-filter-field :label="__('inventory.serial.field.status')" for="ser-status">
            <select id="ser-status" name="status" class="select select-sm select-bordered">
                <option value="all" @selected($status === 'all')>{{ __('Alle') }}</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
        <x-table bare scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('inventory.serial.field.serial_no') }}</th>
                    <th>{{ __('inventory.serial.field.article') }}</th>
                    <th>{{ __('inventory.serial.field.status') }}</th>
                    <th>{{ __('inventory.serial.field.customer') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($serials as $serial)
                <tr>
                    <td><a href="{{ route('serials.show', $serial) }}" class="link link-hover font-mono">{{ $serial->serial_no }}</a></td>
                    <td>{{ $serial->article?->name }}</td>
                    <td><span class="badge badge-sm badge-ghost">{{ $serial->status->label() }}</span></td>
                    <td>{{ $serial->customer?->name ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4"
                               icon='<span class="material-symbols-outlined" aria-hidden="true">qr_code_2</span>'
                               :title="__('inventory.serial.empty')" />
            @endforelse
        </x-table>
    </x-card>
    <div class="mt-3">{{ $serials->links() }}</div>
</x-index-page>
@endsection
