@extends('layouts.app')
@section('title', __('Feiertage') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Feiertage'))

@section('content')
@php
    $currentYear = (int) \Carbon\Carbon::now()->year;
    $years = range($currentYear - 2, $currentYear + 3);
@endphp

<div class="flex h-[calc(100dvh-11rem)] flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">
            {{ __('Feiertagsverwaltung') }} <span class="text-base-content/50">{{ $year }}</span>
        </h1>
        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('holidays.index') }}" class="flex items-center gap-2">
                <label class="text-sm text-base-content/70">{{ __('Jahr') }}</label>
                <select name="year" class="select select-bordered select-sm" onchange="this.form.submit()">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('holidays.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">+ {{ __('Eigener Feiertag') }}</a>
        </div>
    </div>

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
        <table class="table table-sm table-pin-rows">
            <thead>
                <tr>
                    <th class="whitespace-nowrap">{{ __('Datum') }}</th>
                    <th class="whitespace-nowrap">{{ __('Wochentag') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Quelle') }}</th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($merged as $row)
                    @php
                        $isCustom = $row['custom'] !== null;
                        $isPast = $row['date']->isPast() && ! $row['date']->isToday();
                    @endphp
                    <tr class="{{ $isPast ? 'opacity-50' : '' }} {{ $row['date']->isToday() ? 'bg-warning/10' : '' }}">
                        <td class="whitespace-nowrap font-mono">{{ $row['date']->format('d.m.Y') }}</td>
                        <td class="whitespace-nowrap text-xs text-base-content/70">{{ $row['date']->locale(app()->getLocale())->isoFormat('dd') }}</td>
                        <td class="font-semibold">{{ $row['name'] }}</td>
                        <td>
                            @if ($isCustom)
                                @if ($row['custom']->is_recurring)
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
                                <a href="{{ route('holidays.edit', $row['custom']) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                                <form method="POST" action="{{ route('holidays.destroy', $row['custom']) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-title="{{ __('Feiertag löschen') }}"
                                      data-confirm-message="{{ __('Diesen Feiertag wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
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
        <table class="table table-sm table-pin-rows">
            <thead>
                <tr>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Typ') }}</th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customHolidays as $holiday)
                    <tr>
                        <td class="whitespace-nowrap font-mono">{{ optional($holiday->date)->format('d.m.Y') }}</td>
                        <td>{{ $holiday->name }}</td>
                        <td>
                            @if ($holiday->is_recurring)
                                <span class="badge badge-sm badge-info">{{ __('Jährlich') }}</span>
                            @else
                                <span class="badge badge-sm">{{ __('Einmalig') }}</span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('holidays.edit', $holiday) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                            <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('Feiertag löschen') }}"
                                  data-confirm-message="{{ __('Diesen Feiertag wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
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
