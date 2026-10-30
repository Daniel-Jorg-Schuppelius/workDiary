{{--
  Created on   : Fri Oct 30 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : plan_proposal.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorlagen-Vorschlag (Feature 185, MVP-1440): je Rechnungsempfänger mit externer
  Hoheit die Abos ohne Serienvorlage mit allem, was in die Vorlage des
  Buchhaltungsprogramms gehört. Die Vorlage legt der Betreiber dort von Hand an
  (die Lexoffice-API liest Vorlagen nur); der nächste Spiegel schlägt die
  Zuordnung vor. Empfänger mit aktivem Serienlauf des Registers sind markiert.
--}}
@extends('layouts.app')
@section('title', __('resale.plan.proposal.title'))
@section('nav-title', __('resale.title.menu'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :title="__('resale.plan.proposal.title')" :subtitle="__('resale.plan.proposal.subtitle')" back-route="finance.resale.periods.index">
        @include('finance.resale._tabs')
        <p class="text-xs text-muted mb-3">{{ __('resale.plan.proposal.hint') }}</p>

        @if ($groups === [])
            <x-empty-state compact icon="event_repeat" :title="__('resale.plan.proposal.empty')" />
        @else
            <div class="space-y-5 overflow-y-auto">
                @foreach ($groups as $group)
                    <x-card as="section" padding="p-0">
                        <header class="flex flex-wrap items-center gap-2 px-4 py-2 border-b border-base-300">
                            <h2 class="font-semibold">{{ $group['recipient']->name }}</h2>
                            @if ($group['covered'])
                                <x-status-badge tone="info" size="sm" :title="__('resale.plan.proposal.covered_hint')">{{ __('resale.plan.proposal.covered') }}</x-status-badge>
                            @endif
                            <span class="text-xs text-muted ms-auto">{{ trans_choice('resale.plan.proposal.rows', count($group['rows']), ['count' => count($group['rows'])]) }}</span>
                        </header>
                        <x-table bare>
                            <x-slot:head>
                                <tr>
                                    <x-table.th>{{ __('resale.plan.proposal.col.product') }}</x-table.th>
                                    <x-table.th>{{ __('resale.plan.proposal.col.description') }}</x-table.th>
                                    <x-table.th class="text-right">{{ __('resale.plan.proposal.col.quantity') }}</x-table.th>
                                    <x-table.th>{{ __('resale.plan.proposal.col.unit') }}</x-table.th>
                                    <x-table.th class="text-right">{{ __('resale.plan.proposal.col.unit_price') }}</x-table.th>
                                    <x-table.th>{{ __('resale.plan.proposal.col.interval') }}</x-table.th>
                                    <x-table.th>{{ __('resale.plan.proposal.col.first') }}</x-table.th>
                                </tr>
                            </x-slot:head>
                            @foreach ($group['rows'] as $row)
                                <tr>
                                    <td>
                                        <a class="link" href="{{ route('finance.resale.show', $row['subscription']->sqid) }}">{{ $row['product'] }}</a>
                                        @if ($row['article'] !== null && $row['article'] !== $row['product'])
                                            <span class="block text-xs text-muted">{{ $row['article'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['description'] }}</td>
                                    <td class="text-right tabular-nums">{{ $row['quantity'] }}</td>
                                    <td>{{ $row['unit'] }}</td>
                                    <td class="text-right tabular-nums">{{ $row['unit_price']?->format() ?? '—' }}</td>
                                    <td>{{ $row['interval'] }}</td>
                                    <td>
                                        @if ($row['first'] === null)
                                            —
                                        @elseif ($row['overdue'])
                                            <span class="text-error">{{ __('resale.plan.proposal.due_since', ['date' => $row['first']->fdate()]) }}</span>
                                        @else
                                            {{ $row['first']->fdate() }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-table>
                    </x-card>
                @endforeach
            </div>
        @endif
    </x-index-page>
@endsection
