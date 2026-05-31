@extends('layouts.app')
@section('title', __('Arbeitsliste') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Arbeitsliste'))

@section('content')
    @php
        $currentSort = $sort ?? 'bis';
        $currentDir  = $dir  ?? 'desc';
        $tabFilters  = array_filter($filters ?? [], fn($v) => $v !== null && $v !== '' && $v !== '0' && $v !== false);
        $tabs = [
            'auftraege'    => ['label' => __('Aufträge'),   'count' => $tabCounts['auftraege']],
            'bereitschaft' => ['label' => __('Bereitschaft'), 'count' => $tabCounts['bereitschaft']],
            'notdienst'    => ['label' => __('Notdienst'),  'count' => $tabCounts['notdienst']],
            'urlaub'       => ['label' => __('Urlaub'),     'count' => $tabCounts['urlaub']],
        ];
    @endphp
    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">

        {{-- Kopfzeile: Status-Badge + Tabs + Archiv-Link --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <x-status-badge tone="primary" size="md">{{ __('Aktiv') }}</x-status-badge>
                <div role="tablist" class="tabs tabs-box flex-none">
                @foreach ($tabs as $key => $info)
                    <a role="tab"
                       href="{{ route('legacy.diary.index', ['tab' => $key]) }}"
                       class="tab {{ $tab === $key ? 'tab-active' : '' }}">
                        {{ $info['label'] }}
                        <span class="badge badge-sm ml-2">{{ $info['count'] }}</span>
                    </a>
                @endforeach
                </div>{{-- tablist --}}
            </div>{{-- badge+tabs --}}
            <div class="flex items-center gap-2">
                @if ($tab === 'auftraege')
                    @if ($isAdmin)
                        <x-icon-btn icon="add" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('legacy.diary.create')"
                                    show-label>{{ __('Neuer Eintrag') }}</x-icon-btn>
                    @endif
                @elseif ($tab === 'bereitschaft')
                    @if ($isAdmin)
                        <x-icon-btn icon="add" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('legacy.oncall.create')"
                                    show-label>{{ __('Neue Bereitschaft') }}</x-icon-btn>
                    @endif
                @elseif ($tab === 'notdienst')
                    @if ($isAdmin)
                        <x-icon-btn icon="add" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('legacy.notdienst.create')"
                                    show-label>{{ __('Neuer Notdienst') }}</x-icon-btn>
                    @endif
                @else
                    @can('create', \App\Models\Vacation::class)
                        <x-icon-btn icon="add" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('vacations.create') . '?dialog=1'"
                                    show-label>{{ __('Neuer Antrag') }}</x-icon-btn>
                    @endcan
                @endif
                @if ($tab !== 'urlaub')
                    <a href="{{ route('legacy.archive.index', ['tab' => $tab === 'auftraege' ? 'auftraege' : $tab]) }}" class="btn btn-sm btn-ghost">{{ __('Archiv') }} →</a>
                @else
                    <a href="{{ route('duties.index', ['tab' => 'urlaub']) }}" class="btn btn-sm btn-ghost">{{ __('Alle Anträge') }} →</a>
                @endif
            </div>
        </div>

        {{-- ══ TAB: AUFTRÄGE ══════════════════════════════════════════════════ --}}
        @switch($tab)
        @case('auftraege')
            <form method="GET" action="{{ route('legacy.diary.index') }}" class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
                <input type="hidden" name="tab" value="auftraege">
                <input type="hidden" name="sort" value="{{ $currentSort }}">
                <input type="hidden" name="dir"  value="{{ $currentDir }}">
                <div class="flex flex-wrap items-end gap-3">
                    @php /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Legacy\Models\LegacyUser> $users */ @endphp
                    @if ($canViewAll && $users->isNotEmpty())
                        <div class="flex flex-1 flex-col min-w-44">
                            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                            <select name="user" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('Alle') }}</option>
                                @foreach ($users as $u)
                                    @php
                                        $legacySqid = \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $u->id);
                                    @endphp
                                    <option value="{{ $legacySqid }}" @selected((string) ($filters['user'] ?? '') === $legacySqid)>{{ $u->uname }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="flex flex-1 flex-col min-w-48">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                        <select name="status" class="select select-bordered select-sm w-full">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                            <option value="2"   @selected(($filters['status'] ?? '') === '2')>{{ __('Offen') }}</option>
                            <option value="3"   @selected(($filters['status'] ?? '') === '3')>{{ __('Problem') }}</option>
                            <option value="1"   @selected(($filters['status'] ?? '') === '1')>{{ __('Bestätigt') }}</option>
                            <option value="-1"  @selected(($filters['status'] ?? '') === '-1')>{{ __('Erledigt') }}</option>
                        </select>
                    </div>
                    <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
                    @if ($canViewAll && ($canFilterMine ?? false))
                        <label class="label cursor-pointer gap-2 py-1">
                            <input type="checkbox" name="mine" value="1" @checked(!empty($filters['mine'])) class="toggle toggle-sm toggle-primary">
                            <span class="label-text text-sm">{{ __('Nur meine') }}</span>
                        </label>
                    @endif
                    <div class="ml-auto flex items-end gap-2">
                        <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit" show-label>{{ __('Filtern') }}</x-icon-btn>
                        @if (array_filter($tabFilters))
                            <x-icon-btn icon="restart_alt" size="sm" :href="route('legacy.diary.index', ['tab' => 'auftraege'])" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
                        @endif
                    </div>
                </div>
            </form>

            {{-- KPI-Kacheln --}}
            <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @php
                    $activeStatus = (string) ($filters['status'] ?? 'all');
                    $kpiTiles = [
                        ['all',   __('Gesamt'),   'all',  'border-base-300'],
                        ['open',  __('Offen'),    '2',    'border-warning/40'],
                        ['alert', __('Probleme'), '3',    'border-error/40'],
                        ['done',  __('Erledigt'), '-1',   'border-neutral/40'],
                    ];
                @endphp
                @foreach ($kpiTiles as [$key, $label, $statusValue, $borderClass])
                    @php
                        $tileUrl = route('legacy.diary.index', array_merge(
                            $key === 'all' ? [] : ['status' => $statusValue],
                            ['tab' => 'auftraege']
                        ));
                        $isActive = $key === 'all' ? ($activeStatus === 'all') : ($activeStatus === $statusValue);
                    @endphp
                    <a href="{{ $tileUrl }}"
                       class="rounded-box border bg-base-100 px-4 py-3 shadow-xs transition hover:border-primary hover:shadow-md {{ $isActive ? 'border-primary ring-1 ring-primary/40' : $borderClass }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $label }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format($diaryCounts[$key], 0, ',', '.') }}</p>
                    </a>
                @endforeach
            </div>

            {{-- Tabelle mit Bulk-Aktionen --}}
            <div class="min-h-0 flex-1 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
                <form method="POST" action="{{ route('legacy.diary.bulk') }}" id="bulk-form" class="flex h-full flex-col" onsubmit="return bulkConfirm(event);">
                    @csrf
                    <div class="flex-none flex flex-wrap items-center gap-2 border-b border-base-300 bg-base-200/60 px-3 py-2">
                        <span class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Bulk-Aktionen') }}</span>
                        <span id="bulk-count" class="badge badge-sm badge-ghost">0 {{ __('ausgewählt') }}</span>
                        <select name="action" class="select select-bordered select-sm" required>
                            <option value="">{{ __('Aktion wählen…') }}</option>
                            <option value="status_open">{{ __('Status → Offen') }}</option>
                            <option value="status_alert">{{ __('Status → Problem') }}</option>
                            <option value="status_progress">{{ __('Status → Bestätigt') }}</option>
                            <option value="status_done">{{ __('Status → Erledigt') }}</option>
                            <option value="delete" data-confirm="1">{{ __('Löschen') }}</option>
                        </select>
                        <x-icon-btn icon="check" tone="primary" size="sm" type="submit" id="bulk-apply" disabled show-label>{{ __('Anwenden') }}</x-icon-btn>
                    </div>
                    <div class="min-h-0 flex-1 overflow-auto">
                        @php $p = array_merge($filters, ['tab' => 'auftraege']); @endphp
                        <x-table table-sort="server" :route="route('legacy.diary.index')" :current-sort="$currentSort" :current-dir="$currentDir" :sort-params="$p" pin-rows bare scroll="none">
                            <x-slot:head>
                                <tr class="bg-base-200 text-base-content/80">
                                    <th class="w-8"><input type="checkbox" id="bulk-toggle-all" class="checkbox checkbox-sm" aria-label="{{ __('Alle auswählen') }}"></th>
                                    <x-table.th sort="status" class="w-24 text-center">{{ __('Status') }}</x-table.th>
                                    <x-table.th sort="mitarbeiter" class="w-32">{{ __('Mitarbeiter') }}</x-table.th>
                                    <th>{{ __('Inhalt') }}</th>
                                    <th class="w-56">{{ __('Antwort') }}</th>
                                    <x-table.th sort="von" class="w-28">{{ __('Von') }}</x-table.th>
                                    <x-table.th sort="bis" class="w-28">{{ __('Bis') }}</x-table.th>
                                    <th class="w-24 whitespace-nowrap text-right">{{ __('Aktion') }}</th>
                                </tr>
                            </x-slot:head>
                            @forelse ($entries as $entry)
                                @php
                                    $badgeClass = match ((int) $entry->gelesen) {
                                        -1 => 'badge-neutral',
                                        1  => 'badge-success',
                                        2  => 'badge-warning',
                                        3  => 'badge-error',
                                        default => 'badge-ghost',
                                    };
                                    $canModify = (int) $entry->user === (int) (Auth::user()->legacy_user_id ?? 0)
                                        || \App\Legacy\Support\LegacyRoleResolver::isAdmin(Auth::user());
                                @endphp
                                <tr class="hover">
                                    <td>
                                        @if ($canModify)
                                            <input type="checkbox" name="ids[]" value="{{ $entry->id }}" class="checkbox checkbox-sm bulk-row" aria-label="{{ __('Auswählen') }}">
                                        @endif
                                    </td>
                                    <td class="text-center"><span class="badge badge-sm {{ $badgeClass }}">{{ $entry->statusLabel() }}</span></td>
                                    <td>{{ optional($entry->author)->uname ?? __('Unbekannt') }}</td>
                                    <td class="max-w-md truncate" title="{{ $entry->inhalt ?? '' }}">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->inhalt ?? '', 120) }}</td>
                                    <td class="max-w-xs truncate" title="{{ $entry->antwort ?? '' }}">{{ \CommonToolkit\Helper\Data\StringHelper::truncate($entry->antwort ?? '', 80) }}</td>
                                    <td>{{ $entry->von?->format('d.m.Y H:i') ?? '-' }}</td>
                                    <td>{{ $entry->bis?->format('d.m.Y H:i') ?? '-' }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        <x-icon-btn icon="visibility"
                                                    data-entry-modal-trigger
                                                    :href="route('legacy.diary.show', $entry)"
                                                    :label="__('Details')" />
                                        @if ($canModify)
                                            <x-icon-btn icon="edit"
                                                        data-entry-modal-trigger
                                                        :href="route('legacy.diary.edit', $entry)"
                                                        :label="__('Bearbeiten')" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">menu_book</span>' :colspan="8" :title="__('Keine Legacy-Einträge gefunden.')" compact />
                            @endforelse
                        </x-table>
                    </div>
                </form>
            </div>
            <script>
                (function () {
                    const form = document.getElementById('bulk-form');
                    if (!form) return;
                    const toggleAll = document.getElementById('bulk-toggle-all');
                    const counter = document.getElementById('bulk-count');
                    const apply = document.getElementById('bulk-apply');
                    const rows = () => Array.from(form.querySelectorAll('.bulk-row'));
                    function refresh() {
                        const checked = rows().filter(c => c.checked).length;
                        counter.textContent = checked + ' {{ __('ausgewählt') }}';
                        apply.disabled = checked === 0;
                        if (toggleAll) {
                            const all = rows();
                            toggleAll.checked = all.length > 0 && checked === all.length;
                            toggleAll.indeterminate = checked > 0 && checked < all.length;
                        }
                    }
                    toggleAll?.addEventListener('change', () => { rows().forEach(c => { c.checked = toggleAll.checked; }); refresh(); });
                    form.addEventListener('change', (e) => { if (e.target.classList?.contains('bulk-row')) refresh(); });
                    refresh();
                })();
                function bulkConfirm(e) {
                    const form = e.target;
                    const action = form.querySelector('select[name="action"]').value;
                    const count = form.querySelectorAll('.bulk-row:checked').length;
                    if (!action || count === 0) { e.preventDefault(); return false; }
                    if (action === 'delete') {
                        e.preventDefault();
                        window.confirmAction({
                            message: count + ' {{ __('Eintrag/Einträge wirklich löschen?') }}',
                            label: '{{ __('Löschen') }}',
                        }).then(function (ok) { if (ok) form.submit(); });
                        return false;
                    }
                    return true;
                }
            </script>
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

        {{-- ══ TAB: BEREITSCHAFT ══════════════════════════════════════════════ --}}
        @break
        @case('bereitschaft')
            <form method="GET" action="{{ route('legacy.diary.index') }}" class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
                <input type="hidden" name="tab" value="bereitschaft">
                <div class="flex flex-wrap items-end gap-3">
                    @if ($canViewAll)
                        <div class="flex flex-col min-w-48">
                            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                            <select name="user" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('Alle') }}</option>
                                @php /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Legacy\Models\LegacyUser> $users */ @endphp
                                @foreach ($users as $u)
                                    @php /** @var \App\Legacy\Models\LegacyUser $u */ @endphp
                                    @php
                                        $legacySqid = \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $u->id);
                                    @endphp
                                    <option value="{{ $legacySqid }}" @selected((string) ($filters['user'] ?? '') === $legacySqid)>{{ $u->uname }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
                    <div class="ml-auto flex items-end gap-2">
                        <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit" show-label>{{ __('Filtern') }}</x-icon-btn>
                        @if (array_filter($tabFilters))
                            <x-icon-btn icon="restart_alt" size="sm" :href="route('legacy.diary.index', ['tab' => 'bereitschaft'])" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
                        @endif
                    </div>
                </div>
            </form>
            <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([['all', __('Gesamt'), 'border-base-300'], ['today', __('Heute aktiv'), 'border-primary/40'], ['upcoming', __('Kommend'), 'border-info/40'], ['past', __('Vergangen'), 'border-neutral/40']] as [$key, $label, $border])
                    <div class="rounded-box border bg-base-100 px-4 py-3 shadow-xs {{ $border }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $label }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format($oncallCounts[$key] ?? 0, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
            <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
                @php $p = array_merge($filters, ['tab' => 'bereitschaft']); @endphp
                <x-table table-sort="server" :route="route('legacy.diary.index')" :current-sort="$currentSort" :current-dir="$currentDir" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            <x-table.th sort="mitarbeiter">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort="von" class="w-32 text-center">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="bis" class="w-32 text-center">{{ __('Bis') }}</x-table.th>
                            <th class="w-32 text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($oncallItems as $item)
                        <tr class="hover">
                            <td>{{ optional($item->mitarbeiter)->uname ?? __('Unbekannt') }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $item->von?->format('d.m.Y') ?? '-' }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $item->bis?->format('d.m.Y') ?? '-' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($isAdmin)
                                    <x-icon-btn icon="edit"
                                                data-entry-modal-trigger
                                                :href="route('legacy.oncall.edit', $item)"
                                                :label="__('Bearbeiten')" />
                                    <form method="POST" action="{{ route('legacy.oncall.destroy', $item) }}" class="inline"
                                          data-confirm-dialog
                                          data-confirm-message="{{ __('Eintrag wirklich löschen?') }}"
                                          data-confirm-label="{{ __('Löschen') }}">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">notifications_active</span>' :colspan="4" :title="__('Keine Bereitschaftseinträge gefunden.')" compact />
                    @endforelse
                </x-table>
            </div>
            @if ($oncallItems->total() > 0)
                <div class="flex-none">
                    <p class="mb-1 text-xs text-base-content/60">{{ __('Seite') }} {{ $oncallItems->currentPage() }} / {{ $oncallItems->lastPage() }} · {{ $oncallItems->total() }} {{ __('Einträge') }}</p>
                    @if ($oncallItems->hasPages())
                        <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                            {{ $oncallItems->links('vendor.pagination.daisyui-simple') }}
                        </div>
                    @endif
                </div>
            @endif

        {{-- ══ TAB: NOTDIENST ═════════════════════════════════════════════════ --}}
        @break
        @case('notdienst')
            <form method="GET" action="{{ route('legacy.diary.index') }}" class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
                <input type="hidden" name="tab" value="notdienst">
                <div class="flex flex-wrap items-end gap-3">
                    @if ($canViewAll)
                        <div class="flex flex-col min-w-48">
                            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                            <select name="user" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('Alle') }}</option>
                                @php /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Legacy\Models\LegacyUser> $users */ @endphp
                                @foreach ($users as $u)
                                    @php /** @var \App\Legacy\Models\LegacyUser $u */ @endphp
                                    @php
                                        $legacySqid = \App\Support\Sqid::encode(\App\Legacy\Models\LegacyUser::class, $u->id);
                                    @endphp
                                    <option value="{{ $legacySqid }}" @selected((string) ($filters['user'] ?? '') === $legacySqid)>{{ $u->uname }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
                    <div class="ml-auto flex items-end gap-2">
                        <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit" show-label>{{ __('Filtern') }}</x-icon-btn>
                        @if (array_filter($tabFilters))
                            <x-icon-btn icon="restart_alt" size="sm" :href="route('legacy.diary.index', ['tab' => 'notdienst'])" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
                        @endif
                    </div>
                </div>
            </form>
            <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([['all', __('Gesamt'), 'border-base-300'], ['today', __('Heute aktiv'), 'border-warning/40'], ['upcoming', __('Kommend'), 'border-info/40'], ['past', __('Vergangen'), 'border-neutral/40']] as [$key, $label, $border])
                    <div class="rounded-box border bg-base-100 px-4 py-3 shadow-xs {{ $border }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $label }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format($notdienstCounts[$key] ?? 0, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
            <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
                @php $p = array_merge($filters, ['tab' => 'notdienst']); @endphp
                <x-table table-sort="server" :route="route('legacy.diary.index')" :current-sort="$currentSort" :current-dir="$currentDir" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            <x-table.th sort="mitarbeiter">{{ __('Mitarbeiter') }}</x-table.th>
                            <x-table.th sort="von" class="w-32 text-center">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="bis" class="w-32 text-center">{{ __('Bis') }}</x-table.th>
                            <th class="w-32 text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($notdienstItems as $item)
                        <tr class="hover">
                            <td>{{ optional($item->mitarbeiter)->uname ?? __('Unbekannt') }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $item->von?->format('d.m.Y') ?? '-' }}</td>
                            <td class="whitespace-nowrap text-xs text-base-content/70">{{ $item->bis?->format('d.m.Y') ?? '-' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($isAdmin)
                                    <x-icon-btn icon="edit"
                                                data-entry-modal-trigger
                                                :href="route('legacy.notdienst.edit', $item)"
                                                :label="__('Bearbeiten')" />
                                    <form method="POST" action="{{ route('legacy.notdienst.destroy', $item) }}" class="inline"
                                          data-confirm-dialog
                                          data-confirm-message="{{ __('Eintrag wirklich löschen?') }}"
                                          data-confirm-label="{{ __('Löschen') }}">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">medical_services</span>' :colspan="4" :title="__('Keine Notdienst-Einträge gefunden.')" compact />
                    @endforelse
                </x-table>
            </div>
            @if ($notdienstItems->total() > 0)
                <div class="flex-none">
                    <p class="mb-1 text-xs text-base-content/60">{{ __('Seite') }} {{ $notdienstItems->currentPage() }} / {{ $notdienstItems->lastPage() }} · {{ $notdienstItems->total() }} {{ __('Einträge') }}</p>
                    @if ($notdienstItems->hasPages())
                        <div class="rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                            {{ $notdienstItems->links('vendor.pagination.daisyui-simple') }}
                        </div>
                    @endif
                </div>
            @endif
        {{-- ══ TAB: URLAUB ════════════════════════════════════════════════════ --}}
        @break
        @case('urlaub')
            <form method="GET" action="{{ route('legacy.diary.index') }}" class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
                <input type="hidden" name="tab" value="urlaub">
                <div class="flex flex-wrap items-end gap-3">
                    @if ($vacationIsAdmin)
                        <div class="flex flex-1 flex-col min-w-44">
                            <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Mitarbeiter') }}</span></label>
                            <select name="user_id" class="select select-bordered select-sm w-full">
                                <option value="">{{ __('Alle') }}</option>
                                @foreach ($vacationUsers ?? [] as $u)
                                    @php /** @var \App\Models\User $u */ @endphp
                                    <option value="{{ $u->sqid }}" @selected((string) ($filters['user_id'] ?? '') === $u->sqid)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="flex flex-1 flex-col min-w-40">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Typ') }}</span></label>
                        <select name="vtype" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle Typen') }}</option>
                            <option value="{{ \App\Enums\Vacation\VacationType::Vacation->value }}" @selected(($filters['vtype'] ?? '') === \App\Enums\Vacation\VacationType::Vacation->value)>{{ __('Urlaub') }}</option>
                            <option value="{{ \App\Enums\Vacation\VacationType::Sick->value }}"     @selected(($filters['vtype'] ?? '') === \App\Enums\Vacation\VacationType::Sick->value)>{{ __('Krank') }}</option>
                            <option value="{{ \App\Enums\Vacation\VacationType::Special->value }}"  @selected(($filters['vtype'] ?? '') === \App\Enums\Vacation\VacationType::Special->value)>{{ __('Sonderurlaub') }}</option>
                            <option value="{{ \App\Enums\Vacation\VacationType::Unpaid->value }}"   @selected(($filters['vtype'] ?? '') === \App\Enums\Vacation\VacationType::Unpaid->value)>{{ __('Unbezahlt') }}</option>
                        </select>
                    </div>
                    <div class="flex flex-1 flex-col min-w-40">
                        <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                        <select name="vstatus" class="select select-bordered select-sm w-full">
                            <option value="">{{ __('Alle Status') }}</option>
                            <option value="{{ \App\Enums\Vacation\VacationStatus::Pending->value }}"   @selected(($filters['vstatus'] ?? '') === \App\Enums\Vacation\VacationStatus::Pending->value)>{{ __('Ausstehend') }}</option>
                            <option value="{{ \App\Enums\Vacation\VacationStatus::Approved->value }}"  @selected(($filters['vstatus'] ?? '') === \App\Enums\Vacation\VacationStatus::Approved->value)>{{ __('Genehmigt') }}</option>
                            <option value="{{ \App\Enums\Vacation\VacationStatus::Rejected->value }}"  @selected(($filters['vstatus'] ?? '') === \App\Enums\Vacation\VacationStatus::Rejected->value)>{{ __('Abgelehnt') }}</option>
                            <option value="{{ \App\Enums\Vacation\VacationStatus::Cancelled->value }}" @selected(($filters['vstatus'] ?? '') === \App\Enums\Vacation\VacationStatus::Cancelled->value)>{{ __('Storniert') }}</option>
                        </select>
                    </div>
                    @if ($vacationIsAdmin)
                        <div class="flex items-center gap-2 pb-2">
                            <input type="checkbox" id="mine" name="mine" value="1" @checked(!empty($filters['mine'])) class="toggle toggle-sm toggle-primary">
                            <label for="mine" class="label-text text-sm">{{ __('Nur meine') }}</label>
                        </div>
                    @endif
                    <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
                    <div class="ml-auto flex items-end gap-2">
                        <x-icon-btn icon="filter_alt" tone="primary" size="sm" type="submit" show-label>{{ __('Filtern') }}</x-icon-btn>
                        @if (array_filter($tabFilters))
                            <x-icon-btn icon="restart_alt" size="sm" :href="route('legacy.diary.index', ['tab' => 'urlaub'])" show-label>{{ __('Zurücksetzen') }}</x-icon-btn>
                        @endif
                    </div>
                </div>
            </form>
            <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['total',    __('Gesamt'),           'border-base-300'],
                    ['pending',  __('Ausstehend'),       'border-warning/40'],
                    ['approved', __('Genehmigt (Jahr)'), 'border-success/40'],
                    ['rejected', __('Abgelehnt'),        'border-error/40'],
                ] as [$key, $label, $border])
                    <div class="rounded-box border bg-base-100 px-4 py-3 shadow-xs {{ $border }}">
                        <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $label }}</p>
                        <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format($vacationKpis[$key] ?? 0, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
            <div class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
                @php $p = array_merge($filters, ['tab' => 'urlaub']); @endphp
                <x-table table-sort="server" :route="route('legacy.diary.index')" :current-sort="$currentSort" :current-dir="$currentDir" :sort-params="$p" pin-rows bare scroll="none">
                    <x-slot:head>
                        <tr class="bg-base-200">
                            @if ($vacationIsAdmin)
                                <x-table.th sort="mitarbeiter">{{ __('Mitarbeiter') }}</x-table.th>
                            @endif
                            <x-table.th sort="typ">{{ __('Typ') }}</x-table.th>
                            <x-table.th sort="von">{{ __('Von') }}</x-table.th>
                            <x-table.th sort="bis">{{ __('Bis') }}</x-table.th>
                            <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                            <th class="max-w-xs">{{ __('Notiz') }}</th>
                            <th class="w-24 text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($vacations as $v)
                        @php
                            $statusBadge = match ($v->status) {
                                \App\Enums\Vacation\VacationStatus::Pending->value   => 'badge-warning',
                                \App\Enums\Vacation\VacationStatus::Approved->value  => 'badge-success',
                                \App\Enums\Vacation\VacationStatus::Rejected->value  => 'badge-error',
                                \App\Enums\Vacation\VacationStatus::Cancelled->value => 'badge-ghost',
                                default                                => 'badge-neutral',
                            };
                            $statusLabel = match ($v->status) {
                                \App\Enums\Vacation\VacationStatus::Pending->value   => __('Ausstehend'),
                                \App\Enums\Vacation\VacationStatus::Approved->value  => __('Genehmigt'),
                                \App\Enums\Vacation\VacationStatus::Rejected->value  => __('Abgelehnt'),
                                \App\Enums\Vacation\VacationStatus::Cancelled->value => __('Storniert'),
                                default                                => $v->status,
                            };
                            $typeLabel = match ($v->type) {
                                \App\Enums\Vacation\VacationType::Vacation->value => __('Urlaub'),
                                \App\Enums\Vacation\VacationType::Sick->value     => __('Krank'),
                                \App\Enums\Vacation\VacationType::Special->value  => __('Sonderurlaub'),
                                \App\Enums\Vacation\VacationType::Unpaid->value   => __('Unbezahlt'),
                                default                             => $v->type,
                            };
                        @endphp
                        <tr class="hover">
                            @if ($vacationIsAdmin)
                                <td>{{ $v->user?->name ?? '—' }}</td>
                            @endif
                            <td class="whitespace-nowrap">{{ $typeLabel }}</td>
                            <td class="whitespace-nowrap">{{ $v->start_date->format('d.m.Y') }}</td>
                            <td class="whitespace-nowrap">{{ $v->end_date->format('d.m.Y') }}</td>
                            <td><span class="badge badge-sm {{ $statusBadge }}">{{ $statusLabel }}</span></td>
                            <td class="max-w-xs truncate text-base-content/70">{{ $v->note ?? '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                @can('update', $v)
                                    <x-icon-btn icon="edit"
                                                data-entry-modal-trigger
                                                :href="route('vacations.edit', $v)"
                                                :label="__('Bearbeiten')" />
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">beach_access</span>' :colspan="$vacationIsAdmin ? 7 : 6" :title="__('Keine Einträge.')" compact />
                    @endforelse
                </x-table>
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
        @break
        @endswitch

    </div>
@endsection
