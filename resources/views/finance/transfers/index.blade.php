{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Faktura-Übergabe (Feature 045, Teil B): Liste aller Übergabenachweise mit
  Filtern (Kunde, Kanal, Status, Zeitraum) und Modal-Anlage.
--}}

@extends('layouts.app')

@section('title', __('finance.title.transfers'))
@section('nav-title', __('finance.title.transfers'))

@section('content')
    <x-index-page :subtitle="__('finance.subtitle.transfers')">
        <x-slot:actions>
            @if ($canCreate)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('finance.transfers.create')"
                            show-label>{{ __('finance.action.create_draft') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <x-filter-bar :action="route('finance.transfers.index')"
                      :reset="$hasActiveFilters ? route('finance.transfers.index') : null">
            <x-filter-field :label="__('finance.field.customer')" for="finance-filter-customer" class="min-w-44">
                <select id="finance-filter-customer" name="customer" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach ($customers as $c)
                        <option value="{{ $c->sqid }}" @selected($filters['customer'] === $c->sqid)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('finance.field.channel')" for="finance-filter-channel" class="min-w-40">
                <select id="finance-filter-channel" name="channel" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('finance.filter.all') }}</option>
                    @foreach (\App\Enums\Finance\TransferChannel::cases() as $channel)
                        <option value="{{ $channel->value }}" @selected($filters['channel'] === $channel->value)>{{ $channel->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('finance.field.status')" for="finance-filter-status" class="min-w-40">
                <select id="finance-filter-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('finance.filter.all') }}</option>
                    @foreach (\App\Enums\Finance\TransferStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>

        </x-filter-bar>

        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('finance.field.customer') }}</th>
                    <th>{{ __('finance.field.channel') }}</th>
                    <th>{{ __('finance.field.target') }}</th>
                    <th>{{ __('finance.field.period') }}</th>
                    <th class="text-right">{{ __('finance.field.position_count') }}</th>
                    <th class="text-right">{{ __('finance.field.total_amount') }}</th>
                    <th>{{ __('finance.field.status') }}</th>
                    <th>{{ __('finance.field.payload_hash') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($transfers as $transfer)
                <tr class="hover" id="billing-transfer-{{ $transfer->id }}">
                    <td class="font-medium">{{ $transfer->customer?->name ?? '—' }}</td>
                    <td><x-status-badge :tone="$transfer->channel->tone()" outline>{{ $transfer->channel->label() }}</x-status-badge></td>
                    <td><x-status-badge :tone="$transfer->target->tone()" outline>{{ $transfer->target->label() }}</x-status-badge></td>
                    <td class="text-base-content/70">
                        {{ $transfer->period_from?->format('d.m.Y') ?? '—' }} – {{ $transfer->period_to?->format('d.m.Y') ?? '—' }}
                    </td>
                    <td class="text-right tabular-nums">{{ $transfer->position_count }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $transfer->total_amount, 2, withThousandsSeparator: true) }}</td>
                    <td><x-status-badge :tone="$transfer->status->tone()">{{ $transfer->status->label() }}</x-status-badge></td>
                    <td>
                        <span class="font-mono text-xs text-muted" title="{{ $transfer->payload_hash }}">
                            {{ \Illuminate\Support\Str::limit($transfer->payload_hash, 12, '…') }}
                        </span>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" tone="outline" size="xs"
                                        :href="route('finance.transfers.show', $transfer)"
                                        :label="__('finance.action.show')" />
                            @if ($transfer->file_path !== null && $transfer->status === \App\Enums\Finance\TransferStatus::Transferred)
                                <x-icon-btn icon="download" tone="outline" size="xs"
                                            :href="route('finance.transfers.download', $transfer)"
                                            :label="__('finance.action.download')" />
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="9"
                               :title="__('finance.empty_title')"
                               :message="$hasActiveFilters ? __('finance.empty_filtered') : __('finance.empty_message')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$transfers" standing />
    </x-index-page>
@endsection
