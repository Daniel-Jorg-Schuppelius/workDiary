{{--
  Created on   : Fri Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : inbox.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Buchungs-Inbox (Feature 125, MVP-673). Blockiertes steht oben — das ist die
  Arbeit, die jemand anfassen muss. Jeder Vorschlag nennt Betrag, Konten,
  Steuer, Regelversion und Quelle; fehlende Mappings blockieren sichtbar,
  statt auf ein Standardkonto zu raten.
--}}

@extends('layouts.app')

@section('title', __('accounting.inbox.title'))
@section('nav-title', __('accounting.inbox.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('accounting.inbox.subtitle')">
        <x-slot:actions>
            @if ($canPost)
                {{-- Interne Umbuchung (MVP-681): eine Buchung für beide Seiten. --}}
                <x-icon-btn icon="swap_horiz" size="sm" tone="ghost" show-label
                            data-entry-modal-trigger
                            :href="route('finance.accounting.inbox.transfer.create')"
                            :label="__('accounting.transfer.action.record')" />
            @endif
            @if ($canPrepare)
                <x-action-form :action="route('finance.accounting.inbox.batch')" method="POST"
                               :confirm="__('accounting.inbox.confirm.batch')">
                    <input type="hidden" name="kind" value="{{ $selectedKind?->value }}">
                    <x-button type="submit" tone="ghost" size="sm">{{ __('accounting.inbox.action.batch_prepare') }}</x-button>
                </x-action-form>
                @if ($canPost)
                    <x-action-form :action="route('finance.accounting.inbox.batch')" method="POST"
                                   :confirm="__('accounting.inbox.confirm.batch_post')">
                        <input type="hidden" name="kind" value="{{ $selectedKind?->value }}">
                        <input type="hidden" name="post" value="1">
                        <x-button type="submit" tone="primary" size="sm">{{ __('accounting.inbox.action.batch_post') }}</x-button>
                    </x-action-form>
                @endif
            @endif
        </x-slot:actions>

        <x-accounting.sovereignty-note />

        <div class="grid gap-3 sm:grid-cols-4">
            <x-kpi-tile :label="__('accounting.inbox.state.blocked')" :value="$counts['blocked'] ?? 0" />
            <x-kpi-tile :label="__('accounting.inbox.state.open')" :value="$counts['open'] ?? 0" />
            <x-kpi-tile :label="__('accounting.inbox.state.ready')" :value="$counts['ready'] ?? 0" />
            <x-kpi-tile :label="__('accounting.inbox.state.posted')" :value="$counts['posted'] ?? 0" />
        </div>

        @if ($fourEyes)
            <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
                <x-icon name="groups" />
                <span>{{ __('accounting.inbox.four_eyes_active') }}</span>
            </div>
        @endif

        <x-filter-bar :action="route('finance.accounting.inbox.index')" :reset="route('finance.accounting.inbox.index')">
            <select name="kind" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('accounting.inbox.column.kind') }}">
                <option value="">{{ __('accounting.inbox.filter.all_kinds') }}</option>
                @foreach ($kinds as $kind)
                    <option value="{{ $kind->value }}" @selected($selectedKind === $kind)>{{ $kind->label() }}</option>
                @endforeach
            </select>
            <x-filter-toggle name="include_posted" class="order-40"
                             :label="__('accounting.inbox.filter.include_posted')" :checked="$includePosted" />
        </x-filter-bar>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('accounting.inbox.column.kind') }}</th>
                    <th>{{ __('accounting.inbox.column.document') }}</th>
                    <th>{{ __('accounting.inbox.column.booked_on') }}</th>
                    <th>{{ __('accounting.inbox.column.proposal') }}</th>
                    <th class="text-right">{{ __('accounting.ledger.column.amount') }}</th>
                    <th>{{ __('accounting.ledger.column.status') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($items as $item)
                @php($proposal = $item['proposal'])
                @php($entry = $item['entry'])
                <tr class="hover">
                    <td><x-status-badge :tone="$item['kind']->tone()">{{ $item['kind']->label() }}</x-status-badge></td>
                    <td class="font-medium">{{ $proposal?->title ?? $entry?->document_reference ?? '—' }}</td>
                    <td>{{ ($proposal?->bookedOn ?? $entry?->booked_on)?->fdate() ?? '—' }}</td>
                    <td class="text-xs text-base-content/70">
                        @if ($item['blockers'] !== [])
                            <ul class="list-disc pl-4 text-error">
                                @foreach ($item['blockers'] as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        @elseif ($proposal)
                            @foreach ($proposal->lines as $line)
                                <div>
                                    {{ $line->account->number }} · {{ $line->role->label() }} ·
                                    {{ (float) $line->debit > 0 ? __('accounting.ledger.column.debit') : __('accounting.ledger.column.credit') }}
                                    {{ (float) $line->debit > 0 ? $line->debit : $line->credit }}
                                </div>
                            @endforeach
                            <div class="mt-1 opacity-60">{{ $proposal->ruleVersion }}</div>
                        @elseif ($entry)
                            <span>{{ $entry->memo }}</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">
                        {{ $proposal?->debitTotal() ?? $entry?->debitTotal()?->getAmount() ?? '—' }}
                    </td>
                    <td>
                        <x-posting-state :state="$item['state']" />
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            @if ($entry)
                                <x-icon-btn icon="visibility" size="xs" tone="ghost"
                                            :href="route('finance.accounting.journal.show', $entry)"
                                            :label="__('Anzeigen')" />
                                @if ($canPost && $entry->status->isMutable())
                                    <x-action-form :action="route('finance.accounting.inbox.post', $entry)" method="POST">
                                        <x-icon-btn icon="task_alt" size="xs" tone="primary" type="submit"
                                                    :label="__('accounting.ledger.action.post')" />
                                    </x-action-form>
                                @endif
                            @elseif ($canPrepare && $proposal?->isPostable())
                                <x-action-form :action="route('finance.accounting.inbox.prepare')" method="POST">
                                    <input type="hidden" name="kind" value="{{ $item['kind']->value }}">
                                    <input type="hidden" name="source_id" value="{{ $item['source']->getKey() }}">
                                    <x-icon-btn icon="playlist_add" size="xs" tone="ghost" type="submit"
                                                :label="__('accounting.inbox.action.prepare')" />
                                </x-action-form>
                                @if ($canPost)
                                    <x-action-form :action="route('finance.accounting.inbox.prepare')" method="POST">
                                        <input type="hidden" name="kind" value="{{ $item['kind']->value }}">
                                        <input type="hidden" name="source_id" value="{{ $item['source']->getKey() }}">
                                        <input type="hidden" name="post" value="1">
                                        <x-icon-btn icon="task_alt" size="xs" tone="primary" type="submit"
                                                    :label="__('accounting.inbox.action.prepare_and_post')" />
                                    </x-action-form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="inbox" :title="__('accounting.inbox.empty')" compact />
            @endforelse
        </x-table>
    </x-index-page>
@endsection
