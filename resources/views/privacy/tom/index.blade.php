@extends('layouts.app')
@section('title', __('TOM-Katalog'))
@section('content')
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('Technische & organisatorische Maßnahmen') }}</h1>
            <a href="{{ route('dataprotection.tom.create') }}" class="btn btn-primary btn-sm">{{ __('Neue Maßnahme') }}</a>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Maßnahme') }}</th><th>{{ __('Bereich') }}</th><th>{{ __('Status') }}</th><th>{{ __('Review fällig') }}</th></tr></thead>
                <tbody>
                    @forelse ($measures as $m)
                        <tr class="hover">
                            <td><a class="link" href="{{ route('dataprotection.tom.show', $m) }}">{{ $m->name }}</a></td>
                            <td>{{ $m->category->label() }}</td>
                            <td><span class="badge badge-ghost">{{ $m->implementation_status->label() }}</span></td>
                            <td class="{{ $m->isReviewOverdue() ? 'text-error font-semibold' : '' }}">{{ $m->next_review_at?->format('d.m.Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-base-content/60 py-6">{{ __('Noch keine Maßnahmen erfasst.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $measures->links() }}
    </div>
@endsection
