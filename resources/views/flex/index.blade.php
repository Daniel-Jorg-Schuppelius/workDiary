@extends('layouts.app')
@section('title', __('Gleitzeit'))
@php
    /** @var int $month */
    /** @var int $year */
    /** @var bool $isAdmin */
    /** @var \App\Models\User $user */
    /** @var \App\Models\User|null $authUser */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \App\Services\Calendar\WeekViewService $service */
    // canSeeOthers ist die neue, semantisch saubere Variable (Admin + Buchhaltung).
    // Fallback auf $isAdmin für Aufrufer (z. B. flex/admin.blade.php), die die
    // neue Variable noch nicht setzen.
    $canSeeOthers = $canSeeOthers ?? $isAdmin;
    $monthName = \DateTime::createFromFormat('!m', (string)$month)->format('F');
@endphp
@section('nav-title', __('Gleitzeit-Konto') . ' – ' . $monthName . ' ' . $year)
@section('content')
@php
    $fmt = function (int $min): string {
        $sign = $min < 0 ? '-' : '+';
        $abs  = abs($min);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string)($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
    $fmtCell = function (int $min): string {
        if ($min === 0) {
            return '–';
        }
        $sign = $min < 0 ? '-' : '+';
        $abs  = abs($min);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string)($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Gleitzeitkonto: Buchungen und Saldo des gewählten Monats.')" />
    </x-slot:toolbar>
    @if($canSeeOthers && $users->isNotEmpty())
        @php
            $selfId = (int) ($authUser->id ?? 0);
        @endphp
        <div class="flex flex-wrap items-center gap-3 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <label for="flex-user-select" class="text-sm font-medium text-base-content/70">
                {{ __('Mitarbeiter') }}
            </label>
            <select id="flex-user-select"
                    class="select select-sm select-bordered w-full sm:max-w-xs"
                    onchange="if (this.value) window.location.href = this.value;">
                @foreach ($users as $u)
                    @php
                        $isSelf = (int) $u->id === $selfId;
                        $href = route('flex.index', $isSelf ? [] : ['user' => $u->id]);
                        $isActive = (int) $user->id === (int) $u->id;
                    @endphp
                    <option value="{{ $href }}" {{ $isActive ? 'selected' : '' }}>
                        {{ $u->name }}@if($isSelf) ({{ __('Ich') }})@endif
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Monats-Tabs (nur bei >1 Monat im Zeitraum) --}}
    @if (count($months) > 1)
        <div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
            @foreach ($months as $m)
                @php
                    $params = ['activeMonth' => $m['key']];
                    if ($canSeeOthers && (int) $user->id !== (int) ($authUser->id ?? 0)) {
                        $params['user'] = $user->id;
                    }
                @endphp
                <a role="tab"
                   href="{{ route('flex.index', $params) }}"
                   class="tab whitespace-nowrap gap-1.5 {{ $m['key'] === $activeKey ? 'tab-active' : '' }}">
                    <span class="font-semibold">{{ $m['shortLabel'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="text-2xl font-bold">{{ $fmt($summary['target']) }}</div>
            <div class="text-xs text-base-content/60">{{ __('Soll') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="text-2xl font-bold">{{ $fmt($summary['actual']) }}</div>
            <div class="text-xs text-base-content/60">{{ __('Ist') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 text-center shadow-xs">
            <div class="text-2xl font-bold {{ $summary['balance'] < 0 ? 'text-error' : 'text-success' }}">{{ $fmt($summary['balance']) }}</div>
            <div class="text-xs text-base-content/60">{{ __('Saldo') }}</div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
        <x-table table-sort="client" bare size="xs">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date">{{ __('Tag') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Soll') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Ist') }}</x-table.th>
                    <x-table.th sort type="duration" align="right">{{ __('Saldo') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach($summary['days'] as $date => $b)
                @php
                    $isEmpty = $b['target'] === 0 && $b['actual'] === 0;
                    $carbonDate = \Carbon\Carbon::parse($date);
                    $isSunday = $carbonDate->isSunday();
                    $isHoliday = (bool) ($b['is_holiday'] ?? false);
                    $isVacation = (bool) ($b['is_vacation'] ?? false);
                    $holidayName = $b['holiday_name'] ?? null;
                @endphp
                <tr class="{{ $isEmpty && ! $isHoliday && ! $isVacation ? 'opacity-40' : '' }} {{ $isSunday || $isHoliday ? 'text-error' : '' }}">
                    <td data-sort-value="{{ $carbonDate->format('Y-m-d') }}">
                        <span>{{ $carbonDate->translatedFormat('D, d.m.') }}</span>
                        @if ($isHoliday)
                            <span class="badge badge-xs badge-error badge-soft ml-1" title="{{ $holidayName }}">{{ __('Feiertag') }}@if ($holidayName): {{ $holidayName }}@endif</span>
                        @elseif ($isVacation)
                            <span class="badge badge-xs badge-info badge-soft ml-1">{{ __('Urlaub') }}</span>
                        @endif
                    </td>
                    <td class="text-right tabular-nums @if ($b['target'] === 0) opacity-50 @endif" data-sort-value="{{ (int) $b['target'] }}">{{ $fmtCell($b['target']) }}</td>
                    <td class="text-right tabular-nums @if ($b['actual'] === 0) opacity-50 @endif" data-sort-value="{{ (int) $b['actual'] }}">{{ $fmtCell($b['actual']) }}</td>
                    <td class="text-right tabular-nums @if ($b['balance'] === 0) opacity-50 @endif {{ $b['balance'] < 0 ? 'text-error' : '' }}" data-sort-value="{{ (int) $b['balance'] }}">{{ $fmtCell($b['balance']) }}</td>
                </tr>
            @endforeach
        </x-table>
    </div>
</x-page-shell>
@endsection
