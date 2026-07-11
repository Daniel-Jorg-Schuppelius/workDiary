@extends('customer.layout')

@section('title', __('Meine Leihgeräte'))

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ __('Meine Leihgeräte') }}</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($cases->isEmpty())
        <p class="text-sm text-base-content/60">{{ __('Keine Verleihvorgänge vorhanden.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Nummer') }}</th>
                        <th>{{ __('Geräte') }}</th>
                        <th>{{ __('Zeitraum') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cases as $case)
                        <tr>
                            <td><a class="link font-mono" href="{{ route('customer.rentals.show', $case) }}">{{ $case->number }}</a></td>
                            <td>{{ $case->caseAssets->map(fn($ca) => $ca->asset?->name)->filter()->implode(', ') ?: '—' }}</td>
                            <td>{{ $case->starts_at->fdate() }} – {{ $case->ends_at->fdate() }}</td>
                            <td><span class="badge badge-outline">{{ $case->status->label() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-pagination :paginator="$cases" />
    @endif
</div>
@endsection
