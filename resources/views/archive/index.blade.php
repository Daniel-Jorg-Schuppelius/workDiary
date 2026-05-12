@extends('layouts.app')
@section('title', __('Archiv') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Archiv'))

@section('content')
    @php
        $tabs = [
            'diary'        => ['label' => __('Aufträge'),   'count' => $counts['diary']],
            'bereitschaft' => ['label' => __('Bereitschaft'), 'count' => $counts['bereitschaft']],
            'notdienst'    => ['label' => __('Notdienst'),  'count' => $counts['notdienst']],
            'urlaub'       => ['label' => __('Urlaub'),     'count' => $counts['urlaub']],
        ];
        $tabFilters = array_filter($filters ?? [], fn($v) => $v !== null && $v !== '');
        $baseFilters = $tabFilters;
        unset($baseFilters['from'], $baseFilters['to'], $baseFilters['status']);
        $currentFrom   = (string) ($filters['from']   ?? '');
        $currentTo     = (string) ($filters['to']     ?? '');
        $currentStatus = (string) ($statusFilter      ?? 'all');

        $tileLink = fn(array $delta) => route('archive.index', array_merge($baseFilters, ['tab' => $tab], $delta));

        $kpiTiles = $tab === 'diary'
            ? [
                ['label' => __('Gesamt'),        'value' => $tabKpis['total'],    'border' => 'border-base-300',    'href' => $tileLink([]),               'active' => $currentStatus === 'all' && $currentFrom === '' && $currentTo === ''],
                ['label' => __('Erledigt'),       'value' => $tabKpis['erledigt'], 'border' => 'border-neutral/40',  'href' => $tileLink(['status' => '-1']), 'active' => $currentStatus === '-1'],
                ['label' => __('Offen'),          'value' => $tabKpis['offen'],    'border' => 'border-warning/40',  'href' => $tileLink(['status' => '2']),  'active' => $currentStatus === '2'],
                ['label' => __('Mit Eskalation'), 'value' => $tabKpis['alert'],    'border' => 'border-error/40',    'href' => $tileLink(['status' => '3']),  'active' => $currentStatus === '3'],
            ]
            : ($tab === 'urlaub'
                ? [
                    ['label' => __('Gesamt'),     'value' => $tabKpis['total'],     'border' => 'border-base-300',   'href' => $tileLink([]), 'active' => $currentFrom === '' && $currentTo === ''],
                    ['label' => __('Abgelehnt'),  'value' => $tabKpis['rejected'],  'border' => 'border-error/40',   'href' => null,          'active' => false],
                    ['label' => __('Storniert'),  'value' => $tabKpis['cancelled'], 'border' => 'border-neutral/40', 'href' => null,          'active' => false],
                    ['label' => __('Abgelaufen'), 'value' => $tabKpis['expired'],   'border' => 'border-info/40',    'href' => null,          'active' => false],
                ]
                : [
                    ['label' => __('Gesamt'),                  'value' => $tabKpis['total'],   'border' => 'border-base-300',     'href' => $tileLink([]), 'active' => $currentFrom === '' && $currentTo === ''],
                    ['label' => __('Längste Schicht (Tage)'),  'value' => $tabKpis['longest'], 'border' => 'border-info/40',      'href' => null,          'active' => false],
                    ['label' => __('Ø Dauer (Tage)'),          'value' => $tabKpis['avg'],     'border' => 'border-primary/40',   'href' => null,          'active' => false],
                    ['label' => __('Mitarbeiter'),             'value' => $tabKpis['users'],   'border' => 'border-secondary/40', 'href' => null,          'active' => false],
                ]
            );
    @endphp

    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">

        {{-- Kopfzeile: Tabs + Cross-Links --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="badge badge-neutral">{{ __('Archiv') }}</span>
                <div role="tablist" class="tabs tabs-box">
                    @foreach ($tabs as $key => $info)
                        <a role="tab"
                           href="{{ route('archive.index', array_merge($tabFilters, ['tab' => $key])) }}"
                           class="tab {{ $tab === $key ? 'tab-active' : '' }}">
                            {{ $info['label'] }}
                            <span class="badge badge-sm ml-2">{{ $info['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('duties.index', ['tab' => match($tab) { 'diary' => 'diary', 'urlaub' => 'urlaub', default => $tab }]) }}" class="btn btn-sm btn-ghost">
                ← {{ __('Aktive Arbeitsliste') }}
            </a>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('archive.index') }}"
              class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="flex flex-wrap items-end gap-3">
                @if ($isAdmin)
                    <div class="flex flex-1 flex-col min-w-44">
                        <label class="label py-1">
                            <span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span>
                        </label>
                        <select name="user_id" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle') }}</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}" @selected((int) ($filters['user_id'] ?? 0) === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if ($tab === 'diary')
                    <div class="flex flex-1 flex-col min-w-44">
                        <label class="label py-1">
                            <span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span>
                        </label>
                        <select name="status" class="select select-bordered select-sm w-full">
                            <option value="all"  @selected($currentStatus === 'all')>{{ __('Alle') }}</option>
                            <option value="2"    @selected($currentStatus === '2')>{{ __('Offen') }}</option>
                            <option value="3"    @selected($currentStatus === '3')>{{ __('Problem') }}</option>
                            <option value="1"    @selected($currentStatus === '1')>{{ __('Bestätigt') }}</option>
                            <option value="-1"   @selected($currentStatus === '-1')>{{ __('Erledigt') }}</option>
                        </select>
                    </div>
                @elseif ($tab === 'urlaub')
                    <div class="flex flex-1 flex-col min-w-40">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Typ') }}</span></label>
                        <select name="vtype" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle Typen') }}</option>
                            <option value="{{ \App\Models\Vacation::TYPE_VACATION }}" @selected(($filters['vtype'] ?? '') === \App\Models\Vacation::TYPE_VACATION)>{{ __('Urlaub') }}</option>
                            <option value="{{ \App\Models\Vacation::TYPE_SICK }}"     @selected(($filters['vtype'] ?? '') === \App\Models\Vacation::TYPE_SICK)>{{ __('Krank') }}</option>
                            <option value="{{ \App\Models\Vacation::TYPE_SPECIAL }}"  @selected(($filters['vtype'] ?? '') === \App\Models\Vacation::TYPE_SPECIAL)>{{ __('Sonderurlaub') }}</option>
                            <option value="{{ \App\Models\Vacation::TYPE_UNPAID }}"   @selected(($filters['vtype'] ?? '') === \App\Models\Vacation::TYPE_UNPAID)>{{ __('Unbezahlt') }}</option>
                        </select>
                    </div>
                    <div class="flex flex-1 flex-col min-w-40">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                        <select name="vstatus" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle Status') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_REJECTED }}"  @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_REJECTED)>{{ __('Abgelehnt') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_CANCELLED }}" @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_CANCELLED)>{{ __('Storniert') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_APPROVED }}"  @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_APPROVED)>{{ __('Abgelaufen') }}</option>
                        </select>
                    </div>
                @endif
                <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
                <div class="ml-auto flex items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Filtern') }}</button>
                    @if (! empty($tabFilters))
                        <a href="{{ route('archive.index', ['tab' => $tab]) }}" class="btn btn-sm btn-ghost">{{ __('Zurücksetzen') }}</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- KPI-Kacheln --}}
        <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpiTiles as $tile)
                @php
                    $base = 'rounded-box border bg-base-100 px-4 py-3 shadow-xs ' . $tile['border'];
                    $ring = $tile['active'] ? ' border-primary ring-1 ring-primary/40' : '';
                @endphp
                @if (! empty($tile['href']))
                    <a href="{{ $tile['href'] }}"
                       class="{{ $base }} transition hover:border-primary hover:shadow-md{{ $ring }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format((int) $tile['value'], 0, ',', '.') }}</p>
                    </a>
                @else
                    <div class="{{ $base }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format((int) $tile['value'], 0, ',', '.') }}</p>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Tabellenbereich --}}
        <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
            @if ($tab === 'urlaub')
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            @if ($isAdmin)
                                <th class="w-32">{{ __('Mitarbeiter') }}</th>
                            @endif
                            <th>{{ __('Typ') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Bis') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="max-w-xs">{{ __('Notiz') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vacationEntries as $v)
                            @php
                                $statusBadge = match ($v->status) {
                                    \App\Models\Vacation::STATUS_APPROVED  => 'badge-success',
                                    \App\Models\Vacation::STATUS_REJECTED  => 'badge-error',
                                    \App\Models\Vacation::STATUS_CANCELLED => 'badge-ghost',
                                    default                                => 'badge-neutral',
                                };
                                $statusLabel = match ($v->status) {
                                    \App\Models\Vacation::STATUS_APPROVED  => __('Abgelaufen'),
                                    \App\Models\Vacation::STATUS_REJECTED  => __('Abgelehnt'),
                                    \App\Models\Vacation::STATUS_CANCELLED => __('Storniert'),
                                    default                                => $v->status,
                                };
                                $typeLabel = match ($v->type) {
                                    \App\Models\Vacation::TYPE_VACATION => __('Urlaub'),
                                    \App\Models\Vacation::TYPE_SICK     => __('Krank'),
                                    \App\Models\Vacation::TYPE_SPECIAL  => __('Sonderurlaub'),
                                    \App\Models\Vacation::TYPE_UNPAID   => __('Unbezahlt'),
                                    default                             => $v->type,
                                };
                            @endphp
                            <tr class="hover">
                                @if ($isAdmin)
                                    <td class="whitespace-nowrap">{{ $v->user?->name ?? '—' }}</td>
                                @endif
                                <td class="whitespace-nowrap">{{ $typeLabel }}</td>
                                <td class="whitespace-nowrap text-xs text-base-content/70">{{ $v->start_date->format('d.m.Y') }}</td>
                                <td class="whitespace-nowrap text-xs text-base-content/70">{{ $v->end_date->format('d.m.Y') }}</td>
                                <td><span class="badge badge-sm {{ $statusBadge }}">{{ $statusLabel }}</span></td>
                                <td class="max-w-xs truncate text-sm text-base-content/70">{{ $v->note ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 6 : 5 }}" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($tab === 'diary')
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            <th class="w-32">{{ __('Mitarbeiter') }}</th>
                            <th class="w-24 text-center">{{ __('Status') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Bis') }}</th>
                            <th class="w-36 whitespace-nowrap">{{ __('Archiviert am') }}</th>
                            <th>{{ __('Inhalt') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($diaryEntries as $entry)
                            <tr class="hover">
                                <td class="whitespace-nowrap">{{ $entry->user?->name ?? '—' }}</td>
                                <td class="text-center">
                                    <span @class([
                                        'badge badge-sm',
                                        'badge-success' => $entry->statusTone() === 'done',
                                        'badge-info'    => $entry->statusTone() === 'progress',
                                        'badge-warning' => $entry->statusTone() === 'open',
                                        'badge-error'   => $entry->statusTone() === 'alert',
                                        'badge-ghost'   => $entry->statusTone() === 'neutral',
                                    ])>{{ $entry->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs text-base-content/70">{{ $entry->archived_at?->format('d.m.Y') ?? '—' }}</td>
                                <td class="text-sm">{{ truncate($entry->content ?? '', 160) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($tab === 'bereitschaft')
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            <th class="w-32">{{ __('Mitarbeiter') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Bis') }}</th>
                            <th>{{ __('Dauer') }}</th>
                            <th>{{ __('Notiz') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shiftEntries as $entry)
                            @php
                                $duration = ($entry->start_at && $entry->end_at)
                                    ? ((int) $entry->start_at->copy()->startOfDay()->diffInDays($entry->end_at->copy()->startOfDay()) + 1)
                                    : null;
                            @endphp
                            <tr class="hover">
                                <td class="whitespace-nowrap">{{ $entry->user?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="text-xs text-base-content/70">
                                    {{ $duration !== null ? trans_choice('{1} :n Tag|[2,*] :n Tage', $duration, ['n' => $duration]) : '—' }}
                                </td>
                                <td class="max-w-xs truncate text-sm">{{ $entry->note ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            <th class="w-32">{{ __('Mitarbeiter') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Von') }}</th>
                            <th class="w-28 whitespace-nowrap">{{ __('Bis') }}</th>
                            <th>{{ __('Dauer') }}</th>
                            <th>{{ __('Grund') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assignmentEntries as $entry)
                            @php
                                $duration = ($entry->start_at && $entry->end_at)
                                    ? ((int) $entry->start_at->copy()->startOfDay()->diffInDays($entry->end_at->copy()->startOfDay()) + 1)
                                    : null;
                            @endphp
                            <tr class="hover">
                                <td class="whitespace-nowrap">{{ $entry->user?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $entry->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="text-xs text-base-content/70">
                                    {{ $duration !== null ? trans_choice('{1} :n Tag|[2,*] :n Tage', $duration, ['n' => $duration]) : '—' }}
                                </td>
                                <td class="max-w-xs truncate text-sm">{{ $entry->reason ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Paginierung --}}
        @php
            $activePaginator = match ($tab) {
                'bereitschaft' => $shiftEntries,
                'notdienst'    => $assignmentEntries,
                'urlaub'       => $vacationEntries,
                default        => $diaryEntries,
            };
        @endphp
        @if ($activePaginator->total() > 0)
            <div class="flex-none">
                <p class="mb-1 text-xs text-base-content/60">
                    {{ __('Seite') }} {{ $activePaginator->currentPage() }} / {{ $activePaginator->lastPage() }}
                    · {{ $activePaginator->total() }} {{ __('Einträge') }}
                </p>
                @if ($activePaginator->hasPages())
                    <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                        {{ $activePaginator->links('vendor.pagination.daisyui-simple') }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Admin: Archivierung starten --}}
        @if ($isAdmin)
            <details class="flex-none rounded-box border border-base-300 bg-base-200">
                <summary class="cursor-pointer px-4 py-2 text-sm font-semibold">
                    ⚙ {{ __('Archivierung starten') }}
                </summary>
                <form method="POST" action="{{ route('archive.run') }}" class="px-4 pb-4 pt-2">
                    @csrf
                    <p class="mb-3 text-sm text-base-content/70">
                        {{ __('Archiviert alle erledigten Tagebucheinträge und abgelaufenen Dienste, die älter als :days Tage sind.', ['days' => config('archive.threshold_days', 30)]) }}
                    </p>
                    <button type="submit" class="btn btn-warning btn-sm"
                            data-confirm-dialog
                            data-confirm-title="{{ __('Archivierung starten') }}"
                            data-confirm-message="{{ __('Archivierung wirklich starten?') }}"
                            data-confirm-label="{{ __('Starten') }}">
                        {{ __('Archivierung starten') }}
                    </button>
                </form>
            </details>
        @endif

    </div>
@endsection
