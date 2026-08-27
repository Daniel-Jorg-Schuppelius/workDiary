{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : backup-status.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Sicherungen" — Daten: BackupStatusWidget.
--}}
@php
    $sources = $status['sources'] ?? [];
    $overdue = collect($sources)->filter(fn (array $s): bool => (bool) ($s['overdue'] ?? false));
@endphp
<x-card :title="__('Sicherungen')" icon="backup">
    <x-slot:actions>
        <x-button href="{{ route('admin.backup.status') }}" tone="ghost" size="xs">{{ __('Status →') }}</x-button>
    </x-slot:actions>

    @if (empty($sources))
        <x-empty-state compact icon="cloud_off"
                       :title="__('Keine Rückmeldung')" :message="__('Es ist noch keine Sicherung gemeldet worden.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($sources as $source)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border {{ ! empty($source['overdue']) ? 'border-error/40 bg-error/5' : 'border-base-300 bg-base-200' }} px-3 py-2">
                    <span class="min-w-0 truncate">{{ $source['source'] ?? '—' }}</span>
                    <span class="shrink-0 text-xs text-muted">{{ $source['occurred_at']?->diffForHumans() }}</span>
                </li>
            @endforeach
        </ul>
        @if ($overdue->isNotEmpty())
            <p class="mt-2 text-xs text-error">{{ __(':n Quelle(n) überfällig', ['n' => $overdue->count()]) }}</p>
        @endif
    @endif
</x-card>
