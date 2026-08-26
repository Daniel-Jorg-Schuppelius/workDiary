{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  VOB/B-Schreiben (Feature 062, MVP-728): Behinderungsanzeige (§ 6 VOB/B) und
  Bedenkenanmeldung (§ 4 Abs. 3 VOB/B) in einer Liste — die Belegart trennt sie.
--}}

@extends('layouts.app')

@section('title', __('construction.title'))
@section('nav-title', __('construction.title'))

@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('construction.subtitle')">
        <x-slot:actions>
            @foreach ($kinds as $k)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('construction-notices.create', ['kind' => $k->value])"
                            show-label>{{ $k->label() }}</x-icon-btn>
            @endforeach
        </x-slot:actions>

        <x-filter-bar :action="route('construction-notices.index')" :reset="route('construction-notices.index')">
            <x-filter-field :label="__('construction.filter.kind')" for="cn-kind" class="min-w-56">
                <select id="cn-kind" name="kind" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach ($kinds as $k)
                        <option value="{{ $k->value }}" @selected($filters['kind'] === $k->value)>{{ $k->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            <x-filter-field :label="__('construction.filter.status')" for="cn-status" class="min-w-40">
                <select id="cn-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="">{{ __('finance.filter.all') }}</option>
                    @foreach (\App\Enums\Construction\ConstructionNoticeStatus::cases() as $st)
                        <option value="{{ $st->value }}" @selected($filters['status'] === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <x-table scroll="flex" :pin-rows="true" :zebra="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('construction.column.number') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('construction.column.kind') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('construction.column.subject') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('construction.column.project') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('construction.column.occurred_on') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('construction.column.status') }}</x-table.th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @forelse ($notices as $notice)
                <tr class="hover">
                    <td class="font-medium">
                        <a class="link" href="{{ route('construction-notices.show', $notice) }}">{{ $notice->displayNo() }}</a>
                    </td>
                    <td>{{ $notice->kind->label() }}</td>
                    <td>{{ $notice->subject }}</td>
                    <td>{{ $notice->project?->name ?? $notice->site?->name ?? '—' }}</td>
                    <td class="whitespace-nowrap">{{ $notice->occurred_on?->format('d.m.Y') }}</td>
                    <td>
                        <x-status-badge :tone="$notice->status === \App\Enums\Construction\ConstructionNoticeStatus::Draft ? 'ghost' : 'success'" size="sm">
                            {{ $notice->status->label() }}
                        </x-status-badge>
                        @if ($notice->claims_time_extension)
                            <x-status-badge tone="warning" size="sm" outline>{{ __('construction.badge.time_extension') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="picture_as_pdf" size="xs" tone="ghost"
                                        :href="route('construction-notices.pdf', $notice)"
                                        :label="__('construction.action.pdf')" />
                        </div>
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="7" icon="report" :title="__('construction.empty')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$notices" standing />
    </x-index-page>
@endsection
