{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Alle Verbesserungsmaßnahmen mit Statuspflege (Feature 071) — das Dashboard zeigt nur die jüngsten.
     Erwartet: $measures, $status, $sort, $dir, $canManage --}}
@extends('layouts.app')

@section('title', __('Maßnahmenregister'))
@section('nav-title', __('Maßnahmenregister'))
@include('partials.page-fill')

@section('content')
<x-index-page overflow="clip" back-route="sustainability.index" :back-label="__('Nachhaltigkeit')"
              :subtitle="__('Alle Verbesserungsmaßnahmen mit Status, Frist und Wirksamkeit.')">
    <x-filter-bar :action="route('sustainability.measures.index')" :reset="route('sustainability.measures.index')">
        <select name="status" class="select select-sm select-bordered w-60 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            <option value="open" @selected($status === 'open')>{{ __('Offen (noch umzusetzen)') }}</option>
            @foreach (\App\Enums\Sustainability\SustainabilityMeasureStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <x-table scroll="flex" :pinRows="true" :zebra="true" size="sm" table-sort="server"
             :route="route('sustainability.measures.index')" :current-sort="$sort" :current-dir="$dir"
             :sort-params="request()->except(['sort', 'dir', 'page'])">
        <x-slot:head>
            <tr>
                <x-table.th sort="title">{{ __('Maßnahme') }}</x-table.th>
                <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                <th>{{ __('Aufwand') }}</th>
                <th>{{ __('Verantwortlich') }}</th>
                <x-table.th sort="due_on">{{ __('Fällig am') }}</x-table.th>
                <th>{{ __('Wirksamkeit') }}</th>
                <x-table.th sort="created" default="desc">{{ __('Erstellt am') }}</x-table.th>
                <th class="text-right">{{ __('Status ändern') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($measures as $measure)
            <tr class="hover">
                <td>
                    <div class="font-medium">{{ $measure->title }}</div>
                    @if ($measure->expected_impact)
                        <div class="text-xs text-muted">{{ $measure->expected_impact }}</div>
                    @endif
                </td>
                <td><x-status-badge size="md" outline>{{ $measure->status->label() }}</x-status-badge></td>
                <td>{{ __('values.' . $measure->effort) }}</td>
                <td>{{ $measure->responsible?->name ?? '—' }}</td>
                <td>{{ $measure->due_on?->fdate() ?? '—' }}</td>
                <td>
                    @if ($measure->effectiveness)
                        <x-status-badge size="md" :tone="$measure->effectiveness === 'effective' ? 'success' : 'warning'">{{ __('values.' . $measure->effectiveness) }}</x-status-badge>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $measure->created_at?->fdate() ?? '—' }}</td>
                <td class="text-right">
                    @if ($canManage)
                        @include('sustainability._measure_status_form', ['measure' => $measure, 'origin' => 'list'])
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="task_alt" :colspan="8" :title="__('Keine Maßnahmen.')" compact />
        @endforelse
    </x-table>

    <x-pagination :paginator="$measures" standing />
</x-index-page>
@endsection
