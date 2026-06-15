{{--
    Ein Auftrag auf dem Dispatch-Board (Leitstelle, Feature 029).
    Erwartet $item = ['entry' => DiaryEntry, 'dispatch' => DispatchStatus,
                      'sla' => SlaStatus, 'hasHardConflict' => bool].
    Reine Anzeige + Drill-down zum Auftrag (diary.show).
--}}
@php
    /** @var \App\Models\DiaryEntry $entry */
    $entry = $item['entry'];
    $dispatch = $item['dispatch'];
    $sla = $item['sla'];
    $assignee = $entry->assignedUser ?? $entry->user;
    $isSlaRisk = in_array($sla->value, ['atRisk', 'breached'], true);
    $window = null;
    if ($entry->start_at !== null) {
        $window = $entry->start_at->format('d.m. H:i')
            . ($entry->end_at !== null ? ' – ' . $entry->end_at->format('H:i') : '');
    }
@endphp
<a href="{{ route('diary.show', $entry) }}"
   class="block rounded-box border border-base-300 bg-base-100 p-2 text-sm transition hover:border-primary/50 hover:shadow-sm"
   data-dispatch-card data-status="{{ $dispatch->value }}">
    <div class="flex items-start justify-between gap-2">
        <span class="line-clamp-2 font-medium">{{ $entry->title }}</span>
        <x-status-badge :tone="$dispatch->tone() === 'open' ? 'info' : ($dispatch->tone() === 'progress' ? 'warning' : ($dispatch->tone() === 'done' ? 'success' : 'neutral'))" size="xs">
            {{ $dispatch->label() }}
        </x-status-badge>
    </div>

    @if ($entry->customer)
        <div class="mt-1 flex items-center gap-1 text-xs text-base-content/70">
            <span class="material-symbols-outlined text-sm" aria-hidden="true">badge</span>
            <span class="truncate">{{ $entry->customer->name }}</span>
        </div>
    @endif

    @if ($window)
        <div class="mt-0.5 flex items-center gap-1 text-xs text-base-content/70">
            <span class="material-symbols-outlined text-sm" aria-hidden="true">schedule</span>
            <span>{{ $window }}</span>
        </div>
    @endif

    @if ($assignee)
        <div class="mt-0.5 flex items-center gap-1 text-xs text-base-content/70">
            <span class="material-symbols-outlined text-sm" aria-hidden="true">person</span>
            <span class="truncate">{{ $assignee->name }}</span>
        </div>
    @endif

    @if ($isSlaRisk || $item['hasHardConflict'])
        <div class="mt-1.5 flex flex-wrap items-center gap-1">
            @if ($item['hasHardConflict'])
                <span class="badge badge-xs badge-error gap-1" title="{{ __('Harter Dispositionskonflikt') }}">
                    <span class="material-symbols-outlined text-xs" aria-hidden="true">warning</span>
                    {{ __('Konflikt') }}
                </span>
            @endif
            @if ($isSlaRisk)
                <span class="badge badge-xs {{ $sla->value === 'breached' ? 'badge-error' : 'badge-warning' }} gap-1"
                      title="{{ __('SLA') }}: {{ $sla->label() }}">
                    <span class="material-symbols-outlined text-xs" aria-hidden="true">timer</span>
                    {{ $sla->label() }}
                </span>
            @endif
        </div>
    @endif
</a>
