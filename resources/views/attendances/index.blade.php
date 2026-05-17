@extends('layouts.app')
@section('title', __('Stempelungen') . ' — WorkDiary')
@section('nav-title', __('Stempelungen'))

@section('content')
    @php
        /** @var \Illuminate\Pagination\LengthAwarePaginator $attendances */
        /** @var \App\Models\Attendance|null $current */
        /** @var \Carbon\CarbonInterface $from */
        /** @var \Carbon\CarbonInterface $to */
    @endphp

    <div class="mx-auto w-full max-w-screen-xl space-y-6 px-4 py-4 xl:px-8">

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-['Space_Grotesk'] text-2xl font-bold">{{ __('Stempelungen') }}</h1>
                <p class="text-sm text-base-content/60">
                    {{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}
                </p>
            </div>
            <a href="{{ route('today.show') }}" class="btn btn-ghost btn-sm">
                <x-icon name="today" /> {{ __('Heute-Übersicht') }}
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="lg:col-span-1">
                @include('attendances._panel', ['current' => $current])

                <form method="GET" action="{{ route('attendance.index') }}" class="mt-4 rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                    <h2 class="font-['Space_Grotesk'] text-sm font-semibold">{{ __('Filter') }}</h2>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('Von') }}</span>
                            <input type="date" name="from" value="{{ request('from', $from->toDateString()) }}" class="input input-bordered input-sm">
                        </label>
                        <label class="form-control">
                            <span class="label-text text-xs">{{ __('Bis') }}</span>
                            <input type="date" name="to" value="{{ request('to', $to->toDateString()) }}" class="input input-bordered input-sm">
                        </label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary mt-3 w-full">{{ __('Anwenden') }}</button>
                </form>
            </div>

            <div class="lg:col-span-2">
                <div class="rounded-box border border-base-300 bg-base-100 shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Datum') }}</th>
                                    <th>{{ __('Beginn') }}</th>
                                    <th>{{ __('Ende') }}</th>
                                    <th class="text-right">{{ __('Pause') }}</th>
                                    <th class="text-right">{{ __('Dauer') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Quelle') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($attendances as $a)
                                    <tr>
                                        <td>{{ optional($a->date)->format('d.m.Y') }}</td>
                                        <td class="tabular-nums">{{ optional($a->started_at)->format('H:i') }}</td>
                                        <td class="tabular-nums">{{ optional($a->ended_at)->format('H:i') ?? '—' }}</td>
                                        <td class="text-right tabular-nums">{{ $a->break_minutes_total }}</td>
                                        <td class="text-right tabular-nums">
                                            @if ($a->duration_minutes !== null)
                                                {{ sprintf('%d:%02d', intdiv($a->duration_minutes, 60), $a->duration_minutes % 60) }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-sm {{ $a->isOpen() ? 'badge-success' : 'badge-ghost' }}">{{ $a->status }}</span>
                                        </td>
                                        <td class="text-xs text-base-content/60">{{ $a->source }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="py-6 text-center text-sm text-base-content/60">{{ __('Keine Stempelungen im Zeitraum.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3">{{ $attendances->links() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
