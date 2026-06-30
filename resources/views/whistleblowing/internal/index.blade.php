@extends('layouts.app')

@section('title', __('Hinweisgeber-Meldungen'))
@section('nav-title', __('Hinweisgeber-Meldungen'))

@section('content')
    <x-index-page :subtitle="__('Eingegangene Hinweisgeber-Meldungen einsehen und bearbeiten.')">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Fallnummer') }}</x-table.th>
                        <x-table.th>{{ __('Kategorie') }}</x-table.th>
                        <x-table.th>{{ __('Status') }}</x-table.th>
                        <x-table.th>{{ __('Priorität') }}</x-table.th>
                        <x-table.th>{{ __('Eingang bis') }}</x-table.th>
                        <x-table.th>{{ __('Rückmeldung bis') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($cases as $case)
                    <tr>
                        <td>
                            <a class="link" href="{{ route('whistleblowing.internal.show', $case) }}">
                                {{ $case->case_number }}
                            </a>
                        </td>
                        <td>{{ __('whistleblowing.category.' . $case->category->value) }}</td>
                        <td>{{ __('whistleblowing.status.' . $case->status->value) }}</td>
                        <td>{{ $case->priority->value }}</td>
                        <td>{{ optional($case->acknowledgement_due_at)->format('d.m.Y') }}</td>
                        <td>{{ optional($case->feedback_due_at)->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" :title="__('Keine Meldungen.')" />
                @endforelse
            </x-table>
        </x-card>

        <x-pagination :paginator="$cases" standing />
    </x-index-page>
@endsection
