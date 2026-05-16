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
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">
    @if($isAdmin && $users->isNotEmpty())
        <div role="tablist" class="tabs tabs-box">
            @foreach ($users as $u)
                @php
                    $hue = $service->userHue((int) $u->id);
                    $isActive = (int) $user->id === (int) $u->id;
                    $color = "hsl({$hue} 70% 45%)";
                    $soft = "hsl({$hue} 70% 92%)";
                    $isSelf = (int) $u->id === (int) ($authUser->id ?? 0);
                @endphp
                <a role="tab"
                   href="{{ route('flex.index', $isSelf ? [] : ['user' => $u->id]) }}"
                   class="tab gap-2 {{ $isActive ? 'tab-active' : '' }}"
                   style="--tab-bg: {{ $soft }}; --tab-border-color: {{ $color }}; {{ $isActive ? 'color: ' . $color . ';' : '' }}">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $color }};"></span>
                    <span>{{ $u->name }}</span>
                    @if($isSelf)
                        <span class="text-xs text-base-content/50">({{ __('Ich') }})</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    {{-- Monats-Tabs (nur bei >1 Monat im Zeitraum) --}}
    @if (count($months) > 1)
        <div role="tablist" class="tabs tabs-box flex-nowrap overflow-x-auto">
            @foreach ($months as $m)
                @php
                    $params = ['activeMonth' => $m['key']];
                    if ($isAdmin && (int) $user->id !== (int) ($authUser->id ?? 0)) {
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
        <div class="overflow-x-auto">
            <table class="table table-xs table-zebra">
                <thead><tr><th>{{ __('Tag') }}</th><th class="text-right">{{ __('Soll') }}</th><th class="text-right">{{ __('Ist') }}</th><th class="text-right">{{ __('Saldo') }}</th></tr></thead>
                <tbody>
                    @foreach($summary['days'] as $date => $b)
                        @php
                            $isEmpty = $b['target'] === 0 && $b['actual'] === 0;
                            $isSunday = \Carbon\Carbon::parse($date)->isSunday();
                        @endphp
                        <tr class="{{ $isEmpty ? 'opacity-40' : '' }} {{ $isSunday ? 'text-error' : '' }}">
                            <td>{{ \Carbon\Carbon::parse($date)->translatedFormat('D, d.m.') }}</td>
                            <td class="text-right tabular-nums @if ($b['target'] === 0) opacity-50 @endif">{{ $fmtCell($b['target']) }}</td>
                            <td class="text-right tabular-nums @if ($b['actual'] === 0) opacity-50 @endif">{{ $fmtCell($b['actual']) }}</td>
                            <td class="text-right tabular-nums @if ($b['balance'] === 0) opacity-50 @endif {{ $b['balance'] < 0 ? 'text-error' : '' }}">{{ $fmtCell($b['balance']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
