{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@php
/**
 * @var \App\Models\InvoiceSchedule $schedule
 * @var bool $isBlocked
 */
@endphp

@section('nav-title', $schedule->title)

@section('content')
<x-page-shell>
    <x-page-toolbar :subtitle="__('Abrechnungsplan') . ' · ' . ($schedule->customer?->company ?: $schedule->customer?->name ?? '—')">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" size="sm" :href="route('invoice-schedules.index')" show-label>{{ __('Alle Pläne') }}</x-icon-btn>
            @can(\App\Enums\User\Permission::InvoiceUpdate->value)
                <x-icon-btn icon="edit" size="sm"
                            data-entry-modal-trigger
                            :href="route('invoice-schedules.edit', $schedule) . '?dialog=1'"
                            show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('invoice-schedules.items.create', $schedule) . '?dialog=1'"
                            show-label>{{ __('Position hinzufügen') }}</x-icon-btn>
                @if ($schedule->status === \App\Models\InvoiceSchedule::STATUS_ACTIVE)
                    <x-action-form :action="route('invoice-schedules.status', $schedule)" method="PATCH">
                        <input type="hidden" name="status" value="paused">
                        <x-icon-btn icon="pause" tone="warning" size="sm" type="submit" show-label>{{ __('Aussetzen') }}</x-icon-btn>
                    </x-action-form>
                @elseif ($schedule->status === \App\Models\InvoiceSchedule::STATUS_PAUSED)
                    <x-action-form :action="route('invoice-schedules.status', $schedule)" method="PATCH">
                        <input type="hidden" name="status" value="active">
                        <x-icon-btn icon="play_arrow" tone="success" size="sm" type="submit" show-label>{{ __('Fortsetzen') }}</x-icon-btn>
                    </x-action-form>
                @endif
                @if ($schedule->status !== \App\Models\InvoiceSchedule::STATUS_ENDED)
                    <x-action-form :action="route('invoice-schedules.status', $schedule)" method="PATCH"
                          :confirm="__('Plan endgültig beenden? Ein beendeter Plan kann nicht reaktiviert werden.')"
                          :confirm-label="__('Beenden')">
                        <input type="hidden" name="status" value="ended">
                        <x-icon-btn icon="stop" tone="error" size="sm" type="submit" show-label>{{ __('Beenden') }}</x-icon-btn>
                    </x-action-form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-toolbar>

    @if ($isBlocked)
        <div class="alert alert-error text-sm">
            <span>{{ __('Externes Fakturasystem führt die Rechnungen dieses Kunden — der Plan erzeugt keine Entwürfe.') }}</span>
        </div>
    @endif

    <x-card :title="__('Plan')">
        <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
            <div>
                <div class="text-xs text-base-content/60">{{ __('Status') }}</div>
                <x-status-badge size="sm" :tone="$schedule->statusTone()">{{ $schedule->statusLabel() }}</x-status-badge>
            </div>
            <div>
                <div class="text-xs text-base-content/60">{{ __('Intervall') }}</div>
                {{ __('alle :count :unit', ['count' => $schedule->interval_count, 'unit' => $schedule->unitLabel()]) }}
                · {{ $schedule->billing_period_mode === \App\Models\InvoiceSchedule::MODE_CURRENT ? __('laufender Zeitraum') : __('abgelaufener Zeitraum') }}
            </div>
            <div>
                <div class="text-xs text-base-content/60">{{ __('Nächste Läufe') }}</div>
                @forelse ($schedule->upcomingRuns() as $run)
                    <span class="badge badge-ghost badge-sm">{{ $run->fdate() }}</span>
                @empty
                    —
                @endforelse
            </div>
            <div>
                <div class="text-xs text-base-content/60">{{ __('Vertrag') }}</div>
                {{ $schedule->contract?->title ?? '—' }}
                @if ($schedule->end_on !== null)
                    · {{ __('Ende') }}: {{ $schedule->end_on->fdate() }}
                @endif
            </div>
        </div>
    </x-card>

    <x-card :title="__('Positionsvorlage')">
        <p class="text-xs text-base-content/60">{{ __('Platzhalter :von und :bis werden je Lauf durch den Abrechnungszeitraum ersetzt.', ['von' => '{zeitraum_von}', 'bis' => '{zeitraum_bis}']) }}</p>
        <x-table size="sm" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>#</th>
                    <th>{{ __('Beschreibung') }}</th>
                    <th class="text-right">{{ __('Menge') }}</th>
                    <th class="text-right">{{ __('Einzelpreis') }}</th>
                    <th class="text-right">{{ __('Rabatt') }}</th>
                    <th class="text-right">{{ __('USt-Satz') }}</th>
                    <th class="w-px"></th>
                </tr>
            </x-slot:head>
            @forelse ($schedule->items as $item)
                <tr>
                    <td>{{ $item->position }}</td>
                    <td>{{ $item->description }}</td>
                    <td class="text-right tabular-nums">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                    <td class="text-right tabular-nums">{{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                    <td class="text-right tabular-nums">
                        @if ($item->discount_percent !== null)
                            {{ rtrim(rtrim((string) $item->discount_percent, '0'), '.') }} %
                        @elseif ($item->discount_amount !== null)
                            {{ number_format((float) $item->discount_amount, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $item->tax_rate !== null ? rtrim(rtrim((string) $item->tax_rate, '0'), '.') . ' %' : __('Standard') }}</td>
                    <td>
                        @can(\App\Enums\User\Permission::InvoiceUpdate->value)
                            <div class="flex items-center gap-1">
                                <x-icon-btn icon="edit" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('invoice-schedules.items.edit', [$schedule, $item]) . '?dialog=1'"
                                            :label="__('Bearbeiten')" />
                                <x-action-form :action="route('invoice-schedules.items.destroy', [$schedule, $item])" method="DELETE"
                                      :confirm="__('Position wirklich entfernen?')"
                                      :confirm-label="__('Entfernen')">
                                    <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('Entfernen')" />
                                </x-action-form>
                            </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>' :colspan="7" :title="__('Noch keine Positionen — ohne Positionen erzeugt der Plan leere Entwürfe.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('Erzeugte Entwürfe')">
        <x-table size="sm" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Zeitraum') }}</th>
                    <th>{{ __('Rechnung') }}</th>
                    <th class="text-right">{{ __('Betrag') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($schedule->runs as $run)
                <tr>
                    <td class="whitespace-nowrap">{{ $run->period_start->fdate() }} – {{ $run->period_end->fdate() }}</td>
                    <td>
                        @if ($run->invoice !== null)
                            <a href="{{ route('invoices.show', $run->invoice) }}" class="link link-hover">{{ $run->invoice->number }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-right tabular-nums">{{ $run->invoice !== null ? number_format((float) $run->invoice->total, 2, ',', '.') . ' ' . $run->invoice->currency->value : '—' }}</td>
                    <td>{{ $run->invoice?->status ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">event_repeat</span>' :colspan="4" :title="__('Noch keine Läufe')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
