{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rückrufaktion (MVP-921/922). Erwartet: $recall, $items, $stats, $dispatches, $previewDeliveries, $previewStock --}}
@extends('layouts.app')

@section('title', (string) $recall->number)
@section('nav-title', __('recall.title'))

@section('content')
<x-page-shell>
    <x-validation-errors />

    <x-slot:toolbar>
        <x-page-toolbar :title="$recall->number . ' — ' . $recall->title"
                        back-route="recalls.index" :back-label="__('recall.title')">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline :tone="$recall->status->tone()">{{ $recall->status->label() }}</x-status-badge>
                <x-status-badge tone="plain" size="md" outline>{{ $recall->kind->label() }}</x-status-badge>
            </div>
            <x-slot:actions>
                <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('recalls.authority.pdf', $recall)" show-label>{{ __('recall.authority.pdf') }}</x-icon-btn>
                @can('update', $recall)
                    <x-icon-btn icon="gavel" size="sm" data-entry-modal-trigger :href="route('recalls.authority.edit', $recall)" show-label>{{ __('recall.authority.title') }}</x-icon-btn>
                @endcan
                @can('update', $recall)
                    @if ($recall->status === \App\Enums\Inventory\RecallStatus::Draft)
                        <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('recalls.edit', $recall)" show-label>{{ __('recall.action.edit') }}</x-icon-btn>
                    @endif
                    @if ($recall->status === \App\Enums\Inventory\RecallStatus::Active && ($stats['open'] ?? 0) > 0)
                        <x-action-form :action="route('recalls.notify', $recall)" :confirm="__('recall.confirm.notify')" confirm-icon="mail" confirm-tone="warning" :confirm-label="__('recall.action.notify')">
                            <x-button type="submit" size="sm" tone="secondary" icon="mail">{{ __('recall.action.notify') }}</x-button>
                        </x-action-form>
                    @endif
                    @foreach ($recall->status->allowedTransitions() as $target)
                        <x-action-form :action="route('recalls.transition', $recall)" :confirm="__('recall.confirm.' . $target->value)" confirm-icon="campaign" :confirm-tone="$target === \App\Enums\Inventory\RecallStatus::Cancelled ? 'error' : 'warning'" :confirm-label="__('recall.transition.' . $target->value)">
                            <input type="hidden" name="status" value="{{ $target->value }}">
                            <x-button type="submit" size="sm" :tone="$target === \App\Enums\Inventory\RecallStatus::Cancelled ? 'error' : 'primary'"
                                      :placement="$target === \App\Enums\Inventory\RecallStatus::Cancelled ? 'danger' : 'bar'">{{ __('recall.transition.' . $target->value) }}</x-button>
                        </x-action-form>
                    @endforeach
                @endcan
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-card :title="__('recall.section.recall')" icon="campaign" class="lg:col-span-2">
            <x-detail-grid class="grid-cols-2">
                <x-detail-grid.row :label="__('recall.field.variant')">{{ $recall->variant?->article?->name }}{{ $recall->variant?->sku ? ' · ' . $recall->variant->sku : '' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('recall.field.is_blocking_stock')">{{ $recall->is_blocking_stock ? __('recall.yes') : __('recall.no') }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('recall.field.delivered_from')">{{ $recall->delivered_from?->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('recall.field.delivered_until')">{{ $recall->delivered_until?->fdate() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('recall.field.serial_numbers')">{{ $recall->serial_numbers ? implode(', ', $recall->serial_numbers) : '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('recall.field.activated_at')">{{ $recall->activated_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
            </x-detail-grid>
            <p class="mt-3 whitespace-pre-line text-sm">{{ $recall->reason }}</p>
            @if ($recall->customer_message)
                <div class="mt-3 rounded-box border border-base-300 p-3 text-sm">
                    <div class="text-xs text-muted">{{ __('recall.field.customer_message') }}</div>
                    <p class="whitespace-pre-line">{{ $recall->customer_message }}</p>
                </div>
            @endif
        </x-card>

        <x-attachments-section :attachments="$recall->attachments" upload-type="recall" :upload-id="$recall->sqid" :can-upload="Gate::allows('update', $recall)" />
    </div>

    @if ($recall->status === \App\Enums\Inventory\RecallStatus::Draft)
        <x-card :title="__('recall.section.preview')" icon="preview" :subtitle="__('recall.preview.summary', ['deliveries' => $previewDeliveries->count(), 'stock' => (int) $previewStock])" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('recall.field.delivered_at') }}</th>
                        <th>{{ __('recall.field.customer') }}</th>
                        <th class="text-right">{{ __('recall.field.quantity') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($previewDeliveries as $delivery)
                    <tr>
                        <td>{{ $delivery->delivered_at?->fdatetime() ?? '—' }}</td>
                        <td>{{ $delivery->customer?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $delivery->quantity?->format() ?? '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty icon="campaign" :colspan="3" :title="__('recall.preview.none')" compact />
                @endforelse
            </x-table>
        </x-card>
    @else
        {{-- Auswertung (MVP-922) --}}
        @php($total = array_sum($stats))
        <div class="grid gap-4 sm:grid-cols-5">
            @foreach (\App\Enums\Inventory\RecallItemStatus::cases() as $st)
                <x-kpi-tile :label="$st->label()" :value="$stats[$st->value] ?? 0" />
            @endforeach
            <x-kpi-tile :label="__('recall.stats.return_rate')" :value="$total > 0 ? intdiv(100 * (($stats['returned'] ?? 0) + ($stats['resolved'] ?? 0)), $total) . ' %' : '—'" />
        </div>
        <x-card :title="__('recall.section.items')" icon="groups" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('recall.field.customer') }}</th>
                        <th>{{ __('recall.field.delivered_at') }}</th>
                        <th>{{ __('recall.field.serial') }}</th>
                        <th class="text-right">{{ __('recall.field.quantity') }}</th>
                        <th>{{ __('recall.field.status') }}</th>
                        <th>{{ __('recall.field.claim') }}</th>
                        <th class="text-right">{{ __('recall.field.actions') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->customer?->name ?? '—' }}</td>
                        <td>{{ $item->delivery?->delivered_at?->fdatetime() ?? '—' }}</td>
                        <td class="font-mono">{{ $item->serial?->serial_no ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $item->quantity !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($item->quantity, 4, trimTrailingZeros: true) : '—' }}</td>
                        <td><x-status-badge size="sm" outline :tone="$item->status->tone()">{{ $item->status->label() }}</x-status-badge></td>
                        <td>
                            @if ($item->claimCase !== null)
                                <a class="link font-mono" href="{{ route('claims.show', $item->claimCase) }}">{{ $item->claimCase->number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right">
                            @can('update', $recall)
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if ($item->claim_case_id === null && $item->status !== \App\Enums\Inventory\RecallItemStatus::Resolved)
                                        <form method="POST" action="{{ route('recalls.items.claim', [$recall, $item]) }}">
                                            @csrf
                                            <x-button type="submit" tone="ghost" size="xs" title="{{ __('recall.hint.claim') }}">{{ __('recall.action.claim') }}</x-button>
                                        </form>
                                    @endif
                                    @foreach ($item->status->allowedTransitions() as $target)
                                        <form method="POST" action="{{ route('recalls.items.status', [$recall, $item]) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $target->value }}">
                                            <x-button type="submit" tone="plain" size="xs">{{ __('recall.item_transition.' . $target->value) }}</x-button>
                                        </form>
                                    @endforeach
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon="campaign" :colspan="7" :title="__('recall.items_none')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('recall.section.dispatches')" icon="mail" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('recall.field.sent_at') }}</th>
                        <th>{{ __('recall.field.recipient') }}</th>
                        <th>{{ __('recall.field.status') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($dispatches as $dispatch)
                    <tr>
                        <td>{{ $dispatch->created_at?->fdatetime() }}</td>
                        <td>{{ $dispatch->recipient }}</td>
                        <td>{{ __('recall.dispatch.' . $dispatch->status->value) }}</td>
                    </tr>
                @empty
                    <x-table.empty icon="mail" :colspan="3" :title="__('recall.dispatch.none')" compact />
                @endforelse
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection
