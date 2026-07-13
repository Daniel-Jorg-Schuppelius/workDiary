{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorschau/Detail eines Übergabenachweises (Feature 045, Teil B): Kopf,
  entstehende Positionen (Zeit-Blöcke mit Taktung bzw. Material), Quellen,
  Aktionen entlang der Statusmaschine, Ereignisprotokoll. Bei
  transferred+lexoffice: Verweis auf den externen Rechnungsentwurf
  (ExternalReference) — KEIN automatischer Status-Sync (offener Punkt).
--}}

@extends('layouts.app')

@section('title', __('finance.title.transfer'))
@section('nav-title', __('finance.title.transfer'))

@section('content')
<x-page-shell>
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">
                    {{ $transfer->customer?->name ?? '—' }}
                    <span class="text-base-content/50">·</span>
                    {{ $transfer->channel->label() }}
                </h2>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    <x-status-badge :tone="$transfer->status->tone()">{{ $transfer->status->label() }}</x-status-badge>
                    <x-status-badge :tone="$transfer->channel->tone()" outline>{{ $transfer->channel->label() }}</x-status-badge>
                    <x-status-badge :tone="$transfer->target->tone()" outline>{{ $transfer->target->label() }}</x-status-badge>
                </div>
                <dl class="mt-3 grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
                    <div class="flex gap-2">
                        <dt class="text-base-content/60">{{ __('finance.field.period') }}:</dt>
                        <dd>{{ $transfer->period_from?->format('d.m.Y') ?? '—' }} – {{ $transfer->period_to?->format('d.m.Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-base-content/60">{{ __('finance.field.position_count') }}:</dt>
                        <dd class="tabular-nums">{{ $transfer->position_count }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-base-content/60">{{ __('finance.field.total_quantity') }}:</dt>
                        <dd class="tabular-nums">{{ number_format((float) $transfer->total_quantity, 2, ',', '.') }}</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="text-base-content/60">{{ __('finance.field.total_amount') }}:</dt>
                        <dd class="tabular-nums">{{ number_format((float) $transfer->total_amount, 2, ',', '.') }}</dd>
                    </div>
                    <div class="flex gap-2 sm:col-span-2">
                        <dt class="text-base-content/60">{{ __('finance.field.payload_hash') }}:</dt>
                        <dd class="break-all font-mono text-xs">{{ $transfer->payload_hash }}</dd>
                    </div>
                    @if ($transfer->transferred_at !== null)
                        <div class="flex gap-2">
                            <dt class="text-base-content/60">{{ __('finance.field.transferred_at') }}:</dt>
                            <dd>{{ $transfer->transferred_at->format('d.m.Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Failed && $transfer->failure_reason !== null)
                    <div class="alert alert-error mt-3 text-sm">
                        <x-icon name="error" />
                        <span><span class="font-semibold">{{ __('finance.field.failure_reason') }}:</span> {{ $transfer->failure_reason }}</span>
                    </div>
                @endif

                {{-- Statusrücklauf Lexoffice (minimal): Verweis auf den externen Entwurf --}}
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Transferred
                    && $transfer->target === \App\Enums\Finance\TransferTarget::Lexoffice
                    && $transfer->externalReference !== null)
                    <div class="alert alert-info mt-3 text-sm">
                        <x-icon name="cloud_done" />
                        <span>
                            {{ __('finance.hint.lexoffice_draft_created') }}
                            <span class="font-mono text-xs">{{ $transfer->externalReference->external_id }}</span>
                            @php $extUrl = data_get($transfer->externalReference->payload, 'resourceUri'); @endphp
                            @if (is_string($extUrl) && $extUrl !== '')
                                — <a class="link" href="{{ $extUrl }}" target="_blank" rel="noopener noreferrer">{{ __('finance.action.open_external') }}</a>
                            @endif
                        </span>
                    </div>
                @endif

                {{-- Statusrücklauf sevDesk (minimal, analog Lexoffice): Verweis auf den externen Entwurf --}}
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Transferred
                    && $transfer->target === \App\Enums\Finance\TransferTarget::SevDesk
                    && $transfer->externalReference !== null)
                    <div class="alert alert-info mt-3 text-sm">
                        <x-icon name="cloud_done" />
                        <span>
                            {{ __('finance.hint.sevdesk_draft_created') }}
                            <span class="font-mono text-xs">{{ $transfer->externalReference->external_id }}</span>
                        </span>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Draft)
                    @can('confirm', $transfer)
                        <form method="POST" action="{{ route('finance.transfers.confirm', $transfer) }}">
                            @csrf
                            <x-icon-btn icon="check_circle" tone="primary" size="sm" type="submit" show-label>{{ __('finance.action.confirm') }}</x-icon-btn>
                        </form>
                    @endcan
                @endif
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Confirmed)
                    @can('markTransferred', $transfer)
                        <x-action-form :action="route('finance.transfers.execute', $transfer)"
                              data-confirm-title="{{ __('finance.action.execute') }}"
                              :confirm="__('finance.confirm_execute')"
                              confirm-icon="cloud_upload"
                              confirm-tone="primary"
                              :confirm-label="__('finance.action.execute')">
                            <x-icon-btn icon="cloud_upload" tone="primary" size="sm" type="submit" show-label>{{ __('finance.action.execute') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                @endif
                @if ($transfer->status === \App\Enums\Finance\TransferStatus::Failed)
                    @can('confirm', $transfer)
                        <form method="POST" action="{{ route('finance.transfers.confirm', $transfer) }}">
                            @csrf
                            <x-icon-btn icon="refresh" tone="warning" size="sm" type="submit" show-label>{{ __('finance.action.retry') }}</x-icon-btn>
                        </form>
                    @endcan
                @endif
                @if (in_array($transfer->status, [\App\Enums\Finance\TransferStatus::Draft, \App\Enums\Finance\TransferStatus::Confirmed], true))
                    @can('void', $transfer)
                        <x-action-form :action="route('finance.transfers.void', $transfer)"
                              data-confirm-title="{{ __('finance.action.void') }}"
                              :confirm="__('finance.confirm_void')"
                              confirm-icon="delete"
                              confirm-tone="error"
                              :confirm-label="__('finance.action.void')">
                            <x-icon-btn icon="cancel" tone="error" size="sm" type="submit" show-label>{{ __('finance.action.void') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                @endif
                @if ($transfer->file_path !== null && $transfer->status === \App\Enums\Finance\TransferStatus::Transferred)
                    <x-icon-btn icon="download" tone="outline" size="sm"
                                :href="route('finance.transfers.download', $transfer)"
                                show-label>{{ __('finance.action.download') }}</x-icon-btn>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-error mt-3 text-sm">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
    </x-card>

    {{-- Entstehende Positionen (Zeit: Taktungs-Blöcke, Material: je Verwendung) --}}
    <x-card>
        <h3 class="mb-2 text-sm font-semibold">{{ __('finance.title.positions') }}</h3>
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('finance.csv.position') }}</th>
                    <th class="text-right">{{ __('finance.csv.quantity') }}</th>
                    <th>{{ __('finance.csv.unit') }}</th>
                    <th class="text-right">{{ __('finance.csv.unit_price_net') }}</th>
                    <th class="text-right">{{ __('finance.csv.amount') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($positions as $position)
                <tr>
                    <td>{{ $position['name'] }}</td>
                    <td class="text-right tabular-nums">{{ $position['quantity'] }}</td>
                    <td>{{ $position['unit'] }}</td>
                    <td class="text-right tabular-nums">{{ $position['unit_price'] }}</td>
                    <td class="text-right tabular-nums">{{ $position['amount'] }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="5"
                               :title="__('finance.empty_positions_title')"
                               :message="__('finance.empty_positions')" />
            @endforelse
        </x-table>
    </x-card>

    {{-- Einzelquellen (Snapshot des Nachweises). Im Material-Kanal werden die im
         Snapshot festgehaltenen Felder Einheit, Steuersatz und DATEV-Kostenposition
         (Kriterium 045) zusätzlich gezeigt — sie dokumentieren die Materialzeile
         zum Übergabezeitpunkt, unabhängig von späteren Stammdatenänderungen. --}}
    @php $isMaterial = $transfer->channel === \App\Enums\Finance\TransferChannel::Material; @endphp
    <x-card>
        <h3 class="mb-2 text-sm font-semibold">{{ __('finance.title.sources') }}</h3>
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('finance.csv.date') }}</th>
                    <th>{{ __('finance.field.source') }}</th>
                    <th class="text-right">{{ __('finance.csv.quantity') }}</th>
                    @if ($isMaterial)
                        <th>{{ __('finance.csv.unit') }}</th>
                        <th class="text-right">{{ __('finance.csv.tax_rate') }}</th>
                        <th>{{ __('finance.csv.cost_position') }}</th>
                    @endif
                    <th class="text-right">{{ __('finance.csv.amount') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($transfer->items as $item)
                <tr>
                    @if ($item->source instanceof \App\Models\TimeEntry)
                        <td>{{ $item->source->date?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            {{ $item->source->project?->name ?? '—' }}
                            <span class="text-base-content/50">·</span>
                            {{ $item->source->user?->name ?? '—' }}
                            @if (trim((string) $item->source->description) !== '')
                                <span class="block text-xs text-base-content/60">{{ $item->source->description }}</span>
                            @endif
                        </td>
                    @elseif ($item->source instanceof \App\Models\MaterialUsage)
                        <td>{{ $item->source->timesheet?->work_date?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            {{ trim((string) $item->source->description) ?: __('Material') }}
                            <span class="block text-xs text-base-content/60">{{ $item->source->timesheet?->project?->name ?? '' }}</span>
                        </td>
                    @else
                        <td>—</td>
                        <td class="text-base-content/50">{{ __('finance.field.source_deleted') }}</td>
                    @endif
                    <td class="text-right tabular-nums">{{ $item->quantity !== null ? number_format((float) $item->quantity, 2, ',', '.') : '—' }}</td>
                    @if ($isMaterial)
                        <td>{{ $item->unit ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $item->tax_rate !== null ? number_format((float) $item->tax_rate, 2, ',', '.') . ' %' : '—' }}</td>
                        <td class="font-mono text-xs">{{ $item->cost_position ?? '—' }}</td>
                    @endif
                    <td class="text-right tabular-nums">{{ $item->amount !== null ? number_format((float) $item->amount, 2, ',', '.') : '—' }}</td>
                </tr>
            @endforeach
            <tr class="font-semibold">
                <td colspan="2">{{ __('finance.csv.total') }}</td>
                <td class="text-right tabular-nums">{{ number_format((float) $transfer->total_quantity, 2, ',', '.') }}</td>
                @if ($isMaterial)
                    <td colspan="3"></td>
                @endif
                <td class="text-right tabular-nums">{{ number_format((float) $transfer->total_amount, 2, ',', '.') }}</td>
            </tr>
        </x-table>
    </x-card>

    {{-- Ereignisprotokoll (Hash-Kette) --}}
    <x-card>
        <h3 class="mb-2 text-sm font-semibold">{{ __('finance.title.events') }}</h3>
        <ul class="space-y-1 text-sm">
            @foreach ($transfer->events as $event)
                <li class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-xs text-base-content/50">{{ $event->created_at?->format('d.m.Y H:i:s') }}</span>
                    <x-status-badge tone="ghost" outline>{{ $event->event }}</x-status-badge>
                    @if (data_get($event->payload, 'failure_reason'))
                        <span class="text-error">{{ data_get($event->payload, 'failure_reason') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-card>
</x-page-shell>
@endsection
