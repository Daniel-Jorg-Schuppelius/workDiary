@extends('layouts.app')

@section('title', __('Plugins'))
@section('nav-title', __('Plugins'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
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
        $filtered = collect($plugins)->filter(function ($plugin) use ($q, $statusFilter, $settings, $states) {
            if ($q !== '' && ! str_contains(mb_strtolower($plugin->name() . ' ' . $plugin->id() . ' ' . $plugin->description()), $q)) {
                return false;
            }
            $row = $settings[$plugin->id()] ?? null;
            $state = $states[$plugin->id()] ?? null;
            $isAutoDisabled = $state && $state->isAutoDisabled();
            $isEnabled = (bool) ($row?->enabled ?? false);
            return match ($statusFilter) {
                'active' => $isEnabled && ! $isAutoDisabled,
                'inactive' => ! $isEnabled && ! $isAutoDisabled,
                'error' => $isAutoDisabled,
                default => true,
            };
        });
    @endphp

    @if ($filtered->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">extension</span>'
            :title="__('Keine Plugins gefunden')"
            :message="__('Plugin-Klassen werden in config/plugins.php deklariert.')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Name') }}</x-table.th>
                    <x-table.th>{{ __('ID') }}</x-table.th>
                    <x-table.th>{{ __('Version') }}</x-table.th>
                    <x-table.th>{{ __('Schema') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th>{{ __('Health') }}</x-table.th>
                    <x-table.th>{{ __('Capabilities') }}</x-table.th>
                    <x-table.th class="text-right">{{ __('Aktion') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($filtered as $plugin)
                @php
                    $row = $settings[$plugin->id()] ?? null;
                    $state = $states[$plugin->id()] ?? null;
                    $isEnabled = (bool) ($row?->enabled ?? false);
                    $isAutoDisabled = $state && $state->isAutoDisabled();
                    $health = $state?->last_health_status;
                    $healthClass = match ($health) {
                        'ok' => 'badge-success',
                        'degraded' => 'badge-warning',
                        'failing' => 'badge-error',
                        default => 'badge-ghost',
                    };
                    $healthLabel = match ($health) {
                        'ok' => __('ok'),
                        'degraded' => __('eingeschränkt'),
                        'failing' => __('fehlerhaft'),
                        default => __('unbekannt'),
                    };
                @endphp
                <tr data-plugin-row="{{ $plugin->id() }}">
                    <td class="font-medium">{{ $plugin->name() }}</td>
                    <td><code class="text-xs">{{ $plugin->id() }}</code></td>
                    <td class="tabular-nums">{{ $plugin->version() }}</td>
                    <td class="tabular-nums text-xs text-base-content/70">
                        {{ $state?->installed_version ?? '—' }}
                        @if ($state && $state->installed_version !== null && $state->installed_version !== $plugin->schemaVersion())
                            <x-status-badge tone="warning" size="sm" class="ml-1" title="{{ __('Upgrade verfügbar') }}">→ {{ $plugin->schemaVersion() }}</x-status-badge>
                        @endif
                    </td>
                    <td>
                        @if ($isAutoDisabled)
                            <x-status-badge tone="error" size="sm" title="{{ $state->disabled_reason }}">{{ __('auto-deaktiviert') }}</x-status-badge>
                        @elseif ($isEnabled)
                            <x-status-badge tone="success" size="sm">{{ __('aktiv') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost" size="sm">{{ __('inaktiv') }}</x-status-badge>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $healthClass }} badge-sm" title="{{ $state?->last_health_message }}" data-health-badge>{{ $healthLabel }}</span>
                        <span class="text-xs text-base-content/50 ml-1" data-health-time>
                            @if ($state?->last_health_check_at){{ $state->last_health_check_at->diffForHumans() }}@endif
                        </span>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($plugin->capabilities() as $cap)
                                <x-status-badge size="sm" outline>{{ $cap }}</x-status-badge>
                            @endforeach
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-1">
                            <a href="{{ route('admin.plugins.edit', $plugin->id()) }}" data-entry-modal-trigger
                               class="btn btn-sm btn-ghost" title="{{ __('Konfigurieren') }}">
                                <span class="material-symbols-outlined" aria-hidden="true">settings</span>
                            </a>
                            <form method="POST" action="{{ route('admin.plugins.toggle', $plugin->id()) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-ghost" title="{{ $isEnabled ? __('Deaktivieren') : __('Aktivieren') }}">
                                    <span class="material-symbols-outlined" aria-hidden="true">{{ $isEnabled ? 'toggle_on' : 'toggle_off' }}</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.plugins.health-check', $plugin->id()) }}" class="inline"
                                  data-health-check-form data-plugin-id="{{ $plugin->id() }}" data-plugin-name="{{ $plugin->name() }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-ghost" title="{{ __('Healthcheck ausführen') }}">
                                    <span class="material-symbols-outlined" aria-hidden="true">monitor_heart</span>
                                </button>
                            </form>
                            @if ($isAutoDisabled)
                                <form method="POST" action="{{ route('admin.plugins.reset-errors', $plugin->id()) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Failure-Counter zurücksetzen und Plugin wieder aktivieren?') }}"
                                      data-confirm-tone="primary">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning" title="{{ __('Reset & Reaktivieren') }}">
                                        <span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>

@push('scripts')
<script>
(function () {
    var STATUS_TONE = { ok: 'success', degraded: 'warning', failing: 'error' };
    var STATUS_TITLE = {
        ok:       '{{ __('Healthcheck: ok') }}',
        degraded: '{{ __('Healthcheck: eingeschränkt') }}',
        failing:  '{{ __('Healthcheck: fehlerhaft') }}'
    };
    var STATUS_LABEL = {
        ok:       '{{ __('ok') }}',
        degraded: '{{ __('eingeschränkt') }}',
        failing:  '{{ __('fehlerhaft') }}'
    };
    var STATUS_BADGE = {
        ok: 'badge-success', degraded: 'badge-warning', failing: 'badge-error'
    };
    var BADGE_CLASSES = ['badge-success', 'badge-warning', 'badge-error', 'badge-ghost'];

    function updateRow(pluginId, data) {
        var row = document.querySelector('[data-plugin-row="' + (window.CSS && CSS.escape ? CSS.escape(pluginId) : pluginId) + '"]');
        if (!row) return;
        var badge = row.querySelector('[data-health-badge]');
        var time  = row.querySelector('[data-health-time]');
        if (badge) {
            BADGE_CLASSES.forEach(function (c) { badge.classList.remove(c); });
            badge.classList.add(STATUS_BADGE[data.status] || 'badge-ghost');
            badge.textContent = STATUS_LABEL[data.status] || data.status || '—';
            if (data.message) badge.setAttribute('title', data.message);
            else badge.removeAttribute('title');
        }
        if (time) time.textContent = '{{ __('gerade eben') }}';
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
            if (typeof window.notifyAction === 'function') {
                window.notifyAction({ tone: tone, title: title, message: message });
            } else {
                window.alert(title + '\n\n' + message);
            }
        }).catch(function (err) {
            if (typeof window.notifyAction === 'function') {
                window.notifyAction({
                    tone: 'error',
                    title: '{{ __('Healthcheck fehlgeschlagen') }} — ' + pluginName,
                    message: (err && err.message) ? String(err.message) : '{{ __('Anfrage konnte nicht ausgeführt werden.') }}'
                });
            } else {
                window.alert('{{ __('Healthcheck fehlgeschlagen') }}');
            }
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
