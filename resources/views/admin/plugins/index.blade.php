{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Plugins'))
@section('nav-title', __('Plugins'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Plugins verwalten — aktivieren, konfigurieren, Status & Healthchecks prüfen.')">
    <x-slot:actions>
        <x-icon-btn icon="bug_report" tone="ghost" size="sm"
                    :href="route('admin.plugin-errors.index')"
                    show-label>{{ __('Fehler-Inbox') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('admin.plugins.index')" :reset="route('admin.plugins.index')">
        <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        <select name="status" class="select select-sm select-bordered w-40 shrink-0">
            <option value="">{{ __('Alle Status') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Aktiv') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inaktiv') }}</option>
            <option value="error" @selected(($filters['status'] ?? '') === 'error')>{{ __('Auto-deaktiviert') }}</option>
        </select>
    </x-filter-bar>

    @php
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $statusFilter = (string) ($filters['status'] ?? '');
        // Aktiv = Org-Setting ODER ENV-/Config-Fallback (Review 2026-08, A11) —
        // sonst erscheint ein per ENV scharf geschaltetes Plugin als „inaktiv".
        $effectiveEnabled = fn($plugin, $row): bool => (bool) ($row?->enabled ?? false)
            || (bool) config('plugins.' . $plugin->id() . '.enabled', false);
        $filtered = collect($plugins)->filter(function ($plugin) use ($q, $statusFilter, $settings, $states, $effectiveEnabled) {
            if ($q !== '' && ! str_contains(mb_strtolower($plugin->name() . ' ' . $plugin->id() . ' ' . $plugin->description()), $q)) {
                return false;
            }
            $row = $settings[$plugin->id()] ?? null;
            $state = $states[$plugin->id()] ?? null;
            $isAutoDisabled = $state && $state->isAutoDisabled();
            $isEnabled = $effectiveEnabled($plugin, $row);
            return match ($statusFilter) {
                'active' => $isEnabled && ! $isAutoDisabled,
                'inactive' => ! $isEnabled && ! $isAutoDisabled,
                'error' => $isAutoDisabled,
                default => true,
            };
        });
    @endphp

    @if ($filtered->isEmpty())
        @if ($q !== '' || $statusFilter !== '')
            {{-- Filterbewusster Leerzustand (E8): kein irreführender Betreiber-Hinweis. --}}
            <x-empty-state framed
                icon="filter_alt_off"
                :title="__('Keine Treffer')"
                :message="__('Suche/Filter treffen kein Plugin.')">
                <x-slot:action>
                    <a href="{{ route('admin.plugins.index') }}" class="btn btn-sm">{{ __('Filter zurücksetzen') }}</a>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state framed
                icon="extension"
                :title="__('Keine Plugins gefunden')"
                :message="__('Plugin-Klassen werden in config/plugins.php deklariert.')">
                {{-- Prerequisite-Audit (MVP-181): Dateikonfiguration = Betreiber-
                     Aufgabe; die Hilfe erklärt den Einrichtungsweg. --}}
                <x-slot:action>
                    <x-help-button topic="admin.plugins" />
                </x-slot:action>
            </x-empty-state>
        @endif
    @else
        <x-table scroll="flex" :pinRows="true" table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('ID') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Version') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Schema') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Plugin-Status') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Plugin-Zustand') }}</x-table.th>
                    <x-table.th>{{ __('Plugin-Fähigkeiten') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Aktionen') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($filtered as $plugin)
                @php
                    $row = $settings[$plugin->id()] ?? null;
                    $state = $states[$plugin->id()] ?? null;
                    $isEnabled = $effectiveEnabled($plugin, $row);
                    $isOperational = $operational[$plugin->id()] ?? false;
                    $isAutoDisabled = $state && $state->isAutoDisabled();
                    // Deaktivierte Plugins haben keinen Zustand (Review 2026-08, E1):
                    // ein stehen gebliebener Health-Status wäre eine falsche Aussage.
                    $health = $isEnabled || $isAutoDisabled ? $state?->last_health_status : null;
                    $healthClass = match ($health) {
                        'ok' => 'badge-success',
                        'degraded' => 'badge-warning',
                        'failing' => 'badge-error',
                        default => 'badge-ghost',
                    };
                    $healthLabel = match (true) {
                        $health === 'ok' => __('Zustand ok'),
                        $health === 'degraded' => __('Zustand eingeschränkt'),
                        $health === 'failing' => __('Zustand fehlerhaft'),
                        ! $isEnabled && ! $isAutoDisabled => __('Deaktiviert'),
                        default => __('Zustand unbekannt'),
                    };
                    $failureCount = (int) ($state?->failure_count ?? 0);
                    $openErrors = (int) ($errorCounts[$plugin->id()] ?? 0);
                    $checkedAt = $isEnabled || $isAutoDisabled ? $state?->last_health_check_at : null;
                    // Frische (W4b): Ergebnis älter als 2× Check-Intervall (stündlich) gilt als veraltet.
                    $isStale = $checkedAt !== null && $checkedAt->lt(now()->subHours(2));
                @endphp
                <tr data-plugin-row="{{ $plugin->id() }}">
                    <td class="font-medium">{{ $plugin->name() }}</td>
                    <td><code class="text-xs">{{ $plugin->id() }}</code></td>
                    <td class="tabular-nums">
                        {{ $plugin->version() }}
                        @php($compat = $compatibility[$plugin->id()] ?? null)
                        @if ($compat && ! $compat->compatible)
                            <x-status-badge tone="error" size="sm" class="ml-1" title="{{ $compat->message }}">{{ __('plugins.compatibility.incompatible') }}</x-status-badge>
                        @elseif ($compat && ($compat->minAppVersion !== null || $compat->maxAppVersion !== null))
                            <span class="block text-xs text-muted" title="{{ __('plugins.compatibility.range_hint') }}">
                                {{ __('plugins.compatibility.range', ['min' => $compat->minAppVersion ?? '*', 'max' => $compat->maxAppVersion ?? '*']) }}
                            </span>
                        @endif
                    </td>
                    <td class="tabular-nums text-xs text-base-content/70">
                        {{ $state?->installed_version ?? '—' }}
                        @if ($plugin->migrationsPath() !== null && ($state?->installed_version === null || version_compare((string) $state->installed_version, $plugin->schemaVersion(), '<')))
                            {{-- Auslösbar statt Sackgasse (W6/E10): POST admin.plugins.upgrade. --}}
                            <x-action-form :action="route('admin.plugins.upgrade', $plugin->id())" class="inline"
                                  :confirm="__('Schema-Upgrade auf :version ausführen?', ['version' => $plugin->schemaVersion()])"
                                  confirm-tone="primary">
                                <button type="submit" class="badge badge-warning badge-sm ml-1" title="{{ __('Upgrade verfügbar — klicken zum Ausführen') }}">→ {{ $plugin->schemaVersion() }}</button>
                            </x-action-form>
                        @endif
                    </td>
                    <td>
                        @if ($isAutoDisabled)
                            <x-status-badge tone="error" size="sm" title="{{ $state->disabled_reason }}">{{ __('Automatisch deaktiviert') }}</x-status-badge>
                        @elseif ($isEnabled && $isOperational)
                            <x-status-badge tone="success" size="sm">{{ __('Plugin aktiv') }}</x-status-badge>
                        @elseif ($isEnabled)
                            {{-- Aktiviert, aber nicht funktionsfähig (z. B. Key fehlt) — kein falsches Grün (A11). --}}
                            <x-status-badge tone="warning" size="sm" title="{{ __('Aktiviert, aber nicht funktionsfähig — Konfiguration prüfen (z. B. API-Key).') }}">{{ __('Aktiv (unkonfiguriert)') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('Plugin inaktiv') }}</x-status-badge>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $healthClass }} badge-sm {{ $isStale ? 'opacity-60' : '' }}" title="{{ $isEnabled || $isAutoDisabled ? $state?->last_health_message : '' }}" data-health-badge>{{ $healthLabel }}</span>
                        @if ($failureCount > 0)
                            <span class="badge badge-error badge-outline badge-sm ml-1 tabular-nums" data-failure-chip title="{{ __('Aufgezeichnete Fehler in Folge — bei Schwelle :threshold wird das Plugin automatisch deaktiviert.', ['threshold' => (int) config('plugins.auto_disable_threshold', 5)]) }}">{{ $failureCount }} ⚠</span>
                        @endif
                        @if ($openErrors > 0)
                            {{-- Deep-Link auf die gefilterte Fehler-Inbox (W4a / Symptom 2). --}}
                            <a href="{{ route('admin.plugin-errors.index', ['plugin' => $plugin->id()]) }}"
                               class="badge badge-warning badge-outline badge-sm ml-1 tabular-nums"
                               title="{{ __('Offene Fehler dieses Plugins anzeigen') }}">{{ $openErrors }} {{ __('Fehler') }}</a>
                        @endif
                        <span class="text-xs text-muted ml-1" data-health-time
                              title="{{ $state?->last_ok_at ? __('Zuletzt ok: :time', ['time' => $state->last_ok_at->diffForHumans()]) : '' }}">
                            @if ($checkedAt)
                                {{ $checkedAt->diffForHumans() }}@if ($state?->last_health_latency_ms !== null) · {{ $state->last_health_latency_ms }} ms @endif
                                @if ($isStale) · {{ __('veraltet') }} @endif
                            @endif
                        </span>
                        @if (($isEnabled || $isAutoDisabled) && $state?->last_health_message)
                            <div class="text-xs text-muted truncate max-w-xs" title="{{ $state->last_health_message }}">{{ $state->last_health_message }}</div>
                        @endif
                    </td>
                    <td>
                        {{-- Anzeige-Fähigkeiten: erklärte Capabilities plus die,
                             die über eigene Registries laufen (Belegübergabe,
                             Dateispiegelung, Bestands-Rückschrieb). Ohne sie
                             stünden sevDesk, easybill und orgaMAX hier ohne
                             jede Fähigkeit, obwohl sie fakturieren. --}}
                        <div class="flex flex-wrap gap-1">
                            @foreach (app(\App\Plugins\Support\PluginCapabilityOverview::class)->labelsFor($plugin) as $capLabel)
                                <x-status-badge size="sm" outline>{{ $capLabel }}</x-status-badge>
                            @endforeach
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <a href="{{ route('admin.plugins.edit', $plugin->id()) }}" data-entry-modal-trigger
                               class="btn btn-sm btn-ghost" title="{{ __('Konfigurieren') }}">
                                <x-icon name="settings" />
                            </a>
                            <a href="{{ route('admin.plugin-errors.index', ['plugin' => $plugin->id(), 'status' => 'all']) }}"
                               class="btn btn-sm btn-ghost" title="{{ __('Fehler dieses Plugins') }}">
                                <x-icon name="bug_report" />
                            </a>
                            <x-action-form :action="route('admin.plugins.toggle', $plugin->id())">
                                <x-icon-btn type="submit" tone="ghost" size="sm" :icon="$isEnabled ? 'toggle_on' : 'toggle_off'" :label="$isEnabled ? __('Deaktivieren') : __('Aktivieren')" />
                            </x-action-form>
                            @if ($isEnabled || $isAutoDisabled)
                                <x-action-form :action="route('admin.plugins.health-check', $plugin->id())"
                                      data-health-check-form data-plugin-id="{{ $plugin->id() }}" data-plugin-name="{{ $plugin->name() }}">
                                    <x-icon-btn type="submit" tone="ghost" size="sm" icon="monitor_heart" :label="__('Healthcheck ausführen')" />
                                </x-action-form>
                            @endif
                            @if ($isAutoDisabled)
                                <x-action-form :action="route('admin.plugins.reset-errors', $plugin->id())"
                                      :confirm="__('Failure-Counter zurücksetzen und Plugin wieder aktivieren?')"
                                      confirm-tone="primary">
                                    <x-icon-btn type="submit" tone="warning" size="sm" icon="restart_alt" :label="__('Reset & Reaktivieren')" />
                                </x-action-form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>

