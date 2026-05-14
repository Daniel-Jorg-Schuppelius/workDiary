@extends('layouts.app')
@section('title', __('Gleitzeit'))
@section('content')
@php
    $fmt = function (int $min): string {
        $sign = $min < 0 ? '-' : '+';
        $abs  = abs($min);
        return $sign . intdiv($abs, 60) . ':' . str_pad((string)($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
    };
@endphp
<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="font-['Space_Grotesk'] text-xl font-semibold">{{ __('Gleitzeit-Konto') }} – {{ \DateTime::createFromFormat('!m', (string)$month)->format('F') }} {{ $year }}</h1>
        <form method="GET" class="flex gap-2">
            <input type="number" name="year"  value="{{ $year }}"  min="2000" max="2100" class="input input-sm input-bordered w-24">
            <input type="number" name="month" value="{{ $month }}" min="1"    max="12"   class="input input-sm input-bordered w-20">
            <button class="btn btn-sm">{{ __('Anzeigen') }}</button>
            @if($isAdmin)
                <a href="{{ route('flex.admin') }}" class="btn btn-sm btn-ghost">{{ __('Team-Sicht') }}</a>
            @endif
        </form>
    </div>

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
            <table class="table table-xs">
                <thead><tr><th>{{ __('Tag') }}</th><th class="text-right">{{ __('Soll') }}</th><th class="text-right">{{ __('Ist') }}</th><th class="text-right">{{ __('Saldo') }}</th></tr></thead>
                <tbody>
                    @foreach($summary['days'] as $date => $b)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($date)->format('D, d.m.') }}</td>
                            <td class="text-right">{{ $fmt($b['target']) }}</td>
                            <td class="text-right">{{ $fmt($b['actual']) }}</td>
                            <td class="text-right {{ $b['balance'] < 0 ? 'text-error' : '' }}">{{ $fmt($b['balance']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
