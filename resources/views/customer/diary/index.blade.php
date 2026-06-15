@extends('customer.layout')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">{{ __('Auftragsbuch') }}</h1>
    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Datum') }}</x-table.th>
                <x-table.th>{{ __('Titel') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($entries as $entry)
            <tr>
                <td class="whitespace-nowrap">{{ optional($entry->start_at)->fdate() }}</td>
                <td>{{ $entry->title }}</td>
                <td>{{ $entry->status }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="3" :title="__('Keine Einträge vorhanden.')" />
        @endforelse
    </x-table>
    <x-pagination :paginator="$entries" />
@endsection
