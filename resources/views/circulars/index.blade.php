{{--
  Created on   : Thu Aug 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kundenrundschreiben (Feature 119, MVP-608): Preisanpassung, Wartungsfenster,
  Notdienstzeiten — Geschäftsmitteilungen mit Nachweis je Empfänger.
--}}

@extends('layouts.app')

@section('title', __('circular.title'))
@section('nav-title', __('circular.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('circular.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('circulars.create')"
                        show-label>{{ __('circular.action.create') }}</x-icon-btn>
        </x-slot:actions>

        <x-table scroll="flex" :pin-rows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('circular.column.subject') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('circular.column.status') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('circular.column.recipients') }}</x-table.th>
                    <x-table.th sort type="number" align="right">{{ __('circular.column.skipped') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('circular.column.sent_at') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($circulars as $circular)
                <tr class="hover">
                    <td class="font-medium">
                        {{ $circular->subject }}
                        @if ($circular->is_mandatory)
                            <x-status-badge tone="warning" outline>{{ __('circular.mandatory_short') }}</x-status-badge>
                        @endif
                    </td>
                    <td>{{ __('circular.status.' . $circular->status) }}</td>
                    <td class="text-right tabular-nums">{{ $circular->sent_count }}</td>
                    <td class="text-right tabular-nums">{{ $circular->skipped_count }}</td>
                    <td class="whitespace-nowrap">{{ optional($circular->sent_at)->fdatetime() ?? '—' }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="visibility" size="xs" tone="ghost"
                                        :href="route('circulars.show', $circular)"
                                        :label="__('circular.action.show')" />
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="6" icon="campaign" :title="__('circular.empty')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$circulars" standing />
    </x-index-page>
@endsection
