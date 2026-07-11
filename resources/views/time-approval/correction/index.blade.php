{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Meine Korrekturanträge'))
@section('nav-title', __('Meine Korrekturanträge'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Korrekturen an Zeitbuchungen / Anwesenheiten beantragen und verfolgen.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('corrections.create')"
                        show-label>{{ __('Antrag stellen') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('corrections.index')" :reset="route('corrections.index')">
            <select name="status" class="select select-sm select-bordered w-40 shrink-0">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>
                        {{ $s->label() }}
                    </option>
                @endforeach
            </select>
        </x-filter-bar>

        @if ($requests->isEmpty())
            <x-empty-state framed
                icon='<span class="material-symbols-outlined" aria-hidden="true">edit_note</span>'
                :title="__('Keine Korrekturanträge')"
                :message="__('Stellen Sie einen Antrag, um einen Tag in einem gesperrten Monat anzupassen.')" />
        @else
            <x-table scroll="flex" :pinRows="true" table-sort="server"
                     :route="route('corrections.index')" :current-sort="$sort" :current-dir="$dir"
                     :sort-params="array_filter(['status' => $filters['status'] ?: null])">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="scope_date">{{ __('Bezug') }}</x-table.th>
                        <th>{{ __('Mitarbeiter:in') }}</th>
                        <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                        <th class="text-right">{{ __('Items') }}</th>
                        <th class="text-right">{{ __('Aktion') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($requests as $r)
                    <tr>
                        <td class="font-medium">{{ optional($r->scope_date)->fdate() }}</td>
                        <td>{{ $r->user?->name }}</td>
                        <td>
                            <x-status-badge :tone="$r->status->tone()" size="sm">{{ $r->status->label() }}</x-status-badge>
                        </td>
                        <td class="text-right tabular-nums">{{ $r->items->count() }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="arrow_forward" size="sm" tone="ghost"
                                        :href="route('corrections.show', $r)"
                                        :aria-label="__('Öffnen')" />
                        </td>
                    </tr>
                @endforeach
            </x-table>
            <x-pagination :paginator="$requests" standing />
        @endif
    </x-index-page>
@endsection
