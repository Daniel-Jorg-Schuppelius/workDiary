@extends('layouts.app')
@section('title', __('gaeb.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('gaeb.title'))

@section('content')
<x-index-page :subtitle="__('gaeb.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="upload" tone="primary" size="sm" data-entry-modal-trigger
                    :href="route('bill-of-quantities.import-form')" show-label>{{ __('gaeb.import_button') }}</x-icon-btn>
    </x-slot:actions>

    @if (session('gaebErrors'))
        <div class="alert alert-error mb-4">
            <div>
                <ul class="list-disc list-inside text-sm">
                    @foreach (session('gaebErrors') as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($bills->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">request_quote</span>'
                       :title="__('gaeb.empty')" />
    @else
        <x-card padding="p-0">
            <x-table>
                <x-slot:head>
                    <th>{{ __('gaeb.columns.name') }}</th>
                    <th>{{ __('gaeb.columns.project') }}</th>
                    <th>{{ __('gaeb.columns.phase') }}</th>
                    <th>{{ __('gaeb.columns.version') }}</th>
                    <th class="text-right">{{ __('gaeb.columns.items') }}</th>
                </x-slot:head>
                @foreach ($bills as $bill)
                    <tr>
                        <td><a href="{{ route('bill-of-quantities.show', $bill) }}" class="link link-hover font-medium">{{ $bill->name }}</a></td>
                        <td>{{ $bill->project?->name ?: '—' }}</td>
                        <td>{{ $bill->phase?->label() ?: '—' }}</td>
                        <td class="text-sm opacity-70">{{ $bill->gaeb_version ?: '—' }}</td>
                        <td class="text-right tabular-nums">{{ $bill->items_count }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <div class="mt-4">{{ $bills->links() }}</div>
    @endif
</x-index-page>
@endsection
