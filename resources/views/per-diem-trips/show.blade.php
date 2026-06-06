{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Reise :location', ['location' => $trip->location]))
@section('nav-title', __('Verpflegungspauschalen'))

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :title="$trip->location . ' · ' . $trip->started_at->format('d.m.Y')">
                <x-slot:actions>
                    <x-status-badge :tone="$trip->status->tone()">{{ $trip->status->label() }}</x-status-badge>
                    <x-icon-btn icon="picture_as_pdf" tone="ghost" size="sm"
                                :href="route('per-diem-trips.pdf', $trip)"
                                show-label>{{ __('PDF') }}</x-icon-btn>
                    @can('update', $trip)
                        <x-icon-btn icon="edit" tone="ghost" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('per-diem-trips.edit', $trip)"
                                    show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @endcan
                    @can('convert', $trip)
                        <form method="POST" action="{{ route('per-diem-trips.convert', $trip) }}" class="inline"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Reise als Spese einreichen?') }}"
                              data-confirm-label="{{ __('Einreichen') }}">
                            @csrf
                            <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Als Spese einreichen') }}</x-icon-btn>
                        </form>
                    @endcan
                    @if ($trip->expense)
                        <x-icon-btn icon="receipt_long" tone="ghost" size="sm"
                                    :href="route('expenses.index')"
                                    show-label>{{ __('Zur Spese #:id', ['id' => $trip->expense->id]) }}</x-icon-btn>
                    @endif
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi-tile :label="__('Tage')" :value="(string) $trip->days->count()" />
            <x-kpi-tile :label="__('Pauschale gesamt')"
                        :value="number_format((float) $trip->totalAmount(), 2, ',', '.') . ' €'" tone="success" />
            <x-kpi-tile :label="__('Land')" :value="$trip->country" />
            <x-kpi-tile :label="__('3-Monats-Regel')"
                        :value="$eligibility['used_days'] . ' / ' . $eligibility['limit_days']"
                        :tone="$eligibility['eligible'] ? 'success' : 'error'" />
        </div>

        @unless ($eligibility['eligible'])
            <div role="alert" class="alert alert-warning">
                <x-icon name="warning" />
                <span>{{ $eligibility['reason'] }} {{ __('Bitte Anspruch vor der Abrechnung prüfen.') }}</span>
            </div>
        @endunless

        <x-card :title="__('Zweck')" icon="info">
            <p class="text-base-content/80">{{ $trip->purpose }}</p>
            @if ($trip->notes)
                <p class="mt-2 text-sm text-base-content/60 whitespace-pre-line">{{ $trip->notes }}</p>
            @endif
            <p class="mt-2 text-xs text-base-content/60">
                {{ $trip->started_at->orgTz()->format('d.m.Y H:i') }} – {{ $trip->ended_at->orgTz()->format('d.m.Y H:i') }}
                @if ($trip->accommodation_provided)
                    · <x-status-badge tone="ghost" size="xs">{{ __('Übernachtung gestellt') }}</x-status-badge>
                @endif
            </p>
        </x-card>

        <x-card :title="__('Tage & Mahlzeitenkürzung')" icon="restaurant_menu" padding="p-0">
            <x-table bare table-sort="client">
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="date" default="asc">{{ __('Datum') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Art') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Basis') }}</x-table.th>
                        <x-table.th align="center">{{ __('Frühstück') }}</x-table.th>
                        <x-table.th align="center">{{ __('Mittag') }}</x-table.th>
                        <x-table.th align="center">{{ __('Abend') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Kürzung') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Auszahlung') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($trip->days as $day)
                    @php($fid = 'pd-day-form-' . $day->id)
                    <tr>
                        <td class="whitespace-nowrap" data-sort-value="{{ $day->date->format('Y-m-d') }}">{{ $day->date->format('d.m.Y') }}</td>
                        <td><x-status-badge tone="ghost" size="sm">{{ $day->kind->label() }}</x-status-badge></td>
                        <td class="text-right whitespace-nowrap" data-sort-value="{{ $day->base_amount }}">{{ number_format((float) $day->base_amount, 2, ',', '.') }} €</td>
                        <td class="text-center">
                            <input type="hidden" name="meal_breakfast" value="0" form="{{ $fid }}">
                            <input type="checkbox" name="meal_breakfast" value="1" form="{{ $fid }}"
                                   class="checkbox checkbox-sm"
                                   @checked($day->meal_breakfast)
                                   @cannot('update', $trip) disabled @endcannot>
                        </td>
                        <td class="text-center">
                            <input type="hidden" name="meal_lunch" value="0" form="{{ $fid }}">
                            <input type="checkbox" name="meal_lunch" value="1" form="{{ $fid }}"
                                   class="checkbox checkbox-sm"
                                   @checked($day->meal_lunch)
                                   @cannot('update', $trip) disabled @endcannot>
                        </td>
                        <td class="text-center">
                            <input type="hidden" name="meal_dinner" value="0" form="{{ $fid }}">
                            <input type="checkbox" name="meal_dinner" value="1" form="{{ $fid }}"
                                   class="checkbox checkbox-sm"
                                   @checked($day->meal_dinner)
                                   @cannot('update', $trip) disabled @endcannot>
                        </td>
                        <td class="text-right whitespace-nowrap text-warning" data-sort-value="{{ $day->deductions_total }}">
                            @if ((float) $day->deductions_total > 0)
                                − {{ number_format((float) $day->deductions_total, 2, ',', '.') }} €
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap font-semibold" data-sort-value="{{ $day->amount }}">{{ number_format((float) $day->amount, 2, ',', '.') }} €</td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $trip)
                                <button type="submit" form="{{ $fid }}" class="btn btn-primary btn-sm">
                                    <x-icon name="save" /> {{ __('Speichern') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        @can('update', $trip)
            @foreach ($trip->days as $day)
                <form id="pd-day-form-{{ $day->id }}" method="POST"
                      action="{{ route('per-diem-trips.days.update', [$trip, $day]) }}" class="hidden">
                    @csrf @method('PUT')
                </form>
            @endforeach
        @endcan
    </x-page-shell>
@endsection
