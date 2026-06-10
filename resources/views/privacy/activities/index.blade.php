@extends('layouts.app')

@section('title', __('Verarbeitungstätigkeiten'))
@section('nav-title', __('Verzeichnis von Verarbeitungstätigkeiten'))

@section('content')
    <x-index-page :subtitle="__('Verarbeitungstätigkeiten dokumentieren, prüfen und freigeben.')">
        <x-slot:actions>
            <x-icon-btn icon="download" tone="ghost" size="sm"
                        :href="route('dataprotection.activities.export')"
                        show-label>{{ __('JSON') }}</x-icon-btn>
            <x-icon-btn icon="download" tone="ghost" size="sm"
                        :href="route('dataprotection.activities.export', ['format' => 'csv'])"
                        show-label>{{ __('CSV') }}</x-icon-btn>
            <x-icon-btn icon="print" tone="ghost" size="sm"
                        :href="route('dataprotection.activities.export', ['format' => 'print'])"
                        target="_blank"
                        show-label>{{ __('Druck') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        :href="route('dataprotection.activities.create')"
                        show-label>{{ __('Neue Tätigkeit') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <tr>
                        <x-table.th>{{ __('Tätigkeit') }}</x-table.th>
                        <x-table.th>{{ __('Rolle') }}</x-table.th>
                        <x-table.th>{{ __('Status') }}</x-table.th>
                        <x-table.th>{{ __('Review fällig') }}</x-table.th>
                        <x-table.th>{{ __('DSFA') }}</x-table.th>
                    </tr>
                </x-slot:head>
                @forelse ($activities as $a)
                    <tr class="hover">
                        <td><a class="link" href="{{ route('dataprotection.activities.show', $a) }}">{{ $a->name }}</a></td>
                        <td>{{ $a->controller_role->label() }}</td>
                        <td><x-status-badge tone="ghost" size="sm">{{ $a->status->label() }}</x-status-badge></td>
                        <td class="{{ $a->isReviewOverdue() ? 'text-error font-semibold' : '' }}">{{ $a->review_due_at?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $a->dsfa_required ? __('ja') : '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="5" :title="__('Noch keine Verarbeitungstätigkeiten.')" />
                @endforelse
            </x-table>
        </x-card>

        <x-pagination :paginator="$activities" />
    </x-index-page>
@endsection
