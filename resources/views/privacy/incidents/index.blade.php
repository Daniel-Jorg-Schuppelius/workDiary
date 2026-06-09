@extends('layouts.app')
@section('title', __('Datenschutzvorfälle'))
@section('content')
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('Datenschutzvorfälle') }}</h1>
            <a href="{{ route('dataprotection.incidents.create') }}" class="btn btn-error btn-sm">{{ __('Vorfall melden') }}</a>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Nummer') }}</th><th>{{ __('Art') }}</th><th>{{ __('Status') }}</th><th>{{ __('72-h-Frist') }}</th><th>{{ __('Zuständig') }}</th></tr></thead>
                <tbody>
                    @forelse ($incidents as $i)
                        <tr class="hover">
                            <td><a class="link" href="{{ route('dataprotection.incidents.show', $i) }}">{{ $i->incident_number }}</a></td>
                            <td>{{ $i->type->label() }}</td>
                            <td><span class="badge {{ $i->isDeadlineBreached() ? 'badge-error' : 'badge-ghost' }}">{{ $i->status->label() }}</span></td>
                            <td class="{{ $i->isDeadlineBreached() ? 'text-error font-semibold' : '' }}">{{ $i->authority_deadline_at?->format('d.m.Y H:i') ?? '—' }}</td>
                            <td>{{ $i->assignedUser?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-base-content/60 py-6">{{ __('Keine Vorfälle erfasst.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $incidents->links() }}
    </div>
@endsection
