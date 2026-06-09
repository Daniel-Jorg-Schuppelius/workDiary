@extends('layouts.app')

@section('title', __('Verarbeitungstätigkeiten'))

@section('content')
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('Verzeichnis von Verarbeitungstätigkeiten') }}</h1>
            <div class="flex gap-2">
                <a href="{{ route('dataprotection.activities.export') }}" class="btn btn-sm">{{ __('JSON') }}</a>
                <a href="{{ route('dataprotection.activities.export', ['format' => 'csv']) }}" class="btn btn-sm">{{ __('CSV') }}</a>
                <a href="{{ route('dataprotection.activities.export', ['format' => 'print']) }}" target="_blank" class="btn btn-sm">{{ __('Druck') }}</a>
                <a href="{{ route('dataprotection.activities.create') }}" class="btn btn-primary btn-sm">{{ __('Neue Tätigkeit') }}</a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Tätigkeit') }}</th>
                        <th>{{ __('Rolle') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Review fällig') }}</th>
                        <th>{{ __('DSFA') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $a)
                        <tr class="hover">
                            <td><a class="link" href="{{ route('dataprotection.activities.show', $a) }}">{{ $a->name }}</a></td>
                            <td>{{ $a->controller_role->label() }}</td>
                            <td><span class="badge badge-ghost">{{ $a->status->label() }}</span></td>
                            <td class="{{ $a->isReviewOverdue() ? 'text-error font-semibold' : '' }}">{{ $a->review_due_at?->format('d.m.Y') ?? '—' }}</td>
                            <td>{{ $a->dsfa_required ? __('ja') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-base-content/60 py-6">{{ __('Noch keine Verarbeitungstätigkeiten.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $activities->links() }}
    </div>
@endsection
