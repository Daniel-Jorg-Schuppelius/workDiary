@extends('layouts.app')
@section('title', __('Dienstleister'))
@section('content')
    <div class="p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ __('Dienstleister & Vertragspartner') }}</h1>
            <div class="flex gap-2">
                <a href="{{ route('dataprotection.agreements.index') }}" class="btn btn-sm">{{ __('AVV-Register') }}</a>
                <a href="{{ route('dataprotection.processors.create') }}" class="btn btn-primary btn-sm">{{ __('Neuer Dienstleister') }}</a>
            </div>
        </div>
        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Rolle') }}</th><th>{{ __('Ort') }}</th><th>{{ __('Drittland') }}</th><th>{{ __('AVV') }}</th></tr></thead>
                <tbody>
                    @forelse ($processors as $p)
                        <tr class="hover">
                            <td><a class="link" href="{{ route('dataprotection.processors.show', $p) }}">{{ $p->name }}</a></td>
                            <td>{{ $p->role->label() }}</td>
                            <td>{{ $p->location ?? '—' }}</td>
                            <td>{{ $p->third_country ? __('ja') : '—' }}</td>
                            <td>{{ $p->agreements_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-base-content/60 py-6">{{ __('Keine Dienstleister erfasst.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $processors->links() }}
    </div>
@endsection
