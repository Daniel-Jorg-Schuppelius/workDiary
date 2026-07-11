@extends('customer.layout')

@section('title', $case->number)

@section('content')
<div class="space-y-4">
    <h1 class="text-xl font-semibold">{{ __('Verleihvorgang :number', ['number' => $case->number]) }}</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="badge badge-outline">{{ $case->status->label() }}</span>
        <span>{{ $case->starts_at->fdatetime() }} – {{ $case->ends_at->fdatetime() }}</span>
    </div>

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>{{ __('Gerät') }}</th><th>{{ __('Status') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($case->caseAssets as $caseAsset)
                    <tr>
                        <td>{{ $caseAsset->asset->name ?? '—' }}</td>
                        <td><span class="badge badge-outline">{{ __("values.{$caseAsset->status}") }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="text-lg font-medium">{{ __('Übergaben') }}</h2>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr><th>{{ __('Zeitpunkt') }}</th><th>{{ __('Zubehör') }}</th><th>{{ __('Bestätigung') }}</th></tr>
            </thead>
            <tbody>
                @forelse ($case->handoverReports as $report)
                    <tr>
                        <td>{{ $report->reported_at->fdatetime() }}</td>
                        <td>{{ $report->accessoryItems->map(fn($i) => $i->label)->implode(', ') ?: '—' }}</td>
                        <td>
                            @if ($report->portal_confirmed_at !== null)
                                {{ __('bestätigt am :date', ['date' => $report->portal_confirmed_at->fdatetime()]) }}
                            @else
                                <form method="POST" action="{{ route('customer.rentals.confirm', [$case, $report]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary">{{ __('Übergabe bestätigen') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-sm text-base-content/60">{{ __('Noch keine Übergabe.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
