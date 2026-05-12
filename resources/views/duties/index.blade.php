@extends('layouts.app')
@section('title', __('Arbeitsliste') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Arbeitsliste'))

@section('content')
    @php
        $tabs = [
            'diary'        => ['label' => __('Aufträge'),    'count' => $tabCounts['diary']],
            'bereitschaft' => ['label' => __('Bereitschaft'), 'count' => $tabCounts['bereitschaft']],
            'notdienst'    => ['label' => __('Notdienst'),   'count' => $tabCounts['notdienst']],
            'urlaub'       => ['label' => __('Urlaub'),      'count' => $tabCounts['urlaub']],
        ];
        $tabFilters = array_filter($filters ?? [], fn($v) => $v !== null && $v !== '');
    @endphp

    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">

        {{-- Kopfzeile: Status-Badge + Tabs + Aktions-Buttons --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <span class="badge badge-primary">{{ __('Aktiv') }}</span>
                <div role="tablist" class="tabs tabs-box">
                    @foreach ($tabs as $key => $info)
                        <a role="tab"
                           href="{{ route('duties.index', array_merge($tabFilters, ['tab' => $key])) }}"
                           class="tab {{ $tab === $key ? 'tab-active' : '' }}">
                            {{ $info['label'] }}
                            <span class="badge badge-sm ml-2">{{ $info['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($tab === 'diary')
                    <a href="{{ route('diary.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neuer Auftrag') }}</a>
                @elseif ($tab === 'bereitschaft')
                    <a href="{{ route('shifts.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neue Bereitschaft') }}</a>
                @elseif ($tab === 'notdienst')
                    <a href="{{ route('assignments.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neuer Notdienst') }}</a>
                @else
                    @can('create', \App\Models\Vacation::class)
                        <a href="{{ route('vacations.create') }}?dialog=1" data-entry-modal-trigger class="btn btn-sm btn-primary">{{ __('+ Neuer Antrag') }}</a>
                    @endcan
                @endif
                @if ($tab !== 'urlaub')
                    <a href="{{ route('archive.index', ['tab' => $tab === 'diary' ? 'diary' : $tab]) }}" class="btn btn-sm btn-ghost">{{ __('Archiv') }} →</a>
                @else
                    <a href="{{ route('vacations.index') }}" class="btn btn-sm btn-ghost">{{ __('Alle Anträge') }} →</a>
                @endif
            </div>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('duties.index') }}"
              class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="flex flex-wrap items-end gap-3">
                @if ($tab === 'diary')
                    <div class="flex-1 min-w-52">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Suche') }}</span></label>
                        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                               placeholder="{{ __('Inhalt oder Antwort …') }}"
                               class="input input-bordered input-sm w-full">
                    </div>
                    <div class="flex flex-1 flex-col min-w-40">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                        <select name="status" class="select select-bordered select-sm w-full">
                            <option value="all"  @selected(($filters['status'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                            <option value="2"    @selected(($filters['status'] ?? '') === '2')>{{ __('Offen') }}</option>
                            <option value="3"    @selected(($filters['status'] ?? '') === '3')>{{ __('Problem') }}</option>
                            <option value="1"    @selected(($filters['status'] ?? '') === '1')>{{ __('Bestätigt') }}</option>
                            <option value="-1"   @selected(($filters['status'] ?? '') === '-1')>{{ __('Erledigt') }}</option>
                        </select>
                    </div>
                    @if ($allTags->isNotEmpty())
                        <div class="flex flex-col min-w-36">
                            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Tag') }}</span></label>
                            <select name="tag" class="select select-bordered select-sm">
                                <option value="">—</option>
                                @foreach ($allTags as $tag)
                                    <option value="{{ $tag->id }}" @selected((int) ($filters['tag'] ?? 0) === $tag->id)>{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="flex items-center gap-2 pb-2">
                        <input type="checkbox" id="mine" name="mine" value="1" @checked(!empty($filters['mine'])) class="checkbox checkbox-primary checkbox-sm">
                        <label for="mine" class="text-sm text-base-content/75">{{ __('Nur meine') }}</label>
                    </div>
                @elseif ($tab === 'urlaub')
                    @if ($isAdmin)
                        <div class="flex flex-1 flex-col min-w-44">
                            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                            <select name="user_id" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('Alle') }}</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected((int) ($filters['user_id'] ?? 0) === $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
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
                            <option value="{{ \App\Models\Vacation::STATUS_PENDING }}"   @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_PENDING)>{{ __('Ausstehend') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_APPROVED }}"  @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_APPROVED)>{{ __('Genehmigt') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_REJECTED }}"  @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_REJECTED)>{{ __('Abgelehnt') }}</option>
                            <option value="{{ \App\Models\Vacation::STATUS_CANCELLED }}" @selected(($filters['vstatus'] ?? '') === \App\Models\Vacation::STATUS_CANCELLED)>{{ __('Storniert') }}</option>
                        </select>
                    </div>
                    @if ($isAdmin)
                        <div class="flex items-center gap-2 pb-2">
                            <input type="checkbox" id="mine" name="mine" value="1" @checked(!empty($filters['mine'])) class="checkbox checkbox-primary checkbox-sm">
                            <label for="mine" class="text-sm text-base-content/75">{{ __('Nur meine') }}</label>
                        </div>
                    @endif
                @endif
                <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
                <div class="ml-auto flex items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Filtern') }}</button>
                    @if (! empty($tabFilters))
                        <a href="{{ route('duties.index', ['tab' => $tab]) }}" class="btn btn-sm btn-ghost">{{ __('Zurücksetzen') }}</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- KPI-Kacheln --}}
        @php
            $kpiTiles = $tab === 'diary'
                ? [
                    ['label' => __('Gesamt'),   'value' => $diaryCounts['all'],   'border' => 'border-base-300',   'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary']))],
                    ['label' => __('Offen'),    'value' => $diaryCounts['open'],  'border' => 'border-warning/40', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => '2']))],
                    ['label' => __('Probleme'), 'value' => $diaryCounts['alert'], 'border' => 'border-error/40',   'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => '3']))],
                    ['label' => __('Erledigt'), 'value' => $diaryCounts['done'],  'border' => 'border-success/40', 'href' => route('duties.index', array_merge($tabFilters, ['tab' => 'diary', 'status' => '-1']))],
                ]
                : ($tab === 'bereitschaft'
                    ? [
                        ['label' => __('Gesamt'),                 'value' => $shiftKpis['total'],   'border' => 'border-base-300',     'href' => null],
                        ['label' => __('Längste Schicht (Tage)'), 'value' => $shiftKpis['longest'], 'border' => 'border-info/40',      'href' => null],
                        ['label' => __('Ø Dauer (Tage)'),         'value' => $shiftKpis['avg'],     'border' => 'border-primary/40',   'href' => null],
                        ['label' => __('Mitarbeiter'),            'value' => $shiftKpis['users'],   'border' => 'border-secondary/40', 'href' => null],
                    ]
                    : ($tab === 'notdienst'
                        ? [
                            ['label' => __('Gesamt'),                 'value' => $assignmentKpis['total'],   'border' => 'border-base-300',     'href' => null],
                            ['label' => __('Längste Schicht (Tage)'), 'value' => $assignmentKpis['longest'], 'border' => 'border-info/40',      'href' => null],
                            ['label' => __('Ø Dauer (Tage)'),         'value' => $assignmentKpis['avg'],     'border' => 'border-primary/40',   'href' => null],
                            ['label' => __('Mitarbeiter'),            'value' => $assignmentKpis['users'],   'border' => 'border-secondary/40', 'href' => null],
                        ]
                        : [
                            ['label' => __('Gesamt'),           'value' => $vacationKpis['total'],    'border' => 'border-base-300',   'href' => null],
                            ['label' => __('Ausstehend'),       'value' => $vacationKpis['pending'],  'border' => 'border-warning/40', 'href' => null],
                            ['label' => __('Genehmigt (Jahr)'), 'value' => $vacationKpis['approved'], 'border' => 'border-success/40', 'href' => null],
                            ['label' => __('Abgelehnt'),        'value' => $vacationKpis['rejected'], 'border' => 'border-error/40',   'href' => null],
                        ]
                    ));
            $activeStatus = (string) ($filters['status'] ?? 'all');
        @endphp
        <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($kpiTiles as $tile)
                @php
                    $isActive = $tab === 'diary' && (
                        ($tile['label'] === __('Gesamt')   && $activeStatus === 'all') ||
                        ($tile['label'] === __('Offen')    && $activeStatus === '2') ||
                        ($tile['label'] === __('Probleme') && $activeStatus === '3') ||
                        ($tile['label'] === __('Erledigt') && $activeStatus === '-1')
                    );
                    $ring = $isActive ? ' border-primary ring-1 ring-primary/40' : '';
                    $base = 'rounded-box border bg-base-100 px-4 py-3 shadow-xs ' . $tile['border'];
                @endphp
                @if ($tile['href'])
                    <a href="{{ $tile['href'] }}" class="{{ $base }} transition hover:border-primary hover:shadow-md{{ $ring }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format((float) $tile['value'], 0, ',', '.') }}</p>
                    </a>
                @else
                    <div class="{{ $base }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $tile['label'] }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format((float) $tile['value'], 0, ',', '.') }}</p>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Inhalt --}}
        @if ($tab === 'diary')
            {{-- Aufträge: Karten-Ansicht --}}
            <div class="min-h-0 flex-1 overflow-y-auto space-y-3 pr-1">
                @forelse ($entries as $entry)
                    <article class="grid gap-4 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs transition hover:border-primary/30 md:grid-cols-[1fr_auto]">
                        <div>
                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <span @class([
                                    'badge badge-sm',
                                    'badge-success' => $entry->statusTone() === 'done',
                                    'badge-info'    => $entry->statusTone() === 'progress',
                                    'badge-warning' => $entry->statusTone() === 'open',
                                    'badge-error'   => $entry->statusTone() === 'alert',
                                    'badge-ghost'   => $entry->statusTone() === 'neutral',
                                ])>{{ $entry->statusLabel() }}</span>
                                <span class="text-sm text-base-content/70">{{ $entry->user?->name ?? '—' }}</span>
                            </div>
                            <p class="text-base leading-relaxed text-base-content">
                                @php
                                    $snippet = truncate($entry->content, 240);
                                    $needle  = trim((string) ($filters['q'] ?? ''));
                                @endphp
                                @if ($needle !== '')
                                    {!! preg_replace('/(' . preg_quote($needle, '/') . ')/i', '<mark class="bg-warning/40 px-0.5 rounded">$1</mark>', e($snippet)) !!}
                                @else
                                    {{ $snippet }}
                                @endif
                            </p>
                            @if ($entry->tags->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach ($entry->tags as $tag)
                                        <span class="badge badge-outline badge-sm"
                                              @if ($tag->color) style="border-color:{{ $tag->color }};color:{{ $tag->color }};" @endif>#{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-base-content/65">
                                @if ($entry->start_at)<span>{{ __('Von') }} {{ $entry->start_at->format('d.m.Y H:i') }}</span>@endif
                                @if ($entry->end_at)<span>{{ __('Bis') }} {{ $entry->end_at->format('d.m.Y H:i') }}</span>@endif
                                <span>{{ $entry->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 md:items-end md:justify-between">
                            <a href="{{ route('diary.show', $entry) }}" data-entry-modal-trigger class="btn btn-outline btn-primary btn-sm">{{ __('Details') }}</a>
                            @can('update', $entry)
                                <a href="{{ route('diary.edit', $entry) }}" data-entry-modal-trigger class="btn btn-ghost btn-sm">{{ __('Bearbeiten') }}</a>
                            @endcan
                        </div>
                    </article>
                @empty
                    <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-8 text-center text-base-content/70">
                        {{ __('Keine Einträge gefunden.') }}
                        @if (! empty($tabFilters))
                            <a href="{{ route('duties.index', ['tab' => 'diary']) }}" class="ml-2 text-primary underline">{{ __('Filter zurücksetzen') }}</a>
                        @endif
                    </div>
                @endforelse
            </div>
            @if ($entries->total() > 0)
                <div class="flex-none">
                    <p class="mb-1 text-xs text-base-content/60">{{ __('Seite') }} {{ $entries->currentPage() }} / {{ $entries->lastPage() }} · {{ $entries->total() }} {{ __('Einträge') }}</p>
                    @if ($entries->hasPages())
                        <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                            {{ $entries->links('vendor.pagination.daisyui-simple') }}
                        </div>
                    @endif
                </div>
            @endif

        @elseif ($tab === 'bereitschaft')
            {{-- Bereitschaft: Tabelle --}}
            <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th>{{ __('Beginn') }}</th>
                            <th>{{ __('Ende') }}</th>
                            <th>{{ __('Notiz') }}</th>
                            <th class="w-24 text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shifts as $shift)
                            <tr class="hover">
                                <td>{{ $shift->user?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $shift->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $shift->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="max-w-xs truncate">{{ $shift->note ?? '—' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('shifts.edit', $shift) }}" data-entry-modal-trigger
                                       class="btn btn-xs btn-ghost" title="{{ __('Bearbeiten') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($shifts->total() > 0)
                <div class="flex-none">
                    <p class="mb-1 text-xs text-base-content/60">{{ __('Seite') }} {{ $shifts->currentPage() }} / {{ $shifts->lastPage() }} · {{ $shifts->total() }} {{ __('Einträge') }}</p>
                    @if ($shifts->hasPages())
                        <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                            {{ $shifts->links('vendor.pagination.daisyui-simple') }}
                        </div>
                    @endif
                </div>
            @endif

        @elseif ($tab === 'notdienst')
            {{-- Notdienst: Tabelle --}}
            <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            <th>{{ __('Mitarbeiter') }}</th>
                            <th>{{ __('Beginn') }}</th>
                            <th>{{ __('Ende') }}</th>
                            <th>{{ __('Bereitschaft') }}</th>
                            <th>{{ __('Grund') }}</th>
                            <th class="w-24 text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assignments as $a)
                            <tr class="hover">
                                <td>{{ $a->user?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $a->start_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $a->end_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="text-base-content/60 whitespace-nowrap text-xs">
                                    @if ($a->shift)
                                        {{ $a->shift->start_at?->format('d.m.') }}–{{ $a->shift->end_at?->format('d.m.') }}
                                    @else — @endif
                                </td>
                                <td class="max-w-xs truncate">{{ $a->reason ?? '—' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('assignments.edit', $a) }}" data-entry-modal-trigger
                                       class="btn btn-xs btn-ghost" title="{{ __('Bearbeiten') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($assignments->total() > 0)
                <div class="flex-none">
                    <p class="mb-1 text-xs text-base-content/60">{{ __('Seite') }} {{ $assignments->currentPage() }} / {{ $assignments->lastPage() }} · {{ $assignments->total() }} {{ __('Einträge') }}</p>
                    @if ($assignments->hasPages())
                        <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                            {{ $assignments->links('vendor.pagination.daisyui-simple') }}
                        </div>
                    @endif
                </div>
            @endif

        @elseif ($tab === 'urlaub')
            {{-- Urlaub: Tabelle --}}
            <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
                <table class="table table-sm table-zebra table-pin-rows">
                    <thead class="bg-base-200">
                        <tr>
                            @if ($isAdmin)
                                <th>{{ __('Mitarbeiter') }}</th>
                            @endif
                            <th>{{ __('Typ') }}</th>
                            <th>{{ __('Von') }}</th>
                            <th>{{ __('Bis') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="max-w-xs">{{ __('Notiz') }}</th>
                            <th class="w-24 text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vacations as $v)
                            @php
                                $statusBadge = match ($v->status) {
                                    \App\Models\Vacation::STATUS_PENDING   => 'badge-warning',
                                    \App\Models\Vacation::STATUS_APPROVED  => 'badge-success',
                                    \App\Models\Vacation::STATUS_REJECTED  => 'badge-error',
                                    \App\Models\Vacation::STATUS_CANCELLED => 'badge-ghost',
                                    default                                => 'badge-neutral',
                                };
                                $statusLabel = match ($v->status) {
                                    \App\Models\Vacation::STATUS_PENDING   => __('Ausstehend'),
                                    \App\Models\Vacation::STATUS_APPROVED  => __('Genehmigt'),
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
                                    <td>{{ $v->user?->name ?? '—' }}</td>
                                @endif
                                <td class="whitespace-nowrap">{{ $typeLabel }}</td>
                                <td class="whitespace-nowrap">{{ $v->start_date->format('d.m.Y') }}</td>
                                <td class="whitespace-nowrap">{{ $v->end_date->format('d.m.Y') }}</td>
                                <td><span class="badge badge-sm {{ $statusBadge }}">{{ $statusLabel }}</span></td>
                                <td class="max-w-xs truncate text-base-content/70">{{ $v->note ?? '—' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    @can('update', $v)
                                        <a href="{{ route('vacations.edit', $v) }}" data-entry-modal-trigger
                                           class="btn btn-xs btn-ghost" title="{{ __('Bearbeiten') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $isAdmin ? 7 : 6 }}" class="py-6 text-center text-base-content/60">{{ __('Keine Einträge.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($vacations->total() > 0)
                <div class="flex-none">
                    <p class="mb-1 text-xs text-base-content/60">{{ __('Seite') }} {{ $vacations->currentPage() }} / {{ $vacations->lastPage() }} · {{ $vacations->total() }} {{ __('Einträge') }}</p>
                    @if ($vacations->hasPages())
                        <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                            {{ $vacations->links('vendor.pagination.daisyui-simple') }}
                        </div>
                    @endif
                </div>
            @endif
        @endif

    </div>
@endsection

