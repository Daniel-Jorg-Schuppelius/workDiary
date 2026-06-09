@extends('layouts.app')
@section('title', __('AVV-Register'))
@section('content')
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('Auftragsverarbeitungsverträge') }}</h1>
            <a href="{{ route('dataprotection.processors.index') }}" class="btn btn-sm">{{ __('Dienstleister') }}</a>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <p class="text-sm text-base-content/60">{{ __('Neue AVV werden auf der jeweiligen Dienstleister-Seite angelegt.') }}</p>

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Titel') }}</th><th>{{ __('Dienstleister') }}</th><th>{{ __('Version') }}</th><th>{{ __('Status') }}</th><th>{{ __('Gültig bis') }}</th><th>{{ __('Review') }}</th></tr></thead>
                <tbody>
                    @forelse ($agreements as $a)
                        <tr class="hover">
                            <td><a class="link" href="{{ route('dataprotection.agreements.show', $a) }}">{{ $a->title }}</a></td>
                            <td>{{ $a->processor?->name ?? '—' }}</td>
                            <td>v{{ $a->version }}</td>
                            <td><span class="badge badge-ghost">{{ $a->status->label() }}</span></td>
                            <td>{{ $a->valid_until?->format('d.m.Y') ?? '—' }}</td>
                            <td class="{{ $a->isReviewOverdue() ? 'text-error font-semibold' : '' }}">{{ $a->review_due_at?->format('d.m.Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-base-content/60 py-6">{{ __('Keine Verträge erfasst.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $agreements->links() }}
    </div>
@endsection
