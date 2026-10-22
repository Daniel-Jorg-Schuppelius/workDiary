{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', $title)
@section('nav-title', $title)
@include('partials.page-fill')

@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $entries */
    /** @var \Illuminate\Support\Collection $users */
    /** @var \Illuminate\Support\Collection $groups */
    /** @var array<string, string> $filters */
    /** @var array<int, string> $eventOptions */
    $userKey = \App\Support\MorphMap::stableKey(\App\Models\Platform\User::class);
    $groupKey = \App\Support\MorphMap::stableKey(\App\Models\Platform\UserGroup::class);
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="$subtitle">
    <x-filter-bar :action="route($route)" :reset="route($route)">
        <x-date-range class="w-80 shrink-0" :label="false"
                      from-name="from" to-name="to"
                      :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''"
                      :from-label="__('von')" :to-label="__('bis')" />
        <select name="event" class="select select-sm select-bordered w-56 shrink-0" aria-label="{{ __('Ereignis') }}">
            <option value="">{{ __('Alle Ereignisse') }}</option>
            @foreach ($eventOptions as $opt)
                <option value="{{ $opt }}" @selected(($filters['event'] ?? '') === $opt)>{{ (new \App\Models\Audit\AuditLog(['event' => $opt]))->eventLabel() }}</option>
            @endforeach
        </select>
        <input type="text" name="actor" value="{{ $filters['actor'] ?? '' }}"
               class="input input-sm input-bordered w-32 shrink-0"
               placeholder="{{ __('Akteur-ID') }}" aria-label="{{ __('Akteur-ID') }}" />
    </x-filter-bar>

    @if ($entries->isEmpty())
        <x-empty-state framed
            icon="policy" />
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route($route)" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['from' => $filters['from'] ?: null, 'to' => $filters['to'] ?: null, 'event' => $filters['event'] ?: null, 'actor' => $filters['actor'] ?: null])">
            <x-slot:head>
                <x-table.th sort="created_at">{{ __('Zeitpunkt') }}</x-table.th>
                <x-table.th sort="event">{{ __('Ereignis') }}</x-table.th>
                <th>{{ __('Akteur') }}</th>
                <th>{{ __('Betroffen') }}</th>
                <th>{{ __('Details') }}</th>
            </x-slot:head>
            @foreach ($entries as $entry)
                <tr>
                    <td class="whitespace-nowrap text-sm">{{ $entry->created_at?->orgTz()->format('Y-m-d H:i') }}</td>
                    <td class="text-sm">
                        {{ $entry->eventLabel() }}
                        <div class="font-mono text-xs text-muted">{{ $entry->event }}</div>
                    </td>
                    <td class="text-sm">
                        @if ($entry->user_id && $users->has($entry->user_id))
                            {{ $users[$entry->user_id]->name }}
                        @else
                            <span class="text-muted">{{ __('System') }}</span>
                        @endif
                    </td>
                    <td class="text-sm">
                        @if ($entry->auditable_type === $userKey && $users->has($entry->auditable_id))
                            {{ $users[$entry->auditable_id]->name }}
                        @elseif ($entry->auditable_type === $groupKey && $groups->has($entry->auditable_id))
                            {{ $groups[$entry->auditable_id]->name }}
                        @else
                            <span class="text-muted">{{ $entry->auditableTypeLabel() }} #{{ $entry->auditable_id }}</span>
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
