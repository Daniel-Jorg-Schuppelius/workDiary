{{--
  Created on   : Sun Jun 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : audit-log-list.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'logs',       // Collection von AuditLog-Einträgen mit ->eventLabel(), ->user, ->created_at
    'limit' => 8, // sofort sichtbare Einträge; der Rest steht hinter „Weitere Einträge anzeigen“
])

{{--
    <x-audit-log-list :logs="$auditLogs" /> — einheitliche Verlauf-/Audit-Liste
    (Event-Badge + Benutzer + Zeitstempel). Üblicherweise innerhalb
    <x-card :title="__('Verlauf')" icon="history">.
--}}

@php
    $logs = collect($logs)->values();
    // Ein generisches „Geändert“ neben einem Fachereignis desselben Akteurs
    // innerhalb einer Minute ist Rauschen: das Fachereignis erklärt die
    // Änderung (z. B. „Portal-Sichtbarkeit geändert“ + „Geändert“).
    $specific = $logs->reject(fn ($log) => in_array($log->event, ['updated', 'created'], true));
    $visible = $logs->reject(function ($log) use ($specific): bool {
        if ($log->event !== 'updated') {
            return false;
        }

        return $specific->contains(fn ($other): bool => (int) $other->user_id === (int) $log->user_id
            && abs($other->created_at->getTimestamp() - $log->created_at->getTimestamp()) <= 60);
    })->values();
    $limit = max(1, (int) $limit);
    $shown = $visible->take($limit);
    $more = $visible->slice($limit)->values();
@endphp

<ul class="divide-y divide-base-300 text-sm">
    @foreach ($shown as $log)
        <li class="flex items-center justify-between gap-2 py-2">
            <span class="flex items-center gap-2">
                <x-status-badge tone="ghost">{{ $log->eventLabel() }}</x-status-badge>
                {{ optional($log->user)->name ?? '—' }}
            </span>
            <span class="text-muted">{{ $log->created_at->fdatetime() }}</span>
        </li>
    @endforeach
</ul>
@if ($more->isNotEmpty())
    <details class="mt-1 text-sm">
        <summary class="cursor-pointer list-none py-2 text-primary marker:content-none link link-hover">{{ __('Weitere Einträge anzeigen (:count)', ['count' => $more->count()]) }}</summary>
        <ul class="divide-y divide-base-300 border-t border-base-300">
            @foreach ($more as $log)
                <li class="flex items-center justify-between gap-2 py-2">
                    <span class="flex items-center gap-2">
                        <x-status-badge tone="ghost">{{ $log->eventLabel() }}</x-status-badge>
                        {{ optional($log->user)->name ?? '—' }}
                    </span>
                    <span class="text-muted">{{ $log->created_at->fdatetime() }}</span>
                </li>
            @endforeach
        </ul>
    </details>
@endif
