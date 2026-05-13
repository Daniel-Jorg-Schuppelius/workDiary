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
    <div class="mx-auto flex h-[calc(100dvh-11rem)] w-full max-w-screen-2xl flex-col gap-4 px-4 xl:px-8 2xl:px-12">
        <form method="GET" class="flex flex-wrap gap-2 rounded-box border border-base-300 bg-base-100 p-3 shadow-xs">
            <select name="event" class="select select-bordered select-sm">
                <option value="">{{ __('Aktion') }}</option>
                @foreach ($events as $ev)
                    <option value="{{ $ev }}" @selected(($filters['event'] ?? '') === $ev)>{{ $ev }}</option>
                @endforeach
            </select>
            <select name="type" class="select select-bordered select-sm">
                <option value="">{{ __('Typ') }}</option>
                @foreach ($types as $key => $class)
                    <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $key }}</option>
                @endforeach
            </select>
            <select name="user_id" class="select select-bordered select-sm">
                <option value="">{{ __('Benutzer') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((int) ($filters['user_id'] ?? 0) === $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" :label="false" />
            <button class="btn btn-primary btn-sm">{{ __('Filtern') }}</button>
            <a href="{{ route('audit.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurücksetzen') }}</a>
        </form>

        <div class="min-h-0 flex-1 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
        <table class="table table-xs table-zebra table-pin-rows w-full">
                <thead class="bg-base-200">
                    <tr>
                        <th class="whitespace-nowrap">{{ __('Zeit') }}</th>
                        <th>{{ __('Benutzer') }}</th>
                        <th>{{ __('Aktion') }}</th>
                        <th>{{ __('Typ') }}</th>
                        <th>{{ __('Objekt') }}</th>
                        <th>{{ __('Änderungen') }}</th>
                        <th>IP</th>
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
                        <tr><td colspan="7" class="text-center text-sm text-base-content/60 py-6">{{ __('Keine Einträge.') }}</td></tr>
                    @endforelse
                </tbody>
        </table>
        </div>

        <div class="flex-none">{{ $logs->links() }}</div>
    </div>
@endsection
