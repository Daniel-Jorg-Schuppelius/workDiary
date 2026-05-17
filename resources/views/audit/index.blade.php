@extends('layouts.app')
@section('title', __('Audit-Log') . ' — WorkDiary')
@section('nav-title', __('Audit-Log'))

@section('content')
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $logs */
        /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
        /** @var array<string, string> $events */
        /** @var array<string, string> $types */
        /** @var array<string, string> $filters */
    @endphp
    <x-page-shell>
        <x-slot:toolbar>
            <x-filter-bar :action="route('audit.index')" :reset="route('audit.index')">
                <x-filter-field :label="__('Aktion')" for="audit-event">
                    <select id="audit-event" name="event" class="select select-bordered select-sm">
                        <option value="">{{ __('alle') }}</option>
                        @foreach ($events as $ev)
                            <option value="{{ $ev }}" @selected(($filters['event'] ?? '') === $ev)>{{ $ev }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
                <x-filter-field :label="__('Typ')" for="audit-type">
                    <select id="audit-type" name="type" class="select select-bordered select-sm">
                        <option value="">{{ __('alle') }}</option>
                        @foreach ($types as $key => $class)
                            <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $key }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
                <x-filter-field :label="__('Benutzer')" for="audit-user">
                    <select id="audit-user" name="user_id" class="select select-bordered select-sm">
                        <option value="">{{ __('alle') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected((int) ($filters['user_id'] ?? 0) === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            </x-filter-bar>
        </x-slot:toolbar>

        <x-table scroll="flex" :pinRows="true" :zebra="true" size="xs">
                <thead class="bg-base-200">
                    <tr>
                        <?php $p = $filters ?? []; ?>
                        <th class="whitespace-nowrap"><x-sort-th column="created_at" :route="route('audit.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'" default="created_at">{{ __('Zeit') }}</x-sort-th></th>
                        <th><x-sort-th column="user_id" :route="route('audit.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Benutzer') }}</x-sort-th></th>
                        <th><x-sort-th column="event" :route="route('audit.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Aktion') }}</x-sort-th></th>
                        <th><x-sort-th column="auditable_type" :route="route('audit.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">{{ __('Typ') }}</x-sort-th></th>
                        <th>{{ __('Objekt') }}</th>
                        <th>{{ __('Änderungen') }}</th>
                        <th><x-sort-th column="ip" :route="route('audit.index')" :params="$p" :sort="$sort ?? null" :dir="$dir ?? 'desc'">IP</x-sort-th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="hover">
                            <td class="whitespace-nowrap text-xs">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                            <td class="text-xs">{{ optional($log->user)->name ?? '—' }}</td>
                            <td><span class="badge badge-sm">{{ $log->eventLabel() }}</span></td>
                            <td class="text-xs">{{ class_basename($log->auditable_type) }}</td>
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
                        <tr><td colspan="7" class="p-0"><x-empty-state :compact="true" :title="__('Keine Einträge')" /></td></tr>
                    @endforelse
                </tbody>
        </x-table>

        <div class="flex-none">{{ $logs->links() }}</div>
    </x-page-shell>
@endsection
