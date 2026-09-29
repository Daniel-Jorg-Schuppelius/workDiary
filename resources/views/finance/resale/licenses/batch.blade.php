{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : batch.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einkaufspaket (MVP-1024): Bestand des Pakets, Einzellizenzen mit Status
  und die Verkaufshistorie einschließlich berichtigter und zurückgenommener
  Zuordnungen.
--}}

@extends('layouts.app')

@section('title', __('resale.license.batch_title', ['reference' => $batch->reference]))
@section('nav-title', __('resale.title.menu'))

@section('content')
    <x-index-page :subtitle="$batch->product->name . ' · ' . __('resale.license.batch_subtitle', ['date' => $batch->purchased_on->fdate(), 'count' => $batch->quantity])"
                  back-route="finance.resale.licenses.index" :back-label="__('resale.license.title')">
        <x-slot:actions>
            @can(\App\Enums\User\Permission::ResellingManage->value)
                @if ($counts['available'] > 0)
                    <x-icon-btn icon="add_shopping_cart" tone="primary" size="sm" placement="bar" data-entry-modal-trigger
                                :href="route('finance.resale.licenses.sell.create', ['product' => $batch->product->sqid])"
                                show-label>{{ __('resale.license.action.sell') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="upload" size="sm" data-entry-modal-trigger
                            :href="route('finance.resale.licenses.batches.import.create', $batch)"
                            show-label>{{ __('resale.license.action.import') }}</x-icon-btn>
                <x-icon-btn icon="download" size="sm" placement="menu"
                            :href="route('finance.resale.licenses.batches.template', $batch)"
                            show-label>{{ __('resale.license.action.template') }}</x-icon-btn>
                <x-action-form :action="route('finance.resale.licenses.batches.destroy', $batch)" method="DELETE"
                               :confirm="__('resale.license.confirm.delete_batch', ['reference' => $batch->reference])"
                               confirm-icon="delete" confirm-tone="error" :confirm-label="__('resale.license.action.delete_batch')">
                    <x-button placement="danger" type="submit" tone="error" size="sm" class="btn-outline" icon="delete">{{ __('resale.license.action.delete_batch') }}</x-button>
                </x-action-form>
            @endcan
        </x-slot:actions>

        @include('finance.resale._tabs')

        <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-5">
            <x-kpi-tile :label="__('resale.license.field.purchased')" :value="$counts['purchased']" />
            <x-kpi-tile :label="__('resale.license.status.available')" :value="$counts['available']" tone="success" />
            <x-kpi-tile :label="__('resale.license.status.sold')" :value="$counts['sold']" />
            <x-kpi-tile :label="__('resale.license.status.incomplete')" :value="$counts['incomplete']" :tone="$counts['incomplete'] > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('resale.license.status.blocked')" :value="$counts['blocked']" :tone="$counts['blocked'] > 0 ? 'error' : 'neutral'" />
        </div>

        <x-card class="mb-4">
            <x-detail-grid>
                <x-detail-grid.row :label="__('resale.license.field.supplier')" :value="$batch->supplierLabel() ?? '—'" />
                <x-detail-grid.row :label="__('resale.license.field.document')" :value="$batch->document_reference ?? '—'" />
                <x-detail-grid.row :label="__('resale.license.field.key_roles')" :value="implode(', ', array_column($batch->keyRoles(), 'label'))" />
                @if ($batch->note)
                    <x-detail-grid.row :label="__('resale.license.field.note')" :value="$batch->note" />
                @endif
            </x-detail-grid>
        </x-card>

        <x-card :title="__('resale.license.units_title')" icon="key" padding="p-0" class="mb-4">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('resale.license.field.license') }}</th>
                        <th>{{ __('resale.license.field.status') }}</th>
                        <th>{{ __('resale.license.field.keys') }}</th>
                        <th>{{ __('resale.license.field.holder') }}</th>
                        <th>{{ __('resale.license.field.sold_on') }}</th>
                        <th>{{ __('resale.license.field.invoice_reference') }}</th>
                        <th class="text-right"></th>
                    </tr>
                </x-slot:head>
                @foreach ($units as $unit)
                    @include('finance.resale.licenses._unit_row', ['unit' => $unit, 'showProduct' => false])
                @endforeach
            </x-table>
            <x-pagination :paginator="$units" />
        </x-card>

        <x-card :title="__('resale.license.history_title')" icon="history" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('resale.license.field.license') }}</th>
                        <th>{{ __('resale.license.field.holder') }}</th>
                        <th>{{ __('resale.license.field.sold_on') }}</th>
                        <th>{{ __('resale.license.field.invoice_reference') }}</th>
                        <th>{{ __('resale.license.field.ended') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($history as $entry)
                    <tr>
                        <td class="whitespace-nowrap">#{{ $entry->unit->position }}</td>
                        <td class="text-sm">{{ $entry->holderLabel() }}</td>
                        <td class="tabular-nums text-sm">{{ $entry->sold_on->fdate() }}</td>
                        <td class="text-sm">{{ $entry->invoice_reference ?? '—' }}</td>
                        <td class="text-sm">
                            @if ($entry->end_kind !== null)
                                <x-status-badge size="xs" :tone="$entry->end_kind === \App\Enums\Reselling\LicenseAssignmentEnd::Returned ? 'warning' : 'info'" :label="$entry->end_kind->label()" />
                                <span class="text-muted">{{ $entry->ended_at?->fdate() }} · {{ $entry->end_reason }}</span>
                            @else
                                <x-status-badge size="xs" tone="success" :label="__('resale.license.history_active')" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" icon="history" :title="__('resale.license.empty.history')" compact />
                @endforelse
            </x-table>
        </x-card>
    </x-index-page>
@endsection
