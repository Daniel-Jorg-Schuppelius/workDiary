@extends('layouts.app')

@section('title', __('Monat :period', ['period' => $closure->periodLabel()]))
@section('nav-title', __('Monat :period', ['period' => $closure->periodLabel()]))

@php
    use App\Enums\TimeApproval\MonthClosureStatus;
    $totals = $closure->totals ?: $preview;
    $minutes = $totals['minutes'] ?? null;
    $days = $totals['days'] ?? null;
@endphp

@section('content')
    <x-index-page :subtitle="__('Status: :status', ['status' => $closure->status->label()])"
                  :badge="$closure->status->label()" :badgeTone="$closure->status->tone()">
        <x-slot:actions>
            <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                        :href="route('month-approval.index')"
                        show-label>{{ __('Übersicht') }}</x-icon-btn>

            @can('submit', $closure)
                <form method="POST" action="{{ route('month-approval.submit', ['year' => $closure->period_year, 'month' => $closure->period_month]) }}">
                    @csrf
                    <x-icon-btn icon="upload" tone="primary" size="sm" type="submit"
                                show-label>{{ __('Monat einreichen') }}</x-icon-btn>
                </form>
            @endcan

            @if ($closure->status === MonthClosureStatus::Rejected)
                @can('reopen', $closure)
                    <form method="POST" action="{{ route('month-approval.reopen', ['year' => $closure->period_year, 'month' => $closure->period_month]) }}">
                        @csrf
                        <x-icon-btn icon="lock_open" tone="warning" size="sm" type="submit"
                                    show-label>{{ __('Neu bearbeiten') }}</x-icon-btn>
                    </form>
                @endcan
            @endif
        </x-slot:actions>

        @if (session('error'))
            <div role="alert" class="alert alert-warning"><span>{{ session('error') }}</span></div>
        @endif
        @if (session('status'))
            <div role="alert" class="alert alert-success"><span>{{ session('status') }}</span></div>
        @endif

        <div class="card bg-base-100 border border-base-300">
            <div class="card-body gap-3">
                <h2 class="card-title text-base">{{ __('Kennzahlen') }}</h2>
                @if ($minutes)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div>
                            <div class="text-xs opacity-70">{{ __('Soll') }}</div>
                            <div class="font-medium tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes['target'] / 60, 2, withThousandsSeparator: true) }} h</div>
                        </div>
                        <div>
                            <div class="text-xs opacity-70">{{ __('Ist') }}</div>
                            <div class="font-medium tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes['actual'] / 60, 2, withThousandsSeparator: true) }} h</div>
                        </div>
                        <div>
                            <div class="text-xs opacity-70">{{ __('Saldo') }}</div>
                            <div class="font-medium tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes['balance'] / 60, 2, withThousandsSeparator: true) }} h</div>
                        </div>
                        <div>
                            <div class="text-xs opacity-70">{{ __('Anwesenheit') }}</div>
                            <div class="font-medium tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes['attendance'] / 60, 2, withThousandsSeparator: true) }} h</div>
                        </div>
                    </div>
                @else
                    <p class="text-sm opacity-70">{{ __('Noch kein Snapshot vorhanden.') }}</p>
                @endif

                @if ($days)
                    <div class="divider my-1"></div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div><span class="opacity-70">{{ __('Tage gesamt') }}:</span> {{ $closure->days_total }}</div>
                        <div><span class="opacity-70">{{ __('mit Anwesenheit') }}:</span> {{ $days['with_attendance'] }}</div>
                        <div><span class="opacity-70">{{ __('geschlossen') }}:</span> {{ $days['closed'] }}</div>
                        <div><span class="opacity-70">{{ __('offen') }}:</span>
                            <span class="font-medium {{ ($days['open'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $days['open'] }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($closure->decision_note)
            <div class="card bg-base-100 border border-base-300">
                <div class="card-body gap-2">
                    <h2 class="card-title text-base">{{ __('Notiz') }}</h2>
                    <p class="text-sm whitespace-pre-line">{{ $closure->decision_note }}</p>
                </div>
            </div>
        @endif

        <div class="card bg-base-100 border border-base-300">
            <div class="card-body gap-2">
                <h2 class="card-title text-base">{{ __('Verlauf') }}</h2>
                @if ($closure->events->isEmpty())
                    <p class="text-sm opacity-70">{{ __('Noch keine Ereignisse.') }}</p>
                @else
                    <ul class="timeline timeline-vertical timeline-compact">
                        @foreach ($closure->events as $event)
                            <li>
                                <div class="timeline-start text-xs tabular-nums">{{ $event->created_at?->fdatetime() }}</div>
                                <div class="timeline-middle">
                                    <span class="material-symbols-outlined text-base">history</span>
                                </div>
                                <div class="timeline-end timeline-box">
                                    <div class="font-medium text-sm">{{ $event->event }}</div>
                                    @if ($event->actor)
                                        <div class="text-xs opacity-70">{{ $event->actor->name }}</div>
                                    @endif
                                    @if ($event->note)
                                        <div class="text-xs mt-1">{{ $event->note }}</div>
                                    @endif
                                </div>
                                @if (! $loop->last)
                                    <hr />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </x-index-page>
@endsection
