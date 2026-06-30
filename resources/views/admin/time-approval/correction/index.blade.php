{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Korrekturen-Inbox'))
@section('nav-title', __('Korrekturen-Inbox'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Offene und entschiedene Korrekturanträge der Organisation.')">
        <x-filter-bar :action="route('admin.corrections.index')" :reset="route('admin.corrections.index')">
            <select name="status" class="select select-sm select-bordered w-40 shrink-0">
                <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('Alle Status') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>
                        {{ $s->label() }}
                    </option>
                @endforeach
            </select>
        </x-filter-bar>

        @if ($requests->isEmpty())
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">inbox</span>'
                :title="__('Keine Korrekturanträge im Filter')"
                :message="__('Setzen Sie den Statusfilter auf „Alle Status", um auch entschiedene Anträge zu sehen.')" />
        @else
            <x-table scroll="flex" :pinRows="true" table-sort="server"
                     :route="route('admin.corrections.index')" :current-sort="$sort" :current-dir="$dir"
                     :sort-params="request()->except(['sort', 'dir', 'page'])">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="scope_date">{{ __('Bezug') }}</x-table.th>
                        <th>{{ __('Mitarbeiter:in') }}</th>
                        <th>{{ __('Antragsteller:in') }}</th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <th class="text-right">{{ __('Items') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($requests as $r)
                    <tr>
                        <td class="font-medium">{{ optional($r->scope_date)->fdate() }}</td>
                        <td>{{ $r->user?->name }}</td>
                        <td>{{ $r->requestedBy?->name }}</td>
                        <td>
                            <x-status-badge :tone="$r->status->tone()" size="sm">{{ $r->status->label() }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums">{{ $r->items->count() }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="arrow_forward" size="sm" tone="ghost"
                                        :href="route('admin.corrections.show', $r)"
                                        :aria-label="__('Öffnen')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <x-pagination :paginator="$requests" standing />
        @endif
    </x-index-page>
@endsection
