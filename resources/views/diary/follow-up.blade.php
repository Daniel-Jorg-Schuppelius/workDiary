{{--
  Created on   : Thu Nov 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : follow-up.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{--
  Folgeperiode gesammelt anlegen (Feature 198, MVP-1637): Eintragsart und
  Periode wählen, Vorschau prüfen, dann anlegen. Variablen: $entryTypes,
  $selectedType, $period (PeriodKey|null), $periodInput, $rows (Vorschau|null), $limit.
--}}

@extends('layouts.app')

@section('title', __('diary.follow_up.bulk.title'))
@section('nav-title', __('diary.follow_up.bulk.title'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" :subtitle="__('diary.follow_up.bulk.subtitle')">
    <x-filter-bar :action="route('diary.follow-up.index')">
        <x-filter-field :label="__('Eintragstyp')" for="follow-up-type" class="min-w-48">
            <select id="follow-up-type" name="entry_type" class="select select-bordered select-sm w-full">
                <option value="">—</option>
                @foreach ($entryTypes as $type)
                    <option value="{{ $type->sqid }}" @selected($selectedType?->id === $type->id)>{{ $type->label }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('diary.period.label')" for="follow-up-period" class="min-w-32">
            <input id="follow-up-period" type="text" name="period_key" maxlength="10" value="{{ $periodInput }}"
                   placeholder="{{ __('diary.period.placeholder') }}" class="input input-bordered input-sm w-full">
        </x-filter-field>
        <x-slot:extra>
            <x-help-button topic="diary-entries.follow-up" />
        </x-slot:extra>
    </x-filter-bar>

    @if ($rows === null)
        <x-empty-state framed icon="event_repeat" :title="__('diary.follow_up.bulk.choose_title')" :message="__('diary.follow_up.bulk.choose_message')" />
    @elseif ($rows === [])
        <x-empty-state framed icon="event_repeat" :title="__('diary.follow_up.bulk.none_title')" :message="__('diary.follow_up.bulk.none_message', ['period' => $period?->label() ?? '—'])" />
    @else
        @php($creatable = count(array_filter($rows, static fn (array $row): bool => $row['blocked'] === null)))
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-base-content/70">
                {{ __('diary.follow_up.bulk.summary', ['count' => count($rows), 'creatable' => $creatable, 'from' => $period?->label() ?? '—', 'to' => $period?->next()->label() ?? '—']) }}
                @if (count($rows) >= $limit)
                    <span class="text-warning">{{ __('diary.follow_up.bulk.limit', ['limit' => $limit]) }}</span>
                @endif
            </p>
            @if ($creatable > 0)
                <form method="POST" action="{{ route('diary.follow-up.bulk') }}">
                    @csrf
                    <input type="hidden" name="entry_type" value="{{ $selectedType?->sqid }}">
                    <input type="hidden" name="period_key" value="{{ $period?->key() }}">
                    <x-button type="submit" tone="primary" size="sm" icon="event_repeat">{{ __('diary.follow_up.bulk.submit', ['count' => $creatable]) }}</x-button>
                </form>
            @endif
        </div>
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Kunde') }}</th>
                    <th>{{ __('diary.follow_up.bulk.column.source') }}</th>
                    <th>{{ __('diary.product_group.label') }}</th>
                    <th>{{ __('Zuständig') }}</th>
                    <th>{{ __('diary.follow_up.bulk.column.target') }}</th>
                    <th>{{ __('diary.follow_up.bulk.column.result') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($rows as $row)
                <tr class="hover">
                    <td class="text-sm">{{ $row['source']->customer?->name ?? '—' }}</td>
                    <td class="text-sm"><x-order-link :entry="$row['source']" link-class="link link-hover">{{ \App\Services\Diary\Approvals\OrderReviewInboxSubject::title($row['source']) }}</x-order-link></td>
                    <td class="text-sm">{{ $row['source']->productGroupCode() ?? '—' }}</td>
                    <td class="text-sm">{{ $row['source']->assignedUser?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap text-sm">{{ $row['target']->label() }}</td>
                    <td class="text-sm">
                        @if ($row['blocked'] === null)
                            <x-status-badge tone="success" size="sm">{{ __('diary.follow_up.bulk.result.create') }}</x-status-badge>
                        @elseif ($row['blocked'] === 'exists' && $row['existing'])
                            <x-status-badge tone="ghost" size="sm">{{ __('diary.follow_up.bulk.result.exists') }}</x-status-badge>
                            <x-order-link :entry="$row['existing']" link-class="link text-xs">#{{ $row['existing']->id }}</x-order-link>
                        @else
                            <x-status-badge tone="warning" size="sm">{{ __('diary.follow_up.bulk.result.' . $row['blocked']) }}</x-status-badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