@push('scripts')
<script @cspNonce>
(function () {
    var STATUS_TONE = { ok: 'success', degraded: 'warning', failing: 'error' };
    var STATUS_TITLE = {
        ok:       '{{ __('Healthcheck: ok') }}',
        degraded: '{{ __('Healthcheck: eingeschränkt') }}',
        failing:  '{{ __('Healthcheck: fehlerhaft') }}'
    };
    var STATUS_LABEL = {
        ok:       '{{ __('Zustand ok') }}',
        degraded: '{{ __('Zustand eingeschränkt') }}',
        failing:  '{{ __('Zustand fehlerhaft') }}'
    };
    var STATUS_BADGE = {
        ok: 'badge-success', degraded: 'badge-warning', failing: 'badge-error'
    };
    var BADGE_CLASSES = ['badge-success', 'badge-warning', 'badge-error', 'badge-ghost'];

    function updateRow(pluginId, data) {
        // Auto-Disable durch diesen Check: Statusspalte + Buttons ändern sich —
        // Seite neu laden statt halb aktualisierter Zeile (W4b/E7).
        if (data.auto_disabled) {
            window.location.reload();
            return;
        }
        var row = document.querySelector('[data-plugin-row="' + (window.CSS && CSS.escape ? CSS.escape(pluginId) : pluginId) + '"]');
        if (!row) return;
        var badge = row.querySelector('[data-health-badge]');
        var time  = row.querySelector('[data-health-time]');
        var chip  = row.querySelector('[data-failure-chip]');
        if (badge) {
            BADGE_CLASSES.forEach(function (c) { badge.classList.remove(c); });
            badge.classList.add(STATUS_BADGE[data.status] || 'badge-ghost');
            badge.classList.remove('opacity-60');
            badge.textContent = STATUS_LABEL[data.status] || data.status || '—';
            if (data.message) badge.setAttribute('title', data.message);
            else badge.removeAttribute('title');
        }
        if (time) {
            time.textContent = '{{ __('gerade eben') }}'
                + (typeof data.latency_ms === 'number' ? ' · ' + data.latency_ms + ' ms' : '');
        }
        if (chip) {
            if (typeof data.failure_count === 'number' && data.failure_count > 0) {
                chip.textContent = data.failure_count + ' ⚠';
            } else {
                chip.remove();
            }
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.hasAttribute('data-health-check-form')) return;

        event.preventDefault();
        var pluginId   = form.getAttribute('data-plugin-id') || '';
        var pluginName = form.getAttribute('data-plugin-name') || pluginId;
        var button     = form.querySelector('button[type="submit"]');
        var icon       = button ? button.querySelector('.material-symbols-outlined') : null;
        var originalIcon = icon ? icon.textContent : null;
        if (button) button.disabled = true;
        if (icon) icon.textContent = 'progress_activity';
        if (icon) icon.classList.add('animate-spin');

        var token = (form.querySelector('input[name="_token"]') || {}).value || '';
        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; });
        }).then(function (result) {
            var data = result.data || {};
            var status = data.status || 'failing';
            var tone = STATUS_TONE[status] || 'error';
            var title = (STATUS_TITLE[status] || '{{ __('Healthcheck') }}') + ' — ' + pluginName;
            var message = data.message && String(data.message).length > 0
                ? data.message
                : (status === 'ok' ? '{{ __('Plugin meldet sich gesund.') }}' : '{{ __('Keine Detailmeldung vom Plugin.') }}');
            updateRow(pluginId, data);
            window.notifyAction({ tone: tone, title: title, message: message });
        }).catch(function (err) {
            window.notifyAction({
                tone: 'error',
                title: '{{ __('Healthcheck fehlgeschlagen') }} — ' + pluginName,
                message: (err && err.message) ? String(err.message) : '{{ __('Anfrage konnte nicht ausgeführt werden.') }}'
            });
        }).then(function () {
            if (button) button.disabled = false;
            if (icon) {
                icon.classList.remove('animate-spin');
                if (originalIcon !== null) icon.textContent = originalIcon;
            }
        });
    });
})();
</script>
@endpush
@endsection
