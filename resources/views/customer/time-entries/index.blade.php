@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('Zeiterfassungen') }}</h1>
    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Datum') }}</x-table.th>
                <x-table.th>{{ __('Projekt') }}</x-table.th>
                <x-table.th>{{ __('Mitarbeiter') }}</x-table.th>
                <x-table.th>{{ __('Beschreibung') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($entries as $entry)
            <tr>
                <td class="whitespace-nowrap">{{ optional($entry->start_at)->fdatetime() }}</td>
                <td>{{ $entry->project?->name }}</td>
                <td>{{ $entry->user?->name }}</td>
                <td>{{ $entry->description }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('Keine Zeiterfassungen vorhanden.')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$entries" />
@endsection
