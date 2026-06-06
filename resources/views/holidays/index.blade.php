@extends('layouts.app')
@section('title', __('Feiertage') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Feiertage'))

@section('content')

<x-index-page :subtitle="__('Gesetzliche und eigene Feiertage des Mandanten verwalten.')">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('holidays.create')"
                    show-label>{{ __('Eigener Feiertag') }}</x-icon-btn>
    </x-slot:actions>

    {{-- Tabs: Jahresübersicht | Eigene Feiertage --}}
    <div role="tablist" class="tabs tabs-box">
        <a role="tab" href="#yearly" class="tab tab-active" data-holiday-tab="yearly">
            {{ __('Jahresübersicht') }} <span class="badge badge-sm ml-2">{{ $merged->count() }}</span>
        </a>
        <a role="tab" href="#custom" class="tab" data-holiday-tab="custom">
            {{ __('Eigene Feiertage') }} <span class="badge badge-sm ml-2">{{ $customHolidays->count() }}</span>
        </a>
    </div>

    {{-- Jahresübersicht --}}
    <div data-holiday-pane="yearly" class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <x-table table-sort="client" bare scroll="none" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date" default="asc" class="whitespace-nowrap">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="string" class="whitespace-nowrap">{{ __('Wochentag') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Quelle') }}</x-table.th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($merged as $row)
                @php
                    $isCustom = $row['custom'] !== null;
                    $isPast = $row['date']->isPast() && ! $row['date']->isToday();
                    $isSunday = $row['date']->isSunday();
                @endphp
                <tr class="hover {{ $isPast ? 'opacity-50' : '' }} {{ $row['date']->isToday() ? 'bg-warning/10' : '' }} {{ $isSunday ? 'text-error' : '' }}">
                    <td class="whitespace-nowrap font-mono" data-sort-value="{{ $row['date']->format('Y-m-d') }}">{{ $row['date']->fdate() }}</td>
                    <td class="whitespace-nowrap text-xs text-base-content/70">{{ $row['date']->locale(app()->getLocale())->isoFormat('dd') }}</td>
                    <td class="font-semibold">{{ $row['name'] }}</td>
                    <td>
                        @if ($isCustom)
                            @if (($row['custom']->recurrence_type ?? 'fixed') === 'relative')
                                <x-status-badge tone="warning">{{ $row['custom']->recurrenceLabel() }}</x-status-badge>
                            @elseif ($row['custom']->is_recurring)
                                <x-status-badge tone="info">{{ __('Eigen · jährlich') }}</x-status-badge>
                            @else
                                <x-status-badge tone="info">{{ __('Eigen') }}</x-status-badge>
                            @endif
                        @else
                            <x-status-badge tone="ghost">{{ __('Standard') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-right">
                        @if ($isCustom)
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('holidays.edit', $row['custom'])"
                                        :label="__('Bearbeiten')" />
                            <form method="POST" action="{{ route('holidays.destroy', $row['custom']) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-title="{{ __('Feiertag löschen') }}"
                                  data-confirm-message="{{ __('Diesen Feiertag wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </form>
                        @else
                            <span class="text-xs text-base-content/40">{{ __('—') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">event</span>' :colspan="5" :title="__('Keine Feiertage in diesem Jahr')" compact />
            @endforelse
        </x-table>
    </div>

    {{-- Eigene Feiertage (Verwaltung) --}}
    <div data-holiday-pane="custom" class="hidden rounded-box border border-base-300 bg-base-100 shadow-xs">
        <x-table table-sort="client" bare scroll="none" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date" default="asc" class="whitespace-nowrap">{{ __('Datum') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                    <th class="text-right">{{ __('Aktion') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($customHolidays as $holiday)
                @php $isSunday = $holiday->date && $holiday->date->isSunday(); @endphp
                <tr class="hover {{ $isSunday ? 'text-error' : '' }}">
                    <td class="whitespace-nowrap font-mono" data-sort-value="{{ optional($holiday->date)->format('Y-m-d') }}">{{ optional($holiday->date)->fdate() }}</td>
                    <td>{{ $holiday->name }}</td>
                    <td>
                        @if (($holiday->recurrence_type ?? 'fixed') === 'relative')
                            <x-status-badge tone="warning" title="{{ $holiday->recurrenceLabel() }}">{{ $holiday->recurrenceLabel() }}</x-status-badge>
                        @elseif ($holiday->is_recurring)
                            <x-status-badge tone="info">{{ __('Jährl.') }} · {{ optional($holiday->date)->format('d.m.') }}</x-status-badge>
                        @else
                            <x-status-badge tone="ghost">{{ __('Einmalig') }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('holidays.edit', $holiday)"
                                    :label="__('Bearbeiten')" />
                        <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" class="inline"
                              data-confirm-dialog
                              data-confirm-title="{{ __('Feiertag löschen') }}"
                              data-confirm-message="{{ __('Diesen Feiertag wirklich löschen?') }}"
                              data-confirm-label="{{ __('Löschen') }}">
                            @csrf
                            @method('DELETE')
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </form>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">event</span>' :colspan="4" :title="__('Keine eigenen Feiertage vorhanden')" compact />
            @endforelse
        </x-table>
    </div>
</x-index-page>

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
