@extends('layouts.app')

@section('title', __('print.orders.title'))
@section('nav-title', __('print.orders.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('print.orders.subtitle')">
    <x-slot:actions>
        @can('create', \App\Models\Print\PrintOrder::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('print-orders.create')"
                        show-label>{{ __('print.orders.action.create') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-kpi-tile :label="__('print.orders.kpi.open')" :value="$openCount" />
    </div>

    <x-filter-bar :action="route('print-orders.index')" :reset="route('print-orders.index')">
        <select name="status" class="select select-sm select-bordered w-52 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach (\App\Enums\Print\PrintOrderStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
        <x-table bare scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('print.field.article') }}</th>
                    <th class="text-right">{{ __('print.field.quantity') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('print.field.preflight') }}</th>
                    <th>{{ __('print.field.output_kind') }}</th>
                    <th>{{ __('print.field.due_at') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($orders as $order)
                @php $mo = $order->manufacturingOrder; @endphp
                <tr>
                    <td><a href="{{ route('print-orders.show', $order) }}" class="link font-mono">{{ $mo->number ?? '—' }}</a></td>
                    <td>{{ $mo->article->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $mo?->target_qty }}</td>
                    <td><x-status-badge size="md" outline :tone="$order->status->tone()">{{ $order->status->label() }}</x-status-badge></td>
                    <td><x-status-badge size="md" outline :tone="$order->preflight_status->tone()">{{ $order->preflight_status->label() }}</x-status-badge></td>
                    <td>{{ $order->output_kind->label() }}</td>
                    <td class="whitespace-nowrap">{{ optional($mo?->due_at)->fdatetime() ?? '—' }}</td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('print-orders.show', $order)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">print</span>' :colspan="8" :title="__('print.orders.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$orders" standing />
</x-index-page>
@endsection
