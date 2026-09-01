{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Plugin-Fehler'))
@section('nav-title', __('Plugin-Fehler'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Inbox für Plugin-Fehler aus Boot, Runtime und Healthchecks.')">
    <x-slot:actions>
        <x-icon-btn icon="extension" tone="ghost" size="sm"
                    :href="route('admin.plugins.index')"
                    show-label>{{ __('Plugins') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('admin.plugin-errors.index')" :reset="route('admin.plugin-errors.index')">
        {{-- Sortierung überlebt das Filtern (E8): sort/dir als Hidden-Inputs. --}}
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="dir" value="{{ $dir }}">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               class="input input-sm input-bordered w-44 shrink-0"
               placeholder="{{ __('Meldung/Exception') }}" aria-label="{{ __('Suche') }}" />
        <select name="plugin" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Plugin') }}">
            <option value="">{{ __('Alle Plugins') }}</option>
            @foreach ($plugins as $p)
                <option value="{{ $p->id() }}" @selected(($filters['plugin'] ?? '') === $p->id())>{{ $p->name() }}</option>
            @endforeach
        </select>
        <select name="phase" class="select select-sm select-bordered w-32 shrink-0" aria-label="{{ __('Phase') }}">
            <option value="">{{ __('Alle Phasen') }}</option>
            <option value="boot" @selected(($filters['phase'] ?? '') === 'boot')>{{ __('Boot') }}</option>
            <option value="runtime" @selected(($filters['phase'] ?? '') === 'runtime')>{{ __('Runtime') }}</option>
            <option value="healthcheck" @selected(($filters['phase'] ?? '') === 'healthcheck')>{{ __('Healthcheck') }}</option>
            <option value="compatibility" @selected(($filters['phase'] ?? '') === 'compatibility')>{{ __('Kompatibilität') }}</option>
            <option value="manual" @selected(($filters['phase'] ?? '') === 'manual')>{{ __('Manuell') }}</option>
        </select>
        <select name="status" class="select select-sm select-bordered w-32 shrink-0" aria-label="{{ __('Status') }}">
            <option value="open" @selected(($filters['status'] ?? '') === '' || ($filters['status'] ?? '') === 'open')>{{ __('Offen') }}</option>
            <option value="acknowledged" @selected(($filters['status'] ?? '') === 'acknowledged')>{{ __('Bestätigt') }}</option>
            <option value="all" @selected(($filters['status'] ?? '') === 'all')>{{ __('Alle') }}</option>
        </select>
        <x-date-range grid-class="flex flex-wrap items-end gap-2"
                      :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''"
                      :from-label="__('Von')" :to-label="__('Bis')" />
    </x-filter-bar>

    @php($hasFilter = collect($filters)->filter(fn($v) => $v !== '' && $v !== 'open')->isNotEmpty())

    @if ($pluginErrors->isEmpty())
        @if ($hasFilter)
            <x-empty-state framed
                icon="filter_alt_off"
                :title="__('Keine Treffer')"
                :message="__('Der aktuelle Filter trifft keine Fehler.')">
                <x-slot:action>
                    <a href="{{ route('admin.plugin-errors.index') }}" class="btn btn-sm">{{ __('Filter zurücksetzen') }}</a>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state framed
                icon="inbox"
                :title="__('Keine Fehler')"
                :message="__('Aktuell sind keine Plugin-Fehler verzeichnet.')" />
        @endif
    @else
        {{-- Bulk-Quittierung (W4c): Checkbox-Auswahl oder alle gefilterten. --}}
        <form method="POST" action="{{ route('admin.plugin-errors.bulk-acknowledge') }}" id="bulk-ack-form" class="contents">
            @csrf
            <input type="hidden" name="plugin" value="{{ $filters['plugin'] ?? '' }}">
            <input type="hidden" name="phase" value="{{ $filters['phase'] ?? '' }}">
            <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
        </form>
        <div class="flex flex-none items-center gap-2">
            <button type="submit" form="bulk-ack-form" class="btn btn-sm">{{ __('Auswahl als gesehen markieren') }}</button>
            <button type="submit" form="bulk-ack-form" name="all_filtered" value="1" class="btn btn-sm btn-ghost"
                    title="{{ __('Quittiert alle offenen Fehler des aktuellen Filters (Plugin/Phase/Suche).') }}">{{ __('Alle gefilterten quittieren') }}</button>
        </div>
        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('admin.plugin-errors.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['plugin' => $filters['plugin'] ?: null, 'phase' => $filters['phase'] ?: null, 'status' => $filters['status'] ?: null, 'q' => $filters['q'] ?: null, 'from' => $filters['from'] ?: null, 'to' => $filters['to'] ?: null])">
            <x-slot:head>
                <tr>
                    <x-table.th class="w-8"><input type="checkbox" class="checkbox checkbox-sm" data-check-all aria-label="{{ __('Alle auswählen') }}"></x-table.th>
                    <x-table.th sort="occurred_at">{{ __('Zeitpunkt') }}</x-table.th>
                    <x-table.th sort="plugin_id">{{ __('Plugin') }}</x-table.th>
                    <x-table.th>{{ __('Organisation') }}</x-table.th>
                    <x-table.th sort="phase">{{ __('Phase') }}</x-table.th>
                    <x-table.th sort="exception_class">{{ __('Exception') }}</x-table.th>
                    <x-table.th sort="message">{{ __('Nachricht') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Anzahl') }}</x-table.th>
                    <x-table.th sort="acknowledged_at">{{ __('Status') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Aktion') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($pluginErrors as $err)
                <tr class="{{ $err->isAcknowledged() ? 'opacity-60' : '' }}">
                    <td>
                        @unless ($err->isAcknowledged())
                            <input type="checkbox" class="checkbox checkbox-sm" name="ids[]" value="{{ $err->sqid }}" form="bulk-ack-form" aria-label="{{ __('Fehler auswählen') }}">
                        @endunless
                    </td>
                    <td class="text-xs text-base-content/70 whitespace-nowrap" title="{{ $err->occurred_at->toDayDateTimeString() }}">
                        {{ $err->occurred_at->format('d.m.Y H:i') }}
                        @if ($err->last_occurred_at && ! $err->last_occurred_at->equalTo($err->occurred_at))
                            <span class="block text-muted">{{ __('zuletzt :time', ['time' => $err->last_occurred_at->diffForHumans()]) }}</span>
                        @endif
                    </td>
                    <td>
                        {{-- Rückweg zum Plugin (W4a): Übersicht mit passender Suche öffnen. --}}
                        <a href="{{ route('admin.plugins.index', ['q' => $err->plugin_id]) }}" class="link link-hover"><code class="text-xs">{{ $err->plugin_id }}</code></a>
                    </td>
                    <td class="text-xs">
                        {{ $err->organization?->name ?? __('global') }}
                    </td>
                    <td>
                        <x-status-badge size="sm" outline>{{ $err->phase }}</x-status-badge>
                    </td>
                    <td class="text-xs">{{ class_basename((string) $err->exception_class) }}</td>
                    <td class="text-sm max-w-md truncate" title="{{ $err->message }}">{{ $err->message }}</td>
                    <td class="text-right tabular-nums text-xs" title="{{ __('Wiederholungen derselben Störung (dedupliziert)') }}">
                        {{ (int) $err->occurrences > 1 ? '×' . (int) $err->occurrences : '' }}
                    </td>
                    <td>
                        @if ($err->isAcknowledged())
                            <x-status-badge tone="ghost" size="sm">{{ __('bestätigt') }}</x-status-badge>
                        @else
                            <x-status-badge tone="error" size="sm">{{ __('offen') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-icon-btn :href="route('admin.plugin-errors.show', $err)" tone="ghost" size="sm" icon="visibility" :label="__('Details')" />
                            @if (! $err->isAcknowledged())
                                <form method="POST" action="{{ route('admin.plugin-errors.acknowledge', $err) }}" class="inline">
                                    @csrf
                                    <x-icon-btn type="submit" tone="ghost" size="sm" icon="done" :label="__('Als gesehen markieren')" />
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.plugin-errors.reopen', $err) }}" class="inline">
                                    @csrf
                                    <x-icon-btn type="submit" tone="ghost" size="sm" icon="undo" :label="__('Wieder öffnen')" />
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$pluginErrors" standing />
    @endif
</x-index-page>

@push('scripts')
<script @cspNonce>
(function () {
    var checkAll = document.querySelector('[data-check-all]');
    if (!checkAll) return;
    checkAll.addEventListener('change', function () {
        document.querySelectorAll('input[name="ids[]"]').forEach(function (box) {
            box.checked = checkAll.checked;
        });
    });
})();
</script>
@endpush
@endsection
