@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('Auftragsbuch') }}</h1>
    @if ($entries->isEmpty())
        <div class="bg-base-100 border border-base-300 rounded p-6 text-center text-base-content/60">
            {{ __('Keine Einträge vorhanden.') }}
        </div>
    @else
        <table class="w-full bg-base-100 border border-base-300 rounded text-sm">
            <thead>
                <tr class="border-b border-base-300">
                    <th class="text-left p-2">{{ __('Datum') }}</th>
                    <th class="text-left p-2">{{ __('Titel') }}</th>
                    <th class="text-left p-2">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr class="border-b border-base-200">
                        <td class="p-2 whitespace-nowrap">{{ optional($entry->start_at)->fdate() }}</td>
                        <td class="p-2">{{ $entry->title }}</td>
                        <td class="p-2">{{ $entry->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
@endsection
