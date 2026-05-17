@extends('layouts.app')

@section('title', __('Fahrtenbuch'))

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :title="__('Fahrtenbuch')"
                            :subtitle="__('Erfasste Fahrten') . ' — ' . $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y')">
                {{ __('Zeitraum übernommen aus dem Header. Mit der Auswahl oben links wechseln.') }}
                <x-slot:actions>
                    <a href="{{ route('travel-logs.create') }}" data-entry-modal-trigger class="btn btn-sm btn-primary">
                        <x-icon name="add" /> {{ __('Neue Fahrt') }}
                    </a>
                    <a href="{{ route('travel-logs.export', array_merge(request()->query(), ['from' => $from->toDateString(), 'to' => $to->toDateString()])) }}"
                       class="btn btn-sm btn-ghost">
                        <x-icon name="download" /> {{ __('CSV-Export') }}
                    </a>
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>


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

        <x-card padding="p-0">
            <div class="overflow-x-auto">
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
                                <a href="{{ route('travel-logs.edit', $log) }}" data-entry-modal-trigger class="btn btn-xs btn-ghost">{{ __('Bearbeiten') }}</a>
                                <form method="POST" action="{{ route('travel-logs.destroy', $log) }}" class="inline"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Fahrt wirklich löschen?') }}"
                                      data-confirm-label="{{ __('Löschen') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-ghost text-error">{{ __('Löschen') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0"><x-empty-state :compact="true" :title="__('Keine Fahrten im gewählten Zeitraum')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </x-card>

        {{ $logs->links() }}
    </x-page-shell>
@endsection
