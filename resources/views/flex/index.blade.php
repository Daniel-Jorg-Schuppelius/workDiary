@extends('layouts.app')
@section('title', __('Arbeitszeitkonto'))
@php
    /** @var int $month */
    /** @var int $year */
    /** @var bool $isAdmin */
    /** @var \App\Models\User $user */
    /** @var \App\Models\User|null $authUser */
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \App\Services\Calendar\WeekViewService $service */
    /** @var \App\Models\WorkSchedule $schedule */
    /** @var \App\Enums\WorkSchedule\ScheduleType $scheduleType */
    /** @var bool $tracksTarget */
    /** @var bool $modelChanged */
    // canSeeOthers ist die neue, semantisch saubere Variable (Admin + Buchhaltung).
    // Fallback auf $isAdmin für Aufrufer, die die neue Variable noch nicht setzen.
    $canSeeOthers = $canSeeOthers ?? $isAdmin;
    $monthName = \DateTime::createFromFormat('!m', (string)$month)->format('F');
@endphp
@section('nav-title', __('Arbeitszeitkonto') . ' – ' . $monthName . ' ' . $year)
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
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
    $fmtPlain = fn (int $min): string => intdiv(abs($min), 60) . ':' . str_pad((string)(abs($min) % 60), 2, '0', STR_PAD_LEFT) . ' h';

    // Anzahl Tage mit erfasster Ist-Zeit (für Vertrauensarbeitszeit-KPI).
    $presenceDays = collect($summary['days'])->filter(fn ($b) => (int) ($b['actual'] ?? 0) > 0)->count();

    // Modell-Kontextzeile je Typ.
    $modelContext = match ($scheduleType->value) {
        'flextime' => $schedule->core_start
            ? __('Kernzeit') . ' ' . substr((string) $schedule->core_start, 0, 5) . '–' . substr((string) $schedule->core_end, 0, 5)
                . ($schedule->frame_start ? ' · ' . __('Rahmen') . ' ' . substr((string) $schedule->frame_start, 0, 5) . '–' . substr((string) $schedule->frame_end, 0, 5) : '')
            : null,
        'weekly' => __('Wochensoll') . ' ' . $fmtPlain((int) $schedule->weekly_minutes),
        'per_weekday' => __('Soll variiert je Wochentag') . ' · ' . __('Wochensoll') . ' ' . $fmtPlain((int) $schedule->weekly_minutes),
        default => null,
    };

    $subtitle = $tracksTarget
        ? __('Kontostand: Soll, Ist und Saldo des gewählten Monats.')
        : __('Erfasste Anwesenheit des gewählten Monats.');
@endphp
<x-index-page overflow="clip" :subtitle="$subtitle">
    {{-- Kopfzeile: Mitarbeiterauswahl + Modell-Badge + Kontext --}}
    <x-card class="flex flex-wrap items-center gap-3">
        @if($canSeeOthers && $users->isNotEmpty())
            @php $selfId = (int) ($authUser->id ?? 0); @endphp
            <label for="flex-user-select" class="text-sm font-medium text-base-content/70">{{ __('Mitarbeiter') }}</label>
            <select id="flex-user-select"
                    class="select select-sm select-bordered w-full sm:max-w-xs"
                    onchange="if (this.value) window.location.href = this.value;">
                @foreach ($users as $u)
                    @php
                        $isSelf = (int) $u->id === $selfId;
                        $href = route('flex.index', $isSelf ? [] : ['user' => $u->sqid]);
                        $isActive = (int) $user->id === (int) $u->id;
                    @endphp
                    <option value="{{ $href }}" {{ $isActive ? 'selected' : '' }}>
                        {{ $u->name }}@if($isSelf) ({{ __('Ich') }})@endif
                    </option>
                @endforeach
            </select>
        @endif

        <span class="badge badge-soft badge-{{ $scheduleType->badgeTone() }} gap-1">
            <x-icon :name="$scheduleType->icon()" class="text-sm" />
            {{ $scheduleType->label() }}
        </span>

        @if ($modelContext)
            <span class="text-xs text-base-content/60">{{ $modelContext }}</span>
        @endif

        @if ($modelChanged)
            <span class="badge badge-xs badge-ghost gap-1" title="{{ __('Im gewählten Monat wurde das Arbeitszeit-Modell gewechselt.') }}">
                <x-icon name="sync_alt" class="text-xs" /> {{ __('Modellwechsel im Zeitraum') }}
            </span>
        @endif
    </x-card>

    {{-- Monats-Tabs (nur bei >1 Monat im Zeitraum) --}}
    @if (count($months) > 1)
        <div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
            @foreach ($months as $m)
                @php
                    $params = ['activeMonth' => $m['key']];
                    if ($canSeeOthers && (int) $user->id !== (int) ($authUser->id ?? 0)) {
                        $params['user'] = $user->sqid;
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

    @if ($tracksTarget)
        {{-- Soll/Ist/Saldo-Modelle --}}
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('Soll')" :value="$fmt($summary['target'])" />
            <x-kpi-tile :label="__('Ist')" :value="$fmt($summary['actual'])" />
            <x-kpi-tile :label="__('Saldo')" term="flexzeit" :value="$fmt($summary['balance'])" :tone="$summary['balance'] < 0 ? 'error' : 'success'" />
        </div>
    @else
        {{-- Vertrauensarbeitszeit: kein Soll/Saldo --}}
        <div class="alert alert-warning alert-soft text-sm">
            <x-icon name="handshake" />
            <span>{{ __('Vertrauensarbeitszeit – keine Sollzeiterfassung. Angezeigt wird die erfasste Anwesenheit.') }}</span>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-kpi-tile :label="__('Ist')" :value="$fmt($summary['actual'])" />
            <x-kpi-tile :label="__('Anwesenheitstage')" :value="(string) $presenceDays" />
        </div>
    @endif

    <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
        <x-table table-sort="client" bare scroll="flex" :pinRows="true" size="xs">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="date">{{ __('Tag') }}</x-table.th>
                    @if ($tracksTarget)
                        <x-table.th sort type="duration" align="right">{{ __('Soll') }}</x-table.th>
                    @endif
                    <x-table.th sort type="duration" align="right">{{ __('Ist') }}</x-table.th>
                    @if ($tracksTarget)
                        <x-table.th sort type="duration" align="right">{{ __('Saldo') }}</x-table.th>
                    @endif
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
                    @if ($tracksTarget)
                        <td class="text-right tabular-nums @if ($b['target'] === 0) opacity-50 @endif" data-sort-value="{{ (int) $b['target'] }}">{{ $fmtCell($b['target']) }}</td>
                    @endif
                    <td class="text-right tabular-nums @if ($b['actual'] === 0) opacity-50 @endif" data-sort-value="{{ (int) $b['actual'] }}">{{ $fmtCell($b['actual']) }}</td>
                    @if ($tracksTarget)
                        <td class="text-right tabular-nums @if ($b['balance'] === 0) opacity-50 @endif {{ $b['balance'] < 0 ? 'text-error' : '' }}" data-sort-value="{{ (int) $b['balance'] }}">{{ $fmtCell($b['balance']) }}</td>
                    @endif
                </tr>
            @endforeach
        </x-table>
    </x-card>
</x-index-page>
@endsection
