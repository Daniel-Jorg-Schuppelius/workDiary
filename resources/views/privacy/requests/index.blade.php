@extends('layouts.app')

@section('title', __('Betroffenenanfragen'))

@section('content')
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('Betroffenenanfragen') }}</h1>
            <a href="{{ route('dataprotection.requests.create') }}" class="btn btn-primary btn-sm">{{ __('Neue Anfrage') }}</a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Nummer') }}</th>
                        <th>{{ __('Art') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Frist') }}</th>
                        <th>{{ __('Zuständig') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr class="hover">
                            <td><a class="link" href="{{ route('dataprotection.requests.show', $r) }}">{{ $r->request_number }}</a></td>
                            <td>{{ $r->type->label() }}</td>
                            <td>
                                <span class="badge {{ $r->isOverdue() ? 'badge-error' : 'badge-ghost' }}">{{ $r->status->label() }}</span>
                            </td>
                            <td class="{{ $r->isOverdue() ? 'text-error font-semibold' : '' }}">{{ $r->deadline_at?->format('d.m.Y') }}</td>
                            <td>{{ $r->assignedUser?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-base-content/60 py-6">{{ __('Keine Anfragen.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $requests->links() }}
    </div>
@endsection
