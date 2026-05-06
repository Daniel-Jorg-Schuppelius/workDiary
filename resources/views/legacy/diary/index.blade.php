@extends('layouts.app')
@section('title', __('Legacy-Tagebuch') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Einträge'))

@section('content')
    @php
        $currentSort = $sort ?? 'von';
        $currentDir = $dir ?? 'desc';
        $sortLink = function (string $column) use ($currentSort, $currentDir, $filters): string {
            $isCurrent = $currentSort === $column;
            $nextDir = $isCurrent && $currentDir === 'asc' ? 'desc' : 'asc';
            return route('legacy.diary.index', array_merge($filters, ['sort' => $column, 'dir' => $nextDir]));
        };
        $sortIcon = function (string $column) use ($currentSort, $currentDir): string {
            if ($currentSort !== $column) {
                return '↕';
            }

            return $currentDir === 'asc' ? '↑' : '↓';
        };
    @endphp
    <div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
        <form method="GET" action="{{ route('legacy.diary.index') }}" class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
            <input type="hidden" name="sort" value="{{ $currentSort }}">
            <input type="hidden" name="dir" value="{{ $currentDir }}">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-1 flex-col min-w-48">
                    <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Status') }}</span></label>
                    <select name="status" class="select select-bordered select-sm w-full">
                        <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                        <option value="2" @selected(($filters['status'] ?? '') === '2')>{{ __('Offen') }}</option>
                        <option value="3" @selected(($filters['status'] ?? '') === '3')>{{ __('Problem') }}</option>
                        <option value="1" @selected(($filters['status'] ?? '') === '1')>{{ __('Bestätigt') }}</option>
                        <option value="-1" @selected(($filters['status'] ?? '') === '-1')>{{ __('Erledigt') }}</option>
                    </select>
                </div>
                <div class="flex flex-1 flex-col min-w-40">
                    <label class="label py-1"><span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Zeitraum') }}</span></label>
                    <select name="zeitpunkt" class="select select-bordered select-sm w-full">
                        <option value="0" @selected((int) ($filters['zeitpunkt'] ?? 0) === 0)>{{ __('Alle') }}</option>
                        <option value="1" @selected((int) ($filters['zeitpunkt'] ?? 0) === 1)>{{ __('Ab heute') }}</option>
                        <option value="2" @selected((int) ($filters['zeitpunkt'] ?? 0) === 2)>{{ __('Bis heute') }}</option>
                    </select>
                </div>
                <x-date-range :from="$filters['from'] ?? ''" :to="$filters['to'] ?? ''" />
                <label class="label cursor-pointer gap-2 py-1">
                    <input type="checkbox" name="mine" value="1" @checked(!empty($filters['mine'])) class="toggle toggle-sm toggle-primary">
                    <span class="label-text text-sm">{{ __('Nur meine') }}</span>
                </label>
                <div class="ml-auto flex items-end gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Filtern') }}</button>
                    @if (array_filter($filters))
                        <a href="{{ route('legacy.diary.index') }}" class="btn btn-sm btn-ghost" title="{{ __('Filter zurücksetzen') }}" aria-label="{{ __('Filter zurücksetzen') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        </form>

        <div class="flex-none grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $kpiTiles = [
                    ['all',   __('Gesamt'),   'all',  'border-base-300'],
                    ['open',  __('Offen'),    '2',    'border-warning/40'],
                    ['alert', __('Probleme'), '3',    'border-error/40'],
                    ['done',  __('Erledigt'), '-1',   'border-neutral/40'],
                ];
                $activeStatus = (string) ($filters['status'] ?? 'all');
            @endphp
            @foreach ($kpiTiles as [$key, $label, $statusValue, $borderClass])
                @php
                    $tileFilters = $key === 'all' ? [] : array_merge($filters, ['status' => $statusValue]);
                    $isActive = $key === 'all'
                        ? empty(array_filter($filters))
                        : $activeStatus === $statusValue;
                @endphp
                <a href="{{ route('legacy.diary.index', $tileFilters) }}"
                   class="rounded-box border bg-base-100 px-4 py-3 shadow-xs transition hover:border-primary hover:shadow-md {{ $isActive ? 'border-primary ring-1 ring-primary/40' : $borderClass }}">
                    <p class="text-xs uppercase tracking-[0.18em] text-base-content/60">{{ $label }}</p>
                    <p class="mt-2 font-['Space_Grotesk'] text-3xl font-semibold text-base-content">{{ number_format($counts[$key], 0, ',', '.') }}</p>
                </a>
            @endforeach
        </div>

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
                    <button type="submit" id="bulk-apply" class="btn btn-sm btn-primary" disabled>{{ __('Anwenden') }}</button>
                </div>

                <div class="min-h-0 flex-1 overflow-auto">
                <table class="table table-sm table-zebra table-pin-rows">
            <thead class="bg-base-200">
                <tr class="text-base-content/80">
                    <th class="w-8"><input type="checkbox" id="bulk-toggle-all" class="checkbox checkbox-sm" aria-label="{{ __('Alle auswählen') }}"></th>
                    <th class="w-14"><a href="{{ $sortLink('id') }}" class="link link-hover">ID {{ $sortIcon('id') }}</a></th>
                    <th class="w-24 text-center"><a href="{{ $sortLink('status') }}" class="link link-hover">{{ __('Status') }} {!! $sortIcon('status') !!}</a></th>
                    <th class="w-32">{{ __('Mitarbeiter') }}</th>
                    <th>{{ __('Inhalt') }}</th>
                    <th class="w-56">{{ __('Antwort') }}</th>
                    <th class="w-28"><a href="{{ $sortLink('von') }}" class="link link-hover">{{ __('Von') }} {!! $sortIcon('von') !!}</a></th>
                    <th class="w-28"><a href="{{ $sortLink('bis') }}" class="link link-hover">{{ __('Bis') }} {!! $sortIcon('bis') !!}</a></th>
                    <th class="w-24 whitespace-nowrap text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
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
                            || \App\Support\LegacyRoleResolver::isAdmin(Auth::user());
                    @endphp
                    <tr class="hover">
                        <td>
                            @if ($canModify)
                                <input type="checkbox" name="ids[]" value="{{ $entry->id }}" class="checkbox checkbox-sm bulk-row" aria-label="{{ __('Auswählen') }}">
                            @endif
                        </td>
                        <td>{{ $entry->id }}</td>
                        <td class="text-center">
                            <span class="badge badge-sm {{ $badgeClass }}">{{ $entry->statusLabel() }}</span>
                        </td>
                        <td>{{ optional($entry->author)->uname ?? __('Unbekannt') }}</td>
                        <td class="max-w-md truncate" title="{{ $entry->inhalt ?? '' }}">{{ truncate($entry->inhalt ?? '', 120) }}</td>
                        <td class="max-w-xs truncate" title="{{ $entry->antwort ?? '' }}">{{ truncate($entry->antwort ?? '', 80) }}</td>
                        <td>{{ $entry->von?->format('d.m.Y H:i') ?? '-' }}</td>
                        <td>{{ $entry->bis?->format('d.m.Y H:i') ?? '-' }}</td>
                        <td class="whitespace-nowrap text-right">
                            <a href="{{ route('legacy.diary.show', $entry) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost" title="{{ __('Details') }}" aria-label="{{ __('Details') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </a>
                            @if ($canModify)
                                <a href="{{ route('legacy.diary.edit', $entry) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost" title="{{ __('Bearbeiten') }}" aria-label="{{ __('Bearbeiten') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-10 text-center text-base-content/70">{{ __('Keine Legacy-Einträge gefunden.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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

                toggleAll?.addEventListener('change', () => {
                    rows().forEach(c => { c.checked = toggleAll.checked; });
                    refresh();
                });
                form.addEventListener('change', (e) => {
                    if (e.target.classList?.contains('bulk-row')) refresh();
                });
                refresh();
            })();

            function bulkConfirm(e) {
                const form = e.target;
                const action = form.querySelector('select[name="action"]').value;
                const count = form.querySelectorAll('.bulk-row:checked').length;
                if (!action || count === 0) { e.preventDefault(); return false; }
                if (action === 'delete') {
                    return confirm(count + ' {{ __('Eintrag/Einträge wirklich löschen?') }}');
                }
                return true;
            }
        </script>

        @if ($entries->hasPages())
            <div class="flex-none rounded-box border border-base-300 bg-base-100 px-3 py-2 shadow-xs">
                {{ $entries->links('vendor.pagination.daisyui-simple') }}
            </div>
        @endif
    </div>
@endsection
