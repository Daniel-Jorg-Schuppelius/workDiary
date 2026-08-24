{{--
  Created on   : Sun Jun 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : audit-log-list.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@props([
    'logs',     // Collection von AuditLog-Einträgen mit ->eventLabel(), ->user, ->created_at
])

{{--
    <x-audit-log-list :logs="$auditLogs" /> — einheitliche Verlauf-/Audit-Liste
    (Event-Badge + Benutzer + Zeitstempel). Üblicherweise innerhalb
    <x-card :title="__('Verlauf')" icon="history">.
--}}

<ul class="divide-y divide-base-300 text-sm">
    @foreach ($logs as $log)
        <li class="flex items-center justify-between gap-2 py-2">
            <span class="flex items-center gap-2">
                <x-status-badge tone="ghost">{{ $log->eventLabel() }}</x-status-badge>
                {{ optional($log->user)->name ?? '—' }}
            </span>
            <span class="text-muted">{{ $log->created_at->fdatetime() }}</span>
        </li>
    @endforeach
</ul>
