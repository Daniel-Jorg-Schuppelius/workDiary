@extends('layouts.app')
@section('title', __('Feiertage') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Feiertage'))

@section('content')
@php
    $currentYear = (int) \Carbon\Carbon::now()->year;
    $years = range($currentYear - 2, $currentYear + 3);
@endphp

<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    {{-- Filter & Aktionen --}}
    <form method="GET" action="{{ route('holidays.index') }}"
          class="flex-none rounded-box border border-base-300 bg-base-100 p-4 shadow-xs md:p-5">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col min-w-32">
                <label for="filter-year" class="label py-1">
                    <span class="label-text text-xs uppercase tracking-wider text-base-content/60">{{ __('Jahr') }}</span>
                </label>
                <select id="filter-year" name="year" class="select select-bordered select-sm" onchange="this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ml-auto flex items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Filtern') }}</button>
                @if ((int) $year !== $currentYear)
                    <a href="{{ route('holidays.index') }}" class="btn btn-sm btn-ghost">{{ __('Zurücksetzen') }}</a>
                @endif
                <a href="{{ route('holidays.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">
                    + {{ __('Eigener Feiertag') }}
                </a>
            </div>
        </div>
    </form>

    {{-- Tabs: Jahresübersicht | Eigene Feiertage --}}
    <div role="tablist" class="tabs tabs-box w-fit">
        <a role="tab" href="#yearly" class="tab tab-active" data-holiday-tab="yearly">
            {{ __('Jahresübersicht') }} <span class="badge badge-sm ml-2">{{ $merged->count() }}</span>
        </a>
        <a role="tab" href="#custom" class="tab" data-holiday-tab="custom">
            {{ __('Eigene Feiertage') }} <span class="badge badge-sm ml-2">{{ $customHolidays->count() }}</span>
        </a>
    </div>

    {{-- Jahresübersicht --}}
    <div data-holiday-pane="yearly" class="flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
        <table class="table table-sm table-zebra table-pin-rows" data-sortable>
            <thead>
                <tr>
                    <th class="whitespace-nowrap" data-sort data-sort-type="date" data-sort-default="asc">{{ __('Datum') }}</th>
                    <th class="whitespace-nowrap" data-sort>{{ __('Wochentag') }}</th>
                    <th data-sort>{{ __('Name') }}</th>
                    <th data-sort>{{ __('Quelle') }}</th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($merged as $row)
                    @php
                        $isCustom = $row['custom'] !== null;
                        $isPast = $row['date']->isPast() && ! $row['date']->isToday();
                        $isSunday = $row['date']->isSunday();
                    @endphp
                    <tr class="hover {{ $isPast ? 'opacity-50' : '' }} {{ $row['date']->isToday() ? 'bg-warning/10' : '' }} {{ $isSunday ? 'text-error' : '' }}">
                        <td class="whitespace-nowrap font-mono" data-sort-value="{{ $row['date']->format('Y-m-d') }}">{{ $row['date']->format('d.m.Y') }}</td>
                        <td class="whitespace-nowrap text-xs text-base-content/70">{{ $row['date']->locale(app()->getLocale())->isoFormat('dd') }}</td>
                        <td class="font-semibold">{{ $row['name'] }}</td>
                        <td>
                            @if ($isCustom)
                                @if (($row['custom']->recurrence_type ?? 'fixed') === 'relative')
                                    <span class="badge badge-sm badge-warning">{{ $row['custom']->recurrenceLabel() }}</span>
                                @elseif ($row['custom']->is_recurring)
                                    <span class="badge badge-sm badge-info">{{ __('Eigen · jährlich') }}</span>
                                @else
                                    <span class="badge badge-sm badge-info">{{ __('Eigen') }}</span>
                                @endif
                            @else
                                <span class="badge badge-sm badge-ghost">{{ __('Standard') }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            @if ($isCustom)
                                <a href="{{ route('holidays.edit', $row['custom']) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost" title="{{ __('Bearbeiten') }}" aria-label="{{ __('Bearbeiten') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('holidays.destroy', $row['custom']) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('Feiertag löschen') }}"
                                      data-confirm-message="{{ __('Diesen Feiertag wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-ghost text-error" title="{{ __('Löschen') }}" aria-label="{{ __('Löschen') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-base-content/40">{{ __('—') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-base-content/70">{{ __('Keine Feiertage in diesem Jahr.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Eigene Feiertage (Verwaltung) --}}
    <div data-holiday-pane="custom" class="hidden flex-1 min-h-0 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-xs">
        <table class="table table-sm table-zebra table-pin-rows" data-sortable>
            <thead>
                <tr>
                    <th class="whitespace-nowrap" data-sort data-sort-type="date" data-sort-default="asc">{{ __('Datum') }}</th>
                    <th data-sort>{{ __('Name') }}</th>
                    <th data-sort>{{ __('Typ') }}</th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customHolidays as $holiday)
                    @php $isSunday = $holiday->date && $holiday->date->isSunday(); @endphp
                    <tr class="hover {{ $isSunday ? 'text-error' : '' }}">
                        <td class="whitespace-nowrap font-mono" data-sort-value="{{ optional($holiday->date)->format('Y-m-d') }}">{{ optional($holiday->date)->format('d.m.Y') }}</td>
                        <td>{{ $holiday->name }}</td>
                        <td>
                            @if (($holiday->recurrence_type ?? 'fixed') === 'relative')
                                <span class="badge badge-sm badge-warning" title="{{ $holiday->recurrenceLabel() }}">{{ $holiday->recurrenceLabel() }}</span>
                            @elseif ($holiday->is_recurring)
                                <span class="badge badge-sm badge-info">{{ __('Jährl.') }} · {{ optional($holiday->date)->format('d.m.') }}</span>
                            @else
                                <span class="badge badge-sm">{{ __('Einmalig') }}</span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('holidays.edit', $holiday) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost" title="{{ __('Bearbeiten') }}" aria-label="{{ __('Bearbeiten') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('Feiertag löschen') }}"
                                  data-confirm-message="{{ __('Diesen Feiertag wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-ghost text-error" title="{{ __('Löschen') }}" aria-label="{{ __('Löschen') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-6 text-center text-base-content/70">{{ __('Keine eigenen Feiertage vorhanden.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    (function () {
        var tabs = document.querySelectorAll('[data-holiday-tab]');
        var panes = document.querySelectorAll('[data-holiday-pane]');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                var key = tab.getAttribute('data-holiday-tab');
                tabs.forEach(function (t) { t.classList.toggle('tab-active', t === tab); });
                panes.forEach(function (p) {
                    p.classList.toggle('hidden', p.getAttribute('data-holiday-pane') !== key);
                });
            });
        });
    })();
</script>
@endsection
