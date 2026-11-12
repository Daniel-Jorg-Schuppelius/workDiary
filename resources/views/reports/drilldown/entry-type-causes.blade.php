{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : entry-type-causes.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Belege einer Top-Ursache der Auftragstypanalyse (MVP-1619, H3-05). --}}
@extends('layouts.app')
@section('title', __('Drilldown: Ursache (Auftragstyp)'))
@section('nav-title', __('Drilldown: Ursache (Auftragstyp)'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :back="route('reports.entry-types', array_filter(['customer_id' => \App\Support\Sqid::encode(\App\Models\Customer\Customer::class, $customerId), 'user_id' => \App\Support\Sqid::encode(\App\Models\Platform\User::class, $userId), 'entry_type_id' => \App\Support\Sqid::encode(\App\Models\Classification\EntryType::class, $entryTypeId), 'status' => $statusFilter]))" :back-label="__('Zur Auftragstypanalyse')">
            <x-slot:subtitle>
                {{ __('Auftragstyp') }}: {{ $entryType?->label ?? __('Ohne Auftragstyp') }} · {{ $classification->displayLabel() }} · {{ $label }}
            </x-slot:subtitle>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card :title="__('Nacharbeit mit diesem Grund')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('Auftrag') }}</th>
                    <th>{{ __('Mitarbeiter') }}</th>
                    <th class="text-right">{{ __('Minuten') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($timeEntries as $entry)
                <tr>
                    <td>{{ $entry->date?->fdate() }}</td>
                    <td>
                        @if ($entry->diaryEntry)
                            <x-order-link :entry="$entry->diaryEntry" link-class="link link-hover">{{ $entry->diaryEntry->title }}</x-order-link>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $entry->user?->name ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ (int) $entry->minutes }}</td>
                </tr>
            @empty
                <x-table.empty icon="build" :colspan="4" :title="__('Keine Nacharbeitszeiten mit diesem Grund.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('Reklamationen mit dieser Grundursache')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Auftrag') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($claims as $claim)
                <tr>
                    <td>
                        @can('view', $claim)
                            <a href="{{ route('claims.show', $claim) }}" class="link link-hover font-mono">{{ $claim->number }}</a>
                        @else
                            <span class="font-mono">{{ $claim->number }}</span>
                        @endcan
                    </td>
                    <td>
                        @if ($claim->diaryEntry)
                            <x-order-link :entry="$claim->diaryEntry" link-class="link link-hover">{{ $claim->diaryEntry->title }}</x-order-link>
                        @else
                            —
                        @endif
                    </td>
                    <td><x-status-badge size="sm">{{ $claim->status->label() }}</x-status-badge></td>
                </tr>
            @empty
                <x-table.empty icon="report" :colspan="3" :title="__('Keine Reklamationen mit dieser Grundursache.')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-page-shell>
@endsection
