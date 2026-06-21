@extends('layouts.app')
@section('title', ($order->number ?? __('manufacturing.order.title')) . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('manufacturing.order.title'))

@php
    /** @var \App\Models\ManufacturingOrder $order */
    $status = $order->status->value;
    $isOpen = ! $order->status->isTerminal();
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$order->article?->name . ($order->variant ? ' — ' . ($order->variant->name ?? $order->variant->option_signature) : '')">
            <x-slot:actions>
                @if ($canManage)
                    @if ($status === 'draft')
                        <form method="POST" action="{{ route('manufacturing-orders.release', $order) }}">@csrf
                            <x-icon-btn icon="check_circle" tone="primary" size="sm" type="submit" show-label>{{ __('manufacturing.order.action.release') }}</x-icon-btn>
                        </form>
                    @endif
                    @if (in_array($status, ['released', 'waiting', 'blocked'], true))
                        <form method="POST" action="{{ route('manufacturing-orders.start', $order) }}">@csrf
                            <x-icon-btn icon="play_arrow" tone="primary" size="sm" type="submit" show-label>{{ __('manufacturing.order.action.start') }}</x-icon-btn>
                        </form>
                    @endif
                    @if (in_array($status, ['released', 'in_progress'], true))
                        <form method="POST" action="{{ route('manufacturing-orders.reserve', $order) }}">@csrf
                            <x-icon-btn icon="inventory" size="sm" type="submit" show-label>{{ __('manufacturing.order.action.reserve') }}</x-icon-btn>
                        </form>
                    @endif
                    @if ($isOpen)
                        <x-action-form :action="route('manufacturing-orders.cancel', $order)" :confirm="__('manufacturing.order.action.cancel').'?'">
                            <x-icon-btn icon="cancel" tone="error" size="sm" type="submit" :title="__('manufacturing.order.action.cancel')" />
                        </x-action-form>
                    @endif
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    {{-- Kopfdaten --}}
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="font-mono text-sm opacity-70">{{ $order->number ?? '—' }}</div>
                <div class="text-sm opacity-70 mt-1">{{ __('manufacturing.order.field.target_qty') }}: <strong>{{ $order->target_qty }} {{ $order->unit }}</strong>
                    · {{ __('manufacturing.order.field.good') }}: <strong>{{ $order->goodTotal() }}</strong></div>
            </div>
            <span class="badge badge-sm">{{ $order->status->label() }}</span>
        </div>
    </x-card>

    {{-- Material --}}
    <x-card padding="p-0">
        <h2 class="font-semibold p-4 pb-0">{{ __('manufacturing.order.field.materials') }}</h2>
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Artikel') }}</th>
                    <th class="text-right">{{ __('manufacturing.order.field.target_qty') }}</th>
                    <th class="text-right">{{ __('inventory.field.reserved') }}</th>
                    <th class="text-right">{{ __('manufacturing.order.field.produced') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($order->materials as $material)
                <tr>
                    <td>{{ $material->name_snapshot }}</td>
                    <td class="text-right tabular-nums">{{ $material->target_qty }} {{ $material->unit_snapshot }}</td>
                    <td class="text-right tabular-nums">{{ $material->reserved_qty }}</td>
                    <td class="text-right tabular-nums">{{ $material->consumed_qty }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon="build" :title="__('article.no_options')" />
            @endforelse
        </x-table>
    </x-card>

    {{-- Fremdfertigung (E7) --}}
    @if ($canManage && $status === 'draft' && $suppliers->isNotEmpty())
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('manufacturing.order.action.subcontract') }}</h2>
            <form method="POST" action="{{ route('manufacturing-orders.subcontract', $order) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset grow"><label class="fieldset-label">{{ __('procurement.field.supplier') }}</label>
                    <select name="supplier" class="select select-sm select-bordered w-full" required>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->sqid }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select></div>
                <button type="submit" class="btn btn-sm">{{ __('manufacturing.order.action.subcontract') }}</button>
            </form>
        </x-card>
    @endif

    {{-- Arbeitsplatz / Kapazität (E7) --}}
    @if ($canManage && $isOpen && $workCenters->isNotEmpty())
        <x-card>
            <h2 class="font-semibold mb-3">{{ __('manufacturing.capacity.assign') }}</h2>
            <form method="POST" action="{{ route('manufacturing-orders.work-center', $order) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="fieldset grow"><label class="fieldset-label">{{ __('manufacturing.capacity.work_center') }}</label>
                    <select name="work_center" class="select select-sm select-bordered w-full" required>
                        @foreach ($workCenters as $wc)
                            <option value="{{ $wc->sqid }}" @selected($order->work_center_id === $wc->id)>{{ $wc->name }}</option>
                        @endforeach
                    </select></div>
                <div class="fieldset"><label class="fieldset-label">{{ __('manufacturing.capacity.minutes') }}</label>
                    <input name="minutes" type="number" min="0" value="{{ $order->planned_minutes ?? 0 }}" class="input input-sm input-bordered w-24"></div>
                <div class="fieldset"><label class="fieldset-label">{{ __('manufacturing.capacity.day') }}</label>
                    <input name="day" type="date" value="{{ $order->planned_start?->toDateString() }}" class="input input-sm input-bordered"></div>
                <button type="submit" class="btn btn-sm">{{ __('manufacturing.capacity.assign') }}</button>
            </form>
        </x-card>
    @endif

    {{-- Rückmeldung + Auslieferung --}}
    @if ($canManage && in_array($status, ['in_progress', 'released'], true))
        <div class="grid md:grid-cols-2 gap-4">
            <x-card>
                <h2 class="font-semibold mb-3">{{ __('manufacturing.order.action.report') }}</h2>
                <form method="POST" action="{{ route('manufacturing-orders.report', $order) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset"><label class="fieldset-label">{{ __('manufacturing.order.field.produced') }}</label>
                        <input name="produced_qty" type="number" step="0.0001" min="0" value="0" class="input input-sm input-bordered w-24"></div>
                    <div class="fieldset"><label class="fieldset-label">{{ __('manufacturing.order.field.good') }}</label>
                        <input name="good_qty" type="number" step="0.0001" min="0" value="0" class="input input-sm input-bordered w-24"></div>
                    <div class="fieldset"><label class="fieldset-label">{{ __('manufacturing.order.field.scrap') }}</label>
                        <input name="scrap_qty" type="number" step="0.0001" min="0" value="0" class="input input-sm input-bordered w-24"></div>
                    <div class="fieldset"><label class="fieldset-label">{{ __('manufacturing.order.field.rework') }}</label>
                        <input name="rework_qty" type="number" step="0.0001" min="0" value="0" class="input input-sm input-bordered w-24"></div>
                    <x-button type="submit" tone="primary" size="sm">{{ __('manufacturing.order.action.report') }}</x-button>
                </form>
            </x-card>
            <x-card>
                <h2 class="font-semibold mb-3">{{ __('manufacturing.order.action.deliver') }}</h2>
                <form method="POST" action="{{ route('manufacturing-orders.deliver', $order) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset"><label class="fieldset-label">{{ __('manufacturing.order.field.quantity') }}</label>
                        <input name="quantity" type="number" step="0.0001" min="0.0001" class="input input-sm input-bordered w-28"></div>
                    <button type="submit" class="btn btn-sm">{{ __('manufacturing.order.action.deliver') }}</button>
                </form>
            </x-card>
        </div>
    @endif

    {{-- Rückmeldungen --}}
    @if ($order->reports->isNotEmpty())
        <x-card padding="p-0">
            <h2 class="font-semibold p-4 pb-0">{{ __('manufacturing.order.field.reports') }}</h2>
            <x-table bare class="table-sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('manufacturing.order.field.produced') }}</th>
                        <th>{{ __('manufacturing.order.field.good') }}</th>
                        <th>{{ __('manufacturing.order.field.scrap') }}</th>
                        <th>{{ __('manufacturing.order.field.rework') }}</th>
                        <th>{{ __('Zeitpunkt') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($order->reports as $report)
                    <tr>
                        <td class="tabular-nums">{{ $report->produced_qty }}</td>
                        <td class="tabular-nums">{{ $report->good_qty }}</td>
                        <td class="tabular-nums">{{ $report->scrap_qty }}</td>
                        <td class="tabular-nums">{{ $report->rework_qty }}</td>
                        <td>{{ $report->reported_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection
