{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : inbox.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zuordnungs-Inbox (Feature 152, MVP-759): Firmen aus den Anbieter-Exporten
  ohne Halter, mit Vorschlägen aus Kunden- und Fremdkundenstamm, plus die
  letzten Import-Läufe.
--}}
@extends('layouts.app')
@section('title', __('resale.inbox.title'))
@section('nav-title', __('resale.title.menu'))

@section('content')
    <x-index-page :title="__('resale.inbox.title')" :subtitle="__('resale.inbox.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="upload" tone="primary" size="sm" data-entry-modal-trigger :href="route('finance.resale.import.create')" show-label>{{ __('resale.import.action') }}</x-icon-btn>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('finance.resale.index')" show-label>{{ __('resale.action.back') }}</x-icon-btn>
        </x-slot:actions>

        <x-card :title="__('resale.inbox.companies')" padding="p-0" class="mb-4">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.inbox.company') }}</x-table.th>
                        <x-table.th>{{ __('resale.inbox.subscriptions') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.provider') }}</x-table.th>
                        <x-table.th>{{ __('resale.inbox.suggestions') }}</x-table.th>
                        <x-table.th class="text-right"></x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($groups as $group)
                    <tr class="hover">
                        <td class="font-medium">{{ $group['company'] !== '' ? $group['company'] : __('resale.inbox.no_company') }}</td>
                        <td class="text-sm">
                            @foreach ($group['subscriptions'] as $subscription)
                                <a href="{{ route('finance.resale.show', $subscription->sqid) }}" class="link link-hover block">{{ $subscription->label }} × {{ $subscription->quantity }}</a>
                            @endforeach
                        </td>
                        <td class="text-sm">{{ implode(', ', $group['providers']) }}</td>
                        <td class="text-sm">
                            @foreach ($group['suggestions']['customers'] as $customer)
                                <span class="badge badge-outline badge-sm mr-1">{{ __('resale.inbox.mode_customer') }}: {{ $customer->name }}</span>
                            @endforeach
                            @foreach ($group['suggestions']['foreign'] as $foreign)
                                <span class="badge badge-outline badge-sm mr-1">{{ __('resale.inbox.mode_foreign') }}: {{ $foreign->name }} ({{ $foreign->customer?->name }})</span>
                            @endforeach
                            @if ($group['suggestions']['customers']->isEmpty() && $group['suggestions']['foreign']->isEmpty())
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if ($group['company'] !== '')
                                <x-icon-btn icon="person_add" size="xs" tone="primary" data-entry-modal-trigger
                                            :href="route('finance.resale.inbox.assign', ['company' => $group['company']])"
                                            :title="__('resale.inbox.assign')" show-label>{{ __('resale.inbox.assign') }}</x-icon-btn>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" icon="inbox" :title="__('resale.inbox.empty')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-card :title="__('resale.import.recent')" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('resale.import.when') }}</x-table.th>
                        <x-table.th>{{ __('resale.import.kind_label') }}</x-table.th>
                        <x-table.th>{{ __('resale.import.file') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.import.rows') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.import.created') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.import.updated') }}</x-table.th>
                        <x-table.th class="text-right">{{ __('resale.import.unassigned') }}</x-table.th>
                        <x-table.th>{{ __('resale.field.status') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($imports as $import)
                    <tr>
                        <td class="whitespace-nowrap text-sm">{{ $import->created_at->format('d.m.Y H:i') }} · {{ $import->creator?->name }}</td>
                        <td class="text-sm">{{ $import->kindLabel() }}</td>
                        <td class="text-sm">{{ $import->file_name }}</td>
                        <td class="text-right tabular-nums">{{ $import->rows_total }}</td>
                        <td class="text-right tabular-nums">{{ $import->rows_created }}</td>
                        <td class="text-right tabular-nums">{{ $import->rows_updated }}</td>
                        <td class="text-right tabular-nums">{{ $import->rows_unassigned }}</td>
                        <td>
                            <x-status-badge size="xs" :tone="$import->status->tone()" :label="$import->status->label()" :title="$import->error" />
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="8" :title="__('resale.import.none')" compact />
                @endforelse
            </x-table>
        </x-card>
    </x-index-page>
@endsection
