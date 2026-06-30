{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Supportzugriffe'))
@section('nav-title', __('Supportzugriffe'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \App\Models\Organization $organization */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $entries */
    /** @var \Illuminate\Database\Eloquent\Collection $actors */
    /** @var array<string, string> $filters */
    /** @var array<int, string> $eventOptions */
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('Audit-Spur aller Supportzugriffe (support.*) für :org.', ['org' => $organization->name])">
    <x-filter-bar :action="route('admin.support.access-audit.index')" :reset="route('admin.support.access-audit.index')">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
               class="input input-sm input-bordered w-40 shrink-0"
               aria-label="{{ __('von') }}" />
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
               class="input input-sm input-bordered w-40 shrink-0"
               aria-label="{{ __('bis') }}" />
        <select name="event" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('Ereignis') }}">
            <option value="">{{ __('Alle Ereignisse') }}</option>
            @foreach ($eventOptions as $opt)
                <option value="{{ $opt }}" @selected(($filters['event'] ?? '') === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
        <input type="text" name="actor" value="{{ $filters['actor'] ?? '' }}"
               class="input input-sm input-bordered w-32 shrink-0"
               placeholder="{{ __('Akteur-ID') }}" aria-label="{{ __('Akteur-ID') }}" />
    </x-filter-bar>

    @if ($entries->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">policy</span>' />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('admin.support.access-audit.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['from' => $filters['from'] ?: null, 'to' => $filters['to'] ?: null, 'event' => $filters['event'] ?: null, 'actor' => $filters['actor'] ?: null])">
            <x-slot:head>
                <x-table.th sort="created_at">{{ __('Zeitpunkt') }}</x-table.th>
                <x-table.th sort="event">{{ __('Ereignis') }}</x-table.th>
                <th>{{ __('Akteur') }}</th>
                <th>{{ __('Details') }}</th>
            </x-slot:head>
            @foreach ($entries as $entry)
                <tr>
                    <td class="whitespace-nowrap text-sm">{{ optional($entry->created_at)->format('Y-m-d H:i') }}</td>
                    <td class="font-mono text-xs">{{ $entry->event }}</td>
                    <td class="text-sm">
                        @if ($entry->user_id && $actors->has($entry->user_id))
                            {{ $actors[$entry->user_id]->name }}
                        @else
                            <span class="text-base-content/60">{{ __('System') }}</span>
                        @endif
                    </td>
                    <td class="text-xs">
                        <pre class="whitespace-pre-wrap wrap-break-word text-base-content/70">{{ json_encode($entry->changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$entries" standing />
    @endif
</x-index-page>
@endsection
