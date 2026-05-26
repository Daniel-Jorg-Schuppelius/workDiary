@extends('layouts.app')

@section('title', __('Supportzugriffe'))
@section('nav-title', __('Supportzugriffe'))

@php
    /** @var \App\Models\Organization $organization */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $entries */
    /** @var \Illuminate\Database\Eloquent\Collection $actors */
    /** @var array<string, string> $filters */
    /** @var array<int, string> $eventOptions */
@endphp

@section('content')
<x-index-page :subtitle="__('Audit-Spur aller Supportzugriffe (support.*) für :org.', ['org' => $organization->name])">
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
        <x-table>
            <x-slot:head>
                <th>{{ __('Zeitpunkt') }}</th>
                <th>{{ __('Ereignis') }}</th>
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
                        <pre class="whitespace-pre-wrap break-words text-base-content/70">{{ json_encode($entry->changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
</x-index-page>
@endsection
