{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('domain.section.accounting') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('domain.section.accounting'))

@section('content')
<x-index-page overflow="clip" :subtitle="__('domain.accounting.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="dns" size="sm" :href="route('domains.index')" show-label>{{ __('domain.title.index') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('domains.accounting')" :reset="route('domains.accounting')">
        <x-date-range name-from="from" name-to="to" :from="$filters['from'] ?? null" :to="$filters['to'] ?? null" />
        <input type="text" name="type" value="{{ $filters['type'] ?? '' }}" class="input input-sm input-bordered w-40 shrink-0"
               placeholder="{{ __('domain.accounting.type') }}" aria-label="{{ __('domain.accounting.type') }}">
    </x-filter-bar>

    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>{{ __('domain.accounting.date') }}</th>
                    <th>{{ __('domain.accounting.type') }}</th>
                    <th>{{ __('domain.accounting.description') }}</th>
                    <th>{{ __('domain.field.customer') }}</th>
                    <th class="text-right">{{ __('domain.accounting.net') }}</th>
                    <th class="text-right">{{ __('domain.accounting.tax') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="tabular-nums">{{ $entry->entry_date?->format('d.m.Y') ?? '—' }}</td>
                        <td>{{ $entry->type ?? '—' }}</td>
                        <td>{{ $entry->description ?? '—' }}</td>
                        <td>{{ $entry->customer?->name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $entry->net_amount !== null ? number_format((float) $entry->net_amount, 2, ',', '.') : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $entry->tax_amount !== null ? number_format((float) $entry->tax_amount, 2, ',', '.') : '—' }}</td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" :title="__('domain.accounting.empty')" compact />
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$entries" standing />
</x-index-page>
@endsection
