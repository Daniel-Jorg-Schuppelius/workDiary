@extends('layouts.app')

@section('title', __('Hinweisgeber-Meldungen'))

@section('content')
    <div class="p-4 space-y-4">
        <h1 class="text-xl font-semibold">{{ __('Hinweisgeber-Meldungen') }}</h1>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Fallnummer') }}</th>
                        <th>{{ __('Kategorie') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Prioritaet') }}</th>
                        <th>{{ __('Eingang bis') }}</th>
                        <th>{{ __('Rueckmeldung bis') }}</th>
                    </tr>
                </thead>
                <tbody>
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
                        <tr><td colspan="6">{{ __('Keine Meldungen.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $cases->links() }}
    </div>
@endsection
