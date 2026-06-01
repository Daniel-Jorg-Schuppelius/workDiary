@extends('layouts.app')

@section('title', __('Plugin-Fehler'))
@section('nav-title', __('Plugin-Fehler'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Inbox für Plugin-Fehler aus Boot, Runtime und Healthchecks.')">
    <x-slot:actions>
        <x-icon-btn icon="extension" tone="ghost" size="sm"
                    :href="route('admin.plugins.index')"
                    show-label>{{ __('Plugins') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('admin.plugin-errors.index')" :reset="route('admin.plugin-errors.index')">
        <select name="plugin" class="select select-sm select-bordered w-40 shrink-0">
            <option value="">{{ __('Alle Plugins') }}</option>
            @foreach ($plugins as $p)
                <option value="{{ $p->id() }}" @selected(($filters['plugin'] ?? '') === $p->id())>{{ $p->name() }}</option>
            @endforeach
        </select>
        <select name="phase" class="select select-sm select-bordered w-32 shrink-0">
            <option value="">{{ __('Alle Phasen') }}</option>
            <option value="boot" @selected(($filters['phase'] ?? '') === 'boot')>{{ __('Boot') }}</option>
            <option value="runtime" @selected(($filters['phase'] ?? '') === 'runtime')>{{ __('Runtime') }}</option>
            <option value="healthcheck" @selected(($filters['phase'] ?? '') === 'healthcheck')>{{ __('Healthcheck') }}</option>
        </select>
        <select name="status" class="select select-sm select-bordered w-32 shrink-0">
            <option value="open" @selected(($filters['status'] ?? '') === '' || ($filters['status'] ?? '') === 'open')>{{ __('Offen') }}</option>
            <option value="acknowledged" @selected(($filters['status'] ?? '') === 'acknowledged')>{{ __('Bestätigt') }}</option>
            <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('Alle') }}</option>
        </select>
    </x-filter-bar>

    @if ($errors->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">inbox</span>'
            :title="__('Keine Fehler')"
            :message="__('Aktuell sind keine Plugin-Fehler verzeichnet.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Zeitpunkt') }}</x-table.th>
                    <x-table.th>{{ __('Plugin') }}</x-table.th>
                    <x-table.th>{{ __('Phase') }}</x-table.th>
                    <x-table.th>{{ __('Exception') }}</x-table.th>
                    <x-table.th>{{ __('Nachricht') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Aktion') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($errors as $err)
                <tr class="{{ $err->isAcknowledged() ? 'opacity-60' : '' }}">
                    <td class="text-xs text-base-content/70 whitespace-nowrap">{{ $err->occurred_at->format('Y-m-d H:i:s') }}</td>
                    <td><code class="text-xs">{{ $err->plugin_id }}</code></td>
                    <td>
                        <x-status-badge size="sm" outline>{{ $err->phase }}</x-status-badge>
                    </td>
                    <td class="text-xs">{{ class_basename((string) $err->exception_class) }}</td>
                    <td class="text-sm max-w-md truncate" title="{{ $err->message }}">{{ $err->message }}</td>
                    <td>
                        @if ($err->isAcknowledged())
                            <x-status-badge tone="ghost" size="sm">{{ __('bestätigt') }}</x-status-badge>
                        @else
                            <x-status-badge tone="error" size="sm">{{ __('offen') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <a href="{{ route('admin.plugin-errors.show', $err) }}" class="btn btn-sm btn-ghost" title="{{ __('Details') }}">
                                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                            </a>
                            @if (! $err->isAcknowledged())
                                <form method="POST" action="{{ route('admin.plugin-errors.acknowledge', $err) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-ghost" title="{{ __('Als gesehen markieren') }}">
                                        <span class="material-symbols-outlined" aria-hidden="true">done</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$errors" />
    @endif
</x-index-page>
@endsection
