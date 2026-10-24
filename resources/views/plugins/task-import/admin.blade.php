{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : admin.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Verbindungsseite der Aufgabenimporte (Jira, Asana; Feature 169). Variablen
  aus ManagesTaskImportLinks::index(): $provider, $sourceNoun, $routePrefix,
  $pluginId, $configured, $connection, $connected, $links, $openInbox und
  $connectMode (`oauth`: Anmeldung beim Anbieter, danach Quelle wählen;
  `token`: Zugang in den Plugin-Einstellungen, „Verbindung prüfen“) sowie
  $extraView (Abschnitt eines Plugins, z. B. Redmine-Zeitimport) mit dessen Daten.
--}}
@extends('layouts.app')
@section('title', $provider . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', $provider)

@section('content')
@php
    $r = ['provider' => $provider, 'source' => $sourceNoun];
    $ready = $connection?->isReady() === true;
    $sources = $connected ? $connection->availableSources() : [];
    $token = $connectMode === 'token';
    $sourceReachable = $token || ($connected && $connection->sourceId() !== null && in_array($connection->sourceId(), array_column($sources, 'id'), true));
@endphp
<x-index-page
    :subtitle="__('integration.task_import.page.subtitle', $r)"
    :badge="$connected ? __('integration.task_import.connection.connected') : __('integration.task_import.connection.not_connected')"
    :badge-tone="$ready ? 'success' : ($connected ? 'warning' : 'ghost')">

    <x-slot:actions>
        @if ($ready)
            <x-button :href="route($routePrefix . '.links.create')" data-entry-modal-trigger icon="add_link" placement="bar">{{ __('integration.task_import.links.add') }}</x-button>
            <x-action-form :action="route($routePrefix . '.sync')">
                <x-button type="submit" tone="ghost" icon="sync" placement="menu">{{ __('integration.task_import.connection.sync_now') }}</x-button>
            </x-action-form>
        @endif
        @if ($connected)
            <x-button :href="route('admin.integration.inbox', ['plugin' => $pluginId])" tone="ghost" icon="inbox" placement="menu">{{ __('integration.task_import.connection.open_inbox', ['count' => $openInbox]) }}</x-button>
            <x-action-form :action="route($routePrefix . ($token ? '.verify' : '.oauth.start'))">
                <x-button type="submit" tone="ghost" icon="link" placement="menu">{{ $token ? __('integration.task_import.connection.verify') : __('integration.task_import.connection.reconnect') }}</x-button>
            </x-action-form>
            <x-action-form :action="route($routePrefix . '.disconnect')" :confirm="__('integration.task_import.connection.confirm_disconnect', $r)" confirm-tone="error">
                <x-button type="submit" tone="ghost" icon="link_off" placement="danger">{{ __('integration.task_import.connection.disconnect') }}</x-button>
            </x-action-form>
        @elseif ($configured)
            <x-action-form :action="route($routePrefix . ($token ? '.verify' : '.oauth.start'))">
                <x-button type="submit" icon="link" placement="bar">{{ $token ? __('integration.task_import.connection.verify') : __('integration.task_import.connection.connect', $r) }}</x-button>
            </x-action-form>
        @endif
        @if ($token)
            <x-button :href="route('admin.plugins.edit', $pluginId)" data-entry-modal-trigger tone="ghost" icon="key" placement="menu">{{ __('integration.task_import.connection.settings') }}</x-button>
        @endif
    </x-slot:actions>

    <x-validation-errors first />

    @unless ($configured)
        <div role="alert" class="alert alert-warning text-sm">
            <x-icon name="key_off" />
            <span>{{ $token ? __('integration.task_import.connection.not_configured_token', $r) : __('integration.task_import.connection.not_configured', $r) }}</span>
        </div>
    @endunless

    <x-card :title="__('integration.task_import.connection.title')">
        @if (! $connected)
            <div role="note" class="alert text-sm">
                <x-icon name="privacy_tip" />
                <span>{{ $token ? __('integration.task_import.connection.privacy_note_token', $r) : __('integration.task_import.connection.privacy_note', $r) }}</span>
            </div>
            <x-empty-state icon="link" :title="__('integration.task_import.connection.none', $r)" :message="$token ? __('integration.task_import.connection.none_hint_token', $r) : __('integration.task_import.connection.none_hint', $r)" compact />
        @else
            <x-detail-grid class="sm:grid-cols-2">
                <x-detail-grid.row :label="$sourceNoun">
                    {{ $connection->source_name ?? '—' }}
                    @if ($connection->sourceId() !== null && ! $sourceReachable)
                        <x-status-badge tone="warning" size="xs">{{ __('integration.task_import.connection.source_unreachable') }}</x-status-badge>
                    @endif
                </x-detail-grid.row>
                @if ($token && $connection->source_url)
                    <x-detail-grid.row :label="__('integration.task_import.connection.address')">{{ $connection->source_url }}</x-detail-grid.row>
                @endif
                @if ($connection->account_name)
                    <x-detail-grid.row :label="__('integration.task_import.connection.account')">{{ $connection->account_name }}</x-detail-grid.row>
                @endif
                <x-detail-grid.row :label="__('integration.task_import.connection.connected_at')">{{ $connection->connected_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                <x-detail-grid.row :label="__('integration.task_import.connection.last_sync')">{{ $connection->last_synced_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                @if ($connection->last_error)
                    <x-detail-grid.row :label="__('integration.task_import.connection.last_error')" full class="text-error">{{ $connection->last_error }}</x-detail-grid.row>
                @endif
            </x-detail-grid>

            @if (! $token && $sources !== [] && ($connection->sourceId() === null || $links->total() === 0))
                <form method="POST" action="{{ route($routePrefix . '.source') }}" class="mt-4 flex flex-wrap items-end gap-2">
                    @csrf
                    <x-select-field name="source_id" :label="__('integration.task_import.connection.choose_source', $r)" required>
                        @foreach ($sources as $source)
                            <option value="{{ $source['id'] }}" @selected($connection->sourceId() === $source['id'])>{{ $source['name'] }}@if ($source['url']) · {{ $source['url'] }}@endif</option>
                        @endforeach
                    </x-select-field>
                    <x-button type="submit">{{ __('integration.task_import.connection.save_source') }}</x-button>
                </form>
            @elseif (! $token && $sources === [])
                <p class="mt-3 text-sm text-warning">{{ __('integration.task_import.connection.no_sources', $r) }}</p>
            @endif
        @endif
    </x-card>

    @if ($connected && $connection->sourceId() !== null)
        <x-card padding="p-0">
            <header class="flex items-center justify-between border-b border-base-300 px-4 py-3">
                <span class="text-sm font-semibold">{{ __('integration.task_import.links.title') }}</span>
            </header>
            <x-table bare size="sm">
                <x-slot:head>
                    <tr>
                        <th>{{ __('integration.task_import.links.col.source', $r) }}</th>
                        <th>{{ __('integration.task_import.links.col.target') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('integration.task_import.links.col.last_run') }}</th>
                        <th class="text-right">{{ __('Aktionen') }}</th>
                    </tr>
                </x-slot:head>
                @forelse ($links as $link)
                    <tr>
                        <td>
                            <div class="font-medium">{{ $link->sourceLabel() }}</div>
                            @if ($link->source_project_key)
                                <div class="text-xs text-muted">{{ $link->source_project_key }}</div>
                            @endif
                        </td>
                        <td>{{ $link->project?->name ?? '—' }}</td>
                        <td>
                            <x-status-badge :tone="$link->status->tone()">{{ $link->status->label() }}</x-status-badge>
                            @if ($link->paused_reason === \App\Plugins\Support\TaskSync\TaskImportLink::PAUSED_DISCONNECTED)
                                <div class="text-xs text-muted">{{ __('integration.task_import.links.paused_disconnected') }}</div>
                            @endif
                        </td>
                        <td class="text-sm">
                            {{ $link->last_run_at?->fdatetime() ?? '—' }}
                            @if (is_array($link->last_run_counters))
                                <div class="text-xs text-muted">{{ __('integration.task_import.links.counters', array_map('intval', $link->last_run_counters + ['created' => 0, 'updated' => 0, 'conflicts' => 0, 'inbox' => 0, 'failed' => 0])) }}</div>
                            @endif
                            @if ($link->is_last_run_complete === false)
                                <x-status-badge tone="warning" size="xs">{{ __('integration.task_import.links.incomplete') }}</x-status-badge>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-btn icon="preview" :href="route($routePrefix . '.links.preview', $link)" :label="__('integration.task_import.links.preview')" />
                                @if ($link->status === \App\Enums\Integration\TaskImportLinkStatus::Active)
                                    <x-action-form :action="route($routePrefix . '.links.pause', $link)">
                                        <x-icon-btn icon="pause" type="submit" :label="__('integration.task_import.links.pause')" />
                                    </x-action-form>
                                @elseif ($link->status === \App\Enums\Integration\TaskImportLinkStatus::Paused)
                                    <x-action-form :action="route($routePrefix . '.links.resume', $link)">
                                        <x-icon-btn icon="play_arrow" type="submit" :label="__('integration.task_import.links.resume')" />
                                    </x-action-form>
                                @endif
                                <x-action-form :action="route($routePrefix . '.links.destroy', $link)" method="DELETE" :confirm="__('integration.task_import.links.confirm_remove')" confirm-tone="error">
                                    <x-icon-btn icon="delete" type="submit" tone="error" :label="__('integration.task_import.links.remove')" />
                                </x-action-form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" icon="add_link" :title="__('integration.task_import.links.empty')" :message="__('integration.task_import.links.empty_hint', $r)" compact />
                @endforelse
            </x-table>
            <x-pagination :paginator="$links" standing />
        </x-card>

        @if ($extraView !== null)
            @include($extraView)
        @endif
    @endif
</x-index-page>
@endsection
