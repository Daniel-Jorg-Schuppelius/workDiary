@extends('layouts.app')

@section('title', __('Fahrtenbuch'))

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ __('Fahrtenbuch') }}</h1>
                <p class="text-sm text-base-content/60">
                    {{ __('Erfasste Fahrten') }} — {{ $from->format('d.m.Y') }} – {{ $to->format('d.m.Y') }}
                </p>
                <p class="text-xs text-base-content/50">
                    {{ __('Zeitraum übernommen aus dem Header. Mit der Auswahl oben links wechseln.') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('travel-logs.create') }}" class="btn btn-sm btn-primary">
                    <x-icon name="add" /> {{ __('Neue Fahrt') }}
                </a>
                <a href="{{ route('travel-logs.export', array_merge(request()->query(), ['from' => $from->toDateString(), 'to' => $to->toDateString()])) }}"
                   class="btn btn-sm btn-ghost">
                    <x-icon name="download" /> {{ __('CSV-Export') }}
                </a>
            </div>
        </div>


        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Gefahrene Kilometer') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['distance_km'], 2, ',', '.') }} km</div>
            </div>
            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Erstattung') }}</div>
                <div class="text-2xl font-semibold">{{ number_format($totals['reimbursement'], 2, ',', '.') }} €</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Datum') }}</th>
                        <th>{{ __('Von') }}</th>
                        <th>{{ __('Nach') }}</th>
                        <th class="text-right">{{ __('km') }}</th>
                        <th>{{ __('Fahrzeug') }}</th>
                        <th class="text-right">{{ __('Erstattung') }}</th>
                        <th>{{ __('Zweck') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->date?->format('d.m.Y') }}</td>
                            <td>{{ $log->from_address }}</td>
                            <td>{{ $log->to_address }}</td>
                            <td class="text-right">
                                {{ number_format((float) $log->distance_km, 2, ',', '.') }}
                                @if ($log->round_trip)
                                    <span class="badge badge-ghost badge-xs ml-1">{{ __('hin/rück') }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-ghost badge-sm">{{ __($log->vehicle) }}</span>
                            </td>
                            <td class="text-right">
                                {{ number_format((float) $log->reimbursement_total, 2, ',', '.') }} €
                            </td>
                            <td class="max-w-xs truncate">{{ $log->purpose }}</td>
                            <td class="text-right">
                                <a href="{{ route('travel-logs.edit', $log) }}" class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                                <form method="POST" action="{{ route('travel-logs.destroy', $log) }}" class="inline"
                                      onsubmit="return confirm('{{ __('Fahrt wirklich löschen?') }}');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-base-content/60">{{ __('Keine Fahrten im gewählten Zeitraum.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
@endsection
