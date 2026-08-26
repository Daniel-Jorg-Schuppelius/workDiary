{{--
  Created on   : Sun Jun 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('gaeb.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('gaeb.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('gaeb.subtitle')">
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
        <x-empty-state framed icon="request_quote"
                       :title="__('gaeb.empty')" />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('gaeb.columns.name') }}</th>
                    <th>{{ __('gaeb.columns.project') }}</th>
                    <th>{{ __('gaeb.columns.phase') }}</th>
                    <th>{{ __('gaeb.columns.version') }}</th>
                    <th class="text-right">{{ __('gaeb.columns.items') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($bills as $bill)
                <tr class="hover">
                    <td><a href="{{ route('bill-of-quantities.show', $bill) }}" class="link link-hover font-medium">{{ $bill->name }}</a></td>
                    <td>{{ $bill->project?->name ?: '—' }}</td>
                    <td>{{ $bill->phase?->label() ?: '—' }}</td>
                    <td class="text-sm opacity-70">{{ $bill->gaeb_version ?: '—' }}</td>
                    <td class="text-right tabular-nums">{{ $bill->items_count }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$bills" standing />
    @endif
</x-index-page>
@endsection
