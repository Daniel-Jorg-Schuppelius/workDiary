{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('Zeiterfassungen') }}</h1>

    @if ($detail === \App\Enums\CustomerPortal\PortalTimeDetail::Summary)
        {{-- Stufe „Nur Summen": je Monat und Projekt, keine Einzelzeilen. --}}
        <x-table>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Monat') }}</x-table.th>
                    <x-table.th>{{ __('Projekt') }}</x-table.th>
                    <x-table.th align="right">{{ __('Stunden') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($summaries as $row)
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $row->month)->isoFormat('MMMM YYYY') }}</td>
                    <td>{{ $row->project_name }}</td>
                    <td class="whitespace-nowrap text-right tabular-nums">{{ \App\Support\Formats::duration((int) $row->minutes, 'clock') }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="3" :title="__('Keine Zeiterfassungen vorhanden.')" />
            @endforelse
        </x-table>
    @else
        @php
            $withDescription = $detail === \App\Enums\CustomerPortal\PortalTimeDetail::EntriesWithDescription;
            // Rückfragen (MVP-512): CTA nur mit eigener Capability.
            $portalQueryCustomer = auth('customer')->user()?->customer;
            $canQuery = $portalQueryCustomer !== null
                && app(\App\Services\CustomerPortal\PortalVisibility::class)->allows($portalQueryCustomer, \App\Enums\CustomerPortal\PortalCapability::Queries);
            $columns = ($withDescription ? 5 : 4) + ($canQuery ? 1 : 0);
        @endphp
        <x-table>
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Datum') }}</x-table.th>
                    <x-table.th align="right">{{ __('Dauer') }}</x-table.th>
                    <x-table.th>{{ __('Projekt') }}</x-table.th>
                    <x-table.th>{{ __('Mitarbeiter') }}</x-table.th>
                    @if ($withDescription)
                        <x-table.th>{{ __('Beschreibung') }}</x-table.th>
                    @endif
                    @if ($canQuery)
                        <th></th>
                    @endif
                </tr>
            </x-slot:head>
            @forelse ($entries as $entry)
                <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ $entry->date?->fdate() ?? '—' }}</td>
                    <td class="whitespace-nowrap text-right tabular-nums">{{ $entry->hoursFormatted() }}</td>
                    <td>{{ $entry->project?->name }}</td>
                    <td>{{ $entry->user?->name }}</td>
                    @if ($withDescription)
                        {{-- Beschreibung nur für ausdrücklich veröffentlichte Einträge. --}}
                        <td>{{ $entry->customer_visible_at !== null ? $entry->description : '—' }}</td>
                    @endif
                    @if ($canQuery)
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('customer.queries.create', ['subject_type' => 'time_entry', 'subject' => $entry->sqid]) }}"
                               class="btn btn-ghost btn-xs">{{ __('Rückfrage') }}</a>
                        </td>
                    @endif
                </tr>
            @empty
                <x-table.empty :colspan="$columns" :title="__('Keine Zeiterfassungen vorhanden.')" />
            @endforelse
        </x-table>
        <x-pagination :paginator="$entries" />
    @endif
@endsection
