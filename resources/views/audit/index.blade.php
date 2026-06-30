@extends('layouts.app')
@section('title', __('Audit-Log') . ' — WorkDiary')
@section('nav-title', __('Audit-Log'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $logs */
        /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
        /** @var array<string, string> $events */
        /** @var array<string, string> $types */
        /** @var array<string, string> $filters */
    @endphp
    <x-index-page overflow="clip" :subtitle="__('Prüfprotokoll über Änderungen und Aktionen im System.')">

        <x-filter-bar :action="route('audit.index')" :reset="route('audit.index')">
                <x-filter-field :label="__('Aktion')" for="audit-event">
                    <select id="audit-event" name="event" class="select select-bordered select-sm">
                        <option value="">{{ __('alle') }}</option>
                        @foreach ($events as $ev)
                            <option value="{{ $ev }}" @selected(($filters['event'] ?? '') === $ev)>{{ (new \App\Models\AuditLog(['event' => $ev]))->eventLabel() }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
                <x-filter-field :label="__('Typ')" for="audit-type">
                    <select id="audit-type" name="type" class="select select-bordered select-sm">
                        <option value="">{{ __('alle') }}</option>
                        @foreach ($types as $key => $class)
                            <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ __('entity-types.' . class_basename($class)) }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
                <x-filter-field :label="__('Benutzer')" for="audit-user">
                    <select id="audit-user" name="user_id" class="select select-bordered select-sm">
                        <option value="">{{ __('alle') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->sqid }}" @selected((string) ($filters['user_id'] ?? '') === $u->sqid)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" :zebra="true" size="xs"
                 table-sort="server"
                 :route="route('audit.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="$filters ?? []">
                <x-slot:head>
                    <tr class="bg-base-200">
                        <x-table.th sort="created_at" default class="whitespace-nowrap">{{ __('Zeit') }}</x-table.th>
                        <x-table.th sort="user_id">{{ __('Benutzer') }}</x-table.th>
                        <x-table.th sort="event">{{ __('Aktion') }}</x-table.th>
                        <x-table.th sort="auditable_type">{{ __('Typ') }}</x-table.th>
                        <th>{{ __('Objekt') }}</th>
                        <th>{{ __('Änderungen') }}</th>
                        <x-table.th sort="ip">IP</x-table.th>
                    </tr>
                </x-slot:head>
                    @forelse ($logs as $log)
                        <tr class="hover">
                            <td class="whitespace-nowrap text-xs">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                            <td class="text-xs">{{ optional($log->user)->name ?? '—' }}</td>
                            <td><span class="badge badge-sm">{{ $log->eventLabel() }}</span></td>
                            <td class="text-xs">{{ $log->auditableTypeLabel() }}</td>
                            <td class="text-xs">#{{ $log->auditable_id }}</td>
                            <td class="max-w-md">
                                @if ($log->changes)
                                    <details>
                                        <summary class="cursor-pointer text-xs text-base-content/60">{{ __('Anzeigen') }}</summary>
                                        <pre class="mt-1 max-h-40 overflow-auto whitespace-pre-wrap break-all rounded bg-base-200 p-2 text-xs">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @endif
                            </td>
                            <td class="text-xs text-base-content/60">{{ $log->ip }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :colspan="7" :title="__('Keine Einträge')" compact />
                    @endforelse
        </x-table>

        <x-pagination :paginator="$logs" standing />
    </x-index-page>
@endsection
