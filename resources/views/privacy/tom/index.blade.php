{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('TOM-Katalog'))
@section('nav-title', __('Technische & organisatorische Maßnahmen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
    <x-index-page overflow="clip" :subtitle="__('Technische und organisatorische Maßnahmen dokumentieren und prüfen.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('dataprotection.tom.create')"
                        show-label>{{ __('Neue Maßnahme') }}</x-icon-btn>
        </x-slot:actions>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('Maßnahme') }}</x-table.th>
                    <x-table.th>{{ __('Bereich') }}</x-table.th>
                    <x-table.th>{{ __('Status') }}</x-table.th>
                    <x-table.th>{{ __('Review fällig') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($measures as $m)
                <tr class="hover">
                    <td><a class="link" href="{{ route('dataprotection.tom.show', $m) }}">{{ $m->name }}</a></td>
                    <td>{{ $m->category->label() }}</td>
                    <td><x-status-badge tone="ghost" size="sm">{{ $m->implementation_status->label() }}</x-status-badge></td>
                    <td class="{{ $m->isReviewOverdue() ? 'text-error font-semibold' : '' }}">{{ $m->next_review_at?->format('d.m.Y') ?? '—' }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" :title="__('Noch keine Maßnahmen erfasst.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$measures" standing />
    </x-index-page>
@endsection
