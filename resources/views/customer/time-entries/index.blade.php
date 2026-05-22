@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('Zeiterfassungen') }}</h1>
    @if ($entries->isEmpty())
        <div class="bg-base-100 border border-base-300 rounded p-6 text-center text-base-content/60">
            {{ __('Keine Zeiterfassungen vorhanden.') }}
        </div>
    @else
        <table class="w-full bg-base-100 border border-base-300 rounded text-sm">
            <thead>
                <tr class="border-b border-base-300">
                    <th class="text-left p-2">{{ __('Datum') }}</th>
                    <th class="text-left p-2">{{ __('Projekt') }}</th>
                    <th class="text-left p-2">{{ __('Mitarbeiter') }}</th>
                    <th class="text-left p-2">{{ __('Beschreibung') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr class="border-b border-base-200">
                        <td class="p-2 whitespace-nowrap">{{ optional($entry->start_at)->format('d.m.Y H:i') }}</td>
                        <td class="p-2">{{ $entry->project?->name }}</td>
                        <td class="p-2">{{ $entry->user?->name }}</td>
                        <td class="p-2">{{ $entry->description }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
@endsection
