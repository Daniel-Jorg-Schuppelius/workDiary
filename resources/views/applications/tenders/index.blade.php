{{--
  Created on   : Sun Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Ausschreibungen') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Ausschreibungen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Auftragsbewerbungen mit Fristen, Unterlagen, Einreichung und Entscheidung.')">
    <x-slot:actions>
        @can('create', \App\Models\Applications\ApplicationOpportunity::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('tenders.create')"
                        show-label>{{ __('Ausschreibung erfassen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('tenders.index')" :reset="route('tenders.index')">
        <x-filter-field :label="__('Status')" for="tender-status" class="shrink-0">
            <select id="tender-status" name="status" class="select select-sm select-bordered w-44" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __("values.$s") }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <label class="label cursor-pointer gap-2 shrink-0">
            <input type="checkbox" name="open_only" value="1" class="checkbox checkbox-sm" @checked($filters['open_only'] ?? false)>
            <span class="label-text">{{ __('Nur offene') }}</span>
        </label>
    </x-filter-bar>

    <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
        <x-table table-sort="server"
                 :route="route('tenders.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'asc'"
                 :sort-params="[]"
                 bare scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="title">{{ __('Titel') }}</x-table.th>
                    <x-table.th>{{ __('Kunde') }}</x-table.th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <x-table.th sort="submission_deadline" default>{{ __('Abgabefrist') }}</x-table.th>
                    <x-table.th sort="estimated_value" align="right">{{ __('Wertpotenzial') }}</x-table.th>
                    <x-table.th>{{ __('Go/No-go') }}</x-table.th>
                    <x-table.th></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($opportunities as $opportunity)
                <tr>
                    <td><a href="{{ route('tenders.show', $opportunity) }}" class="link link-hover font-medium">{{ $opportunity->title }}</a></td>
                    <td>{{ $opportunity->customer->name ?? '—' }}</td>
                    <td><x-status-badge :tone="$opportunity->statusTone()" size="sm">{{ __("values.{$opportunity->status}") }}</x-status-badge></td>
                    <td class="tabular-nums">{{ optional($opportunity->submission_deadline)->fdate() ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ $opportunity->estimated_value !== null ? number_format((float) $opportunity->estimated_value, 2, ',', '.') . ' €' : '—' }}</td>
                    <td>{{ __("values.{$opportunity->go_decision}") }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('tenders.show', $opportunity)" :label="__('Anzeigen')" />
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">gavel</span>' :colspan="7" :title="__('Keine Ausschreibungen — „Ausschreibung erfassen" startet die erste Akte.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$opportunities" standing />
</x-index-page>
@endsection
