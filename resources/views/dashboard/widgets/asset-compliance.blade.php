{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : asset-compliance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Fällige Prüfungen" — Daten: AssetComplianceWidget.
--}}
<x-card :title="__('Fällige Prüfungen')" icon="fact_check">
    <x-slot:actions>
        <x-button href="{{ route('asset-compliance.index') }}" tone="ghost" size="xs">{{ __('Prüfkalender →') }}</x-button>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-box border {{ $overdue->isNotEmpty() ? 'border-error/40 bg-error/5' : 'border-base-300 bg-base-200' }} px-4 py-3">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Überfällig') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $overdue->isNotEmpty() ? 'text-error' : '' }}">{{ $overdue->count() }}</p>
        </div>
        <div class="rounded-box border {{ $dueSoon->isNotEmpty() ? 'border-warning/40 bg-warning/5' : 'border-base-300 bg-base-200' }} px-4 py-3">
            <p class="text-xs uppercase tracking-wider text-muted">{{ __('Bald fällig') }}</p>
            <p class="mt-1 font-['Space_Grotesk'] text-2xl font-bold tabular-nums {{ $dueSoon->isNotEmpty() ? 'text-warning' : '' }}">{{ $dueSoon->count() }}</p>
        </div>
    </div>

    @if ($next->isNotEmpty())
        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($next as $assignment)
                <li class="flex items-center justify-between gap-2">
                    <span class="min-w-0 truncate">{{ $assignment->asset?->name ?? '—' }} · {{ $assignment->profile?->name ?? '—' }}</span>
                    <span class="shrink-0 tabular-nums text-muted">{{ $assignment->next_due_on?->fdate() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
