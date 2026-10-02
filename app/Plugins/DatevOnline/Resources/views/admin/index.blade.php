{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', 'DATEV-Online')
@section('nav-title', 'DATEV-Online')

@section('content')
@php($connected = $connection && $connection->isActive())
<x-index-page
    :subtitle="__('datev-online::datev.page.subtitle')"
    :badge="$connected ? __('datev-online::datev.connection_status.active') : __('datev-online::datev.connection_status.disconnected')"
    :badge-tone="$connected ? 'success' : 'ghost'">

    <x-slot:badges>
        @if ($sandbox)
            <x-status-badge tone="warning" size="sm">{{ __('datev-online::datev.page.sandbox') }}</x-status-badge>
        @endif
    </x-slot:badges>
    <x-slot:actions>
        @if ($connected)
            <x-action-form :action="route('admin.datev-online.disconnect')" :confirm="__('datev-online::datev.page.disconnect_confirm')">
                <x-button type="submit" tone="ghost" placement="danger">{{ __('datev-online::datev.page.disconnect') }}</x-button>
            </x-action-form>
        @elseif ($configured)
            <x-action-form :action="route('admin.datev-online.oauth.start')">
                <x-button type="submit" placement="bar">{{ __('datev-online::datev.page.connect') }}</x-button>
            </x-action-form>
        @endif
    </x-slot:actions>

    @if ($errors->any())
        <div class="alert alert-error text-sm">{{ $errors->first() }}</div>
    @endif
    @unless ($configured)
        <div class="alert alert-warning text-sm">{{ __('datev-online::datev.page.not_configured') }}</div>
    @endunless

    @if ($connected)
        <x-card :title="__('datev-online::datev.page.client.heading')">
            @if ($clientsError !== null)
                <div class="alert alert-error text-sm">{{ __('datev-online::datev.page.client.error', ['class' => $clientsError]) }}</div>
            @elseif ($clients === [])
                <p class="text-sm text-muted">{{ __('datev-online::datev.page.client.none') }}</p>
            @else
                <form method="POST" action="{{ route('admin.datev-online.client') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <x-select-field name="datev_client" :label="__('datev-online::datev.page.client.choose')" required>
                        @foreach ($clients as $client)
                            <option value="{{ $client['id'] }}" @selected($connection->datev_client_number === $client['id'])>{{ $client['id'] }} · {{ $client['name'] }}</option>
                        @endforeach
                    </x-select-field>
                    <x-button type="submit">{{ __('datev-online::datev.page.client.save') }}</x-button>
                </form>
            @endif
        </x-card>

        @if ($connection->datev_client_number !== null)
            <x-card :title="__('datev-online::datev.page.documents.heading')" class="mt-4">
                <p class="text-sm text-muted">{{ __('datev-online::datev.page.documents.hint') }}</p>
                <form method="POST" action="{{ route('admin.datev-online.documents') }}" class="mt-2 flex flex-wrap items-end gap-3">
                    @csrf
                    <x-checkbox-field name="is_documents_enabled" value="1" :label="__('datev-online::datev.page.documents.enabled')" :checked="$connection->is_documents_enabled" />
                    <x-input-field name="documents_since" type="date" :label="__('datev-online::datev.page.documents.since')" :value="$connection->documents_since?->toDateString()" />
                    <x-button type="submit">{{ __('datev-online::datev.page.documents.save') }}</x-button>
                </form>
                @if ($connection->is_documents_enabled)
                    <x-action-form :action="route('admin.datev-online.documents.upload')" class="mt-2">
                        <x-button type="submit" tone="ghost">{{ __('datev-online::datev.page.documents.upload') }}</x-button>
                    </x-action-form>
                @endif
            </x-card>

            <x-card padding="p-0" class="mt-4">
                <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
                    <span class="text-sm font-semibold">{{ __('datev-online::datev.page.batches.heading') }}</span>
                    <x-action-form :action="route('admin.datev-online.jobs.refresh')">
                        <x-button type="submit" tone="ghost" size="sm">{{ __('datev-online::datev.page.batches.refresh') }}</x-button>
                    </x-action-form>
                </header>
                <p class="px-4 pt-3 text-xs text-muted">{{ __('datev-online::datev.page.batches.hint') }}</p>
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('datev-online::datev.page.batches.col.batch') }}</x-table.th>
                            <x-table.th>{{ __('datev-online::datev.page.batches.col.period') }}</x-table.th>
                            <x-table.th>{{ __('datev-online::datev.page.batches.col.client') }}</x-table.th>
                            <x-table.th>{{ __('datev-online::datev.page.batches.col.status') }}</x-table.th>
                            <x-table.th class="text-right"><span class="sr-only">{{ __('Aktionen') }}</span></x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($batches as $batch)
                        @php($transfer = $batchTransfers->get($batch->id))
                        <tr>
                            <td>{{ $batch->batch_no }}</td>
                            <td class="whitespace-nowrap">{{ $batch->period_from->fdate() }} – {{ $batch->period_to->fdate() }}</td>
                            <td>{{ $batch->advisor_number }}-{{ $batch->client_number }}</td>
                            <td>
                                @if ($transfer)
                                    <x-status-badge :tone="$transfer->status->tone()" size="xs">{{ $transfer->status->label() }}</x-status-badge>
                                    @if ($transfer->error)
                                        <span class="text-xs text-error">{{ $transfer->error }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right">
                                @if (! $transfer || $transfer->status === \App\Plugins\DatevOnline\Enums\DatevTransferStatus::Failed)
                                    <x-action-form :action="route('admin.datev-online.batches.transfer', $batch)">
                                        <x-button type="submit" size="sm">{{ __('datev-online::datev.page.batches.transfer') }}</x-button>
                                    </x-action-form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="5" :title="__('datev-online::datev.page.batches.empty')" compact />
                    @endforelse
                </x-table>
            </x-card>

            <x-card padding="p-0" class="mt-4" :title="__('datev-online::datev.page.transfers.heading')">
                <x-table :bare="true">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('datev-online::datev.page.transfers.col.kind') }}</x-table.th>
                            <x-table.th>{{ __('datev-online::datev.page.transfers.col.date') }}</x-table.th>
                            <x-table.th>{{ __('datev-online::datev.page.transfers.col.status') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($documentTransfers as $transfer)
                        <tr>
                            <td>{{ $transfer->kind->label() }}</td>
                            <td class="whitespace-nowrap">{{ ($transfer->transferred_at ?? $transfer->updated_at)?->fdatetime() }}</td>
                            <td>
                                <x-status-badge :tone="$transfer->status->tone()" size="xs">{{ $transfer->status->label() }}</x-status-badge>
                                @if ($transfer->error)
                                    <span class="text-xs text-error">{{ $transfer->error }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="3" :title="__('datev-online::datev.page.transfers.empty')" compact />
                    @endforelse
                </x-table>
            </x-card>
        @endif
    @endif
</x-index-page>
@endsection
